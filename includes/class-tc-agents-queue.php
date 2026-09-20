<?php
/**
 * Asynchronous Background Job Queue for TripCosmos Agents.
 *
 * Provides non-blocking background task execution for memory synthesis,
 * CRM syncing, sequence notifications, and knowledge embedding.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Queue {

	const CRON_HOOK = 'tc_agents_queue_worker';

	/**
	 * Boot the queue service, schedule cron runner, and register ajax worker.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_pending_jobs' ) );
		add_action( 'wp_ajax_nopriv_tc_agents_async_worker', array( __CLASS__, 'ajax_async_worker' ) );
		add_action( 'wp_ajax_tc_agents_async_worker', array( __CLASS__, 'ajax_async_worker' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'tc_agents_minute', self::CRON_HOOK );
		}
	}

	/**
	 * Add custom 1-minute interval for queue processing.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['tc_agents_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute (TripCosmos Agents)', 'tripcosmos-agents' ),
		);
		return $schedules;
	}

	/**
	 * Push a new job onto the queue.
	 *
	 * @param string $job_type      Identifier e.g. 'refresh_memory', 'sync_crm', 'index_document'.
	 * @param array  $payload       Job parameters.
	 * @param int    $delay_seconds Delay in seconds before job becomes eligible.
	 * @return int Inserted job ID.
	 */
	public static function push( $job_type, $payload = array(), $delay_seconds = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_jobs';

		$run_at = gmdate( 'Y-m-d H:i:s', time() + max( 0, (int) $delay_seconds ) );

		$wpdb->insert(
			$table,
			array(
				'job_type'   => sanitize_key( $job_type ),
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'pending',
				'attempts'   => 0,
				'run_at'     => $run_at,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$job_id = (int) $wpdb->insert_id;

		// Immediately kick off background worker asynchronously if zero delay
		if ( 0 === (int) $delay_seconds ) {
			self::spawn_worker();
		}

		return $job_id;
	}

	/**
	 * Fire a non-blocking asynchronous HTTP request to process the queue immediately.
	 */
	public static function spawn_worker() {
		$url = admin_url( 'admin-ajax.php' );

		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.01, // Fast non-blocking fire-and-forget
				'blocking'  => false,
				'sslverify' => false,
				'body'      => array(
					'action' => 'tc_agents_async_worker',
					'nonce'  => wp_create_nonce( 'tc_agents_queue_nonce' ),
				),
			)
		);
	}

	/**
	 * AJAX endpoint for asynchronous background worker.
	 */
	public static function ajax_async_worker() {
		// Disable execution time limit for background processing
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		self::process_pending_jobs();
		wp_die();
	}

	/**
	 * Claim and execute pending jobs batch.
	 *
	 * @param int $batch_size
	 * @return int Number of processed jobs.
	 */
	public static function process_pending_jobs( $batch_size = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_jobs';

		$now = gmdate( 'Y-m-d H:i:s' );

		// Fetch pending jobs whose run_at has passed
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, job_type, payload, attempts FROM $table
				 WHERE status = 'pending' AND run_at <= %s AND attempts < 4
				 ORDER BY id ASC LIMIT %d",
				$now,
				$batch_size
			),
			ARRAY_A
		);

		if ( empty( $jobs ) ) {
			return 0;
		}

		$processed = 0;
		foreach ( $jobs as $job ) {
			// Atomically lock job to running
			$updated = $wpdb->update(
				$table,
				array(
					'status'     => 'running',
					'attempts'   => (int) $job['attempts'] + 1,
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id'     => (int) $job['id'],
					'status' => 'pending',
				)
			);

			if ( ! $updated ) {
				continue;
			}

			$payload = json_decode( $job['payload'], true ) ?: array();
			$error   = null;

			try {
				self::execute_job( $job['job_type'], $payload );
			} catch ( \Throwable $e ) {
				$error = $e->getMessage();
			}

			if ( $error ) {
				$wpdb->update(
					$table,
					array(
						'status'     => ( (int) $job['attempts'] + 1 >= 4 ) ? 'failed' : 'pending',
						'last_error' => substr( $error, 0, 1000 ),
						'run_at'     => gmdate( 'Y-m-d H:i:s', time() + 120 ), // retry in 2 mins
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => (int) $job['id'] )
				);
			} else {
				$wpdb->update(
					$table,
					array(
						'status'     => 'completed',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => (int) $job['id'] )
				);
				$processed++;
			}
		}

		return $processed;
	}

	/**
	 * Execute specific job handler.
	 *
	 * @param string $job_type
	 * @param array  $payload
	 */
	private static function execute_job( $job_type, $payload ) {
		switch ( $job_type ) {
			case 'refresh_memory':
				if ( ! empty( $payload['contact_id'] ) ) {
					TC_Agent_Memory::synthesize_memory( (int) $payload['contact_id'] );
				}
				break;

			case 'sync_crm':
				if ( ! empty( $payload['contact'] ) ) {
					$contact = $payload['contact'];
					// FluentCRM
					if ( class_exists( 'TC_Integration_FluentCRM' ) ) {
						TC_Integration_FluentCRM::sync_lead( $contact );
					}
					// TwentyCRM
					if ( class_exists( 'TC_Integration_TwentyCRM' ) ) {
						TC_Integration_TwentyCRM::push_lead( $contact );
					}
					// Google Sheets
					if ( class_exists( 'TC_Integration_Sheets' ) ) {
						TC_Integration_Sheets::append_lead( $contact );
					}
				}
				break;

			case 'index_document':
				if ( ! empty( $payload['document_id'] ) && ! empty( $payload['content'] ) ) {
					TC_Agent_Knowledge::index_document_chunks( (int) $payload['document_id'], $payload['content'] );
				}
				break;

			case 'voice_trigger':
				if ( ! empty( $payload['lead'] ) && class_exists( 'TC_Integration_Voice' ) ) {
					TC_Integration_Voice::maybe_dispatch_call( $payload['lead'] );
				}
				break;

			case 'sync_models':
				if ( class_exists( 'TC_AI_Router' ) ) {
					TC_AI_Router::get_instance()->sync_all_models( ! empty( $payload['force'] ) );
				}
				break;

			case 'import_b2b':
				if ( class_exists( 'TC_Integration_Google_Business' ) ) {
					$res = TC_Integration_Google_Business::cron_import();
					if ( '1' === (string) get_option( 'tc_agents_b2b_auto_outreach', '0' ) ) {
						self::push( 'outreach_b2b_batch', array( 'limit' => 10 ), 60 );
					}
				}
				break;

			case 'outreach_b2b_batch':
				if ( class_exists( 'TC_Agent_Tools' ) ) {
					global $wpdb;
					$table = $wpdb->prefix . 'tc_agent_agencies';
					$limit = min( 25, max( 1, (int) ( $payload['limit'] ?? 10 ) ) );
					$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $table WHERE status = 'new' ORDER BY rating DESC, id ASC LIMIT %d", $limit ), ARRAY_A );
					foreach ( (array) $rows as $r ) {
						TC_Agent_Tools::execute( 'outreach_b2b_agency', array( 'agency_id' => (int) $r['id'], 'channel' => 'both' ) );
					}
				}
				break;

			default:
				do_action( "tc_agents_job_{$job_type}", $payload );
				break;
		}
	}
}
