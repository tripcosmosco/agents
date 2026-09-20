<?php
/**
 * Plugin Name: TripCosmos Agents
 * Plugin URI: https://tripcosmos.co
 * Description: Unified multi-provider AI conversational agent layer for TripCosmos.co, featuring customer chat widget, master control console, automated CRM/WhatsApp plumbings, and failover safety guardrails.
 * Version: 1.4.4
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
define( 'TC_AGENTS_VERSION', '1.4.4' );
define( 'TC_AGENTS_FILE', __FILE__ );
define( 'TC_AGENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'TC_AGENTS_URL', plugin_dir_url( __FILE__ ) );

// 2. Core Includes
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-vault.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-activator.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-deactivator.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-logger.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-guardrails.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-github-updater.php';
require_once TC_AGENTS_PATH . 'includes/class-tc-agents-queue.php';

// 3. AI Layer Includes
require_once TC_AGENTS_PATH . 'includes/ai/interface-tc-ai-provider.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-aipuffer.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-openrouter.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-omniroute.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-vmstudio.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-gateway.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-gemini.php';
require_once TC_AGENTS_PATH . 'includes/ai/class-tc-ai-router.php';

// 4. Orchestrator & Tools Includes
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-chunker.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-vector-store.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-deal-math.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-memory.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-knowledge.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-sequences.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-cab-fare-engine.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-itinerary-generator.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-tools.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-orchestrator.php';

// 5. Integrations Includes
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-fluentcrm.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-twentycrm.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-whatsapp.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-evolution.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-brevo.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-google-business.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-sheets.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-voice.php';

// 6. API Includes
require_once TC_AGENTS_PATH . 'includes/api/class-tc-agents-rest.php';
require_once TC_AGENTS_PATH . 'includes/api/class-tc-agents-mobile-api.php';

// 7. Admin & Public Includes
require_once TC_AGENTS_PATH . 'admin/class-tc-agents-admin.php';
require_once TC_AGENTS_PATH . 'public/class-tc-agents-public.php';

// 8. Lifecycle Hooks
register_activation_hook( __FILE__, array( 'TC_Agents_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TC_Agents_Deactivator', 'deactivate' ) );

// 9. Bootstrap Plugin Services
add_action( 'plugins_loaded', function() {
	// Auto-upgrade database tables if version bumped
	TC_Agents_Activator::maybe_upgrade();

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

	// Initialize REST Routes
	add_action( 'rest_api_init', array( 'TC_Agents_REST', 'register_routes' ) );

	// Initialize Itinerary Renderer
	add_action( 'template_redirect', array( 'TC_Itinerary_Generator', 'maybe_render_itinerary' ) );

	// Initialize GitHub-based Updater
	TC_Agents_GitHub_Updater::init();

	// Initialize Admin Screens
	if ( is_admin() ) {
		TC_Agents_Admin::init();
	}

	// Initialize Frontend Widget
	if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		TC_Agents_Public::init();
	}
} );

