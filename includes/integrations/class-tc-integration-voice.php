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
		return '1' === (string) get_option( 'tc_agents_voice_enabled', '0' );
	}

	public static function get_provider() {
		return get_option( 'tc_agents_voice_provider', 'vapi' ); // 'vapi', 'retell', 'twilio'
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
	 *
	 * @param string $recipient_phone Phone number in E.164.
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

		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'voice_not_configured', __( 'Voice provider API key is missing.', 'tripcosmos-agents' ) );
		}

		$provider = self::get_provider();

		// Indian Telecommunications/TRAI compliance: Explicit recording disclosure
		$default_disclosure = sprintf(
			__( 'Namaste %s, this is the automated expedition assistant from TripCosmos.co. Please note this call may be recorded for quality and security purposes. Regarding your trip inquiry: ', 'tripcosmos-agents' ),
			! empty( $customer_name ) ? esc_html( $customer_name ) : ''
		);
		$consent_notice = get_option( 'tc_agents_voice_recording_disclosure', $default_disclosure );

		// Vapi / Retell endpoint
		$endpoint = 'https://api.vapi.ai/call/phone';
		$payload  = array(
			'phoneNumberId'   => self::get_phone_number_id(),
			'assistantId'     => self::get_assistant_id(),
			'customer'        => array(
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
			TC_Agents_Logger::log( 'voice_call_error', 'critical', array( 'error' => $response->get_error_message() ), '', 'voice' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			TC_Agents_Guardrails::record_outbound( 'voice' );
			$call_id = $body['id'] ?? 'call_' . time();

			TC_Agents_Logger::log(
				'voice_call_initiated',
				'info',
				array(
					'call_id'   => $call_id,
					'recipient' => $recipient_phone,
					'reason'    => $reason,
				),
				'',
				'voice'
			);

			return array(
				'success' => true,
				'call_id' => $call_id,
				'status'  => 'queued',
			);
		}

		return new WP_Error( 'voice_call_failed', $body['message'] ?? "Voice call failed (HTTP {$code})" );
	}

	/**
	 * Handle voice call webhook (transcript and recording update).
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
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
