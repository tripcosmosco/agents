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
}
