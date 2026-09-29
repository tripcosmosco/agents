<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Business / Places importer for Indian travel agencies (B2B prospecting).
 * Source-of-truth table: wp_tc_agent_agencies.
 * Features:
 * 1. Google Places Text Search (New) with websiteUri and phone extraction.
 * 2. Strict deduplication (place_id, normalized phone, name + city).
 * 3. Safe daily scheduled rotation across Indian target travel hubs.
 * 4. Automatic sync to FluentCRM B2B lists & tags (triggering Dev Deepawali & Spiritual Circuit funnels).
 */
class TC_Integration_Google_Business {

	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'google_places_api_key', '' ) : get_option( 'tc_agents_google_places_api_key', '' );
	}

	public static function is_configured() {
		return ! empty( self::get_api_key() );
	}

	/**
	 * Search travel agencies in a city via Google Places API (New) Text Search.
	 *
	 * @param string $query Query string e.g. "travel agency in Mumbai"
	 * @param int    $max   Max results (1 - 20)
	 * @return array[]|WP_Error
	 */
	public static function search_agencies( $query, $max = 20 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'places_not_configured', __( 'Google Places API key missing.', 'tripcosmos-agents' ) );
		}

		$field_mask = 'places.displayName,places.formattedAddress,places.internationalPhoneNumber,places.rating,places.id,places.location,places.websiteUri';

		$res = wp_remote_post(
			'https://places.googleapis.com/v1/places:searchText',
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => self::get_api_key(),
					'X-Goog-FieldMask' => $field_mask,
				),
				'body'      => wp_json_encode(
					array(
						'textQuery'      => $query,
						'maxResultCount' => min( 20, max( 1, (int) $max ) ),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		if ( wp_remote_retrieve_response_code( $res ) >= 400 ) {
			return new WP_Error( 'places_api_error', wp_remote_retrieve_body( $res ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();

		foreach ( (array) ( $body['places'] ?? array() ) as $p ) {
			$out[] = array(
				'name'      => $p['displayName']['text'] ?? '',
				'address'   => $p['formattedAddress'] ?? '',
				'phone'     => $p['internationalPhoneNumber'] ?? '',
				'website'   => $p['websiteUri'] ?? '',
				'rating'    => isset( $p['rating'] ) ? (float) $p['rating'] : null,
				'place_id'  => $p['id'] ?? '',
				'lat'       => $p['location']['latitude'] ?? null,
				'lng'       => $p['location']['longitude'] ?? null,
			);
		}

		return $out;
	}

	/**
	 * Normalize phone numbers to standard E.164 (+91 for Indian mobile/landline).
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		if ( 10 === strlen( $digits ) && in_array( substr( $digits, 0, 1 ), array( '6', '7', '8', '9' ), true ) ) {
			return '+91' . $digits;
		}
		if ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
			return '+91' . substr( $digits, 1 );
		}
		if ( 12 === strlen( $digits ) && '91' === substr( $digits, 0, 2 ) ) {
			return '+' . $digits;
		}
		if ( ! empty( $phone ) && 0 === strpos( trim( $phone ), '+' ) ) {
			return '+' . $digits;
		}
		return ! empty( $digits ) ? '+' . $digits : '';
	}

	/**
	 * Upsert agency rows with strict deduplication (place_id, phone, name+city).
	 * Automatically syncs qualified leads with email to FluentCRM to trigger marketing automation.
	 *
	 * @param array  $agencies Array of agency details
	 * @param string $city     City name
	 * @param string $source   'google', 'manual', 'csv'
	 * @return array ['inserted'=>int, 'updated'=>int, 'synced_crm'=>int]
	 */
	public static function upsert_agencies( $agencies, $city = '', $source = 'google' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_agencies';

		$ins = 0;
		$upd = 0;
		$crm = 0;

		$auto_crm = '1' === (string) get_option( 'tc_agents_b2b_auto_crm_sync', '1' );

		foreach ( (array) $agencies as $a ) {
			$name = sanitize_text_field( $a['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$place_id   = sanitize_text_field( $a['place_id'] ?? '' );
			$phone_norm = self::normalize_phone( $a['phone'] ?? '' );
			$target_city = sanitize_text_field( $city ?: ( $a['city'] ?? 'India' ) );
			$email_clean = sanitize_email( $a['email'] ?? '' );

			$data = array(
				'name'       => $name,
				'city'       => $target_city,
				'address'    => sanitize_text_field( $a['address'] ?? '' ),
				'phone'      => $phone_norm,
				'email'      => $email_clean,
				'website'    => esc_url_raw( $a['website'] ?? '' ),
				'rating'     => isset( $a['rating'] ) ? (float) $a['rating'] : null,
				'place_id'   => $place_id,
				'lat'        => isset( $a['lat'] ) ? (float) $a['lat'] : null,
				'lng'        => isset( $a['lng'] ) ? (float) $a['lng'] : null,
				'source'     => sanitize_key( $source ),
				'updated_at' => current_time( 'mysql' ),
			);

			// Strict 3-Tier Deduplication
			$existing = 0;
			if ( $place_id ) {
				$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE place_id = %s LIMIT 1", $place_id ) );
			}
			if ( ! $existing && ! empty( $phone_norm ) && strlen( preg_replace( '/\D/', '', $phone_norm ) ) >= 10 ) {
				$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE phone = %s LIMIT 1", $phone_norm ) );
			}
			if ( ! $existing ) {
				$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s AND city = %s LIMIT 1", $name, $target_city ) );
			}

			$agency_id = 0;
			if ( $existing ) {
				$agency_id = $existing;
				$wpdb->update( $table, $data, array( 'id' => $existing ) );
				$upd++;
			} else {
				$data['status']     = 'new';
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $data );
				$agency_id = (int) $wpdb->insert_id;
				$ins++;
			}

			// Automated Sync to FluentCRM if agency has email
			if ( $auto_crm && $agency_id > 0 && ! empty( $email_clean ) && is_email( $email_clean ) ) {
				if ( class_exists( 'TC_Integration_FluentCRM' ) && TC_Integration_FluentCRM::is_active() ) {
					$fluent_id = TC_Integration_FluentCRM::sync_lead(
						array(
							'name'        => $name,
							'email'       => $email_clean,
							'phone'       => $phone_norm,
							'destination' => $target_city,
							'channel'     => 'b2b_partner',
							'stage'       => 'b2b_partner',
							'score'       => 85,
						)
					);
					if ( $fluent_id ) {
						$wpdb->update( $table, array( 'status' => 'in_crm' ), array( 'id' => $agency_id ) );
						$crm++;
					}
				}
			}
		}

		return array(
			'inserted'   => $ins,
			'updated'    => $upd,
			'synced_crm' => $crm,
		);
	}

	/**
	 * Safe daily scheduled import: rotates 1-2 target Indian cities per run.
	 * Protects API quota, avoids spam flags, and steadily enriches B2B partner network.
	 */
	public static function cron_import() {
		$cities_str = (string) get_option(
			'tc_agents_b2b_import_cities',
			'Varanasi, Mumbai, Ahmedabad, Surat, Delhi, Bengaluru, Hyderabad, Kolkata, Chennai, Jaipur, Lucknow, Pune'
		);
		$cities = array_filter( array_map( 'trim', explode( ',', $cities_str ) ) );
		if ( empty( $cities ) ) {
			$cities = array( 'Varanasi', 'Mumbai' );
		}

		$city_idx = (int) get_option( 'tc_agents_b2b_city_index', 0 );
		if ( $city_idx >= count( $cities ) ) {
			$city_idx = 0;
		}

		// Safe Schedule: Query 1 city per daily cron run (max 20 agencies)
		$city_to_run = $cities[ $city_idx ];
		$next_idx    = ( $city_idx + 1 ) % count( $cities );
		update_option( 'tc_agents_b2b_city_index', $next_idx, false );

		$total = array(
			'city'       => $city_to_run,
			'next_city'  => $cities[ $next_idx ] ?? $cities[0],
			'inserted'   => 0,
			'updated'    => 0,
			'synced_crm' => 0,
		);

		$res = self::search_agencies( 'travel agency in ' . $city_to_run, 20 );
		if ( ! is_wp_error( $res ) && ! empty( $res ) ) {
			$r = self::upsert_agencies( $res, $city_to_run, 'google' );
			$total['inserted']   = $r['inserted'];
			$total['updated']    = $r['updated'];
			$total['synced_crm'] = $r['synced_crm'];
		}

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log( 'b2b_scheduled_import', 'info', $total );
		}

		update_option( 'tc_agents_b2b_last_import', current_time( 'mysql' ), false );
		return $total;
	}
}
