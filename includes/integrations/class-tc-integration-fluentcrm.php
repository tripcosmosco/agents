<?php
/**
 * Fluent CRM & Fluent Forms Pro Native Integration Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_FluentCRM {

	/**
	 * Boot hooks for FluentCRM and Fluent Forms Pro.
	 */
	public static function init() {
		// Listen for Fluent Forms Pro submissions across the site
		add_action( 'fluentform/submission_inserted', array( __CLASS__, 'handle_fluentform_submission' ), 10, 3 );
		add_action( 'fluentform_submission_inserted', array( __CLASS__, 'handle_fluentform_submission' ), 10, 3 );
	}

	/**
	 * Check if Fluent CRM plugin is active on this WordPress instance.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return function_exists( 'FluentCrmApi' ) || defined( 'FLUENTCRM' );
	}

	/**
	 * Handle incoming Fluent Forms submission.
	 * Auto-qualifies lead, creates contact in TripCosmos CRM, syncs to FluentCRM & Twenty CRM,
	 * and enrolls in automated nurture sequence.
	 *
	 * @param int          $entry_id  Entry ID.
	 * @param array        $form_data Form field values.
	 * @param object|array $form      Form definition.
	 */
	public static function handle_fluentform_submission( $entry_id, $form_data, $form = null ) {
		if ( empty( $form_data ) || ! is_array( $form_data ) ) {
			return;
		}

		// 1. Extract contact details
		$email = '';
		$phone = '';
		$name  = '';
		$destination = '';
		$requirements = '';

		// Detect Email
		foreach ( $form_data as $key => $val ) {
			if ( is_string( $val ) && is_email( trim( $val ) ) ) {
				$email = sanitize_email( trim( $val ) );
				break;
			}
		}

		// Detect Phone
		foreach ( $form_data as $key => $val ) {
			if ( is_string( $val ) && preg_match( '/phone|mobile|whatsapp|tel/i', (string) $key ) ) {
				$clean = preg_replace( '/[^\d+]/', '', trim( $val ) );
				if ( strlen( $clean ) >= 7 ) {
					$phone = $clean;
					break;
				}
			}
		}

		// Detect Name
		if ( isset( $form_data['names'] ) && is_array( $form_data['names'] ) ) {
			$name = trim( ( $form_data['names']['first_name'] ?? '' ) . ' ' . ( $form_data['names']['last_name'] ?? '' ) );
		} elseif ( ! empty( $form_data['name'] ) && is_string( $form_data['name'] ) ) {
			$name = sanitize_text_field( $form_data['name'] );
		} elseif ( ! empty( $form_data['full_name'] ) && is_string( $form_data['full_name'] ) ) {
			$name = sanitize_text_field( $form_data['full_name'] );
		}

		// Detect Trek / Destination Interest & Requirements
		foreach ( $form_data as $key => $val ) {
			if ( is_string( $val ) ) {
				$k_lower = strtolower( (string) $key );
				if ( preg_match( '/trek|destination|package|tour|location|itinerary/i', $k_lower ) ) {
					$destination = sanitize_text_field( $val );
				} elseif ( preg_match( '/message|notes|details|requirements|inquiry|comment/i', $k_lower ) ) {
					$requirements .= ' ' . sanitize_textarea_field( $val );
				}
			}
		}

		if ( empty( $email ) && empty( $phone ) ) {
			return; // Insufficient contact info
		}

		// If no synthetic email, generate standard email fallback for CRM tracking
		if ( empty( $email ) && ! empty( $phone ) ) {
			$email = 'traveler-' . preg_replace( '/\D/', '', $phone ) . '@leads.tripcosmos.co';
		}

		// 2. Compute Lead Score (Base 70, +15 for phone, +15 for specific trek/destination)
		$score = 70;
		if ( ! empty( $phone ) ) {
			$score += 15;
		}
		if ( ! empty( $destination ) ) {
			$score += 15;
		}

		// 3. Upsert Lead in TripCosmos Contacts table
		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$existing = null;
		if ( ! empty( $email ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE email = %s LIMIT 1", $email ), ARRAY_A );
		}
		if ( ! $existing && ! empty( $phone ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE phone = %s LIMIT 1", $phone ), ARRAY_A );
		}

		$meta = array(
			'source'          => 'fluent_forms',
			'form_id'         => is_object( $form ) ? ( $form->id ?? 0 ) : ( $form['id'] ?? 0 ),
			'entry_id'        => $entry_id,
			'destination'     => $destination,
			'requirements'    => trim( $requirements ),
			'last_updated_at' => current_time( 'mysql' ),
		);

		$contact_id = 0;
		if ( $existing ) {
			$contact_id = (int) $existing['id'];
			$wpdb->update(
				$table_contacts,
				array(
					'score'     => max( (int) $existing['score'], $score ),
					'meta_data' => wp_json_encode( array_merge( json_decode( (string) $existing['meta_data'], true ) ?: array(), $meta ) ),
					'updated_at'=> current_time( 'mysql' ),
				),
				array( 'id' => $contact_id )
			);
		} else {
			$wpdb->insert(
				$table_contacts,
				array(
					'name'           => $name ?: 'Himalayan Traveler',
					'email'          => $email,
					'phone'          => $phone,
					'source_channel' => 'fluent_forms',
					'stage'          => 'inquiry',
					'score'          => $score,
					'deal_value'     => 15000.00, // Standard baseline package value
					'meta_data'      => wp_json_encode( $meta ),
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				)
			);
			$contact_id = (int) $wpdb->insert_id;
		}

		// 4. Sync to FluentCRM
		$fluent_id = self::sync_lead(
			array(
				'id'           => $contact_id,
				'name'         => $name,
				'email'        => $email,
				'phone'        => $phone,
				'destination'  => $destination,
				'channel'      => 'fluent_forms',
				'score'        => $score,
				'stage'        => 'inquiry',
				'requirements' => trim( $requirements ),
			)
		);

		if ( $fluent_id && $contact_id ) {
			$wpdb->update( $table_contacts, array( 'fluentcrm_id' => $fluent_id ), array( 'id' => $contact_id ) );
		}

		// 5. Sync to Twenty CRM (crm.vmstudio.digital)
		if ( class_exists( 'TC_Integration_TwentyCRM' ) && TC_Integration_TwentyCRM::is_configured() ) {
			$twenty_id = TC_Integration_TwentyCRM::push_lead(
				array(
					'name'         => $name,
					'email'        => $email,
					'phone'        => $phone,
					'destination'  => $destination,
					'requirements' => trim( $requirements ),
					'deal_value'   => 15000.00,
				)
			);
			if ( $twenty_id && $contact_id ) {
				$wpdb->update( $table_contacts, array( 'twentycrm_id' => $twenty_id ), array( 'id' => $contact_id ) );
			}
		}

		// 6. Auto-enroll into Automated Follow-up Sequence
		if ( class_exists( 'TC_Agent_Sequences' ) && $contact_id > 0 ) {
			TC_Agent_Sequences::enroll_contact( $contact_id, 'fluentform_submitted' );
		}

		TC_Agents_Logger::log(
			'fluentform_lead_captured',
			'info',
			array(
				'contact_id'  => $contact_id,
				'email'       => $email,
				'phone'       => $phone,
				'destination' => $destination,
			)
		);
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
	 * Create or update a contact in Fluent CRM with rich metadata and tags.
	 *
	 * @param array $data Contact data ['name', 'email', 'phone', 'destination', 'channel', 'score', 'stage', 'requirements']
	 * @return int|false Contact ID or false.
	 */
	public static function sync_lead( array $data ) {
		if ( ! self::is_active() ) {
			return false;
		}

		$phone = sanitize_text_field( $data['phone'] ?? '' );
		$email = sanitize_email( $data['email'] ?? '' );
		if ( empty( $email ) && ! empty( $phone ) ) {
			$clean_digits = preg_replace( '/\D/', '', $phone );
			if ( ! empty( $clean_digits ) ) {
				$email = 'traveler-' . $clean_digits . '@leads.tripcosmos.co';
			}
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		$name_parts = explode( ' ', trim( $data['name'] ?? '' ), 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';
		$channel    = sanitize_text_field( $data['channel'] ?? 'web' );
		$stage      = sanitize_text_field( $data['stage'] ?? 'inquiry' );
		$score      = (int) ( $data['score'] ?? 50 );

		$tags = array( 'tripcosmos-lead', 'channel-' . $channel );
		if ( ! empty( $data['destination'] ) ) {
			$tags[] = 'trek-' . sanitize_title( $data['destination'] );
		}
		if ( 'hot' === $stage || $score >= 80 ) {
			$tags[] = 'hot-traveler';
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
				// Deep Sync to custom meta fields
				if ( method_exists( $contact, 'updateOrCreateMeta' ) ) {
					$contact->updateOrCreateMeta( 'tc_stage', $stage );
					$contact->updateOrCreateMeta( 'tc_lead_score', $score );
					if ( ! empty( $data['destination'] ) ) {
						$contact->updateOrCreateMeta( 'tc_trek_preference', sanitize_text_field( $data['destination'] ) );
					}
					if ( ! empty( $data['requirements'] ) ) {
						$contact->updateOrCreateMeta( 'tc_requirements', sanitize_textarea_field( $data['requirements'] ) );
					}
				}

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

	/**
	 * Retrieve CRM Context for AI prompt injection.
	 *
	 * @param string $email
	 * @return string
	 */
	public static function get_subscriber_intel( $email ) {
		if ( ! self::is_active() || empty( $email ) ) {
			return '';
		}

		try {
			$contact_api = FluentCrmApi( 'contacts' );
			$contact     = $contact_api->getContact( $email );
			if ( ! $contact ) {
				return '';
			}

			$tags = array();
			if ( isset( $contact->tags ) && is_iterable( $contact->tags ) ) {
				foreach ( $contact->tags as $t ) {
					$tags[] = $t->title ?? $t->slug ?? '';
				}
			}

			$intel = 'TRAVELER CRM CONTEXT: ';
			if ( ! empty( $tags ) ) {
				$intel .= 'Tags: [' . implode( ', ', array_filter( $tags ) ) . ']. ';
			}
			if ( ! empty( $contact->last_activity ) ) {
				$intel .= 'Last CRM Activity: ' . $contact->last_activity . '. ';
			}

			return trim( $intel );
		} catch ( Exception $e ) {
			return '';
		}
	}
}
