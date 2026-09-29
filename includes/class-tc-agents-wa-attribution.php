<?php
/**
 * Web-to-WhatsApp attribution.
 *
 * A click on a WhatsApp link only opens WhatsApp; the visitor's phone is unknown until they send a message.
 * The site adds a short "Ref TC-XXXXX" code to the pre-filled message and records the visit under that code.
 * When the message reaches the webhook, the code links the new contact to the page, ad source and chat session.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_WA_Attribution {

	const REF_PATTERN = '[A-HJ-NP-Z2-9]{5}';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'tc-agents/v1',
			'/wa-click',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_click' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_wa_clicks';
	}

	/**
	 * Record one WhatsApp link click. Public endpoint, so it is rate limited and first-write-wins per ref.
	 */
	public static function handle_click( WP_REST_Request $request ) {
		if ( ! TC_Agents_Security::rate_limit( 'wa_click_ip', TC_Agents_Security::client_ip(), 30, HOUR_IN_SECONDS ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}

		$p = $request->get_json_params();
		if ( ! is_array( $p ) ) {
			$p = $request->get_params();
		}

		$ref = strtoupper( sanitize_text_field( (string) ( $p['ref'] ?? '' ) ) );
		if ( ! preg_match( '/^TC-' . self::REF_PATTERN . '$/', $ref ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'Invalid ref' ), 400 );
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table() . ' (ref, session_id, page_url, referrer, utm_source, utm_medium, utm_campaign, context, created_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)',
				$ref,
				substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $p['session_id'] ?? '' ) ), 0, 64 ),
				substr( esc_url_raw( (string) ( $p['page_url'] ?? '' ) ), 0, 500 ),
				substr( esc_url_raw( (string) ( $p['referrer'] ?? '' ) ), 0, 500 ),
				substr( sanitize_text_field( (string) ( $p['utm_source'] ?? '' ) ), 0, 100 ),
				substr( sanitize_text_field( (string) ( $p['utm_medium'] ?? '' ) ), 0, 100 ),
				substr( sanitize_text_field( (string) ( $p['utm_campaign'] ?? '' ) ), 0, 150 ),
				substr( sanitize_text_field( (string) ( $p['context'] ?? '' ) ), 0, 200 ),
				current_time( 'mysql' )
			)
		);

		// Unclaimed clicks (people who never sent the message) are only useful for a while.
		if ( 1 === wp_rand( 1, 100 ) ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days', current_time( 'timestamp' ) ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE matched_contact_id = 0 AND created_at < %s', $cutoff ) );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Pull "(Ref TC-XXXXX)" out of an inbound message. Returns the code and the message without it.
	 */
	public static function extract_ref( $text ) {
		$text = (string) $text;
		if ( preg_match( '/\(?\s*Ref[:\s]+(TC-' . self::REF_PATTERN . ')\s*\)?/i', $text, $m ) ) {
			$clean = trim( preg_replace( '/\s{2,}/', ' ', str_replace( $m[0], '', $text ) ) );
			return array( 'ref' => strtoupper( $m[1] ), 'text' => $clean );
		}
		return array( 'ref' => '', 'text' => $text );
	}

	/**
	 * Store the visit details on the contact. A ref can only be claimed by one contact.
	 */
	public static function attach_to_contact( $ref, $contact_id ) {
		global $wpdb;
		$contact_id = (int) $contact_id;
		if ( '' === $ref || $contact_id < 1 ) {
			return false;
		}

		$click = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE ref = %s LIMIT 1', $ref ), ARRAY_A );
		if ( ! $click ) {
			return false;
		}
		if ( (int) $click['matched_contact_id'] > 0 && (int) $click['matched_contact_id'] !== $contact_id ) {
			return false;
		}

		$contacts = $wpdb->prefix . 'tc_agent_contacts';
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT meta_data FROM $contacts WHERE id = %d", $contact_id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}

		$meta = json_decode( (string) $row['meta_data'], true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$touch = array(
			'ref'          => $ref,
			'page_url'     => (string) $click['page_url'],
			'referrer'     => (string) $click['referrer'],
			'utm_source'   => (string) $click['utm_source'],
			'utm_medium'   => (string) $click['utm_medium'],
			'utm_campaign' => (string) $click['utm_campaign'],
			'context'      => (string) $click['context'],
			'session_id'   => (string) $click['session_id'],
			'clicked_at'   => (string) $click['created_at'],
		);

		if ( empty( $meta['first_wa_click'] ) ) {
			$meta['first_wa_click'] = $touch;
		}
		$meta['wa_click'] = $touch;

		$wpdb->update(
			$contacts,
			array( 'meta_data' => wp_json_encode( $meta ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $contact_id )
		);
		$wpdb->update(
			self::table(),
			array( 'matched_contact_id' => $contact_id, 'matched_at' => current_time( 'mysql' ) ),
			array( 'ref' => $ref )
		);

		return true;
	}
}
