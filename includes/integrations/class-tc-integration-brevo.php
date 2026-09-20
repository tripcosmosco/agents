<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Brevo integration: SMTP/API transactional + push-style marketing sends.
 * Uses https://api.brevo.com/v3/smtp/email with Vault key brevo_api_key.
 */
class TC_Integration_Brevo {
	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'brevo_api_key', '' ) : get_option( 'tc_agents_brevo_api_key', '' );
	}
	public static function is_configured() { return ! empty( self::get_api_key() ); }
	public static function get_sender() {
		$email = get_option( 'tc_agents_brevo_sender_email', get_option( 'admin_email' ) );
		$name = get_option( 'tc_agents_brevo_sender_name', 'TripCosmos' );
		return array( 'email' => $email, 'name' => $name );
	}
	public static function send_email( $to, $subject, $html, $text = '' ) {
		if ( ! self::is_configured() ) { return new WP_Error( 'brevo_not_configured', __( 'Brevo API key missing.', 'tripcosmos-agents' ) ); }
		if ( empty( $to ) ) { return new WP_Error( 'brevo_no_recipient', __( 'Recipient missing.', 'tripcosmos-agents' ) ); }
		$sender = self::get_sender();
		$res = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
			'timeout' => 12, 'sslverify' => true,
			'headers' => array( 'api-key' => self::get_api_key(), 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body' => wp_json_encode( array( 'sender' => $sender, 'to' => array( array( 'email' => $to ) ), 'subject' => $subject, 'htmlContent' => $html, 'textContent' => $text ?: wp_strip_all_tags( $html ) ) ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 ) { return array( 'success' => true, 'body' => $body ); }
		$error_msg = ! empty( $body['message'] ) ? $body['message'] : sprintf( __( 'Brevo HTTP %d', 'tripcosmos-agents' ), $code );
		return new WP_Error( 'brevo_api_error', $error_msg );
	}
	/**
	 * Push B2B outreach: send to agency contact, log outcome.
	 */
	public static function send_b2b_outreach( $agency ) {
		$email = $agency['email'] ?? '';
		if ( empty( $email ) ) { return new WP_Error( 'no_email', __( 'Agency has no email.', 'tripcosmos-agents' ) ); }
		$name = $agency['name'] ?? 'Partner';
		$subject = sprintf( __( 'B2B Partnership: Varanasi Tour Packages for %s — TripCosmos', 'tripcosmos-agents' ), $name );
		$html = '<p>Namaste ' . esc_html( $name ) . ' team,</p><p>TripCosmos (Varanasi) offers white-label Kashi–Ayodhya–Prayagraj packages, outstation cabs and Ganga Aarti boats for B2B partners with net rates and instant WhatsApp confirmations.</p><p>Reply to discuss commission slabs and contracting.</p><p>— TripCosmos B2B Desk, Varanasi</p>';
		return self::send_email( $email, $subject, $html );
	}
}
