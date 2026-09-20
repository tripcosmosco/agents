<?php
/**
 * Mobile Telephony & Android Companion REST API.
 * Powers Superfone-style Mobile Caller-ID, Call-Logging, and 1-Tap Post-Call Actions.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Mobile_API {

	const NAMESPACE = 'tc-agents/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		// 1. Smart Caller ID Lookup (Displays caller profile when phone rings)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/caller-id',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_caller_id' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 2. Call Log & Recording Ingestion (Syncs call history from phone to CRM)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/call-log',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_call_log' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 3. Post-Call Quick Actions (1-Tap WhatsApp Brochure / Quote Dispatch)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/quick-action',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_quick_action' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 4. Mobile Leads Feed
		register_rest_route(
			self::NAMESPACE,
			'/mobile/leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_leads' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);
	}

	/**
	 * Verify mobile API token from header or query param.
	 */
	public static function verify_token( WP_REST_Request $request ) {
		$token = $request->get_header( 'X-Mobile-Token' ) ?: $request->get_param( 'token' );
		$configured_token = get_option( 'tc_agents_mobile_api_token', '' );

		if ( empty( $configured_token ) ) {
			// If no token set yet, allow administrator or fallback to default secret
			return current_user_can( 'manage_options' ) || ! empty( $token );
		}

		return hash_equals( (string) $configured_token, (string) $token );
	}

	/**
	 * Handle Smart Caller ID.
	 */
	public static function handle_caller_id( WP_REST_Request $request ) {
		$phone = sanitize_text_field( $request->get_param( 'phone' ) ?? '' );
		$clean_phone = preg_replace( '/[^\d]/', '', $phone );

		if ( empty( $clean_phone ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phone parameter is required' ), 400 );
		}

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		// Query by suffix match to handle country codes (+91, etc.)
		$suffix = substr( $clean_phone, -10 );
		$contact = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_contacts WHERE phone LIKE %s LIMIT 1", '%' . $suffix ),
			ARRAY_A
		);

		if ( ! $contact ) {
			return new WP_REST_Response(
				array(
					'ok'    => true,
					'found' => false,
					'phone' => $phone,
					'hint'  => 'New prospective caller. Answer with standard TripCosmos greeting.',
				),
				200
			);
		}

		$meta = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();

		// Fetch memory synthesis if available
		$table_mem = $wpdb->prefix . 'tc_agent_lead_memory';
		$mem = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_mem WHERE contact_id = %d", $contact['id'] ), ARRAY_A );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'found'   => true,
				'contact' => array(
					'id'                  => (int) $contact['id'],
					'name'                => $contact['name'] ?: 'Traveler',
					'phone'               => $contact['phone'],
					'email'               => $contact['email'],
					'stage'               => $contact['stage'],
					'lead_score'          => (int) $contact['score'],
					'deal_value'          => (float) $contact['deal_value'],
					'destination'         => $meta['destination'] ?? 'Varanasi Spiritual Tour',
					'requirements'        => $meta['requirements'] ?? '',
					'ai_summary'          => $mem['summary'] ?? '',
					'next_best_action'    => $mem['next_best_action'] ?? 'Qualify dates, group size, and vehicle/hotel preference.',
				),
			),
			200
		);
	}

	/**
	 * Handle Call Log ingestion from phone.
	 */
	public static function handle_call_log( WP_REST_Request $request ) {
		$params    = $request->get_json_params() ?: $request->get_params();
		$phone     = sanitize_text_field( $params['phone'] ?? '' );
		$call_type = sanitize_text_field( $params['call_type'] ?? 'incoming' ); // incoming, outgoing, missed
		$duration  = (int) ( $params['duration_seconds'] ?? 0 );
		$rec_url   = esc_url_raw( $params['recording_url'] ?? '' );
		$notes     = sanitize_textarea_field( $params['notes'] ?? '' );

		if ( empty( $phone ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phone required' ), 400 );
		}

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$suffix  = substr( preg_replace( '/[^\d]/', '', $phone ), -10 );
		$contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE phone LIKE %s LIMIT 1", '%' . $suffix ), ARRAY_A );

		$contact_id = 0;
		if ( $contact ) {
			$contact_id = (int) $contact['id'];
		}

		TC_Agents_Logger::log(
			'mobile_call_logged',
			'info',
			array(
				'contact_id' => $contact_id,
				'phone'      => $phone,
				'type'       => $call_type,
				'duration'   => $duration,
				'notes'      => $notes,
			)
		);

		// If missed call, trigger instant WhatsApp courtesy auto-reply
		if ( 'missed' === $call_type && ! empty( $phone ) ) {
			$name = $contact['name'] ?? 'Traveler';
			$msg  = sprintf(
				"Namaste %s! 🙏 Sorry we missed your call at TripCosmos Travel Desk. Our Varanasi specialists are currently assisting pilgrims and travelers. How can we help you with your tour, outstation cab, or temple darshan booking?",
				$name
			);
			TC_Integration_WhatsApp::send_message( $phone, $msg );
		}

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'message'    => 'Call log recorded successfully.',
				'contact_id' => $contact_id,
			),
			200
		);
	}

	/**
	 * Handle 1-Tap Post-Call Quick Actions.
	 */
	public static function handle_quick_action( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: $request->get_params();
		$phone  = sanitize_text_field( $params['phone'] ?? '' );
		$action = sanitize_text_field( $params['action'] ?? '' ); // send_brochure, send_gear_guide, send_booking_link

		if ( empty( $phone ) || empty( $action ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phone and action are required' ), 400 );
		}

		$sent = false;
		$msg  = '';

		switch ( $action ) {
			case 'send_brochure':
				$msg = "Namaste! Here is the official TripCosmos Varanasi, Ayodhya & Prayagraj Tour Package Brochure with day-wise itineraries and inclusions: https://tripcosmos.co/varanasi-tour-brochure.pdf 🛕";
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			case 'send_cab_chart':
			case 'send_gear_guide':
				$msg = "Here is our TripCosmos Outstation Cab Rate Chart for Varanasi, Ayodhya, Prayagraj, and Bodhgaya (Swift Dzire, Innova Crysta, and Tempo Traveller): https://tripcosmos.co/cab-fare-chart.pdf 🚗";
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			case 'send_booking_link':
				$msg = "Ready to confirm your tour package or cab booking? You can secure your dates via our official Varanasi booking desk: https://tripcosmos.co/book";
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			default:
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Unknown action' ), 400 );
		}

		return new WP_REST_Response(
			array(
				'ok'      => $sent,
				'action'  => $action,
				'message' => $sent ? 'Quick action dispatched via WhatsApp.' : 'Failed to deliver message.',
			),
			200
		);
	}

	/**
	 * Fetch lightweight leads list for mobile display.
	 */
	public static function handle_get_leads( WP_REST_Request $request ) {
		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$stage = sanitize_text_field( $request->get_param( 'stage' ) ?? '' );
		$where = '1=1';
		$params = array();

		if ( ! empty( $stage ) && 'all' !== $stage ) {
			$where .= ' AND stage = %s';
			$params[] = $stage;
		}

		$query = "SELECT id, name, email, phone, stage, score, deal_value, meta_data, created_at, updated_at FROM $table_contacts WHERE $where ORDER BY updated_at DESC LIMIT 100";
		$rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ) : $wpdb->get_results( $query, ARRAY_A );

		$leads = array();
		foreach ( ( $rows ?: array() ) as $row ) {
			$meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true ) ?: array();
			$leads[] = array(
				'id'           => (int) $row['id'],
				'name'         => ! empty( $row['name'] ) ? $row['name'] : 'Traveler',
				'email'        => (string) ( $row['email'] ?? '' ),
				'phone'        => (string) ( $row['phone'] ?? '' ),
				'stage'        => (string) ( $row['stage'] ?: 'inquiry' ),
				'score'        => (int) ( $row['score'] ?? 50 ),
				'deal_value'   => (float) ( $row['deal_value'] ?? 0.0 ),
				'destination'  => ! empty( $meta['destination'] ) ? $meta['destination'] : 'Varanasi Spiritual Tour',
				'requirements' => (string) ( $meta['requirements'] ?? '' ),
				'created_at'   => (string) ( $row['created_at'] ?? '' ),
				'updated_at'   => (string) ( $row['updated_at'] ?? '' ),
			);
		}

		// Also check if any FluentCRM subscribers with phone should be included
		$table_subscribers = $wpdb->prefix . 'fc_subscribers';
		$subscribers_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_subscribers ) );
		if ( $subscribers_table_exists ) {
			$existing_phones = array_column( $leads, 'phone' );
			$subscribers = $wpdb->get_results(
				"SELECT id, first_name, last_name, email, phone, created_at, updated_at FROM $table_subscribers WHERE phone IS NOT NULL AND phone != '' LIMIT 50",
				ARRAY_A
			);
			if ( ! empty( $subscribers ) ) {
				foreach ( $subscribers as $sub ) {
					$clean_sub_phone = preg_replace( '/[^\d]/', '', $sub['phone'] );
					$already_exists = false;
					foreach ( $existing_phones as $ep ) {
						if ( substr( preg_replace( '/[^\d]/', '', $ep ), -10 ) === substr( $clean_sub_phone, -10 ) ) {
							$already_exists = true;
							break;
						}
					}
					if ( ! $already_exists && ( empty( $stage ) || 'all' === $stage || 'inquiry' === $stage ) ) {
						$sub_name = trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) );
						$leads[] = array(
							'id'           => 100000 + (int) $sub['id'],
							'name'         => ! empty( $sub_name ) ? $sub_name : 'CRM Subscriber',
							'email'        => (string) ( $sub['email'] ?? '' ),
							'phone'        => (string) $sub['phone'],
							'stage'        => 'inquiry',
							'score'        => 55,
							'deal_value'   => 12000.0,
							'destination'  => 'Varanasi / Kashi Darshan',
							'requirements' => 'FluentCRM Contact Sync',
							'created_at'   => (string) ( $sub['created_at'] ?? '' ),
							'updated_at'   => (string) ( $sub['updated_at'] ?? '' ),
						);
					}
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true, 'leads' => $leads ), 200 );
	}
}
