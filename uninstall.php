<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if preserve data option is enabled (defaults to true to protect conversations)
$preserve_data = get_option( 'tc_agents_preserve_data_on_uninstall', '1' );

if ( '0' === $preserve_data ) {
	global $wpdb;

	// Drop custom tables only if user explicitly set preservation to false
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tc_agent_messages" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tc_agent_conversations" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tc_agent_contacts" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tc_agent_personas" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tc_agent_audit_log" );

	// Delete plugin options
	$options = array(
		'tc_agents_kill_switch',
		'tc_agents_rate_limit_hourly',
		'tc_agents_whatsapp_daily_limit',
		'tc_agents_voice_daily_limit',
		'tc_agents_voice_enabled',
		'tc_agents_require_human_approval',
		'tc_agents_provider_priority',
		'tc_agents_circuit_breaker_threshold',
		'tc_agents_timeout_seconds',
		'tc_agents_openrouter_api_key',
		'tc_agents_openrouter_model',
		'tc_agents_omniroute_base_url',
		'tc_agents_omniroute_api_key',
		'tc_agents_omniroute_model',
		'tc_agents_vmstudio_api_key',
		'tc_agents_vmstudio_base_url',
		'tc_agents_whatsapp_api_url',
		'tc_agents_whatsapp_token',
		'tc_agents_whatsapp_webhook_secret',
		'tc_agents_twentycrm_url',
		'tc_agents_twentycrm_api_key',
		'tc_agents_sheets_enabled',
		'tc_agents_human_whatsapp_number',
		'tc_agents_human_notification_email',
		'tc_agents_widget_enabled',
		'tc_agents_widget_title',
		'tc_agents_widget_greeting',
		'tc_agents_widget_primary_color',
	);

	foreach ( $options as $opt ) {
		delete_option( $opt );
	}
}
