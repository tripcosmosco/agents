<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Evolution API (v2) WhatsApp adapter: send, parse webhooks, and manage the instance (state, QR, webhook).
 * Mode option tc_agents_whatsapp_mode: 'legacy' | 'evolution'.
 */
class TC_Integration_Evolution {

	const DEFAULT_BASE_URL = 'https://wa.vmstudio.digital';

	public static function get_base_url() {
		$url = (string) get_option( 'tc_agents_evolution_base_url', '' );
		return untrailingslashit( '' !== $url ? $url : self::DEFAULT_BASE_URL );
	}
	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'evolution_api_key', '' ) : get_option( 'tc_agents_evolution_api_key', '' );
	}
	public static function get_instance() { return sanitize_text_field( get_option( 'tc_agents_evolution_instance', 'tripcosmos' ) ); }
	public static function is_configured() { return ! empty( self::get_base_url() ) && ! empty( self::get_api_key() ); }
	public static function is_active_mode() { return 'evolution' === get_option( 'tc_agents_whatsapp_mode', 'legacy' ) && self::is_configured(); }

	/**
	 * One authenticated call to the Evolution server.
	 *
	 * @return array|WP_Error { code:int, body:array }
	 */
	private static function request( $method, $path, $body = null, $timeout = 12 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'evolution_not_configured', __( 'Evolution API is not configured.', 'tripcosmos-agents' ) );
		}
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array( 'apikey' => self::get_api_key(), 'Content-Type' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( self::get_base_url() . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return array( 'code' => (int) wp_remote_retrieve_response_code( $res ), 'body' => is_array( $decoded ) ? $decoded : array() );
	}

	private static function instance_path( $prefix ) {
		return $prefix . '/' . rawurlencode( self::get_instance() );
	}

	/**
	 * Send a text message.
	 *
	 * @return array|WP_Error { success:true, id:string } — `id` is WhatsApp's message id, used for read receipts and to
	 *                        recognise our own message when Evolution echoes it back as a fromMe webhook.
	 */
	public static function send_message( $to, $message ) {
		$guard = class_exists( 'TC_Agents_Guardrails' ) ? TC_Agents_Guardrails::check_permission( 'whatsapp', $to ) : true;
		if ( is_wp_error( $guard ) ) { return $guard; }
		$res = self::request( 'POST', self::instance_path( '/message/sendText' ), array( 'number' => preg_replace( '/[^0-9]/', '', (string) $to ), 'text' => (string) $message ) );
		if ( is_wp_error( $res ) ) { return $res; }
		if ( $res['code'] >= 200 && $res['code'] < 300 ) {
			if ( class_exists( 'TC_Agents_Guardrails' ) ) { TC_Agents_Guardrails::record_outbound( 'whatsapp' ); }
			$id = (string) ( $res['body']['key']['id'] ?? '' );
			if ( '' !== $id ) {
				self::remember_outbound( $id );
			}
			return array( 'success' => true, 'id' => $id );
		}
		$err = $res['body']['response']['message'] ?? $res['body']['message'] ?? '';
		return new WP_Error( 'evolution_send_failed', sprintf( __( 'Evolution HTTP %1$d %2$s', 'tripcosmos-agents' ), $res['code'], is_array( $err ) ? wp_json_encode( $err ) : (string) $err ) );
	}

	/** Messages we sent come back as fromMe webhooks; remembering their ids lets us skip them. */
	public static function remember_outbound( $id ) {
		set_transient( 'tc_wa_out_' . md5( (string) $id ), 1, 2 * DAY_IN_SECONDS );
	}

	public static function is_our_outbound( $id ) {
		return '' !== (string) $id && (bool) get_transient( 'tc_wa_out_' . md5( (string) $id ) );
	}

	/** 'open', 'connecting', 'close', or an error. */
	public static function connection_state() {
		$res = self::request( 'GET', self::instance_path( '/instance/connectionState' ), null, 8 );
		if ( is_wp_error( $res ) ) { return $res; }
		if ( 404 === $res['code'] ) {
			return new WP_Error( 'evolution_no_instance', sprintf( __( 'Instance "%s" does not exist on the Evolution server.', 'tripcosmos-agents' ), self::get_instance() ) );
		}
		if ( 401 === $res['code'] || 403 === $res['code'] ) {
			return new WP_Error( 'evolution_auth', __( 'The Evolution server rejected the API key.', 'tripcosmos-agents' ) );
		}
		return (string) ( $res['body']['instance']['state'] ?? $res['body']['state'] ?? 'unknown' );
	}

	/** QR code (data: URI) to link the business phone, or an error. */
	public static function connect_qr() {
		$res = self::request( 'GET', self::instance_path( '/instance/connect' ), null, 15 );
		if ( is_wp_error( $res ) ) { return $res; }
		$qr = (string) ( $res['body']['base64'] ?? $res['body']['qrcode']['base64'] ?? '' );
		if ( 0 !== strpos( $qr, 'data:image/' ) ) {
			return new WP_Error( 'evolution_no_qr', __( 'No QR code was returned. The phone may already be connected.', 'tripcosmos-agents' ) );
		}
		return $qr;
	}

	/** Points the instance's webhook at this site, authenticated with our shared secret. */
	public static function register_webhook( $url, $secret ) {
		$res = self::request(
			'POST',
			self::instance_path( '/webhook/set' ),
			array(
				'webhook' => array(
					'enabled'  => true,
					'url'      => $url,
					'headers'  => array( 'x-webhook-secret' => $secret ),
					'byEvents' => false,
					'base64'   => false,
					'events'   => array( 'MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE' ),
				),
			)
		);
		if ( is_wp_error( $res ) ) { return $res; }
		if ( $res['code'] >= 200 && $res['code'] < 300 ) {
			return true;
		}
		$err = $res['body']['response']['message'] ?? $res['body']['message'] ?? '';
		return new WP_Error( 'evolution_webhook', sprintf( __( 'Evolution refused the webhook (HTTP %1$d) %2$s', 'tripcosmos-agents' ), $res['code'], is_array( $err ) ? wp_json_encode( $err ) : (string) $err ) );
	}

	/**
	 * Normalise an inbound webhook to:
	 * { event, from, text, name, from_me, id, quoted_id, media, ignored_jid }
	 *
	 * Evolution v2 payloads are checked FIRST: they carry a top-level `sender` (the business number itself), which the
	 * simple-schema branch would otherwise mistake for the customer.
	 */
	public static function normalize_inbound( $data ) {
		$out = array( 'event' => '', 'from' => '', 'text' => '', 'name' => '', 'from_me' => false, 'id' => '', 'quoted_id' => '', 'media' => '', 'ignored_jid' => false );
		if ( ! is_array( $data ) ) {
			return $out;
		}
		$out['event'] = strtolower( str_replace( '_', '.', (string) ( $data['event'] ?? '' ) ) );

		$d   = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		$key = isset( $d['key'] ) && is_array( $d['key'] ) ? $d['key'] : array();
		$msg = isset( $d['message'] ) && is_array( $d['message'] ) ? $d['message'] : null;

		if ( $key || $msg ) {
			$jid = (string) ( $key['remoteJid'] ?? '' );
			if ( false !== strpos( $jid, 'status@broadcast' ) || false !== strpos( $jid, '@g.us' ) || false !== strpos( $jid, '@newsletter' ) ) {
				$out['ignored_jid'] = true;
				return $out;
			}
			// Newer WhatsApp addresses some chats by a private "@lid"; the real number is in an alternate field.
			if ( false !== strpos( $jid, '@lid' ) ) {
				$jid = (string) ( $key['senderPn'] ?? $key['remoteJidAlt'] ?? $key['participantPn'] ?? $d['senderPn'] ?? $jid );
			}
			$out['from']    = preg_replace( '/[^0-9]/', '', (string) preg_replace( '/@.*$/', '', $jid ) );
			$out['from_me'] = ! empty( $key['fromMe'] );
			$out['id']      = (string) ( $key['id'] ?? '' );
			$out['name']    = $out['from_me'] ? '' : (string) ( $d['pushName'] ?? '' );
			if ( is_array( $msg ) ) {
				$parsed           = self::message_text( $msg );
				$out['text']      = $parsed['text'];
				$out['media']     = $parsed['media'];
				$out['quoted_id'] = (string) ( $d['contextInfo']['stanzaId'] ?? $parsed['quoted_id'] );
			}
			return $out;
		}

		// Simple / legacy schema ({ from, body, name }).
		if ( ! empty( $data['from'] ) ) {
			$text           = $data['body'] ?? $data['message'] ?? $data['text'] ?? '';
			$out['from']    = preg_replace( '/[^0-9]/', '', (string) $data['from'] );
			$out['text']    = is_array( $text ) ? '' : (string) $text;
			$out['name']    = (string) ( $data['name'] ?? $data['pushName'] ?? '' );
			$out['from_me'] = ! empty( $data['fromMe'] );
		}
		return $out;
	}

	/**
	 * Text for any WhatsApp message type. Media become a readable label plus caption, so a voice note or photo is never
	 * silently dropped. Returns { text, media, quoted_id }.
	 */
	public static function message_text( array $m ) {
		if ( isset( $m['ephemeralMessage']['message'] ) && is_array( $m['ephemeralMessage']['message'] ) ) {
			$m = $m['ephemeralMessage']['message'];
		}
		if ( isset( $m['viewOnceMessage']['message'] ) && is_array( $m['viewOnceMessage']['message'] ) ) {
			$m = $m['viewOnceMessage']['message'];
		}
		if ( isset( $m['documentWithCaptionMessage']['message'] ) && is_array( $m['documentWithCaptionMessage']['message'] ) ) {
			$m = $m['documentWithCaptionMessage']['message'];
		}
		$quoted = '';
		foreach ( $m as $part ) {
			if ( is_array( $part ) && ! empty( $part['contextInfo']['stanzaId'] ) ) {
				$quoted = (string) $part['contextInfo']['stanzaId'];
				break;
			}
		}
		$cap = function ( $label, $caption ) {
			$caption = trim( (string) $caption );
			return '' === $caption ? $label : $label . ' ' . $caption;
		};

		if ( isset( $m['conversation'] ) ) {
			return array( 'text' => (string) $m['conversation'], 'media' => '', 'quoted_id' => $quoted );
		}
		if ( isset( $m['extendedTextMessage']['text'] ) ) {
			return array( 'text' => (string) $m['extendedTextMessage']['text'], 'media' => '', 'quoted_id' => $quoted );
		}
		$choices = array(
			$m['buttonsResponseMessage']['selectedDisplayText'] ?? null,
			$m['templateButtonReplyMessage']['selectedDisplayText'] ?? null,
			$m['listResponseMessage']['title'] ?? null,
			$m['listResponseMessage']['singleSelectReply']['selectedRowId'] ?? null,
		);
		foreach ( $choices as $c ) {
			if ( is_string( $c ) && '' !== $c ) {
				return array( 'text' => $c, 'media' => '', 'quoted_id' => $quoted );
			}
		}
		if ( isset( $m['imageMessage'] ) ) {
			return array( 'text' => $cap( '[Photo]', $m['imageMessage']['caption'] ?? '' ), 'media' => 'image', 'quoted_id' => $quoted );
		}
		if ( isset( $m['videoMessage'] ) ) {
			return array( 'text' => $cap( '[Video]', $m['videoMessage']['caption'] ?? '' ), 'media' => 'video', 'quoted_id' => $quoted );
		}
		if ( isset( $m['audioMessage'] ) ) {
			return array( 'text' => ! empty( $m['audioMessage']['ptt'] ) ? '[Voice note]' : '[Audio]', 'media' => 'audio', 'quoted_id' => $quoted );
		}
		if ( isset( $m['documentMessage'] ) ) {
			$name = (string) ( $m['documentMessage']['fileName'] ?? $m['documentMessage']['title'] ?? 'file' );
			return array( 'text' => $cap( '[Document: ' . $name . ']', $m['documentMessage']['caption'] ?? '' ), 'media' => 'document', 'quoted_id' => $quoted );
		}
		if ( isset( $m['locationMessage'] ) ) {
			$l    = $m['locationMessage'];
			$text = '[Location] ' . trim( ( $l['name'] ?? '' ) . ' https://maps.google.com/?q=' . ( $l['degreesLatitude'] ?? '' ) . ',' . ( $l['degreesLongitude'] ?? '' ) );
			return array( 'text' => $text, 'media' => 'location', 'quoted_id' => $quoted );
		}
		if ( isset( $m['contactMessage'] ) ) {
			return array( 'text' => '[Contact] ' . (string) ( $m['contactMessage']['displayName'] ?? '' ), 'media' => 'contact', 'quoted_id' => $quoted );
		}
		if ( isset( $m['stickerMessage'] ) ) {
			return array( 'text' => '[Sticker]', 'media' => 'sticker', 'quoted_id' => $quoted );
		}
		if ( isset( $m['reactionMessage'] ) ) {
			return array( 'text' => '', 'media' => 'reaction', 'quoted_id' => $quoted );
		}
		return array( 'text' => '', 'media' => '', 'quoted_id' => $quoted );
	}

	/**
	 * Delivery / read receipt from a messages.update webhook: { id, status } with status one of
	 * sent | delivered | read | failed, or null when the payload is not a receipt.
	 */
	public static function normalize_receipt( $data ) {
		$d  = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		$id = (string) ( $d['keyId'] ?? $d['key']['id'] ?? $d['messageId'] ?? '' );
		$st = strtoupper( (string) ( $d['status'] ?? $d['update']['status'] ?? '' ) );
		$map = array(
			'SERVER_ACK'   => 'sent',
			'DELIVERY_ACK' => 'delivered',
			'READ'         => 'read',
			'PLAYED'       => 'read',
			'ERROR'        => 'failed',
		);
		if ( '' === $id || ! isset( $map[ $st ] ) ) {
			return null;
		}
		return array( 'id' => $id, 'status' => $map[ $st ] );
	}
}
