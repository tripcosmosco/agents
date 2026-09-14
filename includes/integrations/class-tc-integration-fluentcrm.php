<?php
/**
 * Fluent CRM Native Integration Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_FluentCRM {

	/**
	 * Check if Fluent CRM plugin is active on this WordPress instance.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return function_exists( 'FluentCrmApi' ) || defined( 'FLUENTCRM' );
	}

	/**
	 * Search for an existing contact in Fluent CRM by email or phone.
	 *
	 * @param string $email
	 * @param string $phone
	 * @return object|null
	 */
	public static function find_contact( $email, $phone = '' ) {
		if ( ! self::is_active() ) {
			return null;
		}

		try {
			$contact_api = FluentCrmApi( 'contacts' );

			if ( ! empty( $email ) && is_email( $email ) ) {
				$contact = $contact_api->getContact( $email );
				if ( $contact ) {
					return $contact;
				}
			}

			if ( ! empty( $phone ) ) {
				// Query by phone via FluentCrmApi query builder
				$contact = $contact_api->query()->where( 'phone', $phone )->first();
				if ( $contact ) {
					return $contact;
				}
			}
		} catch ( Exception $e ) {
			TC_Agents_Logger::log( 'fluentcrm_lookup_error', 'warning', array( 'error' => $e->getMessage() ) );
		}

		return null;
	}

	/**
	 * Create or update a contact in Fluent CRM.
	 *
	 * @param array $data Contact data ['name', 'email', 'phone', 'destination', 'channel']
	 * @return int|false Contact ID or false.
	 */
	public static function sync_lead( array $data ) {
		if ( ! self::is_active() ) {
			return false;
		}

		$email = sanitize_email( $data['email'] ?? '' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			// Fluent CRM requires a valid email for contacts
			return false;
		}

		$name_parts = explode( ' ', trim( $data['name'] ?? '' ), 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';
		$phone      = sanitize_text_field( $data['phone'] ?? '' );
		$channel    = sanitize_text_field( $data['channel'] ?? 'web' );

		$tags = array( 'agent-lead', 'channel-' . $channel );
		if ( ! empty( $data['destination'] ) ) {
			$tags[] = 'dest-' . sanitize_title( $data['destination'] );
		}

		try {
			$contact_api = FluentCrmApi( 'contacts' );

			$contact_data = array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'phone'      => $phone,
				'status'     => 'subscribed',
				'tags'       => $tags,
			);

			$contact = $contact_api->createOrUpdate( $contact_data );

			if ( $contact && isset( $contact->id ) ) {
				TC_Agents_Logger::log(
					'fluentcrm_synced_lead',
					'info',
					array(
						'fluent_id' => $contact->id,
						'email'     => $email,
						'tags'      => $tags,
					)
				);
				return (int) $contact->id;
			}
		} catch ( Exception $e ) {
			TC_Agents_Logger::log( 'fluentcrm_sync_error', 'error', array( 'error' => $e->getMessage() ) );
		}

		return false;
	}
}
