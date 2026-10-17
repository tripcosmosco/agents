<?php
/**
 * Central WhatsApp CRM (Superfone-style) on top of the Evolution API.
 *
 * One business number receives every customer. Each new WhatsApp lead is auto-assigned to a team member, who gets
 * the lead and every later customer message on their OWN WhatsApp. The agent answers by swipe-replying to that
 * alert: the reply is relayed to the customer from the business number, stored in the CRM thread, and the AI steps
 * back for that customer. Messages typed on the business phone itself are captured too, so the thread is complete.
 *
 * Staff commands (sent as a swipe-reply to an alert, or prefixed "#<lead id> "):
 *   CLAIM   take an unassigned lead
 *   AI ON   let the AI reply to this customer again
 *   AI OFF  keep the AI quiet for this customer
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_WhatsApp_Hub {

	const ALERT_TTL      = 1209600; // 14 days: how long a swipe-reply to an alert can still reach the customer.
	const MEDIA_ACK_TTL  = 3600;

	private static $staff_cache = null;

	public static function init() {
		add_action( 'admin_post_tc_wa_hub', array( __CLASS__, 'handle_admin_action' ) );
	}

	/* ------------------------------------------------------------------ settings */

	public static function forward_enabled() {
		return '0' !== (string) get_option( 'tc_agents_wa_forward', '1' );
	}

	/* ------------------------------------------------------------------ staff numbers */

	/** Team members with a WhatsApp number on their CRM profile: last-10-digits => user id. */
	public static function staff_numbers() {
		if ( null !== self::$staff_cache ) {
			return self::$staff_cache;
		}
		$desk_num = preg_replace( '/\D/', '', (string) get_option( 'tc_agents_human_whatsapp_number', '919336116210' ) );
		$desk_10  = strlen( $desk_num ) >= 10 ? substr( $desk_num, -10 ) : '9336116210';

		$map = array();
		if ( class_exists( 'TC_CRM_Access' ) ) {
			foreach ( TC_CRM_Access::team() as $u ) {
				$digits = preg_replace( '/\D/', '', (string) get_user_meta( $u->ID, 'tc_crm_whatsapp', true ) );
				if ( strlen( $digits ) >= 10 ) {
					$last10 = substr( $digits, -10 );
					if ( $last10 !== $desk_10 ) {
						$map[ $last10 ] = (int) $u->ID;
					}
				}
			}
		}
		// Also parse manager_phones from option tc_agents_manager_phones (format: Name | 919XXXXXXXXX or just phone)
		$raw_mgrs = (string) get_option( 'tc_agents_manager_phones', '' );
		if ( '' !== $raw_mgrs ) {
			foreach ( preg_split( '/[\r\n]+/', $raw_mgrs ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) { continue; }
				$parts = explode( '|', $line );
				$num   = isset( $parts[1] ) ? trim( $parts[1] ) : trim( $parts[0] );
				$digits = preg_replace( '/\D/', '', $num );
				if ( strlen( $digits ) >= 10 ) {
					$last10 = substr( $digits, -10 );
					if ( $last10 !== $desk_10 ) {
						$map[ $last10 ] = 1; // Map to primary admin
					}
				}
			}
		}
		self::$staff_cache = $map;
		return $map;
	}

	public static function staff_user_for( $digits ) {
		$key = substr( preg_replace( '/\D/', '', (string) $digits ), -10 );
		$map = self::staff_numbers();
		return ( strlen( $key ) === 10 && isset( $map[ $key ] ) ) ? $map[ $key ] : 0;
	}

	/** For tests: forget the per-request staff cache. */
	public static function reset_cache() {
		self::$staff_cache = null;
	}

	/* ------------------------------------------------------------------ conversation helpers */

	public static function conversation_for( $digits, $contact_id = 0 ) {
		$conv = TC_Agent_Orchestrator::get_or_create_conversation( 'wa_' . $digits, 'whatsapp', 'tripcosmos-guide', array( 'phone' => $digits ) );
		if ( $conv && $contact_id && ! (int) $conv['contact_id'] ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'tc_agent_conversations', array( 'contact_id' => (int) $contact_id ), array( 'id' => (int) $conv['id'] ) );
			$conv['contact_id'] = (int) $contact_id;
		}
		return $conv;
	}

	public static function ai_enabled( $conv ) {
		return $conv && 'handed_off' !== $conv['status'];
	}

	public static function set_ai( $conv_id, $enabled ) {
		TC_Agent_Orchestrator::update_conversation_status( (int) $conv_id, $enabled ? 'active' : 'handed_off' );
	}

	/** Records a message a human sent (app, relay, or the business phone) and keeps the AI out of the way. */
	public static function record_agent_message( $contact, $text, $external_id, $via ) {
		$digits = preg_replace( '/\D/', '', (string) $contact['phone'] );
		$conv   = self::conversation_for( $digits, (int) $contact['id'] );
		if ( ! $conv ) {
			return;
		}
		TC_Agent_Orchestrator::save_message(
			array(
				'conversation_id' => (int) $conv['id'],
				'role'            => 'agent',
				'content'         => $text,
				'provider_used'   => 'human:' . $via,
				'external_id'     => $external_id,
				'delivery'        => '' !== (string) $external_id ? 'sent' : '',
			)
		);
		TC_Agent_Orchestrator::touch_conversation( (int) $conv['id'] );
		self::set_ai( (int) $conv['id'], false );
		TC_Contact_Service::add_activity( (int) $contact['id'], 'whatsapp', sprintf( 'WhatsApp sent (%s): %s', $via, wp_trim_words( $text, 40 ) ) );
	}

	/** Sets the WhatsApp id on the newest message of a role that has none (AI replies are saved before sending). */
	public static function tag_latest( $conv_id, $role, $external_id, $delivery = '' ) {
		if ( '' === (string) $external_id ) {
			return;
		}
		global $wpdb;
		$t  = $wpdb->prefix . 'tc_agent_messages';
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE conversation_id = %d AND role = %s AND external_id = '' ORDER BY id DESC LIMIT 1", (int) $conv_id, $role ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $id ) {
			$wpdb->update( $t, array( 'external_id' => substr( (string) $external_id, 0, 100 ), 'delivery' => $delivery ), array( 'id' => $id ) );
		}
	}

	/* ------------------------------------------------------------------ webhook routing */

	/**
	 * Everything that is NOT an ordinary customer text for the AI: receipts, connection changes, our own echoes,
	 * messages typed on the business phone, and staff replies. Returns a response when handled, else null.
	 */
	public static function before_ai( array $data ) {
		$event = strtolower( str_replace( '_', '.', (string) ( $data['event'] ?? '' ) ) );

		if ( 'messages.update' === $event ) {
			$r = TC_Integration_Evolution::normalize_receipt( $data );
			if ( $r ) {
				self::apply_receipt( $r['id'], $r['status'] );
			}
			return new WP_REST_Response( array( 'status' => 'receipt' ), 200 );
		}
		if ( 'connection.update' === $event ) {
			self::on_connection( (string) ( $data['data']['state'] ?? '' ) );
			return new WP_REST_Response( array( 'status' => 'connection' ), 200 );
		}
		if ( '' !== $event && 'messages.upsert' !== $event ) {
			return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'event ' . $event ), 200 );
		}

		$n = TC_Integration_Evolution::normalize_inbound( $data );
		if ( $n['ignored_jid'] ) {
			return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Status broadcast or group message ignored' ), 200 );
		}

		if ( $n['from_me'] ) {
			if ( TC_Integration_Evolution::is_our_outbound( $n['id'] ) || '' === trim( $n['text'] ) || '' === $n['from'] || self::staff_user_for( $n['from'] ) ) {
				return new WP_REST_Response( array( 'status' => 'ignored', 'reason' => 'Outbound message from self (fromMe)' ), 200 );
			}
			// Someone typed on the business phone: keep it in the CRM thread and step the AI back.
			$contact = TC_Contact_Service::find( '', $n['from'] );
			if ( ! $contact ) {
				$res     = TC_Contact_Service::upsert( array( 'phone' => $n['from'], 'name' => 'Traveler', 'source_channel' => 'whatsapp' ), array( 'sync' => false ) );
				$contact = $res['contact'];
			}
			if ( $contact ) {
				self::record_agent_message( $contact, $n['text'], $n['id'], 'business phone' );
			}
			return new WP_REST_Response( array( 'status' => 'recorded_from_business_phone' ), 200 );
		}

		$staff = self::staff_user_for( $n['from'] );
		if ( $staff ) {
			return self::handle_staff_message( $staff, $n );
		}
		return null;
	}

	public static function apply_receipt( $external_id, $status ) {
		global $wpdb;
		$t    = $wpdb->prefix . 'tc_agent_messages';
		$rank = array( '' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3 );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT id, delivery FROM $t WHERE external_id = %s LIMIT 1", (string) $external_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return false;
		}
		// Receipts can arrive out of order; never move backwards (read stays read), but a failure always shows.
		if ( 'failed' !== $status && ( $rank[ $row['delivery'] ] ?? 0 ) >= ( $rank[ $status ] ?? 0 ) ) {
			return false;
		}
		$wpdb->update( $t, array( 'delivery' => $status ), array( 'id' => (int) $row['id'] ) );
		return true;
	}

	private static function on_connection( $state ) {
		$prev = (string) get_option( 'tc_agents_wa_state', '' );
		update_option( 'tc_agents_wa_state', sanitize_key( $state ), false );
		update_option( 'tc_agents_wa_state_at', current_time( 'mysql' ), false );
		if ( 'close' === $state && 'close' !== $prev && class_exists( 'TC_CRM_Notify' ) ) {
			// WhatsApp is down, so this reaches managers by email.
			TC_CRM_Notify::send_managers( __( 'WhatsApp disconnected', 'tripcosmos-agents' ), __( 'The business WhatsApp number was logged out of Evolution. Customers cannot reach you until it is reconnected (Integrations > WhatsApp > Show QR).', 'tripcosmos-agents' ) );
		}
		TC_Agents_Logger::log( 'whatsapp_connection', 'close' === $state ? 'warning' : 'info', array( 'state' => $state, 'previous' => $prev ), '', 'whatsapp' );
	}

	/* ------------------------------------------------------------------ customer side */

	/**
	 * After the webhook has found/created the customer's contact. Assigns new leads and forwards the message to the
	 * owner's WhatsApp (or to managers while unassigned).
	 */
	public static function on_customer_message( array $contact, $text, $is_new ) {
		$id = (int) $contact['id'];
		if ( $is_new && ! (int) $contact['owner_user_id'] ) {
			TC_Contact_Service::auto_assign( $id, false );
			$contact = TC_Contact_Service::get( $id ) ?: $contact;
		}
		if ( ! self::forward_enabled() ) {
			if ( class_exists( 'TC_CRM_Notify' ) ) {
				TC_CRM_Notify::on_customer_reply( $id, $text );
			}
			return;
		}
		// A customer who floods the chat still cannot flood the agent's phone.
		if ( ! TC_Agents_Security::rate_limit( 'wa_forward', (string) $id, 40, HOUR_IN_SECONDS ) ) {
			return;
		}
		if ( '' === trim( (string) $text ) ) {
			return;
		}

		$owner = (int) $contact['owner_user_id'];
		$name  = $contact['name'] ?: 'Traveler';
		$quote = '“' . wp_trim_words( wp_strip_all_tags( (string) $text ), 60 ) . '”';
		$phone = self::pretty_phone( (string) $contact['phone'] );

		if ( $owner ) {
			if ( $is_new ) {
				$new_lead_key = 'tc_wa_new_lead_alert_' . $id;
				if ( get_transient( $new_lead_key ) ) {
					return;
				}
				set_transient( $new_lead_key, 1, 60 );
			}
			$head = $is_new ? "🟢 *New WhatsApp lead* · #{$id}" : "💬 *{$name}* · #{$id}";
			$body = $is_new ? "{$head}\n*{$name}* · {$phone}\n{$quote}" : "{$head}\n{$quote}";
			$body .= "\n\n↩️ Swipe-reply to answer from the TripCosmos number.";
			self::alert( $owner, $body, $id );
			return;
		}
		// Unassigned: debounce so managers are not spammed with repetitive "Reply CLAIM" cards for every message
		$debounced_key = 'tc_wa_unassigned_alert_' . $id;
		$already_sent  = (bool) get_transient( $debounced_key );

		if ( ! $already_sent ) {
			set_transient( $debounced_key, 1, 15 * MINUTE_IN_SECONDS );
			foreach ( TC_CRM_Access::team( TC_CRM_Access::CAP_MANAGE ) as $m ) {
				self::alert( (int) $m->ID, "🟡 *Unassigned WhatsApp lead* · #{$id}\n*{$name}* · {$phone}\n{$quote}\n\n↩️ Reply *CLAIM* to take it, or swipe-reply to answer.", $id );
			}
		} else {
			// Subdued compact snippet throttled so managers aren't pinged for rapid-fire typing
			$sub_debounce_key = 'tc_wa_unassigned_sub_' . $id;
			if ( ! get_transient( $sub_debounce_key ) ) {
				set_transient( $sub_debounce_key, 1, 3 * MINUTE_IN_SECONDS );
				foreach ( TC_CRM_Access::team( TC_CRM_Access::CAP_MANAGE ) as $m ) {
					self::alert( (int) $m->ID, "💬 *#{$id} {$name}:* {$quote}", $id );
				}
			}
		}
	}

	/** Sends an alert to a staff member's own WhatsApp and remembers which lead it was about. */
	public static function alert( $user_id, $text, $contact_id ) {
		$wa = preg_replace( '/\D/', '', (string) get_user_meta( (int) $user_id, 'tc_crm_whatsapp', true ) );
		// Ensure Nishkarsh receives alerts on his personal operational number (918433284773)
		if ( empty( $wa ) && ( (int) $user_id === 73657 || ( ( $u = get_userdata( (int) $user_id ) ) && stripos( $u->user_email, 'nishkarsh' ) !== false ) ) ) {
			$wa = '918433284773';
		}
		if ( strlen( $wa ) < 10 || ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::is_away( (int) $user_id ) ) ) {
			return false;
		}

		// Desk number guard: Never send staff alert to the business desk itself
		$desk_num = preg_replace( '/\D/', '', (string) get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( empty( $desk_num ) ) {
			$desk_num = '919336116210';
		}
		if ( substr( $wa, -10 ) === substr( $desk_num, -10 ) ) {
			return false;
		}

		// Security & UX Guard: Never send staff alerts back to the contact's own phone (e.g. staff testing as customer)
		if ( $contact_id ) {
			$contact = class_exists( 'TC_Contact_Service' ) ? TC_Contact_Service::get( (int) $contact_id ) : null;
			if ( $contact && ! empty( $contact['phone'] ) ) {
				$c_digits = preg_replace( '/\D/', '', (string) $contact['phone'] );
				if ( substr( $c_digits, -10 ) === substr( $wa, -10 ) ) {
					return false;
				}
			}
		}

		// Deduplication Guard: Never dispatch identical alert to the same staff WhatsApp within 15 seconds
		$alert_hash = md5( $wa . '_' . trim( (string) $text ) );
		$alert_key  = 'tc_wa_alert_dedup_' . $alert_hash;
		if ( get_transient( $alert_key ) ) {
			return true;
		}
		set_transient( $alert_key, 1, 15 );

		$msg_id = TC_Integration_WhatsApp::send_message_ex( $wa, $text, 'staff_alert' );
		if ( false === $msg_id ) {
			return false;
		}
		if ( '' !== $msg_id ) {
			set_transient( 'tc_wa_alert_' . md5( $msg_id ), (int) $contact_id, self::ALERT_TTL );
			set_transient( 'tc_wa_last_alert_' . substr( $wa, -10 ), (int) $contact_id, DAY_IN_SECONDS );
		}
		return true;
	}

	/** Holding reply for voice notes / photos the AI cannot read, at most once an hour per customer. */
	public static function media_ack( $digits ) {
		$key = 'tc_wa_media_ack_' . substr( md5( (string) $digits ), 0, 20 );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, self::MEDIA_ACK_TTL );
		TC_Integration_WhatsApp::send_message( $digits, __( 'Thank you! 🙏 A TripCosmos travel specialist will check this and reply shortly.', 'tripcosmos-agents' ) );
	}

	/* ------------------------------------------------------------------ staff side */

	/** Which lead a staff message is about: the alert it quotes, or an explicit "#123" prefix. Returns [contact_id, text]. */
	public static function target_of( array $n ) {
		$text = trim( (string) $n['text'] );
		if ( '' !== (string) ( $n['quoted_id'] ?? '' ) ) {
			$cid = (int) get_transient( 'tc_wa_alert_' . md5( $n['quoted_id'] ) );
			if ( $cid ) {
				return array( $cid, $text );
			}
		}
		// Match "#123 text"
		if ( preg_match( '/^#(\d{1,9})\s*(.*)$/s', $text, $m ) ) {
			return array( (int) $m[1], trim( $m[2] ) );
		}
		// Match "CLAIM #123" or "CLAIM 123"
		if ( preg_match( '/^CLAIM\s*#?(\d{1,9})$/i', $text, $m ) ) {
			return array( (int) $m[1], 'CLAIM' );
		}
		// Match plain "CLAIM"
		if ( 0 === strcasecmp( $text, 'CLAIM' ) ) {
			$clean_from = substr( preg_replace( '/\D/', '', (string) $n['from'] ), -10 );
			$last_cid   = (int) get_transient( 'tc_wa_last_alert_' . $clean_from );
			if ( $last_cid ) {
				return array( $last_cid, 'CLAIM' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'tc_agent_contacts';
			$recent_unassigned = $wpdb->get_var( "SELECT id FROM {$table} WHERE owner_user_id = 0 ORDER BY id DESC LIMIT 1" );
			if ( $recent_unassigned ) {
				return array( (int) $recent_unassigned, 'CLAIM' );
			}
		}
		return array( 0, $text );
	}

	public static function handle_staff_message( $user_id, array $n ) {
		$reply = function ( $text ) use ( $n ) {
			TC_Integration_WhatsApp::send_message( $n['from'], $text );
			return new WP_REST_Response( array( 'status' => 'staff', 'reply' => $text ), 200 );
		};

		list( $cid, $text ) = self::target_of( $n );
		if ( ! $cid ) {
			return $reply( "To answer a customer, swipe-reply to their alert, or start your message with the lead number, e.g. *#12 Namaste…*" );
		}
		$contact = TC_Contact_Service::get( $cid );
		if ( ! $contact || '' === (string) $contact['phone'] ) {
			return $reply( "Lead #{$cid} was not found." );
		}
		$name    = $contact['name'] ?: 'Traveler';
		$owner   = (int) $contact['owner_user_id'];
		$manager = user_can( $user_id, TC_CRM_Access::CAP_VIEW_ALL );
		$command = strtoupper( trim( $text ) );

		if ( ! $manager && $owner && $owner !== (int) $user_id ) {
			$other = get_userdata( $owner );
			return $reply( sprintf( '%s is handled by %s. Ask a manager to reassign it.', $name, $other ? $other->display_name : 'another agent' ) );
		}

		if ( 'CLAIM' === $command ) {
			if ( $owner && $owner !== (int) $user_id && ! $manager ) {
				return $reply( "{$name} is already assigned." );
			}
			TC_Contact_Service::set_owner( $cid, (int) $user_id, false );
			return $reply( "✓ {$name} (#{$cid}) is now yours." );
		}
		$conv = self::conversation_for( preg_replace( '/\D/', '', (string) $contact['phone'] ), $cid );
		if ( 'AI ON' === $command || 'AI OFF' === $command ) {
			self::set_ai( (int) $conv['id'], 'AI ON' === $command );
			return $reply( 'AI ON' === $command ? "✓ The AI will reply to {$name} again." : "✓ The AI will stay quiet for {$name}." );
		}

		if ( 'STATUS' === $command || 'INFO' === $command ) {
			$stage_label = TC_Contact_Service::stages()[ $contact['stage'] ] ?? $contact['stage'];
			$deal_str    = (float) ( $contact['deal_value'] ?? 0 ) > 0 ? '₹' . number_format( (float) $contact['deal_value'] ) : 'Not set';
			$ai_st       = self::ai_enabled( $conv ) ? '🟢 Active' : '⏸️ Paused (Staff handling)';
			$owner_name  = ! empty( $contact['owner'] ) ? $contact['owner'] : ( $owner ? ( ( $u = get_userdata( $owner ) ) ? $u->display_name : 'Agent #' . $owner ) : 'Unassigned' );
			$msg = "📋 *Lead Brief #{$cid}*\n"
				. "• *Traveler:* {$name} (" . self::pretty_phone( (string) $contact['phone'] ) . ")\n"
				. "• *Stage:* {$stage_label}\n"
				. "• *Deal Value:* {$deal_str}\n"
				. "• *Assigned Agent:* {$owner_name}\n"
				. "• *AI Assistant:* {$ai_st}\n\n"
				. "⚡ Commands:\n"
				. "• *STAGE WON* / *STAGE LOST [reason]*\n"
				. "• *STAGE QUOTE* / *STAGE QUALIFIED*\n"
				. "• *NOTE <internal note>*\n"
				. "• *AI ON* / *AI OFF*\n"
				. "• Or swipe-reply to message traveler directly.";
			return $reply( $msg );
		}

		if ( 0 === strpos( $command, 'STAGE ' ) || in_array( $command, array( 'WON', 'LOST' ), true ) ) {
			$stage_arg = trim( substr( $text, 0 === strpos( $command, 'STAGE ' ) ? 6 : 0 ) );
			$parts     = explode( ' ', $stage_arg, 2 );
			$target    = strtolower( trim( $parts[0] ) );
			$reason    = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			$alias_map = array(
				'won'       => 'won',
				'lost'      => 'lost',
				'quote'     => 'proposal',
				'proposal'  => 'proposal',
				'followup'  => 'qualified',
				'qualified' => 'qualified',
				'inquiry'   => 'inquiry',
				'completed' => 'completed',
			);
			$final_stage = $alias_map[ $target ] ?? ( isset( TC_Contact_Service::stages()[ $target ] ) ? $target : '' );
			if ( $final_stage ) {
				TC_Contact_Service::set_stage( $cid, $final_stage, $reason );
				$st_name = TC_Contact_Service::stages()[ $final_stage ] ?? $final_stage;
				return $reply( "✓ Lead #{$cid} ({$name}) updated to stage: *{$st_name}*" . ( $reason ? " ({$reason})" : '' ) . "." );
			}
			return $reply( "Unknown stage '{$target}'. Valid: WON, LOST, QUOTE, QUALIFIED, COMPLETED." );
		}

		if ( 0 === strpos( $command, 'NOTE ' ) ) {
			$note_text = trim( substr( $text, 5 ) );
			if ( ! empty( $note_text ) ) {
				$u = get_userdata( $user_id );
				TC_Contact_Service::add_activity( $cid, 'note', $note_text . ' (via WhatsApp by ' . ( $u ? $u->display_name : 'staff' ) . ')' );
				return $reply( "✓ Internal note saved for #{$cid} ({$name})." );
			}
		}

		if ( '' === $text ) {
			return $reply( 'Only text can be forwarded to the customer. Send photos and documents from the business phone or the app.' );
		}
		if ( ! TC_Contact_Service::can_contact( $contact, 'whatsapp' ) ) {
			return $reply( "{$name} has opted out of WhatsApp messages." );
		}
		if ( ! TC_Agents_Security::rate_limit( 'wa_relay', (string) $user_id, 120, HOUR_IN_SECONDS ) ) {
			return $reply( 'Too many messages in a short time. Try again later.' );
		}
		if ( ! $owner ) {
			// Answering an unassigned lead claims it.
			TC_Contact_Service::set_owner( $cid, (int) $user_id, false );
		}

		$sent = TC_Integration_WhatsApp::send_message_ex( $contact['phone'], $text, 'staff_relay' );
		if ( false === $sent ) {
			return $reply( "✗ Could not deliver to {$name}. Check the WhatsApp connection." );
		}
		$u = get_userdata( $user_id );
		self::record_agent_message( $contact, $text, (string) $sent, $u ? $u->display_name : 'staff' );
		TC_Agents_Logger::log( 'whatsapp_relay', 'info', array( 'lead_id' => $cid, 'user_id' => (int) $user_id ), '', 'whatsapp' );
		return new WP_REST_Response( array( 'status' => 'relayed', 'lead_id' => $cid ), 200 );
	}

	private static function pretty_phone( $raw ) {
		$d = preg_replace( '/\D/', '', $raw );
		return ( 12 === strlen( $d ) && 0 === strpos( $d, '91' ) ) ? '+91 ' . substr( $d, 2, 5 ) . ' ' . substr( $d, 7 ) : $raw;
	}

	/* ------------------------------------------------------------------ admin: connect the Evolution instance */

	public static function handle_admin_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'tripcosmos-agents' ), 403 );
		}
		check_admin_referer( 'tc_wa_hub' );
		$op     = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$result = array( 'op' => $op, 'ok' => false, 'message' => '' );

		if ( 'forward' === $op ) {
			update_option( 'tc_agents_wa_forward', empty( $_POST['forward'] ) ? '0' : '1', false );
			$result = array( 'op' => $op, 'ok' => true, 'message' => __( 'Saved.', 'tripcosmos-agents' ) );
		} elseif ( 'webhook' === $op ) {
			$r      = TC_Integration_Evolution::register_webhook( rest_url( 'tc-agents/v1/whatsapp-webhook' ), self::webhook_secret() );
			$result = is_wp_error( $r ) ? array( 'op' => $op, 'ok' => false, 'message' => $r->get_error_message() ) : array( 'op' => $op, 'ok' => true, 'message' => __( 'Webhook registered. New WhatsApp messages will now reach the CRM.', 'tripcosmos-agents' ) );
			if ( ! is_wp_error( $r ) ) {
				update_option( 'tc_agents_whatsapp_mode', 'evolution' );
			}
		} elseif ( 'qr' === $op ) {
			$r      = TC_Integration_Evolution::connect_qr();
			$result = is_wp_error( $r ) ? array( 'op' => $op, 'ok' => false, 'message' => $r->get_error_message() ) : array( 'op' => $op, 'ok' => true, 'qr' => $r, 'message' => __( 'Scan this with WhatsApp on the business phone: Settings > Linked devices > Link a device.', 'tripcosmos-agents' ) );
		} else {
			$r      = TC_Integration_Evolution::connection_state();
			$result = is_wp_error( $r ) ? array( 'op' => 'check', 'ok' => false, 'message' => $r->get_error_message() ) : array( 'op' => 'check', 'ok' => 'open' === $r, 'message' => 'open' === $r ? __( 'Connected. The business number is online.', 'tripcosmos-agents' ) : sprintf( __( 'Not connected (state: %s). Use "Show QR" to link the business phone.', 'tripcosmos-agents' ), $r ) );
			if ( ! is_wp_error( $r ) ) {
				update_option( 'tc_agents_wa_state', sanitize_key( $r ), false );
			}
		}
		set_transient( 'tc_wa_hub_result_' . get_current_user_id(), $result, 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=tc-agents-integrations#tc-wa-hub' ) );
		exit;
	}

	/** The secret the webhook handler checks (created on first use). */
	public static function webhook_secret() {
		$secret = (string) get_option( 'tc_agents_whatsapp_webhook_secret', '' );
		if ( '' === $secret ) {
			$secret = wp_generate_password( 32, false );
			update_option( 'tc_agents_whatsapp_webhook_secret', $secret, false );
		}
		return $secret;
	}

	public static function render_admin_panel() {
		$result = get_transient( 'tc_wa_hub_result_' . get_current_user_id() );
		if ( $result ) {
			delete_transient( 'tc_wa_hub_result_' . get_current_user_id() );
		}
		$state      = (string) get_option( 'tc_agents_wa_state', '' );
		$configured = TC_Integration_Evolution::is_configured();
		$staff      = self::staff_numbers();
		$form       = function ( $op, $label, $primary = false ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0;">';
			echo '<input type="hidden" name="action" value="tc_wa_hub" /><input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
			wp_nonce_field( 'tc_wa_hub' );
			echo '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
		};
		?>
		<div class="wrap" id="tc-wa-hub" style="max-width: 900px; margin-top: 24px;">
			<h2>Central WhatsApp CRM (Evolution)</h2>
			<p class="description">One business number for every customer. New WhatsApp leads are assigned to the team automatically, and each agent receives their leads on their own WhatsApp. Agents answer by swipe-replying to the alert; the reply goes out from the business number and is saved in the CRM.</p>

			<?php if ( $result ) : ?>
				<div class="notice <?php echo ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $result['message'] ); ?></p>
				<?php if ( ! empty( $result['qr'] ) && 0 === strpos( $result['qr'], 'data:image/png;base64,' ) ) : ?>
					<p><img src="<?php echo esc_attr( $result['qr'] ); ?>" alt="WhatsApp link QR code" width="264" height="264" /></p>
				<?php endif; ?>
				</div>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr><th scope="row">Server</th><td><code><?php echo esc_html( TC_Integration_Evolution::get_base_url() ); ?></code> · instance <code><?php echo esc_html( TC_Integration_Evolution::get_instance() ); ?></code><?php echo $configured ? '' : ' <strong style="color:#b91c1c;">API key missing: add it above and save.</strong>'; ?></td></tr>
				<tr><th scope="row">Status</th><td><?php
					if ( $configured ) {
						$live_st = TC_Integration_Evolution::connection_state();
						if ( ! is_wp_error( $live_st ) ) {
							$state = sanitize_key( $live_st );
							update_option( 'tc_agents_wa_state', $state, false );
						}
					}
					if ( 'open' === $state ) {
						echo '<span style="color:#10b981;font-weight:bold;">🟢 Connected</span> (WhatsApp web socket active)';
					} else {
						echo '<span style="color:#ef4444;font-weight:bold;">🔴 Not connected (' . esc_html( $state ?: 'unknown' ) . ')</span> — <em style="color:#64748b;">Session unlinked on server. Click "Show QR to link phone" below to scan pairing QR code.</em>';
					}
				?></td></tr>
				<tr><th scope="row">Team numbers</th><td><?php echo esc_html( count( $staff ) ); ?> team member(s) have a WhatsApp number. Add numbers in <a href="<?php echo esc_url( class_exists( 'TC_Contacts_Admin' ) ? TC_Contacts_Admin::url( array( 'view' => 'team' ) ) : admin_url( 'admin.php' ) ); ?>">Contacts › Team</a>; only those people receive leads on WhatsApp.</td></tr>
			</table>
			<?php if ( $configured ) : ?>
				<p>
				<?php
				$form( 'check', 'Check connection' );
				$form( 'webhook', 'Connect webhook to this site', true );
				$form( 'qr', 'Show QR to link phone' );
				?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tc_wa_hub" /><input type="hidden" name="op" value="forward" />
				<?php wp_nonce_field( 'tc_wa_hub' ); ?>
				<label><input type="checkbox" name="forward" value="1" <?php checked( self::forward_enabled() ); ?> /> Send each customer message to the assigned agent's WhatsApp (unassigned leads go to managers)</label>
				<button type="submit" class="button">Save</button>
			</form>
		</div>
		<?php
	}
}
