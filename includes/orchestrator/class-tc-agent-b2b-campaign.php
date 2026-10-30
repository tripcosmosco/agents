<?php
/**
 * Automated B2B Festival Campaign Engine for Dev Deepawali 2026 & Spiritual Circuits.
 * Handles Scheduled Cron Runs, WhatsApp Number Pre-Validation, Multi-Channel Dispatch, and Drip Enrollment.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_B2B_Campaign {

	const CRON_HOOK = 'tc_agents_b2b_daily_campaign';

	/**
	 * Initialize the B2B Campaign Automation.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_campaign' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_next_run();
		}

		// Also hook to B2B import completion so newly ingested agencies are seamlessly queued
		add_action( 'tc_agents_b2b_agencies_imported', array( __CLASS__, 'on_agencies_imported' ), 10, 2 );
	}

	/**
	 * Schedule the daily campaign run at 10:30 AM IST.
	 */
	public static function schedule_next_run() {
		try {
			$tz   = new DateTimeZone( 'Asia/Kolkata' );
			$now  = new DateTime( 'now', $tz );
			$next = new DateTime( 'today 10:30:00', $tz );

			if ( $now >= $next ) {
				$next->modify( '+1 day' );
			}

			wp_schedule_event( $next->getTimestamp(), 'daily', self::CRON_HOOK );
		} catch ( Exception $e ) {
			// Fallback: 12 hours from now
			wp_schedule_event( time() + ( 12 * HOUR_IN_SECONDS ), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Called automatically when new agencies are imported via Google Business / Feeder cron.
	 *
	 * @param int    $count Number of imported agencies.
	 * @param string $city  City imported.
	 */
	public static function on_agencies_imported( $count = 0, $city = '' ) {
		if ( '1' === (string) get_option( 'tc_agents_b2b_auto_outreach', '1' ) && class_exists( 'TC_Agents_Queue' ) ) {
			TC_Agents_Queue::push( 'outreach_b2b_batch', array( 'limit' => 10, 'city' => $city ), 120 );
		}
	}

	/**
	 * Main automated campaign runner called by daily WP Cron.
	 *
	 * @param array $args Options { 'limit' => 10, 'city' => '', 'force' => false, 'ignore_quiet_hours' => false }
	 * @return array
	 */
	public static function run_scheduled_campaign( array $args = array() ) {
		// Send Governor Quiet Hours Protection (TRAI compliance: 9 PM to 8:30 AM IST)
		$ignore_quiet = ! empty( $args['ignore_quiet_hours'] );
		if ( ! $ignore_quiet && class_exists( 'TC_Agents_Guardrails' ) && TC_Agents_Guardrails::is_quiet_hours() ) {
			return array( 'ok' => false, 'reason' => 'quiet_hours_active', 'dispatched' => 0 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_agencies';
		$limit = max( 1, min( 25, (int) ( $args['limit'] ?? get_option( 'tc_agents_b2b_daily_campaign_limit', 10 ) ) ) );
		$city  = sanitize_text_field( $args['city'] ?? '' );
		$force = ! empty( $args['force'] );

		$where = $force
			? "WHERE phone <> ''"
			: "WHERE (last_outreach_at IS NULL OR last_outreach_at = '0000-00-00 00:00:00') AND phone <> ''";

		if ( ! empty( $city ) ) {
			$where .= $wpdb->prepare( ' AND city = %s', $city );
		}

		$agencies = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY rating DESC, id ASC LIMIT $limit", ARRAY_A ) ?: array();
		if ( empty( $agencies ) ) {
			return array( 'ok' => true, 'dispatched' => 0, 'message' => 'No matching uncontacted B2B agencies found.' );
		}

		// Pre-validate WhatsApp presence in bulk via Evolution API to filter out landlines & inactive numbers
		$phone_map = array();
		foreach ( $agencies as $a ) {
			$raw_phone = class_exists( 'TC_Contact_Service' ) ? TC_Contact_Service::normalize_phone( $a['phone'] ) : $a['phone'];
			$digits    = preg_replace( '/[^0-9]/', '', (string) $raw_phone );
			if ( strlen( $digits ) >= 10 ) {
				$phone_map[ (int) $a['id'] ] = $digits;
			}
		}

		$valid_wa = self::filter_valid_whatsapp_numbers( array_values( $phone_map ) );

		$dispatched = 0;
		$results    = array();

		foreach ( $agencies as $agency ) {
			$ag_id    = (int) $agency['id'];
			$phone_dg = $phone_map[ $ag_id ] ?? '';
			$has_wa   = ! empty( $phone_dg ) && in_array( $phone_dg, $valid_wa, true );

			$agency_name = ! empty( $agency['name'] ) ? $agency['name'] : 'Partner';

			$pitch_text = sprintf(
				"Namaste %s Team! 🙏 Greetings from TripCosmos Varanasi.\n\nAre your clients inquiring about *Dev Deepawali 2026 in Varanasi (Kartik Poornima)*?\n\nAs your verified on-ground Destination Management Company (DMC), we have pre-allocated ground inventory exclusively for our B2B travel partners:\n• *Reserved Private Bajra Boats:* Prime Dashashwamedh Ghat Maha Aarti viewing decks (Zero line waiting)\n• *VIP Sugam Darshan Passes:* Priority Kashi Vishwanath Temple entry via Gate 4\n• *Dedicated AC Fleet:* Innova Crysta, Force Urbania & Tempo Travellers (Varanasi, Ayodhya & Prayagraj)\n• *100%% White-Label Execution:* Executed seamlessly under *your agency brand* with your client welcome placards\n\n👉 *View 2N3D Dev Deepawali Itinerary:* https://tripcosmos.co/tour-packages/dev-deepawali-varanasi/\n\nMay we share our complete 2026 B2B Net Wholesale Tariff Sheet and Bajra Allotment slabs on this chat?",
				$agency_name
			);

			$wa_sent    = false;
			$email_sent = false;
			$wa_error   = null;
			$email_error = null;

			// 1. WhatsApp Dispatch (if verified active on WhatsApp)
			if ( $has_wa && class_exists( 'TC_Integration_Evolution' ) && TC_Integration_Evolution::is_configured() ) {
				$context = $ignore_quiet ? 'conversational' : 'broadcast';
				$res_wa  = TC_Integration_Evolution::send_message( $phone_dg, $pitch_text, $context );
				if ( is_wp_error( $res_wa ) ) {
					$wa_error = $res_wa->get_error_message();
				} else {
					$wa_sent = true;
				}
			}

			// 2. Responsive Email Dispatch via Brevo
			if ( ! empty( $agency['email'] ) && class_exists( 'TC_Integration_Brevo' ) ) {
				$res_em     = TC_Integration_Brevo::send_b2b_outreach( $agency );
				if ( is_wp_error( $res_em ) ) {
					$email_error = $res_em->get_error_message();
				} else {
					$email_sent = true;
				}
			}

			// 3. Status Update & Sequence Drip Enrollment
			if ( $wa_sent || $email_sent ) {
				$wpdb->update(
					$table,
					array(
						'status'           => 'contacted',
						'last_outreach_at' => current_time( 'mysql' ),
						'updated_at'       => current_time( 'mysql' ),
					),
					array( 'id' => $ag_id )
				);
				$dispatched++;

				// Auto-enroll contact into Sequence 6 (dev_deepawali_b2b: Touch 2 at +48h & Touch 3 at +96h)
				if ( class_exists( 'TC_Contact_Service' ) && class_exists( 'TC_Agent_Sequences' ) ) {
					$existing = TC_Contact_Service::find( $agency['email'] ?? '', $phone_dg );
					$cid      = $existing ? (int) $existing['id'] : 0;
					if ( ! $cid ) {
						$res_c = TC_Contact_Service::upsert(
							array(
								'name'           => $agency_name,
								'phone'          => $phone_dg,
								'email'          => ! empty( $agency['email'] ) ? $agency['email'] : ( 'agency-' . $ag_id . '@b2b.tripcosmos.co' ),
								'destination'    => 'Dev Deepawali Varanasi 2026',
								'source_channel' => 'b2b_agency',
								'stage'          => 'b2b_partner',
								'score'          => 85,
							),
							array( 'sync' => false )
						);
						$cid = isset( $res_c['id'] ) ? (int) $res_c['id'] : 0;
					}
					if ( $cid > 0 ) {
						TC_Agent_Sequences::enroll_contact( $cid, 'dev_deepawali_b2b' );
					}
				}

				// Ensure List 8 + Tag sync in FluentCRM
				if ( class_exists( 'TC_Integration_FluentCRM' ) && ! empty( $agency['email'] ) ) {
					TC_Integration_FluentCRM::sync_lead(
						array(
							'name'        => $agency_name,
							'email'       => $agency['email'],
							'phone'       => $phone_dg,
							'destination' => $agency['city'] ?: 'Varanasi',
							'channel'     => 'b2b_partner',
							'stage'       => 'b2b_partner',
							'score'       => 85,
						)
					);
				}
			}

			$results[] = array(
				'id'          => $ag_id,
				'name'        => $agency_name,
				'city'        => $agency['city'],
				'whatsapp'    => $wa_sent,
				'email'       => $email_sent,
				'has_wa_num'  => $has_wa,
				'wa_error'    => $wa_error,
				'email_error' => $email_error,
			);
		}

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'b2b_campaign_batch_dispatched',
				'info',
				array(
					'dispatched' => $dispatched,
					'total_read' => count( $agencies ),
					'results'    => $results,
				),
				'',
				'campaign'
			);
		}

		return array(
			'ok'         => true,
			'dispatched' => $dispatched,
			'total_read' => count( $agencies ),
			'results'    => $results,
		);
	}

	/**
	 * Pre-validate an array of phone digits against Evolution API to identify real WhatsApp accounts.
	 *
	 * @param array $numbers Array of digits like ['919899123456', ...].
	 * @return array Array of verified digits that exist on WhatsApp.
	 */
	public static function filter_valid_whatsapp_numbers( array $numbers ) {
		if ( empty( $numbers ) ) {
			return array();
		}

		$api_url  = 'https://wa.vmstudio.digital';
		$api_key  = 'bebf8554b69b4eff665465956069890dadeb84547a964ba0ac129e43e98576a2';
		$instance = 'TripCosmos.co';

		$resp = wp_remote_post(
			trailingslashit( $api_url ) . 'chat/whatsappNumbers/' . $instance,
			array(
				'headers' => array(
					'apikey'       => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array( 'numbers' => array_values( array_unique( $numbers ) ) ) ),
				'timeout' => 12,
			)
		);

		if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			// Fallback: assume all numbers are valid if check fails
			return $numbers;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $body ) ) {
			return $numbers;
		}

		$valid = array();
		foreach ( $body as $item ) {
			if ( ! empty( $item['exists'] ) && ! empty( $item['number'] ) ) {
				$valid[] = (string) $item['number'];
			}
		}

		return $valid;
	}
}
