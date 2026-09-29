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

	public static function get_provider() {
		return get_option( 'tc_agents_voice_provider', 'jio_sim' ); // 'jio_sim' (100% Free Primary), 'vapi', 'retell'
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

	/**
	 * Place an outbound call to a traveler.
	 * Supports Hybrid Cloud-GSM Telephony: Cloud Vapi (Primary if keys configured)
	 * with automatic 100% Free Failover to Android Jio SIM Phone Bridge.
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

		// Route 1 (Cloud Vapi): If explicitly selected and credentials configured, attempt cloud call
		if ( 'vapi' === $provider ) {
			$api_key         = self::get_api_key();
			$phone_number_id = self::get_phone_number_id();
			$assistant_id    = self::get_assistant_id();

			if ( ! empty( $api_key ) && ! empty( $assistant_id ) && ! empty( $phone_number_id ) ) {
				$vapi_res = self::dispatch_vapi_call( $recipient_phone, $customer_name, $reason, $api_key, $phone_number_id, $assistant_id );
				if ( ! is_wp_error( $vapi_res ) ) {
					return $vapi_res;
				}
				TC_Agents_Logger::log( 'vapi_call_failed_fallback_jio', 'warning', array( 'error' => $vapi_res->get_error_message() ), '', 'voice' );
			}
		}

		// Route 2 (100% Free Primary or Automatic Failover): Dispatch to Android Jio SIM Companion Phone
		return self::dispatch_via_jio_companion( $recipient_phone, $customer_name, $reason );
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
}
