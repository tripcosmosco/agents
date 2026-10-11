<?php
/**
 * Privacy: WordPress personal-data exporter/eraser and optional data retention purge.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Privacy {

	const CRON_HOOK = 'tc_agents_retention_purge';

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge_expired' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}
		add_action( 'admin_init', array( __CLASS__, 'add_policy_text' ) );
	}

	public static function add_policy_text() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'TripCosmos Agents',
				wp_kses_post( '<p>The chat assistant stores your messages, name, email and phone number so our travel team can follow up on your inquiry. Voice calls and WhatsApp conversations may be transcribed and stored. Data is processed by third-party AI providers to generate replies and can be exported or erased on request.</p>' )
			);
		}
	}

	public static function register_exporter( $exporters ) {
		$exporters['tripcosmos-agents'] = array(
			'exporter_friendly_name' => __( 'TripCosmos Agents', 'tripcosmos-agents' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['tripcosmos-agents'] = array(
			'eraser_friendly_name' => __( 'TripCosmos Agents', 'tripcosmos-agents' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Contact rows matching an email address.
	 */
	private static function contacts_for_email( $email ) {
		global $wpdb;
		$t = $wpdb->prefix . 'tc_agent_contacts';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s", $email ), ARRAY_A ) ?: array();
	}

	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$items = array();
		foreach ( self::contacts_for_email( $email ) as $c ) {
			$items[] = array(
				'group_id'    => 'tc-agents-contact',
				'group_label' => __( 'Travel Inquiry Profile', 'tripcosmos-agents' ),
				'item_id'     => 'tc-contact-' . $c['id'],
				'data'        => array(
					array( 'name' => 'Name', 'value' => (string) $c['name'] ),
					array( 'name' => 'Email', 'value' => (string) $c['email'] ),
					array( 'name' => 'Phone', 'value' => (string) $c['phone'] ),
					array( 'name' => 'Source', 'value' => (string) $c['source_channel'] ),
					array( 'name' => 'Created', 'value' => (string) $c['created_at'] ),
				),
			);
			$tc = $wpdb->prefix . 'tc_agent_conversations';
			$tm = $wpdb->prefix . 'tc_agent_messages';
			$msgs = $wpdb->get_results(
				$wpdb->prepare( "SELECT m.role, m.content, m.created_at FROM $tm m INNER JOIN $tc c ON c.id = m.conversation_id WHERE c.contact_id = %d AND m.role IN ('user','assistant') ORDER BY m.id ASC LIMIT 2000", $c['id'] ),
				ARRAY_A
			) ?: array();
			foreach ( $msgs as $i => $m ) {
				$items[] = array(
					'group_id'    => 'tc-agents-messages',
					'group_label' => __( 'Chat Messages', 'tripcosmos-agents' ),
					'item_id'     => 'tc-msg-' . $c['id'] . '-' . $i,
					'data'        => array(
						array( 'name' => 'Role', 'value' => $m['role'] ),
						array( 'name' => 'Message', 'value' => $m['content'] ),
						array( 'name' => 'Date', 'value' => $m['created_at'] ),
					),
				);
			}
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email, $page = 1 ) {
		$removed  = 0;
		$messages = array();
		foreach ( self::contacts_for_email( $email ) as $c ) {
			// Cascades to FluentCRM (which registers no eraser of its own); reports copies it can't reach.
			$messages = array_merge( $messages, TC_Contact_Service::delete( (int) $c['id'], true ) );
			$removed++;
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => ! empty( $messages ),
			'messages'       => array_values( array_unique( $messages ) ),
			'done'           => true,
		);
	}

	/**
	 * Delete a contact and everything hanging off it.
	 */
	public static function delete_contact( $contact_id ) {
		global $wpdb;
		$tc  = $wpdb->prefix . 'tc_agent_conversations';
		$tm  = $wpdb->prefix . 'tc_agent_messages';
		$tl  = $wpdb->prefix . 'tc_agent_lead_memory';
		$tct = $wpdb->prefix . 'tc_agent_contacts';
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $tc WHERE contact_id = %d", $contact_id ) );
		$wpdb->delete( $wpdb->prefix . 'tc_agent_activities', array( 'contact_id' => $contact_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'tc_agent_bookings', array( 'contact_id' => $contact_id ), array( '%d' ) );
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( "DELETE FROM $tm WHERE conversation_id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints only.
			$wpdb->query( "DELETE FROM $tc WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->delete( $tl, array( 'contact_id' => $contact_id ), array( '%d' ) );
		$wpdb->delete( $tct, array( 'id' => $contact_id ), array( '%d' ) );
	}

	/**
	 * Daily purge of conversations older than the configured retention (days). 0 = keep forever.
	 */
	public static function purge_expired() {
		$days = (int) get_option( 'tc_agents_retention_days', 0 );
		if ( $days < 30 ) {
			return;
		}
		global $wpdb;
		$tc     = $wpdb->prefix . 'tc_agent_conversations';
		$tm     = $wpdb->prefix . 'tc_agent_messages';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $tc WHERE last_message_at < %s LIMIT 1000", $cutoff ) );
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( "DELETE FROM $tm WHERE conversation_id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM $tc WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			TC_Agents_Logger::log( 'retention_purge', 'info', array( 'conversations' => count( $ids ), 'days' => $days ) );
		}
	}
}
