<?php
/**
 * Google Sheets Lead Sync & Pricing Lookup Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_Sheets {

	public static function is_enabled() {
		return '1' === (string) get_option( 'tc_agents_sheets_enabled', '0' );
	}

	public static function get_webhook_url() {
		return get_option( 'tc_agents_sheets_webhook_url', '' );
	}

	/**
	 * Append lead record to Google Sheet.
	 *
	 * @param array $lead_data
	 * @return bool
	 */
	public static function append_lead( array $lead_data ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$webhook_url = self::get_webhook_url();
		if ( empty( $webhook_url ) ) {
			return false;
		}

		$payload = array(
			'timestamp'   => current_time( 'mysql' ),
			'name'        => $lead_data['name'] ?? '',
			'email'       => $lead_data['email'] ?? '',
			'phone'       => $lead_data['phone'] ?? '',
			'destination' => $lead_data['destination'] ?? '',
			'group_size'  => $lead_data['group_size'] ?? '',
			'month'       => $lead_data['month'] ?? '',
			'channel'     => $lead_data['channel'] ?? 'web',
		);

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'   => 6,
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			TC_Agents_Logger::log( 'sheets_sync_error', 'warning', array( 'error' => $response->get_error_message() ) );
			return false;
		}

		return wp_remote_retrieve_response_code( $response ) < 400;
	}
}
