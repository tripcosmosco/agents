<?php
/**
 * Fired during plugin deactivation.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Deactivator {

	/**
	 * Run deactivation cleanup.
	 */
	public static function deactivate() {
		// Clear scheduled cron hooks
		wp_clear_scheduled_hook( 'tc_agents_health_check_cron' );
		wp_clear_scheduled_hook( 'tc_agents_prune_logs_cron' );
		wp_clear_scheduled_hook( 'tc_agents_run_sequences_cron' );
	}
}

