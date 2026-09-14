<?php
/**
 * Guardrails & Safety Enforcement Engine.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Guardrails {

	/**
	 * Check whether the global emergency kill switch is engaged.
	 *
	 * @return bool True if kill switch is active (blocking actions), false otherwise.
	 */
	public static function is_kill_switch_active() {
		return '1' === (string) get_option( 'tc_agents_kill_switch', '0' );
	}

	/**
	 * Guard: Validate before any agent response or outbound message.
	 *
	 * @param string $channel    Channel name: 'web', 'whatsapp', 'voice', 'admin'.
	 * @param string $identifier IP address, session ID, or phone number.
	 * @return true|WP_Error True if allowed, WP_Error if blocked.
	 */
	public static function check_permission( $channel = 'web', $identifier = '' ) {
		// 1. Check Global Kill Switch
		if ( self::is_kill_switch_active() ) {
			TC_Agents_Logger::log(
				'kill_switch_blocked_request',
				'critical',
				array(
					'reason'     => 'Global kill switch is ACTIVE',
					'channel'    => $channel,
					'identifier' => $identifier,
				),
				'',
				$channel
			);
			return new WP_Error(
				'kill_switch_active',
				__( 'The AI agent service is currently paused for scheduled maintenance. Please reach our team directly on WhatsApp or phone.', 'tripcosmos-agents' ),
				array( 'status' => 503 )
			);
		}

		// 2. Channel Specific Checks
		if ( 'voice' === $channel ) {
			$voice_enabled = '1' === (string) get_option( 'tc_agents_voice_enabled', '0' );
			if ( ! $voice_enabled ) {
				return new WP_Error(
					'voice_disabled',
					__( 'Voice calling capability is currently disabled in system settings.', 'tripcosmos-agents' ),
					array( 'status' => 403 )
				);
			}

			// Daily cap check for voice
			$voice_daily_limit = (int) get_option( 'tc_agents_voice_daily_limit', 10 );
			$today_voice_count = (int) get_transient( 'tc_agents_voice_calls_today_' . gmdate( 'Ymd' ) );
			if ( $today_voice_count >= $voice_daily_limit ) {
				TC_Agents_Logger::log( 'voice_daily_limit_exceeded', 'warning', array( 'limit' => $voice_daily_limit ), '', 'voice' );
				return new WP_Error(
					'voice_limit_reached',
					__( 'Daily voice call threshold reached. Outbound call blocked.', 'tripcosmos-agents' ),
					array( 'status' => 429 )
				);
			}
		}

		if ( 'whatsapp' === $channel ) {
			$wa_daily_limit = (int) get_option( 'tc_agents_whatsapp_daily_limit', 100 );
			$today_wa_count = (int) get_transient( 'tc_agents_wa_msgs_today_' . gmdate( 'Ymd' ) );
			if ( $today_wa_count >= $wa_daily_limit ) {
				TC_Agents_Logger::log( 'whatsapp_daily_limit_exceeded', 'warning', array( 'limit' => $wa_daily_limit ), '', 'whatsapp' );
				return new WP_Error(
					'whatsapp_limit_reached',
					__( 'Daily WhatsApp message threshold reached.', 'tripcosmos-agents' ),
					array( 'status' => 429 )
				);
			}
		}

		// 3. Hourly Rate Limit per Session/Identifier
		if ( ! empty( $identifier ) ) {
			$hourly_limit = (int) get_option( 'tc_agents_rate_limit_hourly', 30 );
			$transient_key = 'tc_rate_' . substr( md5( $channel . '_' . $identifier ), 0, 20 );
			$current_count = (int) get_transient( $transient_key );

			if ( $current_count >= $hourly_limit ) {
				TC_Agents_Logger::log(
					'rate_limit_exceeded',
					'warning',
					array(
						'channel'    => $channel,
						'identifier' => $identifier,
						'count'      => $current_count,
						'limit'      => $hourly_limit,
					),
					'',
					$channel
				);
				return new WP_Error(
					'rate_limit_exceeded',
					__( 'You have reached the maximum message rate. Please wait a few moments or connect directly via WhatsApp.', 'tripcosmos-agents' ),
					array( 'status' => 429 )
				);
			}

			// Increment counter
			set_transient( $transient_key, $current_count + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Record a completed outbound message/call against daily counters.
	 */
	public static function record_outbound( $channel = 'web' ) {
		$today_key = gmdate( 'Ymd' );
		if ( 'voice' === $channel ) {
			$key   = 'tc_agents_voice_calls_today_' . $today_key;
			$count = (int) get_transient( $key );
			set_transient( $key, $count + 1, DAY_IN_SECONDS );
		} elseif ( 'whatsapp' === $channel ) {
			$key   = 'tc_agents_wa_msgs_today_' . $today_key;
			$count = (int) get_transient( $key );
			set_transient( $key, $count + 1, DAY_IN_SECONDS );
		}
	}

	/**
	 * Validate whether a proposed tool call is permissible.
	 * Incorporates VM Sales OS capability scoping (public vs internal) and VMAI discount ceiling math.
	 *
	 * @param string $tool_name Name of the tool.
	 * @param array  $arguments Arguments provided by LLM.
	 * @param array  $allowed_tools Allowed tools for the active agent.
	 * @param array  $context Execution context (channel, session_id, etc.).
	 * @return true|WP_Error
	 */
	public static function validate_tool_call( $tool_name, $arguments, $allowed_tools = array(), $context = array() ) {
		// 1. Check persona allowed list
		if ( ! in_array( $tool_name, $allowed_tools, true ) ) {
			return new WP_Error(
				'tool_not_allowed',
				sprintf( __( 'Agent is not permitted to execute tool: %s', 'tripcosmos-agents' ), esc_html( $tool_name ) )
			);
		}

		// 2. Capability Scoping: Public chat visitors cannot invoke internal CRM data inspection
		$channel = $context['channel'] ?? 'web';
		$internal_only_tools = array( 'lookup_contact_crm', 'update_lead_stage', 'charge_card' );

		if ( 'web' === $channel && in_array( $tool_name, $internal_only_tools, true ) ) {
			TC_Agents_Logger::log(
				'tool_capability_blocked',
				'warning',
				array(
					'tool'    => $tool_name,
					'channel' => $channel,
					'reason'  => 'Internal tool invocation attempted from public web channel.',
				)
			);
			return new WP_Error(
				'capability_refusal',
				__( 'This operation requires internal staff clearance.', 'tripcosmos-agents' )
			);
		}

		// 3. Margin & Discount Ceiling Protection (VMAI Deal Math)
		if ( ! empty( $arguments['discount_pct'] ) || ( ! empty( $arguments['base_price'] ) && ! empty( $arguments['offered_price'] ) ) ) {
			$base_price    = floatval( $arguments['base_price'] ?? 0 );
			$offered_price = floatval( $arguments['offered_price'] ?? 0 );
			if ( $base_price > 0 && $offered_price > 0 ) {
				$check = TC_Agent_Deal_Math::validate_discount( $base_price, $offered_price );
				if ( ! $check['allowed'] ) {
					return new WP_Error( 'discount_ceiling_exceeded', $check['reason'] );
				}
			}
		}

		// 4. Prevent transactional commits if human approval is enforced
		$require_approval = '1' === (string) get_option( 'tc_agents_require_human_approval', '1' );
		if ( $require_approval && in_array( $tool_name, array( 'confirm_booking', 'charge_payment', 'cancel_booking' ), true ) ) {
			TC_Agents_Logger::log(
				'transaction_held_for_approval',
				'warning',
				array(
					'tool'      => $tool_name,
					'arguments' => $arguments,
				)
			);
			return new WP_Error(
				'requires_human_approval',
				__( 'This booking or financial modification requires manual confirmation by a TripCosmos travel specialist.', 'tripcosmos-agents' )
			);
		}

		return true;
	}
}
