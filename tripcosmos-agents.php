<?php
/**
 * Plugin Name: TripCosmos Agents
 * Plugin URI: https://tripcosmos.co
 * Description: Unified multi-provider AI conversational agent layer for TripCosmos.co, featuring customer chat widget, master control console, automated CRM/WhatsApp plumbings, and failover safety guardrails.
 * Version: 1.0.0
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
define( 'TC_AGENTS_VERSION', '1.0.0' );
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

// 3. AI Layer Includes
require_once TC_AGENTS_PATH . 'includes/ai/interface-tc-ai-provider.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-aipuffer.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-openrouter.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-omniroute.php';
require_once TC_AGENTS_PATH . 'includes/ai/providers/class-tc-provider-vmstudio.php';
require_once TC_AGENTS_PATH . 'includes/ai/class-tc-ai-router.php';

// 4. Orchestrator & Tools Includes
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-deal-math.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-memory.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-knowledge.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-sequences.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-tools.php';
require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-orchestrator.php';

// 5. Integrations Includes
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-fluentcrm.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-twentycrm.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-whatsapp.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-sheets.php';
require_once TC_AGENTS_PATH . 'includes/integrations/class-tc-integration-voice.php';

// 6. API Includes
require_once TC_AGENTS_PATH . 'includes/api/class-tc-agents-rest.php';

// 7. Admin & Public Includes
require_once TC_AGENTS_PATH . 'admin/class-tc-agents-admin.php';
require_once TC_AGENTS_PATH . 'public/class-tc-agents-public.php';

// 8. Lifecycle Hooks
register_activation_hook( __FILE__, array( 'TC_Agents_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TC_Agents_Deactivator', 'deactivate' ) );

// 9. Bootstrap Plugin Services
add_action( 'plugins_loaded', function() {
	// Initialize Follow-up Sequences Cron Service
	TC_Agent_Sequences::init();

	// Initialize REST Routes
	add_action( 'rest_api_init', array( 'TC_Agents_REST', 'register_routes' ) );

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

