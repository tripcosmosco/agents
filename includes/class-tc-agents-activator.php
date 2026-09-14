<?php
/**
 * Fired during plugin activation.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Activator {

	/**
	 * Run activation routine: create database tables and initialize default settings.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::seed_default_agent();
	}

	/**
	 * Create or update custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Conversations table
		$table_conversations = $wpdb->prefix . 'tc_agent_conversations';
		$sql_conversations   = "CREATE TABLE $table_conversations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			contact_id bigint(20) unsigned DEFAULT 0,
			channel varchar(30) NOT NULL DEFAULT 'web',
			agent_id varchar(50) NOT NULL DEFAULT 'default',
			status varchar(30) NOT NULL DEFAULT 'active',
			metadata longtext DEFAULT NULL,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_message_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY contact_id (contact_id),
			KEY channel (channel),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_conversations );

		// 2. Messages table
		$table_messages = $wpdb->prefix . 'tc_agent_messages';
		$sql_messages   = "CREATE TABLE $table_messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			content longtext NOT NULL,
			tool_calls longtext DEFAULT NULL,
			tool_results longtext DEFAULT NULL,
			provider_used varchar(50) DEFAULT '',
			prompt_tokens int(11) DEFAULT 0,
			completion_tokens int(11) DEFAULT 0,
			latency_ms int(11) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY role (role),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_messages );

		// 3. Contacts & Pipeline Leads table
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';
		$sql_contacts   = "CREATE TABLE $table_contacts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned DEFAULT 0,
			email varchar(100) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			name varchar(100) DEFAULT NULL,
			source_channel varchar(30) DEFAULT 'web',
			stage varchar(30) NOT NULL DEFAULT 'inquiry',
			score int(11) NOT NULL DEFAULT 50,
			deal_value decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT 'INR',
			fluentcrm_id bigint(20) unsigned DEFAULT 0,
			twentycrm_id varchar(100) DEFAULT '',
			meta_data longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY wp_user_id (wp_user_id),
			KEY email (email),
			KEY phone (phone),
			KEY stage (stage),
			KEY score (score)
		) $charset_collate;";
		dbDelta( $sql_contacts );

		// 4. Agent Personas / Config table
		$table_personas = $wpdb->prefix . 'tc_agent_personas';
		$sql_personas   = "CREATE TABLE $table_personas (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(50) NOT NULL,
			name varchar(100) NOT NULL,
			system_prompt longtext NOT NULL,
			greeting_message text DEFAULT '',
			channels varchar(255) NOT NULL DEFAULT 'web,whatsapp',
			allowed_tools longtext DEFAULT '',
			temperature decimal(3,2) NOT NULL DEFAULT 0.70,
			routing_override varchar(50) DEFAULT '',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_personas );

		// 5. Audit Log table
		$table_audit = $wpdb->prefix . 'tc_agent_audit_log';
		$sql_audit   = "CREATE TABLE $table_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			agent_id varchar(50) DEFAULT '',
			channel varchar(30) DEFAULT '',
			details longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_audit );

		// 6. Cross-Session Traveler Memory table
		$table_memory = $wpdb->prefix . 'tc_agent_lead_memory';
		$sql_memory   = "CREATE TABLE $table_memory (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			summary text DEFAULT NULL,
			facts longtext DEFAULT NULL,
			preferences longtext DEFAULT NULL,
			objections longtext DEFAULT NULL,
			next_best_action varchar(255) DEFAULT '',
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY contact_id (contact_id)
		) $charset_collate;";
		dbDelta( $sql_memory );

		// 7. Knowledge Base Document & FAQ Repository
		$table_kb = $wpdb->prefix . 'tc_agent_knowledge';
		$sql_kb   = "CREATE TABLE $table_kb (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(200) NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'faq',
			content longtext NOT NULL,
			tags varchar(255) DEFAULT '',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY is_active (is_active)
		) $charset_collate;";
		dbDelta( $sql_kb );

		// 8. Follow-up Sequences table
		$table_seq = $wpdb->prefix . 'tc_agent_sequences';
		$sql_seq   = "CREATE TABLE $table_seq (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			channel varchar(30) NOT NULL DEFAULT 'whatsapp',
			trigger_event varchar(50) NOT NULL DEFAULT 'inquiry_abandoned',
			steps_json longtext NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY is_active (is_active)
		) $charset_collate;";
		dbDelta( $sql_seq );

		// 9. Sequence Enrollments table
		$table_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		$sql_enroll   = "CREATE TABLE $table_enroll (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sequence_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			current_step int(11) NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'active',
			next_run_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY sequence_id (sequence_id),
			KEY contact_id (contact_id),
			KEY status (status),
			KEY next_run_at (next_run_at)
		) $charset_collate;";
		dbDelta( $sql_enroll );
	}

	/**
	 * Initialize default options.
	 */
	private static function set_default_options() {
		// Guardrails
		add_option( 'tc_agents_kill_switch', '0' );
		add_option( 'tc_agents_rate_limit_hourly', '30' );
		add_option( 'tc_agents_whatsapp_daily_limit', '100' );
		add_option( 'tc_agents_voice_daily_limit', '10' );
		add_option( 'tc_agents_voice_enabled', '0' );
		add_option( 'tc_agents_require_human_approval', '1' );

		// AI Routing Defaults
		add_option( 'tc_agents_provider_priority', array( 'aipuffer', 'openrouter', 'omniroute', 'vmstudio' ) );
		add_option( 'tc_agents_circuit_breaker_threshold', '2' );
		add_option( 'tc_agents_timeout_seconds', '8' );

		// Providers config defaults
		add_option( 'tc_agents_openrouter_api_key', '' );
		add_option( 'tc_agents_openrouter_model', 'anthropic/claude-3.5-sonnet' );
		add_option( 'tc_agents_omniroute_base_url', '' );
		add_option( 'tc_agents_omniroute_api_key', '' );
		add_option( 'tc_agents_omniroute_model', '' );
		add_option( 'tc_agents_vmstudio_api_key', '' );
		add_option( 'tc_agents_vmstudio_base_url', 'https://ai.vmstudio.digital/v1' );

		// Integrations
		add_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' );
		add_option( 'tc_agents_whatsapp_token', '' );
		add_option( 'tc_agents_whatsapp_webhook_secret', wp_generate_password( 24, false ) );
		add_option( 'tc_agents_twentycrm_url', 'https://crm.vmstudio.digital' );
		add_option( 'tc_agents_twentycrm_api_key', '' );
		add_option( 'tc_agents_sheets_enabled', '0' );
		add_option( 'tc_agents_human_whatsapp_number', '+919876543210' );
		add_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) );

		// Frontend Widget
		add_option( 'tc_agents_widget_enabled', '1' );
		add_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' );
		add_option( 'tc_agents_widget_greeting', 'Hi there! Looking for an unforgettable trek or adventure package? How can I help you plan today?' );
		add_option( 'tc_agents_widget_primary_color', '#0ea5e9' );
	}

	/**
	 * Seed the default TripCosmos agent persona.
	 */
	private static function seed_default_agent() {
		global $wpdb;
		$table_personas = $wpdb->prefix . 'tc_agent_personas';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", 'tripcosmos-guide' ) );
		if ( ! $exists ) {
			$wpdb->insert(
				$table_personas,
				array(
					'slug'             => 'tripcosmos-guide',
					'name'             => 'TripCosmos Expedition Guide',
					'system_prompt'    => "You are the official TripCosmos AI Expedition Guide & Mountain Specialist for TripCosmos.co.\n" .
										  "Your primary mission is to help travelers discover, explore, and plan unforgettable Himalayan treks and outdoor tours across India, while proactively qualifying high-intent leads and securing bookings.\n\n" .
										  "SALES INTELLIGENCE & CONVERSATION FRAMEWORK:\n" .
										  "1. DISCOVERY: When travelers express interest in a region or trek, immediately use 'search_trips' to fetch authentic packages, itineraries, difficulties, and pricing. Be outdoorsy, welcoming, and safety-conscious.\n" .
										  "2. CONSULTATIVE RECOMMENDATIONS: Recommend specific departures, altitude profiles, and gear requirements. Reference official policies and mountain safety guidelines.\n" .
										  "3. LEAD CAPTURE & CRM SYNC: Whenever a traveler provides their Name, Phone/WhatsApp number, or Email, or shows booking intent, you MUST immediately invoke the 'sync_lead_crm' tool to register them in Fluent CRM, Twenty CRM, and the TripCosmos pipeline. Do not wait for the end of the chat.\n" .
										  "4. GROUP DEALS & ESTIMATIONS: If travelers ask for group discounts or corporate quotations (4+ travelers), quote based on group tiers and save their requirements via 'sync_lead_crm'. Never exceed the 10% discount margin ceiling.\n" .
										  "5. HUMAN ESCALATION: If the traveler requests a customized custom departure date, flight booking, complex multi-pass expedition, or explicit human agent assistance, invoke the 'request_human_handoff' tool so they can connect with our human specialist on WhatsApp.\n" .
										  "6. SAFETY & COMPLIANCE: Never commit to financial payments or card charges directly in chat — explain that our human travel desk provides secure official booking links.",
					'greeting_message' => "Welcome to TripCosmos! Looking for an unforgettable mountain trek, Himalayan expedition, or customized group adventure? How can I help you plan your journey?",
					'channels'         => 'web,whatsapp',
					'allowed_tools'    => wp_json_encode( array( 'search_trips', 'lookup_contact_crm', 'sync_lead_crm', 'request_human_handoff', 'query_pricing_sheet' ) ),
					'temperature'      => 0.70,
					'is_active'        => 1,
				)
			);

		}
	}
}
