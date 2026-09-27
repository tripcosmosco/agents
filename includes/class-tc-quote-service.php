<?php
/**
 * Quotes: versioned price breakdowns for a booking, shared with the traveler by a private link.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Quote_Service {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_quotes';
	}

	/**
	 * Billing settings with defaults.
	 */
	public static function settings() {
		return wp_parse_args(
			(array) get_option( 'tc_agents_billing', array() ),
			array(
				'business_name'  => get_bloginfo( 'name' ),
				'address'        => '',
				'phone'          => (string) get_option( 'tc_agents_human_whatsapp_number', '' ),
				'email'          => (string) get_option( 'admin_email' ),
				'gstin'          => '',
				'state'          => 'Uttar Pradesh',
				'sac'            => '',
				'gst_enabled'    => 0,
				'gst_rate'       => 5,
				'gst_inclusive'  => 1,
				'gst_split'      => 1,
				'advance_type'   => 'fixed',
				'advance_value'  => 1500,
				'valid_days'     => 7,
				'terms'          => "Advance confirms your booking; the balance is payable before or on arrival.\nCancellation more than 7 days before travel: advance refunded minus gateway charges. Within 7 days: advance is non-refundable.\nTemple darshan slots, boat timings and hotel availability are subject to confirmation by the temple trust / operators.",
				'invoice_prefix' => 'TC',
				'upi_vpa'        => '',
				'upi_name'       => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Price a quote. Pure function.
	 *
	 * @param array $items        [ [ 'desc' => string, 'qty' => number, 'price' => number ], ... ]
	 * @param float $discount     Flat discount in currency.
	 * @param float $tax_rate     GST % (0 = no tax).
	 * @param bool  $inclusive    Whether item prices already include GST.
	 * @param array $advance_rule [ 'type' => 'fixed'|'percent', 'value' => number ]
	 * @return array{items:array,subtotal:float,discount:float,taxable:float,tax_amount:float,total:float,advance_amount:float}
	 */
	public static function calculate( array $items, $discount = 0, $tax_rate = 0, $inclusive = true, array $advance_rule = array() ) {
		$clean    = array();
		$subtotal = 0.0;
		foreach ( $items as $it ) {
			$desc  = trim( (string) ( $it['desc'] ?? '' ) );
			$qty   = max( 0, (float) ( $it['qty'] ?? 1 ) );
			$price = max( 0, (float) ( $it['price'] ?? 0 ) );
			if ( '' === $desc || $qty <= 0 ) {
				continue;
			}
			$line      = round( $qty * $price, 2 );
			$clean[]   = array( 'desc' => $desc, 'qty' => $qty, 'price' => $price, 'amount' => $line );
			$subtotal += $line;
		}
		$subtotal = round( $subtotal, 2 );
		$discount = round( min( max( 0, (float) $discount ), $subtotal ), 2 );
		$after    = $subtotal - $discount;
		$rate     = max( 0, (float) $tax_rate ) / 100;

		if ( $rate > 0 && $inclusive ) {
			$total   = round( $after, 2 );
			$taxable = round( $total / ( 1 + $rate ), 2 );
			$tax     = round( $total - $taxable, 2 );
		} elseif ( $rate > 0 ) {
			$taxable = round( $after, 2 );
			$tax     = round( $taxable * $rate, 2 );
			$total   = round( $taxable + $tax, 2 );
		} else {
			$taxable = round( $after, 2 );
			$tax     = 0.0;
			$total   = $taxable;
		}

		$type    = $advance_rule['type'] ?? 'fixed';
		$value   = max( 0, (float) ( $advance_rule['value'] ?? 0 ) );
		$advance = 'percent' === $type ? round( $total * min( 100, $value ) / 100 ) : $value;
		$advance = round( min( $advance, $total ), 2 );

		return array(
			'items'          => $clean,
			'subtotal'       => $subtotal,
			'discount'       => $discount,
			'taxable'        => $taxable,
			'tax_amount'     => $tax,
			'total'          => $total,
			'advance_amount' => $advance,
		);
	}

	/**
	 * Create a new quote version for a booking. Earlier open versions become 'superseded'.
	 *
	 * @return int|WP_Error Quote id.
	 */
	public static function create( $booking_id, array $input ) {
		global $wpdb;
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", (int) $booking_id ), ARRAY_A );
		if ( ! $booking ) {
			return new WP_Error( 'no_booking', __( 'Booking not found.', 'tripcosmos-agents' ) );
		}
		$set   = self::settings();
		$rate  = isset( $input['tax_rate'] ) ? (float) $input['tax_rate'] : ( $set['gst_enabled'] ? (float) $set['gst_rate'] : 0 );
		$incl  = isset( $input['tax_inclusive'] ) ? (bool) $input['tax_inclusive'] : (bool) $set['gst_inclusive'];
		$calc  = self::calculate( (array) ( $input['items'] ?? array() ), $input['discount'] ?? 0, $rate, $incl, array( 'type' => $set['advance_type'], 'value' => $set['advance_value'] ) );
		if ( ! $calc['items'] || $calc['total'] <= 0 ) {
			return new WP_Error( 'empty', __( 'Add at least one line with a price.', 'tripcosmos-agents' ) );
		}

		// Discount guardrail: agents may not exceed the ceiling configured in Deal Math.
		if ( $calc['discount'] > 0 && class_exists( 'TC_Agent_Deal_Math' ) && ! TC_CRM_Access::can_manage() ) {
			$pct     = 100 * $calc['discount'] / max( 1, $calc['subtotal'] );
			$ceiling = (float) TC_Agent_Deal_Math::get_discount_ceiling();
			if ( $pct > $ceiling ) {
				return new WP_Error( 'discount', sprintf( __( 'A %1$.1f%% discount is above the %2$s%% limit for agents. Ask a manager to approve it.', 'tripcosmos-agents' ), $pct, $ceiling ) );
			}
		}

		$advance = isset( $input['advance_amount'] ) && '' !== (string) $input['advance_amount'] ? round( min( max( 0, (float) $input['advance_amount'] ), $calc['total'] ), 2 ) : $calc['advance_amount'];
		$days    = max( 1, (int) ( $input['valid_days'] ?? $set['valid_days'] ) );
		$version = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . self::table() . ' WHERE booking_id = %d', (int) $booking_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status = 'superseded' WHERE booking_id = %d AND status IN ('draft','sent','viewed')", (int) $booking_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->insert(
			self::table(),
			array(
				'booking_id'     => (int) $booking_id,
				'contact_id'     => (int) $booking['contact_id'],
				'version'        => $version,
				'token'          => bin2hex( random_bytes( 16 ) ),
				'items'          => wp_json_encode( $calc['items'] ),
				'subtotal'       => $calc['subtotal'],
				'discount'       => $calc['discount'],
				'tax_rate'       => $rate,
				'tax_inclusive'  => $incl ? 1 : 0,
				'tax_amount'     => $calc['tax_amount'],
				'total'          => $calc['total'],
				'advance_amount' => $advance,
				'valid_until'    => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " +{$days} days" ) ),
				'notes'          => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
				'status'         => 'draft',
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$qid = (int) $wpdb->insert_id;

		// Keep the booking and pipeline value in step with the latest quote.
		$wpdb->update( $wpdb->prefix . 'tc_agent_bookings', array( 'quote_amount' => $calc['total'], 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $booking_id ) );
		$sum = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(quote_amount) FROM {$wpdb->prefix}tc_agent_bookings WHERE contact_id = %d AND status <> 'cancelled'", (int) $booking['contact_id'] ) );
		$wpdb->update( TC_Contact_Service::table(), array( 'deal_value' => $sum ), array( 'id' => (int) $booking['contact_id'] ) );

		TC_Contact_Service::add_activity( (int) $booking['contact_id'], 'booking', sprintf( 'Quote %s v%d prepared: ₹%s', $booking['ref'], $version, number_format_i18n( $calc['total'] ) ) );
		$contact = TC_Contact_Service::get( (int) $booking['contact_id'] );
		if ( $contact && in_array( $contact['stage'], array( 'inquiry', 'qualified' ), true ) ) {
			TC_Contact_Service::set_stage( (int) $contact['id'], 'proposal', '', false );
		}
		return $qid;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token ) );
		if ( 32 !== strlen( $token ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function for_booking( $booking_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_id = %d ORDER BY version DESC', (int) $booking_id ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The quote currently governing a booking: accepted one, else the newest.
	 */
	public static function current_for_booking( $booking_id ) {
		foreach ( self::for_booking( $booking_id ) as $q ) {
			if ( 'accepted' === $q['status'] ) {
				return $q;
			}
		}
		$all = self::for_booking( $booking_id );
		return $all[0] ?? null;
	}

	public static function url( array $q, $invoice = false ) {
		return add_query_arg( $invoice ? 'tc_invoice' : 'tc_quote', $q['token'], home_url( '/' ) );
	}

	public static function mark_sent( $id, $channel ) {
		global $wpdb;
		$q = self::get( $id );
		if ( ! $q ) {
			return;
		}
		$wpdb->update( self::table(), array( 'sent_at' => current_time( 'mysql' ), 'status' => 'draft' === $q['status'] ? 'sent' : $q['status'] ), array( 'id' => (int) $id ) );
		TC_Contact_Service::add_activity( (int) $q['contact_id'], 'whatsapp' === $channel ? 'whatsapp' : 'email', sprintf( 'Quote v%d sent by %s', $q['version'], $channel ) );
	}

	/**
	 * First view by the customer (staff previews are ignored).
	 */
	public static function mark_viewed( array $q ) {
		global $wpdb;
		if ( $q['viewed_at'] || ( is_user_logged_in() && TC_CRM_Access::can_access() ) ) {
			return;
		}
		$wpdb->update( self::table(), array( 'viewed_at' => current_time( 'mysql' ), 'status' => in_array( $q['status'], array( 'draft', 'sent' ), true ) ? 'viewed' : $q['status'] ), array( 'id' => (int) $q['id'] ) );
		TC_Contact_Service::add_activity( (int) $q['contact_id'], 'system', sprintf( 'Customer opened quote v%d', $q['version'] ) );
		$c = TC_Contact_Service::get( (int) $q['contact_id'] );
		if ( $c && (int) $c['owner_user_id'] && class_exists( 'TC_CRM_Notify' ) && TC_CRM_Notify::enabled( 'reply' ) ) {
			TC_CRM_Notify::send( (int) $c['owner_user_id'], sprintf( __( '%s opened their quote', 'tripcosmos-agents' ), $c['name'] ?: 'Traveler' ), __( 'Good moment to follow up.', 'tripcosmos-agents' ), (int) $c['id'] );
		}
	}

	/**
	 * Customer accepts. Only the live (non-superseded, unexpired) version can be accepted.
	 */
	public static function accept( array $q, $ip ) {
		global $wpdb;
		if ( ! self::is_open( $q ) ) {
			return false;
		}
		$wpdb->update( self::table(), array( 'status' => 'accepted', 'accepted_at' => current_time( 'mysql' ), 'accepted_ip' => substr( (string) $ip, 0, 64 ) ), array( 'id' => (int) $q['id'] ) );
		TC_Contact_Service::add_activity( (int) $q['contact_id'], 'booking', sprintf( '✅ Customer accepted quote v%d (₹%s)', $q['version'], number_format_i18n( (float) $q['total'] ) ) );
		$contact = TC_Contact_Service::get( (int) $q['contact_id'] );
		if ( $contact && in_array( $contact['stage'], array( 'inquiry', 'qualified', 'proposal' ), true ) ) {
			TC_Contact_Service::set_stage( (int) $contact['id'], 'negotiation', '', false );
		}
		if ( $contact && (int) $contact['owner_user_id'] && class_exists( 'TC_CRM_Notify' ) ) {
			TC_CRM_Notify::send( (int) $contact['owner_user_id'], sprintf( __( '%s accepted the quote', 'tripcosmos-agents' ), $contact['name'] ?: 'Traveler' ), sprintf( '₹%s · %s', number_format_i18n( (float) $q['total'] ), __( 'Waiting for advance payment.', 'tripcosmos-agents' ) ), (int) $contact['id'] );
		}
		return true;
	}

	public static function is_open( array $q ) {
		return in_array( $q['status'], array( 'draft', 'sent', 'viewed' ), true ) && ( ! $q['valid_until'] || $q['valid_until'] >= current_time( 'Y-m-d' ) );
	}

	/**
	 * Indian number grouping: 150000 -> ₹1,50,000; 1234.5 -> ₹1,234.50.
	 */
	public static function money( $v ) {
		$v     = round( (float) $v, 2 );
		$neg   = $v < 0;
		$v     = abs( $v );
		$int   = (string) floor( $v );
		$dec   = round( $v - floor( $v ), 2 );
		$last3 = substr( $int, -3 );
		$rest  = substr( $int, 0, -3 );
		$out   = '' !== $rest ? preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', $rest ) . ',' . $last3 : $last3;
		if ( $dec > 0 ) {
			$out .= '.' . str_pad( (string) round( $dec * 100 ), 2, '0', STR_PAD_LEFT );
		}
		return ( $neg ? '-' : '' ) . '₹' . $out;
	}
}
