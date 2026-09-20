<?php
/**
 * Twenty CRM REST & GraphQL Integration Adapter.
 * Connects TripCosmos Agents to Twenty CRM (crm.vmstudio.digital)
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
	 * Push a new lead to Twenty CRM:
	 * 1. Creates/Upserts Person (/rest/people)
	 * 2. Creates Opportunity / Deal (/rest/opportunities)
	 * 3. Creates Expedition Briefing Note (/rest/notes)
	 *
	 * @param array $data ['name', 'email', 'phone', 'destination', 'requirements', 'deal_value']
	 * @return string|false Twenty Person ID or false.
	 */
	public static function push_lead( array $data ) {
		if ( ! self::is_configured() ) {
			return false;
		}

		$name_parts = explode( ' ', trim( $data['name'] ?? '' ), 2 );
		$first_name = $name_parts[0] ?? 'Traveler';
		$last_name  = $name_parts[1] ?? '';
		$email      = sanitize_email( $data['email'] ?? '' );
		$phone      = sanitize_text_field( $data['phone'] ?? '' );
		$dest       = sanitize_text_field( $data['destination'] ?? '' );
		$reqs       = sanitize_textarea_field( $data['requirements'] ?? '' );
		$value      = max( 0.0, (float) ( $data['deal_value'] ?? 15000.00 ) );

		// 1. Create or Find Person
		$endpoint = self::get_base_url() . '/rest/people';
		$payload  = array(
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
		if ( ! empty( $dest ) ) {
			$payload['city'] = $dest;
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

		if ( $code < 200 || $code >= 300 ) {
			return false;
		}

		$person_id = $body['data']['id'] ?? $body['id'] ?? '';
		if ( empty( $person_id ) ) {
			return false;
		}

		TC_Agents_Logger::log( 'twentycrm_person_synced', 'info', array( 'person_id' => $person_id, 'email' => $email ) );

		// 2. Create Opportunity / Deal in Twenty CRM
		if ( $value > 0 || ! empty( $dest ) ) {
			self::create_opportunity( $person_id, $first_name, $dest, $value );
		}

		// 3. Create Note with traveler preferences & requirements
		if ( ! empty( $reqs ) || ! empty( $dest ) ) {
			self::create_note( $person_id, $dest, $reqs );
		}

		return $person_id;
	}

	/**
	 * Create an Opportunity in Twenty CRM linked to the Person.
	 */
	public static function create_opportunity( $person_id, $traveler_name, $destination, $amount ) {
		$endpoint = self::get_base_url() . '/rest/opportunities';
		$title    = sprintf( 'Expedition: %s (%s)', $destination ?: 'Himalayan Trek', $traveler_name );

		$payload = array(
			'name'              => $title,
			'amount'            => array(
				'amountMicros' => (int) round( $amount * 1000000 ),
				'currencyCode' => 'INR',
			),
			'stage'             => 'NEW',
			'pointOfContactId'  => $person_id,
		);

		wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);
	}

	/**
	 * Create a Note in Twenty CRM attached to the Person.
	 */
	public static function create_note( $person_id, $destination, $requirements ) {
		$endpoint = self::get_base_url() . '/rest/notes';
		$content  = sprintf(
			"TripCosmos AI Capture:\nDestination / Trek: %s\nRequirements: %s\nCaptured on: %s via Autonomous Agent",
			$destination ?: 'Himalayan Exploration',
			$requirements ?: 'General expedition inquiry',
			current_time( 'mysql' )
		);

		$payload = array(
			'body'        => $content,
			'attachTo'    => array(
				'personId' => $person_id,
			),
		);

		wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);
	}

	/**
	 * Handle inbound webhook from Twenty CRM (updates lead stage).
	 */
	public static function handle_incoming_webhook( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Empty payload' ), 400 );
		}

		$event = $data['event'] ?? $data['type'] ?? '';
		$obj   = $data['data'] ?? array();

		if ( 'opportunity.updated' === $event && ! empty( $obj['pointOfContactId'] ) ) {
			$stage = strtolower( (string) ( $obj['stage'] ?? '' ) );
			global $wpdb;
			$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

			// Map Twenty stage to TripCosmos stage
			$mapped_stage = 'inquiry';
			if ( in_array( $stage, array( 'won', 'closed_won' ), true ) ) {
				$mapped_stage = 'won';
			} elseif ( in_array( $stage, array( 'lost', 'closed_lost' ), true ) ) {
				$mapped_stage = 'lost';
			} elseif ( in_array( $stage, array( 'negotiation', 'proposal' ), true ) ) {
				$mapped_stage = 'proposal';
			}

			$wpdb->update(
				$table_contacts,
				array( 'stage' => $mapped_stage, 'updated_at' => current_time( 'mysql' ) ),
				array( 'twentycrm_id' => $obj['pointOfContactId'] )
			);
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
