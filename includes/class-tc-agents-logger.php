<?php
/**
 * Audit Logger & Telemetry Keeper.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Logger {

	/**
	 * Log an event to the audit log table.
	 *
	 * @param string $event_type Event name e.g. 'kill_switch_tripped', 'failover_triggered', 'tool_called'.
	 * @param string $severity   'info', 'warning', 'error', 'critical'.
	 * @param mixed  $details    Array or string details.
	 * @param string $agent_id   Agent persona slug.
	 * @param string $channel    'web', 'whatsapp', 'voice', 'admin'.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log( $event_type, $severity = 'info', $details = array(), $agent_id = '', $channel = '' ) {
		global $wpdb;
		if ( empty( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}
		$table = $wpdb->prefix . 'tc_agent_audit_log';

		$details_str = is_string( $details ) ? $details : wp_json_encode( $details );

		$res = $wpdb->insert(
			$table,
			array(
				'event_type' => sanitize_text_field( $event_type ),
				'severity'   => sanitize_text_field( $severity ),
				'agent_id'   => sanitize_text_field( $agent_id ),
				'channel'    => sanitize_text_field( $channel ),
				'details'    => $details_str,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $res ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve audit logs with pagination and filters.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_audit_log';

		$defaults = array(
			'limit'    => 50,
			'offset'   => 0,
			'severity' => '',
			'channel'  => '',
			'search'   => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['severity'] ) ) {
			$where[]  = 'severity = %s';
			$params[] = $args['severity'];
		}
		if ( ! empty( $args['channel'] ) ) {
			$where[]  = 'channel = %s';
			$params[] = $args['channel'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(event_type LIKE %s OR details LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $search_term;
			$params[] = $search_term;
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		if ( ! empty( $params ) ) {
			$prepared = $wpdb->prepare( $query, $params );
		} else {
			$prepared = $query;
		}

		return $wpdb->get_results( $prepared, ARRAY_A ) ?: array();
	}

	/**
	 * Count total logs for pagination.
	 */
	public static function count_logs( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_audit_log';

		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['severity'] ) ) {
			$where[]  = 'severity = %s';
			$params[] = $args['severity'];
		}
		if ( ! empty( $args['channel'] ) ) {
			$where[]  = 'channel = %s';
			$params[] = $args['channel'];
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $table WHERE $where_clause";

		if ( ! empty( $params ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $query, $params ) );
		}
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Retrieve recent system errors & warnings.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_errors( $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_audit_log';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event_type, severity, agent_id, channel, details, created_at
				 FROM $table
				 WHERE severity IN ('error', 'critical', 'warning')
				 ORDER BY id DESC LIMIT %d",
				max( 1, (int) $limit )
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Retrieve stream of recent live activities across all channels.
	 *
	 * @param int $limit
	 * @param string $category 'all', 'leads', 'inquiries', 'system', 'errors'
	 * @return array
	 */
	public static function get_live_activities( $limit = 25, $category = 'all' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_audit_log';

		$where = array( '1=1' );
		if ( 'leads' === $category ) {
			$where[] = "event_type IN ('lead_captured', 'fluentcrm_synced_lead', 'twentycrm_synced')";
		} elseif ( 'inquiries' === $category ) {
			$where[] = "event_type IN ('human_handoff_requested', 'voice_call_queued_jio_sim', 'mobile_call_logged', 'visitor_landing')";
		} elseif ( 'errors' === $category ) {
			$where[] = "severity IN ('error', 'critical', 'warning')";
		} elseif ( 'system' === $category ) {
			$where[] = "event_type IN ('github_push_success', 'github_push_error', 'github_sync_check', 'sequence_step_fired', 'b2b_google_import', 'b2b_scheduled_import')";
		}

		$where_sql = implode( ' AND ', $where );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event_type, severity, agent_id, channel, details, created_at
				 FROM $table
				 WHERE $where_sql
				 ORDER BY id DESC LIMIT %d",
				max( 1, (int) $limit )
			),
			ARRAY_A
		) ?: array();

		$formatted = array();
		foreach ( $rows as $r ) {
			$formatted[] = self::format_activity_row( $r );
		}
		return $formatted;
	}

	/**
	 * Format an audit log entry for human-friendly real-time display.
	 */
	public static function format_activity_row( $row ) {
		$details = json_decode( (string) $row['details'], true );
		if ( ! is_array( $details ) ) {
			$details = array( 'raw' => $row['details'] );
		}

		$icon    = '⚡';
		$title   = ucwords( str_replace( '_', ' ', $row['event_type'] ) );
		$snippet = '';

		switch ( $row['event_type'] ) {
			case 'lead_captured':
				$icon    = '🎯';
				$title   = __( 'New High-Value Lead Captured', 'tripcosmos-agents' );
				$snippet = ( ! empty( $details['name'] ) ? $details['name'] . ' • ' : '' ) . ( $details['phone'] ?? '' );
				break;
			case 'fluentcrm_synced_lead':
				$icon    = '📥';
				$title   = __( 'FluentCRM Contact Synced', 'tripcosmos-agents' );
				$snippet = ( $details['email'] ?? '' ) . ( ! empty( $details['phone'] ) ? ' • ' . $details['phone'] : '' );
				break;
			case 'human_handoff_requested':
				$icon    = '🚨';
				$title   = __( 'Traveler Requested Human Specialist', 'tripcosmos-agents' );
				$snippet = __( 'Routed to WhatsApp VIP support desk', 'tripcosmos-agents' );
				break;
			case 'github_push':
			case 'github_push_success':
				$icon    = '🐙';
				$title   = __( 'Plugin Code Pushed to GitHub', 'tripcosmos-agents' );
				$snippet = 'Commit: ' . ( $details['commit'] ?? '' ) . ' (' . ( $details['branch'] ?? 'main' ) . ')';
				break;
			case 'github_push_error':
				$icon    = '❌';
				$title   = __( 'GitHub Push Failed', 'tripcosmos-agents' );
				$snippet = $details['message'] ?? 'Write access denied';
				break;
			case 'github_sync_check':
				$icon    = '🔄';
				$title   = __( 'GitHub Version Checked', 'tripcosmos-agents' );
				$snippet = 'Status: ' . ( $details['sync_status'] ?? '' ) . ' (Local: v' . ( $details['local'] ?? '' ) . ')';
				break;
			case 'visitor_landing':
				$icon    = '👁️';
				$title   = __( 'Website Visitor Viewing Tour', 'tripcosmos-agents' );
				$snippet = $details['page_title'] ?? 'TripCosmos Package';
				break;
			case 'whatsapp_send_error':
				$icon    = '⚠️';
				$title   = __( 'WhatsApp Delivery Error', 'tripcosmos-agents' );
				$snippet = $details['message'] ?? ( $details['raw'] ?? 'Failed to send WhatsApp message' );
				break;
			case 'provider_failed':
			case 'all_providers_failed':
				$icon    = '⚠️';
				$title   = __( 'AI Router Provider Failover', 'tripcosmos-agents' );
				$snippet = 'Provider failed: ' . ( $details['provider'] ?? 'unknown' );
				break;
			case 'sequence_step_fired':
				$icon    = '⏱️';
				$title   = __( 'Follow-Up Sequence Step Fired', 'tripcosmos-agents' );
				$snippet = 'Step #' . ( $details['step'] ?? '' ) . ' to ' . ( $details['contact_id'] ?? '' );
				break;
			case 'b2b_google_import':
				$icon    = '🏢';
				$title   = __( 'B2B Travel Agency Imported', 'tripcosmos-agents' );
				$snippet = ( $details['name'] ?? '' ) . ' • ' . ( $details['city'] ?? '' );
				break;
		}

		return array(
			'id'         => (int) $row['id'],
			'icon'       => $icon,
			'title'      => $title,
			'snippet'    => $snippet,
			'severity'   => $row['severity'],
			'channel'    => $row['channel'],
			'event_type' => $row['event_type'],
			'time_ago'   => human_time_diff( strtotime( $row['created_at'] ) ) . ' ago',
			'created_at' => $row['created_at'],
			'details'    => $details,
		);
	}

	/**
	 * Purge error logs.
	 */
	public static function clear_errors() {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_audit_log';
		return $wpdb->query( "DELETE FROM $table WHERE severity IN ('error', 'critical', 'warning')" );
	}
}
