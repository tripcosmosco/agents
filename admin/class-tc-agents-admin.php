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
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_save' ) );
		add_action( 'wp_ajax_tc_get_lead_memory', array( __CLASS__, 'ajax_get_lead_memory' ) );
		add_action( 'wp_ajax_tc_update_lead_stage', array( __CLASS__, 'ajax_update_lead_stage' ) );
		add_action( 'wp_ajax_tc_sync_catalog_kb', array( __CLASS__, 'ajax_sync_catalog_kb' ) );
		add_action( 'wp_ajax_tc_sync_aipuffer_bots', array( __CLASS__, 'ajax_sync_aipuffer_bots' ) );
		add_action( 'wp_ajax_tc_sync_provider_models', array( __CLASS__, 'ajax_sync_provider_models' ) );
		add_action( 'wp_ajax_tc_test_twentycrm', array( __CLASS__, 'ajax_test_twentycrm' ) );
		add_action( 'wp_ajax_tc_toggle_widget', array( __CLASS__, 'ajax_toggle_widget' ) );
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

		// 2. Master Agent Console
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Master Agent Console', 'tripcosmos-agents' ),
			__( 'Master Console', 'tripcosmos-agents' ),
			'manage_options',
			'tc-agents-console',
			array( __CLASS__, 'render_console_page' )
		);

		// 3. Leads & Pipeline
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Leads & Deals Pipeline', 'tripcosmos-agents' ),
			__( 'Leads Pipeline', 'tripcosmos-agents' ),
			'manage_options',
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
		require_once TC_AGENTS_PATH . 'admin/views/console.php';
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

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$contact_id = absint( $_GET['contact_id'] ?? 0 );
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

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$lead_id = absint( $_POST['lead_id'] ?? 0 );
		$stage   = sanitize_key( $_POST['stage'] ?? '' );

		$valid_stages = array( 'inquiry', 'qualified', 'proposal', 'negotiation', 'won', 'lost' );
		if ( ! $lead_id || ! in_array( $stage, $valid_stages, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
		}

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';

		$wpdb->update(
			$table_contacts,
			array(
				'stage'      => $stage,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// If won or qualified, trigger automated voice hook or sequence if enabled
		if ( 'won' === $stage || 'proposal' === $stage ) {
			$lead_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE id = %d", $lead_id ), ARRAY_A );
			if ( $lead_row && '1' === (string) get_option( 'tc_agents_voice_enabled', '0' ) ) {
				TC_Agents_Queue::push( 'voice_trigger', array( 'lead' => $lead_row ) );
			}
		}

		wp_send_json_success( array( 'lead_id' => $lead_id, 'stage' => $stage ) );
	}

	/**
	 * AJAX endpoint to trigger 1-click sync of WordPress treks and posts into KB.
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
			$all_convos = $wpdb->get_results( "SELECT * FROM $table_convo ORDER BY id DESC", ARRAY_A ) ?: array();
			foreach ( $all_convos as $c ) {
				fputcsv( $out, array( $c['id'], $c['session_id'], $c['channel'], $c['agent_id'], $c['status'], $c['started_at'], $c['last_message_at'] ) );
			}
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
			global $wpdb;
			$lead_id = absint( $_POST['lead_id'] ?? 0 );
			$stage   = sanitize_text_field( $_POST['stage'] ?? 'inquiry' );
			if ( $lead_id > 0 ) {
				$wpdb->update(
					$wpdb->prefix . 'tc_agent_contacts',
					array( 'stage' => $stage, 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => $lead_id )
				);
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-leads', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'save_lead' === $action ) {
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'tc_agent_contacts',
				array(
					'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
					'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
					'email'          => sanitize_email( $_POST['email'] ?? '' ),
					'source_channel' => sanitize_text_field( $_POST['source_channel'] ?? 'web' ),
					'stage'          => sanitize_text_field( $_POST['stage'] ?? 'inquiry' ),
					'deal_value'     => floatval( $_POST['deal_value'] ?? 0.0 ),
					'score'          => 50,
					'currency'       => 'INR',
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				)
			);
			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-leads', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 2. Knowledge Base Actions
		if ( 'save_knowledge' === $action ) {
			$doc_id = absint( $_POST['doc_id'] ?? 0 );
			TC_Agent_Knowledge::save_document(
				array(
					'title'     => sanitize_text_field( $_POST['title'] ?? '' ),
					'category'  => sanitize_text_field( $_POST['category'] ?? 'faq' ),
					'tags'      => sanitize_text_field( $_POST['tags'] ?? '' ),
					'content'   => wp_kses_post( $_POST['content'] ?? '' ),
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
			$delays   = array_map( 'absint', (array) ( $_POST['step_delays'] ?? array() ) );
			$messages = array_map( 'sanitize_textarea_field', (array) ( $_POST['step_messages'] ?? array() ) );

			$steps = array();
			foreach ( $delays as $idx => $delay ) {
				$msg = $messages[ $idx ] ?? '';
				if ( ! empty( $msg ) ) {
					$steps[] = array(
						'delay_hours' => max( 1, $delay ),
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

		// 4. AI Routing (canonical: openrouter, gateway, aipuffer, gemini)
		if ( 'save_routing' === $action ) {
			$priority = array_map( 'sanitize_text_field', (array) ( $_POST['provider_priority'] ?? array() ) );
			// Migrate any retired slugs submitted by stale forms.
			$map = array( 'omniroute' => 'gateway', 'vmstudio' => 'gateway' );
			$priority = array_values( array_unique( array_map( function( $s ) use ( $map ) { return $map[ $s ] ?? $s; }, $priority ) ) );
			if ( empty( $priority ) ) { $priority = array( 'openrouter', 'gateway', 'aipuffer', 'gemini' ); }
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
			update_option( 'tc_agents_whatsapp_webhook_secret', sanitize_text_field( $_POST['whatsapp_webhook_secret'] ?? '' ) );
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
			update_option( 'tc_agents_widget_title', sanitize_text_field( $_POST['widget_title'] ?? 'TripCosmos Travel Desk' ) );
			update_option( 'tc_agents_widget_subtitle', sanitize_text_field( $_POST['widget_subtitle'] ?? 'Online • Varanasi Desk ✓' ) );
			update_option( 'tc_agents_bot_avatar', esc_url_raw( $_POST['widget_avatar'] ?? '' ) );
			update_option( 'tc_agents_bot_avatar_emoji', sanitize_text_field( $_POST['widget_avatar_emoji'] ?? '🛕' ) );
			update_option( 'tc_agents_widget_greeting', sanitize_textarea_field( $_POST['widget_greeting'] ?? '' ) );
			update_option( 'tc_agents_launcher_teaser_text', sanitize_text_field( $_POST['launcher_teaser_text'] ?? '' ) );
			update_option( 'tc_agents_widget_side', in_array( $_POST['widget_side'] ?? 'right', array( 'right', 'left' ), true ) ? $_POST['widget_side'] : 'right' );
			update_option( 'tc_agents_widget_primary_color', sanitize_hex_color( $_POST['widget_primary_color'] ?? '#ea580c' ) );
			update_option( 'tc_agents_widget_secondary_color', sanitize_hex_color( $_POST['widget_secondary_color'] ?? '#9333ea' ) );
			update_option( 'tc_agents_starter_prompts', sanitize_textarea_field( $_POST['starter_prompts'] ?? '' ) );
			update_option( 'tc_agents_human_whatsapp_number', sanitize_text_field( $_POST['human_whatsapp_number'] ?? '' ) );
			update_option( 'tc_agents_human_notification_email', sanitize_email( $_POST['human_notification_email'] ?? '' ) );

			wp_safe_redirect( add_query_arg( array( 'page' => 'tc-agents-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// 8. Personas (migrate retired routing overrides to gateway)
		if ( 'save_agent' === $action ) {
			global $wpdb;
			$table = $wpdb->prefix . 'tc_agent_personas';
			$agent_id = absint( $_POST['agent_id'] ?? 0 );

			$tools = array_map( 'sanitize_text_field', (array) ( $_POST['allowed_tools'] ?? array() ) );
			$routing_override = sanitize_text_field( $_POST['routing_override'] ?? '' );
			if ( in_array( $routing_override, array( 'aipuffer', 'omniroute', 'vmstudio' ), true ) ) { $routing_override = 'gateway'; }

			$data = array(
				'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
				'system_prompt'    => sanitize_textarea_field( $_POST['system_prompt'] ?? '' ),
				'greeting_message' => sanitize_textarea_field( $_POST['greeting_message'] ?? '' ),
				'channels'         => sanitize_text_field( $_POST['channels'] ?? 'web,whatsapp' ),
				'allowed_tools'    => wp_json_encode( $tools ),
				'temperature'      => floatval( $_POST['temperature'] ?? 0.7 ),
				'routing_override' => $routing_override,
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
	}
}
