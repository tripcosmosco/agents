<?php
/**
 * Agent Conversation Orchestrator & State Machine.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Orchestrator {

	/**
	 * Process an incoming user message across any channel.
	 *
	 * @param string $user_message User input string.
	 * @param string $session_id   Unique session identifier (UUID, cookie, or WhatsApp sender phone).
	 * @param string $channel      'web', 'whatsapp', 'voice', 'admin'.
	 * @param string $agent_slug   Persona slug ('tripcosmos-guide', etc.).
	 * @param array  $metadata     Optional extra metadata (user IP, location, CRM ID).
	 * @return array Normalized response payload or WP_Error.
	 */
	public static function handle_message( $user_message, $session_id, $channel = 'web', $agent_slug = 'tripcosmos-guide', $metadata = array() ) {
		// 1. Enforce Guardrails
		$identifier = $metadata['ip'] ?? $session_id;
		$guard_res  = TC_Agents_Guardrails::check_permission( $channel, $identifier );
		if ( is_wp_error( $guard_res ) ) {
			return $guard_res;
		}

		// 2. Fetch or Create Conversation Record
		$conversation = self::get_or_create_conversation( $session_id, $channel, $agent_slug, $metadata );
		if ( ! $conversation ) {
			return new WP_Error( 'conversation_error', __( 'Unable to initialize conversation record.', 'tripcosmos-agents' ) );
		}

		// 3. Save User Message
		self::save_message(
			array(
				'conversation_id' => $conversation['id'],
				'role'            => 'user',
				'content'         => $user_message,
			)
		);

		// 4. Strict Human Takeover Protocol (VM Sales OS Rule):
		// If human specialist has taken over, the AI bot automatically remains silent.
		if ( 'handed_off' === $conversation['status'] && 'admin' !== $channel ) {
			return array(
				'session_id'    => $session_id,
				'reply'         => __( 'An expedition specialist from our team has joined this conversation. Please wait a moment while they reply, or chat with them directly on WhatsApp.', 'tripcosmos-agents' ),
				'handoff'       => array( 'human_takeover_active' => true ),
				'provider_used' => 'human_takeover_guard',
				'latency_ms'    => 0,
				'status'        => 'handed_off',
			);
		}

		// 5. Proactive Heuristic Lead Capture: Detect phone or email in user message
		if ( empty( $conversation['contact_id'] ) ) {
			$detected_phone = '';
			$detected_email = '';
			if ( preg_match( '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', $user_message, $em_match ) ) {
				$detected_email = sanitize_email( $em_match[0] );
			}
			if ( preg_match( '/(?:\+91|91|0)?[6-9]\d{9}\b|\+?[0-9]{8,15}\b/', $user_message, $ph_match ) ) {
				$detected_phone = preg_replace( '/[^0-9+]/', '', $ph_match[0] );
			}

			if ( ! empty( $detected_phone ) || ! empty( $detected_email ) ) {
				$lead_sync = TC_Agent_Tools::execute(
					'sync_lead_crm',
					array(
						'name'  => 'Traveler (' . ( $detected_phone ?: $detected_email ) . ')',
						'phone' => $detected_phone,
						'email' => $detected_email,
					),
					array(
						'channel'    => $channel,
						'session_id' => $session_id,
					)
				);
				if ( ! empty( $lead_sync['contact_id'] ) ) {
					$conversation['contact_id'] = (int) $lead_sync['contact_id'];
				}
			}
		}

		if ( ! empty( $conversation['contact_id'] ) ) {
			TC_Agent_Memory::extract_heuristic_insights( (int) $conversation['contact_id'], $user_message );
			// Non-blocking async queue for deep LLM memory synthesis
			TC_Agents_Queue::push( 'refresh_memory', array( 'contact_id' => (int) $conversation['contact_id'] ) );
		}

		// 6. Load Agent Persona & Append Persistent Traveler Memory & Knowledge Base
		$persona = self::get_persona( $agent_slug );
		if ( ! $persona ) {
			return new WP_Error( 'persona_not_found', sprintf( __( 'Agent persona "%s" not found.', 'tripcosmos-agents' ), esc_html( $agent_slug ) ) );
		}

		$system_prompt = $persona['system_prompt'];

		// Inject visitor's active browsing context if available (Page URL / Title)
		if ( ! empty( $metadata['page_url'] ) || ! empty( $metadata['page_title'] ) ) {
			$page_ctx = "\n--- VISITOR CURRENT BROWSING CONTEXT ---";
			if ( ! empty( $metadata['page_title'] ) ) {
				$page_ctx .= "\nPage Title: " . esc_html( $metadata['page_title'] );
			}
			if ( ! empty( $metadata['page_url'] ) ) {
				$page_ctx .= "\nPage URL: " . esc_url( $metadata['page_url'] );
			}
			$page_ctx .= "\nUse this browsing context to provide immediate, highly tailored recommendations for the trek or package they are currently viewing.";
			$page_ctx .= "\n-----------------------------------------\n";
			$system_prompt .= $page_ctx;
		}

		// Inject relevant official knowledge & policy context
		if ( class_exists( 'TC_Agent_Knowledge' ) ) {
			$kb_context = TC_Agent_Knowledge::get_relevant_context( $user_message );
			if ( ! empty( $kb_context ) ) {
				$system_prompt .= "\n" . $kb_context;
			}
		}

		// Append traveler cross-session memory
		if ( ! empty( $conversation['contact_id'] ) ) {
			$memory_context = TC_Agent_Memory::format_for_prompt( (int) $conversation['contact_id'] );
			if ( ! empty( $memory_context ) ) {
				$system_prompt .= "\n" . $memory_context;
			}
		}

		// Multilingual Pilgrim Adaptation:
		$lang = sanitize_text_field( $metadata['language'] ?? 'en' );
		$lang_names = array(
			'hi' => 'Hindi (हिन्दी)',
			'gu' => 'Gujarati (ગુજરાતી)',
			'te' => 'Telugu (తెలుగు)',
			'bn' => 'Bengali (বাংলা)',
			'mr' => 'Marathi (मराठी)',
			'ta' => 'Tamil (தமிழ்)',
		);
		if ( ! empty( $lang ) && 'en' !== $lang && isset( $lang_names[ $lang ] ) ) {
			$system_prompt .= "\n\n--- MULTILINGUAL PILGRIM DIRECTIVE ---";
			$system_prompt .= "\nThe traveler has selected to communicate in {$lang_names[$lang]}.";
			$system_prompt .= "\nReply fluently, respectfully, and warmly in {$lang_names[$lang]} using its native script.";
			$system_prompt .= "\nMaintain devotional warmth ('जय काशी विश्वनाथ', 'जय श्री राम', 'हर हर महादेव'), spiritual clarity, and high hospitality.";
			$system_prompt .= "\nKeep key vehicle types ('Innova Crysta', 'Swift Dzire', 'Ertiga', 'Tempo Traveller'), train stations ('Varanasi Junction BSB', 'Pandit Deen Dayal Upadhyaya DDU'), flight codes ('VNS Babatpur', 'AYJ Ayodhya'), and currency pricing in clearly readable numbers (e.g. ₹3,500).";
			$system_prompt .= "\n-----------------------------------------\n";
		}

		// 7. Assemble Context Messages for LLM
		$allowed_tools    = ! empty( $persona['allowed_tools'] ) ? json_decode( $persona['allowed_tools'], true ) : array();
		$tool_definitions = self::filter_tools( TC_Agent_Tools::get_definitions(), $allowed_tools );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

		// Fetch past turns (last 10 conversational dialogue turns)
		$history = self::get_recent_messages( $conversation['id'], 10 );
		foreach ( $history as $h ) {
			if ( in_array( $h['role'], array( 'user', 'assistant' ), true ) && '' !== trim( (string) $h['content'] ) ) {
				$messages[] = array(
					'role'    => $h['role'],
					'content' => $h['content'],
				);
			}
		}

		// Options
		$options = array(
			'temperature'      => (float) $persona['temperature'],
			'routing_override' => $persona['routing_override'] ?? '',
		);

		$router         = new TC_AI_Router();
		$max_turns      = 3; // Max tool iteration loops
		$current_turn   = 0;
		$reply_content  = '';
		$provider_used  = '';
		$handoff_data   = null;
		$executed_tools = array();

		$ai_response = $router->chat( $messages, $tool_definitions, $options );
		if ( is_wp_error( $ai_response ) ) {
			return $ai_response;
		}

		$reply_content = $ai_response['content'] ?? '';
		$provider_used = $ai_response['provider'] ?? '';
		$tool_calls    = $ai_response['tool_calls'] ?? array();

		while ( ! empty( $tool_calls ) && $current_turn < $max_turns ) {
			$current_turn++;

			// Save assistant message that triggered tool call
			self::save_message(
				array(
					'conversation_id'   => $conversation['id'],
					'role'              => 'assistant',
					'content'           => $reply_content,
					'tool_calls'        => wp_json_encode( $tool_calls ),
					'provider_used'     => $provider_used,
					'prompt_tokens'     => $ai_response['prompt_tokens'] ?? 0,
					'completion_tokens' => $ai_response['completion_tokens'] ?? 0,
					'latency_ms'        => $ai_response['latency_ms'] ?? 0,
				)
			);

			// Append to active context
			$messages[] = array(
				'role'       => 'assistant',
				'content'    => $reply_content ?: null,
				'tool_calls' => $tool_calls,
			);

			// Execute each tool
			foreach ( $tool_calls as $tc ) {
				$call_id   = $tc['id'] ?? ( 'call_' . wp_generate_password( 8, false ) );
				$fn_name   = $tc['function']['name'] ?? '';
				$fn_args   = ! empty( $tc['function']['arguments'] ) ? json_decode( $tc['function']['arguments'], true ) : array();
				if ( ! is_array( $fn_args ) ) {
					$fn_args = array();
				}

				$context_data = array(
					'session_id'      => $session_id,
					'channel'         => $channel,
					'conversation_id' => $conversation['id'],
				);

				// Check Guardrail (Capability scoping & discount ceilings)
				$guard_tool = TC_Agents_Guardrails::validate_tool_call( $fn_name, $fn_args, $allowed_tools, $context_data );
				if ( is_wp_error( $guard_tool ) ) {
					$tool_output = array( 'error' => $guard_tool->get_error_message() );
				} else {
					$tool_output = TC_Agent_Tools::execute( $fn_name, $fn_args, $context_data );

					if ( 'request_human_handoff' === $fn_name ) {
						$handoff_data = $tool_output;
						// Update conversation status to handed_off
						self::update_conversation_status( $conversation['id'], 'handed_off' );
					}
				}

				$executed_tools[] = array(
					'tool'   => $fn_name,
					'args'   => $fn_args,
					'output' => $tool_output,
				);

				$tool_output_str = wp_json_encode( $tool_output );

				// Append tool result to context
				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $call_id,
					'name'         => $fn_name,
					'content'      => $tool_output_str,
				);

				// Save tool execution record
				self::save_message(
					array(
						'conversation_id' => $conversation['id'],
						'role'            => 'tool',
						'content'         => $tool_output_str,
						'tool_calls'      => wp_json_encode( array( 'call_id' => $call_id, 'tool' => $fn_name ) ),
					)
				);
			}

			// Call LLM again to synthesize final reply with tool results
			$ai_response = $router->chat( $messages, $tool_definitions, $options );
			if ( is_wp_error( $ai_response ) ) {
				break;
			}

			$reply_content = $ai_response['content'] ?? '';
			$tool_calls    = $ai_response['tool_calls'] ?? array();
		}

		// 8. Save Final Assistant Reply
		if ( ! empty( $reply_content ) ) {
			self::save_message(
				array(
					'conversation_id'   => $conversation['id'],
					'role'              => 'assistant',
					'content'           => $reply_content,
					'provider_used'     => $provider_used,
					'prompt_tokens'     => $ai_response['prompt_tokens'] ?? 0,
					'completion_tokens' => $ai_response['completion_tokens'] ?? 0,
					'latency_ms'        => $ai_response['latency_ms'] ?? 0,
				)
			);
		}

		// Record outbound event against daily limits
		TC_Agents_Guardrails::record_outbound( $channel );

		// 9. Update Conversation timestamp
		self::touch_conversation( $conversation['id'] );

		return array(
			'session_id'     => $session_id,
			'reply'          => $reply_content,
			'handoff'        => $handoff_data,
			'executed_tools' => $executed_tools,
			'provider_used'  => $provider_used,
			'latency_ms'     => $ai_response['latency_ms'] ?? 0,
			'status'         => 'success',
		);
	}

	/**
	 * Retrieve or create conversation record.
	 */
	public static function get_or_create_conversation( $session_id, $channel = 'web', $agent_slug = 'tripcosmos-guide', $metadata = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_conversations';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );

		if ( $row ) {
			return $row;
		}

		// Link to logged in user if available
		$wp_user_id = get_current_user_id();
		$contact_id = 0;

		if ( $wp_user_id > 0 ) {
			$contact_id = self::get_or_create_contact_for_user( $wp_user_id, $channel );
		}

		$wpdb->insert(
			$table,
			array(
				'session_id'      => sanitize_text_field( $session_id ),
				'contact_id'      => $contact_id,
				'channel'         => sanitize_text_field( $channel ),
				'agent_id'        => sanitize_text_field( $agent_slug ),
				'status'          => 'active',
				'metadata'        => wp_json_encode( $metadata ),
				'started_at'      => current_time( 'mysql' ),
				'last_message_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );
	}

	/**
	 * Build message history chain with system prompt and recent turns.
	 */
	private static function build_message_chain( $conversation_id, $system_prompt ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_messages';

		// Get recent 16 messages
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM $table WHERE conversation_id = %d ORDER BY id DESC LIMIT 16",
				$conversation_id
			),
			ARRAY_A
		);

		$chain = array();
		// System prompt first
		$chain[] = array(
			'role'    => 'system',
			'content' => $system_prompt,
		);

		if ( ! empty( $rows ) ) {
			$rows = array_reverse( $rows );
			foreach ( $rows as $r ) {
				$chain[] = array(
					'role'    => $r['role'],
					'content' => $r['content'],
				);
			}
		}

		return $chain;
	}

	/**
	 * Save an individual message to database.
	 */
	public static function save_message( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_messages';

		$wpdb->insert(
			$table,
			array(
				'conversation_id'   => (int) ( $data['conversation_id'] ?? 0 ),
				'role'              => sanitize_text_field( $data['role'] ?? 'user' ),
				'content'           => $data['content'] ?? '',
				'tool_calls'        => $data['tool_calls'] ?? null,
				'tool_results'      => $data['tool_results'] ?? null,
				'provider_used'     => sanitize_text_field( $data['provider_used'] ?? '' ),
				'prompt_tokens'     => (int) ( $data['prompt_tokens'] ?? 0 ),
				'completion_tokens' => (int) ( $data['completion_tokens'] ?? 0 ),
				'latency_ms'        => (int) ( $data['latency_ms'] ?? 0 ),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve recent conversation turns for context assembly.
	 *
	 * @param int $conversation_id
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_messages( $conversation_id, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_messages';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM $table WHERE conversation_id = %d AND role IN ('user', 'assistant') AND content != '' ORDER BY id DESC LIMIT %d",
				(int) $conversation_id,
				(int) $limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		// Reverse to chronological order (oldest to newest)
		return array_reverse( $rows );
	}

	/**
	 * Update conversation status.
	 */
	public static function update_conversation_status( $conversation_id, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_conversations';
		$wpdb->update(
			$table,
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => (int) $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update last message timestamp.
	 */
	public static function touch_conversation( $conversation_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_conversations';
		$wpdb->update(
			$table,
			array( 'last_message_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Load persona by slug.
	 */
	public static function get_persona( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_personas';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s AND is_active = 1 LIMIT 1", $slug ), ARRAY_A );
	}

	/**
	 * Match or create contact record for logged-in WP user.
	 */
	private static function get_or_create_contact_for_user( $user_id, $channel = 'web' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_contacts';

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE wp_user_id = %d LIMIT 1", $user_id ) );
		if ( $existing ) {
			return (int) $existing;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 0;
		}

		$wpdb->insert(
			$table,
			array(
				'wp_user_id'     => $user_id,
				'email'          => $user->user_email,
				'name'           => $user->display_name,
				'source_channel' => $channel,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Filter tool definitions according to persona configuration.
	 *
	 * @param array $all_tools     All registered tool definitions.
	 * @param array $allowed_slugs Allowed tool function names.
	 * @return array
	 */
	public static function filter_tools( array $all_tools, array $allowed_slugs = array() ) {
		if ( empty( $allowed_slugs ) ) {
			return $all_tools;
		}

		$filtered = array();
		foreach ( $all_tools as $tool ) {
			$name = $tool['function']['name'] ?? '';
			if ( in_array( $name, $allowed_slugs, true ) ) {
				$filtered[] = $tool;
			}
		}

		return ! empty( $filtered ) ? $filtered : $all_tools;
	}
}
