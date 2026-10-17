<?php
/**
 * Payments for bookings: Cashfree Payment Links (automatic), UPI to the business VPA and manual receipts.
 *
 * A payment is only ever marked paid after a server-to-server check with Cashfree (webhooks and the
 * customer's return trip just trigger that check), and marking paid is idempotent.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Payments {

	const API_VERSION = '2022-09-01';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		// Safety net: re-check open links every 5 minutes (shares the CRM alerts schedule).
		add_action( 'tc_crm_alerts_tick', array( __CLASS__, 'reconcile_open' ) );
	}

	/**
	 * Re-check up to 15 open Cashfree links from the last 8 days, each at most every 10 minutes.
	 */
	public static function reconcile_open() {
		global $wpdb;
		if ( ! self::cashfree_ready() ) {
			return;
		}
		$now  = strtotime( current_time( 'mysql' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE provider = 'cashfree' AND status = 'created' AND provider_link_id <> '' AND created_at >= %s AND updated_at <= %s ORDER BY updated_at ASC LIMIT 15", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				gmdate( 'Y-m-d H:i:s', $now - 8 * DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', $now - 10 * MINUTE_IN_SECONDS )
			),
			ARRAY_A
		) ?: array();
		foreach ( $rows as $p ) {
			self::reconcile( $p );
		}
	}

	public static function routes() {
		register_rest_route(
			'tc-agents/v1',
			'/cashfree-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => '__return_true', // authenticated by signature + server-side re-check
			)
		);
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_payments';
	}

	/* ------------------------------------------------------------------ Cashfree credentials */

	/**
	 * @return array{app_id:string,secret:string,sandbox:bool,source:string}
	 */
	public static function cashfree_credentials() {
		$set = (array) get_option( 'tc_agents_billing', array() );
		if ( ! isset( $set['cf_use_woo'] ) || ! empty( $set['cf_use_woo'] ) ) {
			$woo = get_option( 'woocommerce_cashfree_settings' );
			if ( is_array( $woo ) && ! empty( $woo['app_id'] ) && ! empty( $woo['secret_key'] ) ) {
				return array( 'app_id' => (string) $woo['app_id'], 'secret' => (string) $woo['secret_key'], 'sandbox' => 'yes' === ( $woo['sandbox'] ?? 'no' ), 'source' => 'woocommerce' );
			}
		}
		return array(
			'app_id'  => (string) TC_Agents_Vault::get( 'cashfree_app_id' ),
			'secret'  => (string) TC_Agents_Vault::get( 'cashfree_secret' ),
			'sandbox' => ! empty( $set['cf_sandbox'] ),
			'source'  => 'tripcosmos',
		);
	}

	public static function cashfree_ready() {
		$c = self::cashfree_credentials();
		return '' !== $c['app_id'] && '' !== $c['secret'];
	}

	private static function cf_request( $method, $path, $body = null ) {
		$c    = self::cashfree_credentials();
		$base = $c['sandbox'] ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'x-client-id'     => $c['app_id'],
				'x-client-secret' => $c['secret'],
				'x-api-version'   => self::API_VERSION,
				'Content-Type'    => 'application/json',
				'Accept'          => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( $base . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true ) ?: array();
		if ( $code >= 300 ) {
			return new WP_Error( 'cashfree_' . $code, (string) ( $data['message'] ?? 'Cashfree error ' . $code ) );
		}
		return $data;
	}

	/* ------------------------------------------------------------------ Links */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function for_booking( $booking_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_id = %d ORDER BY id DESC', (int) $booking_id ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function booking( $booking_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", (int) $booking_id ), ARRAY_A ) ?: null;
	}

	/**
	 * Total due for a booking = governing quote total (or the booking's quote amount).
	 */
	public static function amounts( $booking_id ) {
		$b     = self::booking( $booking_id );
		$q     = TC_Quote_Service::current_for_booking( $booking_id );
		$total = $q ? (float) $q['total'] : (float) ( $b['quote_amount'] ?? 0 );
		$paid  = (float) ( $b['advance_paid'] ?? 0 );
		$adv   = $q ? (float) $q['advance_amount'] : 0;
		return array(
			'total'       => $total,
			'paid'        => $paid,
			'balance'     => max( 0, round( $total - $paid, 2 ) ),
			'advance_due' => max( 0, round( $adv - $paid, 2 ) ),
		);
	}

	/**
	 * Create (or reuse) a Cashfree payment link for an amount on a booking.
	 *
	 * @return array|WP_Error Payment row.
	 */
	public static function create_cashfree_link( $booking_id, $amount, $purpose = '' ) {
		global $wpdb;
		$amount  = round( (float) $amount, 2 );
		$booking = self::booking( $booking_id );
		if ( ! $booking || $amount < 1 ) {
			return new WP_Error( 'invalid', __( 'Nothing to pay.', 'tripcosmos-agents' ) );
		}
		if ( ! self::cashfree_ready() ) {
			return new WP_Error( 'not_configured', __( 'Cashfree is not configured.', 'tripcosmos-agents' ) );
		}
		// Reuse an open link for the same amount instead of spamming the customer with new ones.
		$open = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE booking_id = %d AND provider = 'cashfree' AND status = 'created' AND amount = %f AND created_at >= %s ORDER BY id DESC LIMIT 1", (int) $booking_id, $amount, gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 6 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $open && $open['link_url'] ) {
			return $open;
		}

		$contact   = TC_Contact_Service::get( (int) $booking['contact_id'] );
		$raw_phone = ! empty( $contact['phone_e164'] ) ? $contact['phone_e164'] : ( $contact['phone'] ?? '' );
		$phone10   = substr( preg_replace( '/\D/', '', (string) $raw_phone ), -10 );
		if ( 10 !== strlen( $phone10 ) ) {
			return new WP_Error( 'no_phone', __( 'Cashfree needs a valid 10-digit mobile number on the contact.', 'tripcosmos-agents' ) );
		}
		$quote = TC_Quote_Service::current_for_booking( $booking_id );

		$wpdb->insert(
			self::table(),
			array(
				'booking_id' => (int) $booking_id,
				'quote_id'   => $quote ? (int) $quote['id'] : 0,
				'contact_id' => (int) $booking['contact_id'],
				'amount'     => $amount,
				'provider'   => 'cashfree',
				'status'     => 'created',
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
		$pid     = (int) $wpdb->insert_id;
		$link_id = 'tc_' . (int) $booking_id . '_' . $pid . '_' . wp_rand( 1000, 9999 );
		$return  = $quote ? add_query_arg( 'tc_paid', $pid, TC_Quote_Service::url( $quote ) ) : home_url( '/' );

		$body = array(
			'link_id'               => $link_id,
			'link_amount'           => $amount,
			'link_currency'         => 'INR',
			'link_purpose'          => substr( $purpose ?: sprintf( 'TripCosmos booking %s', $booking['ref'] ), 0, 500 ),
			'customer_details'      => array_filter(
				array(
					'customer_phone' => $phone10,
					'customer_name'  => substr( (string) ( $contact['name'] ?? '' ), 0, 100 ),
					'customer_email' => TC_Contact_Service::is_real_email( $contact['email'] ?? '' ) ? $contact['email'] : null,
				)
			),
			'link_partial_payments' => false,
			'link_notify'           => array( 'send_sms' => false, 'send_email' => false ),
			'link_expiry_time'      => gmdate( 'Y-m-d\TH:i:s\Z', time() + 7 * DAY_IN_SECONDS ),
			'link_meta'             => array( 'return_url' => $return, 'notify_url' => rest_url( 'tc-agents/v1/cashfree-webhook' ) ),
			'link_notes'            => array( 'booking_ref' => (string) $booking['ref'], 'payment_id' => (string) $pid ),
		);
		$res = self::cf_request( 'POST', '/links', $body );
		if ( is_wp_error( $res ) || empty( $res['link_url'] ) ) {
			$err = is_wp_error( $res ) ? $res->get_error_message() : 'No link returned';
			$wpdb->update( self::table(), array( 'status' => 'failed', 'meta' => wp_json_encode( array( 'error' => $err ) ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $pid ) );
			TC_Agents_Logger::log( 'cashfree_link_failed', 'error', array( 'booking_id' => $booking_id, 'error' => $err ) );
			return new WP_Error( 'cashfree', sprintf( __( 'Cashfree could not create the link: %s', 'tripcosmos-agents' ), $err ) );
		}
		$wpdb->update( self::table(), array( 'provider_link_id' => $link_id, 'link_url' => esc_url_raw( $res['link_url'] ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $pid ) );
		TC_Contact_Service::add_activity( (int) $booking['contact_id'], 'booking', sprintf( 'Payment link created for %s (%s)', TC_Quote_Service::money( $amount ), $booking['ref'] ) );
		return self::get( $pid );
	}

	/**
	 * Ask Cashfree for the real status of a link and apply it.
	 *
	 * @return string Resulting status.
	 */
	public static function reconcile( array $p ) {
		global $wpdb;
		if ( 'cashfree' !== $p['provider'] || 'created' !== $p['status'] || '' === $p['provider_link_id'] ) {
			return $p['status'];
		}
		$res = self::cf_request( 'GET', '/links/' . rawurlencode( $p['provider_link_id'] ) );
		if ( is_wp_error( $res ) ) {
			return $p['status'];
		}
		$status = strtoupper( (string) ( $res['link_status'] ?? '' ) );
		if ( 'PAID' === $status ) {
			$amt = (float) ( $res['link_amount_paid'] ?? $p['amount'] );
			self::mark_paid( (int) $p['id'], $amt > 0 ? $amt : (float) $p['amount'], 'cashfree', (string) $p['provider_link_id'] );
			return 'paid';
		}
		if ( in_array( $status, array( 'EXPIRED', 'CANCELLED' ), true ) ) {
			$wpdb->update( self::table(), array( 'status' => strtolower( $status ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $p['id'] ) );
			return strtolower( $status );
		}
		$wpdb->update( self::table(), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $p['id'] ) ); // last checked
		return 'created';
	}

	/**
	 * Cashfree webhook. The signature is checked, then the link is re-fetched server-to-server;
	 * the payload itself is never trusted to mark anything paid.
	 */
	public static function webhook( WP_REST_Request $request ) {
		global $wpdb;
		$raw = (string) $request->get_body();
		$ts  = (string) $request->get_header( 'x-webhook-timestamp' );
		$sig = (string) $request->get_header( 'x-webhook-signature' );
		$c   = self::cashfree_credentials();
		if ( '' !== $sig && '' !== $c['secret'] ) {
			$expected = base64_encode( hash_hmac( 'sha256', $ts . $raw, $c['secret'], true ) );
			if ( ! hash_equals( $expected, $sig ) ) {
				TC_Agents_Logger::log( 'cashfree_webhook_bad_signature', 'warning', array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ) );
				return new WP_REST_Response( array( 'ok' => false ), 401 );
			}
		}
		$link_id = self::find_key( json_decode( $raw, true ) ?: array(), 'link_id' );
		if ( $link_id ) {
			$p = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE provider_link_id = %s', $link_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $p ) {
				self::reconcile( $p );
			}
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private static function find_key( $arr, $key ) {
		if ( ! is_array( $arr ) ) {
			return '';
		}
		foreach ( $arr as $k => $v ) {
			if ( $k === $key && is_scalar( $v ) ) {
				return (string) $v;
			}
			if ( is_array( $v ) ) {
				$found = self::find_key( $v, $key );
				if ( '' !== $found ) {
					return $found;
				}
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ Manual / UPI */

	/**
	 * Staff records a payment received outside the gateway (UPI, cash, bank transfer).
	 */
	public static function record_manual( $booking_id, $amount, $method, $reference = '' ) {
		global $wpdb;
		$b = self::booking( $booking_id );
		if ( ! $b || (float) $amount <= 0 ) {
			return false;
		}
		$q = TC_Quote_Service::current_for_booking( $booking_id );
		$wpdb->insert(
			self::table(),
			array(
				'booking_id' => (int) $booking_id,
				'quote_id'   => $q ? (int) $q['id'] : 0,
				'contact_id' => (int) $b['contact_id'],
				'amount'     => round( (float) $amount, 2 ),
				'provider'   => 'manual',
				'status'     => 'created',
				'method'     => sanitize_key( $method ),
				'reference'  => sanitize_text_field( $reference ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
		return self::mark_paid( (int) $wpdb->insert_id, (float) $amount, sanitize_key( $method ), sanitize_text_field( $reference ) );
	}

	public static function upi_link( $amount, $note ) {
		$s = TC_Quote_Service::settings();
		if ( '' === trim( (string) $s['upi_vpa'] ) ) {
			return '';
		}
		return 'upi://pay?' . http_build_query(
			array(
				'pa' => trim( $s['upi_vpa'] ),
				'pn' => $s['upi_name'],
				'am' => number_format( (float) $amount, 2, '.', '' ),
				'cu' => 'INR',
				'tn' => substr( $note, 0, 60 ),
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/* ------------------------------------------------------------------ Applying money */

	/**
	 * Mark a payment paid exactly once and apply it to the booking.
	 */
	public static function mark_paid( $payment_id, $amount, $method, $reference ) {
		global $wpdb;
		$now     = current_time( 'mysql' );
		$changed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'paid', amount = %f, method = %s, reference = %s, paid_at = %s, updated_at = %s WHERE id = %d AND status <> 'paid'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				round( (float) $amount, 2 ),
				$method,
				$reference,
				$now,
				$now,
				(int) $payment_id
			)
		);
		if ( 1 !== (int) $changed ) {
			return false; // already applied
		}
		$p = self::get( $payment_id );
		self::apply_to_booking( $p );
		return true;
	}

	private static function apply_to_booking( array $p ) {
		global $wpdb;
		$t_b = $wpdb->prefix . 'tc_agent_bookings';
		$wpdb->query( $wpdb->prepare( "UPDATE $t_b SET advance_paid = advance_paid + %f, updated_at = %s WHERE id = %d", (float) $p['amount'], current_time( 'mysql' ), (int) $p['booking_id'] ) );
		$b   = self::booking( (int) $p['booking_id'] );
		$amt = self::amounts( (int) $p['booking_id'] );
		$row = array();
		if ( ! in_array( $b['status'], array( 'completed', 'cancelled' ), true ) ) {
			$row['status'] = ( $amt['total'] > 0 && $amt['paid'] >= $amt['total'] - 0.5 ) ? 'paid' : 'advance_paid';
		}
		if ( '' === (string) $b['invoice_no'] ) {
			$row['invoice_no']   = self::next_invoice_no();
			$row['invoice_date'] = current_time( 'Y-m-d' );
		}
		if ( $row ) {
			$wpdb->update( $t_b, $row, array( 'id' => (int) $b['id'] ) );
		}

		$labels = array( 'cashfree' => 'Cashfree', 'upi' => 'UPI', 'cash' => 'cash', 'bank' => 'bank transfer', 'card' => 'card' );
		$how    = $labels[ $p['method'] ] ?? $p['method'];
		TC_Contact_Service::add_activity( (int) $p['contact_id'], 'booking', sprintf( '💰 Payment %1$s received by %2$s for %3$s%4$s. Paid %5$s of %6$s.', TC_Quote_Service::money( $p['amount'] ), $how, $b['ref'], $p['reference'] ? ' (ref ' . $p['reference'] . ')' : '', TC_Quote_Service::money( $amt['paid'] ), TC_Quote_Service::money( $amt['total'] ) ) );

		$contact = TC_Contact_Service::get( (int) $p['contact_id'] );
		if ( $contact && ! in_array( $contact['stage'], array( 'won', 'completed' ), true ) ) {
			TC_Contact_Service::set_stage( (int) $contact['id'], 'won' );
		}
		if ( $contact && (int) $contact['owner_user_id'] && class_exists( 'TC_CRM_Notify' ) ) {
			TC_CRM_Notify::send( (int) $contact['owner_user_id'], sprintf( __( 'Payment received: %s', 'tripcosmos-agents' ), TC_Quote_Service::money( $p['amount'] ) ), TC_CRM_Notify::contact_line( $contact ) . "\n" . $b['ref'] . ' · ' . sprintf( __( 'Balance %s', 'tripcosmos-agents' ), TC_Quote_Service::money( $amt['balance'] ) ), (int) $contact['id'] );
		}

		// Transactional receipt to the traveler (allowed even when marketing is opted out, but not for do-not-contact).
		$q = $p['quote_id'] ? TC_Quote_Service::get( (int) $p['quote_id'] ) : TC_Quote_Service::current_for_booking( (int) $p['booking_id'] );
		$receipt_phone = ! empty( $contact['phone_e164'] ) ? $contact['phone_e164'] : ( $contact['phone'] ?? '' );
		if ( $contact && empty( $contact['dnd'] ) && ! empty( $receipt_phone ) && class_exists( 'TC_Integration_WhatsApp' ) && '0' !== (string) ( TC_Quote_Service::settings()['receipt_whatsapp'] ?? '1' ) ) {
			$msg = sprintf(
				"Namaste %s 🙏\nWe have received %s for your TripCosmos booking %s. Thank you!\nPaid: %s · Balance: %s%s",
				strtok( (string) $contact['name'], ' ' ) ?: 'Traveler',
				TC_Quote_Service::money( $p['amount'] ),
				$b['ref'],
				TC_Quote_Service::money( $amt['paid'] ),
				TC_Quote_Service::money( $amt['balance'] ),
				$q ? "\nReceipt: " . TC_Quote_Service::url( $q, true ) : ''
			);
			TC_Integration_WhatsApp::send_message( ltrim( $receipt_phone, '+' ), $msg );
		}
		do_action( 'tc_agents_payment_received', $p, $b );
	}

	/**
	 * Sequential invoice number per Indian financial year: PREFIX/2026-27/0001.
	 */
	public static function next_invoice_no() {
		global $wpdb;
		$y      = (int) current_time( 'Y' );
		$start  = (int) current_time( 'n' ) >= 4 ? $y : $y - 1;
		$fy     = $start . '-' . substr( (string) ( $start + 1 ), -2 );
		$option = 'tc_agents_invoice_seq_' . $fy;
		add_option( $option, 0, '', false );
		// Atomic increment so two payments at once can't get the same number.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $option ) );
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$seq    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		$prefix = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) TC_Quote_Service::settings()['invoice_prefix'] ) ?: 'TC';
		return sprintf( '%s/%s/%04d', $prefix, $fy, $seq );
	}
}
