<?php
/**
 * Plugin Name: TripCosmos Agents
 * Plugin URI: https://tripcosmos.co
 * Description: Unified multi-provider AI conversational agent layer for TripCosmos.co, featuring customer chat widget, omnipresent master control console popup, automated CRM/WhatsApp plumbings, and failover safety guardrails.
 * Version: 1.9.0
 * Author: TripCosmos Team
 * Author URI: https://tripcosmos.co
 * Text Domain: tripcosmos-agents
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Plugin Constants
define( 'TC_AGENTS_VERSION', '1.9.0' );
define( 'TC_AGENTS_FILE', __FILE__ );
define( 'TC_AGENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'TC_AGENTS_URL', plugin_dir_url( __FILE__ ) );

// 2. High-Performance Class Autoloader (Zero-I/O Lazy Loading)
spl_autoload_register( function( $class ) {
	if ( strpos( $class, 'TC_' ) !== 0 ) {
		return;
	}

	static $map = null;
	if ( null === $map ) {
		$map = array(
			// Core
			'TC_Agents_Vault'                => 'includes/class-tc-agents-vault.php',
			'TC_Agents_Security'             => 'includes/class-tc-agents-security.php',
			'TC_Agents_WA_Attribution'       => 'includes/class-tc-agents-wa-attribution.php',
			'TC_Agents_Activator'            => 'includes/class-tc-agents-activator.php',
			'TC_Agents_Deactivator'          => 'includes/class-tc-agents-deactivator.php',
			'TC_Agents_Logger'               => 'includes/class-tc-agents-logger.php',
			'TC_Agents_Guardrails'           => 'includes/class-tc-agents-guardrails.php',
			'TC_Agents_GitHub_Updater'       => 'includes/class-tc-agents-github-updater.php',
			'TC_Agents_Presets'              => 'includes/class-tc-agents-presets.php',
			'TC_Agents_Queue'                => 'includes/class-tc-agents-queue.php',
			'TC_Agents_Privacy'              => 'includes/class-tc-agents-privacy.php',
			'TC_Contact_Service'             => 'includes/class-tc-contact-service.php',
			'TC_CRM_Access'                  => 'includes/class-tc-crm-access.php',
			'TC_CRM_Notify'                  => 'includes/class-tc-crm-notify.php',
			'TC_Quote_Service'               => 'includes/class-tc-quote-service.php',
			'TC_Payments'                    => 'includes/class-tc-payments.php',
			'TC_WhatsApp_Hub'                => 'includes/class-tc-whatsapp-hub.php',
			'TC_Quote_Page'                  => 'public/class-tc-quote-page.php',

			// AI Layer
			'TC_AI_Provider_Interface'       => 'includes/ai/interface-tc-ai-provider.php',
			'TC_Provider_AIPuffer'           => 'includes/ai/providers/class-tc-provider-aipuffer.php',
			'TC_Provider_Gateway'            => 'includes/ai/providers/class-tc-provider-gateway.php',
			'TC_Provider_Gemini'             => 'includes/ai/providers/class-tc-provider-gemini.php',
			'TC_Provider_Omniroute'          => 'includes/ai/providers/class-tc-provider-omniroute.php',
			'TC_Provider_OpenRouter'         => 'includes/ai/providers/class-tc-provider-openrouter.php',
			'TC_Provider_VMStudio'           => 'includes/ai/providers/class-tc-provider-vmstudio.php',
			'TC_AI_Router'                   => 'includes/ai/class-tc-ai-router.php',

			// Orchestrator & Tools
			'TC_Agent_Chunker'               => 'includes/orchestrator/class-tc-agent-chunker.php',
			'TC_Agent_Vector_Store'          => 'includes/orchestrator/class-tc-agent-vector-store.php',
			'TC_Agent_Deal_Math'             => 'includes/orchestrator/class-tc-agent-deal-math.php',
			'TC_Agent_Memory'                => 'includes/orchestrator/class-tc-agent-memory.php',
			'TC_Agent_Knowledge'             => 'includes/orchestrator/class-tc-agent-knowledge.php',
			'TC_Agent_Sequences'             => 'includes/orchestrator/class-tc-agent-sequences.php',
			'TC_Cab_Fare_Engine'             => 'includes/orchestrator/class-tc-cab-fare-engine.php',
			'TC_Itinerary_Generator'         => 'includes/orchestrator/class-tc-itinerary-generator.php',
			'TC_Agent_Tools'                 => 'includes/orchestrator/class-tc-agent-tools.php',
			'TC_Agent_Orchestrator'          => 'includes/orchestrator/class-tc-agent-orchestrator.php',

			// Integrations
			'TC_Integration_FluentCRM'       => 'includes/integrations/class-tc-integration-fluentcrm.php',
			'TC_Integration_TwentyCRM'       => 'includes/integrations/class-tc-integration-twentycrm.php',
			'TC_Integration_WhatsApp'        => 'includes/integrations/class-tc-integration-whatsapp.php',
			'TC_Integration_Evolution'       => 'includes/integrations/class-tc-integration-evolution.php',
			'TC_Integration_Brevo'           => 'includes/integrations/class-tc-integration-brevo.php',
			'TC_Integration_Google_Business' => 'includes/integrations/class-tc-integration-google-business.php',
			'TC_Integration_Sheets'          => 'includes/integrations/class-tc-integration-sheets.php',
			'TC_Integration_Voice'           => 'includes/integrations/class-tc-integration-voice.php',

			// API
			'TC_Agents_REST'                 => 'includes/api/class-tc-agents-rest.php',
			'TC_Agents_Mobile_API'           => 'includes/api/class-tc-agents-mobile-api.php',
			'TC_Agents_Mobile_CRM_API'       => 'includes/api/class-tc-agents-mobile-crm-api.php',

			// Admin & Public
			'TC_Agents_Admin'                => 'admin/class-tc-agents-admin.php',
			'TC_Contacts_Admin'              => 'admin/class-tc-contacts-admin.php',
			'TC_Agents_Public'               => 'public/class-tc-agents-public.php',
		);
	}

	if ( isset( $map[ $class ] ) ) {
		// If loading an AI provider, ensure interface is declared first
		if ( strpos( $class, 'TC_Provider_' ) === 0 && ! interface_exists( 'TC_AI_Provider_Interface', false ) ) {
			require_once TC_AGENTS_PATH . 'includes/ai/interface-tc-ai-provider.php';
		}
		$file = TC_AGENTS_PATH . $map[ $class ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
} );

// 3. Lifecycle Hooks
register_activation_hook( __FILE__, array( 'TC_Agents_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TC_Agents_Deactivator', 'deactivate' ) );

// 4. Bootstrap Plugin Services
add_action( 'plugins_loaded', function() {
	// Auto-upgrade database tables if version bumped
	TC_Agents_Activator::maybe_upgrade();

	// Per-agent mobile tokens, rate limits and webhook authentication
	TC_Agents_Security::init();

	// Web-to-WhatsApp click attribution
	TC_Agents_WA_Attribution::init();

	// Privacy exporter/eraser + retention purge
	TC_Agents_Privacy::init();

	// CRM staff alerts (assignment, due follow-ups, SLA, replies, morning digest)
	TC_CRM_Notify::init();

	// Quotes, payments (Cashfree / UPI) and the customer quote page
	TC_Payments::init();

	// Central WhatsApp CRM: lead routing to agents' own WhatsApp, relay, receipts
	TC_WhatsApp_Hub::init();
	TC_Quote_Page::init();

	// Initialize Background Queue
	TC_Agents_Queue::init();

	// Initialize FluentCRM & Fluent Forms Listener
	TC_Integration_FluentCRM::init();

	// Initialize Follow-up Sequences Cron Service
	TC_Agent_Sequences::init();

	// Live model sync (2x daily) + B2B Google import (daily) via queue.
	if ( ! wp_next_scheduled( 'tc_agents_sync_models' ) ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'tc_agents_sync_models' );
	}
	add_action( 'tc_agents_sync_models', function() {
		if ( class_exists( 'TC_Agents_Queue' ) ) { TC_Agents_Queue::push( 'sync_models', array(), 5 ); }
	} );
	if ( ! wp_next_scheduled( 'tc_agents_import_b2b' ) ) {
		wp_schedule_event( time() + 900, 'daily', 'tc_agents_import_b2b' );
	}
	add_action( 'tc_agents_import_b2b', function() {
		if ( class_exists( 'TC_Agents_Queue' ) ) { TC_Agents_Queue::push( 'import_b2b', array(), 5 ); }
	} );

	// Initialize Mobile Companion Telephony API
	TC_Agents_Mobile_API::init();

	// Mobile CRM API (dashboard, tasks, bookings, quotes, payments, threads, AI call summary)
	TC_Agents_Mobile_CRM_API::init();

	// Initialize REST Routes
	add_action( 'rest_api_init', array( 'TC_Agents_REST', 'register_routes' ) );

	// Initialize Itinerary Renderer
	add_action( 'template_redirect', array( 'TC_Itinerary_Generator', 'maybe_render_itinerary' ) );

	// Initialize GitHub-based Updater
	TC_Agents_GitHub_Updater::init();

	// Initialize Admin Screens
	if ( is_admin() ) {
		TC_Agents_Admin::init();
		TC_Contacts_Admin::init();
	}

	// Initialize Frontend Widget
	if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		TC_Agents_Public::init();
	}
} );
