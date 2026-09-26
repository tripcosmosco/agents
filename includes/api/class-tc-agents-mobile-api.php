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

		// 5. WhatsApp Inbound Leads Stream (Center WhatsApp Hub)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/whatsapp-leads',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_whatsapp_leads' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 6. Admin Assigns Lead to Manager
		register_rest_route(
			self::NAMESPACE,
			'/mobile/assign-lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assign_lead' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 7. Dynamic Tour Quote & Itinerary Generator
		register_rest_route(
			self::NAMESPACE,
			'/mobile/generate-quote',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_generate_quote' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 8. Multi-Channel Dispatch (Brevo SMS Driver Dispatch & FluentCRM Sync)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/send-dispatch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_send_dispatch' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);
	}

	/**
	 * Get or initialize mobile API secret token.
	 */
	public static function get_api_token() {
		$token = get_option( 'tc_agents_mobile_api_token', '' );
		if ( empty( $token ) ) {
			$token = wp_generate_password( 32, false );
			update_option( 'tc_agents_mobile_api_token', $token, true );
		}
		return $token;
	}

	/**
	 * Verify mobile API token from header or query param.
	 */
	public static function verify_token( WP_REST_Request $request ) {
		$ip = TC_Agents_Security::client_ip();
		if ( TC_Agents_Security::rate_exceeded( 'mobile_auth_fail', $ip, 30 ) ) {
			return new WP_Error( 'tc_rate_limited', 'Too many failed attempts. Try again later.', array( 'status' => 429 ) );
		}

		// Identity of the caller, set here so handlers can trust it (never taken from the request body).
		$request->set_param( '_tc_agent', '' );
		$token = (string) $request->get_header( 'X-Mobile-Token' );

		if ( '' !== $token ) {
			if ( hash_equals( (string) self::get_api_token(), $token ) ) {
				$request->set_param( '_tc_agent', 'master' );
				return true;
			}
			$label = TC_Agents_Security::match_agent_token( $token );
			if ( false !== $label ) {
				$request->set_param( '_tc_agent', $label );
				return true;
			}
		}

		if ( current_user_can( 'manage_options' ) ) {
			$request->set_param( '_tc_agent', 'admin' );
			return true;
		}

		TC_Agents_Security::rate_limit( 'mobile_auth_fail', $ip, 30, 600 );
		return false;
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
				'agent'      => (string) $request->get_param( '_tc_agent' ),
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

	/**
	 * Inbound WhatsApp Leads Stream for Mobile Center Hub.
	 */
	public static function handle_get_whatsapp_leads( WP_REST_Request $request ) {
		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$contacts = $wpdb->get_results(
			"SELECT id, name, phone, email, stage, score, deal_value, meta_data, created_at, updated_at FROM $table_contacts ORDER BY updated_at DESC LIMIT 50",
			ARRAY_A
		);

		$whatsapp_leads = array();
		foreach ( ( $contacts ?: array() ) as $c ) {
			$meta = json_decode( (string) ( $c['meta_data'] ?? '' ), true ) ?: array();
			$assigned_manager = $meta['assigned_manager'] ?? null;
			$last_msg = $meta['last_message'] ?? ( "Inquiring about " . ( $meta['destination'] ?? "Varanasi Tour Package" ) . ". Please send detailed quote & car options." );
			$time_ago = human_time_diff( strtotime( $c['updated_at'] ?: $c['created_at'] ), current_time( 'timestamp' ) ) . ' ago';

			$score = (int) ( $c['score'] ?? 75 );
			if ( $score < 60 ) { $score = 75; }

			$whatsapp_leads[] = array(
				'id'               => (string) $c['id'],
				'customer_name'    => ! empty( $c['name'] ) ? $c['name'] : 'Traveler (' . substr( $c['phone'], -4 ) . ')',
				'phone'            => (string) $c['phone'],
				'last_message'     => (string) $last_msg,
				'time_ago'         => (string) $time_ago,
				'unread_count'     => (int) ( $meta['unread_count'] ?? 0 ),
				'tour_interest'    => ! empty( $meta['destination'] ) ? $meta['destination'] : 'Varanasi 3D2N Spiritual Tour',
				'estimated_budget' => (float) ( $c['deal_value'] > 0 ? $c['deal_value'] : 15000.0 ),
				'assigned_manager' => ! empty( $assigned_manager ) ? $assigned_manager : null,
				'lead_score'       => $score,
				'status'           => ! empty( $assigned_manager ) ? 'assigned' : 'new',
			);
		}

		return new WP_REST_Response( array( 'ok' => true, 'leads' => $whatsapp_leads ), 200 );
	}

	/**
	 * Admin assigns WhatsApp lead to manager and optionally dispatches WhatsApp alert.
	 */
	public static function handle_assign_lead( WP_REST_Request $request ) {
		$params         = $request->get_json_params() ?: $request->get_params();
		$lead_id        = (int) ( $params['lead_id'] ?? 0 );
		$manager_name   = sanitize_text_field( $params['manager_name'] ?? '' );
		$notify_manager = ! empty( $params['notify_manager'] );

		if ( empty( $lead_id ) || empty( $manager_name ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'lead_id and manager_name are required' ), 400 );
		}

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE id = %d", $lead_id ), ARRAY_A );
		if ( ! $contact ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Lead not found' ), 404 );
		}

		$meta = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
		$meta['assigned_manager'] = $manager_name;
		$meta['assigned_at']      = current_time( 'mysql' );
		$meta['assigned_by']      = (string) $request->get_param( '_tc_agent' );

		$wpdb->update(
			$table_contacts,
			array(
				'meta_data'  => wp_json_encode( $meta ),
				'stage'      => 'qualified',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'message'    => "Lead #$lead_id assigned to $manager_name",
				'manager'    => $manager_name,
			),
			200
		);
	}

	/**
	 * Dynamic Tour Package Quote Generator.
	 */
	public static function handle_generate_quote( WP_REST_Request $request ) {
		$params      = $request->get_json_params() ?: $request->get_params();
		$destination = sanitize_text_field( $params['destination'] ?? 'varanasi_3d2n' );
		$tier        = sanitize_text_field( $params['tier'] ?? 'deluxe' );
		$pax         = max( 1, (int) ( $params['pax'] ?? 2 ) );
		$name        = sanitize_text_field( $params['customer_name'] ?? 'Traveler' );
		$dates       = sanitize_text_field( $params['dates'] ?? 'Upcoming Weekend' );
		$rate_type   = sanitize_text_field( $params['rate_type'] ?? 'b2c' ); // 'b2c' or 'b2b'

		$retail_pricing = 14500;
		$net_pricing    = 12000;
		$title          = "3D2N Spiritual Kashi Classical Tour";

		switch ( $destination ) {
			case 'ayodhya_day_trip':
				$title          = "Varanasi to Ayodhya Ram Mandir Same-Day Excursion";
				$retail_pricing = ( 'luxury' === $tier ) ? 7500 : 4500;
				$net_pricing    = ( 'luxury' === $tier ) ? 6200 : 3700;
				$vehicle        = ( 'luxury' === $tier ) ? 'Innova Crysta AC (6+1)' : 'Swift Dzire AC';

				$quote = "🚗 *TripCosmos Ayodhya Ram Janmabhoomi Day Excursion*\n\n" .
						"Namaste {$name} ji! 🙏 Here is your customized private cab quote:\n\n" .
						"• *Vehicle:* {$vehicle}\n" .
						"• *Dates:* {$dates}\n" .
						"• *Travelers:* {$pax} Pax\n" .
						"• *Inclusions:* Fuel, Highway Tolls, Parking, Driver Allowance.\n" .
						"• *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti.\n\n" .
						"💰 *Total All-Inclusive Fare:* ₹" . number_format( $retail_pricing ) . "\n" .
						"🔒 *Token Advance to Block Cab:* ₹1,500 via UPI\n" .
						"👉 *Instant Booking Link:* https://tripcosmos.co/book?ref=ayodhya-" . time();

				$white_label = "🚗 *Ayodhya Ram Janmabhoomi Private Day Excursion Itinerary*\n\n" .
						"Guest Name: {$name} | Travelers: {$pax} Pax\n" .
						"Tour Dates: {$dates}\n\n" .
						"• *Vehicle:* Dedicated {$vehicle} (Varanasi Pick to Drop)\n" .
						"• *Sightseeing Covered:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Dashrath Mahal, Sarayu River Evening Aarti.\n" .
						"• *Inclusions:* Air-conditioned vehicle, all highway tolls, parking charges, verified driver.\n\n" .
						"💰 *Package Price:* ₹" . number_format( $retail_pricing ) . " All-Inclusive";
				break;

			case 'varanasi_prayagraj_ayodhya_4d3n':
				$title          = "4D3N Sacred Triangle (Varanasi, Prayagraj Sangam & Ayodhya)";
				$multiplier     = max( 1, ceil( $pax / 2 ) );
				$retail_pricing = ( 'luxury' === $tier ) ? ( 28000 * $multiplier ) : ( 19500 * $multiplier );
				$net_pricing    = ( 'luxury' === $tier ) ? ( 23500 * $multiplier ) : ( 16000 * $multiplier );
				$hotel          = ( 'luxury' === $tier ) ? '4-Star Luxury Heritage Hotel' : '3-Star Deluxe Hotel near Ghats';
				$vehicle        = ( 'luxury' === $tier ) ? 'Innova Crysta AC' : 'Dedicated AC Sedan';

				$quote = "🌟 *TripCosmos 4D3N Sacred Triangle Pilgrimage Tour*\n\n" .
						"Namaste {$name} ji! 🙏 Here is your comprehensive spiritual itinerary:\n\n" .
						"• *Day 1:* Varanasi Arrival, Hotel Check-in, Dashashwamedh Ghat Evening Ganga Aarti with Reserved Boat Seating.\n" .
						"• *Day 2:* Subah-e-Banaras Sunrise Boat Ride, Kashi Vishwanath Temple Sugam VIP Darshan, Annapurna Mandir, Kaal Bhairav, Sarnath Tour.\n" .
						"• *Day 3:* Early Drive to Prayagraj, Triveni Sangam Holy Snan & Boat, Bade Hanuman Mandir, Anand Bhavan, Drive to Ayodhya & Overnight Hotel.\n" .
						"• *Day 4:* Ayodhya Shri Ram Janmabhoomi VIP Darshan, Hanuman Garhi, Sarayu Ghat Aarti, Return to Varanasi Airport Drop.\n\n" .
						"🏨 *Hotel:* {$hotel} with Daily Breakfast\n" .
						"🚗 *Vehicle:* {$vehicle} throughout\n" .
						"💰 *Total Package Price ({$pax} Pax):* ₹" . number_format( $retail_pricing ) . "\n" .
						"🔒 *Token Advance to Secure Booking:* ₹3,000 via UPI / Card\n" .
						"👉 *Official Booking Link:* https://tripcosmos.co/book?ref=triangle-" . time();

				$white_label = "🌟 *4D3N Sacred Triangle Pilgrimage Itinerary*\n" .
						"(Varanasi • Prayagraj Sangam • Ayodhya Ram Mandir)\n\n" .
						"Guest: {$name} | Travelers: {$pax} Pax | Dates: {$dates}\n\n" .
						"• *Day 1:* Varanasi Arrival, Ghat transfer, Evening Ganga Aarti boat cruise with reserved seating.\n" .
						"• *Day 2:* Sunrise boat cruise, Kashi Vishwanath VIP Darshan Pass, Annapurna Temple, Kaal Bhairav, Sarnath Tour.\n" .
						"• *Day 3:* Triveni Sangam Holy Snan at Prayagraj, Bade Hanuman Mandir, Anand Bhavan, Drive to Ayodhya & Overnight stay.\n" .
						"• *Day 4:* Ayodhya Shri Ram Janmabhoomi Darshan, Hanuman Garhi, Sarayu Aarti, Return to Varanasi Drop.\n\n" .
						"🏨 *Accommodation:* {$hotel} (with Breakfast)\n" .
						"🚗 *Transportation:* Private {$vehicle}\n" .
						"💰 *Package Price ({$pax} Pax):* ₹" . number_format( $retail_pricing );
				break;

			case 'varanasi_3d2n':
			default:
				$title          = "3D2N Spiritual Kashi Tour";
				$multiplier     = max( 1, ceil( $pax / 2 ) );
				$retail_pricing = ( 'luxury' === $tier ) ? ( 22500 * $multiplier ) : ( 14500 * $multiplier );
				$net_pricing    = ( 'luxury' === $tier ) ? ( 18500 * $multiplier ) : ( 12000 * $multiplier );
				$hotel          = ( 'luxury' === $tier ) ? '4-Star Premium Hotel with Swimming Pool' : '3-Star Deluxe Hotel with Breakfast near Ghats';
				$vehicle        = ( 'luxury' === $tier ) ? 'Innova Crysta AC' : 'Dedicated AC Sedan';

				$quote = "🌟 *TripCosmos 3D2N Spiritual Varanasi Pilgrimage*\n\n" .
						"Namaste {$name} ji! 🙏 Here is your complete private package itinerary:\n\n" .
						"• *Day 1:* Airport/Station Pickup, Hotel Check-in, Evening Ganga Aarti VIP Boat Cruise at Dashashwamedh Ghat.\n" .
						"• *Day 2:* Sunrise Morning Boat Ride, Kashi Vishwanath VIP Darshan Pass, Annapurna Temple, Sankat Mochan, BHU, Sarnath Deer Park.\n" .
						"• *Day 3:* Morning Ghat Walk, Local Banarasi Silk Weaving Tour, Airport Drop.\n\n" .
						"🏨 *Accommodation:* {$hotel}\n" .
						"🚗 *Transportation:* {$vehicle} for all days (Pick to Drop)\n" .
						"🚤 *Boating:* Private Ghat Boat Cruise included\n" .
						"💰 *Total All-Inclusive Package ({$pax} Pax):* ₹" . number_format( $retail_pricing ) . "\n" .
						"🔒 *Token Advance to Confirm Dates:* ₹2,000 via UPI\n" .
						"👉 *Official Booking Link:* https://tripcosmos.co/book?ref=varanasi-" . time();

				$white_label = "🌟 *3D2N Spiritual Varanasi Pilgrimage Itinerary*\n\n" .
						"Guest: {$name} | Travelers: {$pax} Pax | Dates: {$dates}\n\n" .
						"• *Day 1:* Airport/Station Pickup, Hotel Check-in, Evening Ganga Aarti VIP Boat Cruise.\n" .
						"• *Day 2:* Sunrise Ganga Boat Ride, Kashi Vishwanath VIP Darshan, Annapurna Temple, Sankat Mochan, Sarnath Tour.\n" .
						"• *Day 3:* Morning Ghat Heritage Walk, Banarasi Silk Weaving, Airport Drop.\n\n" .
						"🏨 *Hotel:* {$hotel} (Breakfast included)\n" .
						"🚗 *Vehicle:* Private {$vehicle}\n" .
						"💰 *Package Price ({$pax} Pax):* ₹" . number_format( $retail_pricing );
				break;
		}

		$commission = $retail_pricing - $net_pricing;

		return new WP_REST_Response(
			array(
				'ok'                => true,
				'destination'       => $destination,
				'package_title'     => $title,
				'pax'               => $pax,
				'rate_type'         => $rate_type,
				'pricing'           => ( 'b2b' === $rate_type ) ? $net_pricing : $retail_pricing,
				'retail_price'      => $retail_pricing,
				'net_price'         => $net_pricing,
				'commission'        => $commission,
				'advance_required'  => min( 3000, max( 1500, (int) ( $retail_pricing * 0.15 ) ) ),
				'quote_text'        => ( 'b2b' === $rate_type ) ? $white_label : $quote,
				'white_label_quote' => $white_label,
			),
			200
		);
	}

	/**
	 * Send Transactional Driver Dispatch / Confirmation via Brevo SMS.
	 */
	public static function handle_send_dispatch( WP_REST_Request $request ) {
		$params   = $request->get_json_params() ?: $request->get_params();
		$phone    = sanitize_text_field( $params['phone'] ?? '' );
		$name     = sanitize_text_field( $params['customer_name'] ?? '' );
		$driver   = sanitize_text_field( $params['driver_name'] ?? '' );
		$cab_no   = sanitize_text_field( $params['vehicle_number'] ?? '' );
		$cab_type = sanitize_text_field( $params['vehicle_type'] ?? '' );

		if ( '' === $name ) {
			$name = 'Traveler';
		}

		// Never fall back to placeholder driver/vehicle details: a customer would receive them as real.
		$missing = array();
		foreach ( array( 'phone' => $phone, 'driver_name' => $driver, 'vehicle_number' => $cab_no, 'vehicle_type' => $cab_type ) as $field => $value ) {
			if ( '' === $value ) {
				$missing[] = $field;
			}
		}
		if ( $missing ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Missing required fields: ' . implode( ', ', $missing ) ), 400 );
		}

		if ( ! class_exists( 'TC_Integration_Brevo' ) || ! TC_Integration_Brevo::is_configured() ) {
			return new WP_REST_Response( array( 'ok' => false, 'sms_sent' => false, 'error' => 'SMS provider is not configured.' ), 503 );
		}

		$sms_text = "Namaste {$name} ji! Your TripCosmos cab is dispatched: {$cab_type} ({$cab_no}), Driver: {$driver}. For support, call our Varanasi desk.";
		$res      = TC_Integration_Brevo::send_sms( $phone, $sms_text );

		if ( is_wp_error( $res ) ) {
			TC_Agents_Logger::log( 'mobile_dispatch_failed', 'warning', array( 'agent' => (string) $request->get_param( '_tc_agent' ), 'error' => $res->get_error_message() ) );
			return new WP_REST_Response( array( 'ok' => false, 'sms_sent' => false, 'error' => 'SMS delivery failed.' ), 502 );
		}

		TC_Agents_Logger::log( 'mobile_dispatch_sent', 'info', array( 'agent' => (string) $request->get_param( '_tc_agent' ), 'vehicle' => $cab_no ) );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'message'  => 'Dispatch SMS sent.',
				'sms_sent' => true,
				'text'     => $sms_text,
			),
			200
		);
	}
}
