<?php
/**
 * Twenty CRM REST Integration Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_TwentyCRM {

	public static function get_base_url() {
		$url = get_option( 'tc_agents_twentycrm_url', 'https://crm.vmstudio.digital' );
		return untrailingslashit( $url );
	}

	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'twentycrm_api_key' ) : get_option( 'tc_agents_twentycrm_api_key', '' );
	}


	public static function is_configured() {
		return ! empty( self::get_base_url() ) && ! empty( self::get_api_key() );
	}

	/**
	 * Test connection to Twenty CRM.
	 */
	public static function test_connection() {
		if ( ! self::is_configured() ) {
			return array( 'success' => false, 'message' => __( 'Twenty CRM URL or API Key missing.', 'tripcosmos-agents' ) );
		}

		$endpoint = self::get_base_url() . '/rest/people?limit=1';
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_api_key(),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'message' => __( 'Connected to Twenty CRM.', 'tripcosmos-agents' ) );
		}

		return array( 'success' => false, 'message' => sprintf( __( 'Twenty CRM returned HTTP %d', 'tripcosmos-agents' ), $code ) );
	}

	/**
	 * Push a new lead/person to Twenty CRM.
	 */
	public static function push_lead( array $data ) {
		if ( ! self::is_configured() ) {
			return false;
		}

		$name_parts = explode( ' ', trim( $data['name'] ?? '' ), 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';
		$email      = sanitize_email( $data['email'] ?? '' );
		$phone      = sanitize_text_field( $data['phone'] ?? '' );

		$endpoint = self::get_base_url() . '/rest/people';

		$payload = array(
			'name' => array(
				'firstName' => $first_name,
				'lastName'  => $last_name,
			),
		);

		if ( ! empty( $email ) ) {
			$payload['emails'] = array(
				'primaryEmail' => $email,
			);
		}
		if ( ! empty( $phone ) ) {
			$payload['phones'] = array(
				'primaryPhone' => $phone,
			);
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 8,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_api_key(),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			TC_Agents_Logger::log( 'twentycrm_push_error', 'warning', array( 'error' => $response->get_error_message() ) );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			$twenty_id = $body['data']['id'] ?? $body['id'] ?? '';
			TC_Agents_Logger::log( 'twentycrm_lead_pushed', 'info', array( 'twenty_id' => $twenty_id, 'email' => $email ) );
			return $twenty_id;
		}

		return false;
	}
}
