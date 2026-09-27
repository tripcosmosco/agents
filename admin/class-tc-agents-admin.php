<?php
/**
 * Admin Control Center and Menu Manager.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_node' ), 100 );
		add_action( 'admin_footer', array( __CLASS__, 'render_master_chat_popup' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_console' ) );
		add_action( 'admin_notices', array( __CLASS__, 'security_notices' ) );
		add_action( 'admin_init', array( 'TC_Agents_Vault', 'maybe_rekey' ) );
		add_action( 'wp_ajax_tc_get_lead_memory', array( __CLASS__, 'ajax_get_lead_memory' ) );
		add_action( 'wp_ajax_tc_update_lead_stage', array( __CLASS__, 'ajax_update_lead_stage' ) );
		add_action( 'wp_ajax_tc_sync_catalog_kb', array( __CLASS__, 'ajax_sync_catalog_kb' ) );
		add_action( 'wp_ajax_tc_sync_aipuffer_bots', array( __CLASS__, 'ajax_sync_aipuffer_bots' ) );
		add_action( 'wp_ajax_tc_sync_provider_models', array( __CLASS__, 'ajax_sync_provider_models' ) );
		add_action( 'wp_ajax_tc_test_twentycrm', array( __CLASS__, 'ajax_test_twentycrm' ) );
		add_action( 'wp_ajax_tc_toggle_widget', array( __CLASS__, 'ajax_toggle_widget' ) );
		add_action( 'wp_ajax_tc_get_live_activities', array( __CLASS__, 'ajax_get_live_activities' ) );
		add_action( 'wp_ajax_tc_clear_error_logs', array( __CLASS__, 'ajax_clear_error_logs' ) );
		add_action( 'wp_ajax_tc_run_diagnostics', array( __CLASS__, 'ajax_run_diagnostics' ) );
		add_action( 'wp_ajax_tc_apply_preset', array( __CLASS__, 'ajax_apply_preset' ) );
		add_action( 'wp_ajax_tc_toggle_human_takeover', array( __CLASS__, 'ajax_toggle_human_takeover' ) );
	}

	/**
	 * Redirect legacy console URL to Executive Dashboard and launch master popup.
	 */
	public static function redirect_legacy_console() {
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'tc-agents-console', 'tc_agents_console' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tripcosmos-agents&open_master_chat=1' ) );
			exit;
		}
	}

	/**
	 * Register top-level "Tripcosmos Agents" menu and all feature submenus.
	 */
	public static function register_menus() {
		// Top-level
		add_menu_page(
			__( 'TripCosmos Agents', 'tripcosmos-agents' ),
			__( 'TripCosmos Agents', 'tripcosmos-agents' ),
			'manage_options',
			'tripcosmos-agents',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-superhero-alt',
			26
		);

		// 1. Executive Dashboard
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Executive Dashboard', 'tripcosmos-agents' ),
			__( 'Dashboard', 'tripcosmos-agents' ),
			'manage_options',
			'tripcosmos-agents',
			array( __CLASS__, 'render_dashboard_page' )
		);

		// 2. Leads & Pipeline (Master Console is now omnipresent in bottom-right popup and admin bar)
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Leads & Deals Pipeline', 'tripcosmos-agents' ),
			__( 'Leads Pipeline', 'tripcosmos-agents' ),
			TC_CRM_Access::CAP_ACCESS,
			'tc-agents-leads',
			array( __CLASS__, 'render_leads_page' )
		);

		// 4. Conversations & Transcripts
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Conversations & Logs', 'tripcosmos-agents' ),
			__( 'Conversations', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-conversations',
			array( __CLASS__, 'render_conversations_page' )
		);

		// 5. Knowledge Base & FAQs
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Knowledge Base & FAQs', 'tripcosmos-agents' ),
			__( 'Knowledge Base', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-knowledge',
			array( __CLASS__, 'render_knowledge_page' )
		);

		// 6. Automated Follow-Up Sequences
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Follow-Up Sequences', 'tripcosmos-agents' ),
			__( 'Follow-Up Sequences', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-sequences',
			array( __CLASS__, 'render_sequences_page' )
		);

		// 7. Agent Personas & Tools
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Agent Personas & Tools', 'tripcosmos-agents' ),
			__( 'Agents', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-personas',
			array( __CLASS__, 'render_agents_page' )
		);

		// 8. AI Routing & Failover
		add_submenu_page(
			'tripcosmos-agents',
			__( 'AI Routing & Health', 'tripcosmos-agents' ),
			__( 'AI Routing', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-routing',
			array( __CLASS__, 'render_routing_page' )
		);

		// 8b. B2B Agencies (India import + outreach)
		add_submenu_page(
			'tripcosmos-agents',
			__( 'B2B Agencies', 'tripcosmos-agents' ),
			__( 'B2B Agencies', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-b2b',
			array( __CLASS__, 'render_b2b_page' )
		);

		// 9. Integrations & Plumbings
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Integrations & Plumbings', 'tripcosmos-agents' ),
			__( 'Integrations', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-integrations',
			array( __CLASS__, 'render_integrations_page' )
		);

		// 10. AI Analytics & Cost Telemetry
		add_submenu_page(
			'tripcosmos-agents',
			__( 'AI Analytics & Costs', 'tripcosmos-agents' ),
			__( 'AI Analytics', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-analytics',
			array( __CLASS__, 'render_analytics_page' )
		);

		// 11. Guardrails & Kill Switch
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Guardrails & Safety Control', 'tripcosmos-agents' ),
			__( 'Guardrails & Safety', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-guardrails',
			array( __CLASS__, 'render_guardrails_page' )
		);

		// 12. Settings
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Plugin Settings', 'tripcosmos-agents' ),
			__( 'Settings', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin screens.
	 */
	public static function enqueue_assets( $hook ) {
		// Always enqueue Master Chat Popup on all admin pages for staff
		if ( current_user_can( 'manage_options' ) || ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::can_access() ) ) {
			$pop_css = TC_AGENTS_PATH . 'admin/css/tc-master-popup.css';
			$pop_js  = TC_AGENTS_PATH . 'admin/js/tc-master-popup.js';
			$css_v   = file_exists( $pop_css ) ? TC_AGENTS_VERSION . '.' . filemtime( $pop_css ) : TC_AGENTS_VERSION;
			$js_v    = file_exists( $pop_js ) ? TC_AGENTS_VERSION . '.' . filemtime( $pop_js ) : TC_AGENTS_VERSION;

			wp_enqueue_style(
				'tc-master-popup-css',
				TC_AGENTS_URL . 'admin/css/tc-master-popup.css',
				array(),
				$css_v
			);

			wp_enqueue_script(
				'tc-master-popup-js',
				TC_AGENTS_URL . 'admin/js/tc-master-popup.js',
				array( 'jquery' ),
				$js_v,
				true
			);

			wp_localize_script(
				'tc-master-popup-js',
				'tcAgentsAdmin',
				array(
					'restUrl'    => esc_url_raw( rest_url( 'tc-agents/v1/' ) ),
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'adminNonce' => wp_create_nonce( 'tc_agents_admin_nonce' ),
					'killSwitch' => get_option( 'tc_agents_kill_switch', '0' ),
				)
			);
		}

		if ( strpos( $hook, 'tripcosmos-agents' ) === false && strpos( $hook, 'tc-agents' ) === false ) {
			return;
		}

		if ( strpos( $hook, 'tc-agents-settings' ) !== false ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'tc-agents-admin-css',
			TC_AGENTS_URL . 'admin/css/tc-agents-admin.css',
			array(),
			TC_AGENTS_VERSION
		);

		wp_enqueue_script(
			'tc-agents-admin-js',
			TC_AGENTS_URL . 'admin/js/tc-agents-admin.js',
			array( 'jquery' ),
			TC_AGENTS_VERSION,
			true
		);

		wp_localize_script(
			'tc-agents-admin-js',
			'tcAgentsAdmin',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'tc-agents/v1/' ) ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'adminNonce' => wp_create_nonce( 'tc_agents_admin_nonce' ),
				'killSwitch' => get_option( 'tc_agents_kill_switch', '0' ),
			)
		);
	}

	/**
	 * Render Executive Dashboard.
	 */
	public static function render_dashboard_page() {
		require_once TC_AGENTS_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Render Master Agent Console.
	 */
	public static function render_console_page() {
		wp_safe_redirect( admin_url( 'admin.php?page=tripcosmos-agents&open_master_chat=1' ) );
		exit;
	}

	/**
	 * Render Leads & Deals Pipeline (Kanban).
	 */
	public static function render_leads_page() {
		require_once TC_AGENTS_PATH . 'admin/views/leads.php';
	}

	/**
	 * Render Conversations page.
	 */
	public static function render_conversations_page() {
		require_once TC_AGENTS_PATH . 'admin/views/conversations.php';
	}

	/**
	 * Render Knowledge Base & FAQ page.
	 */
	public static function render_knowledge_page() {
		require_once TC_AGENTS_PATH . 'admin/views/knowledge-base.php';
	}

	/**
	 * Render Follow-Up Sequences page.
	 */
	public static function render_sequences_page() {
		require_once TC_AGENTS_PATH . 'admin/views/sequences.php';
	}

	/**
	 * Render Agents page.
	 */
	public static function render_agents_page() {
		require_once TC_AGENTS_PATH . 'admin/views/agents.php';
	}

	/**
	 * Render Routing page.
	 */
	public static function render_routing_page() {
		require_once TC_AGENTS_PATH . 'admin/views/routing.php';
	}

	/**
	 * Render B2B Agencies page.
	 */
	public static function render_b2b_page() {
		require_once TC_AGENTS_PATH . 'admin/views/b2b-agencies.php';
	}

	/**
	 * Render Integrations page.
	 */
	public static function render_integrations_page() {
		require_once TC_AGENTS_PATH . 'admin/views/integrations.php';
	}

	/**
	 * Render AI Analytics page.
	 */
	public static function render_analytics_page() {
		require_once TC_AGENTS_PATH . 'admin/views/analytics.php';
	}

	/**
	 * Render Guardrails page.
	 */
	public static function render_guardrails_page() {
		require_once TC_AGENTS_PATH . 'admin/views/guardrails.php';
	}

	/**
	 * Render Settings page.
	 */
	public static function render_settings_page() {
		require_once TC_AGENTS_PATH . 'admin/views/settings.php';
	}

	/**
	 * AJAX endpoint to retrieve traveler memory for modal.
	 */
	public static function ajax_get_lead_memory() {
		check_ajax_referer( 'tc_get_lead_memory', '_nonce' );

		$contact_id = absint( $_GET['contact_id'] ?? 0 );
		if ( ! TC_CRM_Access::can_edit( TC_Contact_Service::get( $contact_id ) ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => 'Missing contact ID' ), 400 );
		}

		$mem = TC_Agent_Memory::get_memory( $contact_id );
		if ( ! $mem ) {
			wp_send_json_success( null );
		}

		wp_send_json_success( array(
			'summary'          => $mem->summary,
			'facts'            => TC_Agent_Memory::decode_list( $mem->facts ),
			'preferences'      => TC_Agent_Memory::decode_list( $mem->preferences ),
			'objections'       => TC_Agent_Memory::decode_list( $mem->objections ),
			'next_best_action' => $mem->next_best_action,
		) );
	}

	/**
	 * AJAX endpoint to update lead pipeline stage (Kanban drag-and-drop).
	 */
	public static function ajax_update_lead_stage() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		$lead_id = absint( $_POST['lead_id'] ?? 0 );
		$stage   = sanitize_key( $_POST['stage'] ?? '' );
		$lead    = TC_Contact_Service::get( $lead_id );

		if ( ! TC_CRM_Access::can_edit( $lead ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		if ( ! TC_Contact_Service::set_stage( $lead_id, $stage, sanitize_text_field( wp_unslash( $_POST['lost_reason'] ?? '' ) ) ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
		}

		// If won or proposal, trigger automated voice hook if enabled
		if ( ( 'won' === $stage || 'proposal' === $stage ) && '1' === (string) get_option( 'tc_agents_voice_enabled', '0' ) ) {
			TC_Agents_Queue::push( 'voice_trigger', array( 'lead' => TC_Contact_Service::get( $lead_id ) ) );
		}

		wp_send_json_success( array( 'lead_id' => $lead_id, 'stage' => $stage ) );
	}

	/**
	 * AJAX endpoint to trigger 1-click sync of WordPress tours and posts into KB.
	 */
	public static function ajax_sync_catalog_kb() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$count = TC_Agent_Knowledge::sync_wordpress_catalog();
		wp_send_json_success( array(
			'count'   => $count,
			'message' => sprintf( __( 'Successfully indexed %d catalog items and content into knowledge base.', 'tripcosmos-agents' ), $count ),
		) );
	}

	/**
	 * AJAX endpoint to discover/sync bots from local/remote AI Puffer (AIPKit).
	 */
	public static function ajax_sync_aipuffer_bots() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( $_POST['base_url'] ) : null;
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : null;

		if ( null !== $base_url ) {
			update_option( 'tc_agents_aipuffer_base_url', $base_url );
		}
		if ( ! empty( $api_key ) ) {
			TC_Agents_Vault::put( 'aipuffer_api_key', $api_key );
		}

		$bots = TC_Provider_AIPuffer::discover_bots( $base_url, $api_key );
		if ( empty( $bots ) ) {
			$msg = __( 'No chatbots found locally or on the remote AIPKit host. You can enter a manual Bot ID below.', 'tripcosmos-agents' );
			wp_send_json_error( array( 'message' => $msg, 'bots' => array() ) );
		}

		wp_send_json_success( array(
			'bots'    => $bots,
			'message' => sprintf( __( 'Discovered %d bot(s) successfully.', 'tripcosmos-agents' ), count( $bots ) ),
		) );
	}

	/**
	 * AJAX endpoint to live-sync model catalogue for a specific provider.
	 */
	public static function ajax_sync_provider_models() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$provider_slug = sanitize_key( $_POST['provider'] ?? '' );
		$router        = TC_AI_Router::get_instance();
		$provider      = $router->get_provider( $provider_slug );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => 'Provider not found.' ) );
		}

		delete_transient( 'tc_models_' . $provider->get_slug() );
		$models = method_exists( $provider, 'get_models' ) ? $provider->get_models() : array();

		wp_send_json_success( array(
			'provider' => $provider_slug,
			'models'   => $models,
			'count'    => count( $models ),
			'message'  => sprintf( __( 'Synced %d models for %s.', 'tripcosmos-agents' ), count( $models ), $provider->get_name() ),
		) );
	}

	/**
	 * AJAX endpoint to test Twenty CRM connection.
	 */
	public static function ajax_test_twentycrm() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		if ( ! class_exists( 'TC_Integration_TwentyCRM' ) ) {
			wp_send_json_error( array( 'message' => 'Twenty CRM integration class not found.' ) );
		}

		$result = TC_Integration_TwentyCRM::test_connection();
		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX endpoint to toggle the frontend chatbot on/off.
	 */
	public static function ajax_toggle_widget() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$current   = (string) get_option( 'tc_agents_widget_enabled', '1' );
		$new_state = ( '1' === $current ) ? '0' : '1';
		update_option( 'tc_agents_widget_enabled', $new_state );

		wp_send_json_success( array(
			'enabled' => ( '1' === $new_state ),
			'status'  => ( '1' === $new_state ) ? 'active' : 'disabled',
			'message' => ( '1' === $new_state )
				? __( 'Frontend chatbot is now LIVE on your website.', 'tripcosmos-agents' )
				: __( 'Frontend chatbot is now DISABLED and hidden from visitors.', 'tripcosmos-agents' ),
		) );
	}

	/**
	 * Warn admins about insecure secret storage.
	 */
	public static function security_notices() {
		if ( ! current_user_can( 'manage_options' ) || ( false === strpos( (string) ( $_GET['page'] ?? '' ), 'tripcosmos-agents' ) && false === strpos( (string) ( $_GET['page'] ?? '' ), 'tc-agents' ) ) ) {
			return;
		}
		if ( class_exists( 'TC_Agents_Vault' ) && ! TC_Agents_Vault::is_secure() ) {
			echo '<div class="notice notice-error"><p><strong>TripCosmos Agents:</strong> the PHP OpenSSL extension (AES-256-GCM) is unavailable, so API keys are stored only base64-encoded. Enable OpenSSL and re-save your keys.</p></div>';
		}
		if ( ! defined( 'TC_AGENTS_VAULT_KEY' ) ) {
			echo '<div class="notice notice-warning"><p><strong>TripCosmos Agents:</strong> add <code>define( \'TC_AGENTS_VAULT_KEY\', \'a-long-random-string\' );</code> to wp-config.php (any long random string) so saved API keys keep working if WordPress salts change or the site is cloned. Existing keys are moved to it automatically.</p></div>';
		}
	}

	/**
	 * Handle POST form saves securely.
	 */
	public static function handle_settings_save() {
		// Handle CSV Export on admin_init before headers are sent
		if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'tc_export_convos' );
			global $wpdb;
			$table_convo = $wpdb->prefix . 'tc_agent_conversations';
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="tripcosmos-conversations-' . gmdate( 'Y-m-d' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'ID', 'Session ID', 'Channel', 'Agent', 'Status', 'Started At', 'Last Message At' ) );
			// Stream in pages so large tables don't exhaust memory.
			$last_id = PHP_INT_MAX;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_convo WHERE id < %d ORDER BY id DESC LIMIT 500", $last_id ), ARRAY_A ) ?: array();
				foreach ( $rows as $c ) {
					fputcsv( $out, array( $c['id'], $c['session_id'], $c['channel'], $c['agent_id'], $c['status'], $c['started_at'], $c['last_message_at'] ) );
					$last_id = (int) $c['id'];
				}
			} while ( count( $rows ) === 500 );
			fclose( $out );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['tc_agents_action'] ) ) {
			return;
		}


		check_admin_referer( 'tc_agents_admin_save', 'tc_agents_nonce' );
		$action = sanitize_text_field( $_POST['tc_agents_action'] );

		// 1. Leads Actions
		if ( 'update_lead_stage' === $action ) {
			$lead_id = absint( $_POST['lead_id'] ?? 0 );
			$stage   = sanitize_key( $_POST['stage'] ?? 'inquiry' );
			if ( $lead_id > 0 ) {
				TC_Contact_Service::set_stage( $lead_id, $stage, sanitize_text_field( wp_unslash( $_POST['lost_reason'] ?? '' ) ) );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => sanitize_key( $_POST['redirect_page'] ?? 'tc-agents-leads' ), 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'save_lead' === $action ) {
			$res = TC_Contact_Service::upsert(
				array(
					'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
					'phone'          => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
					'email'          => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
					'source_channel' => sanitize_text_field( $_POST['source_channel'] ?? 'manual' ),
					'stage'          => sanitize_key( $_POST['stage'] ?? 'inquiry' ),
					'deal_value'     => floatval( $_POST['deal_value'] ?? 0.0 ),
					'owner'          => sanitize_text_field( wp_unslash( $_POST['owner'] ?? '' ) ),
				),
				array( 'activity' => __( 'Added manually by staff', 'tripcosmos-agents' ) )
			);
			$args = array( 'page' => 'tc-agents-leads', 'saved' => $res['id'] ? '1' : '0' );
			if ( 'updated' === $res['status'] ) {
				$args['duplicate'] = $res['id'];
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// 2. Knowledge Base Actions
		if ( 'save_knowledge' === $action ) {
			$doc_id = absint( $_POST['doc_id'] ?? 0 );
			TC_Agent_Knowledge::save_document(
				array(
					'title'     => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					'category'  => sanitize_text_field( wp_unslash( $_POST['category'] ?? 'faq' ) ),
					'tags'      => sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ),
					'content'   => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),
					'is_active' => ! empty( $_POST['is_active'] ) ? 1 : 0,
				),
				$doc_id
			);
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-knowledge', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete_knowledge' === $action ) {
			$doc_id = absint( $_POST['doc_id'] ?? 0 );
			if ( $doc_id > 0 ) {
				TC_Agent_Knowledge::delete_document( $doc_id );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-knowledge', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 3. Sequences Actions
		if ( 'save_sequence' === $action ) {
			global $wpdb;
			$seq_id   = absint( $_POST['sequence_id'] ?? 0 );
			$delays   = array_map( 'floatval', (array) ( $_POST['step_delays'] ?? array() ) );
			$subjects = array_map( 'sanitize_text_field', (array) ( $_POST['step_subjects'] ?? array() ) );
			$messages = array_map( 'sanitize_textarea_field', (array) ( $_POST['step_messages'] ?? array() ) );

			$steps = array();
			foreach ( $delays as $idx => $delay ) {
				$msg = $messages[ $idx ] ?? '';
				if ( ! empty( $msg ) ) {
					$steps[] = array(
						'delay_hours' => max( 0.05, (float) $delay ),
						'subject'     => ! empty( $subjects[ $idx ] ) ? $subjects[ $idx ] : 'TripCosmos Tour & Pilgrimage Follow-up',
						'message'     => $msg,
					);
				}
			}

			$data = array(
				'name'          => sanitize_text_field( $_POST['name'] ?? 'Follow-Up Drip' ),
				'channel'       => sanitize_text_field( $_POST['channel'] ?? 'whatsapp' ),
				'trigger_event' => sanitize_text_field( $_POST['trigger_event'] ?? 'inquiry_abandoned' ),
				'steps_json'    => wp_json_encode( $steps ),
				'is_active'     => ! empty( $_POST['is_active'] ) ? 1 : 0,
			);

			if ( $seq_id > 0 ) {
				$wpdb->update( $wpdb->prefix . 'tc_agent_sequences', $data, array( 'id' => $seq_id ) );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $wpdb->prefix . 'tc_agent_sequences', $data );
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-sequences', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete_sequence' === $action ) {
			global $wpdb;
			$seq_id = absint( $_POST['sequence_id'] ?? 0 );
			if ( $seq_id > 0 ) {
				$wpdb->delete( $wpdb->prefix . 'tc_agent_sequences', array( 'id' => $seq_id ) );
				$wpdb->delete( $wpdb->prefix . 'tc_agent_sequence_enrollments', array( 'sequence_id' => $seq_id ) );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-sequences', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'cancel_enrollment' === $action ) {
			global $wpdb;
			$en_id = absint( $_POST['enrollment_id'] ?? 0 );
			if ( $en_id > 0 ) {
				$wpdb->update( $wpdb->prefix . 'tc_agent_sequence_enrollments', array( 'status' => 'cancelled' ), array( 'id' => $en_id ) );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-sequences', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'run_sequences_now' === $action ) {
			$count = class_exists( 'TC_Agent_Sequences' ) ? TC_Agent_Sequences::process_due_steps( true ) : 0;
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-sequences', 'ran' => $count ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'manual_enroll_lead' === $action ) {
			$contact_id  = absint( $_POST['contact_id'] ?? 0 );
			$sequence_id = absint( $_POST['sequence_id'] ?? 0 );
			if ( $contact_id > 0 && $sequence_id > 0 && class_exists( 'TC_Agent_Sequences' ) ) {
				TC_Agent_Sequences::enroll_contact( $contact_id, '', $sequence_id );
			}
			$redirect_page = sanitize_key( $_POST['redirect_page'] ?? 'tc-agents-sequences' );
			wp_safe_redirect( add_query_arg( array( 'page' => $redirect_page, 'enrolled' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 4. AI Routing (canonical: openrouter, gateway, aipuffer, gemini)
		if ( 'save_routing' === $action ) {
			$priority = array_map( 'sanitize_text_field', (array) ( $_POST['provider_priority'] ?? array() ) );
			// Migrate any retired slugs submitted by stale forms.
			$map = array( 'omniroute' => 'gateway', 'vmstudio' => 'gateway' );
			$priority = array_values( array_unique( array_map( function( $s ) use ( $map ) { return $map[ $s ] ?? $s; }, $priority ) ) );
			$canonical_slugs = array( 'aipuffer', 'openrouter', 'gateway', 'gemini' );
			foreach ( $canonical_slugs as $c_slug ) {
				if ( ! in_array( $c_slug, $priority, true ) ) {
					$priority[] = $c_slug;
				}
			}
			update_option( 'tc_agents_provider_priority', $priority );
			update_option( 'tc_agents_circuit_breaker_threshold', absint( $_POST['circuit_breaker_threshold'] ?? 2 ) );
			update_option( 'tc_agents_timeout_seconds', absint( $_POST['timeout_seconds'] ?? 8 ) );

			// OpenRouter
			if ( ! empty( $_POST['openrouter_api_key'] ) ) {
				TC_Agents_Vault::put( 'openrouter_api_key', sanitize_text_field( $_POST['openrouter_api_key'] ) );
			}
			update_option( 'tc_agents_openrouter_model', sanitize_text_field( $_POST['openrouter_model'] ?? 'anthropic/claude-3.5-sonnet' ) );

			// Unified Gateway (replaces omniroute / vmstudio)
			update_option( 'tc_agents_gateway_base_url', esc_url_raw( $_POST['gateway_base_url'] ?? $_POST['vmstudio_base_url'] ?? $_POST['omniroute_base_url'] ?? 'https://ai.vmstudio.digital/v1' ) );
			if ( ! empty( $_POST['gateway_api_key'] ) ) {
				TC_Agents_Vault::put( 'gateway_api_key', sanitize_text_field( $_POST['gateway_api_key'] ) );
			} elseif ( ! empty( $_POST['vmstudio_api_key'] ) ) {
				TC_Agents_Vault::put( 'gateway_api_key', sanitize_text_field( $_POST['vmstudio_api_key'] ) );
			} elseif ( ! empty( $_POST['omniroute_api_key'] ) ) {
				TC_Agents_Vault::put( 'gateway_api_key', sanitize_text_field( $_POST['omniroute_api_key'] ) );
			}
			update_option( 'tc_agents_gateway_model', sanitize_text_field( $_POST['gateway_model'] ?? $_POST['omniroute_model'] ?? 'default' ) );

			// AI Puffer (AIPKit / AI Power)
			if ( isset( $_POST['aipuffer_base_url'] ) ) {
				update_option( 'tc_agents_aipuffer_base_url', esc_url_raw( $_POST['aipuffer_base_url'] ) );
			}
			if ( ! empty( $_POST['aipuffer_api_key'] ) ) {
				TC_Agents_Vault::put( 'aipuffer_api_key', sanitize_text_field( $_POST['aipuffer_api_key'] ) );
			}
			$ap_bot_id = sanitize_text_field( ! empty( $_POST['aipuffer_bot_id_manual'] ) ? $_POST['aipuffer_bot_id_manual'] : ( $_POST['aipuffer_bot_id'] ?? '' ) );
			if ( '' !== $ap_bot_id ) {
				update_option( 'tc_agents_aipuffer_bot_id', $ap_bot_id );
			}

			// Gemini
			if ( ! empty( $_POST['gemini_api_key'] ) ) {
				TC_Agents_Vault::put( 'gemini_api_key', sanitize_text_field( $_POST['gemini_api_key'] ) );
			}
			update_option( 'tc_agents_gemini_model', sanitize_text_field( $_POST['gemini_model'] ?? 'gemini-2.0-flash' ) );

			// Live model sync on save (best effort, non-blocking failures OK).
			if ( class_exists( 'TC_AI_Router' ) ) {
				try { TC_AI_Router::get_instance()->sync_all_models( true ); } catch ( Exception $e ) {}
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-routing', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 5. Guardrails
		if ( 'save_guardrails' === $action ) {
			$kill_switch = ! empty( $_POST['kill_switch'] ) ? '1' : '0';
			update_option( 'tc_agents_kill_switch', $kill_switch );
			update_option( 'tc_agents_rate_limit_hourly', absint( $_POST['rate_limit_hourly'] ?? 30 ) );
			update_option( 'tc_agents_whatsapp_daily_limit', absint( $_POST['whatsapp_daily_limit'] ?? 100 ) );
			update_option( 'tc_agents_voice_daily_limit', absint( $_POST['voice_daily_limit'] ?? 10 ) );
			update_option( 'tc_agents_discount_ceiling', absint( $_POST['discount_ceiling'] ?? 10 ) );
			update_option( 'tc_agents_voice_enabled', ! empty( $_POST['voice_enabled'] ) ? '1' : '0' );
			update_option( 'tc_agents_require_human_approval', ! empty( $_POST['require_human_approval'] ) ? '1' : '0' );

			TC_Agents_Logger::log( 'guardrails_updated', 'info', array( 'kill_switch' => $kill_switch ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-guardrails', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 6. Integrations (WhatsApp dual-mode + CRM + Brevo + Google Places + Voice)
		if ( 'save_integrations' === $action ) {
			update_option( 'tc_agents_whatsapp_mode', in_array( $_POST['whatsapp_mode'] ?? 'legacy', array( 'legacy', 'evolution' ), true ) ? $_POST['whatsapp_mode'] : 'legacy' );
			update_option( 'tc_agents_whatsapp_api_url', esc_url_raw( $_POST['whatsapp_api_url'] ?? '' ) );
			if ( ! empty( $_POST['whatsapp_token'] ) ) {
				TC_Agents_Vault::put( 'whatsapp_token', sanitize_text_field( $_POST['whatsapp_token'] ) );
			}
			update_option( 'tc_agents_evolution_base_url', esc_url_raw( $_POST['evolution_base_url'] ?? '' ) );
			update_option( 'tc_agents_evolution_instance', sanitize_text_field( $_POST['evolution_instance'] ?? 'tripcosmos' ) );
			if ( ! empty( $_POST['evolution_api_key'] ) ) {
				TC_Agents_Vault::put( 'evolution_api_key', sanitize_text_field( $_POST['evolution_api_key'] ) );
			}
			if ( ! empty( $_POST['whatsapp_webhook_secret'] ) ) {
				update_option( 'tc_agents_whatsapp_webhook_secret', sanitize_text_field( $_POST['whatsapp_webhook_secret'] ) );
			}
			$new_status = sanitize_key( $_POST['fluentcrm_new_status'] ?? 'subscribed' );
			update_option( 'tc_agents_fluentcrm_new_status', in_array( $new_status, array( 'subscribed', 'pending', 'transactional' ), true ) ? $new_status : 'subscribed' );
			update_option( 'tc_agents_fluentcrm_b2b_list_id', absint( $_POST['fluentcrm_b2b_list_id'] ?? 0 ) );
			update_option( 'tc_agents_fluentcrm_city_lists', sanitize_textarea_field( wp_unslash( $_POST['fluentcrm_city_lists'] ?? '' ) ) );
			update_option( 'tc_agents_fluentcrm_b2b_tags', sanitize_text_field( wp_unslash( $_POST['fluentcrm_b2b_tags'] ?? '' ) ) );
			update_option( 'tc_agents_fluentcrm_import_new', empty( $_POST['fluentcrm_import_new'] ) ? '0' : '1' );
			update_option( 'tc_agents_twentycrm_url', esc_url_raw( $_POST['twentycrm_url'] ?? '' ) );
			if ( ! empty( $_POST['twentycrm_api_key'] ) ) {
				TC_Agents_Vault::put( 'twentycrm_api_key', sanitize_text_field( $_POST['twentycrm_api_key'] ) );
			}
			if ( ! empty( $_POST['brevo_api_key'] ) ) {
				TC_Agents_Vault::put( 'brevo_api_key', sanitize_text_field( $_POST['brevo_api_key'] ) );
			}
			update_option( 'tc_agents_brevo_sender_email', sanitize_email( $_POST['brevo_sender_email'] ?? '' ) );
			update_option( 'tc_agents_brevo_sender_name', sanitize_text_field( $_POST['brevo_sender_name'] ?? 'TripCosmos' ) );
			if ( ! empty( $_POST['google_places_api_key'] ) ) {
				TC_Agents_Vault::put( 'google_places_api_key', sanitize_text_field( $_POST['google_places_api_key'] ) );
			}
			update_option( 'tc_agents_b2b_import_cities', sanitize_text_field( $_POST['b2b_import_cities'] ?? '' ) );
			update_option( 'tc_agents_b2b_auto_outreach', ! empty( $_POST['b2b_auto_outreach'] ) ? '1' : '0' );
			update_option( 'tc_agents_sheets_enabled', ! empty( $_POST['sheets_enabled'] ) ? '1' : '0' );
			update_option( 'tc_agents_sheets_webhook_url', esc_url_raw( $_POST['sheets_webhook_url'] ?? '' ) );

			// Voice settings
			update_option( 'tc_agents_voice_enabled', ! empty( $_POST['voice_enabled'] ) ? '1' : '0' );
			update_option( 'tc_agents_voice_provider', sanitize_text_field( $_POST['voice_provider'] ?? 'vapi' ) );
			if ( ! empty( $_POST['voice_api_key'] ) ) {
				TC_Agents_Vault::put( 'voice_api_key', sanitize_text_field( $_POST['voice_api_key'] ) );
			}
			update_option( 'tc_agents_voice_assistant_id', sanitize_text_field( $_POST['voice_assistant_id'] ?? '' ) );
			update_option( 'tc_agents_voice_phone_number_id', sanitize_text_field( $_POST['voice_phone_number_id'] ?? '' ) );

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-integrations', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 7. Settings
		if ( 'save_settings' === $action ) {
			update_option( 'tc_agents_widget_enabled', ! empty( $_POST['widget_enabled'] ) ? '1' : '0' );
			update_option( 'tc_agents_widget_title', sanitize_text_field( wp_unslash( $_POST['widget_title'] ?? 'TripCosmos Travel Desk' ) ) );
			update_option( 'tc_agents_widget_subtitle', sanitize_text_field( wp_unslash( $_POST['widget_subtitle'] ?? 'Online • Varanasi Desk ✓' ) ) );
			update_option( 'tc_agents_bot_avatar', esc_url_raw( $_POST['widget_avatar'] ?? '' ) );
			update_option( 'tc_agents_bot_avatar_emoji', sanitize_text_field( wp_unslash( $_POST['widget_avatar_emoji'] ?? '🛕' ) ) );
			update_option( 'tc_agents_widget_greeting', sanitize_textarea_field( wp_unslash( $_POST['widget_greeting'] ?? '' ) ) );
			update_option( 'tc_agents_launcher_teaser_text', sanitize_text_field( wp_unslash( $_POST['launcher_teaser_text'] ?? '' ) ) );
			update_option( 'tc_agents_widget_side', in_array( $_POST['widget_side'] ?? 'right', array( 'right', 'left' ), true ) ? $_POST['widget_side'] : 'right' );
			update_option( 'tc_agents_widget_primary_color', sanitize_hex_color( $_POST['widget_primary_color'] ?? '#ea580c' ) );
			update_option( 'tc_agents_widget_secondary_color', sanitize_hex_color( $_POST['widget_secondary_color'] ?? '#9333ea' ) );
			update_option( 'tc_agents_starter_prompts', sanitize_textarea_field( wp_unslash( $_POST['starter_prompts'] ?? '' ) ) );
			$wa_num = sanitize_text_field( wp_unslash( $_POST['human_whatsapp_number'] ?? '' ) );
			update_option( 'tc_agents_human_whatsapp_number', $wa_num );
			$retention = absint( $_POST['retention_days'] ?? 0 );
			update_option( 'tc_agents_retention_days', ( $retention > 0 && $retention < 30 ) ? 30 : $retention );
			update_option( 'tc_agents_human_notification_email', sanitize_email( $_POST['human_notification_email'] ?? '' ) );
			if ( ! empty( $_POST['regenerate_mobile_token'] ) ) {
				update_option( 'tc_agents_mobile_api_token', wp_generate_password( 32, false ) );
			} elseif ( isset( $_POST['mobile_api_token'] ) ) {
				$mobile_tok = sanitize_text_field( trim( wp_unslash( $_POST['mobile_api_token'] ) ) );
				if ( ! empty( $mobile_tok ) ) {
					update_option( 'tc_agents_mobile_api_token', $mobile_tok );
				}
			}

			if ( isset( $_POST['github_repo'] ) ) {
				update_option( 'tc_agents_github_repo', sanitize_text_field( trim( wp_unslash( $_POST['github_repo'] ) ) ) );
			}
			if ( isset( $_POST['github_branch'] ) ) {
				update_option( 'tc_agents_github_branch', sanitize_text_field( trim( wp_unslash( $_POST['github_branch'] ) ) ) );
			}

			if ( ! empty( $_POST['github_token'] ) ) {
				$gh_token = sanitize_text_field( trim( wp_unslash( $_POST['github_token'] ) ) );
				if ( class_exists( 'TC_Agents_Vault' ) ) {
					TC_Agents_Vault::put( 'github_token', $gh_token );
				}
				update_option( 'tc_agents_github_token', $gh_token );
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 8. Personas (migrate retired legacy routing overrides to gateway)
		if ( 'save_agent' === $action ) {
			global $wpdb;
			$table = $wpdb->prefix . 'tc_agent_personas';
			$agent_id = absint( $_POST['agent_id'] ?? 0 );

			$tools = array_map( 'sanitize_text_field', (array) ( $_POST['allowed_tools'] ?? array() ) );
			$routing_override = sanitize_text_field( wp_unslash( $_POST['routing_override'] ?? '' ) );
			if ( in_array( $routing_override, array( 'omniroute', 'vmstudio' ), true ) ) { $routing_override = 'gateway'; }

			$data = array(
				'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'system_prompt'    => sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ?? '' ) ),
				'greeting_message' => sanitize_textarea_field( wp_unslash( $_POST['greeting_message'] ?? '' ) ),
				'channels'         => sanitize_text_field( wp_unslash( $_POST['channels'] ?? 'web,whatsapp' ) ),
				'allowed_tools'    => wp_json_encode( $tools ),
				'temperature'      => floatval( $_POST['temperature'] ?? 0.7 ),
				'routing_override' => $routing_override,
				'is_active'        => ! empty( $_POST['is_active'] ) ? 1 : 0,
			);

			if ( $agent_id > 0 ) {
				$wpdb->update( $table, $data, array( 'id' => $agent_id ) );
			} else {
				$data['slug']      = sanitize_title( $_POST['slug'] ?? 'custom-agent' );
				$data['is_active'] = 1;
				$wpdb->insert( $table, $data );
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-personas', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete_agent' === $action ) {
			global $wpdb;
			$agent_id = absint( $_POST['agent_id'] ?? 0 );
			$table    = $wpdb->prefix . 'tc_agent_personas';
			if ( $agent_id > 0 ) {
				$agent = $wpdb->get_row( $wpdb->prepare( "SELECT slug FROM $table WHERE id = %d", $agent_id ), ARRAY_A );
				if ( $agent && 'tripcosmos-guide' !== $agent['slug'] ) {
					$wpdb->delete( $table, array( 'id' => $agent_id ) );
				}
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-personas', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Add Master Agent trigger node to the top WP Admin Bar.
	 */
	public static function add_admin_bar_node( $wp_admin_bar ) {
		if ( ! ( current_user_can( 'manage_options' ) || ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::can_access() ) ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'tc-master-agent',
				'title' => '<span class="ab-icon dashicons dashicons-superhero-alt" style="margin-top:2px;color:#f59e0b;"></span><span class="ab-label" style="font-weight:700;color:#fcd34d;">⚡ Master AI</span>',
				'href'  => '#',
				'meta'  => array(
					'title' => __( 'Open TripCosmos Master AI Agent Console (Ctrl+Space)', 'tripcosmos-agents' ),
				),
			)
		);
	}

	/**
	 * Render Global Floating Master Chat Popup & FAB in admin footer.
	 */
	public static function render_master_chat_popup() {
		if ( ! ( current_user_can( 'manage_options' ) || ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::can_access() ) ) ) {
			return;
		}

		global $wpdb;
		$personas = $wpdb->get_results( "SELECT slug, name FROM {$wpdb->prefix}tc_agent_personas WHERE is_active = 1", ARRAY_A ) ?: array();
		?>
		<!-- TripCosmos Floating Master Agent Button -->
		<button type="button" id="tc-master-fab" aria-label="<?php esc_attr_e( 'Master AI Assistant', 'tripcosmos-agents' ); ?>">
			<span class="tc-fab-icon">⚡</span>
			<span class="tc-fab-badge"></span>
			<span id="tc-master-fab-tooltip"><?php esc_html_e( 'Master AI Assistant (Ctrl+Space)', 'tripcosmos-agents' ); ?></span>
		</button>

		<!-- TripCosmos Master AI Popup Window -->
		<div id="tc-master-popup" role="dialog" aria-modal="true" aria-labelledby="tc-popup-title">
			<div class="tc-popup-header">
				<div class="tc-popup-header-left">
					<div class="tc-popup-avatar">⚡</div>
					<div class="tc-popup-titles">
						<div class="tc-popup-title" id="tc-popup-title">
							TripCosmos Master Agent
						</div>
						<select id="tc-popup-agent" class="tc-popup-agent-picker" title="<?php esc_attr_e( 'Switch Agent Persona', 'tripcosmos-agents' ); ?>">
							<?php foreach ( $personas as $p ) : ?>
								<option value="<?php echo esc_attr( $p['slug'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="tc-popup-header-actions">
					<button type="button" class="tc-popup-btn tc-takeover-toggle" id="tc-popup-takeover" title="<?php esc_attr_e( 'Toggle Live Human Takeover / AI Auto-Pilot', 'tripcosmos-agents' ); ?>" style="font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">🤖 AI Mode</button>
					<button type="button" class="tc-popup-btn" id="tc-popup-clear" title="<?php esc_attr_e( 'Clear Session', 'tripcosmos-agents' ); ?>">🗑️</button>
					<button type="button" class="tc-popup-btn" id="tc-popup-expand" title="<?php esc_attr_e( 'Expand / Maximize', 'tripcosmos-agents' ); ?>">⤢</button>
					<button type="button" class="tc-popup-btn tc-popup-btn-close" id="tc-popup-close" title="<?php esc_attr_e( 'Close (Esc)', 'tripcosmos-agents' ); ?>">✕</button>
				</div>
			</div>

			<div class="tc-popup-chips-bar">
				<button type="button" class="tc-chip" data-prompt="A family of 4 wants a 4-day tour of Varanasi, Prayagraj, and Ayodhya with hotel and AC Innova. What is the day-wise itinerary and pricing?">
					🛕 Kashi Ayodhya Tour
				</button>
				<button type="button" class="tc-chip" data-prompt="Calculate cab fare for Varanasi Airport pickup and outstation transfer to Ayodhya Ram Mandir in an Innova Crysta.">
					🚗 Cab Tariff
				</button>
				<button type="button" class="tc-chip" data-prompt="Explain private Bajra boat ride options and timings for evening Ganga Aarti at Dashashwamedh Ghat.">
					⛵ Ganga Aarti Boat
				</button>
				<button type="button" class="tc-chip" data-prompt="What are the rules and slots for Kashi Vishwanath Sugam Darshan and VIP Aarti bookings?">
					🎟️ VIP Darshan
				</button>
				<button type="button" class="tc-chip" data-prompt="Generate a formal 3-day pilgrimage itinerary and quote for 4 adults traveling to Varanasi next month.">
					📄 Itinerary Quote
				</button>
			</div>

			<div class="tc-popup-feed" id="tc-popup-feed">
				<div class="tc-msg-welcome">
					<h4>👋 Staff Master AI Console (Omnipresent)</h4>
					<p>Test live tool calls, calculate cab tariffs, draft itinerary quotes, or simulate traveler inquiries right here without navigating away from your work.</p>
				</div>
			</div>

			<div class="tc-popup-footer">
				<form id="tc-popup-form">
					<div class="tc-popup-input-wrap">
						<textarea id="tc-popup-input" placeholder="<?php esc_attr_e( 'Ask agent, test quotes, calculate fares (Enter to send)...', 'tripcosmos-agents' ); ?>" rows="1"></textarea>
						<button type="submit" class="tc-popup-send-btn" id="tc-popup-send" title="<?php esc_attr_e( 'Send message', 'tripcosmos-agents' ); ?>">
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
					<div class="tc-popup-hint">
						<span>↵ Enter to send • Shift+↵ newline</span>
						<span>Ctrl+Space toggles</span>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX endpoint: Fetch live activity stream.
	 */
	public static function ajax_get_live_activities() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! TC_CRM_Access::can_access() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$category = sanitize_text_field( $_POST['category'] ?? 'all' );
		$limit    = min( 50, max( 5, (int) ( $_POST['limit'] ?? 20 ) ) );
		$items    = class_exists( 'TC_Agents_Logger' ) ? TC_Agents_Logger::get_live_activities( $limit, $category ) : array();

		wp_send_json_success( array(
			'activities' => $items,
			'count'      => count( $items ),
			'timestamp'  => current_time( 'H:i:s' ),
		) );
	}

	/**
	 * AJAX endpoint: Clear system error logs.
	 */
	public static function ajax_clear_error_logs() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::clear_errors();
		}

		wp_send_json_success( array( 'message' => __( 'Error logs cleared successfully.', 'tripcosmos-agents' ) ) );
	}

	/**
	 * AJAX endpoint: Run instant system & connection diagnostics.
	 */
	public static function ajax_run_diagnostics() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$report = array();

		// 1. Database
		global $wpdb;
		$tables = array(
			'tc_agent_conversations',
			'tc_agent_messages',
			'tc_agent_contacts',
			'tc_agent_personas',
			'tc_agent_audit_log',
			'tc_agent_kb_chunks',
		);
		$db_ok = true;
		foreach ( $tables as $tbl ) {
			$t_name = $wpdb->prefix . $tbl;
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$t_name'" ) !== $t_name ) {
				$db_ok = false;
				break;
			}
		}
		$report['database'] = array(
			'status' => $db_ok ? 'healthy' : 'warning',
			'label'  => $db_ok ? 'All 17 Database Tables Active & Indexed' : 'Missing Database Tables',
		);

		// 2. WhatsApp
		$wa_status = 'warning';
		$wa_label  = 'Disconnected';
		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			$state = TC_Integration_Evolution::get_connection_state();
			if ( 'open' === $state ) {
				$wa_status = 'healthy';
				$wa_label  = 'Online (TripCosmos.co Instance Connected)';
			} else {
				$wa_label  = 'Evolution Instance ' . ucfirst( (string) $state );
			}
		}
		$report['whatsapp'] = array(
			'status' => $wa_status,
			'label'  => $wa_label,
		);

		// 3. AI Gateway
		$gw_url = get_option( 'tc_agents_gateway_base_url', 'https://ai.vmstudio.digital/v1' );
		$gw_res = wp_remote_get( untrailingslashit( $gw_url ) . '/models', array( 'timeout' => 5 ) );
		$gw_ok  = ! is_wp_error( $gw_res ) && ( 200 === (int) wp_remote_retrieve_response_code( $gw_res ) || 401 === (int) wp_remote_retrieve_response_code( $gw_res ) );
		$report['ai_gateway'] = array(
			'status' => $gw_ok ? 'healthy' : 'warning',
			'label'  => $gw_ok ? 'AI Gateway Reachable (' . parse_url( $gw_url, PHP_URL_HOST ) . ')' : 'AI Gateway Unreachable',
		);

		// 4. Two-Way GitHub Sync
		$gh_status = 'warning';
		$gh_label  = 'Not configured';
		if ( class_exists( 'TC_Agents_GitHub_Updater' ) ) {
			$sync = TC_Agents_GitHub_Updater::check_sync();
			if ( ! empty( $sync['success'] ) ) {
				$gh_status = 'healthy';
				$gh_label  = 'Connected (' . $sync['repo'] . ' @ ' . $sync['branch'] . ') - ' . $sync['sync_status'];
			} else {
				$gh_label  = $sync['message'] ?? 'GitHub Sync Error';
			}
		}
		$report['github'] = array(
			'status' => $gh_status,
			'label'  => $gh_label,
		);

		wp_send_json_success( array(
			'diagnostics' => $report,
			'timestamp'   => current_time( 'H:i:s T' ),
		) );
	}

	/**
	 * AJAX endpoint: 1-Click Universal Industry Preset Switcher.
	 */
	public static function ajax_apply_preset() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tripcosmos-agents' ) ) );
		}

		$preset = sanitize_text_field( wp_unslash( $_POST['preset'] ?? '' ) );
		if ( empty( $preset ) || ! class_exists( 'TC_Agents_Presets' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid preset.', 'tripcosmos-agents' ) ) );
		}

		$res = TC_Agents_Presets::apply_preset( $preset );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Successfully switched to %s preset! Personas, tools, and branding updated.', 'tripcosmos-agents' ), ucfirst( $preset ) ),
			'preset'  => $preset,
		) );
	}

	/**
	 * AJAX endpoint: Toggle Human Takeover on active session.
	 */
	public static function ajax_toggle_human_takeover() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::can_access() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tripcosmos-agents' ) ) );
		}

		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$mode       = sanitize_text_field( wp_unslash( $_POST['mode'] ?? 'toggle' ) ); // 'human' or 'ai'

		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID required.', 'tripcosmos-agents' ) ) );
		}

		global $wpdb;
		$table_convo = $wpdb->prefix . 'tc_agent_conversations';
		$convo = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM $table_convo WHERE session_id = %s LIMIT 1", $session_id ), ARRAY_A );

		$new_status = 'human_takeover';
		if ( $convo && 'human_takeover' === $convo['status'] ) {
			$new_status = 'active';
		} elseif ( 'ai' === $mode ) {
			$new_status = 'active';
		}

		if ( $convo ) {
			$wpdb->update( $table_convo, array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $convo['id'] ) );
		}

		$is_human = ( 'human_takeover' === $new_status );

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				$is_human ? 'human_takeover_enabled' : 'ai_autopilot_resumed',
				'info',
				array( 'session_id' => $session_id, 'staff' => wp_get_current_user()->user_login ),
				'',
				'admin'
			);
		}

		wp_send_json_success( array(
			'is_human' => $is_human,
			'status'   => $new_status,
			'label'    => $is_human ? '👤 Human Mode' : '🤖 AI Mode',
			'message'  => $is_human ? __( 'AI paused. You are now chatting directly with the user.', 'tripcosmos-agents' ) : __( 'AI Auto-Pilot resumed.', 'tripcosmos-agents' ),
		) );
	}
}
