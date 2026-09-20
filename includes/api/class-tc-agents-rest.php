<?php
/**
 * REST API Endpoints for Chat Widget, WhatsApp Webhook, Health, Guardrails, Leads & History.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_REST {

	const NAMESPACE = 'tc-agents/v1';

	public static function register_routes() {
		// Enable permissive CORS for widget, headless apps, and Android clients
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'send_cors_headers' ), 10, 4 );

		// 1. Chat Endpoint (Widget, Mobile & Console)
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( __CLASS__, 'handle_chat' ),
				'permission_callback' => '__return_true', // Widget is public, guarded by session/rate limits
			)
		);

		// 2. Conversation History Endpoint (Session Restore for Widget & Mobile App)
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<session_id>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_conversation_history' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2b. Lead Capture Endpoint (Instant Quote Form & Fast Lead Sync)
		register_rest_route(
			self::NAMESPACE,
			'/lead-capture',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_lead_capture' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2c. Direct AI Call Trigger (In-Chat Instant Phone Call Request)
		register_rest_route(
			self::NAMESPACE,
			'/trigger-call',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_trigger_call' ),
				'permission_callback' => '__return_true',
			)
		);

		// 3. Health Check Endpoint
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_health' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// 4. Kill Switch Endpoint (Admin toggle)
		register_rest_route(
			self::NAMESPACE,
			'/kill-switch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_kill_switch' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// 5. WhatsApp Inbound Webhook
		register_rest_route(
			self::NAMESPACE,
			'/whatsapp-webhook',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( 'TC_Integration_WhatsApp', 'handle_incoming_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// 6. Voice Inbound Webhook
		register_rest_route(
			self::NAMESPACE,
			'/voice-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'TC_Integration_Voice', 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// 7. Lead Stage Update Endpoint (Quick Kanban Stage Change & Webhook integration)
		register_rest_route(
			self::NAMESPACE,
			'/leads/(?P<id>\d+)/stage',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_update_lead_stage' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// 8. Lead Traveler Memory Endpoint (REST synthesis inspection)
		register_rest_route(
			self::NAMESPACE,
			'/leads/(?P<id>\d+)/memory',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_lead_memory' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// 9. Twenty CRM Inbound Webhook
		register_rest_route(
			self::NAMESPACE,
			'/twentycrm-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'TC_Integration_TwentyCRM', 'handle_incoming_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// 10. Live model catalogue sync (admin).
		register_rest_route(
			self::NAMESPACE,
			'/models/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_sync_models' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// 11. B2B agency import + outreach (admin).
		register_rest_route(
			self::NAMESPACE,
			'/b2b/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_b2b_import' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/b2b/outreach',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_b2b_outreach' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/b2b/sync-crm',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_b2b_sync_crm' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Send CORS headers for mobile and external headless frontends.
	 */
	public static function send_cors_headers( $served, $result, $request, $server ) {
		if ( strpos( $request->get_route(), self::NAMESPACE ) !== false ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Webhook-Secret' );
		}
		return $served;
	}

	/**
	 * Handle Chat Message.
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		// Support both JSON body and form-encoded data
		$params  = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$message = sanitize_text_field( $params['message'] ?? '' );
		$session = sanitize_text_field( $params['session_id'] ?? '' );
		$channel = sanitize_text_field( $params['channel'] ?? 'web' );
		$agent   = sanitize_text_field( $params['agent_slug'] ?? 'tripcosmos-guide' );

		if ( empty( $message ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Message content cannot be empty.', 'tripcosmos-agents' ) ), 400 );
		}

		if ( empty( $session ) ) {
			$session = 'anon_' . wp_generate_uuid4();
		}

		// Check if frontend chatbot is disabled for public web visitors
		if ( 'web' === $channel && '1' !== (string) get_option( 'tc_agents_widget_enabled', '1' ) ) {
			return new WP_REST_Response( array( 'error' => __( 'The website chatbot is currently disabled.', 'tripcosmos-agents' ) ), 503 );
		}

		// Security: Admin console requests must verify nonce
		if ( 'admin' === $channel ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 403 );
			}
			$nonce = $request->get_header( 'x-wp-nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
			}
		}

		$metadata = array(
			'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'page_url'   => esc_url_raw( $params['page_url'] ?? '' ),
			'page_title' => sanitize_text_field( $params['page_title'] ?? '' ),
			'language'   => sanitize_text_field( $params['language'] ?? 'en' ),
		);

		$is_stream = ! empty( $params['stream'] );

		// Handle Streaming SSE if requested
		if ( $is_stream ) {
			self::stream_chat_response( $message, $session, $channel, $agent, $metadata );
			exit;
		}

		$result = TC_Agent_Orchestrator::handle_message( $message, $session, $channel, $agent, $metadata );

		if ( is_wp_error( $result ) ) {
			$status_code = $result->get_error_data()['status'] ?? 500;
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status_code
			);
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'session_id'     => $result['session_id'],
				'reply'          => $result['reply'],
				'handoff'        => $result['handoff'],
				'executed_tools' => $result['executed_tools'] ?? array(),
				'provider_used'  => $result['provider_used'],
				'latency_ms'     => $result['latency_ms'],
			),
			200
		);
	}

	/**
	 * Retrieve message history for a conversation session (for Widget or Mobile App restoration).
	 */
	public static function handle_get_conversation_history( WP_REST_Request $request ) {
		global $wpdb;
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) );
		$table_convo = $wpdb->prefix . 'tc_agent_conversations';
		$table_msg   = $wpdb->prefix . 'tc_agent_messages';

		$convo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_convo WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );
		if ( ! $convo ) {
			return new WP_REST_Response( array( 'session_id' => $session_id, 'messages' => array() ), 200 );
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, provider_used, created_at FROM $table_msg WHERE conversation_id = %d AND role IN ('user', 'assistant') AND content != '' ORDER BY id ASC LIMIT 50",
				$convo['id']
			),
			ARRAY_A
		) ?: array();

		return new WP_REST_Response(
			array(
				'session_id' => $session_id,
				'channel'    => $convo['channel'],
				'status'     => $convo['status'],
				'messages'   => $messages,
			),
			200
		);
	}

	/**
	 * Stream Chat Response via Server-Sent Events (SSE).
	 */
	public static function stream_chat_response( $message, $session, $channel, $agent, $metadata ) {
		// Prevent LiteSpeed Cache and web server buffering
		if ( function_exists( 'apache_setenv' ) ) {
			apache_setenv( 'no-gzip', '1' );
		}
		ini_set( 'zlib.output_compression', '0' );
		ini_set( 'implicit_flush', '1' );

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Nginx / LiteSpeed buffer bypass

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();

		$result = TC_Agent_Orchestrator::handle_message( $message, $session, $channel, $agent, $metadata );

		if ( is_wp_error( $result ) ) {
			echo 'data: ' . wp_json_encode( array( 'type' => 'error', 'error' => $result->get_error_message() ) ) . "\n\n";
			flush();
			return;
		}

		// Stream tokens/words smoothly
		$reply_text = $result['reply'] ?? '';
		$words      = preg_split( '/(\s+)/', $reply_text, -1, PREG_SPLIT_DELIM_CAPTURE );

		foreach ( $words as $chunk ) {
			echo 'data: ' . wp_json_encode( array( 'type' => 'chunk', 'text' => $chunk ) ) . "\n\n";
			flush();
			usleep( 25000 ); // 25ms delay for human-like natural typewriter cadence
		}

		// Send final metadata
		echo 'data: ' . wp_json_encode(
			array(
				'type'           => 'done',
				'session_id'     => $result['session_id'],
				'reply'          => $result['reply'],
				'handoff'        => $result['handoff'],
				'executed_tools' => $result['executed_tools'] ?? array(),
				'provider_used'  => $result['provider_used'],
				'latency_ms'     => $result['latency_ms'],
			)
		) . "\n\n";
		flush();
	}

	/**
	 * Update Lead Stage via REST.
	 */
	public static function handle_update_lead_stage( WP_REST_Request $request ) {
		global $wpdb;
		$lead_id = absint( $request->get_param( 'id' ) );
		$params  = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$stage   = sanitize_text_field( $params['stage'] ?? 'inquiry' );

		$allowed_stages = array( 'inquiry', 'qualified', 'proposal', 'negotiation', 'won', 'lost' );
		if ( ! in_array( $stage, $allowed_stages, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid stage value' ), 400 );
		}

		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';
		$updated = $wpdb->update(
			$table_contacts,
			array(
				'stage'      => $stage,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id )
		);

		if ( false === $updated ) {
			return new WP_REST_Response( array( 'error' => 'Database update failed' ), 500 );
		}

		// If marked won, cancel active drip sequences
		if ( 'won' === $stage && class_exists( 'TC_Agent_Sequences' ) ) {
			TC_Agent_Sequences::cancel_for_contact( $lead_id );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'lead_id' => $lead_id,
				'stage'   => $stage,
			),
			200
		);
	}

	/**
	 * Retrieve Lead Memory via REST.
	 */
	public static function handle_get_lead_memory( WP_REST_Request $request ) {
		$contact_id = absint( $request->get_param( 'id' ) );
		if ( ! $contact_id ) {
			return new WP_REST_Response( array( 'error' => 'Invalid contact ID' ), 400 );
		}

		$mem = TC_Agent_Memory::get_memory( $contact_id );
		if ( ! $mem ) {
			return new WP_REST_Response( array( 'contact_id' => $contact_id, 'memory' => null ), 200 );
		}

		return new WP_REST_Response(
			array(
				'contact_id' => $contact_id,
				'memory'     => array(
					'summary'          => $mem->summary,
					'facts'            => TC_Agent_Memory::decode_list( $mem->facts ),
					'preferences'      => TC_Agent_Memory::decode_list( $mem->preferences ),
					'objections'       => TC_Agent_Memory::decode_list( $mem->objections ),
					'next_best_action' => $mem->next_best_action,
					'updated_at'       => $mem->updated_at,
				),
			),
			200
		);
	}

	/**
	 * Handle Fast Lead Capture (Instant Quote Form & In-Widget Lead Generator).
	 */
	public static function handle_lead_capture( WP_REST_Request $request ) {
		$params      = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$name        = sanitize_text_field( $params['name'] ?? '' );
		$phone       = sanitize_text_field( $params['phone'] ?? '' );
		$email       = sanitize_email( $params['email'] ?? '' );
		$destination = sanitize_text_field( $params['destination'] ?? '' );
		$group_size  = sanitize_text_field( $params['group_size'] ?? '' );
		$month       = sanitize_text_field( $params['travel_month'] ?? '' );
		$session_id  = sanitize_text_field( $params['session_id'] ?? '' );

		if ( empty( $name ) && empty( $phone ) && empty( $email ) ) {
			return new WP_REST_Response( array( 'error' => 'Please provide at least a name and phone number or email.' ), 400 );
		}

		$result = TC_Agent_Tools::execute(
			'sync_lead_crm',
			array(
				'name'         => $name,
				'phone'        => $phone,
				'email'        => $email,
				'destination'  => $destination,
				'group_size'   => $group_size,
				'travel_month' => $month,
			),
			array(
				'channel'    => 'web_quote_form',
				'session_id' => $session_id,
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
				'message' => __( 'Your trip inquiry has been received! Our expedition specialist will review your request.', 'tripcosmos-agents' ),
			),
			200
		);
	}

	/**
	 * Handle Instant Outbound AI Call Trigger from Chat Widget.
	 */
	public static function handle_trigger_call( WP_REST_Request $request ) {
		$params     = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$phone      = sanitize_text_field( $params['phone'] ?? '' );
		$name       = sanitize_text_field( $params['name'] ?? '' );
		$reason     = sanitize_text_field( $params['reason'] ?? 'Himalayan expedition inquiry from website' );
		$session_id = sanitize_text_field( $params['session_id'] ?? '' );

		if ( empty( $phone ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'A valid phone number is required.' ), 400 );
		}

		$result = TC_Agent_Tools::execute(
			'request_voice_call',
			array(
				'phone'         => $phone,
				'traveler_name' => $name,
				'reason'        => $reason,
			),
			array(
				'channel'    => 'web_chat_widget',
				'session_id' => $session_id,
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response( $result, 400 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle Health Check request.
	 */
	public static function handle_health( WP_REST_Request $request ) {
		$router  = TC_AI_Router::get_instance();
		$healths = $router->check_all_health();
		return new WP_REST_Response( array( 'providers' => $healths ), 200 );
	}

	public static function handle_sync_models( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$force = ! empty( $params['force'] );
		$router = TC_AI_Router::get_instance();
		$models = $router->sync_all_models( $force );
		return new WP_REST_Response( array( 'success' => true, 'models' => $models, 'synced_at' => current_time( 'mysql' ) ), 200 );
	}

	public static function handle_b2b_import( WP_REST_Request $request ) {
		$params   = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$city     = sanitize_text_field( $params['city'] ?? '' );
		$raw_list = $params['raw_list'] ?? '';

		if ( ! empty( $raw_list ) ) {
			$lines    = explode( "\n", str_replace( "\r", '', $raw_list ) );
			$agencies = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				$parts = array_map( 'trim', preg_split( '/[,\t|]/', $line ) );
				if ( count( $parts ) >= 1 && ! empty( $parts[0] ) ) {
					$agencies[] = array(
						'name'    => $parts[0],
						'city'    => $parts[1] ?? ( $city ?: 'India' ),
						'phone'   => $parts[2] ?? '',
						'email'   => $parts[3] ?? '',
						'source'  => 'manual',
					);
				}
			}
			if ( ! empty( $agencies ) ) {
				$res = TC_Integration_Google_Business::upsert_agencies( $agencies, $city, 'manual' );
				return new WP_REST_Response( array( 'success' => true, 'data' => $res ), 200 );
			}
		}

		if ( '' !== $city ) {
			$res = TC_Agent_Tools::execute( 'import_b2b_agencies', array( 'city' => $city, 'max_results' => 20 ) );
			return new WP_REST_Response( array( 'success' => empty( $res['error'] ), 'data' => $res ), empty( $res['error'] ) ? 200 : 400 );
		}
		if ( class_exists( 'TC_Agents_Queue' ) ) { TC_Agents_Queue::push( 'import_b2b', array(), 0 ); }
		return new WP_REST_Response( array( 'success' => true, 'queued' => true ), 200 );
	}

	public static function handle_b2b_outreach( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$id = absint( $params['agency_id'] ?? 0 );
		if ( $id > 0 ) {
			$res = TC_Agent_Tools::execute( 'outreach_b2b_agency', array( 'agency_id' => $id, 'channel' => sanitize_key( $params['channel'] ?? 'both' ) ) );
			return new WP_REST_Response( array( 'success' => empty( $res['error'] ), 'data' => $res ), empty( $res['error'] ) ? 200 : 400 );
		}
		if ( class_exists( 'TC_Agents_Queue' ) ) { TC_Agents_Queue::push( 'outreach_b2b_batch', array( 'limit' => 10 ), 0 ); }
		return new WP_REST_Response( array( 'success' => true, 'queued' => true ), 200 );
	}

	public static function handle_b2b_sync_crm( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$id     = absint( $params['agency_id'] ?? 0 );
		if ( ! $id ) {
			return new WP_REST_Response( array( 'error' => 'agency_id required' ), 400 );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'tc_agent_agencies';
		$agency = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		if ( ! $agency ) {
			return new WP_REST_Response( array( 'error' => 'Agency not found' ), 404 );
		}

		// Push to FluentCRM
		$fluent_id = false;
		if ( class_exists( 'TC_Integration_FluentCRM' ) && TC_Integration_FluentCRM::is_active() ) {
			$fluent_id = TC_Integration_FluentCRM::sync_lead( array(
				'name'        => $agency['name'],
				'email'       => $agency['email'] ?: ( 'agency-' . $agency['id'] . '@b2b.tripcosmos.co' ),
				'phone'       => $agency['phone'],
				'destination' => $agency['city'] ?: 'Varanasi',
				'channel'     => 'b2b_partner',
				'stage'       => 'b2b_partner',
				'score'       => 85,
			) );
		}

		// Push to Twenty CRM
		$twenty_id = false;
		if ( class_exists( 'TC_Integration_TwentyCRM' ) && TC_Integration_TwentyCRM::is_configured() ) {
			$twenty_id = TC_Integration_TwentyCRM::push_lead( array(
				'name'         => $agency['name'],
				'email'        => $agency['email'],
				'phone'        => $agency['phone'],
				'destination'  => $agency['city'],
				'requirements' => 'B2B Travel Agent Partnership: Ground Handling, Cabs & Hotel Allotments',
				'deal_value'   => 50000.00,
			) );
		}

		$wpdb->update( $table, array( 'status' => 'in_crm' ), array( 'id' => $id ) );

		return new WP_REST_Response( array(
			'success'   => true,
			'fluent_id' => $fluent_id,
			'twenty_id' => $twenty_id,
			'message'   => __( 'Agency synced to CRM!', 'tripcosmos-agents' ),
		), 200 );
	}

	/**
	 * Handle Kill Switch Toggle.
	 */
	public static function handle_kill_switch( WP_REST_Request $request ) {
		$params  = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$enabled = ! empty( $params['active'] ) ? '1' : '0';

		update_option( 'tc_agents_kill_switch', $enabled );

		TC_Agents_Logger::log(
			'kill_switch_toggled',
			'critical',
			array(
				'new_state' => $enabled ? 'ACTIVE (ALL AGENTS STOPPED)' : 'INACTIVE (OPERATIONAL)',
				'user_id'   => get_current_user_id(),
			)
		);

		return new WP_REST_Response(
			array(
				'success'     => true,
				'kill_switch' => $enabled,
				'message'     => $enabled ? __( 'Emergency Kill Switch is now ENGAGED. All outbound agent activities are stopped.', 'tripcosmos-agents' ) : __( 'Kill Switch DISENGAGED. Agent operations resumed.', 'tripcosmos-agents' ),
			),
			200
		);
	}
}
