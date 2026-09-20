<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Google Business / Places importer for Indian travel agencies (B2B prospecting).
 * Source-of-truth table: wp_tc_agent_agencies. Uses Google Places Text Search (New) if key present;
 * otherwise accepts manual CSV import via admin/REST and queues enrichment.
 */
class TC_Integration_Google_Business {
	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'google_places_api_key', '' ) : get_option( 'tc_agents_google_places_api_key', '' );
	}
	public static function is_configured() { return ! empty( self::get_api_key() ); }
	/**
	 * Search travel agencies in a city via Places API (New) Text Search.
	 * @return array[] Each: name, address, phone, rating, place_id, lat, lng.
	 */
	public static function search_agencies( $query, $max = 20 ) {
		if ( ! self::is_configured() ) { return new WP_Error( 'places_not_configured', __( 'Google Places API key missing.', 'tripcosmos-agents' ) ); }
		$res = wp_remote_post( 'https://places.googleapis.com/v1/places:searchText', array(
			'timeout' => 15, 'sslverify' => true,
			'headers' => array( 'Content-Type' => 'application/json', 'X-Goog-Api-Key' => self::get_api_key(), 'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.internationalPhoneNumber,places.rating,places.id,places.location' ),
			'body' => wp_json_encode( array( 'textQuery' => $query, 'maxResultCount' => min( 20, max( 1, (int) $max ) ) ) ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		if ( wp_remote_retrieve_response_code( $res ) >= 400 ) { return new WP_Error( 'places_api_error', wp_remote_retrieve_body( $res ) ); }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out = array();
		foreach ( (array) ( $body['places'] ?? array() ) as $p ) {
			$out[] = array(
				'name' => $p['displayName']['text'] ?? '',
				'address' => $p['formattedAddress'] ?? '',
				'phone' => $p['internationalPhoneNumber'] ?? '',
				'rating' => $p['rating'] ?? null,
				'place_id' => $p['id'] ?? '',
				'lat' => $p['location']['latitude'] ?? null,
				'lng' => $p['location']['longitude'] ?? null,
			);
		}
		return $out;
	}
	/**
	 * Upsert agency rows (dedupe by place_id, else phone, else name+city).
	 * @return array ['inserted'=>int,'updated'=>int]
	 */
	public static function upsert_agencies( $agencies, $city = '', $source = 'google' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_agencies';
		$ins = 0; $upd = 0;
		foreach ( (array) $agencies as $a ) {
			$name = sanitize_text_field( $a['name'] ?? '' );
			if ( '' === $name ) { continue; }
			$place_id = sanitize_text_field( $a['place_id'] ?? '' );
			$phone = sanitize_text_field( $a['phone'] ?? '' );
			$data = array(
				'name' => $name, 'city' => sanitize_text_field( $city ?: ( $a['city'] ?? '' ) ),
				'address' => sanitize_text_field( $a['address'] ?? '' ), 'phone' => $phone,
				'email' => sanitize_email( $a['email'] ?? '' ), 'website' => esc_url_raw( $a['website'] ?? '' ),
				'rating' => isset( $a['rating'] ) ? (float) $a['rating'] : null,
				'place_id' => $place_id,
				'lat' => isset( $a['lat'] ) ? (float) $a['lat'] : null, 'lng' => isset( $a['lng'] ) ? (float) $a['lng'] : null,
				'source' => sanitize_key( $source ), 'status' => 'new', 'updated_at' => current_time( 'mysql' ),
			);
			$existing = 0;
			if ( $place_id ) { $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE place_id = %s LIMIT 1", $place_id ) ); }
			if ( ! $existing && $phone ) { $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE phone = %s LIMIT 1", $phone ) ); }
			if ( ! $existing ) { $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s AND city = %s LIMIT 1", $name, $data['city'] ) ); }
			if ( $existing ) { $wpdb->update( $table, $data, array( 'id' => (int) $existing ) ); $upd++; }
			else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $table, $data ); $ins++; }
		}
		return array( 'inserted' => $ins, 'updated' => $upd );
	}
	/**
	 * Daily cron import: queries default Indian agency queries.
	 */
	public static function cron_import() {
		$cities = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'tc_agents_b2b_import_cities', 'Varanasi, Delhi, Mumbai, Jaipur, Kolkata, Chennai, Bengaluru, Hyderabad, Ahmedabad, Lucknow' ) ) ) );
		if ( empty( $cities ) ) { $cities = array( 'Varanasi' ); }
		$total = array( 'inserted' => 0, 'updated' => 0, 'cities' => 0 );
		foreach ( array_slice( $cities, 0, 10 ) as $city ) {
			$res = self::search_agencies( 'travel agency in ' . $city, 20 );
			if ( is_wp_error( $res ) || empty( $res ) ) { continue; }
			$r = self::upsert_agencies( $res, $city, 'google' );
			$total['inserted'] += $r['inserted']; $total['updated'] += $r['updated']; $total['cities']++;
		}
		if ( class_exists( 'TC_Agents_Logger' ) ) { TC_Agents_Logger::log( 'b2b_google_import', 'info', $total ); }
		update_option( 'tc_agents_b2b_last_import', current_time( 'mysql' ), false );
		return $total;
	}
}
