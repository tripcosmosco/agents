<?php
/**
 * WhatsApp Gateway Integration Adapter (wa.vmstudio.digital).
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_WhatsApp {

	public static function get_api_url() {
		$url = get_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' );
		return untrailingslashit( $url );
	}

	public static function get_token() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'whatsapp_token' ) : get_option( 'tc_agents_whatsapp_token', '' );
	}


	public static function is_configured() {
		$mode = get_option( 'tc_agents_whatsapp_mode', 'legacy' );
		if ( 'evolution' === $mode ) {
			return class_exists( 'TC_Integration_Evolution' ) && TC_Integration_Evolution::is_configured();
		}
		return ! empty( self::get_api_url() ) && ! empty( self::get_token() );
	}

	/**
	 * Process incoming webhook from wa.vmstudio.digital.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle_incoming_webhook( WP_REST_Request $request ) {
		$secret_configured = get_option( 'tc_agents_whatsapp_webhook_secret', '' );

		// 1. Meta Cloud API / WhatsApp GET verification challenge
		$hub_mode      = $request->get_param( 'hub_mode' ) ?: $request->get_param( 'hub.mode' );
		$hub_token     = $request->get_param( 'hub_verify_token' ) ?: $request->get_param( 'hub.verify_token' );
		$hub_challenge = $request->get_param( 'hub_challenge' ) ?: $request->get_param( 'hub.challenge' );

		if ( 'subscribe' === $hub_mode ) {
			if ( empty( $secret_configured ) || $hub_token === $secret_configured ) {
				status_header( 200 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo sanitize_text_field( (string) $hub_challenge );
				exit;
			}
			return new WP_REST_Response( 'Forbidden', 403 );
		}

		$provided_token = $request->get_header( 'x-webhook-secret' ) ?: $request->get_param( 'token' ) ?: $request->get_header( 'apikey' ) ?: $request->get_header( 'x-api-key' );

		// Fail closed: with no secret configured anyone could post forged messages. Create one so it shows under Integrations > WhatsApp.
		if ( empty( $secret_configured ) ) {
			update_option( 'tc_agents_whatsapp_webhook_secret', wp_generate_password( 24, false ) );
			TC_Agents_Logger::log( 'whatsapp_webhook_rejected', 'warning', array( 'reason' => 'no_secret_configured' ), '', 'whatsapp' );
			return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
		}

		$evolution_key = (string) get_option( 'tc_agents_evolution_api_key', '' );
		$is_authorized = hash_equals( (string) $secret_configured, (string) $provided_token )
			|| ( ! empty( $evolution_key ) && hash_equals( (string) $evolution_key, (string) $provided_token ) );

		if ( ! $is_authorized ) {
			TC_Agents_Logger::log( 'whatsapp_webhook_auth_failed', 'warning', array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ), '', 'whatsapp' );
			return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
		}

		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		// Immediate FastCGI Ack: Flush HTTP 200 to Evolution API immediately so Axios never times out (499) or floods retry loops
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			status_header( 200 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'status' => 'received' ) );
			fastcgi_finish_request();
		}

		// Receipts, connection changes, our own echoes, the business phone and staff replies never reach the AI.
		if ( class_exists( 'TC_WhatsApp_Hub' ) && is_array( $data ) ) {
			$handled = TC_WhatsApp_Hub::before_ai( $data );
			if ( $handled instanceof WP_REST_Response ) {
				return $handled;
			}
		}

		// Normalize sender/text: Evolution messages.upsert first, then legacy schemas.
		$sender_phone = '';
		$message_text = '';
		$media        = '';
		$external_id  = '';
		$sender_name  = $data['name'] ?? '';
		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			$norm = TC_Integration_Evolution::normalize_inbound( is_array( $data ) ? $data : array() );
			if ( ! empty( $norm['from_me'] ) ) {
				return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Outbound message from self (fromMe)' ), 200 );
			}
			if ( ! empty( $norm['ignored_jid'] ) ) {
				return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Status broadcast or group message ignored' ), 200 );
			}
			$sender_phone = $norm['from'] ?? '';
			$message_text = $norm['text'] ?? '';
			$sender_name  = ( $norm['name'] ?? '' ) ?: $sender_name;
			$media        = (string) ( $norm['media'] ?? '' );
			$media_url    = (string) ( $norm['media_url'] ?? '' );
			$media_name   = (string) ( $norm['media_name'] ?? '' );
			$external_id  = (string) ( $norm['id'] ?? '' );
		}
		if ( empty( $sender_phone ) || empty( $message_text ) ) {
			$sender_phone = $sender_phone ?: ( $data['from'] ?? $data['sender'] ?? $data['phone'] ?? ( $data['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? '' ) );
			$meta_msg     = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? array();
			$raw_text     = $data['body'] ?? $data['message'] ?? $data['text'] ?? ( $meta_msg['text']['body'] ?? '' );
			if ( empty( $raw_text ) && ! empty( $meta_msg ) ) {
				// Meta WhatsApp Cloud interactive button / list / quick replies
				$raw_text = $meta_msg['interactive']['button_reply']['title']
					?? $meta_msg['interactive']['list_reply']['title']
					?? $meta_msg['button']['text']
					?? '';
			}
			$message_text = $message_text ?: ( is_array( $raw_text ) ? '' : (string) $raw_text );
		}

		if ( empty( $sender_phone ) || empty( $message_text ) ) {
			return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'No message or sender' ), 200 );
		}

		// Normalize phone
		$sender_phone = preg_replace( '/[^0-9]/', '', $sender_phone );
		$session_id   = 'wa_' . $sender_phone;

		// Deduplication Guard: Discard duplicate webhooks (e.g. Evolution retries, upsert re-deliveries)
		if ( ! empty( $external_id ) ) {
			$dedup_key = 'tc_wa_msg_seen_' . md5( $external_id );
			if ( get_transient( $dedup_key ) ) {
				return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Duplicate WhatsApp message ID' ), 200 );
			}
			set_transient( $dedup_key, 1, 15 * MINUTE_IN_SECONDS );
		}

		// Secondary Content Deduplication Guard: Discard duplicate identical payloads from the same sender within 10 seconds
		$content_hash   = md5( $sender_phone . '_' . trim( (string) $message_text ) );
		$dedup_hash_key = 'tc_wa_hash_seen_' . $content_hash;
		if ( get_transient( $dedup_hash_key ) ) {
			return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Duplicate payload from sender within cooldown' ), 200 );
		}
		set_transient( $dedup_hash_key, 1, 10 );

		// STOP / START keywords: record consent and confirm, without involving the AI.
		$keyword = TC_Contact_Service::handle_whatsapp_keyword( $sender_phone, $message_text );
		if ( $keyword ) {
			$confirm = 'opted_out' === $keyword
				? __( 'You have been unsubscribed from TripCosmos WhatsApp updates. Reply START to subscribe again. You can still message us any time for help.', 'tripcosmos-agents' )
				: __( 'You are subscribed to TripCosmos WhatsApp updates again. Reply STOP to unsubscribe.', 'tripcosmos-agents' );
			self::send_message( $sender_phone, $confirm );
			return new WP_REST_Response( array( 'status' => $keyword ), 200 );
		}

		// Unify or lookup contact
		list( $lead_contact_id, $is_new_lead ) = self::ensure_whatsapp_contact( $sender_phone, $sender_name );

		// Web-to-WhatsApp attribution: the first message carries "(Ref TC-XXXXX)" added by the site's WhatsApp button.
		if ( class_exists( 'TC_Agents_WA_Attribution' ) ) {
			$extracted = TC_Agents_WA_Attribution::extract_ref( $message_text );
			if ( '' !== $extracted['ref'] ) {
				if ( $lead_contact_id ) {
					TC_Agents_WA_Attribution::attach_to_contact( $extracted['ref'], $lead_contact_id );
				}
				if ( '' !== $extracted['text'] ) {
					$message_text = $extracted['text'];
				}
			}
		}

		// Stop-on-Reply Governor: Cancel active automated follow-up sequences when lead replies on WhatsApp
		if ( $lead_contact_id && class_exists( 'TC_Agent_Sequences' ) ) {
			TC_Agent_Sequences::cancel_for_contact( $lead_contact_id );
		}
		$conversation = null;
		if ( $lead_contact_id && class_exists( 'TC_WhatsApp_Hub' ) ) {
			$contact_row = TC_Contact_Service::get( $lead_contact_id );
			if ( $contact_row ) {
				TC_WhatsApp_Hub::on_customer_message( $contact_row, $message_text, $is_new_lead );
			}
			$conversation = TC_WhatsApp_Hub::conversation_for( $sender_phone, $lead_contact_id );
		} elseif ( $lead_contact_id && class_exists( 'TC_CRM_Notify' ) ) {
			TC_CRM_Notify::on_customer_reply( $lead_contact_id, $message_text );
		}

		// Master Automation Kill Switch: If all automations are disabled (Human-Only Mode), never trigger bots or automated greetings
		$disable_all_automations = class_exists( 'TC_Agents_Guardrails' )
			? TC_Agents_Guardrails::is_automation_disabled()
			: ( '1' === (string) get_option( 'tc_agents_disable_all_automations', '0' ) );

		if ( $disable_all_automations ) {
			// Save incoming customer message (or media) to conversation history so human sales rep can see it in CRM & X-Agents
			if ( $conversation ) {
				TC_Agent_Orchestrator::save_message(
					array(
						'conversation_id' => (int) $conversation['id'],
						'role'            => 'user',
						'content'         => ! empty( $message_text ) ? $message_text : ( '[' . ucfirst( $media ?: 'media' ) . ' received]' ),
						'external_id'     => $external_id,
						'media_type'      => $media,
						'media_url'       => $media_url,
						'media_name'      => $media_name,
					)
				);
				TC_Agent_Orchestrator::touch_conversation( (int) $conversation['id'] );
				TC_WhatsApp_Hub::tag_latest( (int) $conversation['id'], 'user', $external_id );
			}
			TC_Agents_Logger::log(
				'automation_kill_switch_held_inbound',
				'info',
				array(
					'phone'     => $sender_phone,
					'has_media' => ! empty( $media ),
					'message'   => mb_substr( $message_text, 0, 100 ),
					'mode'      => '100% Human-Only Mode (All automations paused)',
				),
				'',
				'whatsapp'
			);
			return new WP_REST_Response( array( 'status' => 'stored_for_human_agent', 'auto_reply' => false, 'automations_disabled' => true ), 200 );
		}

		// Photos, voice notes and documents: the AI cannot read them, so keep them for the agent and acknowledge.
		if ( '' !== $media && $conversation ) {
			TC_Agent_Orchestrator::save_message(
				array(
					'conversation_id' => (int) $conversation['id'],
					'role'            => 'user',
					'content'         => $message_text,
					'external_id'     => $external_id,
					'media_type'      => $media,
					'media_url'       => $media_url,
					'media_name'      => $media_name,
				)
			);
			TC_Agent_Orchestrator::touch_conversation( (int) $conversation['id'] );
			if ( TC_WhatsApp_Hub::ai_enabled( $conversation ) ) {
				TC_WhatsApp_Hub::media_ack( $sender_phone );
			}
			return new WP_REST_Response( array( 'status' => 'stored_for_agent', 'media' => $media ), 200 );
		}

		// Auto-Reply Guard: Check if AI auto-reply is enabled globally (default: 0 = disabled / Human Only)
		$auto_reply_enabled = get_option( 'tc_agents_whatsapp_auto_reply', '0' );
		$is_ai_allowed      = ( '1' === (string) $auto_reply_enabled ) && ( ! $conversation || TC_WhatsApp_Hub::ai_enabled( $conversation ) );

		if ( ! $is_ai_allowed ) {
			// Save incoming customer message to the conversation history so human agents see it in X-Agents
			if ( $conversation ) {
				TC_Agent_Orchestrator::save_message(
					array(
						'conversation_id' => (int) $conversation['id'],
						'role'            => 'user',
						'content'         => $message_text,
						'external_id'     => $external_id,
						'media_type'      => $media,
						'media_url'       => $media_url,
						'media_name'      => $media_name,
					)
				);
				TC_Agent_Orchestrator::touch_conversation( (int) $conversation['id'] );
				TC_WhatsApp_Hub::tag_latest( (int) $conversation['id'], 'user', $external_id );
			}

			// 1-Touch Deterministic First Greeting (High-Converting Intake Qualification)
			$first_greet_enabled = get_option( 'tc_agents_whatsapp_first_greeting_enabled', '1' );
			if ( '1' === (string) $first_greet_enabled && $conversation ) {
				$clean_sender_phone = preg_replace( '/\D/', '', $sender_phone );
				$greet_key          = 'tc_wa_first_greet_' . $clean_sender_phone;
				$greet_lock_key     = 'tc_wa_first_greet_lock_' . $clean_sender_phone;

				// Atomic deduplication: if already locked or greeted, skip immediately
				if ( ! get_transient( $greet_key ) && ! get_transient( $greet_lock_key ) ) {
					set_transient( $greet_lock_key, 1, 60 );
					global $wpdb;
					$prior_replies = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}tc_agent_messages WHERE conversation_id = %d AND role IN ('assistant', 'agent')",
							(int) $conversation['id']
						)
					);

					if ( 0 === $prior_replies ) {
						set_transient( $greet_key, 1, DAY_IN_SECONDS );

						$custom_greet = get_option( 'tc_agents_whatsapp_first_greeting_text', '' );
						$first_greeting_text = ! empty( $custom_greet ) ? $custom_greet : (
							"🙏 *Namaste! Welcome to TripCosmos — Varanasi's Premier Pilgrimage & Luxury Fleet Desk.*\n\n"
							. "We have received your inquiry. Our Varanasi pilgrimage desk (+91 93361 16210) is reviewing your requirements.\n\n"
							. "To help us share an instant personalized quote, please reply with your choice:\n"
							. "1️⃣ *Kashi Vishwanath VIP Darshan & Ganga Aarti (3D/2N — Best Seller)*\n"
							. "2️⃣ *Kashi + Ayodhya Ram Mandir Circuit (4D/3N)*\n"
							. "3️⃣ *The Holy Trinity: Varanasi - Prayagraj - Ayodhya (5D/4N)*\n"
							. "4️⃣ *AC Cab Only (Swift Dzire / Innova Crysta / Tempo Traveller)*\n\n"
							. "📅 *Please also share:*\n"
							. "• Expected Travel Dates\n"
							. "• Number of Guests (Adults / Children)\n\n"
							. "Har Har Mahadev! 🕉️"
						);

						$sent_id = self::send_message_ex( $sender_phone, $first_greeting_text, 'inbound_reply' );

						TC_Agent_Orchestrator::save_message(
							array(
								'conversation_id' => (int) $conversation['id'],
								'role'            => 'assistant',
								'content'         => $first_greeting_text,
								'provider_used'   => 'first_auto_greeting',
								'external_id'     => is_string( $sent_id ) ? $sent_id : '',
								'delivery'        => is_string( $sent_id ) ? 'sent' : '',
							)
						);
						TC_Agent_Orchestrator::touch_conversation( (int) $conversation['id'] );
						TC_WhatsApp_Hub::tag_latest( (int) $conversation['id'], 'assistant', is_string( $sent_id ) ? $sent_id : '' );

						return new WP_REST_Response( array( 'status' => 'first_auto_greeting_sent', 'auto_reply' => true ), 200 );
					}
				}
			}

			return new WP_REST_Response( array( 'status' => 'stored_for_human_agent', 'auto_reply' => false ), 200 );
		}

		// Real-time Optimization: Mark read & trigger typing presence simulation while AI processes
		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			if ( ! empty( $external_id ) ) {
				TC_Integration_Evolution::mark_read( $sender_phone, $external_id );
			}
			TC_Integration_Evolution::send_presence( $sender_phone, 'composing', 1800 );
		}

		// Pass to Agent Orchestrator
		$result = TC_Agent_Orchestrator::handle_message(
			$message_text,
			$session_id,
			'whatsapp',
			'tripcosmos-guide',
			array( 'phone' => $sender_phone, 'raw_data' => $data )
		);

		if ( $conversation ) {
			TC_WhatsApp_Hub::tag_latest( (int) $conversation['id'], 'user', $external_id );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'status' => 'error',
					'error'  => $result->get_error_message(),
				),
				200
			);
		}

		$reply_text = $result['reply'] ?? '';
		if ( ! empty( $reply_text ) ) {
			$sent_id = self::send_message_ex( $sender_phone, $reply_text, 'inbound_reply' );
			if ( $conversation && is_string( $sent_id ) ) {
				TC_WhatsApp_Hub::tag_latest( (int) $conversation['id'], 'assistant', $sent_id, 'sent' );
			}
		}

		return new WP_REST_Response( array( 'status' => 'success', 'delivered' => ! empty( $reply_text ) ), 200 );
	}

	/**
	 * Send an outbound WhatsApp message via wa.vmstudio.digital.
	 *
	 * @param string $recipient_phone E.164 or digits.
	 * @param string $message
	 * @param string $context 'conversational' | 'inbound_reply' | 'staff_relay' | 'staff_alert' | 'broadcast'
	 * @return bool
	 */
	public static function send_message( $recipient_phone, $message, $context = 'conversational' ) {
		return false !== self::send_message_ex( $recipient_phone, $message, $context );
	}

	/**
	 * Like send_message(), but returns WhatsApp's message id ('' when the gateway gives none) or false on failure.
	 */
	public static function send_message_ex( $recipient_phone, $message, $context = 'conversational' ) {
		// Guardrails check before any outbound message
		$guard = TC_Agents_Guardrails::check_permission( 'whatsapp', $recipient_phone, $context );
		if ( is_wp_error( $guard ) ) {
			TC_Agents_Logger::log( 'whatsapp_outbound_blocked', 'warning', array( 'reason' => $guard->get_error_message() ), '', 'whatsapp' );
			return false;
		}

		// Evolution API mode (preferred for new setups).
		if ( 'evolution' === get_option( 'tc_agents_whatsapp_mode', 'legacy' ) && class_exists( 'TC_Integration_Evolution' ) ) {
			$res = TC_Integration_Evolution::send_message( $recipient_phone, $message, $context );
			if ( is_wp_error( $res ) ) {
				TC_Agents_Logger::log( 'whatsapp_send_error', 'error', array( 'error' => $res->get_error_message(), 'mode' => 'evolution' ), '', 'whatsapp' );
				return false;
			}
			return (string) ( $res['id'] ?? '' );
		}

		if ( ! self::is_configured() ) {
			TC_Agents_Logger::log( 'whatsapp_not_configured', 'warning', array(), '', 'whatsapp' );
			return false;
		}

		$endpoint = self::get_api_url() . '/api/send';
		$payload  = array(
			'to'      => preg_replace( '/[^0-9]/', '', $recipient_phone ),
			'message' => $message,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . self::get_token(),
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			TC_Agents_Logger::log( 'whatsapp_send_error', 'error', array( 'error' => $response->get_error_message() ), '', 'whatsapp' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			TC_Agents_Guardrails::record_outbound( 'whatsapp' );
			return '';
		}

		TC_Agents_Logger::log( 'whatsapp_send_failed_http', 'warning', array( 'code' => $code ), '', 'whatsapp' );
		return false;
	}

	/**
	 * Ensure contact record is linked to this WhatsApp phone.
	 */
	/**
	 * @return array{0:int,1:bool} Contact id, and whether it was created just now (a brand-new lead).
	 */
	private static function ensure_whatsapp_contact( $phone, $name = '' ) {
		$existing = TC_Contact_Service::find( '', $phone );
		if ( $existing ) {
			return array( (int) $existing['id'], false );
		}
		$res = TC_Contact_Service::upsert(
			array(
				'phone'          => $phone,
				'name'           => $name ?: 'Traveler',
				'source_channel' => 'whatsapp',
			),
			// The WhatsApp hub assigns it and sends one alert that includes the customer's first message.
			array( 'sync' => false, 'auto_assign' => ! class_exists( 'TC_WhatsApp_Hub' ) )
		);
		return array( (int) $res['id'], 'created' === ( $res['status'] ?? '' ) );
	}
}
