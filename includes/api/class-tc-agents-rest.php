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
		add_filter( 'rest_allowed_cors_headers', function( $headers ) {
			$headers[] = 'X-Mobile-Token';
			$headers[] = 'X-Requested-With';
			return $headers;
		} );

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

		// 2b. Real-Time Polling for Frontend Website Chatbot Widget (Human agent replies)
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<session_id>[a-zA-Z0-9_\-]+)/poll',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_poll_conversation' ),
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

		// 2d. Visitor Landing & Live Activity Tracker
		register_rest_route(
			self::NAMESPACE,
			'/track-visitor',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_track_visitor' ),
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

		// 5. WhatsApp Inbound Webhook (standard and evolution alias)
		register_rest_route(
			self::NAMESPACE,
			'/whatsapp-webhook',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( 'TC_Integration_WhatsApp', 'handle_incoming_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/webhook/evolution',
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

		// 6b. Voice AI RAG Query (Companion App & Voice Playground)
		register_rest_route(
			self::NAMESPACE,
			'/voice/query-rag',
			array(
				'methods'             => array( 'POST', 'OPTIONS' ),
				'callback'            => array( __CLASS__, 'handle_voice_query_rag' ),
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
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_options' ) || ( class_exists( 'TC_Agents_Mobile_API' ) && TC_Agents_Mobile_API::verify_token( $request ) === true );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/b2b/outreach',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_b2b_outreach' ),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_options' ) || ( class_exists( 'TC_Agents_Mobile_API' ) && TC_Agents_Mobile_API::verify_token( $request ) === true );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/b2b/sync-crm',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_b2b_sync_crm' ),
				'permission_callback' => function( $request ) {
					return current_user_can( 'manage_options' ) || ( class_exists( 'TC_Agents_Mobile_API' ) && TC_Agents_Mobile_API::verify_token( $request ) === true );
				},
			)
		);
	}

	/**
	 * Emit Access-Control-Allow-Origin only for this site (plus origins added via the
	 * `tc_agents_allowed_origins` filter). Native apps and server webhooks don't use CORS.
	 */
	public static function send_allowed_origin() {
		$origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$allowed  = apply_filters( 'tc_agents_allowed_origins', array( untrailingslashit( home_url() ), untrailingslashit( site_url() ) ) );
		$is_local = preg_match( '/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i', $origin );
		if ( $origin && ( in_array( untrailingslashit( $origin ), $allowed, true ) || $is_local ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Vary: Origin' );
		}
	}

	/**
	 * Send CORS headers for mobile and external headless frontends.
	 */
	public static function send_cors_headers( $served, $result, $request, $server ) {
		if ( strpos( $request->get_route(), self::NAMESPACE ) !== false ) {
			self::send_allowed_origin();
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Webhook-Secret, X-Mobile-Token, X-Requested-With' );
		}
		return $served;
	}

	/**
	 * Handle Chat Message.
	 */
	public static function handle_chat( WP_REST_Request $request ) {
		// Handle CORS preflight requests gracefully
		if ( 'OPTIONS' === $request->get_method() ) {
			return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
		}

		// Support both JSON body and form-encoded data
		$params  = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$message = sanitize_text_field( $params['message'] ?? $params['transcript'] ?? '' );
		$session = sanitize_text_field( $params['session_id'] ?? '' );
		$channel = sanitize_text_field( $params['channel'] ?? 'web' );
		$agent   = sanitize_text_field( $params['agent_slug'] ?? 'tripcosmos-guide' );

		if ( empty( $message ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Message content cannot be empty.', 'tripcosmos-agents' ) ), 400 );
		}
		if ( 'admin' !== $channel && mb_strlen( $message ) > 2000 ) {
			return new WP_REST_Response( array( 'error' => __( 'Message is too long. Please keep it under 2000 characters.', 'tripcosmos-agents' ) ), 400 );
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

		$user_ctx = null;
		if ( ! empty( $params['currentUser'] ) && is_array( $params['currentUser'] ) ) {
			$user_ctx = $params['currentUser'];
		} elseif ( ! empty( $params['user'] ) && is_array( $params['user'] ) ) {
			$user_ctx = $params['user'];
		} elseif ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			$user_ctx = array(
				'id'    => (int) $u->ID,
				'name'  => (string) $u->display_name,
				'email' => (string) $u->user_email,
				'phone' => (string) ( get_user_meta( $u->ID, 'billing_phone', true ) ?: get_user_meta( $u->ID, 'phone', true ) ?: '' ),
			);
		}

		$metadata = array(
			'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'page_url'   => esc_url_raw( $params['page_url'] ?? '' ),
			'page_title' => sanitize_text_field( $params['page_title'] ?? '' ),
			'language'   => sanitize_text_field( $params['language'] ?? 'en' ),
			'user'       => $user_ctx,
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
				'executed_tools' => $result['executed_tools'] ?? $result['tools_used'] ?? array(),
				'provider_used'  => $result['provider_used'],
				'latency_ms'     => $result['latency_ms'],
			),
			200
		);
	}

	/**
	 * Voice AI RAG query for voice calls and companion app playground
	 */
	public static function handle_voice_query_rag( WP_REST_Request $request ) {
		$params  = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$message = sanitize_text_field( $params['transcript'] ?? $params['message'] ?? '' );
		$session = sanitize_text_field( $params['session_id'] ?? '' );
		$agent   = sanitize_text_field( $params['agent_slug'] ?? $params['agent'] ?? 'nishkarsh-ai' );

		if ( empty( $message ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Transcript or message cannot be empty.', 'tripcosmos-agents' ) ), 400 );
		}

		if ( empty( $session ) ) {
			$session = 'voice_rag_' . wp_generate_uuid4();
		}

		$metadata = array(
			'channel' => 'voice_playground',
			'source'  => 'x_agents_app',
		);

		$result = TC_Agent_Orchestrator::handle_message( $message, $session, 'voice_playground', $agent, $metadata );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'reply'   => __( 'Namaste! Our travel specialist will connect with you on WhatsApp right away.', 'tripcosmos-agents' ),
					'error'   => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'ok'             => true,
				'session_id'     => $result['session_id'] ?? $session,
				'reply'          => $result['reply'] ?? '',
				'handoff'        => $result['handoff'] ?? array(),
				'executed_tools' => $result['executed_tools'] ?? $result['tools_used'] ?? array(),
				'provider_used'  => $result['provider_used'] ?? '',
				'latency_ms'     => $result['latency_ms'] ?? 0,
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

		// Session ID is the only credential: require an unguessable id and never expose non-web channels
		// (WhatsApp/voice session ids are derived from phone numbers).
		if ( strlen( $session_id ) < 20 && ! current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response( array( 'session_id' => $session_id, 'messages' => array() ), 200 );
		}

		$convo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_convo WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );
		if ( ! $convo || ( 'web' !== $convo['channel'] && ! current_user_can( 'manage_options' ) ) ) {
			return new WP_REST_Response( array( 'session_id' => $session_id, 'messages' => array() ), 200 );
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, role, content, provider_used, created_at FROM $table_msg WHERE conversation_id = %d AND role IN ('user', 'assistant', 'agent') AND content != '' ORDER BY id ASC LIMIT 100",
				$convo['id']
			),
			ARRAY_A
		) ?: array();

		return new WP_REST_Response(
			array(
				'session_id'   => $session_id,
				'channel'      => $convo['channel'],
				'status'       => $convo['status'],
				'human_active' => 'handed_off' === $convo['status'],
				'messages'     => $messages,
			),
			200
		);
	}

	/**
	 * Real-time poll endpoint for the frontend chatbot widget.
	 * Returns new messages (including human agent responses) since `after` ID.
	 */
	public static function handle_poll_conversation( WP_REST_Request $request ) {
		global $wpdb;
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) );
		$after_id    = absint( $request->get_param( 'after' ) );
		$table_convo = $wpdb->prefix . 'tc_agent_conversations';
		$table_msg   = $wpdb->prefix . 'tc_agent_messages';

		$convo = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, channel FROM $table_convo WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );
		if ( ! $convo ) {
			return new WP_REST_Response( array( 'session_id' => $session_id, 'messages' => array(), 'status' => 'active', 'human_active' => false ), 200 );
		}

		$sql = "SELECT id, role, content, provider_used, created_at FROM $table_msg WHERE conversation_id = %d AND role IN ('user', 'assistant', 'agent') AND content != ''";
		if ( $after_id > 0 ) {
			$sql .= $wpdb->prepare( " AND id > %d", $after_id );
		}
		$sql .= " ORDER BY id ASC LIMIT 20";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, (int) $convo['id'] ), ARRAY_A ) ?: array();

		$messages = array();
		foreach ( $rows as $r ) {
			$by = (string) $r['provider_used'];
			$agent_name = 0 === strpos( $by, 'human:' ) ? substr( $by, 6 ) : '';

			$messages[] = array(
				'id'         => (int) $r['id'],
				'role'       => (string) $r['role'],
				'content'    => (string) $r['content'],
				'provider'   => $by,
				'agent_name' => $agent_name,
				'created_at' => (string) $r['created_at'],
			);
		}

		return new WP_REST_Response(
			array(
				'session_id'   => $session_id,
				'status'       => $convo['status'],
				'human_active' => 'handed_off' === $convo['status'],
				'messages'     => $messages,
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
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		self::send_allowed_origin();
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Webhook-Secret' );

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
				'executed_tools' => $result['executed_tools'] ?? $result['tools_used'] ?? array(),
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

		if ( ! isset( TC_Contact_Service::stages()[ $stage ] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid stage value', 'allowed' => array_keys( TC_Contact_Service::stages() ) ), 400 );
		}
		if ( ! TC_Contact_Service::set_stage( $lead_id, $stage, sanitize_text_field( $params['lost_reason'] ?? '' ) ) ) {
			return new WP_REST_Response( array( 'error' => 'Lead not found' ), 404 );
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

		// Public endpoint: throttle per visitor so it can't be used to flood the CRM or trigger follow-up sequences.
		$guard = TC_Agents_Guardrails::check_permission( 'web', 'lead_' . sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( is_wp_error( $guard ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => $guard->get_error_message() ), 429 );
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
				'channel'     => 'web_quote_form',
				'session_id'  => $session_id,
				'consent'     => ! empty( $params['consent'] ),
				'attribution' => array(
					'utm_source'   => $params['utm_source'] ?? '',
					'utm_medium'   => $params['utm_medium'] ?? '',
					'utm_campaign' => $params['utm_campaign'] ?? '',
					'referrer'     => esc_url_raw( $params['referrer'] ?? '' ),
					'landing_page' => esc_url_raw( $params['landing_page'] ?? ( $params['page_url'] ?? '' ) ),
				),
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
				'message' => __( 'Your trip inquiry has been received! Our travel specialist will review your request.', 'tripcosmos-agents' ),
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
		$reason     = sanitize_text_field( $params['reason'] ?? 'TripCosmos pilgrimage & tour inquiry from website' );
		$session_id = sanitize_text_field( $params['session_id'] ?? '' );

		$clean_phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( empty( $clean_phone ) || strlen( preg_replace( '/[^0-9]/', '', $clean_phone ) ) < 10 ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => __( 'A valid 10-digit mobile number is required.', 'tripcosmos-agents' ) ), 400 );
		}

		// Security Rate Limit per IP/Identifier
		$identifier = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		$guard      = TC_Agents_Guardrails::check_permission( 'voice', $identifier );
		if ( is_wp_error( $guard ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => $guard->get_error_message() ), 429 );
		}

		// One callback per number every 6 hours, so this button can't be used to repeatedly ring someone else's phone.
		$cooldown_key = 'tc_call_cd_' . substr( md5( substr( preg_replace( '/[^0-9]/', '', $clean_phone ), -10 ) ), 0, 20 );
		if ( get_transient( $cooldown_key ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => __( 'A callback for this number was requested recently. Our team will call you shortly, or you can reach us on WhatsApp.', 'tripcosmos-agents' ) ), 429 );
		}

		$result = TC_Agent_Tools::execute(
			'request_voice_call',
			array(
				'phone'         => $clean_phone,
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

		set_transient( $cooldown_key, 1, 6 * HOUR_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle Visitor Landing & Page Tracking for Live Android App Alerts.
	 */
	public static function handle_track_visitor( WP_REST_Request $request ) {
		$raw_ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$ua     = strtolower( $raw_ua );
		if ( empty( $ua ) || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|lighthouse|headless|uptime|pingdom/i', $ua ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'ignored' => 'bot' ), 200 );
		}

		$params     = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$page_url   = esc_url_raw( $params['page_url'] ?? '' );
		$page_title = sanitize_text_field( $params['page_title'] ?? 'TripCosmos Home' );
		$session_id = sanitize_text_field( $params['session_id'] ?? '' );
		$action     = sanitize_text_field( $params['action'] ?? 'landed' );
		$referrer   = esc_url_raw( $params['referrer'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) );
		$utm_source = sanitize_text_field( $params['utm_source'] ?? '' );
		$lang       = sanitize_text_field( $params['language'] ?? 'en' );

		$ip      = class_exists( 'TC_Agents_Security' ) ? TC_Agents_Security::client_ip() : sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		$country = sanitize_text_field( $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'IN' );
		$city    = sanitize_text_field( $_SERVER['HTTP_CF_IPCITY'] ?? '' );

		// Parse clean device + browser label
		$os = 'Desktop';
		if ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
			$os = 'iPhone / iOS';
		} elseif ( strpos( $ua, 'android' ) !== false ) {
			$os = 'Android Phone';
		} elseif ( strpos( $ua, 'macintosh' ) !== false || strpos( $ua, 'mac os' ) !== false ) {
			$os = 'Mac';
		} elseif ( strpos( $ua, 'windows' ) !== false ) {
			$os = 'Windows PC';
		}

		$browser = 'Browser';
		if ( strpos( $ua, 'instagram' ) !== false ) {
			$browser = 'Instagram App';
		} elseif ( strpos( $ua, 'fbav' ) !== false || strpos( $ua, 'fban' ) !== false ) {
			$browser = 'Facebook App';
		} elseif ( strpos( $ua, 'edg/' ) !== false ) {
			$browser = 'Edge';
		} elseif ( strpos( $ua, 'chrome' ) !== false && strpos( $ua, 'edg' ) === false ) {
			$browser = 'Chrome';
		} elseif ( strpos( $ua, 'safari' ) !== false && strpos( $ua, 'chrome' ) === false ) {
			$browser = 'Safari';
		} elseif ( strpos( $ua, 'firefox' ) !== false ) {
			$browser = 'Firefox';
		}
		$device_label = $os . ' • ' . $browser;

		// Check if user info was passed from frontend or WP session
		$user_ctx = null;
		if ( ! empty( $params['currentUser'] ) && is_array( $params['currentUser'] ) ) {
			$user_ctx = $params['currentUser'];
		} elseif ( ! empty( $params['user'] ) && is_array( $params['user'] ) ) {
			$user_ctx = $params['user'];
		} elseif ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			$user_ctx = array(
				'id'    => (int) $u->ID,
				'name'  => (string) $u->display_name,
				'email' => (string) $u->user_email,
				'phone' => (string) ( get_user_meta( $u->ID, 'billing_phone', true ) ?: get_user_meta( $u->ID, 'phone', true ) ?: '' ),
			);
		}

		// Check if session has a known traveler name/phone from chat, CRM or logged-in user
		$visitor_name  = ! empty( $user_ctx['name'] ) ? sanitize_text_field( $user_ctx['name'] ) : '';
		$visitor_phone = ! empty( $user_ctx['phone'] ) ? sanitize_text_field( $user_ctx['phone'] ) : '';

		if ( ! empty( $session_id ) ) {
			global $wpdb;
			$conv_table = $wpdb->prefix . 'tc_agent_conversations';
			$cont_table = $wpdb->prefix . 'tc_agent_contacts';
			$conv       = $wpdb->get_row( $wpdb->prepare( "SELECT id, contact_id, metadata FROM {$conv_table} WHERE session_id = %s ORDER BY id DESC LIMIT 1", $session_id ), ARRAY_A );

			// If logged in and conversation has no contact_id yet, link it!
			if ( $user_ctx && ( ! empty( $user_ctx['email'] ) || ! empty( $user_ctx['id'] ) ) ) {
				$matched_cid = (int) ( $conv['contact_id'] ?? 0 );
				if ( ! $matched_cid ) {
					$upsert = TC_Contact_Service::upsert(
						array(
							'wp_user_id'     => (int) ( $user_ctx['id'] ?? 0 ),
							'email'          => sanitize_email( $user_ctx['email'] ?? '' ),
							'name'           => sanitize_text_field( $user_ctx['name'] ?? '' ),
							'phone'          => sanitize_text_field( $user_ctx['phone'] ?? '' ),
							'source_channel' => 'web',
						),
						array( 'sync' => false )
					);
					if ( ! empty( $upsert['id'] ) ) {
						$matched_cid = (int) $upsert['id'];
						if ( $conv ) {
							$wpdb->update( $conv_table, array( 'contact_id' => $matched_cid ), array( 'id' => (int) $conv['id'] ) );
						}
					}
				}
			}

			if ( empty( $visitor_name ) && ! empty( $conv['contact_id'] ) ) {
				$c = $wpdb->get_row( $wpdb->prepare( "SELECT name, phone FROM {$cont_table} WHERE id = %d", (int) $conv['contact_id'] ), ARRAY_A );
				if ( $c ) {
					$visitor_name  = (string) ( $c['name'] ?? '' );
					$visitor_phone = (string) ( $c['phone'] ?? '' );
				}
			}
		}

		$event_details = array(
			'action'        => $action,
			'page_url'      => $page_url,
			'page_title'    => $page_title,
			'session_id'    => $session_id,
			'ip'            => $ip,
			'country'       => $country,
			'city'          => $city,
			'device'        => $device_label,
			'referrer'      => $referrer,
			'utm_source'    => $utm_source,
			'language'      => $lang,
			'visitor_name'  => $visitor_name,
			'visitor_phone' => $visitor_phone,
			'user_agent'    => sanitize_text_field( substr( $raw_ua, 0, 150 ) ),
			'last_seen_ts'  => time(),
			'last_seen_at'  => current_time( 'mysql' ),
		);

		// Update live active visitor presence map (15-minute sliding window)
		$visitor_key = ! empty( $session_id ) ? $session_id : md5( $ip . '|' . $raw_ua );
		$active_map  = get_transient( 'tc_live_active_visitors' );
		if ( ! is_array( $active_map ) ) {
			$active_map = array();
		}
		$now_ts = time();
		foreach ( $active_map as $k => $v ) {
			if ( ( $now_ts - (int) ( $v['last_seen_ts'] ?? 0 ) ) > 900 ) {
				unset( $active_map[ $k ] );
			}
		}
		$prev_views = isset( $active_map[ $visitor_key ]['page_views'] ) ? (int) $active_map[ $visitor_key ]['page_views'] : 0;
		$first_seen = isset( $active_map[ $visitor_key ]['first_seen_ts'] ) ? (int) $active_map[ $visitor_key ]['first_seen_ts'] : $now_ts;
		$event_details['page_views']    = ( 'heartbeat' === $action ) ? max( 1, $prev_views ) : ( $prev_views + 1 );
		$event_details['first_seen_ts'] = $first_seen;
		$active_map[ $visitor_key ]     = $event_details;
		set_transient( 'tc_live_active_visitors', $active_map, 30 * MINUTE_IN_SECONDS );

		// Only write to audit log on page view or chat open (skip silent heartbeats so DB stays clean)
		if ( 'heartbeat' !== $action && class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'visitor_landing',
				'info',
				$event_details,
				'tripcosmos-guide',
				'web'
			);
		}

		return new WP_REST_Response( array( 'ok' => true, 'active_visitors' => count( $active_map ) ), 200 );
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

		if ( ! empty( $params['run_scheduled_now'] ) ) {
			if ( class_exists( 'TC_Integration_Google_Business' ) ) {
				$stats = TC_Integration_Google_Business::cron_import();
				return new WP_REST_Response( array(
					'success'       => true,
					'scheduled_run' => true,
					'data'          => $stats,
					'message'       => sprintf( __( 'Scheduled import completed for %s (%d new, %d in CRM).', 'tripcosmos-agents' ), $stats['city'] ?? 'City', $stats['inserted'] ?? 0, $stats['synced_crm'] ?? 0 ),
				), 200 );
			}
		}

		if ( ! empty( $raw_list ) ) {
			$lines    = explode( "
", str_replace( "
", '', $raw_list ) );
			$agencies = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				$parts = array_map( 'trim', preg_split( '/[,	|]/', $line ) );
				if ( count( $parts ) >= 1 && ! empty( $parts[0] ) ) {
					// Smart extraction
					$p_name  = $parts[0];
					$p_city  = $city ?: 'India';
					$p_phone = '';
					$p_email = '';

					for ( $i = 1; $i < count( $parts ); $i++ ) {
						$val = $parts[ $i ];
						if ( is_email( $val ) ) {
							$p_email = $val;
						} elseif ( preg_match( '/\+?[\d\s\-()]{8,15}/', $val ) && strlen( preg_replace( '/\D/', '', $val ) ) >= 8 ) {
							$p_phone = $val;
						} elseif ( 1 === $i && ! preg_match( '/\d/', $val ) ) {
							$p_city = $val;
						}
					}

					$agencies[] = array(
						'name'    => $p_name,
						'city'    => $p_city,
						'phone'   => $p_phone,
						'email'   => $p_email,
						'source'  => 'manual',
					);
				}
			}
			if ( ! empty( $agencies ) ) {
				$res = TC_Integration_Google_Business::upsert_agencies( $agencies, $city, 'manual' );
				return new WP_REST_Response( array(
					'success' => true,
					'data'    => $res,
					'message' => sprintf( __( '%d agencies imported (%d synced to FluentCRM).', 'tripcosmos-agents' ), $res['inserted'] + $res['updated'], $res['synced_crm'] ?? 0 ),
				), 200 );
			}
		}

		if ( '' !== $city ) {
			$res = TC_Agent_Tools::execute( 'import_b2b_agencies', array( 'city' => $city, 'max_results' => 20 ) );
			return new WP_REST_Response( array( 'success' => empty( $res['error'] ), 'data' => $res, 'message' => __( 'City import completed.', 'tripcosmos-agents' ) ), empty( $res['error'] ) ? 200 : 400 );
		}
		if ( class_exists( 'TC_Agents_Queue' ) ) { TC_Agents_Queue::push( 'import_b2b', array(), 0 ); }
		return new WP_REST_Response( array( 'success' => true, 'queued' => true, 'message' => __( 'Import task queued.', 'tripcosmos-agents' ) ), 200 );
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
		$params   = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$id       = absint( $params['agency_id'] ?? 0 );
		$sync_all = ! empty( $params['sync_all'] );

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_agencies';

		if ( $sync_all || ! $id ) {
			$agencies = $wpdb->get_results( "SELECT * FROM $table WHERE (status = 'new' OR status IS NULL) AND ( ( email != '' AND email IS NOT NULL ) OR ( phone != '' AND phone IS NOT NULL ) ) LIMIT 100", ARRAY_A );
			$synced = 0;
			foreach ( (array) $agencies as $a ) {
				$email = ! empty( $a['email'] ) ? $a['email'] : ( 'agency-' . $a['id'] . '@b2b.tripcosmos.co' );
				if ( class_exists( 'TC_Integration_FluentCRM' ) && TC_Integration_FluentCRM::is_active() ) {
					$fid = TC_Integration_FluentCRM::sync_lead( array(
						'name'        => $a['name'],
						'email'       => $email,
						'phone'       => $a['phone'],
						'destination' => $a['city'] ?: 'Varanasi',
						'channel'     => 'b2b_partner',
						'stage'       => 'b2b_partner',
						'score'       => 85,
					) );
					if ( $fid ) {
						$wpdb->update( $table, array( 'status' => 'in_crm' ), array( 'id' => (int) $a['id'] ) );
						$synced++;
					}
				}
			}
			return new WP_REST_Response( array(
				'success' => true,
				'synced'  => $synced,
				'message' => sprintf( __( '%d agencies synced to FluentCRM & enrolled in Dev Deepawali Funnel!', 'tripcosmos-agents' ), $synced ),
			), 200 );
		}

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
			'message'   => __( 'Agency synced to FluentCRM & Twenty CRM!', 'tripcosmos-agents' ),
		), 200 );
	}

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
