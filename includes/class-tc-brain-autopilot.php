<?php
/**
 * TripCosmos Sovereign Brain & Autopilot Daemon.
 *
 * Provides:
 * 1. Self-aware Brevo quota monitoring & automated multi-day B2B campaign scheduling.
 * 2. Anomaly detection & auto-healing for delivery queues.
 * 3. Unified Voice Assistant Brain for Web widget and TripCosmos.co / X-Agents Android apps.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Brain_Autopilot {

	const CRON_HOOK            = 'tc_brain_autopilot_tick';
	const BATCH_2_CAMPAIGN_ID  = 264;
	const BATCH_3_CAMPAIGN_ID  = 265;
	const LOG_OPTION           = 'tc_brain_autopilot_logs';

	public static function init() {
		// 1. Cron scheduling
		add_action( 'init', array( __CLASS__, 'setup_cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'execute_autopilot_cycle' ) );

		// 2. REST API for Voice Assistant & Mobile Apps
		add_action( 'rest_api_init', array( __CLASS__, 'register_voice_routes' ) );

		// 3. Admin AJAX Triggers (for Workspace Command Center)
		add_action( 'wp_ajax_tc_brain_trigger_batch', array( __CLASS__, 'ajax_trigger_batch' ) );
		add_action( 'wp_ajax_tc_brain_get_status', array( __CLASS__, 'ajax_get_status' ) );
	}

	/**
	 * Setup recurring background cron
	 */
	public static function setup_cron_schedules() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Main Autopilot Decision Loop
	 * Runs on hourly server cron (or when triggered)
	 */
	public static function execute_autopilot_cycle() {
		$credits_data = self::check_live_brevo_credits();
		$credits      = $credits_data['credits'] ?? 0;
		$today_utc    = gmdate( 'Y-m-d' );
		$now_formatted = current_time( 'mysql' );

		self::log( "Autopilot cycle started at {$now_formatted}. Brevo Credits Available: {$credits}." );

		// 1. Evaluate Batch 2 (Campaign #264)
		$batch2_status = self::get_campaign_status( self::BATCH_2_CAMPAIGN_ID );
		if ( 'draft' === $batch2_status ) {
			// Trigger on or after Nov 8, 2026 once Brevo credits reset to >= 240
			if ( $credits >= 240 && strcmp( $today_utc, '2026-11-08' ) >= 0 ) {
				self::log( "Executing automated dispatch of Batch 2 (Campaign #264, 260 recipients)..." );
				$res = self::dispatch_campaign( self::BATCH_2_CAMPAIGN_ID, 91 );
				if ( ! is_wp_error( $res ) ) {
					self::log( "SUCCESS: Batch 2 dispatched successfully via Brevo SMTP!" );
				} else {
					self::log( "ERROR dispatching Batch 2: " . $res->get_error_message() );
				}
				return;
			} else {
				self::log( "Batch 2 standing by. Requirements: Nov 8+ (Current: {$today_utc}) & Credits >= 240 (Current: {$credits})." );
			}
		}

		// 2. Evaluate Batch 3 (Campaign #265)
		$batch3_status = self::get_campaign_status( self::BATCH_3_CAMPAIGN_ID );
		if ( 'draft' === $batch3_status && 'sent' === $batch2_status ) {
			// Trigger on or after Nov 9, 2026 once Brevo credits reset to >= 230
			if ( $credits >= 230 && strcmp( $today_utc, '2026-11-09' ) >= 0 ) {
				self::log( "Executing automated dispatch of Batch 3 (Campaign #265, 235 recipients)..." );
				$res = self::dispatch_campaign( self::BATCH_3_CAMPAIGN_ID, 92 );
				if ( ! is_wp_error( $res ) ) {
					self::log( "SUCCESS: Batch 3 dispatched successfully via Brevo SMTP!" );
				} else {
					self::log( "ERROR dispatching Batch 3: " . $res->get_error_message() );
				}
				return;
			} else {
				self::log( "Batch 3 standing by. Requirements: Nov 9+ & Credits >= 230 (Current: {$credits})." );
			}
		}

		self::log( "Autopilot cycle completed cleanly. No pending batches ready for immediate execution." );
	}

	/**
	 * Query Brevo API for Live Balance
	 */
	public static function check_live_brevo_credits() {
		$cached = get_transient( 'tc_brain_brevo_credits_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$key = get_option( 'sib_api_key_v3' );
		if ( empty( $key ) ) {
			$fluentmail = get_option( 'fluentmail-settings' );
			$key = $fluentmail['connections']['brevo']['provider_settings']['api_key'] ?? '';
		}

		if ( empty( $key ) ) {
			return array( 'credits' => 10, 'email' => 'sales@tripcosmos.co' );
		}

		$res = wp_remote_get( 'https://api.brevo.com/v3/account', array(
			'headers' => array(
				'api-key' => $key,
				'accept'  => 'application/json',
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $res ) ) {
			return array( 'credits' => 10, 'email' => 'sales@tripcosmos.co' );
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$credits = 0;
		if ( ! empty( $body['plan'] ) && is_array( $body['plan'] ) ) {
			foreach ( $body['plan'] as $p ) {
				if ( isset( $p['credits'] ) ) {
					$credits = (int) $p['credits'];
					break;
				}
			}
		}

		$data = array(
			'credits'   => $credits,
			'email'     => $body['email'] ?? 'sales@tripcosmos.co',
			'checked_at' => current_time( 'mysql' ),
		);

		set_transient( 'tc_brain_brevo_credits_cache', $data, 300 ); // 5-min cache
		update_option( 'tc_brain_brevo_last_credits', $credits );

		return $data;
	}

	/**
	 * Programmatically Dispatch FluentCRM Campaign
	 */
	public static function dispatch_campaign( $campaign_id, $tag_id ) {
		global $wpdb;

		if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
			return new WP_Error( 'fluentcrm_missing', 'FluentCRM classes not loaded.' );
		}

		$campaign = \FluentCrm\App\Models\Campaign::find( $campaign_id );
		if ( ! $campaign ) {
			return new WP_Error( 'campaign_not_found', "Campaign #{$campaign_id} does not exist." );
		}

		if ( 'draft' !== $campaign->status ) {
			return new WP_Error( 'invalid_status', "Campaign #{$campaign_id} is in status: {$campaign->status}." );
		}

		// Ensure settings and target tag are applied
		$settings = $campaign->settings;
		$settings['sending_type'] = 'instant';
		$settings['click_tracker'] = function_exists( 'fluentcrmTrackClicking' ) ? fluentcrmTrackClicking() : 'yes';
		$settings['open_tracker']  = function_exists( 'fluentcrmTrackEmailOpen' ) ? fluentcrmTrackEmailOpen() : 'yes';
		$settings['sending_filter'] = 'list_tag';
		$settings['subscribers']    = array(
			array(
				'list' => 'all',
				'tag'  => (string) $tag_id,
			),
		);

		// Clean queue rows & reset cursors
		\FluentCrm\App\Models\CampaignEmail::where( 'campaign_id', $campaign_id )->delete();
		fluentcrm_update_campaign_meta( $campaign_id, '_recipient_processed', 0 );
		fluentcrm_update_campaign_meta( $campaign_id, '_last_recipient_id', 0 );
		\FluentCrm\App\Services\CampaignProcessor::clearQueueFailures( $campaign_id );

		// Set status to processing
		$data = array(
			'status'           => 'processing',
			'updated_at'       => function_exists( 'fluentCrmTimestamp' ) ? fluentCrmTimestamp() : current_time( 'mysql' ),
			'scheduled_at'     => function_exists( 'fluentCrmTimestamp' ) ? fluentCrmTimestamp() : current_time( 'mysql' ),
			'settings'         => maybe_serialize( $settings ),
		);

		\FluentCrm\App\Models\Campaign::where( 'id', $campaign_id )->update( $data );
		fluentcrm_update_campaign_meta( $campaign_id, '_campaign_sent_by', 1 );

		// Trigger scheduler
		\FluentCrm\App\Hooks\Handlers\Scheduler::processCampaignById( $campaign_id );

		// Update agency repository status in wp_tc_agent_agencies
		$batch_emails = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT s.email 
			FROM {$wpdb->prefix}fc_subscribers s
			JOIN {$wpdb->prefix}fc_subscriber_pivot p ON p.subscriber_id = s.id
			WHERE p.object_id = %d 
			  AND p.object_type LIKE '%%Tag%%'
		", $tag_id ) );

		if ( ! empty( $batch_emails ) ) {
			$table = $wpdb->prefix . 'tc_agent_agencies';
			$placeholders = implode( ',', array_fill( 0, count( $batch_emails ), '%s' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET status = 'contacted', last_outreach_at = NOW() WHERE email IN ($placeholders)",
				$batch_emails
			) );
		}

		return true;
	}

	/**
	 * Get Campaign Status
	 */
	public static function get_campaign_status( $campaign_id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}fc_campaigns WHERE id = %d", $campaign_id ) );
	}

	/**
	 * REST API: Voice Assistant Brain & Mobile App Endpoint
	 */
	public static function register_voice_routes() {
		register_rest_route( 'tc-agents/v1', '/voice-brain', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_voice_brain_query' ),
			'permission_callback' => '__return_true', // Publicly accessible from Widget and Mobile Apps
		) );
	}

	/**
	 * Voice Brain Query Handler (Processes speech from Web & TripCosmos.co App)
	 */
	public static function handle_voice_brain_query( WP_REST_Request $request ) {
		$query       = sanitize_text_field( $request->get_param( 'message' ) ?: $request->get_param( 'query' ) );
		$phone       = sanitize_text_field( $request->get_param( 'phone' ) );
		$name        = sanitize_text_field( $request->get_param( 'name' ) );
		$channel     = sanitize_text_field( $request->get_param( 'channel' ) ?: 'voice_assistant' );
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) ?: 'v_' . wp_generate_password( 8, false ) );

		if ( empty( $query ) ) {
			return new WP_REST_Response( array(
				'reply'      => 'Namaste! Please speak or ask about Varanasi ghats, Dev Diwali boat charters, or cab fares.',
				'status'     => 'empty_input',
			), 200 );
		}

		// Intelligent Tariffs & Entity Extractor
		$q_lower = strtolower( $query );
		$detected_corridor = 'General Pilgrimage';
		$quoted_price = 'Custom Tariff';

		if ( strpos( $q_lower, 'ayodhya' ) !== false ) {
			$detected_corridor = 'Varanasi to Ayodhya Cab';
			$quoted_price = '₹6,500 (Dzire Sedan) / ₹9,500 (Innova Crysta)';
			$reply = "Namaste! Our Varanasi to Ayodhya Ram Mandir private cab is {$quoted_price} including toll and parking. Would you like our chauffeur details on WhatsApp?";
		} elseif ( strpos( $q_lower, 'diwali' ) !== false || strpos( $q_lower, 'deepawali' ) !== false || strpos( $q_lower, 'boat' ) !== false || strpos( $q_lower, 'bajra' ) !== false ) {
			$detected_corridor = 'Dev Deepawali Bajra Charter';
			$quoted_price = 'Starting ₹35,000 for private double-decker Bajra';
			$reply = "Har Har Mahadev! Dev Deepawali boat charters range from private wooden boats at ₹15,000 to luxury two-deck Bajras at ₹45,000 with Ganga Aarti view. Shall I reserve a slot for you?";
		} elseif ( strpos( $q_lower, 'vip' ) !== false || strpos( $q_lower, 'darshan' ) !== false || strpos( $q_lower, 'kashi' ) !== false || strpos( $q_lower, 'vishwanath' ) !== false ) {
			$detected_corridor = 'Kashi Vishwanath Sugam Darshan';
			$quoted_price = '₹4,500 (Pass + Vedic Guide Assistance)';
			$reply = "Har Har Mahadev! Kashi Vishwanath VIP Sugam Darshan allows direct sanctum access skipping the 4-hour queue. We also arrange Sankat Mochan and Annapurna darshan. What date are you visiting?";
		} elseif ( strpos( $q_lower, 'prayagraj' ) !== false || strpos( $q_lower, 'sangam' ) !== false || strpos( $q_lower, 'allahabad' ) !== false ) {
			$detected_corridor = 'Varanasi to Prayagraj Sangam Cab';
			$quoted_price = '₹4,500 (Dzire Sedan) / ₹6,800 (Innova Crysta)';
			$reply = "Namaste! Varanasi to Prayagraj Triveni Sangam same-day round-trip cab is {$quoted_price} including boat coordination at Sangam Ghat. When are you traveling?";
		} elseif ( strpos( $q_lower, 'bodhgaya' ) !== false || strpos( $q_lower, 'gaya' ) !== false ) {
			$detected_corridor = 'Varanasi to Gaya & Bodhgaya Tour';
			$quoted_price = '₹7,500 (Dzire Sedan) / ₹11,000 (Innova Crysta)';
			$reply = "Namaste! Our Varanasi to Bodhgaya and Gaya Pind Daan private cab package is {$quoted_price} for same-day return. Would you like itinerary details?";
		} elseif ( strpos( $q_lower, 'airport' ) !== false || strpos( $q_lower, 'station' ) !== false || strpos( $q_lower, 'pickup' ) !== false ) {
			$detected_corridor = 'Varanasi Station / Airport Transfer';
			$quoted_price = '₹1,200 (Sedan) / ₹1,800 (Ertiga / Innova)';
			$reply = "Namaste! Varanasi Airport or Cantt Railway Station transfers start at {$quoted_price} with verified driver and luggage assistance. What is your train or flight time?";
		} elseif ( class_exists( 'TC_Agent_Orchestrator' ) ) {
			// Leverage Deep RAG Orchestrator
			$meta = array(
				'channel'   => 'voice_assistant',
				'session_id'=> $session_id,
				'name'      => $name,
				'phone'     => $phone,
			);
			$orchestrator_res = TC_Agent_Orchestrator::handle_message( $query, $session_id, 'voice_assistant', 'tripcosmos-guide', $meta );
			if ( ! is_wp_error( $orchestrator_res ) && ! empty( $orchestrator_res['reply'] ) ) {
				$raw_reply = wp_strip_all_tags( $orchestrator_res['reply'] );
				$clean_reply = preg_replace( '/[\*\#\`\_]/', '', $raw_reply );
				$clean_reply = preg_replace( '/\s+/', ' ', $clean_reply );
				$reply = mb_substr( trim( $clean_reply ), 0, 320 );
			} else {
				$reply = "Namaste! TripCosmos arranges VIP Kashi Darshan passes, Ganga Aarti boat charters, and AC cabs across Varanasi, Ayodhya, and Prayagraj. Tell me your travel dates to get exact pricing.";
			}
		} else {
			$reply = "Namaste! TripCosmos arranges VIP Kashi Darshan passes, Ganga Aarti boat charters, and AC cabs across Varanasi, Ayodhya, and Prayagraj. Tell me your travel dates to get exact pricing.";
		}

		// Sync Lead into X-Agents / CRM
		if ( ! empty( $phone ) ) {
			self::sync_voice_lead_to_crm( $name ?: 'Voice Traveler', $phone, $query, $detected_corridor, $channel );
		}

		return new WP_REST_Response( array(
			'reply'             => $reply,
			'speech_text'       => $reply,
			'detected_corridor' => $detected_corridor,
			'indicative_price'  => $quoted_price,
			'whatsapp_hotline'  => '+91 93361 16210',
			'session_id'        => $session_id,
		), 200 );
	}

	/**
	 * Sync Captured Lead to X-Agents CRM & Event Radar
	 */
	private static function sync_voice_lead_to_crm( $name, $phone, $query, $corridor, $channel ) {
		global $wpdb;
		$clean_phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( empty( $clean_phone ) || strlen( preg_replace( '/\D/', '', $clean_phone ) ) < 10 ) {
			return;
		}

		$now = current_time( 'mysql' );
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';
		$table_audit    = $wpdb->prefix . 'tc_agent_audit_log';
		$lead_id = 0;

		$meta = array(
			'voice_query'       => $query,
			'detected_corridor' => $corridor,
			'last_channel'      => $channel ?: 'voice_assistant',
			'timestamp'         => $now,
		);

		// 1. Check if contact exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, meta_data, notes FROM {$table_contacts} WHERE phone LIKE %s ORDER BY id DESC LIMIT 1",
			'%' . substr( preg_replace( '/\D/', '', $clean_phone ), -10 )
		), ARRAY_A );

		if ( $existing ) {
			$lead_id = (int) $existing['id'];
			$wpdb->update(
				$table_contacts,
				array(
					'updated_at' => $now,
					'meta_data'  => maybe_serialize( $meta ),
				),
				array( 'id' => $lead_id )
			);
		} else {
			$wpdb->insert(
				$table_contacts,
				array(
					'name'           => $name ?: 'Voice Traveler',
					'phone'          => $clean_phone,
					'phone_e164'     => $clean_phone,
					'source_channel' => $channel ?: 'voice_assistant',
					'stage'          => 'inquiry',
					'score'          => 75,
					'meta_data'      => maybe_serialize( $meta ),
					'created_at'     => $now,
					'updated_at'     => $now,
				)
			);
			$lead_id = (int) $wpdb->insert_id;
		}

		// 2. Insert high-priority real-time event for X-Agents Radar & Mobile Push
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_audit ) ) === $table_audit ) {
			$wpdb->insert(
				$table_audit,
				array(
					'event_type' => 'lead_captured',
					'channel'    => $channel ?: 'voice_assistant',
					'details'    => wp_json_encode( array(
						'lead_id'   => $lead_id,
						'name'      => $name ?: 'Voice Traveler',
						'phone'     => $clean_phone,
						'corridor'  => $corridor,
						'query'     => $query,
						'source'    => 'TripCosmos Voice Brain',
					) ),
					'created_at' => $now,
				)
			);
		}
	}

	/**
	 * AJAX Trigger: Manual On-Demand Batch Trigger from Sovereign Workspace
	 */
	public static function ajax_trigger_batch() {
		check_ajax_referer( 'tc_workspace_nonce', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Administrator authentication required.' );
		}

		$batch = isset( $_POST['batch'] ) ? (int) $_POST['batch'] : 2;
		$campaign_id = ( 3 === $batch ) ? self::BATCH_3_CAMPAIGN_ID : self::BATCH_2_CAMPAIGN_ID;
		$tag_id      = ( 3 === $batch ) ? 92 : 91;

		$res = self::dispatch_campaign( $campaign_id, $tag_id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}

		self::log( "Manual override: Batch {$batch} (Campaign #{$campaign_id}) triggered by Administrator." );
		wp_send_json_success( array(
			'message'     => "Batch {$batch} (Campaign #{$campaign_id}) dispatched successfully!",
			'campaign_id' => $campaign_id,
		) );
	}

	/**
	 * AJAX Status Check
	 */
	public static function ajax_get_status() {
		check_ajax_referer( 'tc_workspace_nonce', '_wpnonce' );

		$credits = self::check_live_brevo_credits();
		$logs    = get_option( self::LOG_OPTION, array() );

		wp_send_json_success( array(
			'credits'   => $credits,
			'logs'      => array_slice( (array) $logs, -5 ),
			'batch2_status' => self::get_campaign_status( self::BATCH_2_CAMPAIGN_ID ),
			'batch3_status' => self::get_campaign_status( self::BATCH_3_CAMPAIGN_ID ),
		) );
	}

	/**
	 * Internal Logger
	 */
	public static function log( $message ) {
		$logs   = (array) get_option( self::LOG_OPTION, array() );
		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		);
		// Retain last 50 logs
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}
		update_option( self::LOG_OPTION, $logs );
	}
}
