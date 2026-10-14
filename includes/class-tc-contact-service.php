<?php
/**
 * Contact / Lead Service.
 *
 * Every write to tc_agent_contacts goes through here so that phone normalisation, de-duplication,
 * the activity timeline, consent and FluentCRM sync behave the same no matter where a lead comes from
 * (chat tool, quote form, Fluent Forms, WhatsApp, mobile app, admin).
 *
 * Ownership: FluentCRM owns identity + marketing state (email, subscription status, tags). This table
 * owns the operational pipeline (stage, owner, follow-ups, bookings) and is linked by fluentcrm_id.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Contact_Service {

	/** Synthetic addresses older versions invented for phone-only leads. Never email them. */
	const SYNTHETIC_EMAIL_DOMAINS = array( '@leads.tripcosmos.co', '@b2b.tripcosmos.co' );

	/** FluentCRM statuses that mean "do not send marketing email". */
	const EMAIL_BLOCKED_STATUSES = array( 'unsubscribed', 'bounced', 'complained', 'spammed' );

	/** WhatsApp keywords that opt a number out / back in. */
	const OPT_OUT_KEYWORDS = array( 'STOP', 'UNSUBSCRIBE', 'STOP ALL', 'OPT OUT', 'OPTOUT' );
	const OPT_IN_KEYWORDS  = array( 'START', 'SUBSCRIBE', 'UNSTOP' );

	/** Set while we are pushing to FluentCRM so its hooks don't echo the change back to us. */
	public static $syncing = false;

	/**
	 * Pipeline stages, in order.
	 *
	 * @return array slug => label
	 */
	public static function stages() {
		return array(
			'inquiry'     => __( 'Inquiry', 'tripcosmos-agents' ),
			'qualified'   => __( 'Qualified', 'tripcosmos-agents' ),
			'proposal'    => __( 'Proposal', 'tripcosmos-agents' ),
			'negotiation' => __( 'Negotiation', 'tripcosmos-agents' ),
			'won'         => __( 'Won', 'tripcosmos-agents' ),
			'completed'   => __( 'Trip Completed', 'tripcosmos-agents' ),
			'lost'        => __( 'Lost', 'tripcosmos-agents' ),
		);
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_contacts';
	}

	/**
	 * Normalise a phone number to E.164. Numbers without a country code are assumed to be in the
	 * default country (India unless the tc_agents_default_country_code option says otherwise).
	 *
	 * @param string $raw Phone as typed.
	 * @return string E.164 (e.g. +919876543210) or '' when it isn't a plausible number.
	 */
	public static function normalize_phone( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$has_plus = 0 === strpos( $raw, '+' ) || 0 === strpos( $raw, '00' );
		$digits   = preg_replace( '/\D/', '', $raw );
		if ( 0 === strpos( $raw, '00' ) ) {
			$digits = substr( $digits, 2 );
		}
		$cc = preg_replace( '/\D/', '', (string) get_option( 'tc_agents_default_country_code', '91' ) ) ?: '91';

		if ( ! $has_plus ) {
			if ( 11 === strlen( $digits ) && '0' === $digits[0] ) {
				$digits = $cc . substr( $digits, 1 );            // 09876543210 (trunk prefix)
			} elseif ( 10 === strlen( $digits ) ) {
				$digits = $cc . $digits;                          // 9876543210
			} elseif ( 0 !== strpos( $digits, $cc ) ) {
				// Unknown length without a country code: keep as-is if it looks international.
				if ( strlen( $digits ) < 11 ) {
					return '';
				}
			}
		}
		if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
			return '';
		}
		return '+' . $digits;
	}

	/**
	 * True for a real, deliverable-looking email (not one of the synthetic lead addresses).
	 */
	public static function is_real_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}
		foreach ( self::SYNTHETIC_EMAIL_DOMAINS as $d ) {
			if ( substr( $email, -strlen( $d ) ) === $d ) {
				return false;
			}
		}
		return true;
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", (int) $id ), ARRAY_A ) ?: null;
	}

	/**
	 * Find an existing contact by email, then normalised phone, then (for rows saved before v1.5)
	 * by the last 10 digits of the phone.
	 */
	public static function find( $email = '', $phone = '', $fluentcrm_id = 0 ) {
		global $wpdb;
		$t = self::table();

		if ( $fluentcrm_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE fluentcrm_id = %d ORDER BY id ASC LIMIT 1", (int) $fluentcrm_id ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		if ( self::is_real_email( $email ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s ORDER BY id ASC LIMIT 1", strtolower( trim( $email ) ) ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		$e164 = self::normalize_phone( $phone );
		if ( '' !== $e164 ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE phone_e164 = %s ORDER BY id ASC LIMIT 1", $e164 ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
			$last10 = substr( $e164, -10 );
			$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE phone_e164 IS NULL AND phone LIKE %s ORDER BY id ASC LIMIT 1", '%' . $wpdb->esc_like( $last10 ) ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Create or update a contact.
	 *
	 * Accepted keys: name, email, phone, source_channel, stage, score, deal_value, owner, tags (array|string),
	 * meta (array, merged into meta_data), utm_source, utm_medium, utm_campaign, referrer, landing_page,
	 * consent (bool, marketing consent given), consent_source, fluentcrm_id, next_follow_up_at.
	 *
	 * Existing values are never blanked by an empty input, stage is only changed when passed explicitly,
	 * and attribution (UTM/referrer) is first-touch: only filled while empty.
	 *
	 * @param array $data   Contact fields.
	 * @param array $opts   'sync' => bool (push to FluentCRM, default true), 'activity' => string note for the timeline.
	 * @return array { id:int, status:'created'|'updated'|'invalid', contact:array|null }
	 */
	public static function upsert( array $data, array $opts = array() ) {
		global $wpdb;
		$t   = self::table();
		$now = current_time( 'mysql' );

		$email = strtolower( sanitize_email( $data['email'] ?? '' ) );
		if ( ! self::is_real_email( $email ) ) {
			$email = '';
		}
		$phone_raw = sanitize_text_field( $data['phone'] ?? '' );
		$e164      = self::normalize_phone( $phone_raw );
		$name      = sanitize_text_field( $data['name'] ?? '' );

		if ( '' === $email && '' === $e164 && empty( $data['fluentcrm_id'] ) ) {
			return array( 'id' => 0, 'status' => 'invalid', 'contact' => null );
		}

		$existing = self::find( $email, $e164, (int) ( $data['fluentcrm_id'] ?? 0 ) );

		$row = array();
		if ( '' !== $name ) {
			$row['name'] = $name;
		}
		if ( '' !== $email ) {
			$row['email'] = $email;
		}
		if ( '' !== $e164 ) {
			$row['phone']      = $e164;
			$row['phone_e164'] = $e164;
		}
		foreach ( array( 'owner', 'source_channel' ) as $k ) {
			if ( isset( $data[ $k ] ) && '' !== (string) $data[ $k ] ) {
				$row[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['score'] ) ) {
			$score        = max( 0, min( 100, (int) $data['score'] ) );
			$row['score'] = $existing ? max( (int) $existing['score'], $score ) : $score;
		}
		if ( isset( $data['deal_value'] ) && (float) $data['deal_value'] > 0 ) {
			$row['deal_value'] = (float) $data['deal_value'];
		}
		if ( ! empty( $data['fluentcrm_id'] ) ) {
			$row['fluentcrm_id'] = (int) $data['fluentcrm_id'];
		}
		$owner_uid = (int) ( $data['owner_user_id'] ?? 0 );
		if ( ! empty( $data['next_follow_up_at'] ) ) {
			$row['next_follow_up_at'] = sanitize_text_field( $data['next_follow_up_at'] );
		}
		if ( isset( $data['tags'] ) ) {
			$new_tags    = self::parse_tags( $data['tags'] );
			$old_tags    = $existing ? self::parse_tags( $existing['tags'] ?? '' ) : array();
			$row['tags'] = implode( ',', array_values( array_unique( array_merge( $old_tags, $new_tags ) ) ) );
		}
		if ( ! empty( $data['consent'] ) ) {
			$row['consent_source'] = sanitize_text_field( $data['consent_source'] ?? ( $data['source_channel'] ?? 'web' ) );
			$row['consent_at']     = $now;
		}

		// First-touch attribution.
		foreach ( array( 'utm_source' => 100, 'utm_medium' => 100, 'utm_campaign' => 150, 'referrer' => 255, 'landing_page' => 255 ) as $k => $len ) {
			$v = isset( $data[ $k ] ) ? substr( sanitize_text_field( $data[ $k ] ), 0, $len ) : '';
			if ( '' !== $v && ( ! $existing || '' === (string) ( $existing[ $k ] ?? '' ) ) ) {
				$row[ $k ] = $v;
			}
		}

		$meta_in = isset( $data['meta'] ) && is_array( $data['meta'] ) ? array_filter( $data['meta'], static function ( $v ) { return '' !== $v && null !== $v; } ) : array();

		if ( $existing ) {
			$id = (int) $existing['id'];
			if ( $meta_in ) {
				$row['meta_data'] = wp_json_encode( array_merge( json_decode( (string) $existing['meta_data'], true ) ?: array(), $meta_in ) );
			}
			if ( ! empty( $data['stage'] ) && isset( self::stages()[ $data['stage'] ] ) && $data['stage'] !== $existing['stage'] ) {
				self::set_stage( $id, $data['stage'], '', false );
			}
			$row['updated_at'] = $now;
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			$status = 'updated';
		} else {
			$stage = ( ! empty( $data['stage'] ) && isset( self::stages()[ $data['stage'] ] ) ) ? $data['stage'] : 'inquiry';
			$row  += array(
				'name'           => '' !== $name ? $name : __( 'Traveler', 'tripcosmos-agents' ),
				'source_channel' => 'web',
				'stage'          => $stage,
				'score'          => 50,
				'currency'       => 'INR',
				'meta_data'      => wp_json_encode( $meta_in ?: new stdClass() ),
				'created_at'     => $now,
				'updated_at'     => $now,
			);
			$wpdb->insert( $t, $row );
			$id     = (int) $wpdb->insert_id;
			$status = 'created';
			self::add_activity( $id, 'system', sprintf( __( 'Contact created from %s', 'tripcosmos-agents' ), $row['source_channel'] ) );
			if ( $owner_uid ) {
				self::set_owner( $id, $owner_uid );
			} elseif ( ! empty( $opts['auto_assign'] ) || ! isset( $opts['auto_assign'] ) ) {
				self::auto_assign( $id );
			}
		}

		if ( ! empty( $opts['activity'] ) && $id ) {
			self::add_activity( $id, $opts['activity_type'] ?? 'system', $opts['activity'] );
		}

		if ( $id && ( $opts['sync'] ?? true ) ) {
			self::sync_to_fluentcrm( $id );
		}

		return array( 'id' => $id, 'status' => $status, 'contact' => self::get( $id ) );
	}

	/**
	 * Update editable fields of an existing contact (admin edit form). Unlike upsert(), empty values do clear fields.
	 */
	public static function update( $id, array $fields, $sync = true ) {
		global $wpdb;
		$id      = (int) $id;
		$contact = self::get( $id );
		if ( ! $contact ) {
			return false;
		}
		$row = array();
		foreach ( array( 'name', 'owner', 'lost_reason', 'source_channel' ) as $k ) {
			if ( array_key_exists( $k, $fields ) ) {
				$row[ $k ] = sanitize_text_field( $fields[ $k ] );
			}
		}
		if ( array_key_exists( 'email', $fields ) ) {
			$email        = strtolower( sanitize_email( $fields['email'] ) );
			$row['email'] = self::is_real_email( $email ) ? $email : '';
		}
		if ( array_key_exists( 'phone', $fields ) ) {
			$e164              = self::normalize_phone( $fields['phone'] );
			$row['phone']      = '' !== $e164 ? $e164 : sanitize_text_field( $fields['phone'] );
			$row['phone_e164'] = '' !== $e164 ? $e164 : null;
		}
		if ( array_key_exists( 'deal_value', $fields ) ) {
			$row['deal_value'] = max( 0, (float) $fields['deal_value'] );
		}
		if ( array_key_exists( 'score', $fields ) ) {
			$row['score'] = max( 0, min( 100, (int) $fields['score'] ) );
		}
		if ( array_key_exists( 'tags', $fields ) ) {
			$row['tags'] = implode( ',', self::parse_tags( $fields['tags'] ) );
		}
		if ( array_key_exists( 'next_follow_up_at', $fields ) ) {
			$v                        = trim( (string) $fields['next_follow_up_at'] );
			$row['next_follow_up_at'] = '' !== $v ? gmdate( 'Y-m-d H:i:s', strtotime( $v ) ) : null;
		}
		foreach ( array( 'whatsapp_opt_out', 'dnd' ) as $k ) {
			if ( array_key_exists( $k, $fields ) ) {
				$row[ $k ] = empty( $fields[ $k ] ) ? 0 : 1;
				if ( (int) $contact[ $k ] !== $row[ $k ] ) {
					self::add_activity( $id, 'consent', sprintf( '%s %s', 'dnd' === $k ? 'Do-not-contact' : 'WhatsApp opt-out', $row[ $k ] ? 'ON' : 'OFF' ) );
					if ( $row[ $k ] && class_exists( 'TC_Agent_Sequences' ) ) {
						TC_Agent_Sequences::cancel_for_contact( $id );
					}
				}
			}
		}
		$row['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table(), $row, array( 'id' => $id ) );

		if ( array_key_exists( 'stage', $fields ) && $fields['stage'] !== $contact['stage'] ) {
			self::set_stage( $id, $fields['stage'], $fields['lost_reason'] ?? '', false );
		}
		if ( $sync ) {
			self::sync_to_fluentcrm( $id );
		}
		return true;
	}

	/**
	 * Move a contact to a pipeline stage, recording history and running stage automations.
	 */
	public static function set_stage( $id, $stage, $lost_reason = '', $sync = true ) {
		global $wpdb;
		$id      = (int) $id;
		$stages  = self::stages();
		$contact = self::get( $id );
		if ( ! $contact || ! isset( $stages[ $stage ] ) ) {
			return false;
		}
		$from = (string) $contact['stage'];
		$row  = array( 'stage' => $stage, 'updated_at' => current_time( 'mysql' ) );
		if ( 'lost' === $stage && '' !== trim( (string) $lost_reason ) ) {
			$row['lost_reason'] = sanitize_text_field( $lost_reason );
		}
		$wpdb->update( self::table(), $row, array( 'id' => $id ) );

		if ( $from !== $stage ) {
			self::add_activity(
				$id,
				'stage_change',
				sprintf( '%s → %s', $stages[ $from ] ?? $from, $stages[ $stage ] ) . ( 'lost' === $stage && $lost_reason ? ' (' . sanitize_text_field( $lost_reason ) . ')' : '' ),
				array( 'from' => $from, 'to' => $stage )
			);

			if ( class_exists( 'TC_Agent_Sequences' ) ) {
				if ( in_array( $stage, array( 'won', 'lost' ), true ) ) {
					TC_Agent_Sequences::cancel_for_contact( $id ); // stop sales nurture
				}
				if ( 'won' === $stage ) {
					TC_Agent_Sequences::enroll_contact( $id, 'pre_arrival' );
				} elseif ( 'completed' === $stage ) {
					TC_Agent_Sequences::enroll_contact( $id, 'post_trip_feedback' );
				}
			}
		}
		if ( $sync ) {
			self::sync_to_fluentcrm( $id );
		}
		return true;
	}

	/**
	 * Add a timeline entry. Types: note, task, stage_change, call, whatsapp, email, form, booking, consent, sync, system.
	 */
	public static function add_activity( $contact_id, $type, $content, array $meta = array(), $due_at = null ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'tc_agent_activities',
			array(
				'contact_id' => (int) $contact_id,
				'type'       => sanitize_key( $type ),
				'content'    => wp_kses_post( (string) $content ),
				'meta'       => $meta ? wp_json_encode( $meta ) : null,
				'due_at'     => $due_at,
				'user_id'    => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'created_at' => current_time( 'mysql' ),
			)
		);
		if ( in_array( $type, array( 'call', 'whatsapp', 'email' ), true ) ) {
			$wpdb->update( self::table(), array( 'last_contacted_at' => current_time( 'mysql' ) ), array( 'id' => (int) $contact_id ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Add a follow-up task; also moves the contact's next_follow_up_at if this one is sooner.
	 */
	public static function add_task( $contact_id, $content, $due_at ) {
		global $wpdb;
		$due = gmdate( 'Y-m-d H:i:s', strtotime( (string) $due_at ) ?: time() + DAY_IN_SECONDS );
		$aid = self::add_activity( $contact_id, 'task', $content, array(), $due );
		self::refresh_next_follow_up( $contact_id );
		return $aid;
	}

	public static function complete_task( $activity_id ) {
		global $wpdb;
		$t   = $wpdb->prefix . 'tc_agent_activities';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT contact_id FROM $t WHERE id = %d AND type = 'task'", (int) $activity_id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$wpdb->update( $t, array( 'done_at' => current_time( 'mysql' ) ), array( 'id' => (int) $activity_id ) );
		self::refresh_next_follow_up( (int) $row['contact_id'] );
		return true;
	}

	/**
	 * next_follow_up_at = earliest open task.
	 */
	public static function refresh_next_follow_up( $contact_id ) {
		global $wpdb;
		$t    = $wpdb->prefix . 'tc_agent_activities';
		$next = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(due_at) FROM $t WHERE contact_id = %d AND type = 'task' AND done_at IS NULL", (int) $contact_id ) );
		$wpdb->update( self::table(), array( 'next_follow_up_at' => $next ?: null ), array( 'id' => (int) $contact_id ) );
	}

	/**
	 * Timeline for a contact, newest first.
	 */
	public static function activities( $contact_id, $limit = 100 ) {
		global $wpdb;
		$t = $wpdb->prefix . 'tc_agent_activities';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE contact_id = %d ORDER BY id DESC LIMIT %d", (int) $contact_id, (int) $limit ), ARRAY_A ) ?: array();
	}

	/**
	 * Whether automated messages may be sent to this contact on a channel.
	 *
	 * @param array  $contact Contact row.
	 * @param string $channel 'whatsapp' | 'email' | 'voice'.
	 */
	public static function can_contact( array $contact, $channel ) {
		if ( ! empty( $contact['dnd'] ) ) {
			return false;
		}
		if ( 'whatsapp' === $channel ) {
			return empty( $contact['whatsapp_opt_out'] ) && '' !== (string) ( $contact['phone'] ?? '' );
		}
		if ( 'email' === $channel ) {
			return self::is_real_email( $contact['email'] ?? '' ) && ! in_array( (string) ( $contact['email_status'] ?? '' ), self::EMAIL_BLOCKED_STATUSES, true );
		}
		return true;
	}

	/**
	 * Handle STOP / START keywords on an inbound WhatsApp message.
	 *
	 * @return string|null 'opted_out' | 'opted_in' | null when the message isn't a keyword.
	 */
	public static function handle_whatsapp_keyword( $phone, $text ) {
		$word = strtoupper( trim( preg_replace( '/[^\p{L}\s]/u', '', (string) $text ) ) );
		$out  = in_array( $word, self::OPT_OUT_KEYWORDS, true );
		$in   = in_array( $word, self::OPT_IN_KEYWORDS, true );
		if ( ! $out && ! $in ) {
			return null;
		}
		$contact = self::find( '', $phone );
		if ( ! $contact ) {
			$res     = self::upsert( array( 'phone' => $phone, 'source_channel' => 'whatsapp' ), array( 'sync' => false ) );
			$contact = $res['contact'];
		}
		if ( ! $contact ) {
			return null;
		}
		self::update( (int) $contact['id'], array( 'whatsapp_opt_out' => $out ? 1 : 0 ), false );
		return $out ? 'opted_out' : 'opted_in';
	}

	/**
	 * Merge $remove_id into $keep_id: fills blanks, moves conversations, timeline, bookings, sequences, then deletes $remove_id.
	 */
	public static function merge( $keep_id, $remove_id ) {
		global $wpdb;
		$keep   = self::get( $keep_id );
		$remove = self::get( $remove_id );
		if ( ! $keep || ! $remove || (int) $keep_id === (int) $remove_id ) {
			return false;
		}
		$fill = array();
		foreach ( array( 'email', 'phone', 'phone_e164', 'owner', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'landing_page', 'consent_source', 'consent_at', 'twentycrm_id' ) as $k ) {
			if ( empty( $keep[ $k ] ) && ! empty( $remove[ $k ] ) ) {
				$fill[ $k ] = $remove[ $k ];
			}
		}
		if ( empty( $keep['owner_user_id'] ) && ! empty( $remove['owner_user_id'] ) ) {
			$fill['owner_user_id'] = (int) $remove['owner_user_id'];
		}
		if ( empty( $keep['fluentcrm_id'] ) && ! empty( $remove['fluentcrm_id'] ) ) {
			$fill['fluentcrm_id'] = (int) $remove['fluentcrm_id'];
		}
		if ( in_array( $keep['name'], array( '', 'Traveler', 'WhatsApp Traveler', 'Pilgrim Traveler' ), true ) && ! empty( $remove['name'] ) ) {
			$fill['name'] = $remove['name'];
		}
		$fill['score']            = max( (int) $keep['score'], (int) $remove['score'] );
		$fill['deal_value']       = max( (float) $keep['deal_value'], (float) $remove['deal_value'] );
		$fill['whatsapp_opt_out'] = max( (int) $keep['whatsapp_opt_out'], (int) $remove['whatsapp_opt_out'] ); // keep the stricter consent
		$fill['dnd']              = max( (int) $keep['dnd'], (int) $remove['dnd'] );
		$fill['tags']             = implode( ',', array_unique( array_merge( self::parse_tags( $keep['tags'] ), self::parse_tags( $remove['tags'] ) ) ) );
		$fill['meta_data']        = wp_json_encode( array_merge( json_decode( (string) $remove['meta_data'], true ) ?: array(), json_decode( (string) $keep['meta_data'], true ) ?: array() ) );
		$fill['updated_at']       = current_time( 'mysql' );
		$wpdb->update( self::table(), $fill, array( 'id' => (int) $keep_id ) );

		$p = $wpdb->prefix;
		foreach ( array( 'tc_agent_conversations', 'tc_agent_activities', 'tc_agent_bookings', 'tc_agent_sequence_enrollments' ) as $tbl ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . $tbl ) ) ) {
				$wpdb->update( $p . $tbl, array( 'contact_id' => (int) $keep_id ), array( 'contact_id' => (int) $remove_id ) );
			}
		}
		$mem = $p . 'tc_agent_lead_memory';
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $mem WHERE contact_id = %d", (int) $keep_id ) ) ) {
			$wpdb->update( $mem, array( 'contact_id' => (int) $keep_id ), array( 'contact_id' => (int) $remove_id ) );
		} else {
			$wpdb->delete( $mem, array( 'contact_id' => (int) $remove_id ) );
		}
		$wpdb->delete( self::table(), array( 'id' => (int) $remove_id ) );
		self::add_activity( $keep_id, 'system', sprintf( __( 'Merged duplicate contact #%1$d (%2$s)', 'tripcosmos-agents' ), (int) $remove_id, $remove['name'] ) );
		self::sync_to_fluentcrm( $keep_id );
		return true;
	}

	/**
	 * Groups of likely duplicates (same normalised phone or same email).
	 *
	 * @return array[] each: array of contact rows
	 */
	public static function find_duplicates( $limit = 100 ) {
		global $wpdb;
		$t      = self::table();
		$groups = array();
		$keys   = array(
			'phone_e164' => $wpdb->get_col( $wpdb->prepare( "SELECT phone_e164 FROM $t WHERE phone_e164 IS NOT NULL AND phone_e164 <> '' GROUP BY phone_e164 HAVING COUNT(*) > 1 LIMIT %d", $limit ) ),
			'email'      => $wpdb->get_col( $wpdb->prepare( "SELECT email FROM $t WHERE email IS NOT NULL AND email <> '' GROUP BY email HAVING COUNT(*) > 1 LIMIT %d", $limit ) ),
		);
		$seen = array();
		foreach ( $keys as $col => $vals ) {
			foreach ( $vals as $v ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $col = %s ORDER BY id ASC", $v ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $col is a fixed key.
				$sig  = implode( ',', wp_list_pluck( $rows, 'id' ) );
				if ( count( $rows ) > 1 && ! isset( $seen[ $sig ] ) ) {
					$seen[ $sig ] = true;
					$groups[]     = $rows;
				}
			}
		}
		return $groups;
	}

	/**
	 * Delete a contact and all local data. With $crm = true also removes the FluentCRM subscriber.
	 *
	 * @return string[] notes about copies that could not be removed automatically.
	 */
	public static function delete( $id, $crm = false ) {
		global $wpdb;
		$contact = self::get( $id );
		if ( ! $contact ) {
			return array();
		}
		$notes = array();
		if ( $crm && ! empty( $contact['fluentcrm_id'] ) && class_exists( '\FluentCrm\App\Services\Helper' ) ) {
			self::$syncing = true;
			try {
				\FluentCrm\App\Services\Helper::deleteContacts( array( (int) $contact['fluentcrm_id'] ) );
			} catch ( \Throwable $e ) {
				$notes[] = 'FluentCRM subscriber #' . (int) $contact['fluentcrm_id'] . ' could not be deleted: ' . $e->getMessage();
			}
			self::$syncing = false;
		}
		if ( $crm && ! empty( $contact['twentycrm_id'] ) ) {
			$notes[] = 'TwentyCRM person ' . $contact['twentycrm_id'] . ' must be deleted in TwentyCRM.';
		}
		if ( $crm && '1' === (string) get_option( 'tc_agents_sheets_enabled', '0' ) ) {
			$notes[] = 'Rows for this contact in the Google Sheet must be removed manually.';
		}
		$p = $wpdb->prefix;
		$wpdb->delete( $p . 'tc_agent_activities', array( 'contact_id' => (int) $id ) );
		$wpdb->delete( $p . 'tc_agent_bookings', array( 'contact_id' => (int) $id ) );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . 'tc_agent_sequence_enrollments' ) ) ) {
			$wpdb->delete( $p . 'tc_agent_sequence_enrollments', array( 'contact_id' => (int) $id ) );
		}
		if ( class_exists( 'TC_Agents_Privacy' ) ) {
			TC_Agents_Privacy::delete_contact( (int) $id );
		}
		return $notes;
	}

	/**
	 * Push a contact to FluentCRM and record the result on the row.
	 *
	 * @return bool
	 */
	public static function sync_to_fluentcrm( $id ) {
		global $wpdb;
		if ( ! class_exists( 'TC_Integration_FluentCRM' ) || ! TC_Integration_FluentCRM::is_active() ) {
			return false;
		}
		$c = self::get( $id );
		if ( ! $c ) {
			return false;
		}
		$now = current_time( 'mysql' );
		if ( ! self::is_real_email( $c['email'] ) ) {
			$wpdb->update( self::table(), array( 'sync_status' => 'skipped_no_email', 'sync_error' => '', 'last_synced_at' => $now ), array( 'id' => (int) $id ) );
			return false;
		}
		$meta = json_decode( (string) $c['meta_data'], true ) ?: array();

		self::$syncing = true;
		try {
			$fid = TC_Integration_FluentCRM::sync_lead(
				array(
					'name'         => $c['name'],
					'email'        => $c['email'],
					'phone'        => $c['phone'],
					'channel'      => $c['source_channel'],
					'stage'        => $c['stage'],
					'score'        => (int) $c['score'],
					'destination'  => $meta['destination'] ?? '',
					'requirements' => $meta['requirements'] ?? '',
					'owner'        => $c['owner'],
					'tags'         => self::parse_tags( $c['tags'] ),
					'consent'      => ! empty( $c['consent_at'] ),
				)
			);
			$err = $fid ? '' : 'FluentCRM rejected the contact (see audit log).';
		} catch ( \Throwable $e ) {
			$fid = false;
			$err = substr( $e->getMessage(), 0, 250 );
		}
		self::$syncing = false;

		$row = array(
			'sync_status'    => $fid ? 'synced' : 'error',
			'sync_error'     => $err,
			'last_synced_at' => $now,
		);
		if ( $fid ) {
			$row['fluentcrm_id'] = (int) $fid;
			$sub                 = TC_Integration_FluentCRM::get_subscriber( (int) $fid );
			if ( $sub && ! empty( $sub->status ) ) {
				$row['email_status'] = (string) $sub->status;
			}
		}
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		return (bool) $fid;
	}

	/**
	 * Apply a change that happened in FluentCRM to the linked local contact(s). Never creates contacts unless
	 * the tc_agents_fluentcrm_import_new option is on.
	 *
	 * @param object $subscriber FluentCRM Subscriber model.
	 * @param bool   $is_new     True when fired by contact_created.
	 */
	public static function pull_from_fluentcrm( $subscriber, $is_new = false ) {
		global $wpdb;
		if ( self::$syncing || ! is_object( $subscriber ) || empty( $subscriber->id ) ) {
			return;
		}
		$t     = self::table();
		$email = strtolower( (string) ( $subscriber->email ?? '' ) );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE fluentcrm_id = %d OR ( email = %s AND email <> '' )", (int) $subscriber->id, $email ) );

		if ( ! $ids ) {
			if ( $is_new && '1' === (string) get_option( 'tc_agents_fluentcrm_import_new', '0' ) ) {
				self::import_subscriber( $subscriber );
			}
			return;
		}

		$tags = array();
		if ( isset( $subscriber->tags ) && is_iterable( $subscriber->tags ) ) {
			foreach ( $subscriber->tags as $tag ) {
				$tags[] = (string) ( $tag->slug ?? $tag->title ?? '' );
			}
		}
		$name = trim( ( $subscriber->first_name ?? '' ) . ' ' . ( $subscriber->last_name ?? '' ) );
		foreach ( $ids as $id ) {
			$row = array(
				'fluentcrm_id'   => (int) $subscriber->id,
				'email_status'   => (string) ( $subscriber->status ?? '' ),
				'tags'           => implode( ',', array_filter( $tags ) ),
				'sync_status'    => 'synced',
				'last_synced_at' => current_time( 'mysql' ),
			);
			if ( self::is_real_email( $email ) ) {
				$row['email'] = $email;
			}
			if ( '' !== $name ) {
				$row['name'] = $name;
			}
			$e164 = self::normalize_phone( (string) ( $subscriber->phone ?? '' ) );
			if ( '' !== $e164 ) {
				$row['phone']      = $e164;
				$row['phone_e164'] = $e164;
			}
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
		}
	}

	/**
	 * Create a local contact from a FluentCRM subscriber (or return the existing link).
	 */
	public static function import_subscriber( $subscriber ) {
		if ( ! is_object( $subscriber ) || empty( $subscriber->id ) ) {
			return 0;
		}
		$res = self::upsert(
			array(
				'fluentcrm_id'   => (int) $subscriber->id,
				'name'           => trim( ( $subscriber->first_name ?? '' ) . ' ' . ( $subscriber->last_name ?? '' ) ),
				'email'          => (string) ( $subscriber->email ?? '' ),
				'phone'          => (string) ( $subscriber->phone ?? '' ),
				'source_channel' => 'fluentcrm',
			),
			array( 'sync' => false, 'auto_assign' => false )
		);
		if ( $res['id'] ) {
			self::pull_from_fluentcrm( $subscriber );
		}
		return (int) $res['id'];
	}

	/**
	 * @param string|array $tags
	 * @return string[]
	 */
	public static function parse_tags( $tags ) {
		if ( is_string( $tags ) ) {
			$tags = explode( ',', $tags );
		}
		$out = array();
		foreach ( (array) $tags as $tag ) {
			$tag = sanitize_title( trim( (string) $tag ) );
			if ( '' !== $tag ) {
				$out[] = $tag;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Data-quality problems worth showing on the contact page.
	 *
	 * @return array[] each { level: 'error'|'warning'|'info', text: string, dup_id?: int }
	 */
	public static function quality_issues( array $c ) {
		global $wpdb;
		$issues = array();
		$e164   = (string) ( $c['phone_e164'] ?? '' );
		$cc     = preg_replace( '/\D/', '', (string) get_option( 'tc_agents_default_country_code', '91' ) ) ?: '91';
		if ( '' !== (string) $c['phone'] && '' === $e164 ) {
			$issues[] = array( 'level' => 'error', 'text' => __( 'Phone number is not valid, so WhatsApp and calls won\'t reach this person.', 'tripcosmos-agents' ) );
		} elseif ( '' !== $e164 && 0 === strpos( $e164, '+' . $cc ) ) {
			if ( '91' === $cc && ! preg_match( '/^\+91[6-9]\d{9}$/', $e164 ) ) {
				$issues[] = array( 'level' => 'warning', 'text' => sprintf( __( '%s is not a valid Indian mobile (+91 then 10 digits starting 6–9).', 'tripcosmos-agents' ), $e164 ) );
			}
		} elseif ( '' !== $e164 ) {
			$issues[] = array( 'level' => 'warning', 'text' => sprintf( __( '%s is not a +%s number. Check the country code (a missing or extra digit is common).', 'tripcosmos-agents' ), $e164, $cc ) );
		}
		if ( '' === (string) $c['phone'] ) {
			$issues[] = array( 'level' => 'info', 'text' => __( 'No phone number: WhatsApp follow-ups and calls are not possible.', 'tripcosmos-agents' ) );
		}
		if ( ! self::is_real_email( $c['email'] ?? '' ) ) {
			$issues[] = array( 'level' => 'info', 'text' => __( 'No email address: this contact stays in TripCosmos only and is not synced to FluentCRM.', 'tripcosmos-agents' ) );
		}
		$t   = self::table();
		$dup = 0;
		if ( '' !== $e164 ) {
			$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE phone_e164 = %s AND id <> %d LIMIT 1", $e164, (int) $c['id'] ) );
		}
		if ( ! $dup && self::is_real_email( $c['email'] ?? '' ) ) {
			$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE email = %s AND id <> %d LIMIT 1", $c['email'], (int) $c['id'] ) );
		}
		if ( $dup ) {
			$issues[] = array( 'level' => 'warning', 'text' => sprintf( __( 'Possible duplicate of contact #%d (same phone or email).', 'tripcosmos-agents' ), $dup ), 'dup_id' => $dup );
		}
		return $issues;
	}

	/**
	 * Assign a contact to a staff member (0 = unassigned). Keeps the legacy text owner + mobile-app meta in sync.
	 */
	public static function set_owner( $id, $user_id, $notify = true, $custom_owner_name = '' ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return false;
		}
		$user_id = (int) $user_id;
		$u       = $user_id ? get_userdata( $user_id ) : null;
		if ( $user_id && ! $u ) {
			return false;
		}

		$name = '' !== trim( (string) $custom_owner_name ) ? sanitize_text_field( $custom_owner_name ) : ( $u ? $u->display_name : '' );
		if ( (int) $c['owner_user_id'] === $user_id && (string) $c['owner'] === $name ) {
			return true;
		}

		$meta = json_decode( (string) $c['meta_data'], true ) ?: array();
		$meta['assigned_manager'] = $name;
		$meta['assigned_at']      = current_time( 'mysql' );
		$wpdb->update(
			self::table(),
			array( 'owner_user_id' => $user_id, 'owner' => $name, 'meta_data' => wp_json_encode( $meta ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id )
		);
		$by   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$text = $name ? sprintf( __( 'Assigned to %s', 'tripcosmos-agents' ), $name ) : __( 'Unassigned', 'tripcosmos-agents' );
		if ( $c['owner'] && $c['owner'] !== $name ) {
			$text .= sprintf( __( ' (was %s)', 'tripcosmos-agents' ), $c['owner'] );
		}
		self::add_activity( $id, 'system', $text );
		if ( $notify && $user_id && class_exists( 'TC_CRM_Notify' ) ) {
			TC_CRM_Notify::on_assigned( $id, $user_id, $by );
		}
		return true;
	}

	/**
	 * Auto-assign a new contact.
	 * Default: Assign to Nishkarsh AI (Team Nishkarsh, User 73657).
	 * Unassigned leads alert the managers instead.
	 *
	 * @return int Assigned user id (0 when none).
	 */
	public static function auto_assign( $id, $notify = true ) {
		global $wpdb;
		$mode = (string) get_option( 'tc_agents_crm_assign_mode', 'nishkarsh_ai' );
		if ( 'none' === $mode ) {
			return 0;
		}

		// Dedicated default assignment rule: Always auto-assign new leads to Nishkarsh AI.
		if ( 'nishkarsh_ai' === $mode || empty( $mode ) || 'round_robin' === $mode ) {
			$nishkarsh_user = get_user_by( 'email', 'hellonishkarsh@gmail.com' ) ?: get_userdata( 73657 );
			$pick = $nishkarsh_user ? (int) $nishkarsh_user->ID : 73657;
			self::set_owner( $id, $pick, $notify, 'Nishkarsh AI' );
			return $pick;
		}

		$pool = array();
		if ( class_exists( 'TC_CRM_Access' ) ) {
			foreach ( TC_CRM_Access::team() as $u ) {
				if ( TC_CRM_Access::in_rotation( $u ) && ! TC_CRM_Access::is_away( $u->ID ) ) {
					$pool[] = (int) $u->ID;
				}
			}
			// If rotation pool is empty, fallback to active managers
			if ( empty( $pool ) ) {
				foreach ( TC_CRM_Access::team( TC_CRM_Access::CAP_MANAGE ) as $m ) {
					if ( ! TC_CRM_Access::is_away( $m->ID ) ) {
						$pool[] = (int) $m->ID;
					}
				}
			}
		}
		$pick = 0;
		if ( $pool ) {
			sort( $pool );
			if ( 'least_loaded' === $mode ) {
				$in    = implode( ',', $pool );
				$t     = self::table();
				$loads = $wpdb->get_results( "SELECT owner_user_id u, COUNT(*) n FROM $t WHERE owner_user_id IN ($in) AND stage NOT IN ('won','completed','lost') GROUP BY owner_user_id", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ints only.
				usort( $pool, static function ( $a, $b ) use ( $loads ) { return ( (int) ( $loads[ $a ]->n ?? 0 ) <=> (int) ( $loads[ $b ]->n ?? 0 ) ) ?: $a <=> $b; } );
				$pick = $pool[0];
			} else {
				$last = (int) get_option( 'tc_agents_crm_rr_last', 0 );
				foreach ( $pool as $uid ) {
					if ( $uid > $last ) {
						$pick = $uid;
						break;
					}
				}
				$pick = $pick ?: $pool[0];
				update_option( 'tc_agents_crm_rr_last', $pick, false );
			}
			self::set_owner( $id, $pick, $notify );
		} elseif ( $notify && class_exists( 'TC_CRM_Notify' ) && TC_CRM_Notify::enabled( 'assigned' ) ) {
			$c = self::get( $id );
			if ( $c && ! (int) $c['owner_user_id'] ) {
				TC_CRM_Notify::send_managers( __( 'New unassigned lead', 'tripcosmos-agents' ), TC_CRM_Notify::contact_line( $c ), (int) $id );
			}
		}
		return $pick;
	}
}
