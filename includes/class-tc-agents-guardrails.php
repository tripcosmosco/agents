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

		// 3. Quiet Hours Check for Outbound Channels (WhatsApp & Voice)
		// Strict TRAI & privacy compliance: No cold/automated outreach between 21:00 (9 PM) and 08:30 (8:30 AM) IST.
		if ( in_array( $channel, array( 'whatsapp', 'voice' ), true ) ) {
			try {
				$tz = new DateTimeZone( 'Asia/Kolkata' );
				$now = new DateTime( 'now', $tz );
				$hour = (int) $now->format( 'G' );
				$minute = (int) $now->format( 'i' );
				$time_decimal = $hour + ( $minute / 60 );

				if ( $time_decimal >= 21.0 || $time_decimal < 8.5 ) {
					TC_Agents_Logger::log(
						'quiet_hours_blocked',
						'info',
						array(
							'channel'    => $channel,
							'identifier' => $identifier,
							'ist_time'   => $now->format( 'H:i:s' ),
							'reason'     => 'Quiet hours in effect (9:00 PM - 8:30 AM IST).',
						),
						'',
						$channel
					);
					return new WP_Error(
						'quiet_hours_active',
						__( 'Outbound communications are paused during quiet hours (9:00 PM to 8:30 AM IST) for recipient privacy.', 'tripcosmos-agents' ),
						array( 'status' => 429 )
					);
				}
			} catch ( Exception $e ) {
				// Fallback gracefully
			}
		}

		// 4. Hourly Rate Limit per Session/Identifier
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
		$internal_only_tools = array( 'lookup_contact_crm', 'import_b2b_agencies', 'outreach_b2b_agency', 'update_lead_stage', 'charge_card' );

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

		// 3. Margin & Discount Ceiling Protection
		if ( class_exists( 'TC_Agent_Deal_Math' ) ) {
			$ceiling = TC_Agent_Deal_Math::get_discount_ceiling();
			if ( isset( $arguments['discount_pct'] ) && floatval( $arguments['discount_pct'] ) > $ceiling ) {
				return new WP_Error(
					'discount_ceiling_exceeded',
					sprintf( __( 'Requested discount of %.1f%% exceeds authorized maximum of %d%%.', 'tripcosmos-agents' ), floatval( $arguments['discount_pct'] ), $ceiling )
				);
			}
			if ( ! empty( $arguments['base_price'] ) && ! empty( $arguments['offered_price'] ) ) {
				$base_price    = floatval( $arguments['base_price'] );
				$offered_price = floatval( $arguments['offered_price'] );
				if ( $base_price > 0 && $offered_price > 0 ) {
					$check = TC_Agent_Deal_Math::validate_discount( $base_price, $offered_price );
					if ( ! $check['allowed'] ) {
						return new WP_Error( 'discount_ceiling_exceeded', $check['reason'] );
					}
				}
			}
		}

		// 4. Prevent transactional commits if human approval is enforced
		$require_approval = '1' === (string) get_option( 'tc_agents_require_human_approval', '1' );
		if ( $require_approval && in_array( $tool_name, array( 'confirm_booking', 'charge_payment', 'cancel_booking', 'outreach_b2b_agency', 'import_b2b_agencies' ), true ) ) {
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

	/**
	 * Verify an inbound webhook secret (fail-closed).
	 * Accepts the X-Webhook-Secret header or ?secret= / ?token= param.
	 * If no secret exists yet one is generated (visible under Integrations) and the request is rejected.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $option  Option name holding the secret.
	 * @param string          $channel Channel label for the audit log.
	 * @return true|WP_REST_Response
	 */
	public static function verify_webhook_secret( $request, $option, $channel ) {
		$configured = (string) get_option( $option, '' );
		if ( '' === $configured ) {
			update_option( $option, wp_generate_password( 32, false ), false );
			TC_Agents_Logger::log( $channel . '_webhook_rejected', 'warning', array( 'reason' => 'no_secret_configured' ), '', $channel );
			return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
		}
		$provided = (string) ( $request->get_header( 'x-webhook-secret' ) ?: $request->get_param( 'secret' ) ?: $request->get_param( 'token' ) );
		if ( '' === $provided || ! hash_equals( $configured, $provided ) ) {
			TC_Agents_Logger::log( $channel . '_webhook_auth_failed', 'warning', array( 'ip' => $_SERVER['REMOTE_ADDR'] ?? '' ), '', $channel );
			return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
		}
		return true;
	}
}
