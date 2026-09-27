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

		// 9. Jio SIM Call Queue (Android Companion polls pending ₹0 calls)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/call-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_call_queue' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 10. Jio SIM Call Dispatch Status (Android Companion updates call progress/result)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/call-dispatch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_call_dispatch' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 11. Voice AI RAG Query (AI Puffer Bot 155464 spoken answer for voice calls)
		register_rest_route(
			self::NAMESPACE,
			'/voice/query-rag',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_voice_query_rag' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);

		// 13. Mobile Updates Lead Stage & Notes (Bidirectional CRM Sync)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/update-lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_update_lead' ),
				'permission_callback' => array( __CLASS__, 'verify_token' ),
			)
		);


		// 12. Live Real-Time Events Feed for Android Companion Notifications
		register_rest_route(
			self::NAMESPACE,
			'/mobile/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_events' ),
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
	 * Verify the mobile token (X-Mobile-Token header only: query-string tokens leak into access logs).
	 * Accepts the master token or a per-agent token, and records WHO is calling in the private
	 * `_tc_agent` request param so handlers can trust it (it is never read from the request body).
	 */
	public static function verify_token( WP_REST_Request $request ) {
		$ip = TC_Agents_Security::client_ip();
		if ( TC_Agents_Security::rate_exceeded( 'mobile_auth_fail', $ip, 30 ) ) {
			return new WP_Error( 'tc_rate_limited', 'Too many failed attempts. Try again later.', array( 'status' => 429 ) );
		}

		$request->set_param( '_tc_agent', '' );
		$token = (string) $request->get_header( 'X-Mobile-Token' );

		if ( '' !== $token ) {
			$label = hash_equals( (string) self::get_api_token(), $token ) ? 'master' : TC_Agents_Security::match_agent_token( $token );
			if ( false !== $label ) {
				// Per-device ceiling: generous for normal use, stops a stuck retry loop or a leaked token scraping the CRM.
				if ( ! TC_Agents_Security::rate_limit( 'mobile_req', hash( 'sha256', $token ), 1200, 600 ) ) {
					return new WP_Error( 'tc_rate_limited', 'Too many requests from this device. Wait a few minutes.', array( 'status' => 429 ) );
				}
				$request->set_param( '_tc_agent', $label );
				return true;
			}
		}

		// Logged-in admins (browser + REST nonce) may also call these routes.
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

		// Match on the last 10 digits (handles +91 etc.). Anything shorter would match unrelated customers
		// ("204" returned a real person's details), so a short number is treated as an unknown caller.
		$suffix  = substr( $clean_phone, -10 );
		$contact = null;
		if ( strlen( $suffix ) >= 10 ) {
			$contact = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM $table_contacts WHERE phone LIKE %s ORDER BY updated_at DESC LIMIT 1", '%' . $wpdb->esc_like( $suffix ) ),
				ARRAY_A
			);
		}

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
					'destination'         => $meta['destination'] ?? '',
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
		if ( ! in_array( $call_type, array( 'incoming', 'outgoing', 'missed' ), true ) ) {
			$call_type = 'incoming';
		}
		$duration  = (int) ( $params['duration_seconds'] ?? 0 );
		$rec_url   = esc_url_raw( $params['recording_url'] ?? '' );
		$notes     = sanitize_textarea_field( $params['notes'] ?? '' );

		if ( empty( $phone ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phone required' ), 400 );
		}

		// Idempotency: the app may retry after a timeout. A repeated call id / Idempotency-Key is acknowledged, not re-processed.
		$idem = sanitize_text_field( (string) ( $request->get_header( 'Idempotency-Key' ) ?: ( $params['call_id'] ?? '' ) ) );
		if ( '' !== $idem ) {
			$idem_key = 'tc_calllog_' . md5( $idem );
			if ( get_transient( $idem_key ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
			}
			set_transient( $idem_key, 1, DAY_IN_SECONDS );
		}

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$suffix  = substr( preg_replace( '/[^\d]/', '', $phone ), -10 );
		$contact = TC_Contact_Service::find( '', $phone );

		$contact_id = 0;
		if ( $contact ) {
			$contact_id = (int) $contact['id'];
			TC_Contact_Service::add_activity(
				$contact_id,
				'call',
				trim( sprintf( '%s call, %ds', ucfirst( $call_type ), max( 0, $duration ) ) . ( $notes ? ' — ' . $notes : '' ) ),
				array_filter( array( 'type' => $call_type, 'duration' => $duration, 'recording_url' => $rec_url ) )
			);

			// Keep the call on the lead's own record, so it shows against the lead and not only in the audit log.
			$meta    = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
			$history = isset( $meta['call_history'] ) && is_array( $meta['call_history'] ) ? $meta['call_history'] : array();
			array_unshift(
				$history,
				array(
					'type'     => in_array( $call_type, array( 'incoming', 'outgoing', 'missed' ), true ) ? $call_type : 'other',
					'duration' => max( 0, $duration ),
					'notes'    => $notes,
					'at'       => current_time( 'mysql' ),
				)
			);
			$meta['call_history']      = array_slice( $history, 0, 20 );
			$meta['last_contacted_at'] = current_time( 'mysql' );
			$wpdb->update( $table_contacts, array( 'meta_data' => wp_json_encode( $meta ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $contact_id ), array( '%s', '%s' ), array( '%d' ) );
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

		// Courtesy reply to a missed call: only to people already in the CRM (never to a staff member's personal
		// contacts or spam numbers) and at most once every 6 hours per number.
		$auto_reply_sent = false;
		if ( 'missed' === $call_type && $contact && TC_Contact_Service::can_contact( $contact, 'whatsapp' ) ) {
			$cooldown_key = 'tc_missed_ar_' . substr( md5( $suffix ), 0, 20 );
			if ( ! get_transient( $cooldown_key ) ) {
				$name = ! empty( $contact['name'] ) ? $contact['name'] : 'Traveler';
				$msg  = sprintf(
					"Namaste %s! 🙏 Sorry we missed your call at TripCosmos Travel Desk. Our Varanasi specialists are currently assisting pilgrims and travelers. How can we help you with your tour, outstation cab, or temple darshan booking?",
					$name
				);
				$auto_reply_sent = (bool) TC_Integration_WhatsApp::send_message( $phone, $msg );
				if ( $auto_reply_sent ) {
					set_transient( $cooldown_key, 1, 6 * HOUR_IN_SECONDS );
				}
			}
		}

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'message'    => 'Call log recorded successfully.',
				'contact_id' => $contact_id,
				'auto_reply_sent' => $auto_reply_sent,
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

		// These used to point at PDFs that do not exist (404) and at /book, which redirects to an unrelated boat-booking page.
		$brochure_url = esc_url_raw( get_option( 'tc_agents_brochure_url', home_url( '/tour-packages/' ) ) );
		$cab_url      = esc_url_raw( get_option( 'tc_agents_cab_chart_url', home_url( '/outstation-cab-services-from-varanasi/' ) ) );

		switch ( $action ) {
			case 'send_brochure':
				$msg = "Namaste! Here are TripCosmos tour packages for Varanasi, Ayodhya & Prayagraj with day-wise itineraries and inclusions: " . $brochure_url . " 🛕";
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			case 'send_cab_chart':
			case 'send_gear_guide':
				$msg = "Here are TripCosmos outstation cab services and rates from Varanasi (Swift Dzire, Innova Crysta and Tempo Traveller): " . $cab_url . " 🚗";
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			case 'send_booking_link':
				$msg = "Ready to confirm your tour package or cab booking? Message our Varanasi booking desk here and we will secure your dates: " . self::booking_link( 'quick-action-' . time() );
				$sent = TC_Integration_WhatsApp::send_message( $phone, $msg );
				break;

			default:
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'Unknown action' ), 400 );
		}

		TC_Agents_Logger::log( 'mobile_quick_action', 'info', array( 'action' => $action, 'phone' => $phone, 'sent' => (bool) $sent ), '', 'whatsapp' );

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

		// Paged, so the list keeps working as the CRM grows. The defaults keep the old behaviour (first 100).
		$limit    = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
		$offset   = max( 0, (int) $request->get_param( 'offset' ) );
		$query    = "SELECT id, name, email, phone, stage, score, deal_value, owner, tags, next_follow_up_at, meta_data, created_at, updated_at FROM $table_contacts WHERE $where ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;
		$rows     = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		$leads = array();
		foreach ( ( $rows ?: array() ) as $row ) {
			$meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true ) ?: array();
			$leads[] = array(
				'id'           => (int) $row['id'],
				'name'         => ! empty( $row['name'] ) ? $row['name'] : 'Traveler',
				'email'        => (string) ( $row['email'] ?? '' ),
				'phone'        => (string) ( $row['phone'] ?? '' ),
				'stage'        => (string) ( $row['stage'] ?: 'inquiry' ),
				'score'        => (int) ( $row['score'] ?? 0 ),
				'deal_value'   => (float) ( $row['deal_value'] ?? 0.0 ),
				'destination'  => ! empty( $meta['destination'] ) ? $meta['destination'] : '',
				'requirements' => (string) ( $meta['requirements'] ?? '' ),
				'owner'        => (string) ( $row['owner'] ?? '' ),
				'tags'         => ! empty( $row['tags'] ) ? $row['tags'] : '',
				'next_follow_up_at' => (string) ( $row['next_follow_up_at'] ?? '' ),
				'created_at'   => (string) ( $row['created_at'] ?? '' ),
				'updated_at'   => (string) ( $row['updated_at'] ?? '' ),
			);
		}

		// Also check if any FluentCRM subscribers with phone should be included
		$table_subscribers = $wpdb->prefix . 'fc_subscribers';
		$subscribers_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_subscribers ) );
		if ( $subscribers_table_exists && 0 === $offset ) {
			$existing_phones = array_column( $leads, 'phone' );
			$subscribers = $wpdb->get_results(
				"SELECT id, first_name, last_name, email, phone, created_at, updated_at FROM $table_subscribers WHERE phone IS NOT NULL AND phone != '' ORDER BY id DESC LIMIT 50",
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
							'score'        => 0,
							'deal_value'   => 0.0,
							'destination'  => '',
							'requirements' => 'Newsletter subscriber (not yet a CRM lead). Assigning it promotes it to a lead.',
							'source'       => 'fluentcrm_subscriber',
							'created_at'   => (string) ( $sub['created_at'] ?? '' ),
							'updated_at'   => (string) ( $sub['updated_at'] ?? '' ),
						);
					}
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true, 'leads' => $leads, 'limit' => $limit, 'offset' => $offset ), 200 );
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
			// Show what the customer really wrote. This used to fabricate "Inquiring about ..." text for every lead.
			$last_msg = ! empty( $meta['last_message'] ) ? $meta['last_message'] : '';
			if ( '' === $last_msg ) {
				$real_msg = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT m.content FROM {$wpdb->prefix}tc_agent_messages m INNER JOIN {$wpdb->prefix}tc_agent_conversations cv ON cv.id = m.conversation_id WHERE cv.contact_id = %d AND m.role = 'user' AND m.content <> '' ORDER BY m.id DESC LIMIT 1",
						(int) $c['id']
					)
				);
				$last_msg = $real_msg ? mb_substr( (string) $real_msg, 0, 160 ) : '';
			}
			$time_ago = human_time_diff( strtotime( $c['updated_at'] ?: $c['created_at'] ), current_time( 'timestamp' ) ) . ' ago';

			// The stored score is shown as it is. Scores below 60 used to be silently raised to 75.
			$score = (int) ( $c['score'] ?? 0 );

			$whatsapp_leads[] = array(
				'id'               => (string) $c['id'],
				'customer_name'    => ! empty( $c['name'] ) ? $c['name'] : 'Traveler (' . substr( $c['phone'], -4 ) . ')',
				'phone'            => (string) $c['phone'],
				'last_message'     => (string) $last_msg,
				'time_ago'         => (string) $time_ago,
				'unread_count'     => (int) ( $meta['unread_count'] ?? 0 ),
				'tour_interest'    => ! empty( $meta['destination'] ) ? $meta['destination'] : '',
				'estimated_budget' => (float) $c['deal_value'],
				'assigned_manager' => ! empty( $assigned_manager ) ? $assigned_manager : null,
				'lead_score'       => $score,
				'status'           => ! empty( $assigned_manager ) ? 'assigned' : 'new',
			);
		}

		return new WP_REST_Response( array( 'ok' => true, 'leads' => $whatsapp_leads ), 200 );
	}

	/**
	 * Turn a FluentCRM subscriber (shown in the lead list) into a real CRM contact, reusing an existing one when possible.
	 *
	 * @param int $subscriber_id FluentCRM subscriber id.
	 * @return int Contact id, or 0 if the subscriber does not exist.
	 */
	private static function promote_subscriber_to_contact( $subscriber_id ) {
		global $wpdb;
		$subscriber_id = (int) $subscriber_id;
		if ( $subscriber_id < 1 ) {
			return 0;
		}
		$contacts = $wpdb->prefix . 'tc_agent_contacts';
		$sub      = $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, last_name, email, phone FROM {$wpdb->prefix}fc_subscribers WHERE id = %d", $subscriber_id ), ARRAY_A );
		if ( ! $sub ) {
			return 0;
		}

		$res = TC_Contact_Service::upsert(
			array(
				'fluentcrm_id'   => $subscriber_id,
				'name'           => trim( ( $sub['first_name'] ?? '' ) . ' ' . ( $sub['last_name'] ?? '' ) ),
				'email'          => (string) ( $sub['email'] ?? '' ),
				'phone'          => (string) ( $sub['phone'] ?? '' ),
				'source_channel' => 'fluentcrm',
			),
			array( 'sync' => false )
		);
		if ( $res['id'] ) {
			$wpdb->update( $contacts, array( 'fluentcrm_id' => $subscriber_id ), array( 'id' => $res['id'] ), array( '%d' ), array( '%d' ) );
		}
		return (int) $res['id'];
	}

	/**
	 * A WhatsApp link to the booking desk with the quote reference pre-filled. Replaces /book, which redirected
	 * to an unrelated boat-booking page.
	 *
	 * @param string $ref Quote reference.
	 * @return string
	 */
	private static function booking_link( $ref ) {
		$wa = preg_replace( '/[^0-9]/', '', (string) get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( '' === $wa ) {
			return home_url( '/contact/' );
		}
		return 'https://wa.me/' . $wa . '?text=' . rawurlencode( 'Hello TripCosmos, I would like to confirm my quote (ref ' . $ref . ').' );
	}

	/**
	 * A vehicle that can physically carry the group. Prices in the quote templates are unchanged; this only stops
	 * a quote promising a sedan to a group of eight.
	 *
	 * @param int    $pax   Travellers.
	 * @param string $tier  Tier ('luxury' picks the SUV).
	 * @param string $sedan Default sedan name for this quote.
	 * @param string $suv   Luxury SUV name for this quote.
	 * @return string
	 */
	private static function vehicle_for_group( $pax, $tier, $sedan, $suv ) {
		$pax = (int) $pax;
		if ( $pax <= 4 ) {
			return ( 'luxury' === $tier ) ? $suv : $sedan;
		}
		if ( $pax <= 6 ) {
			return ( 'luxury' === $tier ) ? $suv : 'Maruti Ertiga / XL6 AC (6 seater)';
		}
		if ( $pax <= 12 ) {
			return 'Force Tempo Traveller AC (12 seater)';
		}
		return 'Two or more vehicles (combination confirmed at booking)';
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

		// Ids of 100000 and above are newsletter subscribers shown in the list. Make them real CRM contacts on first assignment.
		if ( $lead_id >= 100000 ) {
			$lead_id = self::promote_subscriber_to_contact( $lead_id - 100000 );
		}

		$contact = ( $lead_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE id = %d", $lead_id ), ARRAY_A ) : null;
		if ( ! $contact ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Lead not found' ), 404 );
		}

		$meta     = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
		$previous = $meta['assigned_manager'] ?? '';
		$history  = isset( $meta['assignment_history'] ) && is_array( $meta['assignment_history'] ) ? $meta['assignment_history'] : array();
		array_unshift( $history, array( 'to' => $manager_name, 'from' => $previous, 'at' => current_time( 'mysql' ) ) );
		$meta['assignment_history'] = array_slice( $history, 0, 20 );
		$meta['assigned_manager']   = $manager_name;
		$meta['assigned_at']        = current_time( 'mysql' );

		// Assigning a lead does not qualify it, so the pipeline stage is left exactly as it was.
		// Map the app's manager name to a CRM user so ownership, "My leads" and alerts work.
		$staff = null;
		foreach ( TC_CRM_Access::team() as $u ) {
			if ( 0 === strcasecmp( $u->display_name, $manager_name ) || 0 === strcasecmp( $u->user_login, $manager_name ) || 0 === strcasecmp( $u->user_email, $manager_name ) ) {
				$staff = $u;
				break;
			}
		}
		if ( $staff ) {
			TC_Contact_Service::set_owner( $lead_id, (int) $staff->ID, $notify_manager );
			$meta['assigned_manager'] = $staff->display_name;
			$manager_name             = $staff->display_name;
		} else {
			TC_Contact_Service::add_activity( $lead_id, 'system', sprintf( 'Assigned to %s', $manager_name ) . ( $previous ? sprintf( ' (was %s)', $previous ) : '' ) );
		}
		$wpdb->update(
			$table_contacts,
			array(
				'owner'      => $manager_name,
				'meta_data'  => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		TC_Agents_Logger::log( 'mobile_lead_assigned', 'info', array( 'lead_id' => $lead_id, 'manager' => $manager_name, 'previous' => $previous ), '', 'admin' );

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'message'    => "Lead #$lead_id assigned to $manager_name",
				'lead_id'    => $lead_id,
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
				$vehicle        = self::vehicle_for_group( $pax, $tier, 'Swift Dzire AC', 'Innova Crysta AC (6+1)' );

				$quote = "🚗 *TripCosmos Ayodhya Ram Janmabhoomi Day Excursion*\n\n" .
						"Namaste {$name} ji! 🙏 Here is your customized private cab quote:\n\n" .
						"• *Vehicle:* {$vehicle}\n" .
						"• *Dates:* {$dates}\n" .
						"• *Travelers:* {$pax} Pax\n" .
						"• *Inclusions:* Fuel, Highway Tolls, Parking, Driver Allowance.\n" .
						"• *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti.\n\n" .
						"💰 *Total All-Inclusive Fare:* ₹" . number_format( $retail_pricing ) . "\n" .
						"🔒 *Token Advance to Block Cab:* ₹1,500 via UPI\n" .
						"👉 *Confirm on WhatsApp:* " . self::booking_link( 'ayodhya-' . time() );

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
				$vehicle        = self::vehicle_for_group( $pax, $tier, 'Dedicated AC Sedan', 'Innova Crysta AC' );

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
						"👉 *Confirm on WhatsApp:* " . self::booking_link( 'triangle-' . time() );

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
				$vehicle        = self::vehicle_for_group( $pax, $tier, 'Dedicated AC Sedan', 'Innova Crysta AC' );

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
						"👉 *Confirm on WhatsApp:* " . self::booking_link( 'varanasi-' . time() );

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

		// The message text used to carry its own fixed advance (Rs 1,500 / 2,000 / 3,000) that disagreed with the
		// advance_required field below. One number now feeds both.
		$advance = min( 3000, max( 1500, (int) ( $retail_pricing * 0.15 ) ) );
		$quote   = preg_replace( '/(Token Advance[^\n]*?₹)[\d,]+/u', '${1}' . number_format( $advance ), $quote );

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
				'advance_required'  => $advance,
				'price_source'      => 'template',
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
		$name     = sanitize_text_field( $params['customer_name'] ?? 'Traveler' );
		$driver   = sanitize_text_field( $params['driver_name'] ?? '' );
		$cab_no   = sanitize_text_field( $params['vehicle_number'] ?? '' );
		$cab_type = sanitize_text_field( $params['vehicle_type'] ?? '' );

		if ( empty( $phone ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Phone parameter required' ), 400 );
		}

		// Never invent a driver or a number plate: a customer must only be told what was actually assigned.
		if ( '' === $driver || '' === $cab_no ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'driver_name and vehicle_number are required. Nothing was sent.' ), 400 );
		}

		$support  = preg_replace( '/[^0-9+]/', '', (string) get_option( 'tc_agents_human_whatsapp_number', '' ) );
		$vehicle  = trim( $cab_type . ' (' . $cab_no . ')' );
		$sms_text = "Namaste {$name} ji! Your TripCosmos cab is dispatched: {$vehicle}, Driver: {$driver}. For support call {$support}.";

		$sms_sent = false;
		if ( class_exists( 'TC_Integration_Brevo' ) && TC_Integration_Brevo::is_configured() ) {
			$res = TC_Integration_Brevo::send_sms( $phone, $sms_text );
			$sms_sent = ! is_wp_error( $res );
		}

		TC_Agents_Logger::log( 'mobile_dispatch_sms', 'info', array( 'phone' => $phone, 'driver' => $driver, 'vehicle' => $cab_no, 'sent' => $sms_sent ), '', 'sms' );

		return new WP_REST_Response(
			array(
				'ok'       => $sms_sent,
				'message'  => $sms_sent ? 'Dispatch SMS sent.' : 'The SMS was NOT sent (provider not configured or it rejected the message). Copy the text and send it manually.',
				'sms_sent' => $sms_sent,
				'text'     => $sms_text,
			),
			200
		);
	}

	/**
	 * Get pending outbound calls for Android Jio SIM Companion.
	 */
	public static function handle_get_call_queue( WP_REST_Request $request ) {
		$pending_calls = array();

		// 1. Check fast transient
		$transient_calls = get_transient( 'tc_jio_pending_calls' );
		if ( ! empty( $transient_calls ) && is_array( $transient_calls ) ) {
			$now_ts  = time();
			$changed = false;
			foreach ( $transient_calls as $call_id => $call_data ) {
				// Lease: hand each call to one phone at a time. It is offered again after 5 minutes if never completed.
				if ( ! empty( $call_data['claimed_at'] ) && ( $now_ts - (int) $call_data['claimed_at'] ) < 300 ) {
					continue;
				}
				$transient_calls[ $call_id ]['claimed_at'] = $now_ts;
				$changed                                   = true;
				$pending_calls[]                           = $call_data;
			}
			if ( $changed ) {
				set_transient( 'tc_jio_pending_calls', $transient_calls, 30 * MINUTE_IN_SECONDS );
			}
		}

		// 2. Check async jobs queue if available
		global $wpdb;
		$table_name = $wpdb->prefix . 'tc_agent_jobs';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
			$db_jobs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, job_type, payload, created_at FROM {$table_name}
					 WHERE job_type = 'jio_outbound_call'
					 AND ( status = 'pending' OR ( status = 'processing' AND updated_at < %s ) )
					 ORDER BY id ASC LIMIT 10",
					gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 300 )
				),
				ARRAY_A
			);
			if ( ! empty( $db_jobs ) ) {
				$existing_ids = wp_list_pluck( $pending_calls, 'call_id' );
				foreach ( $db_jobs as $job ) {
					$payload = maybe_unserialize( $job['payload'] );
					if ( is_string( $payload ) ) {
						$payload = json_decode( $payload, true ) ?: array();
					}
					$cid = $payload['call_id'] ?? ( 'job_' . $job['id'] );
					// Claim the job atomically: only the request that flips it wins, so two phones never get the same call.
					$claimed = $wpdb->query(
						$wpdb->prepare(
							"UPDATE {$table_name} SET status = 'processing', attempts = attempts + 1, updated_at = %s WHERE id = %d AND ( status = 'pending' OR ( status = 'processing' AND updated_at < %s ) )",
							current_time( 'mysql' ),
							(int) $job['id'],
							gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 300 )
						)
					);
					if ( 1 === (int) $claimed && ! in_array( $cid, $existing_ids, true ) ) {
						$payload['job_db_id'] = $job['id'];
						$pending_calls[]      = $payload;
					}
				}
			}
		}

		return new WP_REST_Response(
			array(
				'ok'    => true,
				'count' => count( $pending_calls ),
				'calls' => $pending_calls,
			),
			200
		);
	}

	/**
	 * Update call dispatch status from Android Jio SIM Companion.
	 */
	public static function handle_call_dispatch( WP_REST_Request $request ) {
		$params   = $request->get_json_params() ?: $request->get_params();
		$call_id  = sanitize_text_field( $params['call_id'] ?? '' );
		$status   = sanitize_text_field( $params['status'] ?? 'completed' ); // dialing, connected, completed, failed, busy
		$duration = intval( $params['duration'] ?? 0 );
		$notes    = sanitize_textarea_field( $params['notes'] ?? '' );
		$phone    = sanitize_text_field( $params['phone'] ?? '' );

		if ( empty( $call_id ) && empty( $phone ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'call_id or phone required' ), 400 );
		}

		// 1. Remove from transient queue if completed or failed
		$transient_calls = get_transient( 'tc_jio_pending_calls' );
		if ( ! empty( $transient_calls ) && is_array( $transient_calls ) ) {
			if ( isset( $transient_calls[ $call_id ] ) ) {
				if ( in_array( $status, array( 'completed', 'failed', 'busy', 'rejected' ), true ) ) {
					unset( $transient_calls[ $call_id ] );
				} else {
					$transient_calls[ $call_id ]['status'] = $status;
				}
				set_transient( 'tc_jio_pending_calls', $transient_calls, 30 * MINUTE_IN_SECONDS );
			}
		}

		// 2. Update DB job if table exists
		global $wpdb;
		$table_name = $wpdb->prefix . 'tc_agent_jobs';
		if ( ! empty( $call_id ) && $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
			if ( in_array( $status, array( 'completed', 'failed', 'busy', 'rejected' ), true ) ) {
				$db_status = ( 'completed' === $status ) ? 'completed' : 'failed';
				// Match the exact call id. The old substring match let call "1" also close calls "10" and "11".
				$json_like = '%"call_id":"' . $wpdb->esc_like( $call_id ) . '"%';
				$ser_like  = '%"call_id";s:' . strlen( $call_id ) . ':"' . $wpdb->esc_like( $call_id ) . '"%';
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table_name} SET status = %s, updated_at = %s WHERE job_type = 'jio_outbound_call' AND ( payload LIKE %s OR payload LIKE %s )",
						$db_status,
						current_time( 'mysql' ),
						$json_like,
						$ser_like
					)
				);
			}
		}

		// 3. Log Call Event
		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'jio_sim_call_status',
				'info',
				array(
					'call_id'  => $call_id,
					'phone'    => $phone,
					'status'   => $status,
					'duration' => $duration,
					'notes'    => $notes,
				),
				'',
				'voice'
			);
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'call_id' => $call_id,
				'status'  => $status,
				'message' => 'Call dispatch recorded successfully',
			),
			200
		);
	}

	/**
	 * Voice AI RAG Query for live telephony Q&A (100% Free via local AI Puffer Bot 155464).
	 */
	public static function handle_voice_query_rag( WP_REST_Request $request ) {
		$params     = $request->get_json_params() ?: $request->get_params();
		$transcript = sanitize_text_field( $params['transcript'] ?? $params['query'] ?? '' );
		$session_id = sanitize_text_field( $params['session_id'] ?? '' );
		$phone      = sanitize_text_field( $params['phone'] ?? '' );

		if ( empty( $transcript ) ) {
			return new WP_REST_Response(
				array(
					'ok'    => true,
					'reply' => 'Namaste! How may I assist your Varanasi journey today?',
				),
				200
			);
		}

		// Ensure voice integration class is available
		if ( ! class_exists( 'TC_Integration_Voice' ) ) {
			$voice_path = dirname( __DIR__ ) . '/integrations/class-tc-integration-voice.php';
			if ( file_exists( $voice_path ) ) {
				require_once $voice_path;
			}
		}

		if ( class_exists( 'TC_Integration_Voice' ) ) {
			$result = TC_Integration_Voice::query_voice_rag( $transcript, $session_id );
			return new WP_REST_Response(
				array(
					'ok'       => true,
					'reply'    => $result['reply'] ?? '',
					'provider' => $result['provider_used'] ?? 'aipuffer_rag_local',
					'is_free'  => true,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'reply'   => 'Namaste! TripCosmos tour specialists are ready to assist you. What destination are you exploring?',
				'is_free' => true,
			),
			200
		);
	}

	/**
	 * Get Real-time Events Feed for Android Companion Notifications.
	 * Returns visitor landing events, new leads captured, queued calls, and inbound WhatsApp messages.
	 */
	public static function handle_get_events( WP_REST_Request $request ) {
		global $wpdb;
		$table_audit = $wpdb->prefix . 'tc_agent_audit_log';

		$since_id = max( 0, (int) $request->get_param( 'since_id' ) );
		$limit    = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 20 ) ) );

		$where = "event_type IN ('lead_captured', 'visitor_landing', 'jio_outbound_call', 'call_dispatched', 'chat_started', 'quote_requested')";
		if ( $since_id > 0 ) {
			$where .= $wpdb->prepare( " AND id > %d", $since_id );
		}

		$rows = $wpdb->get_results(
			"SELECT id, event_type, severity, details, channel, created_at FROM {$table_audit} WHERE {$where} ORDER BY id DESC LIMIT {$limit}",
			ARRAY_A
		) ?: array();

		$events = array();
		$max_id = $since_id;

		foreach ( $rows as $row ) {
			$row_id = (int) $row['id'];
			if ( $row_id > $max_id ) {
				$max_id = $row_id;
			}
			$details = json_decode( (string) ( $row['details'] ?? '' ), true ) ?: array();

			$title = 'Activity Event';
			$subtitle = '';

			switch ( $row['event_type'] ) {
				case 'lead_captured':
					$name = ! empty( $details['name'] ) ? $details['name'] : 'Traveler';
					$phone = ! empty( $details['phone'] ) ? $details['phone'] : '';
					$dest = ! empty( $details['destination'] ) ? $details['destination'] : 'Spiritual Circuit';
					$val = ! empty( $details['deal_value'] ) ? '₹' . number_format( (float) $details['deal_value'] ) : '₹15,000';
					$title = "🎯 Lead: {$name}";
					$subtitle = "{$phone} • {$dest} • {$val}";
					break;
				case 'visitor_landing':
					$page = ! empty( $details['page_title'] ) ? $details['page_title'] : 'TripCosmos Website';
					$action = ! empty( $details['action'] ) ? $details['action'] : 'landed';
					$title = ( 'chat_opened' === $action ) ? "💬 Traveler Opened Chat" : "👁️ Traveler on Site";
					$subtitle = "Browsing: {$page}";
					break;
				case 'jio_outbound_call':
					$phone = ! empty( $details['phone'] ) ? $details['phone'] : '';
					$title = "📞 Free Jio Call Queued";
					$subtitle = "Dialing {$phone} via companion phone";
					break;
				default:
					$title = ucwords( str_replace( '_', ' ', $row['event_type'] ) );
					$subtitle = substr( wp_strip_all_tags( (string) $row['details'] ), 0, 80 );
					break;
			}

			$events[] = array(
				'id'         => $row_id,
				'event_type' => $row['event_type'],
				'severity'   => $row['severity'],
				'title'      => $title,
				'subtitle'   => $subtitle,
				'details'    => $details,
				'channel'    => $row['channel'],
				'created_at' => $row['created_at'],
			);
		}

		return new WP_REST_Response(
			array(
				'ok'        => true,
				'count'     => count( $events ),
				'latest_id' => $max_id,
				'events'    => $events,
			),
			200
		);
	}

	/**
	 * Mobile updates lead stage and adds activity notes.
	 */
	public static function handle_update_lead( WP_REST_Request $request ) {
		$params  = $request->get_json_params() ?: $request->get_params();
		$lead_id = absint( $params['lead_id'] ?? 0 );
		$stage   = sanitize_key( $params['stage'] ?? '' );
		$note    = sanitize_textarea_field( $params['note'] ?? '' );

		if ( empty( $lead_id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'lead_id is required' ), 400 );
		}

		$contact = TC_Contact_Service::get( $lead_id );
		if ( ! $contact ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Lead not found' ), 404 );
		}

		if ( ! empty( $stage ) ) {
			$stage_map = array(
				'new'        => 'inquiry',
				'contacted'  => 'qualified',
				'quote_sent' => 'proposal',
			);
			$crm_stage = $stage_map[ $stage ] ?? $stage;
			TC_Contact_Service::set_stage( $lead_id, $crm_stage );
		}

		$owner = sanitize_text_field( $params['owner'] ?? '' );
		if ( ! empty( $owner ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'tc_agent_contacts',
				array( 'owner' => $owner, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $lead_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			TC_Contact_Service::add_activity( $lead_id, 'assignment', sprintf( __( 'Lead assigned to %s', 'tripcosmos-agents' ), $owner ) );
		}
		if ( ! empty( $note ) ) {
			TC_Contact_Service::add_activity( $lead_id, 'note', sprintf( __( '📱 Mobile App Note: %s', 'tripcosmos-agents' ), $note ) );
		}

		return new WP_REST_Response( array( 'ok' => true, 'lead_id' => $lead_id, 'stage' => $stage ), 200 );
	}
}
