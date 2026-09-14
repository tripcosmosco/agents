<?php
/**
 * Automated Follow-Up Drip Sequences Engine.
 * Inspired by VM Sales OS & VMAI Sequences Modules.
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
	}

	/**
	 * Enroll a contact into an automated follow-up sequence.
	 *
	 * @param int    $contact_id
	 * @param string $trigger_event e.g. 'inquiry_abandoned'
	 * @return bool
	 */
	public static function enroll_contact( $contact_id, $trigger_event = 'inquiry_abandoned' ) {
		global $wpdb;
		$table_seq    = $wpdb->prefix . 'tc_agent_sequences';
		$table_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';

		// Find active sequence matching trigger
		$seq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_seq WHERE trigger_event = %s AND is_active = 1 LIMIT 1", $trigger_event ) );
		if ( ! $seq ) {
			return false;
		}

		// Check if already active in this sequence
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_enroll WHERE sequence_id = %d AND contact_id = %d AND status = 'active' LIMIT 1", $seq->id, $contact_id ) );
		if ( $exists ) {
			return false;
		}

		$steps = json_decode( $seq->steps_json, true ) ?: array();
		if ( empty( $steps ) ) {
			return false;
		}

		$delay_hours = (int) ( $steps[0]['delay_hours'] ?? 2 );
		$next_run    = gmdate( 'Y-m-d H:i:s', time() + ( $delay_hours * HOUR_IN_SECONDS ) );

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
	 * Process all due follow-up steps across enrolled contacts.
	 */
	public static function process_due_steps() {
		// Guardrails check before sequence dispatch
		if ( TC_Agents_Guardrails::is_kill_switch_active() ) {
			return;
		}

		global $wpdb;
		$table_seq     = $wpdb->prefix . 'tc_agent_sequences';
		$table_enroll  = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		$table_contact = $wpdb->prefix . 'tc_agent_contacts';

		$now = gmdate( 'Y-m-d H:i:s' );
		$due = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table_enroll WHERE status = 'active' AND next_run_at <= %s LIMIT 15", $now ),
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

			$steps = json_decode( $seq['steps_json'], true ) ?: array();
			$step_idx = (int) $row['current_step'];

			if ( ! isset( $steps[ $step_idx ] ) ) {
				$wpdb->update( $table_enroll, array( 'status' => 'completed' ), array( 'id' => $row['id'] ) );
				continue;
			}

			$step = $steps[ $step_idx ];
			$message_body = self::personalize_message( $step['message'] ?? '', $con );

			// Dispatch step via channel
			$sent = false;
			if ( 'whatsapp' === $seq['channel'] && ! empty( $con['phone'] ) ) {
				$sent = TC_Integration_WhatsApp::send_message( $con['phone'], $message_body );
			} elseif ( ! empty( $con['email'] ) ) {
				$subject = $step['subject'] ?? 'TripCosmos Expedition Follow-up';
				$sent = (bool) wp_mail( $con['email'], $subject, $message_body );
			}

			$next_step_idx = $step_idx + 1;
			if ( isset( $steps[ $next_step_idx ] ) ) {
				$next_delay = (int) ( $steps[ $next_step_idx ]['delay_hours'] ?? 24 );
				$wpdb->update(
					$table_enroll,
					array(
						'current_step' => $next_step_idx,
						'next_run_at'  => gmdate( 'Y-m-d H:i:s', time() + ( $next_delay * HOUR_IN_SECONDS ) ),
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
		}
	}

	/**
	 * Personalize message placeholders.
	 */
	private static function personalize_message( $template, array $contact ) {
		$name       = ! empty( $contact['name'] ) ? $contact['name'] : 'Traveler';
		$deal_val   = ! empty( $contact['deal_value'] ) ? '₹' . number_format( (float) $contact['deal_value'] ) : 'custom group package';
		$phone      = ! empty( $contact['phone'] ) ? $contact['phone'] : '';
		$email      = ! empty( $contact['email'] ) ? $contact['email'] : '';

		$replacements = array(
			'{name}'         => $name,
			'{{name}}'       => $name,
			'{Name}'         => $name,
			'{{Name}}'       => $name,
			'{deal_value}'   => $deal_val,
			'{{deal_value}}' => $deal_val,
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
