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

		// 10. Knowledge Base Vector Chunks (Semantic Embeddings)
		$table_kb_chunks = $wpdb->prefix . 'tc_agent_kb_chunks';
		$sql_kb_chunks   = "CREATE TABLE $table_kb_chunks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			chunk_index int(10) unsigned NOT NULL DEFAULT 0,
			content longtext NULL,
			embedding longblob NULL,
			dimensions smallint(5) unsigned NOT NULL DEFAULT 0,
			token_count int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY doc_chunk (document_id, chunk_index),
			KEY document_id (document_id),
			FULLTEXT KEY chunk_ft (content)
		) $charset_collate;";
		dbDelta( $sql_kb_chunks );

		// 11. Non-Blocking Async Background Jobs Queue
		$table_jobs = $wpdb->prefix . 'tc_agent_jobs';
		$sql_jobs   = "CREATE TABLE $table_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(60) NOT NULL,
			payload longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			run_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status_run (status, run_at),
			KEY job_type (job_type)
		) $charset_collate;";
		dbDelta( $sql_jobs );

		// 12. B2B agencies prospect table (Google Business import).
		$table_agencies = $wpdb->prefix . 'tc_agent_agencies';
		$sql_agencies   = "CREATE TABLE $table_agencies (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			city varchar(100) DEFAULT '',
			address varchar(255) DEFAULT '',
			phone varchar(50) DEFAULT '',
			email varchar(150) DEFAULT '',
			website varchar(255) DEFAULT '',
			rating decimal(3,2) DEFAULT NULL,
			place_id varchar(150) DEFAULT '',
			lat decimal(10,7) DEFAULT NULL,
			lng decimal(10,7) DEFAULT NULL,
			source varchar(30) DEFAULT 'google',
			status varchar(30) NOT NULL DEFAULT 'new',
			last_outreach_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY city (city),
			KEY status (status),
			KEY place_id (place_id),
			KEY phone (phone)
		) $charset_collate;";
		dbDelta( $sql_agencies );
	}

	/**
	 * Run upgrade routine automatically when plugin version is bumped.
	 */
	public static function maybe_upgrade() {
		$installed_ver = get_option( 'tc_agents_db_version', '0' );
		if ( version_compare( $installed_ver, TC_AGENTS_VERSION, '<' ) ) {
			self::create_tables();
			self::set_default_options();
			self::seed_default_agent();
			update_option( 'tc_agents_db_version', TC_AGENTS_VERSION );
		}
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

		// AI Routing Defaults (canonical: openrouter, gateway, gemini)
		$cur = get_option( 'tc_agents_provider_priority', array( 'openrouter', 'gateway', 'gemini' ) );
		if ( ! is_array( $cur ) || empty( $cur ) ) { $cur = array( 'openrouter', 'gateway', 'gemini' ); }
		$map = array( 'aipuffer' => 'gateway', 'omniroute' => 'gateway', 'vmstudio' => 'gateway' );
		$cur = array_values( array_unique( array_map( function( $s ) use ( $map ) { return $map[ $s ] ?? $s; }, $cur ) ) );
		update_option( 'tc_agents_provider_priority', $cur );
		add_option( 'tc_agents_circuit_breaker_threshold', '2' );
		add_option( 'tc_agents_timeout_seconds', '8' );

		// Providers config defaults (canonical only; legacy keys kept for migration reads)
		add_option( 'tc_agents_openrouter_api_key', '' );
		add_option( 'tc_agents_openrouter_model', 'anthropic/claude-3.5-sonnet' );
		add_option( 'tc_agents_gateway_base_url', 'https://ai.vmstudio.digital/v1' );
		add_option( 'tc_agents_gateway_model', 'default' );
		add_option( 'tc_agents_gemini_model', 'gemini-2.0-flash' );

		// Embeddings & Vector Search
		add_option( 'tc_agents_enable_vector_search', '1' );
		add_option( 'tc_agents_embed_model', 'text-embedding-3-small' );

		// Integrations (WhatsApp dual-mode, Brevo, Google Places/B2B)
		add_option( 'tc_agents_whatsapp_mode', 'legacy' );
		add_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' );
		add_option( 'tc_agents_whatsapp_token', '' );
		add_option( 'tc_agents_whatsapp_webhook_secret', wp_generate_password( 24, false ) );
		add_option( 'tc_agents_twentycrm_url', 'https://crm.vmstudio.digital' );
		add_option( 'tc_agents_twentycrm_api_key', '' );
		add_option( 'tc_agents_sheets_enabled', '0' );
		add_option( 'tc_agents_brevo_sender_name', 'TripCosmos' );
		add_option( 'tc_agents_b2b_import_cities', 'Varanasi, Delhi, Mumbai, Jaipur, Kolkata, Chennai, Bengaluru, Hyderabad, Ahmedabad, Lucknow' );
		add_option( 'tc_agents_b2b_auto_outreach', '0' );
		add_option( 'tc_agents_human_whatsapp_number', '+919876543210' );
		add_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) );

		// Frontend Widget
		add_option( 'tc_agents_widget_enabled', '1' );
		add_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' );
		add_option( 'tc_agents_widget_greeting', 'Hi there! Looking for an unforgettable trek or adventure package? How can I help you plan today?' );
		add_option( 'tc_agents_widget_primary_color', '#0ea5e9' );
	}

	/**
	 * Seed default TripCosmos agent personas.
	 */
	private static function seed_default_agent() {
		global $wpdb;
		$table_personas = $wpdb->prefix . 'tc_agent_personas';

		// 1. Varanasi Spiritual & Heritage Tour Specialist
		$exists_guide = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", 'tripcosmos-guide' ) );
		if ( ! $exists_guide ) {
			$wpdb->insert(
				$table_personas,
				array(
					'slug'             => 'tripcosmos-guide',
					'name'             => 'TripCosmos Tour & Pilgrimage Specialist',
					'system_prompt'    => "You are the official TripCosmos AI Tour Specialist for TripCosmos.co, a premier travel agency headquartered in Varanasi (Kashi), Uttar Pradesh.\n" .
										  "TripCosmos offers customized tour packages, hotel bookings, outstation cabs, and private boat rides across: Varanasi, Ayodhya, Prayagraj, Bodhgaya, Chitrakoot, Lucknow, Mathura, Vrindavan, and Delhi.\n\n" .
										  "CORE DESTINATIONS & CIRCUITS:\n" .
										  "- VARANASI (Kashi): Kashi Vishwanath Corridor, evening Ganga Aarti at Dashashwamedh Ghat, Subah-e-Banaras morning boat, Kaal Bhairav, Sarnath, Sankat Mochan, Banarasi Silk & food trails.\n" .
										  "- AYODHYA: Ram Janmabhoomi Mandir, Hanumangarhi, Kanak Bhavan, Saryu Aarti, Dashrath Mahal.\n" .
										  "- PRAYAGRAJ: Triveni Sangam holy dip & boat, Bade Hanuman Ji temple, Alopi Devi Shaktipeeth, Anand Bhavan.\n" .
										  "- BODHGAYA & GAYA: Mahabodhi Temple, Bodhi tree, 80-ft Buddha, Vishnupad temple, Falgu River, Pind Daan rituals for ancestors.\n" .
										  "- CHITRAKOOT: Kamadgiri Parikrama, Ramghat on Mandakini, Gupt Godavari, Sphatik Shila, Sati Anusuya Ashram.\n" .
										  "- LUCKNOW: Bara Imambara (Bhool Bhulaiya), Rumi Darwaza, Chota Imambara, Hazratganj, Awadhi cuisine & Chikankari shopping.\n" .
										  "- MATHURA & VRINDAVAN: Shri Krishna Janmabhoomi, Banke Bihari, Prem Mandir lighting, ISKCON, Radha Rani Barsana, Govardhan Parikrama.\n" .
										  "- DELHI: Airport pickup/transfers, monument tours, connection point for inbound/NRI travelers.\n\n" .
										  "SERVICES & LOGISTICS:\n" .
										  "- Outstation Cabs: AC Sedan (Dzire/Etios), SUV (Innova Crysta/Ertiga), Tempo Traveller (12, 17, 26 seater) for family, senior citizen & group yatras.\n" .
										  "- Hotels: Ghat-facing riverside hotels in Varanasi, properties near Ram Mandir in Ayodhya, luxury & budget stays.\n" .
										  "- Boats & VIP Darshan: Private morning sunrise boat, evening Aarti boat reservation, VIP Darshan assistance, Rudrabhishek & Pind Daan pandit coordination.\n\n" .
										  "SALES INTELLIGENCE & CONVERSATION FRAMEWORK:\n" .
										  "1. DISCOVERY: When a traveler asks about destinations or packages, immediately use 'search_trips' to fetch authentic circuits, cab options, and pricing. Be respectful, knowledgeable, and hospitable (use 'Namaste').\n" .
										  "2. LEAD CAPTURE & CRM SYNC: Whenever the traveler provides their Name, Phone/WhatsApp number, or Email, or shows travel intent, you MUST immediately invoke the 'sync_lead_crm' tool to register them in Fluent CRM, Twenty CRM, and the TripCosmos pipeline. Do not wait for the chat to end.\n" .
										  "3. CAB & GROUP ESTIMATES: Offer accurate vehicle recommendations (e.g. Sedan for 1-3 pax, Innova Crysta for 4-6 pax, Tempo Traveller for 7+ pax). Never exceed the 10% discount margin ceiling.\n" .
										  "4. HUMAN ESCALATION & VOICE CALL: Offer direct WhatsApp handoff ('request_human_handoff') or instant phone callback ('request_voice_call') for complex custom itineraries or immediate bookings.",
					'greeting_message' => "Namaste and welcome to TripCosmos (Varanasi)! Looking for an authentic tour package, hotel booking, or outstation cab for Varanasi, Ayodhya, Prayagraj, Bodhgaya, or Mathura? How can I assist you today?",
					'channels'         => 'web,whatsapp',
					'allowed_tools'    => wp_json_encode( array( 'search_trips', 'lookup_contact_crm', 'sync_lead_crm', 'request_human_handoff', 'query_pricing_sheet', 'request_voice_call' ) ),
					'temperature'      => 0.70,
					'is_active'        => 1,
				)
			);
		}

		// 2. Cabs, Hotels & Ganga Aarti Concierge
		$exists_concierge = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", 'tripcosmos-concierge' ) );
		if ( ! $exists_concierge ) {
			$wpdb->insert(
				$table_personas,
				array(
					'slug'             => 'tripcosmos-concierge',
					'name'             => 'TripCosmos Cabs, Hotels & Aarti Concierge',
					'system_prompt'    => "You are the TripCosmos Cabs, Hotels & Boat Concierge in Varanasi.\n" .
										  "Your role is to assist travelers who need outstation cabs (Swift Dzire, Innova Crysta, Tempo Traveller 12-26 seater), Varanasi Airport (Babatpur VNS) / Ayodhya Airport (AYJ) pickup/drops, ghat-facing hotel reservations, and private boat rides for Dashashwamedh evening Ganga Aarti.\n" .
										  "Always ensure traveler contact details are saved via 'sync_lead_crm' and offer direct WhatsApp handoff for finalized cab itineraries.",
					'greeting_message' => "Namaste! Ready to book an outstation cab, reserve a private boat for Varanasi Ganga Aarti, or book hotels in Varanasi or Ayodhya? I'm here to assist!",
					'channels'         => 'web,whatsapp',
					'allowed_tools'    => wp_json_encode( array( 'search_trips', 'sync_lead_crm', 'lookup_contact_crm', 'request_human_handoff', 'request_voice_call' ) ),
					'temperature'      => 0.50,
					'is_active'        => 1,
				)
			);
		}

		// 3. Yatra, Darshan & Ritual Support Desk
		$exists_support = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", 'tripcosmos-support' ) );
		if ( ! $exists_support ) {
			$wpdb->insert(
				$table_personas,
				array(
					'slug'             => 'tripcosmos-support',
					'name'             => 'TripCosmos Yatra & Ritual Support',
					'system_prompt'    => "You are the TripCosmos Yatra & Ritual Support Desk in Varanasi.\n" .
										  "You provide guidance on Kashi Vishwanath VIP Darshan, Rudrabhishek puja timings, Ganga Aarti schedules, Gaya Pind Daan rituals, Triveni Sangam holy dip boat logistics in Prayagraj, and Senior Citizen pilgrimage accessibility.\n" .
										  "Always prioritize safety, respect, and traditional hospitality.",
					'greeting_message' => "TripCosmos Yatra Support Desk. How can we assist with your temple darshan timings, ritual arrangements, or pilgrimage logistics today?",
					'channels'         => 'web,whatsapp',
					'allowed_tools'    => wp_json_encode( array( 'request_human_handoff', 'lookup_contact_crm' ) ),
					'temperature'      => 0.30,
					'is_active'        => 1,
				)
			);
		}

		// 4. B2B Agency Partner Hunter.
		$exists_b2b = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", 'tripcosmos-b2b' ) );
		if ( ! $exists_b2b ) {
			$wpdb->insert(
				$table_personas,
				array(
					'slug'             => 'tripcosmos-b2b',
					'name'             => 'TripCosmos B2B Partner Hunter',
					'system_prompt'    => "You are the TripCosmos B2B Partner Hunter in Varanasi. Recruit Indian travel agencies as B2B partners for white-label Kashi-Ayodhya-Prayagraj packages, cabs, hotels and Ganga Aarti boats. Use import_b2b_agencies per city, qualify by rating, then outreach_b2b_agency via WhatsApp + Brevo email. Log to FluentCRM + TwentyCRM. Max 1 outreach per agency per 7 days.",
					'greeting_message' => "TripCosmos B2B Desk (Varanasi). Give me a city and I will import travel agencies for partnership outreach!",
					'channels'         => 'admin,web,whatsapp',
					'allowed_tools'    => wp_json_encode( array( 'import_b2b_agencies', 'outreach_b2b_agency', 'lookup_contact_crm', 'sync_lead_crm', 'search_trips' ) ),
					'temperature'      => 0.40,
					'is_active'        => 1,
				)
			);
		}
	}
}
