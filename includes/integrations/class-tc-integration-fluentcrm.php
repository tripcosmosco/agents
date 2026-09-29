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

		// Two-way sync: changes made in FluentCRM flow back to the linked TripCosmos contact.
		add_action( 'fluent_crm/contact_created', array( __CLASS__, 'on_contact_created' ), 20, 1 );
		add_action( 'fluent_crm/contact_updated', array( __CLASS__, 'on_contact_changed' ), 20, 1 );
		add_action( 'fluent_crm/contact_email_changed', array( __CLASS__, 'on_contact_email_changed' ), 20, 2 );
		add_action( 'fluent_crm/subscriber_status_changed', array( __CLASS__, 'on_contact_changed' ), 20, 1 );
		add_action( 'fluent_crm/contact_added_to_tags', array( __CLASS__, 'on_contact_changed' ), 20, 1 );
		add_action( 'fluent_crm/contact_removed_from_tags', array( __CLASS__, 'on_contact_changed' ), 20, 1 );
	}

	public static function on_contact_created( $subscriber ) {
		TC_Contact_Service::pull_from_fluentcrm( $subscriber, true );
	}

	public static function on_contact_changed( $subscriber ) {
		TC_Contact_Service::pull_from_fluentcrm( $subscriber, false );
	}

	/**
	 * Email changed in FluentCRM: relink by the old address, then pull.
	 */
	public static function on_contact_email_changed( $subscriber, $old_email = '' ) {
		global $wpdb;
		if ( TC_Contact_Service::$syncing || ! is_object( $subscriber ) ) {
			return;
		}
		if ( $old_email ) {
			$wpdb->update( TC_Contact_Service::table(), array( 'fluentcrm_id' => (int) $subscriber->id ), array( 'email' => strtolower( (string) $old_email ) ) );
		}
		TC_Contact_Service::pull_from_fluentcrm( $subscriber, false );
	}

	/**
	 * Load a FluentCRM subscriber by id.
	 *
	 * @return object|null
	 */
	public static function get_subscriber( $id ) {
		if ( ! self::is_active() || ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return null;
		}
		try {
			return \FluentCrm\App\Models\Subscriber::find( (int) $id );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * FluentCRM list ids for B2B leads: 'all' => master list, plus city keyword => list id.
	 * Configured under Integrations (tc_agents_fluentcrm_b2b_list_id / tc_agents_fluentcrm_city_lists).
	 *
	 * @return array{0:int,1:array<string,int>}
	 */
	public static function b2b_list_config() {
		$master = (int) get_option( 'tc_agents_fluentcrm_b2b_list_id', 0 );
		$cities = array();
		foreach ( preg_split( '/\R/', (string) get_option( 'tc_agents_fluentcrm_city_lists', '' ) ) as $line ) {
			if ( preg_match( '/^\s*([^=]+?)\s*=\s*(\d+)\s*$/', $line, $m ) ) {
				foreach ( explode( '|', strtolower( $m[1] ) ) as $kw ) {
					if ( '' !== trim( $kw ) ) {
						$cities[ trim( $kw ) ] = (int) $m[2];
					}
				}
			}
		}
		return array( $master, $cities );
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

		// Fallback: a phone field with an arbitrary name (e.g. "numeric_field"): a value made only of
		// digits, spaces, +, -, ( ) with 10-13 digits.
		if ( '' === $phone ) {
			foreach ( $form_data as $key => $val ) {
				if ( 0 === strpos( (string) $key, '_' ) ) {
					continue;
				}
				if ( is_string( $val ) && preg_match( '/^\+?[\d\s\-()]{10,20}$/', trim( $val ) ) ) {
					$digits = preg_replace( '/\D/', '', $val );
					if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 13 ) {
						$phone = ( 0 === strpos( trim( $val ), '+' ) ? '+' : '' ) . $digits;
						break;
					}
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

		// Detect Tour / Destination Interest & Requirements
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

		// 2. Lead score: base 70, +15 for phone, +15 for a specific tour/destination
		$score = 70 + ( $phone ? 15 : 0 ) + ( $destination ? 15 : 0 );

		// 3. Upsert via the contact service (dedupes on email / normalised phone, syncs FluentCRM)
		$res = TC_Contact_Service::upsert(
			array(
				'name'           => $name,
				'email'          => $email,
				'phone'          => $phone,
				'source_channel' => 'fluent_forms',
				'score'          => $score,
				'meta'           => array(
					'source'       => 'fluent_forms',
					'form_id'      => is_object( $form ) ? ( $form->id ?? 0 ) : ( $form['id'] ?? 0 ),
					'entry_id'     => $entry_id,
					'destination'  => $destination,
					'requirements' => trim( $requirements ),
				),
				'referrer'       => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			),
			array(
				'activity'      => sprintf( 'Fluent Forms submission (form #%s, entry #%s)', is_object( $form ) ? ( $form->id ?? '?' ) : ( $form['id'] ?? '?' ), $entry_id ),
				'activity_type' => 'form',
			)
		);
		$contact_id = (int) $res['id'];
		if ( ! $contact_id ) {
			return;
		}

		// 4. Twenty CRM (new contacts only)
		if ( 'created' === $res['status'] && class_exists( 'TC_Integration_TwentyCRM' ) && TC_Integration_TwentyCRM::is_configured() ) {
			$twenty_id = TC_Integration_TwentyCRM::push_lead(
				array(
					'name'         => $name,
					'email'        => $email,
					'phone'        => $phone,
					'destination'  => $destination,
					'requirements' => trim( $requirements ),
					'deal_value'   => 0.00,
				)
			);
			if ( $twenty_id ) {
				global $wpdb;
				$wpdb->update( TC_Contact_Service::table(), array( 'twentycrm_id' => $twenty_id ), array( 'id' => $contact_id ) );
			}
		}

		// 5. Auto-enroll into automated follow-up sequence
		if ( class_exists( 'TC_Agent_Sequences' ) ) {
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
	 * Defensive alias for sync_lead.
	 */
	public static function create_or_update_contact( array $data ) {
		return self::sync_lead( $data );
	}

	/**
	 * Create or update a contact in Fluent CRM with rich metadata, tags, and automated lists.
	 *
	 * @param array $data Contact data ['name', 'email', 'phone', 'destination', 'channel', 'score', 'stage', 'requirements']
	 * @return int|false Contact ID or false.
	 */
	public static function sync_lead( array $data ) {
		if ( ! self::is_active() ) {
			return false;
		}

		$phone      = sanitize_text_field( $data['phone'] ?? '' );
		$email      = sanitize_email( $data['email'] ?? '' );
		$channel    = sanitize_text_field( $data['channel'] ?? 'web' );
		$stage      = sanitize_text_field( $data['stage'] ?? 'inquiry' );
		$score      = (int) ( $data['score'] ?? 50 );
		$is_b2b     = ( 'b2b' === $channel || 'b2b_partner' === $channel );

		// Phone-only leads stay local: inventing an address would bounce and pollute FluentCRM lists.
		if ( ! TC_Contact_Service::is_real_email( $email ) ) {
			return false;
		}

		$name_parts = explode( ' ', trim( $data['name'] ?? '' ), 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		$tags  = array( 'tripcosmos-lead', 'channel-' . $channel );
		$lists = array();

		// B2B tagging & list segmentation (tags + list ids configured under Integrations)
		if ( $is_b2b ) {
			$b2b_tags = TC_Contact_Service::parse_tags( get_option( 'tc_agents_fluentcrm_b2b_tags', 'b2b-travel-agency' ) );
			$tags     = array_merge( $tags, $b2b_tags );
			list( $master_list, $city_lists ) = self::b2b_list_config();
			if ( $master_list ) {
				$lists[] = $master_list;
			}
			$dest_lower = strtolower( (string) ( $data['destination'] ?? '' ) );
			foreach ( $city_lists as $kw => $list_id ) {
				if ( '' !== $dest_lower && false !== strpos( $dest_lower, $kw ) ) {
					$lists[] = $list_id;
					break;
				}
			}
		}

		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$tags = array_merge( $tags, TC_Contact_Service::parse_tags( $data['tags'] ) );
		}

		if ( ! empty( $data['destination'] ) ) {
			$tags[] = 'tour-' . sanitize_title( $data['destination'] );
		}
		$is_hot = ( 'hot' === $stage || $score >= 80 ) && ! in_array( $stage, array( 'won', 'completed', 'lost' ), true );
		if ( $is_hot ) {
			$tags[] = 'hot-traveler';
		}

		try {
			$contact_api = FluentCrmApi( 'contacts' );

			$contact_data = array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'tags'       => array_values( array_unique( $tags ) ),
			);
			if ( '' !== $phone ) {
				$contact_data['phone'] = $phone;
			}
			// Only set a status for brand-new subscribers. Existing ones keep theirs, so a
			// lead sync can never resubscribe someone who unsubscribed or bounced.
			if ( ! $contact_api->getContact( $email ) ) {
				$contact_data['status'] = sanitize_key( get_option( 'tc_agents_fluentcrm_new_status', 'subscribed' ) );
			}
			if ( ! empty( $lists ) ) {
				$contact_data['lists'] = array_values( array_unique( $lists ) );
			}

			$contact = $contact_api->createOrUpdate( $contact_data );

			if ( $contact && isset( $contact->id ) ) {
				// Attach lists explicitly to guarantee firing list-added trigger sequence
				if ( ! empty( $lists ) && method_exists( $contact, 'attachLists' ) ) {
					$contact->attachLists( $lists );
				}

				// Lead cooled down / closed: drop the hot tag so hot-lead automations stop.
				if ( ! $is_hot && method_exists( $contact, 'detachTags' ) && class_exists( '\FluentCrm\App\Models\Tag' ) ) {
					$hot = \FluentCrm\App\Models\Tag::where( 'slug', 'hot-traveler' )->first();
					if ( $hot ) {
						$contact->detachTags( array( $hot->id ) );
					}
				}

				// Deep Sync to custom meta fields
				if ( method_exists( $contact, 'updateOrCreateMeta' ) ) {
					$contact->updateOrCreateMeta( 'tc_stage', $stage );
					$contact->updateOrCreateMeta( 'tc_owner', sanitize_text_field( $data['owner'] ?? '' ) );
					$contact->updateOrCreateMeta( 'tc_lead_score', $score );
					if ( ! empty( $data['destination'] ) ) {
						$contact->updateOrCreateMeta( 'tc_destination_preference', sanitize_text_field( $data['destination'] ) );
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
						'lists'     => $lists,
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
