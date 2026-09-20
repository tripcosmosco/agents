<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Evolution API WhatsApp integration (dual-mode alongside legacy wa.vmstudio.digital).
 * Mode option tc_agents_whatsapp_mode: 'legacy' | 'evolution'.
 */
class TC_Integration_Evolution {
	public static function get_base_url() { return untrailingslashit( get_option( 'tc_agents_evolution_base_url', '' ) ); }
	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'evolution_api_key', '' ) : get_option( 'tc_agents_evolution_api_key', '' );
	}
	public static function get_instance() { return sanitize_text_field( get_option( 'tc_agents_evolution_instance', 'tripcosmos' ) ); }
	public static function is_configured() { return ! empty( self::get_base_url() ) && ! empty( self::get_api_key() ); }
	public static function is_active_mode() { return 'evolution' === get_option( 'tc_agents_whatsapp_mode', 'legacy' ) && self::is_configured(); }
	/**
	 * Send text message via Evolution API.
	 */
	public static function send_message( $to, $message ) {
		if ( ! self::is_configured() ) { return new WP_Error( 'evolution_not_configured', __( 'Evolution API not configured.', 'tripcosmos-agents' ) ); }
		$guard = class_exists( 'TC_Agents_Guardrails' ) ? TC_Agents_Guardrails::check_permission( 'whatsapp', $to ) : true;
		if ( is_wp_error( $guard ) ) { return $guard; }
		$url = self::get_base_url() . '/message/sendText/' . rawurlencode( self::get_instance() );
		$res = wp_remote_post( $url, array(
			'timeout' => 12, 'sslverify' => false,
			'headers' => array( 'apikey' => self::get_api_key(), 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'number' => preg_replace( '/[^0-9]/', '', $to ), 'text' => $message ) ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			if ( class_exists( 'TC_Agents_Guardrails' ) ) { TC_Agents_Guardrails::record_outbound( 'whatsapp' ); }
			return array( 'success' => true );
		}
		return new WP_Error( 'evolution_send_failed', sprintf( __( 'Evolution HTTP %d', 'tripcosmos-agents' ), $code ) );
	}
	/**
	 * Normalize inbound Evolution webhook payload to {from, text, name}.
	 * Supports event messages.upsert with keyRemoteJid + message conversation/extendedText.
	 */
	public static function normalize_inbound( $data ) {
		$from = ''; $text = ''; $name = '';
		// Direct simple schema.
		if ( ! empty( $data['from'] ) || ! empty( $data['sender'] ) ) {
			$from = $data['from'] ?? $data['sender'] ?? '';
			$text = $data['body'] ?? $data['message'] ?? $data['text'] ?? '';
			$name = $data['name'] ?? $data['pushName'] ?? '';
			return array( 'from' => $from, 'text' => is_array( $text ) ? '' : (string) $text, 'name' => (string) $name );
		}
		// Evolution messages.upsert schema.
		$msg = $data['data']['message'] ?? $data['message'] ?? null;
		$key = $data['data']['key'] ?? $data['key'] ?? array();
		if ( is_array( $msg ) ) {
			$from = $key['remoteJid'] ?? '';
			$from = preg_replace( '/@.*$/', '', (string) $from );
			$text = $msg['conversation'] ?? $msg['extendedTextMessage']['text'] ?? $msg['text'] ?? '';
			$name = $data['data']['pushName'] ?? '';
		}
		return array( 'from' => (string) $from, 'text' => is_array( $text ) ? '' : (string) $text, 'name' => (string) $name );
	}
}
