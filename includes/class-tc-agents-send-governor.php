<?php
/**
 * TripCosmos Agents - Send Governor
 *
 * Outbound compliance and anti-ban protection for marketing, cold B2B, and automated follow-ups.
 * Features:
 * 1. Quiet Hours Enforcement (e.g. 09:00 - 20:30 IST) - Never disturb travelers or partners overnight.
 * 2. Per-Channel Daily Caps (WhatsApp, Brevo Email, SMS) - Eliminates risk of Meta / carrier bans.
 * 3. Conversational Exception: Direct customer replies and urgent inbound inquiries are NEVER throttled.
 *
 * Ported from VM Sales OS enterprise architecture.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Send_Governor {

	const OPTION_WINDOW_ENABLED = 'tc_agents_send_window_enabled';
	const OPTION_WINDOW         = 'tc_agents_send_window';
	const OPTION_CAP_PREFIX     = 'tc_agents_daily_cap_';
	const OPTION_COUNT_PREFIX   = 'tc_agents_daily_count_';

	/**
	 * Default daily sending limits per channel.
	 */
	const DEFAULT_CAPS = array(
		'whatsapp' => 50,  // Max 50 cold/automated WhatsApps/day per number to prevent Meta bans
		'email'    => 500, // Brevo daily transactional / campaign allocation
		'sms'      => 100,
	);

	/**
	 * Parse "HH:MM-HH:MM" into minutes-of-day. Overnight windows (20:00-06:00) supported.
	 *
	 * @param string $window Format: "09:00-20:30"
	 * @return array{0:int, 1:int}|null
	 */
	public static function parse_window( $window ) {
		if ( ! preg_match( '/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', (string) $window, $m ) ) {
			return null;
		}

		$start_hour = (int) $m[1];
		$start_min  = (int) $m[2];
		$end_hour   = (int) $m[3];
		$end_min    = (int) $m[4];

		if ( $start_hour > 23 || $end_hour > 23 || $start_min > 59 || $end_min > 59 ) {
			return null;
		}

		return array( $start_hour * 60 + $start_min, $end_hour * 60 + $end_min );
	}

	/**
	 * Is a local timestamp inside the allowed window?
	 *
	 * @param int    $timestamp
	 * @param string $window
	 * @return bool
	 */
	public static function is_within_window( $timestamp, $window ) {
		$parsed = self::parse_window( $window );
		if ( null === $parsed ) {
			return true; // Malformed window never blocks.
		}

		list( $start, $end ) = $parsed;
		$minute = ( (int) wp_date( 'G', $timestamp ) ) * 60 + (int) wp_date( 'i', $timestamp );

		if ( $start === $end ) {
			return true;
		}

		return $start < $end
			? ( $minute >= $start && $minute < $end )
			: ( $minute >= $start || $minute < $end );
	}

	/**
	 * Next timestamp at or after $timestamp when the window opens.
	 *
	 * @param int    $timestamp
	 * @param string $window
	 * @return int
	 */
	public static function next_window_start( $timestamp, $window ) {
		$parsed = self::parse_window( $window );
		if ( null === $parsed || self::is_within_window( $timestamp, $window ) ) {
			return $timestamp;
		}

		list( $start ) = $parsed;
		$midnight = (int) strtotime( wp_date( 'Y-m-d 00:00:00', $timestamp ) );
		$open_today = $midnight + $start * 60;

		return $timestamp < $open_today ? $open_today : $open_today + DAY_IN_SECONDS;
	}

	/**
	 * Check if sending window is currently open (IST).
	 *
	 * @return bool
	 */
	public static function window_open() {
		$enabled = get_option( self::OPTION_WINDOW_ENABLED, '1' );
		if ( '0' === (string) $enabled || false === $enabled ) {
			return true;
		}

		$window = (string) get_option( self::OPTION_WINDOW, '09:00-20:30' );
		return self::is_within_window( (int) current_time( 'timestamp' ), $window );
	}

	/**
	 * Seconds until window reopens.
	 *
	 * @return int
	 */
	public static function seconds_until_open() {
		$now    = (int) current_time( 'timestamp' );
		$window = (string) get_option( self::OPTION_WINDOW, '09:00-20:30' );
		return max( 60, self::next_window_start( $now, $window ) - $now );
	}

	/**
	 * Get configured daily cap for a channel.
	 *
	 * @param string $channel 'whatsapp' | 'email' | 'sms'
	 * @return int
	 */
	public static function cap( $channel ) {
		$ch = sanitize_key( $channel );
		$defaults = self::DEFAULT_CAPS;
		return (int) get_option( self::OPTION_CAP_PREFIX . $ch, $defaults[ $ch ] ?? 50 );
	}

	/**
	 * Get number of messages sent today on this channel.
	 *
	 * @param string $channel
	 * @return int
	 */
	public static function sent_today( $channel ) {
		$ch      = sanitize_key( $channel );
		$counter = get_option( self::OPTION_COUNT_PREFIX . $ch, array() );
		$today   = (string) current_time( 'Y-m-d' );

		return ( is_array( $counter ) && ( $counter['date'] ?? '' ) === $today ) ? (int) $counter['count'] : 0;
	}

	/**
	 * Can we send on this channel right now without exceeding daily cap?
	 *
	 * @param string $channel
	 * @return bool
	 */
	public static function can_send( $channel ) {
		$cap = self::cap( $channel );
		return $cap <= 0 || self::sent_today( $channel ) < $cap;
	}

	/**
	 * Increment sent counter for today.
	 *
	 * @param string $channel
	 */
	public static function record_send( $channel ) {
		$ch      = sanitize_key( $channel );
		$today   = (string) current_time( 'Y-m-d' );
		$counter = get_option( self::OPTION_COUNT_PREFIX . $ch, array() );

		if ( ! is_array( $counter ) || ( $counter['date'] ?? '' ) !== $today ) {
			$counter = array( 'date' => $today, 'count' => 0 );
		}

		$counter['count'] = (int) $counter['count'] + 1;
		update_option( self::OPTION_COUNT_PREFIX . $ch, $counter, false );
	}

	/**
	 * Remaining sends available today.
	 *
	 * @param string $channel
	 * @return int
	 */
	public static function remaining_today( $channel ) {
		$cap = self::cap( $channel );
		return $cap <= 0 ? PHP_INT_MAX : max( 0, $cap - self::sent_today( $channel ) );
	}

	/**
	 * Master pre-flight check before initiating automated or outbound send.
	 *
	 * @param string $channel 'whatsapp' | 'email' | 'sms'
	 * @param bool   $is_direct_reply True if this is an instant conversational answer to an incoming query.
	 * @return array{allowed:bool, reason:string, retry_after:int}
	 */
	public static function check( $channel, $is_direct_reply = false ) {
		// Conversational replies are never blocked by quiet hours or daily caps.
		if ( $is_direct_reply ) {
			return array( 'allowed' => true, 'reason' => '', 'retry_after' => 0 );
		}

		if ( ! self::window_open() ) {
			return array(
				'allowed'     => false,
				'reason'      => 'quiet_hours',
				'retry_after' => self::seconds_until_open(),
			);
		}

		if ( ! self::can_send( $channel ) ) {
			$now      = (int) current_time( 'timestamp' );
			$midnight = (int) strtotime( wp_date( 'Y-m-d 00:00:00', $now ) ) + DAY_IN_SECONDS;
			return array(
				'allowed'     => false,
				'reason'      => 'daily_cap_exceeded',
				'retry_after' => max( 60, $midnight - $now ),
			);
		}

		return array( 'allowed' => true, 'reason' => '', 'retry_after' => 0 );
	}
}
