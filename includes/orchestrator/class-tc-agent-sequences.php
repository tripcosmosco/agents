<?php
/**
 * Automated Follow-Up Drip Sequences Engine.
 * Features Cross-Channel Failover, Quiet Hours Governor, Stop-on-Reply, and Domain Personalization.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Sequences {

	public static function init() {
		add_action( 'tc_agents_run_sequences_cron', array( __CLASS__, 'process_due_steps' ) );

		if ( ! wp_next_scheduled( 'tc_agents_run_sequences_cron' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'tc_agents_run_sequences_cron' );
		}

		self::maybe_seed_defaults();
	}

	/**
	 * Seed default high-converting tour & pilgrimage follow-up sequences.
	 */
	public static function maybe_seed_defaults() {
		global $wpdb;
		$table_seq = $wpdb->prefix . 'tc_agent_sequences';

		// 1. Abandoned Chat Inquiry Recovery
		$exists_abandoned = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_seq WHERE trigger_event = %s LIMIT 1", 'inquiry_abandoned' ) );
		if ( ! $exists_abandoned ) {
			$steps_abandoned = array(
				array(
					'delay_hours' => 1,
					'subject'     => 'Quick question regarding your Varanasi tour & cab inquiry - TripCosmos',
					'message'     => "Namaste {name}! TripCosmos team here from Varanasi 🛕\n\nI noticed you were exploring tour packages & cabs for {destination}. Did you have any questions regarding temple darshan timings, outstation cab fares (Dzire / Innova Crysta / Tempo Traveller), or ghat-view hotel stays? Here is our full circuit guide: https://tripcosmos.co/packages\n\nLet me know if you would like me to customize the day-wise itinerary for your family or group!",
				),
				array(
					'delay_hours' => 24,
					'subject'     => 'Varanasi Travel Tip: Evening Ganga Aarti Boat & Temple Darshan',
					'message'     => "Namaste {name}! Quick travel tip from Varanasi: reserving your private Ganga Aarti boat and temple darshan in advance saves hours of waiting in line ⛵\n\nWould you like us to block your Aarti boat or arrange an AC cab for your arrival at Varanasi (VNS) or Ayodhya airport?",
				),
				array(
					'delay_hours' => 48,
					'subject'     => 'TripCosmos: Cab & Hotel Availability for {destination}',
					'message'     => "Namaste {name}, our travel desk is scheduling vehicle allocations and hotel blocks for next week. Would you like me to hold a tentative cab/hotel slot or connect on a quick 5-min briefing call?",
				),
			);

			$wpdb->insert(
				$table_seq,
				array(
					'name'          => 'Varanasi Tour & Cab Inquiry Recovery',
					'channel'       => 'whatsapp',
					'trigger_event' => 'inquiry_abandoned',
					'steps_json'    => wp_json_encode( $steps_abandoned ),
					'is_active'     => 1,
					'created_at'    => current_time( 'mysql' ),
				)
			);
		}

		// 2. Fluent Forms Lead Nurture Sequence
		$exists_fluent = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_seq WHERE trigger_event = %s LIMIT 1", 'fluentform_submitted' ) );
		if ( ! $exists_fluent ) {
			$steps_fluent = array(
				array(
					'delay_hours' => 0.25, // 15 mins
					'subject'     => 'TripCosmos: We received your Varanasi / Ayodhya tour request!',
					'message'     => "Namaste {name}! 🛕 Thank you for reaching out to TripCosmos (Varanasi).\n\nOur travel desk has received your request for {destination}. We are calculating the best cab fare, hotel options, and temple circuit itinerary for your dates.\n\nAre you traveling with family or senior citizens, and would you like airport/railway station pickup included?",
				),
				array(
					'delay_hours' => 24,
					'subject'     => 'Your Detailed Tour Itinerary & Cab Quotation',
					'message'     => "Namaste {name}, here is your customized day-wise tour itinerary and cab quotation for {destination}.\n\nWould you like to speak with our travel specialist on a quick 5-minute call to finalize the package or customize any stops?",
				),
			);

			$wpdb->insert(
				$table_seq,
				array(
					'name'          => 'Fluent Forms Lead Instant Nurture',
					'channel'       => 'whatsapp',
					'trigger_event' => 'fluentform_submitted',
					'steps_json'    => wp_json_encode( $steps_fluent ),
					'is_active'     => 1,
					'created_at'    => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Enroll a contact into an automated follow-up sequence.
	 *
	 * @param int    $contact_id
	 * @param string $trigger_event e.g. 'inquiry_abandoned' or 'fluentform_submitted'
	 * @return bool
	 */
	public static function enroll_contact( $contact_id, $trigger_event = 'inquiry_abandoned' ) {
		global $wpdb;
		$table_seq    = $wpdb->prefix . 'tc_agent_sequences';
		$table_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';

		$seq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_seq WHERE trigger_event = %s AND is_active = 1 LIMIT 1", $trigger_event ) );
		if ( ! $seq ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_enroll WHERE sequence_id = %d AND contact_id = %d AND status = 'active' LIMIT 1", $seq->id, $contact_id ) );
		if ( $exists ) {
			return false;
		}

		$steps = json_decode( $seq->steps_json, true ) ?: array();
		if ( empty( $steps ) ) {
			return false;
		}

		$delay_hours = (float) ( $steps[0]['delay_hours'] ?? 1 );
		$next_run    = gmdate( 'Y-m-d H:i:s', time() + (int) round( $delay_hours * HOUR_IN_SECONDS ) );

		return (bool) $wpdb->insert(
			$table_enroll,
			array(
				'sequence_id'  => $seq->id,
				'contact_id'   => $contact_id,
				'current_step' => 0,
				'status'       => 'active',
				'next_run_at'  => $next_run,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check if currently in quiet hours (9 PM to 8 AM local time).
	 *
	 * @return bool True if quiet hours active (dispatch should postpone).
	 */
	public static function is_quiet_hours() {
		$local_hour = (int) current_time( 'G' );
		return ( $local_hour < 8 || $local_hour >= 21 );
	}

	/**
	 * Process all due follow-up steps across enrolled contacts.
	 */
	public static function process_due_steps() {
		if ( TC_Agents_Guardrails::is_kill_switch_active() ) {
			return;
		}

		// Send Governor Quiet Hours Protection
		if ( self::is_quiet_hours() ) {
			return;
		}

		global $wpdb;
		$table_seq     = $wpdb->prefix . 'tc_agent_sequences';
		$table_enroll  = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		$table_contact = $wpdb->prefix . 'tc_agent_contacts';

		$now = gmdate( 'Y-m-d H:i:s' );
		$due = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table_enroll WHERE status = 'active' AND next_run_at <= %s LIMIT 20", $now ),
			ARRAY_A
		);

		if ( empty( $due ) ) {
			return;
		}

		foreach ( $due as $row ) {
			$seq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_seq WHERE id = %d", $row['sequence_id'] ), ARRAY_A );
			$con = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contact WHERE id = %d", $row['contact_id'] ), ARRAY_A );

			if ( ! $seq || ! $con ) {
				$wpdb->update( $table_enroll, array( 'status' => 'cancelled' ), array( 'id' => $row['id'] ) );
				continue;
			}

			// If lead is already won, stop sequence
			if ( 'won' === $con['stage'] ) {
				$wpdb->update( $table_enroll, array( 'status' => 'completed' ), array( 'id' => $row['id'] ) );
				continue;
			}

			$steps    = json_decode( $seq['steps_json'], true ) ?: array();
			$step_idx = (int) $row['current_step'];

			if ( ! isset( $steps[ $step_idx ] ) ) {
				$wpdb->update( $table_enroll, array( 'status' => 'completed' ), array( 'id' => $row['id'] ) );
				continue;
			}

			$step         = $steps[ $step_idx ];
			$message_body = self::personalize_message( $step['message'] ?? '', $con );
			$subject      = $step['subject'] ?? 'TripCosmos Tour & Pilgrimage Follow-up';

			// Cross-Channel Smart Failover Dispatch
			$sent         = false;
			$used_channel = '';
			$primary_wa   = ( 'whatsapp' === $seq['channel'] );

			if ( $primary_wa && ! empty( $con['phone'] ) ) {
				$check = TC_Agents_Guardrails::check_permission( 'whatsapp', $con['phone'] );
				if ( ! is_wp_error( $check ) ) {
					$sent = TC_Integration_WhatsApp::send_message( $con['phone'], $message_body );
					if ( $sent ) {
						$used_channel = 'whatsapp';
						TC_Agents_Guardrails::record_outbound( 'whatsapp' );
					}
				}
			} elseif ( ! $primary_wa && ! empty( $con['email'] ) ) {
				$sent = (bool) wp_mail( $con['email'], $subject, $message_body );
				if ( $sent ) {
					$used_channel = 'email';
				}
			}

			// Failover to secondary channel if primary was unavailable or failed
			if ( ! $sent ) {
				if ( $primary_wa && ! empty( $con['email'] ) ) {
					$sent = (bool) wp_mail( $con['email'], $subject, $message_body );
					if ( $sent ) {
						$used_channel = 'email (failover)';
					}
				} elseif ( ! $primary_wa && ! empty( $con['phone'] ) ) {
					$check = TC_Agents_Guardrails::check_permission( 'whatsapp', $con['phone'] );
					if ( ! is_wp_error( $check ) ) {
						$sent = TC_Integration_WhatsApp::send_message( $con['phone'], $message_body );
						if ( $sent ) {
							$used_channel = 'whatsapp (failover)';
							TC_Agents_Guardrails::record_outbound( 'whatsapp' );
						}
					}
				}
			}

			if ( $sent ) {
				TC_Agents_Logger::log(
					'sequence_step_fired',
					'info',
					array(
						'contact_id'   => $con['id'],
						'step'         => $step_idx,
						'channel'      => $used_channel,
					)
				);

				// Advance to next step
				$next_step_idx = $step_idx + 1;
				if ( isset( $steps[ $next_step_idx ] ) ) {
					$next_delay = (float) ( $steps[ $next_step_idx ]['delay_hours'] ?? 24 );
					$wpdb->update(
						$table_enroll,
						array(
							'current_step' => $next_step_idx,
							'next_run_at'  => gmdate( 'Y-m-d H:i:s', time() + (int) round( $next_delay * HOUR_IN_SECONDS ) ),
							'updated_at'   => current_time( 'mysql' ),
						),
						array( 'id' => $row['id'] )
					);
				} else {
					$wpdb->update(
						$table_enroll,
						array( 'status' => 'completed', 'updated_at' => current_time( 'mysql' ) ),
						array( 'id' => $row['id'] )
					);
				}
			} else {
				// Retry in 2 hours
				$wpdb->update(
					$table_enroll,
					array(
						'next_run_at' => gmdate( 'Y-m-d H:i:s', time() + 7200 ),
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $row['id'] )
				);
			}
		}
	}

	/**
	 * Personalize message placeholders.
	 */
	private static function personalize_message( $template, array $contact ) {
		$name     = ! empty( $contact['name'] ) ? $contact['name'] : 'Traveler';
		$deal_val = ! empty( $contact['deal_value'] ) ? '₹' . number_format( (float) $contact['deal_value'] ) : 'custom group package';
		$phone    = ! empty( $contact['phone'] ) ? $contact['phone'] : '';
		$email    = ! empty( $contact['email'] ) ? $contact['email'] : '';

		$meta = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
		$dest = ! empty( $meta['destination'] ) ? $meta['destination'] : 'Varanasi & Spiritual Circuit';

		$replacements = array(
			'{name}'         => $name,
			'{{name}}'       => $name,
			'{Name}'         => $name,
			'{{Name}}'       => $name,
			'{deal_value}'   => $deal_val,
			'{{deal_value}}' => $deal_val,
			'{destination}'  => $dest,
			'{{destination}}'=> $dest,
			'{phone}'        => $phone,
			'{{phone}}'      => $phone,
			'{email}'        => $email,
			'{{email}}'      => $email,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Cancel all active enrollments for a contact when they reply or book.
	 */
	public static function cancel_for_contact( $contact_id ) {
		global $wpdb;
		$table_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		return $wpdb->update(
			$table_enroll,
			array( 'status' => 'replied', 'updated_at' => current_time( 'mysql' ) ),
			array( 'contact_id' => (int) $contact_id, 'status' => 'active' )
		);
	}
}
