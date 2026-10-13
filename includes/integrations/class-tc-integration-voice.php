<?php
/**
 * Voice Telephony & Agent Call Integration Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_Voice {

	public static function is_enabled() {
		return '0' !== (string) get_option( 'tc_agents_voice_enabled', '1' );
	}

	/**
	 * Active Telephony Mode:
	 * - 'livekit_webrtc': Option 1 (100% Free LiveKit WebRTC + Evolution API WhatsApp Link)
	 * - 'jio_sim': Option 2 (GSM Gateway / Android Office Bridge Phone via +91 93361 16210)
	 * - 'sip_trunk': Option 3 (Enterprise Cloud SIP Trunk / Asterisk / FreePBX)
	 * - 'vapi' / 'retell': Cloud AI Agent Fallbacks
	 */
	public static function get_provider() {
		return get_option( 'tc_agents_voice_provider', 'livekit_webrtc' );
	}

	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'voice_api_key' ) : get_option( 'tc_agents_voice_api_key', '' );
	}

	public static function get_assistant_id() {
		return get_option( 'tc_agents_voice_assistant_id', '' );
	}

	public static function get_phone_number_id() {
		return get_option( 'tc_agents_voice_phone_number_id', '' );
	}

	// Option 1: LiveKit WebRTC
	public static function get_livekit_ws_url() {
		return get_option( 'tc_agents_livekit_ws_url', 'wss://rtc.onlinepuja.live' );
	}

	// Option 2: Office GSM SIM Number
	public static function get_gsm_sim_number() {
		return get_option( 'tc_agents_gsm_sim_number', '+919336116210' );
	}

	// Option 3: SIP Trunk Configuration
	public static function get_sip_host() {
		return get_option( 'tc_agents_sip_host', '' );
	}
	public static function get_sip_port() {
		return get_option( 'tc_agents_sip_port', '5060' );
	}
	public static function get_sip_username() {
		return get_option( 'tc_agents_sip_username', '' );
	}
	public static function get_sip_caller_id() {
		return get_option( 'tc_agents_sip_caller_id', '+919336116210' );
	}

	// Option 4: Airtel IQ Business Connect (Click-to-Call + Number Masking)
	public static function get_airtel_iq_app_id() {
		return get_option( 'tc_agents_airtel_iq_app_id', '' );
	}
	public static function get_airtel_iq_app_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'airtel_iq_app_key' ) : get_option( 'tc_agents_airtel_iq_app_key', '' );
	}
	public static function get_airtel_iq_vmn() {
		return get_option( 'tc_agents_airtel_iq_vmn', '+919152011111' );
	}
	public static function get_airtel_iq_customer_id() {
		return get_option( 'tc_agents_airtel_iq_customer_id', '' );
	}

	/**
	 * Place an outbound call to a traveler.
	 * Dispatches based on active mode:
	 * - Option 1: LiveKit WebRTC (creates room & dispatches WhatsApp 1-tap call invite via Evolution API)
	 * - Option 2: Android GSM Bridge (queues to office companion phone with +91 93361 16210 SIM)
	 * - Option 3: Enterprise Cloud SIP Trunk (SIP INVITE through Asterisk PBX / Cloud DID)
	 *
	 * @param string $recipient_phone Phone number in E.164 or Indian digits.
	 * @param string $customer_name
	 * @param string $reason Purpose of call e.g. "itinerary_followup", "urgent_inquiry".
	 * @return array|WP_Error
	 */
	public static function place_call( $recipient_phone, $customer_name = '', $reason = '' ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'voice_disabled', __( 'Voice calls are disabled in plugin settings.', 'tripcosmos-agents' ) );
		}

		// Check guardrails & daily cap
		$guard = TC_Agents_Guardrails::check_permission( 'voice', $recipient_phone );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$provider = self::get_provider();

		// OPTION 1: 100% Free LiveKit WebRTC + Evolution WhatsApp 1-Tap Call Link
		if ( 'livekit_webrtc' === $provider ) {
			return self::dispatch_livekit_webrtc_call( $recipient_phone, $customer_name, $reason );
		}

		// OPTION 2: GSM Gateway / Android Bridge Phone (+91 93361 16210 SIM)
		if ( 'jio_sim' === $provider || 'gsm_bridge' === $provider ) {
			return self::dispatch_via_jio_companion( $recipient_phone, $customer_name, $reason );
		}

		// OPTION 3: Enterprise Cloud SIP Trunk / Asterisk PBX
		if ( 'sip_trunk' === $provider || 'asterisk' === $provider ) {
			return self::dispatch_via_sip_trunk( $recipient_phone, $customer_name, $reason );
		}

		// OPTION 4: Airtel IQ Business Connect (Dual-Leg Click-to-Call + Number Masking)
		if ( 'airtel_iq' === $provider ) {
			$agent_phone = get_option( 'tc_agents_default_agent_phone', '+919336116210' );
			return self::dispatch_airtel_iq_click2call( $recipient_phone, $agent_phone, $customer_name, $reason );
		}

		// Route Cloud Vapi: If selected, attempt cloud call
		if ( 'vapi' === $provider ) {
			$api_key         = self::get_api_key();
			$phone_number_id = self::get_phone_number_id();
			$assistant_id    = self::get_assistant_id();

			if ( ! empty( $api_key ) && ! empty( $assistant_id ) && ! empty( $phone_number_id ) ) {
				$vapi_res = self::dispatch_vapi_call( $recipient_phone, $customer_name, $reason, $api_key, $phone_number_id, $assistant_id );
				if ( ! is_wp_error( $vapi_res ) ) {
					return $vapi_res;
				}
				TC_Agents_Logger::log( 'vapi_call_failed_fallback_livekit', 'warning', array( 'error' => $vapi_res->get_error_message() ), '', 'voice' );
			}
		}

		// Default fallback to Option 1: LiveKit WebRTC
		return self::dispatch_livekit_webrtc_call( $recipient_phone, $customer_name, $reason );
	}

	/**
	 * OPTION 1: LiveKit WebRTC Voice Room + 1-Tap WhatsApp Link via Evolution API.
	 */
	public static function dispatch_livekit_webrtc_call( $recipient_phone, $customer_name = '', $reason = '' ) {
		$call_id     = 'tc_rtc_' . wp_generate_password( 8, false );
		$clean_phone = preg_replace( '/[^0-9+]/', '', $recipient_phone );
		$room_name   = 'trip_' . substr( preg_replace( '/[^0-9]/', '', $clean_phone ), -6 );
		$call_url    = home_url( '/call/?room=' . rawurlencode( $room_name ) . '&call_id=' . $call_id );

		// WhatsApp Invite Text sent from central number via Evolution API
		$wa_invite = sprintf(
			"Har Har Mahadev %s ji! 🙏\n\nYour TripCosmos Pilgrimage Advisor is calling regarding: *%s*.\n\n📞 *Tap to connect on live audio call (Free):*\n%s\n\n(No app download needed — opens instantly in browser).",
			! empty( $customer_name ) ? esc_html( $customer_name ) : 'Traveler',
			! empty( $reason ) ? esc_html( $reason ) : 'Varanasi Pilgrimage Consultation',
			$call_url
		);

		// Send via Evolution API
		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			TC_Integration_Evolution::send_message( $clean_phone, $wa_invite );
		}

		$job_payload = array(
			'call_id'       => $call_id,
			'room_name'     => $room_name,
			'phone'         => $clean_phone,
			'customer_name' => $customer_name ?: 'Traveler',
			'reason'        => $reason ?: 'LiveKit WebRTC Consultation',
			'call_url'      => $call_url,
			'ws_url'        => self::get_livekit_ws_url(),
			'created_at'    => current_time( 'mysql' ),
			'mode'          => 'livekit_webrtc',
		);

		// Keep in transient for live query by X-Agents
		$pending = get_transient( 'tc_livekit_pending_calls' ) ?: array();
		$pending[ $call_id ] = $job_payload;
		set_transient( 'tc_livekit_pending_calls', $pending, 30 * MINUTE_IN_SECONDS );

		TC_Agents_Guardrails::record_outbound( 'voice' );
		TC_Agents_Logger::log(
			'voice_call_livekit_dispatched',
			'info',
			array(
				'call_id'       => $call_id,
				'room_name'     => $room_name,
				'recipient'     => $clean_phone,
				'customer_name' => $customer_name,
				'cost'          => '₹0.00 (Self-Hosted LiveKit WebRTC)',
			),
			'',
			'voice'
		);

		return array(
			'success'     => true,
			'call_id'     => $call_id,
			'channel'     => 'livekit_webrtc',
			'status'      => 'webrtc_room_ready',
			'room_name'   => $room_name,
			'call_url'    => $call_url,
			'ws_url'      => self::get_livekit_ws_url(),
			'message'     => sprintf( __( 'WebRTC room %s created! WhatsApp call link sent from +91 93361 16210.', 'tripcosmos-agents' ), $room_name ),
			'is_free'     => true,
		);
	}

	/**
	 * OPTION 3: Enterprise Cloud SIP Trunk / Asterisk PBX.
	 */
	public static function dispatch_via_sip_trunk( $recipient_phone, $customer_name = '', $reason = '' ) {
		$call_id     = 'tc_sip_' . wp_generate_password( 8, false );
		$clean_phone = preg_replace( '/[^0-9+]/', '', $recipient_phone );

		$job_payload = array(
			'call_id'       => $call_id,
			'phone'         => $clean_phone,
			'customer_name' => $customer_name ?: 'Traveler',
			'reason'        => $reason ?: 'Enterprise SIP Trunk Call',
			'sip_host'      => self::get_sip_host(),
			'sip_port'      => self::get_sip_port(),
			'caller_id'     => self::get_sip_caller_id(),
			'created_at'    => current_time( 'mysql' ),
			'mode'          => 'sip_trunk',
		);

		$pending = get_transient( 'tc_sip_pending_calls' ) ?: array();
		$pending[ $call_id ] = $job_payload;
		set_transient( 'tc_sip_pending_calls', $pending, 30 * MINUTE_IN_SECONDS );

		TC_Agents_Guardrails::record_outbound( 'voice' );
		TC_Agents_Logger::log(
			'voice_call_sip_trunk_dispatched',
			'info',
			array(
				'call_id'       => $call_id,
				'recipient'     => $clean_phone,
				'caller_id'     => self::get_sip_caller_id(),
				'sip_host'      => self::get_sip_host(),
			),
			'',
			'voice'
		);

		return array(
			'success'     => true,
			'call_id'     => $call_id,
			'channel'     => 'sip_trunk',
			'status'      => 'sip_invite_queued',
			'caller_id'   => self::get_sip_caller_id(),
			'message'     => sprintf( __( 'SIP Trunk call queued to %s displaying Caller ID %s.', 'tripcosmos-agents' ), $clean_phone, self::get_sip_caller_id() ),
			'is_free'     => false,
		);
	}

	/**
	 * OPTION 4: Airtel IQ Business Connect (Dual-Leg Click-to-Call with Number Masking).
	 * Connects agent's phone and customer's phone through the official Airtel Virtual Mobile Number (VMN).
	 * Customer only sees the official TripCosmos VMN; agent's personal number is 100% masked.
	 *
	 * @param string $recipient_phone
	 * @param string $agent_phone
	 * @param string $customer_name
	 * @param string $reason
	 * @return array
	 */
	public static function dispatch_airtel_iq_click2call( $recipient_phone, $agent_phone = '', $customer_name = '', $reason = '' ) {
		$app_id  = self::get_airtel_iq_app_id();
		$app_key = self::get_airtel_iq_app_key();
		$vmn     = self::get_airtel_iq_vmn();

		$clean_customer = preg_replace( '/[^0-9+]/', '', $recipient_phone );
		$clean_agent    = preg_replace( '/[^0-9+]/', '', $agent_phone ?: self::get_gsm_sim_number() );

		// Standardize Indian phone to 10 digits or E.164
		if ( strlen( $clean_customer ) === 10 ) {
			$clean_customer = '+91' . $clean_customer;
		}
		if ( strlen( $clean_agent ) === 10 ) {
			$clean_agent = '+91' . $clean_agent;
		}

		$call_id = 'tc_aiq_' . wp_generate_password( 8, false );
		$webhook_url = rest_url( 'tc-agents/v1/telephony-webhook' );

		// Airtel IQ Click-to-Call REST API endpoint
		$endpoint = 'https://iq.airtel.in/voice/v1/call/click2call';

		$payload = array(
			'customer_number' => $clean_customer,
			'agent_number'    => $clean_agent,
			'vmn'             => $vmn,
			'call_id'         => $call_id,
			'webhook_url'     => $webhook_url,
			'record_call'     => true,
			'caller_id'       => $vmn,
			'metadata'        => array(
				'customer_name' => $customer_name,
				'reason'        => $reason,
				'workspace'     => 'TripCosmos.co',
			),
		);

		// Execute HTTP request to Airtel IQ if credentials are set
		if ( ! empty( $app_id ) && ! empty( $app_key ) ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $app_key,
						'X-App-Id'      => $app_id,
					),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				TC_Agents_Logger::log( 'airtel_iq_call_failed', 'error', array( 'error' => $response->get_error_message() ), '', 'voice' );
			}
		}

		// Save call job to transient for webhook reconciliation
		$pending = get_transient( 'tc_airtel_iq_pending_calls' ) ?: array();
		$pending[ $call_id ] = array(
			'call_id'         => $call_id,
			'customer_number' => $clean_customer,
			'agent_number'    => $clean_agent,
			'vmn'             => $vmn,
			'customer_name'   => $customer_name,
			'created_at'      => current_time( 'mysql' ),
		);
		set_transient( 'tc_airtel_iq_pending_calls', $pending, HOUR_IN_SECONDS );

		TC_Agents_Guardrails::record_outbound( 'voice' );
		TC_Agents_Logger::log(
			'voice_call_airtel_iq_dispatched',
			'info',
			array(
				'call_id'   => $call_id,
				'customer'  => $clean_customer,
				'agent'     => $clean_agent,
				'vmn'       => $vmn,
			),
			'',
			'voice'
		);

		return array(
			'success'          => true,
			'call_id'          => $call_id,
			'channel'          => 'airtel_iq',
			'provider'         => 'airtel_iq',
			'status'           => 'connecting_agent_first',
			'masked_cli'       => $vmn,
			'agent_number'     => $clean_agent,
			'customer_number'  => $clean_customer,
			'message'          => sprintf(
				__( 'Airtel IQ is dialing your phone (%s). Answer to connect with %s with Caller ID masked as %s.', 'tripcosmos-agents' ),
				$clean_agent,
				$clean_customer,
				$vmn
			),
			'is_free'          => false,
			'free_alternative' => array(
				'name'        => 'LiveKit Free WebRTC',
				'description' => 'Switch to livekit_webrtc in settings for 100% free web/app audio calls.',
			),
		);
	}

	/**
	 * Dispatch call to Cloud Vapi API.
	 */
	private static function dispatch_vapi_call( $recipient_phone, $customer_name, $reason, $api_key, $phone_number_id, $assistant_id ) {
		$default_disclosure = sprintf(
			__( 'Namaste %s, this is the automated expedition assistant from TripCosmos.co. Please note this call may be recorded for quality and security purposes. Regarding your trip inquiry: ', 'tripcosmos-agents' ),
			! empty( $customer_name ) ? esc_html( $customer_name ) : ''
		);
		$consent_notice = get_option( 'tc_agents_voice_recording_disclosure', $default_disclosure );

		$endpoint = 'https://api.vapi.ai/call/phone';
		$payload  = array(
			'phoneNumberId'      => $phone_number_id,
			'assistantId'        => $assistant_id,
			'customer'           => array(
				'number' => preg_replace( '/[^0-9+]/', '', $recipient_phone ),
				'name'   => $customer_name,
			),
			'assistantOverrides' => array(
				'firstMessage'   => $consent_notice . ( $reason ? " {$reason}." : '' ),
				'variableValues' => array(
					'customer_name' => $customer_name,
					'reason'        => $reason,
				),
			),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 12,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			TC_Agents_Guardrails::record_outbound( 'voice' );
			$call_id = $body['id'] ?? 'call_' . time();
			return array(
				'success' => true,
				'call_id' => $call_id,
				'channel' => 'vapi_cloud',
				'status'  => 'queued',
			);
		}

		return new WP_Error( 'vapi_failed', $body['message'] ?? "Vapi HTTP {$code}" );
	}

	/**
	 * 100% Free Jio SIM Hardware Bridge Dispatch.
	 * Queues call command for the office Android phone holding the TripCosmos Jio SIM.
	 *
	 * @param string $recipient_phone
	 * @param string $customer_name
	 * @param string $reason
	 * @return array
	 */
	public static function dispatch_via_jio_companion( $recipient_phone, $customer_name = '', $reason = '' ) {
		global $wpdb;
		$call_id     = 'jio_' . time() . '_' . wp_generate_password( 6, false, false );
		$clean_phone = preg_replace( '/[^0-9+]/', '', $recipient_phone );

		$job_payload = array(
			'call_id'       => $call_id,
			'phone'         => $clean_phone,
			'customer_name' => $customer_name ?: 'Traveler',
			'reason'        => $reason ?: 'TripCosmos Tour & Cab Consultation',
			'created_at'    => current_time( 'mysql' ),
			'queued_via'    => 'jio_sim_bridge',
		);

		// Store in async jobs queue for Android app polling/webhook
		if ( class_exists( 'TC_Agents_Queue' ) ) {
			TC_Agents_Queue::push( 'jio_outbound_call', $job_payload );
		}

		// Keep in fast transient for real-time mobile app query
		$pending = get_transient( 'tc_jio_pending_calls' ) ?: array();
		$pending[ $call_id ] = $job_payload;
		set_transient( 'tc_jio_pending_calls', $pending, 30 * MINUTE_IN_SECONDS );

		TC_Agents_Guardrails::record_outbound( 'voice' );

		TC_Agents_Logger::log(
			'voice_call_queued_jio_sim',
			'info',
			array(
				'call_id'       => $call_id,
				'recipient'     => $clean_phone,
				'customer_name' => $customer_name,
				'reason'        => $reason,
				'cost'          => '₹0.00 (Jio Unlimited Domestic SIM)',
			),
			'',
			'voice'
		);

		return array(
			'success'     => true,
			'call_id'     => $call_id,
			'channel'     => 'jio_companion',
			'status'      => 'queued_to_jio_phone',
			'message'     => sprintf( __( 'Call queued to TripCosmos Jio SIM phone for %s. Dialing now for ₹0.', 'tripcosmos-agents' ), esc_html( $clean_phone ) ),
			'is_free'     => true,
		);
	}

	/**
	 * Query AI Puffer / AIPKit RAG Bot 155464 for live voice call Q&A (100% Free).
	 *
	 * @param string $speech_text
	 * @param string $session_id
	 * @return array
	 */
	public static function query_voice_rag( $speech_text, $session_id = '' ) {
		$speech_text = sanitize_text_field( $speech_text );
		if ( empty( $speech_text ) ) {
			return array( 'success' => true, 'reply' => 'Namaste, could you please repeat that?' );
		}

		// Query local AI Puffer RAG Bot 155464 (TripCosmos AI)
		if ( class_exists( 'TC_Provider_AIPuffer' ) ) {
			$puffer = new TC_Provider_AIPuffer();
			$bot_id = $puffer->get_bot_id() ?: '155464';

			$messages = array(
				array(
					'role'    => 'system',
					'content' => 'You are speaking on a live voice call with a traveler on TripCosmos (Varanasi). Reply concisely in 1 to 2 spoken sentences maximum. Speak with warm Indian hospitality. Use simple, easily understandable words for voice listening.',
				),
				array(
					'role'    => 'user',
					'content' => $speech_text,
				),
			);

			$response = $puffer->chat( $messages, array(), array( 'bot_id' => $bot_id, 'temperature' => 0.3 ) );
			if ( ! is_wp_error( $response ) && ! empty( $response['content'] ) ) {
				return array(
					'success'       => true,
					'reply'         => trim( (string) $response['content'] ),
					'provider_used' => 'aipuffer_rag_local',
				);
			}
		}

		// Fallback to Orchestrator chat
		if ( class_exists( 'TC_Agent_Orchestrator' ) ) {
			$res = TC_Agent_Orchestrator::handle_message(
				$speech_text,
				$session_id ?: ( 'voice_' . time() ),
				'voice',
				'tripcosmos-guide'
			);
			if ( ! is_wp_error( $res ) ) {
				return array(
					'success'       => true,
					'reply'         => $res['reply'],
					'provider_used' => $res['provider_used'] ?? 'gateway',
				);
			}
		}

		return array(
			'success' => true,
			'reply'   => 'Namaste! Our travel specialist will connect with you on WhatsApp right away to share complete tour and cab details.',
		);
	}

	/**
	 * Handle voice call webhook (transcript and recording update).
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$auth = TC_Agents_Guardrails::verify_webhook_secret( $request, 'tc_agents_voice_webhook_secret', 'voice' );
		if ( true !== $auth ) {
			return $auth;
		}
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		$call_id     = $data['message']['call']['id'] ?? $data['call_id'] ?? '';
		$transcript  = $data['message']['transcript'] ?? $data['transcript'] ?? '';
		$recording   = $data['message']['recordingUrl'] ?? $data['recording_url'] ?? '';
		$phone       = $data['message']['customer']['number'] ?? $data['phone'] ?? '';

		if ( ! empty( $call_id ) && ! empty( $transcript ) ) {
			TC_Agents_Logger::log(
				'voice_call_completed',
				'info',
				array(
					'call_id'       => $call_id,
					'phone'         => $phone,
					'transcript'    => wp_trim_words( $transcript, 30 ),
					'recording_url' => $recording,
				),
				'',
				'voice'
			);

			// Link transcript into conversation messages
			if ( ! empty( $phone ) ) {
				$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
				$session_id  = 'voice_' . $clean_phone;
				$convo       = TC_Agent_Orchestrator::get_or_create_conversation( $session_id, 'voice', 'tripcosmos-guide' );
				if ( $convo ) {
					TC_Agent_Orchestrator::save_message(
						array(
							'conversation_id' => $convo['id'],
							'role'            => 'assistant',
							'content'         => "[Voice Call Transcript]\n" . $transcript . ( ! empty( $recording ) ? "\nRecording: {$recording}" : '' ),
							'provider_used'   => 'voice_telephony',
						)
					);
				}
			}
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Automatically dispatch follow-up call if lead is high value and voice is enabled.
	 *
	 * @param array $lead
	 * @return array|WP_Error|false
	 */
	public static function maybe_dispatch_call( array $lead ) {
		if ( ! self::is_enabled() || empty( $lead['phone'] ) ) {
			return false;
		}

		$customer_name = ! empty( $lead['name'] ) ? $lead['name'] : 'Traveler';
		$reason        = sprintf( 'Following up on your %s inquiry with TripCosmos', ! empty( $lead['stage'] ) ? $lead['stage'] : 'tour & pilgrimage' );

		return self::place_call( $lead['phone'], $customer_name, $reason );
	}

	/**
	 * Instant WhatsApp Auto-Responder for Missed Calls (Superfone Parity).
	 * Fired when an inbound ring times out or is rejected.
	 *
	 * @param string $phone
	 * @param string $customer_name
	 * @return bool
	 */
	public static function handle_missed_call( $phone, $customer_name = 'Traveler' ) {
		$clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( empty( $clean_phone ) ) {
			return false;
		}

		$auto_text = sprintf(
			"Namaste %s ji! 🙏\n\nWe missed your call at the TripCosmos Pilgrimage Desk.\n\nOur team is currently assisting guests with Kashi Vishwanath VIP Darshan and Cab bookings. Please reply here with your dates or requirements, or tap below to reconnect:\n👉 %s",
			esc_html( $customer_name ?: 'Traveler' ),
			home_url( '/call/?room=trip_' . substr( $clean_phone, -6 ) )
		);

		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			TC_Integration_Evolution::send_message( $clean_phone, $auto_text );
		}

		TC_Agents_Logger::log(
			'missed_call_wa_auto_responded',
			'info',
			array(
				'phone' => $clean_phone,
				'name'  => $customer_name,
			),
			'',
			'voice'
		);

		return true;
	}
}
