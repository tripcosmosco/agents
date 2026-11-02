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
		$vault_key = class_exists( 'TC_Agents_Vault' ) ? (string) TC_Agents_Vault::get( 'evolution_api_key', '' ) : '';
		if ( '' !== $vault_key ) {
			return $vault_key;
		}
		$key = (string) get_option( 'tc_agents_evolution_api_key', '' );
		if ( '' !== $key ) {
			return $key;
		}
		return 'bebf8554b69b4eff665465956069890dadeb84547a964ba0ac129e43e98576a2';
	}
	public static function get_instance() { return sanitize_text_field( get_option( 'tc_agents_evolution_instance', 'TripCosmos.co' ) ); }
	public static function is_configured() { return ! empty( self::get_base_url() ) && ! empty( self::get_api_key() ); }
	public static function is_active_mode() { return 'evolution' === get_option( 'tc_agents_whatsapp_mode', 'legacy' ) && self::is_configured(); }

	/**
	 * One authenticated call to the Evolution server (prefers fast 127.0.0.1:18080 loopback when on same VPS).
	 *
	 * @return array|WP_Error { code:int, body:array }
	 */
	private static function request( $method, $path, $body = null, $timeout = 12 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'evolution_not_configured', __( 'Evolution API is not configured.', 'tripcosmos-agents' ) );
		}
		$args = array(
			'method'    => $method,
			'timeout'   => $timeout,
			'sslverify' => false,
			'headers'   => array( 'apikey' => self::get_api_key(), 'Content-Type' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		// Try fast local Docker loopback first (5ms vs external DNS/TLS)
		$res = wp_remote_request( 'http://127.0.0.1:18080' . $path, $args );
		if ( is_wp_error( $res ) ) {
			$res = wp_remote_request( self::get_base_url() . $path, $args );
		}
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
	 * @param string $to
	 * @param string $message
	 * @param string $context 'conversational' | 'inbound_reply' | 'staff_relay' | 'staff_alert' | 'broadcast'
	 * @return array|WP_Error { success:true, id:string } — `id` is WhatsApp's message id, used for read receipts and to
	 *                        recognise our own message when Evolution echoes it back as a fromMe webhook.
	 */
	public static function send_message( $to, $message, $context = 'conversational' ) {
		$guard = class_exists( 'TC_Agents_Guardrails' ) ? TC_Agents_Guardrails::check_permission( 'whatsapp', $to, $context ) : true;
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

	/**
	 * Simulate real-time human typing or recording presence on WhatsApp.
	 *
	 * @param string $to Phone number.
	 * @param string $presence 'composing' | 'recording' | 'available' | 'paused'.
	 * @param int $delay Milliseconds to simulate typing.
	 * @return array|WP_Error
	 */
	/**
	 * Send Interactive WhatsApp Action Buttons (Evolution API v2 native).
	 *
	 * @param string $to Phone number.
	 * @param string $title Header title.
	 * @param string $description Main message body text.
	 * @param array $buttons Array of button definitions: [['displayText' => '...', 'id' => '...']].
	 * @param string $footer Optional footer label.
	 * @return array|WP_Error
	 */
	public static function send_buttons( $to, $title, $description, array $buttons, $footer = 'TripCosmos.co • Pilgrimage Desk' ) {
		$clean = preg_replace( '/[^0-9]/', '', (string) $to );
		if ( empty( $clean ) ) {
			return new WP_Error( 'invalid_phone', 'Invalid recipient phone' );
		}

		$formatted_buttons = array();
		foreach ( $buttons as $idx => $btn ) {
			$text = is_array( $btn ) ? ( $btn['displayText'] ?? $btn['text'] ?? "Option " . ( $idx + 1 ) ) : (string) $btn;
			$id   = is_array( $btn ) ? ( $btn['id'] ?? "btn_" . ( $idx + 1 ) ) : "btn_" . ( $idx + 1 );
			$formatted_buttons[] = array(
				'type'        => 'reply',
				'displayText' => $text,
				'id'          => $id,
			);
		}

		$payload = array(
			'number'      => $clean,
			'title'       => $title,
			'description' => $description,
			'footer'      => $footer,
			'buttons'     => $formatted_buttons,
		);

		$res = self::request( 'POST', self::instance_path( '/message/sendButtons' ), $payload, 15 );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $res['code'] >= 200 && $res['code'] < 300 ) {
			$id = (string) ( $res['body']['key']['id'] ?? '' );
			if ( '' !== $id ) {
				self::remember_outbound( $id );
			}
			return array( 'success' => true, 'id' => $id );
		}
		return new WP_Error( 'evolution_send_buttons_failed', sprintf( __( 'Evolution buttons error HTTP %d', 'tripcosmos-agents' ), $res['code'] ) );
	}

	/**
	 * Retrieve base64 encoded media payload for voice notes, photos, and documents.
	 *
	 * @param string $message_id Message ID.
	 * @param bool $convert_mp4 Convert to MP4 if video/audio.
	 * @return string Base64 string or empty string on failure.
	 */
	public static function get_media_base64( $message_id, $convert_mp4 = false ) {
		if ( empty( $message_id ) ) {
			return '';
		}
		$payload = array(
			'message' => array(
				'key' => array(
					'id' => $message_id,
				),
			),
			'convertToMp4' => (bool) $convert_mp4,
		);
		$res = self::request( 'POST', self::instance_path( '/chat/getBase64FromMediaMessage' ), $payload, 15 );
		if ( ! is_wp_error( $res ) && in_array( (int) ( $res['code'] ?? 0 ), array( 200, 201 ), true ) && ! empty( $res['body']['base64'] ) ) {
			return (string) $res['body']['base64'];
		}
		return '';
	}

	/**
	 * Resolves an accessible public URL for WhatsApp media (photos, videos, audio, PDFs).
	 * Caches decrypted media under /wp-content/uploads/tc-media/{message_id}.{ext} to avoid repeat Evolution hits.
	 */
	public static function resolve_media_url( $message_id, $media_type = 'image', $existing_url = '' ) {
		if ( empty( $message_id ) ) {
			return $existing_url;
		}

		// If existing_url is already a valid non-encrypted public URL, return it
		if ( ! empty( $existing_url ) && 0 === strpos( $existing_url, 'http' ) && false === strpos( $existing_url, 'mmg.whatsapp.net' ) && false === strpos( $existing_url, '.enc' ) ) {
			return $existing_url;
		}

		$ext_map = array(
			'image'    => 'jpeg',
			'photo'    => 'jpeg',
			'video'    => 'mp4',
			'audio'    => 'mp3',
			'document' => 'pdf',
			'pdf'      => 'pdf',
		);
		$ext = $ext_map[ strtolower( $media_type ) ] ?? 'jpeg';

		$upload_dir = wp_upload_dir();
		$media_dir  = $upload_dir['basedir'] . '/tc-media';
		$file_path  = $media_dir . '/' . $message_id . '.' . $ext;
		$public_url = $upload_dir['baseurl'] . '/tc-media/' . $message_id . '.' . $ext;

		if ( file_exists( $file_path ) && filesize( $file_path ) > 0 ) {
			return $public_url;
		}

		// Fetch from Evolution API
		$b64 = self::get_media_base64( $message_id, ( 'video' === $media_type ) );
		if ( ! empty( $b64 ) ) {
			if ( ! file_exists( $media_dir ) ) {
				wp_mkdir_p( $media_dir );
			}
			$decoded = base64_decode( $b64 );
			if ( ! empty( $decoded ) ) {
				@file_put_contents( $file_path, $decoded );
				return $public_url;
			}
		}

		return $existing_url;
	}

	/**
	 * Self-healing watchdog: checks connection state and auto-reconnects if dropped.
	 */
	public static function auto_heal() {
		$state = self::connection_state();
		if ( 'open' === $state ) {
			return true;
		}
		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log( 'evolution_auto_heal_triggered', 'warning', array( 'prior_state' => is_wp_error( $state ) ? $state->get_error_message() : $state ), '', 'whatsapp' );
		}
		self::restart_instance();
		return self::connect_qr();
	}

	public static function send_presence( $to, $presence = 'composing', $delay = 1200 ) {
		$clean = preg_replace( '/[^0-9]/', '', (string) $to );
		if ( empty( $clean ) ) {
			return false;
		}
		return self::request(
			'POST',
			self::instance_path( '/chat/sendPresence' ),
			array(
				'number'   => $clean,
				'presence' => $presence,
				'delay'    => (int) $delay,
			),
			5
		);
	}

	/**
	 * Mark an inbound message as read (blue ticks) on customer's WhatsApp.
	 *
	 * @param string $to Phone number.
	 * @param string $message_id WhatsApp message ID.
	 * @return array|WP_Error
	 */
	public static function mark_read( $to, $message_id ) {
		$clean = preg_replace( '/[^0-9]/', '', (string) $to );
		if ( empty( $clean ) || empty( $message_id ) ) {
			return false;
		}
		return self::request(
			'POST',
			self::instance_path( '/chat/markMessageAsRead' ),
			array(
				'readMessages' => array(
					array(
						'id'        => (string) $message_id,
						'fromMe'    => false,
						'remoteJid' => $clean . '@s.whatsapp.net',
					),
				),
			),
			5
		);
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

	/** QR code (data: URI) to link the business phone, pairing code, or status. */
	public static function connect_qr() {
		// First verify if instance exists, if 404 create it
		$state = self::connection_state();
		if ( is_wp_error( $state ) && 'evolution_no_instance' === $state->get_error_code() ) {
			self::create_instance();
		}

		$business_phone = preg_replace( '/[^0-9]/', '', (string) get_option( 'tc_agents_human_whatsapp_number', '919336116210' ) );
		$connect_path   = self::instance_path( '/instance/connect' );
		if ( ! empty( $business_phone ) ) {
			$connect_path .= '?number=' . rawurlencode( $business_phone );
		}

		$res = self::request( 'GET', $connect_path, null, 20 );
		if ( is_wp_error( $res ) ) { return $res; }

		// In Evolution v2, QR is in body.base64 or body.qrcode.base64, pairing code is in body.pairingCode
		$qr         = (string) ( $res['body']['base64'] ?? $res['body']['qrcode']['base64'] ?? '' );
		$pairing    = (string) ( $res['body']['pairingCode'] ?? '' );
		$state_name = (string) ( $res['body']['instance']['state'] ?? $res['body']['state'] ?? '' );

		if ( ! empty( $pairing ) && strlen( $pairing ) === 8 ) {
			$pairing = substr( $pairing, 0, 4 ) . '-' . substr( $pairing, 4 );
		}

		if ( ! empty( $qr ) ) {
			if ( 0 !== strpos( $qr, 'data:image/' ) ) {
				$qr = 'data:image/png;base64,' . $qr;
			}
			return array(
				'success'      => true,
				'qr'           => $qr,
				'pairing_code' => $pairing,
				'state'        => $state_name ?: 'connecting',
				'message'      => __( 'Scan this QR code with WhatsApp on your phone (+91 93361 16210) to link session.', 'tripcosmos-agents' ),
			);
		}

		if ( 'open' === $state_name ) {
			return array(
				'success' => true,
				'qr'      => '',
				'state'   => 'open',
				'message' => __( 'WhatsApp Gateway is already connected and online!', 'tripcosmos-agents' ),
			);
		}

		if ( ! empty( $pairing ) ) {
			return array(
				'success'      => true,
				'qr'           => '',
				'pairing_code' => $pairing,
				'state'        => $state_name ?: 'connecting',
				'message'      => sprintf( __( 'Pairing Code: %s. Enter this code in WhatsApp -> Linked Devices -> Link with phone number.', 'tripcosmos-agents' ), $pairing ),
			);
		}

		return new WP_Error( 'evolution_no_qr', __( 'No QR code was returned by Evolution. Instance may be initializing or already connected.', 'tripcosmos-agents' ) );
	}

	/** Create instance if missing on server */
	public static function create_instance() {
		return self::request(
			'POST',
			'/instance/create',
			array(
				'instanceName' => self::get_instance(),
				'token'        => self::get_api_key(),
				'qrcode'       => true,
				'integration'  => 'WHATSAPP-BAILEYS',
			),
			15
		);
	}

	/** Logout current disconnected session to force a fresh QR code */
	public static function logout_instance() {
		return self::request( 'DELETE', self::instance_path( '/instance/logout' ), null, 15 );
	}

	/** Restart the instance container / socket */
	public static function restart_instance() {
		return self::request( 'POST', self::instance_path( '/instance/restart' ), null, 15 );
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
				$out['media_url'] = (string) ( $parsed['media_url'] ?? '' );
				$out['media_name']= (string) ( $parsed['file_name'] ?? '' );
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
		$quoted      = '';
		$quoted_text = '';
		foreach ( $m as $part ) {
			if ( is_array( $part ) && ! empty( $part['contextInfo']['stanzaId'] ) ) {
				$quoted = (string) $part['contextInfo']['stanzaId'];
				if ( ! empty( $part['contextInfo']['quotedMessage'] ) ) {
					$qm          = $part['contextInfo']['quotedMessage'];
					$quoted_text = (string) ( $qm['conversation'] ?? $qm['extendedTextMessage']['text'] ?? '' );
				}
				break;
			}
		}
		$cap = function ( $label, $caption ) {
			$caption = trim( (string) $caption );
			return '' === $caption ? $label : $label . ' ' . $caption;
		};

		if ( isset( $m['conversation'] ) ) {
			return array( 'text' => (string) $m['conversation'], 'media' => '', 'quoted_id' => $quoted, 'quoted_text' => $quoted_text );
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
			$img = $m['imageMessage'];
			$media_url = ! empty( $img['url'] ) ? (string) $img['url'] : ( ! empty( $img['jpegThumbnail'] ) ? 'data:image/jpeg;base64,' . $img['jpegThumbnail'] : '' );
			return array(
				'text'      => $cap( '[Photo]', $img['caption'] ?? '' ),
				'media'     => 'image',
				'media_url' => $media_url,
				'quoted_id' => $quoted,
			);
		}
		if ( isset( $m['videoMessage'] ) ) {
			$vid = $m['videoMessage'];
			$media_url = ! empty( $vid['url'] ) ? (string) $vid['url'] : '';
			return array(
				'text'      => $cap( '[Video]', $vid['caption'] ?? '' ),
				'media'     => 'video',
				'media_url' => $media_url,
				'quoted_id' => $quoted,
			);
		}
		if ( isset( $m['audioMessage'] ) ) {
			$aud = $m['audioMessage'];
			$media_url = ! empty( $aud['url'] ) ? (string) $aud['url'] : '';
			return array(
				'text'      => ! empty( $aud['ptt'] ) ? '[Voice note]' : '[Audio]',
				'media'     => 'audio',
				'media_url' => $media_url,
				'quoted_id' => $quoted,
			);
		}
		if ( isset( $m['documentMessage'] ) ) {
			$doc = $m['documentMessage'];
			$name = (string) ( $doc['fileName'] ?? $doc['title'] ?? 'document' );
			$media_url = ! empty( $doc['url'] ) ? (string) $doc['url'] : '';
			return array(
				'text'      => $cap( '[Document: ' . $name . ']', $doc['caption'] ?? '' ),
				'media'     => 'document',
				'media_url' => $media_url,
				'file_name' => $name,
				'quoted_id' => $quoted,
			);
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

	/**
	 * Extract clean digits phone number from an Evolution chat or message key (supports @lid + remoteJidAlt).
	 */
	public static function extract_phone_from_key( array $key, $fallback_jid = '' ) {
		$jid = (string) ( $key['remoteJidAlt'] ?? $key['senderPn'] ?? $key['participantPn'] ?? $key['remoteJid'] ?? $fallback_jid );
		if ( false !== strpos( $jid, '@lid' ) && ! empty( $key['remoteJidAlt'] ) ) {
			$jid = (string) $key['remoteJidAlt'];
		}
		// If still @lid without an alt JID, return empty phone so caller knows it's an unmapped LID
		if ( false !== strpos( $jid, '@lid' ) ) {
			return '';
		}
		$clean = preg_replace( '/[^0-9]/', '', (string) preg_replace( '/@.*$/', '', $jid ) );
		return $clean;
	}

	/**
	 * Fetch live WhatsApp chat threads directly from Evolution API (/chat/findChats/{instance}).
	 */
	public static function fetch_live_chats( $limit = 60 ) {
		$res = self::request( 'POST', self::instance_path( '/chat/findChats' ), new stdClass(), 10 );
		if ( is_wp_error( $res ) || 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			return array();
		}

		$threads = array();
		foreach ( $res['body'] as $chat ) {
			if ( ! is_array( $chat ) ) {
				continue;
			}
			$remote_jid = (string) ( $chat['remoteJid'] ?? '' );
			if ( '' === $remote_jid || false !== strpos( $remote_jid, '@g.us' ) || false !== strpos( $remote_jid, 'status@broadcast' ) || false !== strpos( $remote_jid, '@newsletter' ) ) {
				continue;
			}

			$last_msg_obj = isset( $chat['lastMessage'] ) && is_array( $chat['lastMessage'] ) ? $chat['lastMessage'] : array();
			$key          = isset( $last_msg_obj['key'] ) && is_array( $last_msg_obj['key'] ) ? $last_msg_obj['key'] : array();
			$phone        = self::extract_phone_from_key( $key, $remote_jid );

			// If no phone extracted from @lid, skip unless pushName is present
			if ( '' === $phone && empty( $chat['pushName'] ) ) {
				continue;
			}

			$msg_payload = isset( $last_msg_obj['message'] ) && is_array( $last_msg_obj['message'] ) ? $last_msg_obj['message'] : array();
			$parsed_msg  = ! empty( $msg_payload ) ? self::message_text( $msg_payload ) : array( 'text' => '', 'media' => '' );
			$last_text   = (string) ( $parsed_msg['text'] ?? '' );

			$from_me     = ! empty( $key['fromMe'] );
			$push_name   = (string) ( $chat['pushName'] ?? '' );
			if ( '' === $push_name && ! $from_me && ! empty( $last_msg_obj['pushName'] ) && 'Você' !== $last_msg_obj['pushName'] ) {
				$push_name = (string) $last_msg_obj['pushName'];
			}

			$ts = ! empty( $last_msg_obj['messageTimestamp'] )
				? (int) $last_msg_obj['messageTimestamp']
				: ( ! empty( $chat['updatedAt'] ) ? strtotime( (string) $chat['updatedAt'] ) : time() );

			$raw_status = strtoupper( (string) ( $last_msg_obj['status'] ?? 'SERVER_ACK' ) );
			$status_map = array(
				'READ'         => 'read',
				'PLAYED'       => 'read',
				'DELIVERY_ACK' => 'delivered',
				'SERVER_ACK'   => 'sent',
			);

			$threads[] = array(
				'remote_jid'          => $remote_jid,
				'remote_jid_alt'      => (string) ( $key['remoteJidAlt'] ?? '' ),
				'phone'               => $phone,
				'push_name'           => $push_name,
				'profile_pic_url'     => (string) ( $chat['profilePicUrl'] ?? '' ),
				'last_message'        => $last_text,
				'last_message_from_me'=> $from_me,
				'last_message_status' => $status_map[ $raw_status ] ?? 'sent',
				'timestamp'           => $ts,
				'updated_at'          => gmdate( 'Y-m-d H:i:s', $ts ),
				'unread_count'        => (int) ( $chat['unreadCount'] ?? 0 ),
			);

			if ( count( $threads ) >= $limit ) {
				break;
			}
		}

		return $threads;
	}

	/**
	 * Fetch real chronological WhatsApp message history for a customer from Evolution API (/chat/findMessages/{instance}).
	 */
	public static function fetch_chat_messages( $phone, $remote_jid = '', $limit = 40 ) {
		$clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( strlen( $clean_phone ) === 10 ) {
			$clean_phone = '91' . $clean_phone;
		}

		$queries = array();
		if ( '' !== $remote_jid ) {
			$queries[] = array( 'where' => array( 'key' => array( 'remoteJid' => $remote_jid ) ), 'limit' => $limit );
		}
		if ( '' !== $clean_phone ) {
			$std_jid = $clean_phone . '@s.whatsapp.net';
			if ( $std_jid !== $remote_jid ) {
				$queries[] = array( 'where' => array( 'key' => array( 'remoteJid' => $std_jid ) ), 'limit' => $limit );
			}
			$queries[] = array( 'where' => array( 'key' => array( 'remoteJidAlt' => $std_jid ) ), 'limit' => $limit );
		}

		$raw_records = array();
		foreach ( $queries as $q ) {
			$res = self::request( 'POST', self::instance_path( '/chat/findMessages' ), $q, 10 );
			if ( ! is_wp_error( $res ) && 200 === $res['code'] && ! empty( $res['body']['messages']['records'] ) && is_array( $res['body']['messages']['records'] ) ) {
				foreach ( $res['body']['messages']['records'] as $rec ) {
					$msg_id = (string) ( $rec['key']['id'] ?? $rec['id'] ?? '' );
					if ( '' !== $msg_id && ! isset( $raw_records[ $msg_id ] ) ) {
						$raw_records[ $msg_id ] = $rec;
					}
				}
			}
		}

		$messages = array();
		foreach ( $raw_records as $msg_id => $rec ) {
			$msg_type = (string) ( $rec['messageType'] ?? '' );
			if ( 'secretEncryptedMessage' === $msg_type || 'reactionMessage' === $msg_type || 'protocolMessage' === $msg_type ) {
				continue;
			}
			$msg_payload = isset( $rec['message'] ) && is_array( $rec['message'] ) ? $rec['message'] : array();
			$parsed      = self::message_text( $msg_payload );
			$text        = trim( (string) ( $parsed['text'] ?? '' ) );
			if ( '' === $text ) {
				continue;
			}

			$from_me = ! empty( $rec['key']['fromMe'] );
			$ts      = (int) ( $rec['messageTimestamp'] ?? time() );

			// Determine delivery/read receipt status
			$status = 'sent';
			if ( ! empty( $rec['MessageUpdate'] ) && is_array( $rec['MessageUpdate'] ) ) {
				foreach ( $rec['MessageUpdate'] as $upd ) {
					$st = strtoupper( (string) ( $upd['status'] ?? '' ) );
					if ( 'READ' === $st || 'PLAYED' === $st ) {
						$status = 'read';
						break;
					} elseif ( 'DELIVERY_ACK' === $st ) {
						$status = 'delivered';
					}
				}
			}

			$media_type = (string) ( $parsed['media'] ?? '' );
			$media_url  = (string) ( $parsed['media_url'] ?? '' );

			// Auto-resolve encrypted media (WhatsApp MMG/ENC) into public cached files
			if ( in_array( $media_type, array( 'image', 'photo', 'video', 'audio', 'document', 'pdf' ), true ) ) {
				$media_url = self::resolve_media_url( $msg_id, $media_type, $media_url );
			}

			$messages[] = array(
				'id'               => $msg_id,
				'text'             => $text,
				'is_from_customer' => ! $from_me,
				'timestamp'        => gmdate( 'c', $ts ),
				'epoch'            => $ts,
				'status'           => $status,
				'media'            => $media_type,
				'media_url'        => $media_url,
				'media_type'       => $media_type,
				'author_name'      => $from_me ? 'TripCosmos Desk' : (string) ( $rec['pushName'] ?? 'Customer' ),
			);
		}

		// Sort oldest -> newest for chat display
		usort(
			$messages,
			function ( $a, $b ) {
				return ( $a['epoch'] ?? 0 ) <=> ( $b['epoch'] ?? 0 );
			}
		);

		return array_slice( $messages, -$limit );
	}

	/**
	 * Delete a sent message for everyone on WhatsApp via Evolution API.
	 */
	public static function delete_message( $remote_jid, $message_id, $from_me = true ) {
		if ( empty( $remote_jid ) || empty( $message_id ) ) {
			return false;
		}
		$payload = array(
			'key' => array(
				'remoteJid' => $remote_jid,
				'fromMe'    => (bool) $from_me,
				'id'        => $message_id,
			),
		);
		$res = self::request( 'DELETE', self::instance_path( '/chat/deleteMessageForEveryone' ), $payload, 15 );
		if ( is_wp_error( $res ) || 200 !== (int) ( $res['code'] ?? 0 ) ) {
			$res = self::request( 'POST', self::instance_path( '/chat/deleteMessageForEveryone' ), $payload, 15 );
		}
		return ! is_wp_error( $res ) && in_array( (int) ( $res['code'] ?? 0 ), array( 200, 201 ), true );
	}
}
