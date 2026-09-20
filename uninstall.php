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

	// Drop all custom tables created by TripCosmos Agents
	$tables = array(
		"{$wpdb->prefix}tc_agent_messages",
		"{$wpdb->prefix}tc_agent_conversations",
		"{$wpdb->prefix}tc_agent_contacts",
		"{$wpdb->prefix}tc_agent_personas",
		"{$wpdb->prefix}tc_agent_audit_log",
		"{$wpdb->prefix}tc_agent_lead_memory",
		"{$wpdb->prefix}tc_agent_knowledge",
		"{$wpdb->prefix}tc_agent_sequences",
		"{$wpdb->prefix}tc_agent_sequence_enrollments",
		"{$wpdb->prefix}tc_agent_kb_chunks",
		"{$wpdb->prefix}tc_agent_jobs",
		"{$wpdb->prefix}tc_agent_agencies",
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete all plugin options and settings
	$options = array(
		'tc_agents_version',
		'tc_agents_db_version',
		'tc_agents_preserve_data_on_uninstall',
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
		'tc_agents_gateway_base_url',
		'tc_agents_gateway_api_key',
		'tc_agents_gateway_model',
		'tc_agents_gemini_api_key',
		'tc_agents_gemini_model',
		'tc_agents_aipuffer_enabled',
		'tc_agents_aipuffer_bridge_url',
		'tc_agents_aipuffer_api_key',
		'tc_agents_aipuffer_bot_id',
		'tc_agents_whatsapp_mode',
		'tc_agents_whatsapp_api_url',
		'tc_agents_whatsapp_token',
		'tc_agents_whatsapp_webhook_secret',
		'tc_agents_evolution_base_url',
		'tc_agents_evolution_instance',
		'tc_agents_evolution_api_key',
		'tc_agents_twentycrm_url',
		'tc_agents_twentycrm_api_key',
		'tc_agents_brevo_api_key',
		'tc_agents_brevo_sender_email',
		'tc_agents_brevo_sender_name',
		'tc_agents_google_places_api_key',
		'tc_agents_b2b_import_cities',
		'tc_agents_b2b_auto_outreach',
		'tc_agents_sheets_enabled',
		'tc_agents_sheets_webhook_url',
		'tc_agents_voice_provider',
		'tc_agents_voice_api_key',
		'tc_agents_voice_assistant_id',
		'tc_agents_voice_phone_number_id',
		'tc_agents_human_whatsapp_number',
		'tc_agents_human_notification_email',
		'tc_agents_widget_enabled',
		'tc_agents_widget_title',
		'tc_agents_widget_subtitle',
		'tc_agents_widget_greeting',
		'tc_agents_widget_primary_color',
		'tc_agents_widget_secondary_color',
		'tc_agents_widget_side',
		'tc_agents_bot_avatar',
		'tc_agents_bot_avatar_emoji',
		'tc_agents_starter_prompts',
		'tc_agents_launcher_teaser_text',
		'tc_agents_discount_ceiling',
		'tc_agents_mobile_api_token',
		'tc_agents_vault',
		'tc_agents_aipuffer_base_url',
		'tc_agents_aipuffer_bots_cache',
		'tc_agents_aipuffer_bots_synced_at',
		'tc_agents_omniroute_api_key',
		'tc_agents_omniroute_model',
		'tc_agents_omniroute_base_url',
		'tc_agents_vmstudio_api_key',
		'tc_agents_vmstudio_model',
		'tc_agents_vmstudio_base_url',
		'tc_agents_gateway_models_cache',
		'tc_agents_gateway_models_synced_at',
		'tc_agents_b2b_last_import',
		'tc_agents_models_last_sync',
		'tc_agents_vector_dimensions',
		'tc_agents_github_token',
	);

	foreach ( $options as $opt ) {
		delete_option( $opt );
	}

	// Delete transients
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%tc_models_%' OR option_name LIKE '%tc_agents_%' OR option_name LIKE '%tc_rate_%'" );
}
