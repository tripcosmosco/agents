<?php
/**
 * WhatsApp Gateway Integration Adapter (wa.vmstudio.digital).
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_WhatsApp {

	public static function get_api_url() {
		$url = get_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' );
		return untrailingslashit( $url );
	}

	public static function get_token() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'whatsapp_token' ) : get_option( 'tc_agents_whatsapp_token', '' );
	}


	public static function is_configured() {
		return ! empty( self::get_api_url() ) && ! empty( self::get_token() );
	}

	/**
	 * Process incoming webhook from wa.vmstudio.digital.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle_incoming_webhook( WP_REST_Request $request ) {
		$secret_configured = get_option( 'tc_agents_whatsapp_webhook_secret', '' );

		// 1. Meta Cloud API / WhatsApp GET verification challenge
		$hub_mode      = $request->get_param( 'hub_mode' ) ?: $request->get_param( 'hub.mode' );
		$hub_token     = $request->get_param( 'hub_verify_token' ) ?: $request->get_param( 'hub.verify_token' );
		$hub_challenge = $request->get_param( 'hub_challenge' ) ?: $request->get_param( 'hub.challenge' );

		if ( 'subscribe' === $hub_mode ) {
			if ( empty( $secret_configured ) || $hub_token === $secret_configured ) {
				echo esc_html( $hub_challenge );
				exit;
			}
			return new WP_REST_Response( 'Forbidden', 403 );
		}

		$provided_token = $request->get_header( 'x-webhook-secret' ) ?: $request->get_param( 'token' );

		if ( ! empty( $secret_configured ) && $secret_configured !== $provided_token ) {
			TC_Agents_Logger::log( 'whatsapp_webhook_auth_failed', 'warning', array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ), '', 'whatsapp' );
			return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
		}

		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		// Normalize sender and text across common gateway schemas
		$sender_phone = $data['from'] ?? $data['sender'] ?? $data['phone'] ?? ( $data['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? '' );
		$message_text = $data['body'] ?? $data['message'] ?? $data['text'] ?? ( $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? '' );

		if ( empty( $sender_phone ) || empty( $message_text ) ) {
			return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'No message or sender' ), 200 );
		}

		// Normalize phone
		$sender_phone = preg_replace( '/[^0-9]/', '', $sender_phone );
		$session_id   = 'wa_' . $sender_phone;

		// Unify or lookup contact
		self::ensure_whatsapp_contact( $sender_phone, $data['name'] ?? '' );

		// Pass to Agent Orchestrator
		$result = TC_Agent_Orchestrator::handle_message(
			$message_text,
			$session_id,
			'whatsapp',
			'tripcosmos-guide',
			array( 'phone' => $sender_phone, 'raw_data' => $data )
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'status' => 'error',
					'error'  => $result->get_error_message(),
				),
				200
			);
		}

		$reply_text = $result['reply'] ?? '';
		if ( ! empty( $reply_text ) ) {
			self::send_message( $sender_phone, $reply_text );
		}

		return new WP_REST_Response( array( 'status' => 'success', 'delivered' => true ), 200 );
	}

	/**
	 * Send an outbound WhatsApp message via wa.vmstudio.digital.
	 *
	 * @param string $recipient_phone E.164 or digits.
	 * @param string $message
	 * @return bool
	 */
	public static function send_message( $recipient_phone, $message ) {
		// Guardrails check before any outbound message
		$guard = TC_Agents_Guardrails::check_permission( 'whatsapp', $recipient_phone );
		if ( is_wp_error( $guard ) ) {
			TC_Agents_Logger::log( 'whatsapp_outbound_blocked', 'warning', array( 'reason' => $guard->get_error_message() ), '', 'whatsapp' );
			return false;
		}

		if ( ! self::is_configured() ) {
			TC_Agents_Logger::log( 'whatsapp_not_configured', 'warning', array(), '', 'whatsapp' );
			return false;
		}

		$endpoint = self::get_api_url() . '/api/send';
		$payload  = array(
			'to'      => preg_replace( '/[^0-9]/', '', $recipient_phone ),
			'message' => $message,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_token(),
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			TC_Agents_Logger::log( 'whatsapp_send_error', 'error', array( 'error' => $response->get_error_message() ), '', 'whatsapp' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			TC_Agents_Guardrails::record_outbound( 'whatsapp' );
			return true;
		}

		TC_Agents_Logger::log( 'whatsapp_send_failed_http', 'warning', array( 'code' => $code ), '', 'whatsapp' );
		return false;
	}

	/**
	 * Ensure contact record is linked to this WhatsApp phone.
	 */
	private static function ensure_whatsapp_contact( $phone, $name = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_contacts';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE phone = %s LIMIT 1", $phone ) );
		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'phone'          => $phone,
					'name'           => $name ?: 'WhatsApp Traveler',
					'source_channel' => 'whatsapp',
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}
}
