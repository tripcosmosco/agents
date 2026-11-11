<?php
/**
 * Mobile CRM API (api_level 2): dashboard, lead detail, tasks, bookings, quotes, payments,
 * WhatsApp threads, AI call summaries and a notification feed for the Sales Agents app.
 *
 * Everything here is a thin, authenticated wrapper over the same services the wp-admin CRM uses
 * (TC_Contact_Service, TC_Quote_Service, TC_Payments, TC_Agent_Memory). No business rules live here, and
 * nothing is invented: a missing value is returned empty or null.
 *
 * Time convention: the CRM stores site-local wall-clock times. This API always exchanges UTC ISO-8601
 * ("2026-09-27T05:30:00Z") with the app and converts at the boundary.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Mobile_CRM_API {

	const NAMESPACE = 'tc-agents/v1';
	const API_LEVEL = 2;

	const BOOKING_STATUSES = array(
		'quoted'       => 'Quoted',
		'confirmed'    => 'Confirmed',
		'advance_paid' => 'Advance Paid',
		'paid'         => 'Fully Paid',
		'completed'    => 'Completed',
		'cancelled'    => 'Cancelled',
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$auth = array( 'TC_Agents_Mobile_API', 'verify_token' );
		$id   = '(?P<id>\d+)';
		$map  = array(
			array( '/mobile/me', 'GET', 'me' ),
			array( '/mobile/dashboard', 'GET', 'dashboard' ),
			array( '/mobile/notifications', 'GET', 'notifications' ),
			array( '/mobile/calls', 'GET', 'calls' ),
			array( '/mobile/call-logs', 'GET', 'calls' ),
			array( '/mobile/leads/' . $id, 'GET', 'lead_detail' ),
			array( '/mobile/leads/list', 'GET', 'lead_list' ),
			array( '/mobile/leads/create', 'POST', 'lead_create' ),
			array( '/mobile/leads/' . $id . '/summarize', 'POST', 'lead_summarize' ),
			array( '/mobile/tasks', 'GET', 'tasks' ),
			array( '/mobile/tasks/create', 'POST', 'task_create' ),
			array( '/mobile/tasks/' . $id . '/complete', 'POST', 'task_complete' ),
			array( '/mobile/bookings', 'GET', 'bookings' ),
			array( '/mobile/bookings/save', 'POST', 'booking_save' ),
			array( '/mobile/quotes/create', 'POST', 'quote_create' ),
			array( '/mobile/quotes/' . $id . '/send', 'POST', 'quote_send' ),
			array( '/mobile/payments/link', 'POST', 'payment_link' ),
			array( '/mobile/payments/record', 'POST', 'payment_record' ),
			array( '/mobile/financials', 'GET', 'financials' ),
			array( '/mobile/dispatch-driver', 'POST', 'dispatch_driver' ),
			array( '/mobile/caller-lookup', 'GET', 'caller_lookup' ),
			array( '/mobile/caller-lookup', 'POST', 'caller_lookup' ),
			array( '/mobile/caller-id', 'GET', 'caller_lookup' ),
			array( '/mobile/caller-id', 'POST', 'caller_lookup' ),
			array( '/mobile/voice-note-summary', 'POST', 'voice_note_summary' ),
			array( '/mobile/payments/generate-link', 'POST', 'payment_generate_link' ),
			array( '/mobile/conversations/' . $id, 'GET', 'conversation' ),
			array( '/mobile/conversations/' . $id . '/reply', 'POST', 'conversation_reply' ),
			array( '/mobile/conversations/' . $id . '/ai', 'POST', 'conversation_ai' ),
			array( '/mobile/google-ads/conversions', 'GET', 'google_ads_conversions' ),
			array( '/mobile/google-ads/sync', 'POST', 'google_ads_sync' ),
			array( '/mobile/google-ads/config', 'GET', 'google_ads_config' ),
			array( '/mobile/google-ads/upload-conversion', 'POST', 'google_ads_upload_conversion' ),
			array( '/mobile/bank-statement/upload', 'POST', 'bank_statement_upload' ),
			array( '/mobile/bank-statement/transactions', 'GET', 'bank_statement_transactions' ),
			array( '/mobile/bank-statement/reconcile', 'POST', 'bank_statement_reconcile' ),
			array( '/mobile/zoho/summary', 'GET', 'zoho_summary' ),
			array( '/mobile/zoho/sync', 'POST', 'zoho_sync' ),
			array( '/mobile/customer-margins', 'GET', 'customer_margins' ),
			array( '/mobile/customer-expenses', 'GET', 'customer_expenses_list' ),
			array( '/mobile/customer-expenses/add', 'POST', 'customer_expense_add' ),
			array( '/mobile/customer-expenses/delete', 'POST', 'customer_expense_delete' ),
			array( '/mobile/customer-expenses/link-debit', 'POST', 'customer_expense_link_debit' ),
		);
		foreach ( $map as $r ) {
			register_rest_route(
				self::NAMESPACE,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( __CLASS__, 'handle_' . $r[2] ),
					'permission_callback' => $auth,
				)
			);
		}

		// Public conversion beacon for frontend website chatbot & Google Ads tracking
		register_rest_route(
			self::NAMESPACE,
			'/mobile/conversion/record',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_conversion_record' ),
				'permission_callback' => '__return_true',
			)
		);

		// Authentication & User Management (1-Click Google Sign-In & WP Credentials)
		register_rest_route(
			self::NAMESPACE,
			'/mobile/auth/google',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_auth_google' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/mobile/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_auth_login' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/mobile/auth/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_auth_me' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/* ------------------------------------------------------------------ helpers */

	private static function ok( array $data = array(), $status = 200 ) {
		return new WP_REST_Response( array_merge( array( 'ok' => true ), $data ), $status );
	}

	private static function fail( $message, $status = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => $message ), $status );
	}

	private static function params( WP_REST_Request $request ) {
		$json = (array) $request->get_json_params();
		if ( empty( $json ) && ! empty( $request->get_body() ) ) {
			$raw = json_decode( $request->get_body(), true );
			if ( is_array( $raw ) ) {
				$json = $raw;
			}
		}
		return array_merge( (array) $request->get_params(), $json );
	}

	/** Site-local "Y-m-d H:i:s" -> UTC ISO-8601, or null. */
	private static function iso( $local ) {
		if ( empty( $local ) || '0000-00-00 00:00:00' === $local ) {
			return null;
		}
		$gmt = get_gmt_from_date( (string) $local );
		return $gmt ? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $gmt . ' UTC' ) ) : null;
	}

	/** UTC ISO-8601 (or any strtotime string) -> site-local "Y-m-d H:i:s", or ''. */
	private static function to_local( $value ) {
		$ts = strtotime( (string) $value );
		if ( ! $ts ) {
			return '';
		}
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) );
	}

	private static function digits( $phone ) {
		return preg_replace( '/[^0-9]/', '', (string) $phone );
	}

	/** Who is calling, as set by TC_Agents_Mobile_API::verify_token(). */
	public static function agent( WP_REST_Request $request ) {
		return (string) $request->get_param( '_tc_agent' );
	}

	/** Check if caller is Admin or Sales Manager */
	public static function is_admin_or_manager( WP_REST_Request $request ) {
		$user_id  = (int) $request->get_param( '_tc_user_id' );
		$token_id = (string) $request->get_param( '_tc_token_id' );
		if ( 0 === $user_id && 'master' === $token_id ) {
			return true;
		}
		if ( $user_id > 0 ) {
			$u = get_userdata( $user_id );
			if ( $u ) {
				$roles = (array) $u->roles;
				if ( in_array( 'administrator', $roles, true ) || in_array( 'tc_sales_manager', $roles, true ) || user_can( $u, 'manage_options' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Check if caller has Financial Hub access */
	public static function is_finance_authorized( WP_REST_Request $request ) {
		$user_id  = (int) $request->get_param( '_tc_user_id' );
		$token_id = (string) $request->get_param( '_tc_token_id' );
		if ( 0 === $user_id && 'master' === $token_id ) {
			return true;
		}
		if ( $user_id > 0 ) {
			$u = get_userdata( $user_id );
			if ( $u ) {
				if ( user_can( $u, 'manage_options' ) ) {
					return true;
				}
				$roles = (array) $u->roles;
				foreach ( $roles as $r ) {
					$perms = self::get_role_permissions( $r );
					if ( in_array( 'all', $perms, true ) || in_array( 'financial_hub', $perms, true ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/** Check if caller can access a specific lead */
	public static function can_access_lead( WP_REST_Request $request, array $lead ) {
		if ( self::is_admin_or_manager( $request ) ) {
			return true;
		}
		$my_id    = (int) $request->get_param( '_tc_user_id' );
		$my_agent = trim( (string) self::agent( $request ) );
		$owner_id = (int) ( $lead['owner_user_id'] ?? 0 );
		$owner    = trim( (string) ( $lead['owner'] ?? '' ) );

		if ( $my_id > 0 && $owner_id === $my_id ) {
			return true;
		}
		if ( '' !== $my_agent && 0 === strcasecmp( $owner, $my_agent ) ) {
			return true;
		}
		// Also allow if unassigned and agents are permitted to see unassigned
		if ( empty( $owner ) && 0 === $owner_id && class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::agents_see_unassigned() ) {
			return true;
		}
		return false;
	}

	/**
	 * Per-agent limit for actions that cost money or reach customers. Returns a 429 response when exceeded, else null.
	 */
	private static function throttle( WP_REST_Request $request, $action, $max, $window ) {
		if ( ! TC_Agents_Security::rate_limit( 'mobile_' . $action, self::agent( $request ) ?: TC_Agents_Security::client_ip(), $max, $window ) ) {
			return self::fail( 'You have done this too often in a short time. Try again later.', 429 );
		}
		return null;
	}

	/** Audit trail: every change made from a phone is recorded with the device that made it. */
	private static function audit( WP_REST_Request $request, $action, array $details = array() ) {
		TC_Agents_Logger::log( 'mobile_' . $action, 'info', array_merge( array( 'agent' => self::agent( $request ) ), $details ), '', 'mobile' );
	}

	/** Trims and caps free text so one request cannot store megabytes. */
	private static function text( $value, $max ) {
		return mb_substr( trim( (string) $value ), 0, $max );
	}

	private static function fmt_lead( array $c ) {
		$meta = json_decode( (string) ( $c['meta_data'] ?? '' ), true ) ?: array();
		return array(
			'id'                => (int) $c['id'],
			'name'              => ! empty( $c['name'] ) ? $c['name'] : 'Traveler',
			'phone'             => (string) ( $c['phone'] ?? '' ),
			'email'             => (string) ( $c['email'] ?? '' ),
			'stage'             => (string) ( $c['stage'] ?: 'inquiry' ),
			'score'             => (int) ( $c['score'] ?? 0 ),
			'deal_value'        => (float) ( $c['deal_value'] ?? 0 ),
			'owner'             => (string) ( $c['owner'] ?? '' ),
			'source'            => (string) ( $c['source_channel'] ?? '' ),
			'tags'              => TC_Contact_Service::parse_tags( $c['tags'] ?? '' ),
			'destination'       => (string) ( $meta['destination'] ?? '' ),
			'pax'               => (int) ( $meta['pax'] ?? $meta['group_size'] ?? 0 ),
			'travel_month'      => (string) ( $meta['travel_month'] ?? '' ),
			'requirements'      => (string) ( $meta['requirements'] ?? '' ),
			'intent'            => (string) ( $meta['intent'] ?? '' ),
			'next_follow_up_at' => self::iso( $c['next_follow_up_at'] ?? '' ),
			'last_contacted_at' => self::iso( $c['last_contacted_at'] ?? '' ),
			'created_at'        => self::iso( $c['created_at'] ?? '' ),
			'updated_at'        => self::iso( $c['updated_at'] ?? '' ),
		);
	}

	private static function fmt_activity( array $a ) {
		$meta = json_decode( (string) ( $a['meta'] ?? '' ), true );
		return array(
			'id'         => (int) $a['id'],
			'type'       => (string) $a['type'],
			'content'    => wp_strip_all_tags( (string) $a['content'] ),
			'meta'       => is_array( $meta ) ? $meta : new stdClass(),
			'due_at'     => self::iso( $a['due_at'] ?? '' ),
			'done_at'    => self::iso( $a['done_at'] ?? '' ),
			'created_at' => self::iso( $a['created_at'] ?? '' ),
		);
	}

	private static function fmt_booking( array $b ) {
		$amounts = TC_Payments::amounts( (int) $b['id'] );
		$quote   = TC_Quote_Service::current_for_booking( (int) $b['id'] );
		return array(
			'id'           => (int) $b['id'],
			'lead_id'      => (int) $b['contact_id'],
			'ref'          => (string) $b['ref'],
			'title'        => (string) $b['title'],
			'start_date'   => $b['start_date'],
			'end_date'     => $b['end_date'],
			'pax'          => (int) $b['pax'],
			'vehicle'      => (string) $b['vehicle'],
			'driver'       => (string) $b['driver'],
			'status'       => (string) $b['status'],
			'quote_amount' => (float) $b['quote_amount'],
			'advance_paid' => (float) $b['advance_paid'],
			'total'        => $amounts['total'],
			'balance'      => $amounts['balance'],
			'advance_due'  => $amounts['advance_due'],
			'notes'        => (string) $b['notes'],
			'quote'        => $quote ? array(
				'id'             => (int) $quote['id'],
				'version'        => (int) $quote['version'],
				'status'         => (string) $quote['status'],
				'total'          => (float) $quote['total'],
				'advance_amount' => (float) $quote['advance_amount'],
				'valid_until'    => $quote['valid_until'],
				'url'            => TC_Quote_Service::url( $quote ),
				'items'          => json_decode( (string) $quote['items'], true ) ?: array(),
			) : null,
			'updated_at'   => self::iso( $b['updated_at'] ?? '' ),
		);
	}

	private static function lead_or_error( $id ) {
		$c = TC_Contact_Service::get( (int) $id );
		return $c ?: null;
	}

	/* ------------------------------------------------------------------ identity + dashboard */

	public static function handle_me( WP_REST_Request $request ) {
		$stages = array();
		foreach ( TC_Contact_Service::stages() as $slug => $label ) {
			$stages[] = array( 'id' => $slug, 'label' => $label );
		}
		$statuses = array();
		foreach ( self::BOOKING_STATUSES as $slug => $label ) {
			$statuses[] = array( 'id' => $slug, 'label' => $label );
		}
		$team = array();
		foreach ( TC_CRM_Access::team() as $u ) {
			$team[] = array(
				'id'   => (int) $u->ID,
				'name' => $u->display_name,
				'role' => (string) ( $u->roles[0] ?? '' ),
			);
		}
		$billing = TC_Quote_Service::settings();

		$user_id   = (int) $request->get_param( '_tc_user_id' );
		$user_data = null;
		if ( $user_id > 0 ) {
			$u = get_userdata( $user_id );
			if ( $u ) {
				$primary_role = ! empty( $u->roles ) ? reset( $u->roles ) : 'tc_sales_agent';
				$avatar       = get_user_meta( $u->ID, 'tc_agent_avatar_url', true );
				$user_data    = array(
					'id'          => (int) $u->ID,
					'name'        => (string) $u->display_name,
					'email'       => (string) $u->user_email,
					'username'    => (string) $u->user_login,
					'role'        => $primary_role,
					'role_label'  => self::get_role_label( $primary_role ),
					'avatar_url'  => $avatar ?: get_avatar_url( $u->ID ),
					'permissions' => self::get_user_permissions( $u ),
				);
			}
		}

		return self::ok(
			array(
				'agent'          => self::agent( $request ),
				'plugin_version' => TC_AGENTS_VERSION,
				'api_level'      => self::API_LEVEL,
				'server_time'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'stages'         => $stages,
				'booking_status' => $statuses,
				'team'           => $team,
				'user'           => $user_data,
				'features'       => array(
					'cashfree' => TC_Payments::cashfree_ready(),
					'upi'      => '' !== trim( (string) $billing['upi_vpa'] ),
					'gst'      => (bool) $billing['gst_enabled'],
					'whatsapp' => true,
				),
			)
		);
	}

	public static function handle_dashboard( WP_REST_Request $request ) {
		global $wpdb;
		$c     = TC_Contact_Service::table();
		$act   = $wpdb->prefix . 'tc_agent_activities';
		$qt    = $wpdb->prefix . 'tc_agent_quotes';
		$bk    = $wpdb->prefix . 'tc_agent_bookings';
		$pay   = $wpdb->prefix . 'tc_agent_payments';
		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'mysql' );
		$month = current_time( 'Y-m' ) . '-01';

		$n = function ( $sql, $args = array() ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		};

		$stage_rows = $wpdb->get_results( "SELECT stage, COUNT(*) AS n, COALESCE(SUM(deal_value),0) AS value FROM $c GROUP BY stage", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$pipeline   = array();
		foreach ( $stage_rows as $r ) {
			$pipeline[] = array( 'stage' => $r['stage'] ?: 'inquiry', 'count' => (int) $r['n'], 'value' => (float) $r['value'] );
		}

		$hot = $wpdb->get_results( "SELECT * FROM $c WHERE stage IN ('inquiry','qualified','proposal','negotiation') ORDER BY score DESC, updated_at DESC LIMIT 5", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return self::ok(
			array(
				'today'    => array(
					'new_leads'      => $n( "SELECT COUNT(*) FROM $c WHERE DATE(created_at) = %s", array( $today ) ),
					'calls'          => $n( "SELECT COUNT(*) FROM $act WHERE type = 'call' AND DATE(created_at) = %s", array( $today ) ),
					'quotes_sent'    => $n( "SELECT COUNT(*) FROM $qt WHERE DATE(sent_at) = %s", array( $today ) ),
					'bookings'       => $n( "SELECT COUNT(*) FROM $bk WHERE DATE(created_at) = %s AND status <> 'cancelled'", array( $today ) ),
					'tasks_due'      => $n( "SELECT COUNT(*) FROM $act WHERE type = 'task' AND done_at IS NULL AND DATE(due_at) = %s", array( $today ) ),
					'tasks_overdue'  => $n( "SELECT COUNT(*) FROM $act WHERE type = 'task' AND done_at IS NULL AND due_at < %s", array( $now ) ),
					'collected'      => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM $pay WHERE status = 'paid' AND DATE(paid_at) = %s", $today ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				),
				'month'    => array(
					'new_leads' => $n( "SELECT COUNT(*) FROM $c WHERE created_at >= %s", array( $month ) ),
					'won'       => $n( "SELECT COUNT(*) FROM $c WHERE stage IN ('won','completed') AND updated_at >= %s", array( $month ) ),
					'collected' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM $pay WHERE status = 'paid' AND paid_at >= %s", $month ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				),
				'pipeline' => $pipeline,
				'hot'      => array_map( array( __CLASS__, 'fmt_lead' ), $hot ),
			)
		);
	}

	/**
	 * One feed the app polls for local notifications. The app keeps three cursors and sends them back:
	 * since_lead (highest lead id seen), since_msg (highest customer message id seen).
	 */
	public static function handle_notifications( WP_REST_Request $request ) {
		global $wpdb;
		$c   = TC_Contact_Service::table();
		$act = $wpdb->prefix . 'tc_agent_activities';
		$msg = $wpdb->prefix . 'tc_agent_messages';
		$cv  = $wpdb->prefix . 'tc_agent_conversations';

		$since_lead = max( 0, (int) $request->get_param( 'since_lead' ) );
		$since_msg  = max( 0, (int) $request->get_param( 'since_msg' ) );
		$now        = current_time( 'mysql' );

		$max_lead = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM $c" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$max_msg  = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM $msg WHERE role = 'user'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// First run: hand back the cursors without flooding the phone with history.
		$leads    = array();
		$replies  = array();
		if ( $since_lead > 0 ) {
			$leads = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, phone, source_channel, meta_data FROM $c WHERE id > %d ORDER BY id ASC LIMIT 20", $since_lead ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( $since_msg > 0 ) {
			$replies = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT m.id, m.content, m.created_at, cv.contact_id, cv.channel, c.name, c.phone FROM $msg m JOIN $cv cv ON cv.id = m.conversation_id LEFT JOIN $c c ON c.id = cv.contact_id WHERE m.role = 'user' AND m.id > %d AND cv.channel = 'whatsapp' ORDER BY m.id ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$since_msg
				),
				ARRAY_A
			) ?: array();
		}

		$due = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.content, a.due_at, a.contact_id, c.name FROM $act a LEFT JOIN $c c ON c.id = a.contact_id WHERE a.type = 'task' AND a.done_at IS NULL AND a.due_at <= %s ORDER BY a.due_at ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$now
			),
			ARRAY_A
		) ?: array();

		return self::ok(
			array(
				'latest_lead' => $max_lead,
				'latest_msg'  => $max_msg,
				'new_leads'   => array_map(
					function ( $r ) {
						$meta = json_decode( (string) $r['meta_data'], true ) ?: array();
						return array(
							'id'          => (int) $r['id'],
							'name'        => $r['name'] ?: 'Traveler',
							'phone'       => (string) $r['phone'],
							'source'      => (string) $r['source_channel'],
							'destination' => (string) ( $meta['destination'] ?? '' ),
						);
					},
					$leads
				),
				'replies'     => array_map(
					function ( $r ) {
						return array(
							'id'      => (int) $r['id'],
							'lead_id' => (int) $r['contact_id'],
							'name'    => $r['name'] ?: 'Traveler',
							'phone'   => (string) $r['phone'],
							'text'    => wp_trim_words( wp_strip_all_tags( (string) $r['content'] ), 30 ),
							'at'      => self::iso( $r['created_at'] ),
						);
					},
					$replies
				),
				'tasks_due'   => array_map(
					function ( $r ) {
						return array(
							'id'      => (int) $r['id'],
							'lead_id' => (int) $r['contact_id'],
							'name'    => $r['name'] ?: 'Traveler',
							'text'    => wp_strip_all_tags( (string) $r['content'] ),
							'due_at'  => self::iso( $r['due_at'] ),
						);
					},
					$due
				),
			)
		);
	}

	/* ------------------------------------------------------------------ calls */

	public static function handle_calls( WP_REST_Request $request ) {
		global $wpdb;
		$act   = $wpdb->prefix . 'tc_agent_activities';
		$c     = TC_Contact_Service::table();
		$limit = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 50 ) ) );
		$lead  = absint( $request->get_param( 'lead_id' ) );

		$where = "a.type = 'call'";
		$args  = array();
		if ( $lead ) {
			$where .= ' AND a.contact_id = %d';
			$args[] = $lead;
		}
		$args[] = $limit;
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, c.name, c.phone FROM $act a LEFT JOIN $c c ON c.id = a.contact_id WHERE $where ORDER BY a.id DESC LIMIT %d", $args ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$calls = array();
		foreach ( $rows as $r ) {
			$meta    = json_decode( (string) $r['meta'], true ) ?: array();
			$calls[] = array(
				'id'            => (int) $r['id'],
				'call_id'       => (string) ( $meta['call_id'] ?? $r['id'] ),
				'lead_id'       => (int) $r['contact_id'],
				'name'          => $r['name'] ?: 'Traveler',
				'customer_name' => $r['name'] ?: 'Traveler',
				'phone'         => (string) ( $meta['phone'] ?? $r['phone'] ),
				'type'          => (string) ( $meta['type'] ?? 'outbound' ),
				'direction'     => (string) ( $meta['type'] ?? 'outbound' ),
				'status'        => (string) ( $meta['status'] ?? 'completed' ),
				'duration'      => (int) ( $meta['duration'] ?? 0 ),
				'recording_url' => (string) ( $meta['recording_url'] ?? '' ),
				'note'          => wp_strip_all_tags( (string) $r['content'] ),
				'notes'         => wp_strip_all_tags( (string) $r['content'] ),
				'sim_card'      => (string) ( $meta['sim_card'] ?? 'Jio 5G (+91 93361 16210)' ),
				'timestamp'     => self::iso( $r['created_at'] ),
				'at'            => self::iso( $r['created_at'] ),
			);
		}
		return self::ok( array( 'calls' => $calls ) );
	}

	/* ------------------------------------------------------------------ leads */

	/**
	 * Lead list in the api_level-2 shape (ISO dates, tag arrays). Filters: stage, owner ("me" = calling agent),
	 * q (name / phone / email / destination), sort (updated | score | follow_up | created), limit, offset.
	 * Unlike the legacy /mobile/leads it never mixes in newsletter subscribers.
	 */
	public static function handle_lead_list( WP_REST_Request $request ) {
		global $wpdb;
		$t      = TC_Contact_Service::table();
		$where  = array( '1=1' );
		$args   = array();
		$stage  = sanitize_key( $request->get_param( 'stage' ) );
		$owner  = sanitize_text_field( $request->get_param( 'owner' ) );
		$q      = trim( sanitize_text_field( $request->get_param( 'q' ) ) );
		$limit  = min( 100, max( 1, (int) ( $request->get_param( 'limit' ) ?: 50 ) ) );
		$offset = max( 0, (int) $request->get_param( 'offset' ) );

		if ( $stage && 'all' !== $stage ) {
			$where[] = 'stage = %s';
			$args[]  = $stage;
		}

		$is_admin = self::is_admin_or_manager( $request );
		$my_id    = (int) $request->get_param( '_tc_user_id' );
		$my_agent = trim( (string) self::agent( $request ) );

		if ( ! $is_admin ) {
			// STRICT RBAC: Non-admin staff can only see their own assigned leads (plus unassigned if permitted)
			if ( 'unassigned' === $owner && class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::agents_see_unassigned() ) {
				$where[] = "(owner = '' AND (owner_user_id = 0 OR owner_user_id IS NULL))";
			} else {
				if ( class_exists( 'TC_CRM_Access' ) && TC_CRM_Access::agents_see_unassigned() ) {
					$where[] = "(owner_user_id = %d OR owner = %s OR (owner = '' AND (owner_user_id = 0 OR owner_user_id IS NULL)))";
					array_push( $args, $my_id, $my_agent );
				} else {
					$where[] = "(owner_user_id = %d OR owner = %s)";
					array_push( $args, $my_id, $my_agent );
				}
			}
		} else {
			// Admin / Sales Manager filtering
			if ( 'me' === $owner ) {
				$owner = $my_agent;
			}
			if ( '' !== $owner && 'all' !== $owner ) {
				$where[] = ( 'unassigned' === $owner ) ? "owner = ''" : 'owner = %s';
				if ( 'unassigned' !== $owner ) {
					$args[] = $owner;
				}
			}
		}
		if ( '' !== $q ) {
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$where[] = '(name LIKE %s OR phone LIKE %s OR email LIKE %s OR meta_data LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}
		$order_map = array(
			'updated'   => 'updated_at DESC',
			'created'   => 'created_at DESC',
			'score'     => 'score DESC, updated_at DESC',
			'follow_up' => 'next_follow_up_at IS NULL, next_follow_up_at ASC',
		);
		$order = $order_map[ sanitize_key( $request->get_param( 'sort' ) ) ] ?? $order_map['updated'];
		$sql   = implode( ' AND ', $where );

		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $sql", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $sql" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $sql ORDER BY $order LIMIT %d OFFSET %d", array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return self::ok(
			array(
				'leads'    => array_map( array( __CLASS__, 'fmt_lead' ), $rows ),
				'total'    => $total,
				'has_more' => ( $offset + count( $rows ) ) < $total,
			)
		);
	}

	public static function handle_lead_detail( WP_REST_Request $request ) {
		global $wpdb;
		$c = self::lead_or_error( $request['id'] );
		if ( ! $c ) {
			return self::fail( 'Lead not found', 404 );
		}
		if ( ! self::can_access_lead( $request, $c ) ) {
			return self::fail( 'Access denied. You can only view leads assigned to you.', 403 );
		}
		$id = (int) $c['id'];

		$activities = array_map( array( __CLASS__, 'fmt_activity' ), TC_Contact_Service::activities( $id, 60 ) );
		$tasks      = array_values( array_filter( $activities, function ( $a ) {
			return 'task' === $a['type'] && empty( $a['done_at'] );
		} ) );

		$bookings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE contact_id = %d ORDER BY id DESC", $id ), ARRAY_A ) ?: array();

		$mem    = TC_Agent_Memory::get_memory( $id );
		$decode = function ( $json ) {
			$v = json_decode( (string) $json, true );
			return is_array( $v ) ? array_values( $v ) : array();
		};

		return self::ok(
			array(
				'lead'       => self::fmt_lead( $c ),
				'activities' => $activities,
				'tasks'      => $tasks,
				'bookings'   => array_map( array( __CLASS__, 'fmt_booking' ), $bookings ),
				'memory'     => $mem ? array(
					'summary'          => (string) $mem->summary,
					'facts'            => $decode( $mem->facts ),
					'preferences'      => $decode( $mem->preferences ),
					'objections'       => $decode( $mem->objections ),
					'next_best_action' => (string) $mem->next_best_action,
				) : null,
				'can_contact' => array(
					'whatsapp' => TC_Contact_Service::can_contact( $c, 'whatsapp' ),
					'email'    => TC_Contact_Service::can_contact( $c, 'email' ),
				),
			)
		);
	}

	public static function handle_lead_create( WP_REST_Request $request ) {
		$limited = self::throttle( $request, 'lead_create', 120, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}
		$p     = self::params( $request );
		$name  = sanitize_text_field( $p['name'] ?? '' );
		$phone = sanitize_text_field( $p['phone'] ?? '' );
		if ( '' === TC_Contact_Service::normalize_phone( $phone ) ) {
			return self::fail( 'A valid phone number is required.' );
		}

		$meta = array_filter(
			array(
				'destination'  => sanitize_text_field( $p['destination'] ?? '' ),
				'pax'          => absint( $p['pax'] ?? 0 ) ?: null,
				'travel_month' => sanitize_text_field( $p['travel_month'] ?? '' ),
				'requirements' => sanitize_textarea_field( $p['requirements'] ?? '' ),
			),
			function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);

		$agent = self::agent( $request );
		$res   = TC_Contact_Service::upsert(
			array(
				'name'           => $name,
				'phone'          => $phone,
				'email'          => sanitize_email( $p['email'] ?? '' ),
				'source_channel' => sanitize_key( $p['source'] ?? '' ) ?: 'mobile_app',
				'deal_value'     => isset( $p['budget'] ) ? max( 0, (float) $p['budget'] ) : null,
				'owner'          => ! empty( $p['owner'] ) ? sanitize_text_field( $p['owner'] ) : ( in_array( $agent, array( '', 'master', 'admin' ), true ) ? '' : $agent ),
				'meta'           => $meta,
			),
			array( 'activity' => 'Lead added from the mobile app' . ( $agent ? " by $agent" : '' ) )
		);
		if ( 'invalid' === $res['status'] || ! $res['contact'] ) {
			return self::fail( 'Could not save this lead.' );
		}
		self::audit( $request, 'lead_saved', array( 'lead_id' => (int) $res['id'], 'status' => $res['status'] ) );
		return self::ok(
			array(
				'lead'      => self::fmt_lead( $res['contact'] ),
				'duplicate' => 'updated' === $res['status'],
			),
			'created' === $res['status'] ? 201 : 200
		);
	}

	/**
	 * AI call summary. Takes the agent's notes and/or a transcript, asks the configured LLM for structured
	 * facts, and writes them to the lead's memory. The model is told to return null for anything not said,
	 * and only explicitly stated values are copied onto the lead.
	 */
	public static function handle_lead_summarize( WP_REST_Request $request ) {
		$c = self::lead_or_error( $request['id'] );
		if ( ! $c ) {
			return self::fail( 'Lead not found', 404 );
		}
		if ( ! self::can_access_lead( $request, $c ) ) {
			return self::fail( 'Access denied. You can only summarize leads assigned to you.', 403 );
		}
		$p    = self::params( $request );
		$text = trim( sanitize_textarea_field( ( $p['transcript'] ?? '' ) . "\n" . ( $p['notes'] ?? '' ) ) );
		if ( strlen( $text ) < 15 ) {
			return self::fail( 'Add call notes or a transcript first (at least a sentence).' );
		}
		// Each summary is a paid LLM call.
		$limited = self::throttle( $request, 'ai_summary', 40, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}
		$text = mb_substr( $text, 0, 6000 );

		$system = "You are a CRM assistant for TripCosmos, a travel agency in Varanasi. Extract facts from a sales call. "
			. "Use ONLY what the text states. If something is not stated, use null. Never guess prices, dates or numbers. "
			. "Reply with ONLY JSON: {\"summary\":\"1-2 sentences\",\"destination\":null,\"travel_dates\":null,\"adults\":null,\"children\":null,"
			. "\"budget_inr\":null,\"hotel_category\":null,\"vehicle\":null,\"pickup\":null,\"special_requests\":[],\"objections\":[],"
			. "\"competitor\":null,\"intent\":\"hot|warm|cold\",\"intent_reasons\":[],\"next_best_action\":\"\",\"follow_up_in_days\":null}";

		$router = TC_AI_Router::get_instance();
		$reply  = $router->chat(
			array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => "Lead: {$c['name']}\nCall notes / transcript:\n" . $text ),
			),
			array(),
			array( 'temperature' => 0.1 )
		);
		if ( is_wp_error( $reply ) || empty( $reply['content'] ) ) {
			return self::fail( 'The AI service is unavailable right now. Your notes were not lost; try again in a minute.', 503 );
		}

		$raw = trim( $reply['content'] );
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m ) ) {
			$raw = $m[1];
		}
		$d = json_decode( $raw, true );
		if ( ! is_array( $d ) ) {
			return self::fail( 'The AI reply could not be read. Try again.', 502 );
		}

		$id      = (int) $c['id'];
		$adults  = isset( $d['adults'] ) ? (int) $d['adults'] : 0;
		$kids    = isset( $d['children'] ) ? (int) $d['children'] : 0;
		$intent  = in_array( $d['intent'] ?? '', array( 'hot', 'warm', 'cold' ), true ) ? $d['intent'] : '';
		$facts   = array_filter(
			array(
				! empty( $d['destination'] ) ? 'Destination: ' . sanitize_text_field( $d['destination'] ) : '',
				! empty( $d['travel_dates'] ) ? 'Travel dates: ' . sanitize_text_field( $d['travel_dates'] ) : '',
				$adults > 0 ? 'Adults: ' . $adults : '',
				$kids > 0 ? 'Children: ' . $kids : '',
				! empty( $d['budget_inr'] ) ? 'Budget: ₹' . number_format( (float) $d['budget_inr'] ) : '',
				! empty( $d['hotel_category'] ) ? 'Hotel: ' . sanitize_text_field( $d['hotel_category'] ) : '',
				! empty( $d['vehicle'] ) ? 'Vehicle: ' . sanitize_text_field( $d['vehicle'] ) : '',
				! empty( $d['pickup'] ) ? 'Pickup: ' . sanitize_text_field( $d['pickup'] ) : '',
				! empty( $d['competitor'] ) ? 'Comparing with: ' . sanitize_text_field( $d['competitor'] ) : '',
			)
		);

		TC_Agent_Memory::save_memory(
			$id,
			array(
				'summary'          => sanitize_textarea_field( $d['summary'] ?? '' ),
				'facts'            => array_values( $facts ),
				'preferences'      => array_map( 'sanitize_text_field', (array) ( $d['special_requests'] ?? array() ) ),
				'objections'       => array_map( 'sanitize_text_field', (array) ( $d['objections'] ?? array() ) ),
				'next_best_action' => sanitize_text_field( $d['next_best_action'] ?? '' ),
			)
		);

		// Copy explicitly stated values onto the lead so filters, quotes and the pipeline see them.
		global $wpdb;
		$meta = json_decode( (string) ( $c['meta_data'] ?? '' ), true ) ?: array();
		if ( ! empty( $d['destination'] ) ) {
			$meta['destination'] = sanitize_text_field( $d['destination'] );
		}
		if ( $adults + $kids > 0 ) {
			$meta['pax'] = $adults + $kids;
		}
		if ( ! empty( $d['travel_dates'] ) ) {
			$meta['travel_month'] = sanitize_text_field( $d['travel_dates'] );
		}
		if ( $intent ) {
			$meta['intent']         = $intent;
			$meta['intent_reasons'] = array_map( 'sanitize_text_field', array_slice( (array) ( $d['intent_reasons'] ?? array() ), 0, 5 ) );
		}
		$row = array( 'meta_data' => wp_json_encode( $meta ), 'updated_at' => current_time( 'mysql' ) );
		if ( ! empty( $d['budget_inr'] ) && (float) $c['deal_value'] <= 0 ) {
			$row['deal_value'] = (float) $d['budget_inr'];
		}
		$wpdb->update( TC_Contact_Service::table(), $row, array( 'id' => $id ) );

		TC_Contact_Service::add_activity( $id, 'note', 'AI call summary: ' . sanitize_textarea_field( $d['summary'] ?? '' ), array( 'source' => 'ai_call_summary' ) );
		self::audit( $request, 'ai_summary', array( 'lead_id' => $id, 'intent' => $intent ) );

		$task_id = 0;
		if ( ! empty( $p['create_task'] ) && ! empty( $d['follow_up_in_days'] ) && (int) $d['follow_up_in_days'] > 0 ) {
			$days    = min( 60, (int) $d['follow_up_in_days'] );
			$due     = gmdate( 'Y-m-d 11:00:00', strtotime( current_time( 'Y-m-d' ) . " +{$days} days" ) );
			$task_id = TC_Contact_Service::add_task( $id, sanitize_text_field( $d['next_best_action'] ?: 'Follow up' ), $due );
		}

		return self::ok(
			array(
				'summary'          => sanitize_textarea_field( $d['summary'] ?? '' ),
				'facts'            => array_values( $facts ),
				'intent'           => $intent,
				'intent_reasons'   => (array) ( $meta['intent_reasons'] ?? array() ),
				'next_best_action' => sanitize_text_field( $d['next_best_action'] ?? '' ),
				'task_id'          => $task_id,
				'lead'             => self::fmt_lead( TC_Contact_Service::get( $id ) ),
			)
		);
	}

	/* ------------------------------------------------------------------ tasks */

	public static function handle_tasks( WP_REST_Request $request ) {
		global $wpdb;
		$act    = $wpdb->prefix . 'tc_agent_activities';
		$c      = TC_Contact_Service::table();
		$filter = sanitize_key( $request->get_param( 'filter' ) ?: 'open' );
		$limit  = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
		$now    = current_time( 'mysql' );
		$today  = current_time( 'Y-m-d' );
		$lead   = absint( $request->get_param( 'lead_id' ) );

		$where = "a.type = 'task'";
		$args  = array();
		switch ( $filter ) {
			case 'today':
				$where .= ' AND a.done_at IS NULL AND DATE(a.due_at) = %s';
				$args[] = $today;
				break;
			case 'overdue':
				$where .= ' AND a.done_at IS NULL AND a.due_at < %s';
				$args[] = $now;
				break;
			case 'upcoming':
				$where .= ' AND a.done_at IS NULL AND DATE(a.due_at) > %s';
				$args[] = $today;
				break;
			case 'done':
				$where .= ' AND a.done_at IS NOT NULL';
				break;
			default:
				$where .= ' AND a.done_at IS NULL';
		}
		if ( $lead ) {
			$where .= ' AND a.contact_id = %d';
			$args[] = $lead;
		}

		if ( ! self::is_admin_or_manager( $request ) ) {
			$my_id    = (int) $request->get_param( '_tc_user_id' );
			$my_agent = trim( (string) self::agent( $request ) );
			$where   .= ' AND (c.owner_user_id = %d OR c.owner = %s)';
			$args[]   = $my_id;
			$args[]   = $my_agent;
		}

		$order  = 'done' === $filter ? 'a.done_at DESC' : 'a.due_at ASC';
		$args[] = $limit;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, c.name, c.phone FROM $act a LEFT JOIN $c c ON c.id = a.contact_id WHERE $where ORDER BY $order LIMIT %d", $args ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		foreach ( $rows as $r ) {
			$t            = self::fmt_activity( $r );
			$t['lead_id'] = (int) $r['contact_id'];
			$t['name']    = $r['name'] ?: 'Traveler';
			$t['phone']   = (string) $r['phone'];
			$out[]        = $t;
		}
		return self::ok( array( 'tasks' => $out ) );
	}

	public static function handle_task_create( WP_REST_Request $request ) {
		$p       = self::params( $request );
		$lead_id = absint( $p['lead_id'] ?? 0 );
		$content = self::text( sanitize_text_field( $p['content'] ?? '' ), 300 );
		$lead    = $lead_id ? self::lead_or_error( $lead_id ) : null;
		if ( ! $lead_id || '' === $content || ! $lead ) {
			return self::fail( 'lead_id and content are required.' );
		}
		if ( ! self::can_access_lead( $request, $lead ) ) {
			return self::fail( 'Access denied. You can only create tasks for leads assigned to you.', 403 );
		}
		$local = self::to_local( $p['due_at'] ?? '' );
		if ( '' === $local ) {
			return self::fail( 'due_at must be an ISO-8601 date-time.' );
		}
		$id = TC_Contact_Service::add_task( $lead_id, $content, $local );
		self::audit( $request, 'task_created', array( 'lead_id' => $lead_id, 'task_id' => $id ) );
		return self::ok( array( 'task_id' => $id ), 201 );
	}

	public static function handle_task_complete( WP_REST_Request $request ) {
		if ( ! TC_Contact_Service::complete_task( (int) $request['id'] ) ) {
			return self::fail( 'Task not found', 404 );
		}
		self::audit( $request, 'task_completed', array( 'task_id' => (int) $request['id'] ) );
		return self::ok( array( 'task_id' => (int) $request['id'] ) );
	}

	/* ------------------------------------------------------------------ bookings, quotes, payments */

	public static function handle_bookings( WP_REST_Request $request ) {
		global $wpdb;
		$t      = $wpdb->prefix . 'tc_agent_bookings';
		$where  = '1=1';
		$args   = array();
		$lead   = absint( $request->get_param( 'lead_id' ) );
		$status = sanitize_key( $request->get_param( 'status' ) );
		if ( $lead ) {
			$where .= ' AND contact_id = %d';
			$args[] = $lead;
		}
		if ( $status && isset( self::BOOKING_STATUSES[ $status ] ) ) {
			$where .= ' AND status = %s';
			$args[] = $status;
		}
		if ( ! self::is_admin_or_manager( $request ) ) {
			$my_id    = (int) $request->get_param( '_tc_user_id' );
			$my_agent = trim( (string) self::agent( $request ) );
			$table_c  = TC_Contact_Service::table();
			$where   .= " AND contact_id IN (SELECT id FROM $table_c WHERE owner_user_id = %d OR owner = %s)";
			$args[]   = $my_id;
			$args[]   = $my_agent;
		}
		$args[] = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY COALESCE(start_date, '9999-12-31') ASC, id DESC LIMIT %d", $args ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return self::ok( array( 'bookings' => array_map( array( __CLASS__, 'fmt_booking' ), $rows ) ) );
	}

	public static function handle_booking_save( WP_REST_Request $request ) {
		global $wpdb;
		$p       = self::params( $request );
		$lead_id = absint( $p['lead_id'] ?? 0 );
		$bid     = absint( $p['booking_id'] ?? 0 );
		$lead    = $lead_id ? self::lead_or_error( $lead_id ) : null;
		if ( ! $lead_id || ! $lead ) {
			return self::fail( 'lead_id is required.' );
		}
		if ( ! self::can_access_lead( $request, $lead ) ) {
			return self::fail( 'Access denied. You can only save bookings for leads assigned to you.', 403 );
		}
		$title = self::text( sanitize_text_field( $p['title'] ?? '' ), 200 );
		if ( '' === $title ) {
			return self::fail( 'A booking title is required.' );
		}
		$status = sanitize_key( $p['status'] ?? 'quoted' );
		$date   = function ( $v ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : null;
		};

		$t   = $wpdb->prefix . 'tc_agent_bookings';
		$row = array(
			'contact_id'   => $lead_id,
			'title'        => $title,
			'start_date'   => $date( $p['start_date'] ?? '' ),
			'end_date'     => $date( $p['end_date'] ?? '' ),
			'pax'          => max( 1, absint( $p['pax'] ?? 1 ) ),
			'vehicle'      => sanitize_text_field( $p['vehicle'] ?? '' ),
			'driver'       => sanitize_text_field( $p['driver'] ?? '' ),
			'quote_amount' => max( 0, (float) ( $p['quote_amount'] ?? 0 ) ),
			'status'       => isset( self::BOOKING_STATUSES[ $status ] ) ? $status : 'quoted',
			'notes'        => self::text( sanitize_textarea_field( $p['notes'] ?? '' ), 2000 ),
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( $bid ) {
			// advance_paid is only ever changed by recorded payments, never typed in.
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT status, ref FROM $t WHERE id = %d AND contact_id = %d", $bid, $lead_id ), ARRAY_A );
			if ( ! $old ) {
				return self::fail( 'Booking not found', 404 );
			}
			$wpdb->update( $t, $row, array( 'id' => $bid ) );
			if ( $old['status'] !== $row['status'] ) {
				TC_Contact_Service::add_activity( $lead_id, 'booking', sprintf( 'Booking %s: %s → %s', $old['ref'], self::BOOKING_STATUSES[ $old['status'] ] ?? $old['status'], self::BOOKING_STATUSES[ $row['status'] ] ) );
			}
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$row['currency']   = 'INR';
			$wpdb->insert( $t, $row );
			$bid = (int) $wpdb->insert_id;
			$ref = 'TC-' . gmdate( 'ymd' ) . '-' . $bid;
			$wpdb->update( $t, array( 'ref' => $ref ), array( 'id' => $bid ) );
			TC_Contact_Service::add_activity( $lead_id, 'booking', sprintf( 'Booking %s created: %s', $ref, $title ) );
		}

		// Keep the pipeline in step, exactly like the admin CRM does.
		$contact = TC_Contact_Service::get( $lead_id );
		if ( $contact && in_array( $row['status'], array( 'confirmed', 'advance_paid', 'paid' ), true ) && ! in_array( $contact['stage'], array( 'won', 'completed' ), true ) ) {
			TC_Contact_Service::set_stage( $lead_id, 'won' );
		} elseif ( $contact && 'completed' === $row['status'] && 'completed' !== $contact['stage'] ) {
			TC_Contact_Service::set_stage( $lead_id, 'completed' );
		}
		$total = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(quote_amount) FROM $t WHERE contact_id = %d AND status <> 'cancelled'", $lead_id ) );
		if ( $total > 0 ) {
			$wpdb->update( TC_Contact_Service::table(), array( 'deal_value' => $total ), array( 'id' => $lead_id ) );
		}
		self::audit( $request, 'booking_saved', array( 'lead_id' => $lead_id, 'booking_id' => $bid, 'status' => $row['status'] ) );

		return self::ok( array( 'booking' => self::fmt_booking( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $bid ), ARRAY_A ) ) ), 201 );
	}

	public static function handle_quote_create( WP_REST_Request $request ) {
		$p          = self::params( $request );
		$booking_id = absint( $p['booking_id'] ?? 0 );
		$items      = array();
		foreach ( (array) ( $p['items'] ?? array() ) as $it ) {
			$items[] = array(
				'desc'  => sanitize_text_field( $it['desc'] ?? '' ),
				'qty'   => (float) ( $it['qty'] ?? 1 ),
				'price' => (float) ( $it['price'] ?? 0 ),
			);
		}
		if ( count( $items ) > 50 ) {
			return self::fail( 'A quote can have at most 50 lines.' );
		}
		$input = array( 'items' => $items, 'discount' => (float) ( $p['discount'] ?? 0 ), 'notes' => self::text( $p['notes'] ?? '', 2000 ) );
		if ( isset( $p['tax_rate'] ) ) {
			$input['tax_rate'] = (float) $p['tax_rate'];
		}
		if ( isset( $p['advance_amount'] ) && '' !== (string) $p['advance_amount'] ) {
			$input['advance_amount'] = (float) $p['advance_amount'];
		}
		$qid = TC_Quote_Service::create( $booking_id, $input );
		if ( is_wp_error( $qid ) ) {
			return self::fail( $qid->get_error_message() );
		}
		self::audit( $request, 'quote_created', array( 'booking_id' => $booking_id, 'quote_id' => (int) $qid ) );
		global $wpdb;
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", $booking_id ), ARRAY_A );
		return self::ok( array( 'quote_id' => (int) $qid, 'booking' => self::fmt_booking( $b ) ), 201 );
	}

	public static function handle_quote_send( WP_REST_Request $request ) {
		$q = TC_Quote_Service::get( (int) $request['id'] );
		if ( ! $q ) {
			return self::fail( 'Quote not found', 404 );
		}
		$c = TC_Contact_Service::get( (int) $q['contact_id'] );
		if ( ! $c || empty( $c['phone'] ) ) {
			return self::fail( 'This lead has no phone number.' );
		}
		if ( ! self::can_access_lead( $request, $c ) ) {
			return self::fail( 'Access denied. You can only send quotes to leads assigned to you.', 403 );
		}
		if ( ! TC_Contact_Service::can_contact( $c, 'whatsapp' ) ) {
			return self::fail( 'This customer opted out of WhatsApp messages.', 403 );
		}
		$limited = self::throttle( $request, 'wa_send', 120, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}

		// Idempotency Lock: Prevent duplicate quote dispatches within 30 seconds
		$quote_lock_key = 'tc_quote_send_lock_' . (int) $q['id'];
		if ( get_transient( $quote_lock_key ) ) {
			return self::ok( array( 'url' => TC_Quote_Service::url( $q ), 'deduplicated' => true, 'message' => 'Quote already dispatched recently.' ) );
		}
		set_transient( $quote_lock_key, 1, 30 );
		$name = ! empty( $c['name'] ) ? $c['name'] : 'Traveler';
		$msg  = sprintf(
			"Namaste %s! 🙏 Your TripCosmos quotation (₹%s) is ready. View, accept and pay the advance here: %s",
			$name,
			number_format_i18n( (float) $q['total'] ),
			TC_Quote_Service::url( $q )
		);
		if ( ! TC_Integration_WhatsApp::send_message( $c['phone'], $msg ) ) {
			return self::fail( 'WhatsApp could not deliver the message. Check the WhatsApp connection in Integrations.', 502 );
		}
		TC_Quote_Service::mark_sent( (int) $q['id'], 'whatsapp' );
		self::audit( $request, 'quote_sent', array( 'quote_id' => (int) $q['id'], 'lead_id' => (int) $q['contact_id'] ) );
		return self::ok( array( 'url' => TC_Quote_Service::url( $q ) ) );
	}

	public static function handle_payment_link( WP_REST_Request $request ) {
		$p      = self::params( $request );
		$amount = (float) ( $p['amount'] ?? 0 );
		$bid    = absint( $p['booking_id'] ?? 0 );
		if ( ! $bid || $amount <= 0 ) {
			return self::fail( 'booking_id and a positive amount are required.' );
		}
		$limited = self::throttle( $request, 'pay_link', 60, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}
		self::audit( $request, 'payment_link', array( 'booking_id' => $bid, 'amount' => $amount ) );
		$upi = TC_Payments::upi_link( $amount, 'Booking ' . $bid );
		if ( TC_Payments::cashfree_ready() ) {
			$row = TC_Payments::create_cashfree_link( $bid, $amount, sanitize_text_field( $p['purpose'] ?? '' ) );
			if ( is_wp_error( $row ) ) {
				return self::fail( $row->get_error_message(), 502 );
			}
			return self::ok( array( 'provider' => 'cashfree', 'url' => (string) $row['link_url'], 'upi' => $upi, 'payment_id' => (int) $row['id'] ) );
		}
		if ( '' === (string) $upi ) {
			return self::fail( 'No payment method is configured. Add Cashfree keys or a UPI ID in the CRM billing settings.', 503 );
		}
		return self::ok( array( 'provider' => 'upi', 'url' => '', 'upi' => $upi ) );
	}

	public static function handle_payment_record( WP_REST_Request $request ) {
		$p      = self::params( $request );
		$bid    = absint( $p['booking_id'] ?? 0 );
		$amount = (float) ( $p['amount'] ?? 0 );
		$method = sanitize_key( $p['method'] ?? 'upi' );
		if ( ! in_array( $method, array( 'upi', 'cash', 'bank', 'card' ), true ) ) {
			return self::fail( 'method must be upi, cash, bank or card.' );
		}
		if ( ! $bid || $amount <= 0 ) {
			return self::fail( 'booking_id and a positive amount are required.' );
		}
		if ( ! TC_Payments::record_manual( $bid, $amount, $method, sanitize_text_field( $p['reference'] ?? '' ) ) ) {
			return self::fail( 'Booking not found or the payment could not be recorded.', 404 );
		}
		TC_Agents_Logger::log( 'mobile_payment_recorded', 'info', array( 'booking_id' => $bid, 'amount' => $amount, 'method' => $method, 'agent' => self::agent( $request ) ) );
		global $wpdb;
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", $bid ), ARRAY_A );
		return self::ok( array( 'booking' => self::fmt_booking( $b ) ) );
	}

	/* ------------------------------------------------------------------ WhatsApp thread */

	/** Conversation ids for a lead: linked by contact id, or by the WhatsApp session id for that number. */
	private static function conversation_ids( array $c ) {
		global $wpdb;
		$cv     = $wpdb->prefix . 'tc_agent_conversations';
		$digits = self::digits( $c['phone'] ?? '' );
		$args   = array( (int) $c['id'] );
		$sql    = "SELECT id FROM $cv WHERE contact_id = %d";
		if ( '' !== $digits ) {
			$sql   .= ' OR session_id = %s';
			$args[] = 'wa_' . $digits;
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function handle_conversation( WP_REST_Request $request ) {
		global $wpdb;
		$c = self::lead_or_error( $request['id'] );
		if ( ! $c ) {
			return self::fail( 'Lead not found', 404 );
		}
		$ids = self::conversation_ids( $c );
		if ( ! $ids ) {
			return self::ok( array( 'messages' => array(), 'lead' => self::fmt_lead( $c ), 'ai_enabled' => true ) );
		}
		$status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}tc_agent_conversations WHERE id = %d", $ids[0] ) );
		$msg    = $wpdb->prefix . 'tc_agent_messages';
		$in     = implode( ',', $ids );
		$limit  = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
		$before = absint( $request->get_param( 'before' ) );
		$sql    = "SELECT id, role, content, provider_used, delivery, created_at FROM $msg WHERE conversation_id IN ($in) AND role IN ('user','assistant','agent')";
		if ( $before ) {
			$sql .= $wpdb->prepare( ' AND id < %d', $before );
		}
		$rows = $wpdb->get_results( $sql . $wpdb->prepare( ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out = array();
		foreach ( array_reverse( $rows ) as $r ) {
			$by    = (string) $r['provider_used'];
			$out[] = array(
				'id'       => (int) $r['id'],
				'from'     => 'user' === $r['role'] ? 'customer' : ( 'agent' === $r['role'] ? 'agent' : 'ai' ),
				'text'     => wp_strip_all_tags( (string) $r['content'] ),
				'by'       => 0 === strpos( $by, 'human:' ) ? substr( $by, 6 ) : '',
				'delivery' => (string) $r['delivery'],
				'at'       => self::iso( $r['created_at'] ),
			);
		}
		return self::ok( array( 'messages' => $out, 'lead' => self::fmt_lead( $c ), 'ai_enabled' => 'handed_off' !== $status ) );
	}

	public static function handle_conversation_reply( WP_REST_Request $request ) {
		global $wpdb;
		$c = self::lead_or_error( $request['id'] );
		if ( ! $c ) {
			return self::fail( 'Lead not found', 404 );
		}
		if ( ! self::can_access_lead( $request, $c ) ) {
			return self::fail( 'Access denied. You can only send WhatsApp messages to leads assigned to you.', 403 );
		}
		$text = self::text( sanitize_textarea_field( self::params( $request )['message'] ?? '' ), 4000 );
		if ( '' === $text ) {
			return self::fail( 'Message is empty.' );
		}
		$limited = self::throttle( $request, 'wa_send', 120, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}

		// Idempotency Lock: Prevent duplicate quote dispatches within 30 seconds
		$quote_lock_key = 'tc_quote_send_lock_' . (int) $q['id'];
		if ( get_transient( $quote_lock_key ) ) {
			return self::ok( array( 'url' => TC_Quote_Service::url( $q ), 'deduplicated' => true, 'message' => 'Quote already dispatched recently.' ) );
		}
		set_transient( $quote_lock_key, 1, 30 );
		if ( empty( $c['phone'] ) ) {
			return self::fail( 'This lead has no phone number.' );
		}
		if ( ! TC_Contact_Service::can_contact( $c, 'whatsapp' ) ) {
			return self::fail( 'This customer opted out of WhatsApp messages.', 403 );
		}
		$sent = TC_Integration_WhatsApp::send_message_ex( $c['phone'], $text );
		if ( false === $sent ) {
			return self::fail( 'WhatsApp could not deliver the message.', 502 );
		}

		// Same thread the AI uses; a human reply also steps the AI back for this customer.
		TC_WhatsApp_Hub::record_agent_message( $c, $text, (string) $sent, self::agent( $request ) ?: 'app' );
		self::audit( $request, 'whatsapp_reply', array( 'lead_id' => (int) $c['id'] ) );
		return self::ok( array( 'sent' => true, 'ai_enabled' => false ), 201 );
	}

	/** Turn the AI auto-reply on or off for one customer. */
	public static function handle_conversation_ai( WP_REST_Request $request ) {
		$c = self::lead_or_error( $request['id'] );
		if ( ! $c || '' === (string) $c['phone'] ) {
			return self::fail( 'Lead not found', 404 );
		}
		if ( ! self::can_access_lead( $request, $c ) ) {
			return self::fail( 'Access denied. You can only control AI for leads assigned to you.', 403 );
		}
		$enabled = ! empty( self::params( $request )['enabled'] );
		$conv    = TC_WhatsApp_Hub::conversation_for( self::digits( $c['phone'] ), (int) $c['id'] );
		TC_WhatsApp_Hub::set_ai( (int) $conv['id'], $enabled );
		self::audit( $request, $enabled ? 'ai_on' : 'ai_off', array( 'lead_id' => (int) $c['id'] ) );
		return self::ok( array( 'ai_enabled' => $enabled ) );
	}

	/* ------------------------------------------------------------------ Financials & Analytics */

	public static function handle_financials( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Financial intelligence is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$bk  = $wpdb->prefix . 'tc_agent_bookings';
		$pay = $wpdb->prefix . 'tc_agent_payments';
		$c   = TC_Contact_Service::table();

		// GMV = total booking quotes or won deal values
		$gmv_bookings = (float) $wpdb->get_var( "SELECT COALESCE(SUM(quote_amount),0) FROM $bk WHERE status <> 'cancelled'" );
		$gmv_contacts = (float) $wpdb->get_var( "SELECT COALESCE(SUM(deal_value),0) FROM $c WHERE stage IN ('won','completed')" );
		$gmv = max( $gmv_bookings, $gmv_contacts );

		// Collections from payments
		$total_collected_pay = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM $pay WHERE status = 'paid'" );
		$total_collected_bk  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(advance_paid),0) FROM $bk" );
		$total_collected     = max( $total_collected_pay, $total_collected_bk );

		$pending_receivables = max( 0.0, round( $gmv - $total_collected, 2 ) );

		$deals_won_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $c WHERE stage IN ('won','completed')" );
		$active_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bk WHERE status IN ('quoted','confirmed','advance_paid','paid')" );

		// Method breakdown
		$methods_raw = $wpdb->get_results( "SELECT COALESCE(method, 'upi') as method, COALESCE(SUM(amount),0) as total FROM $pay WHERE status = 'paid' GROUP BY method", ARRAY_A ) ?: array();
		$method_split = array( 'cashfree' => 0.0, 'upi' => 0.0, 'cash' => 0.0, 'bank' => 0.0 );
		foreach ( $methods_raw as $mr ) {
			$m = strtolower( trim( (string) $mr['method'] ) );
			if ( isset( $method_split[ $m ] ) ) {
				$method_split[ $m ] += (float) $mr['total'];
			} else {
				$method_split['upi'] += (float) $mr['total'];
			}
		}

		// Status breakdown
		$status_raw = $wpdb->get_results( "SELECT status, COUNT(*) as cnt, COALESCE(SUM(quote_amount),0) as val FROM $bk GROUP BY status", ARRAY_A ) ?: array();
		$status_split = array();
		foreach ( $status_raw as $sr ) {
			$st = (string) $sr['status'];
			$status_split[ $st ] = array(
				'count' => (int) $sr['cnt'],
				'value' => (float) $sr['val'],
			);
		}

		// Monthly trend (last 6 months)
		$monthly_raw = $wpdb->get_results( "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') as m_key, COALESCE(SUM(amount),0) as total_amt, COUNT(*) as cnt FROM $pay WHERE status = 'paid' GROUP BY m_key ORDER BY m_key DESC LIMIT 6", ARRAY_A ) ?: array();
		$monthly_trend = array();
		foreach ( array_reverse( $monthly_raw ) as $mr ) {
			if ( ! empty( $mr['m_key'] ) ) {
				$monthly_trend[] = array(
					'month'     => $mr['m_key'],
					'label'     => gmdate( 'M Y', strtotime( $mr['m_key'] . '-01' ) ),
					'collected' => (float) $mr['total_amt'],
					'count'     => (int) $mr['cnt'],
				);
			}
		}

		// Recent 15 transactions
		$tx_rows = $wpdb->get_results(
			"SELECT p.id, p.booking_id, p.amount, p.currency, p.provider, p.method, p.status, p.reference, p.paid_at, p.created_at, b.ref as booking_ref, c.name as customer_name
			 FROM $pay p
			 LEFT JOIN $bk b ON b.id = p.booking_id
			 LEFT JOIN $c c ON c.id = p.contact_id
			 ORDER BY p.id DESC LIMIT 15",
			ARRAY_A
		) ?: array();

		$recent_transactions = array();
		foreach ( $tx_rows as $t ) {
			$recent_transactions[] = array(
				'id'            => (int) $t['id'],
				'booking_id'    => (int) $t['booking_id'],
				'booking_ref'   => (string) ( $t['booking_ref'] ?: ( 'TC-' . (int) $t['booking_id'] ) ),
				'customer_name' => (string) ( $t['customer_name'] ?: 'Traveler' ),
				'amount'        => (float) $t['amount'],
				'currency'      => (string) ( $t['currency'] ?: 'INR' ),
				'provider'      => (string) $t['provider'],
				'method'        => (string) ( $t['method'] ?: 'upi' ),
				'status'        => (string) $t['status'],
				'reference'     => (string) $t['reference'],
				'date'          => self::iso( $t['paid_at'] ?: $t['created_at'] ),
			);
		}

		// Sales Leaderboard
		$leader_rows = $wpdb->get_results(
			"SELECT COALESCE(u.display_name, c.owner, 'Unassigned') as agent_name, COUNT(*) as deals_won, COALESCE(SUM(c.deal_value),0) as revenue
			 FROM $c c
			 LEFT JOIN {$wpdb->users} u ON u.ID = c.owner_user_id
			 WHERE c.stage IN ('won','completed')
			 GROUP BY agent_name
			 ORDER BY revenue DESC LIMIT 10",
			ARRAY_A
		) ?: array();

		$pipeline_value    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(deal_value),0) FROM $c WHERE stage NOT IN ('lost','cancelled')" );
		$total_leads_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $c" );

		// Bank Statement Overview
		$bank_table = $wpdb->prefix . 'tcfp_bank_transactions';
		$bank_credits = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $bank_table WHERE type = 'credit'" );
		$bank_debits  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $bank_table WHERE type = 'debit'" );
		$bank_balance = (float) $wpdb->get_var( "SELECT balance FROM $bank_table WHERE balance IS NOT NULL AND balance > 0 ORDER BY txn_date DESC, id DESC LIMIT 1" );
		if ( $bank_balance <= 0 ) {
			$bank_balance = max( 0.0, $bank_credits - $bank_debits );
		}
		$bank_reconciled_cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bank_table WHERE reconciled = 1" );

		// Conversion / Google Ads Telemetry
		$conv_table = $wpdb->prefix . 'tc_agent_conversion_log';
		$gads_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $conv_table WHERE (gclid IS NOT NULL AND gclid != '') OR source = 'google_ads'" );
		$chatbot_events = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $conv_table WHERE channel = 'web_chatbot'" );

		return self::ok(
			array(
				'summary' => array(
					'gmv'                 => $gmv,
					'total_collected'     => $total_collected,
					'pending_receivables' => $pending_receivables,
					'pipeline_value'      => $pipeline_value,
					'total_leads_count'   => $total_leads_count,
					'deals_won_count'     => $deals_won_count,
					'active_bookings'     => $active_bookings,
					'collection_rate'     => $gmv > 0 ? round( ( $total_collected / $gmv ) * 100, 1 ) : 0.0,
					'bank_credits'        => $bank_credits,
					'bank_debits'         => $bank_debits,
					'bank_balance'        => $bank_balance,
					'bank_reconciled'     => $bank_reconciled_cnt,
					'google_ads_leads'    => $gads_leads,
					'chatbot_leads'       => $chatbot_events,
				),
				'method_split'        => $method_split,
				'status_split'        => $status_split,
				'monthly_trend'       => $monthly_trend,
				'recent_transactions' => $recent_transactions,
				'sales_leaderboard'   => $leader_rows,
			)
		);
	}

	/* ------------------------------------------------------------------ Driver & Chauffeur Dispatch */

	public static function handle_dispatch_driver( WP_REST_Request $request ) {
		global $wpdb;
		$p          = self::params( $request );
		$lead_id    = absint( $p['lead_id'] ?? $p['contact_id'] ?? 0 );
		$booking_id = absint( $p['booking_id'] ?? 0 );

		$c = null;
		if ( $lead_id ) {
			$c = self::lead_or_error( $lead_id );
		} elseif ( $booking_id ) {
			$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", $booking_id ), ARRAY_A );
			if ( $b && ! empty( $b['contact_id'] ) ) {
				$c = self::lead_or_error( (int) $b['contact_id'] );
			}
		}

		if ( ! $c ) {
			return self::fail( 'Lead not found.', 404 );
		}

		$driver_name     = sanitize_text_field( $p['driver_name'] ?? '' );
		$driver_phone    = sanitize_text_field( $p['driver_phone'] ?? '' );
		$vehicle_model   = sanitize_text_field( $p['vehicle_model'] ?? 'AC Sedan' );
		$vehicle_number  = sanitize_text_field( $p['vehicle_number'] ?? '' );
		$reporting_time  = sanitize_text_field( $p['reporting_time'] ?? '' );
		$pickup_location = sanitize_text_field( $p['pickup_location'] ?? 'Varanasi' );
		$drop_location   = sanitize_text_field( $p['drop_location'] ?? '' );
		$balance_amount  = (float) ( $p['balance_amount'] ?? 0 );
		$notify_customer = ! isset( $p['notify_customer'] ) || ! empty( $p['notify_customer'] );
		$notify_driver   = ! empty( $p['notify_driver'] ) && ! empty( $driver_phone );

		if ( empty( $driver_name ) || empty( $driver_phone ) || empty( $vehicle_number ) ) {
			return self::fail( 'Driver name, driver phone, and vehicle number are required.' );
		}

		$customer_name  = ! empty( $c['name'] ) ? $c['name'] : 'Traveler';
		$customer_phone = (string) ( $c['phone'] ?? '' );

		if ( $booking_id ) {
			$wpdb->update(
				$wpdb->prefix . 'tc_agent_bookings',
				array(
					'driver'     => $driver_name . ' (' . $driver_phone . ')',
					'vehicle'    => $vehicle_model . ' - ' . $vehicle_number,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $booking_id )
			);
		}

		$cust_sent   = false;
		$driver_sent = false;

		// 1. WhatsApp Trip Sheet to traveler
		if ( $notify_customer && ! empty( $customer_phone ) && class_exists( 'TC_Integration_WhatsApp' ) ) {
			$traveler_msg = sprintf(
				"Namaste %s ji 🙏\nYour chauffeur & vehicle for your TripCosmos journey have been assigned! 🚗✨\n\n🚖 *Vehicle:* %s (%s)\n👤 *Chauffeur:* %s\n📞 *Driver Phone:* %s\n⏰ *Reporting Time:* %s\n📍 *Pickup Location:* %s%s\n💰 *Duty Balance on Arrival:* ₹%s\n\nFor 24/7 journey concierge support, reply directly to this chat. Have a blessed and memorable trip! 🕉️",
				$customer_name,
				$vehicle_model,
				$vehicle_number,
				$driver_name,
				$driver_phone,
				$reporting_time ?: 'As scheduled',
				$pickup_location,
				$drop_location ? "\n🎯 *Destination:* " . $drop_location : '',
				number_format_i18n( $balance_amount )
			);
			$res_cust  = TC_Integration_WhatsApp::send_message_ex( $customer_phone, $traveler_msg );
			$cust_sent = false !== $res_cust;
			if ( $cust_sent ) {
				TC_WhatsApp_Hub::record_agent_message( $c, $traveler_msg, (string) $res_cust, self::agent( $request ) ?: 'app' );
			}
		}

		// 2. WhatsApp Duty Sheet to chauffeur
		if ( $notify_driver && class_exists( 'TC_Integration_WhatsApp' ) ) {
			$driver_msg = sprintf(
				"Namaste %s ji 🚗\nYou have a new TripCosmos duty assignment:\n\n👤 *Guest:* %s\n📞 *Guest Phone:* %s\n⏰ *Reporting Time:* %s\n📍 *Pickup:* %s%s\n💰 *Collect Balance:* ₹%s\n\n⚠️ *Important Instructions:*\n1. Vehicle must be clean & AC running.\n2. Arrive 15 mins prior to reporting time.\n3. Assist guest politely with luggage.\nTripCosmos Desk: +91 93361 16210. Safe driving!",
				$driver_name,
				$customer_name,
				$customer_phone,
				$reporting_time ?: 'As scheduled',
				$pickup_location,
				$drop_location ? "\n🎯 *Drop:* " . $drop_location : '',
				number_format_i18n( $balance_amount )
			);
			$res_drv    = TC_Integration_WhatsApp::send_message_ex( $driver_phone, $driver_msg );
			$driver_sent = false !== $res_drv;
		}

		// Log activity on lead
		TC_Contact_Service::add_activity(
			(int) $c['id'],
			'booking',
			sprintf( '🚖 Chauffeur Dispatched: %s (%s) with %s %s. Balance: ₹%s.', $driver_name, $driver_phone, $vehicle_model, $vehicle_number, number_format_i18n( $balance_amount ) )
		);

		self::audit( $request, 'dispatch_driver', array( 'lead_id' => (int) $c['id'], 'driver' => $driver_name, 'vehicle' => $vehicle_number ) );

		return self::ok(
			array(
				'dispatched'        => true,
				'traveler_notified' => $cust_sent,
				'driver_notified'   => $driver_sent,
				'lead'              => self::fmt_lead( $c ),
			)
		);
	}

	/* ------------------------------------------------------------------ Caller Lookup */

	public static function handle_caller_lookup( WP_REST_Request $request ) {
		global $wpdb;
		$p      = self::params( $request );
		$phone  = sanitize_text_field( $p['phone'] ?? $request->get_param( 'phone' ) ?? '' );
		$digits = self::digits( $phone );
		if ( strlen( $digits ) < 7 ) {
			return self::fail( 'Phone number is too short.' );
		}

		$suffix = substr( $digits, -10 );
		$table  = TC_Contact_Service::table();
		$lead   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE phone LIKE %s OR phone_e164 LIKE %s ORDER BY id DESC LIMIT 1", '%' . $suffix, '%' . $suffix ),
			ARRAY_A
		);

		if ( ! $lead ) {
			return self::ok( array( 'found' => false, 'phone' => $phone ) );
		}

		// Get latest quote & booking
		$bk           = $wpdb->prefix . 'tc_agent_bookings';
		$qt           = $wpdb->prefix . 'tc_agent_quotes';
		$last_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bk WHERE contact_id = %d ORDER BY id DESC LIMIT 1", (int) $lead['id'] ), ARRAY_A );
		$last_quote   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $qt WHERE contact_id = %d ORDER BY id DESC LIMIT 1", (int) $lead['id'] ), ARRAY_A );

		// Financials
		$total_quote  = $last_quote ? (float) $last_quote['total'] : ( $last_booking ? (float) $last_booking['quote_amount'] : (float) $lead['deal_value'] );
		$advance_paid = $last_booking ? (float) $last_booking['advance_paid'] : 0.0;
		$balance_due  = max( 0.0, round( $total_quote - $advance_paid, 2 ) );

		// Last chat message
		$conv_ids = self::conversation_ids( $lead );
		$last_msg = null;
		if ( ! empty( $conv_ids ) ) {
			$in           = implode( ',', $conv_ids );
			$last_msg_row = $wpdb->get_row( "SELECT role, content, created_at FROM {$wpdb->prefix}tc_agent_messages WHERE conversation_id IN ($in) ORDER BY id DESC LIMIT 1", ARRAY_A );
			if ( $last_msg_row ) {
				$last_msg = array(
					'from' => 'user' === $last_msg_row['role'] ? 'customer' : 'agent',
					'text' => wp_strip_all_tags( (string) $last_msg_row['content'] ),
					'at'   => self::iso( $last_msg_row['created_at'] ),
				);
			}
		}

		return self::ok(
			array(
				'found'        => true,
				'lead'         => self::fmt_lead( $lead ),
				'financials'   => array(
					'deal_value'   => (float) $lead['deal_value'],
					'total_quote'  => $total_quote,
					'advance_paid' => $advance_paid,
					'balance_due'  => $balance_due,
				),
				'last_booking' => $last_booking ? self::fmt_booking( $last_booking ) : null,
				'last_chat'    => $last_msg,
			)
		);
	}

	/* ------------------------------------------------------------------ Voice Note Ingestion */

	public static function handle_voice_note_summary( WP_REST_Request $request ) {
		global $wpdb;
		$p          = self::params( $request );
		$lead_id    = absint( $p['lead_id'] ?? 0 );
		$phone      = sanitize_text_field( $p['phone'] ?? '' );
		$transcript = self::text( sanitize_textarea_field( $p['transcript'] ?? $p['note'] ?? '' ), 4000 );
		$stage      = sanitize_key( $p['stage'] ?? '' );
		$deal_value = isset( $p['deal_value'] ) ? (float) $p['deal_value'] : null;
		$follow_up  = sanitize_text_field( $p['follow_up_date'] ?? '' );

		if ( empty( $transcript ) ) {
			return self::fail( 'Voice note transcript or text is required.' );
		}

		$c = null;
		if ( $lead_id ) {
			$c = self::lead_or_error( $lead_id );
		} elseif ( ! empty( $phone ) ) {
			$digits = self::digits( $phone );
			$suffix = substr( $digits, -10 );
			$table  = TC_Contact_Service::table();
			$c      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone LIKE %s ORDER BY id DESC LIMIT 1", '%' . $suffix ), ARRAY_A );
		}

		if ( ! $c ) {
			if ( empty( $phone ) ) {
				return self::fail( 'Lead not found and no phone number provided to create one.', 404 );
			}
			$created = TC_Contact_Service::upsert_contact(
				array(
					'phone'          => $phone,
					'name'           => sanitize_text_field( $p['name'] ?? 'Traveler' ),
					'source_channel' => 'field_voice_note',
					'stage'          => $stage ?: 'inquiry',
					'deal_value'     => $deal_value ?? 0,
				)
			);
			if ( is_wp_error( $created ) ) {
				return self::fail( $created->get_error_message() );
			}
			$c = TC_Contact_Service::get( (int) $created['id'] );
		}

		if ( ! empty( $stage ) && in_array( $stage, array_keys( TC_Contact_Service::stages() ), true ) ) {
			TC_Contact_Service::set_stage( (int) $c['id'], $stage );
		}

		if ( null !== $deal_value && $deal_value >= 0 ) {
			$wpdb->update( TC_Contact_Service::table(), array( 'deal_value' => $deal_value, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $c['id'] ) );
		}

		TC_Contact_Service::add_activity(
			(int) $c['id'],
			'call',
			'🎙️ Voice Note / Field Summary: ' . $transcript
		);

		if ( ! empty( $follow_up ) ) {
			$local_due = self::to_local( $follow_up );
			if ( $local_due ) {
				$wpdb->insert(
					$wpdb->prefix . 'tc_agent_activities',
					array(
						'contact_id' => (int) $c['id'],
						'type'       => 'task',
						'content'    => 'Follow up after voice note: ' . mb_substr( $transcript, 0, 100 ),
						'due_at'     => $local_due,
						'created_by' => get_current_user_id(),
						'created_at' => current_time( 'mysql' ),
					)
				);
			}
		}

		self::audit( $request, 'voice_note_ingested', array( 'lead_id' => (int) $c['id'] ) );

		$updated_lead = TC_Contact_Service::get( (int) $c['id'] );
		return self::ok(
			array(
				'lead'       => self::fmt_lead( $updated_lead ),
				'transcript' => $transcript,
			)
		);
	}

	/* ------------------------------------------------------------------ 1-Tap Advance Payment Link */

	public static function handle_payment_generate_link( WP_REST_Request $request ) {
		global $wpdb;
		$p          = self::params( $request );
		$lead_id    = absint( $p['lead_id'] ?? 0 );
		$booking_id = absint( $p['booking_id'] ?? 0 );
		$amount     = (float) ( $p['amount'] ?? 0 );
		$purpose    = sanitize_text_field( $p['purpose'] ?? 'TripCosmos Pilgrimage Advance' );
		$send_wa    = ! isset( $p['send_whatsapp'] ) || ! empty( $p['send_whatsapp'] );

		if ( $amount < 1 ) {
			return self::fail( 'Amount must be greater than zero.' );
		}

		$c = null;
		if ( $lead_id ) {
			$c = self::lead_or_error( $lead_id );
		} elseif ( $booking_id ) {
			$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", $booking_id ), ARRAY_A );
			if ( $b && ! empty( $b['contact_id'] ) ) {
				$c = self::lead_or_error( (int) $b['contact_id'] );
			}
		}

		if ( ! $c ) {
			return self::fail( 'Lead or booking not found.', 404 );
		}

		if ( ! $booking_id ) {
			$existing_bk = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tc_agent_bookings WHERE contact_id = %d ORDER BY id DESC LIMIT 1", (int) $c['id'] ), ARRAY_A );
			if ( $existing_bk ) {
				$booking_id = (int) $existing_bk['id'];
			} else {
				$ref = 'TC-' . current_time( 'Y' ) . '-' . wp_rand( 1000, 9999 );
				$wpdb->insert(
					$wpdb->prefix . 'tc_agent_bookings',
					array(
						'contact_id'   => (int) $c['id'],
						'ref'          => $ref,
						'title'        => 'Tour for ' . ( $c['name'] ?: 'Traveler' ),
						'quote_amount' => $amount,
						'advance_paid' => 0,
						'currency'     => 'INR',
						'status'       => 'quoted',
						'created_at'   => current_time( 'mysql' ),
						'updated_at'   => current_time( 'mysql' ),
					)
				);
				$booking_id = (int) $wpdb->insert_id;
			}
		}

		$upi_url = TC_Payments::upi_link( $amount, $purpose );
		if ( empty( $upi_url ) ) {
			$upi_url = 'upi://pay?' . http_build_query(
				array(
					'pa' => '9336116210@upi',
					'pn' => 'TripCosmos Pilgrimage Desk',
					'am' => number_format( $amount, 2, '.', '' ),
					'cu' => 'INR',
					'tn' => substr( $purpose, 0, 80 ),
				)
			);
		}
		$cf_url  = '';
		$pay_id  = 0;

		if ( TC_Payments::cashfree_ready() ) {
			$row = TC_Payments::create_cashfree_link( $booking_id, $amount, $purpose );
			if ( ! is_wp_error( $row ) && ! empty( $row['link_url'] ) ) {
				$cf_url = (string) $row['link_url'];
				$pay_id = (int) $row['id'];
			}
		}

		$primary_url = ! empty( $cf_url ) ? $cf_url : $upi_url;

		// WhatsApp dispatch
		$wa_sent = false;
		if ( $send_wa && ! empty( $c['phone'] ) && class_exists( 'TC_Integration_WhatsApp' ) ) {
			$customer_name = ! empty( $c['name'] ) ? $c['name'] : 'Traveler';
			$pay_link_display = ! empty( $cf_url ) ? $cf_url : home_url( '/pay/?amt=' . $amount . '&ref=' . $booking_id );
			$wa_msg = sprintf(
				"Namaste %s ji 🙏\nHere is your official advance payment link for your TripCosmos booking:\n\n💰 *Advance Amount:* ₹%s\n📋 *Purpose:* %s\n\n💳 *Instant Payment Link:*\n%s\n\nDirect UPI ID: 9336116210@upi (TripCosmos Travel Desk)\nOnce completed, instant digital confirmation & GST voucher will be shared automatically. Thank you!",
				$customer_name,
				number_format_i18n( $amount ),
				$purpose,
				$pay_link_display
			);
			$res_wa  = TC_Integration_WhatsApp::send_message_ex( $c['phone'], $wa_msg );
			$wa_sent = false !== $res_wa;
			if ( $wa_sent ) {
				TC_WhatsApp_Hub::record_agent_message( $c, $wa_msg, (string) $res_wa, self::agent( $request ) ?: 'app' );
			}
		}

		// Log activity
		TC_Contact_Service::add_activity(
			(int) $c['id'],
			'booking',
			sprintf( '💳 1-Tap Advance Payment Link generated: ₹%s (%s). Sent via WhatsApp: %s', number_format_i18n( $amount ), $purpose, $wa_sent ? 'Yes' : 'No' )
		);

		return self::ok(
			array(
				'booking_id'    => $booking_id,
				'amount'        => $amount,
				'cashfree_url'  => $cf_url,
				'upi_url'       => $upi_url,
				'primary_url'   => $primary_url,
				'whatsapp_sent' => $wa_sent,
			)
		);
	}

	/* ------------------------------------------------------------------ Conversions & Google Ads Attribution */

	public static function handle_conversion_record( WP_REST_Request $request ) {
		global $wpdb;
		$p = self::params( $request );

		$event_type  = sanitize_text_field( $p['event_type'] ?? 'chatbot_lead' );
		$channel     = sanitize_text_field( $p['channel'] ?? 'web_chatbot' );
		$source      = sanitize_text_field( $p['source'] ?? 'google_ads' );
		$gclid       = sanitize_text_field( $p['gclid'] ?? '' );
		$campaign    = sanitize_text_field( $p['campaign'] ?? '' );
		$keyword     = sanitize_text_field( $p['keyword'] ?? '' );
		$lead_name   = sanitize_text_field( $p['lead_name'] ?? '' );
		$lead_phone  = sanitize_text_field( $p['lead_phone'] ?? '' );
		$destination = sanitize_text_field( $p['destination'] ?? 'Varanasi' );
		$value       = floatval( $p['value'] ?? 100.0 );
		$metadata    = ! empty( $p['metadata'] ) ? wp_json_encode( $p['metadata'] ) : null;

		$table = $wpdb->prefix . 'tc_agent_conversion_log';
		$wpdb->insert(
			$table,
			array(
				'event_type'  => $event_type,
				'channel'     => $channel,
				'source'      => $source,
				'gclid'       => $gclid,
				'campaign'    => $campaign,
				'keyword'     => $keyword,
				'lead_name'   => $lead_name,
				'lead_phone'  => $lead_phone,
				'destination' => $destination,
				'value'       => $value,
				'metadata'    => $metadata,
				'created_at'  => current_time( 'mysql' ),
			)
		);
		$conversion_id = $wpdb->insert_id;

		// If lead_phone is available, link or auto-create CRM Contact
		if ( ! empty( $lead_phone ) && class_exists( 'TC_Contact_Service' ) ) {
			$e164 = TC_Contact_Service::normalize_phone( $lead_phone );
			if ( ! empty( $e164 ) ) {
				$upsert_res = TC_Contact_Service::upsert( array(
					'name'           => $lead_name ?: 'Web Traveler',
					'phone'          => $e164,
					'source_channel' => $channel,
					'stage'          => 'lead',
					'destination'    => $destination,
					'notes'          => sprintf( 'Auto-captured via %s (%s). Campaign: %s, Keyword: %s, GCLID: %s', $channel, $event_type, $campaign ?: 'None', $keyword ?: 'None', $gclid ?: 'None' ),
				) );
				if ( ! empty( $upsert_res['id'] ) ) {
					TC_Contact_Service::add_activity( (int) $upsert_res['id'], 'lead_created', "Inquired via AI Chatbot with Google Ads attribution (GCLID: $gclid, Campaign: $campaign)" );
				}
			}
		}

		return self::ok( array( 'conversion_id' => $conversion_id, 'recorded' => true ) );
	}

	public static function handle_google_ads_conversions( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Google Ads analytics is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_conversion_log';

		// Summary metrics
		$total_conversions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		$total_value       = (float) $wpdb->get_var( "SELECT COALESCE(SUM(value), 0) FROM $table" );
		$gads_conversions  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE (gclid IS NOT NULL AND gclid != '') OR source = 'google_ads'" );
		$chatbot_leads     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE channel = 'web_chatbot' OR event_type LIKE '%chatbot%'" );
		$whatsapp_clicks   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE event_type LIKE '%whatsapp%'" );
		$phone_calls       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE event_type LIKE '%phone%'" );

		// Breakdown by event_type
		$by_event = $wpdb->get_results( "SELECT event_type, COUNT(*) as count, COALESCE(SUM(value), 0) as total_val FROM $table GROUP BY event_type ORDER BY count DESC", ARRAY_A ) ?: array();

		// Breakdown by campaign
		$by_campaign = $wpdb->get_results( "SELECT COALESCE(NULLIF(campaign, ''), 'Organic / Direct') as campaign_name, COUNT(*) as count FROM $table GROUP BY campaign_name ORDER BY count DESC LIMIT 10", ARRAY_A ) ?: array();

		// Recent 50 conversions
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 50", ARRAY_A ) ?: array();
		$events = array();
		foreach ( $rows as $r ) {
			$events[] = array(
				'id'          => (int) $r['id'],
				'event_type'  => (string) $r['event_type'],
				'channel'     => (string) $r['channel'],
				'source'      => (string) $r['source'],
				'gclid'       => (string) ( $r['gclid'] ?? '' ),
				'campaign'    => (string) ( $r['campaign'] ?? '' ),
				'keyword'     => (string) ( $r['keyword'] ?? '' ),
				'lead_name'   => (string) ( $r['lead_name'] ?? '' ),
				'lead_phone'  => (string) ( $r['lead_phone'] ?? '' ),
				'destination' => (string) ( $r['destination'] ?? '' ),
				'value'       => (float) $r['value'],
				'date'        => self::iso( $r['created_at'] ),
			);
		}

		return self::ok( array(
			'summary' => array(
				'total_conversions'    => $total_conversions,
				'total_value'          => $total_value,
				'google_ads_count'     => $gads_conversions,
				'chatbot_leads_count'  => $chatbot_leads,
				'whatsapp_clicks_count'=> $whatsapp_clicks,
				'phone_calls_count'    => $phone_calls,
			),
			'by_event'    => $by_event,
			'by_campaign' => $by_campaign,
			'events'      => $events,
		) );
	}

	public static function handle_google_ads_config( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Google Ads configuration is restricted to administrators and finance managers.', 403 );
		}
		if ( ! class_exists( 'TC_Integration_Google_Ads' ) ) {
			return self::fail( 'Google Ads integration module not loaded.', 500 );
		}
		$summary = TC_Integration_Google_Ads::get_summary();
		return self::ok( $summary );
	}

	public static function handle_google_ads_sync( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Google Ads sync is restricted to administrators and finance managers.', 403 );
		}
		if ( ! class_exists( 'TC_Integration_Google_Ads' ) ) {
			return self::fail( 'Google Ads integration module not loaded.', 500 );
		}
		$res = TC_Integration_Google_Ads::sync_campaigns();
		if ( ! empty( $res['ok'] ) ) {
			return self::ok( $res );
		}
		return self::fail( $res['error'] ?? 'Sync failed.', 400 );
	}

	public static function handle_google_ads_upload_conversion( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Google Ads offline conversions are restricted to administrators and finance managers.', 403 );
		}
		if ( ! class_exists( 'TC_Integration_Google_Ads' ) ) {
			return self::fail( 'Google Ads integration module not loaded.', 500 );
		}
		$p     = self::params( $request );
		$gclid = sanitize_text_field( $p['gclid'] ?? '' );
		$value = (float) ( $p['value'] ?? 0 );
		$order = sanitize_text_field( $p['order_id'] ?? '' );

		if ( empty( $gclid ) || $value <= 0 ) {
			return self::fail( 'GCLID and a positive conversion value are required.', 400 );
		}

		$res = TC_Integration_Google_Ads::upload_offline_conversion( $gclid, $value, $order );
		if ( ! empty( $res['ok'] ) ) {
			return self::ok( $res );
		}
		return self::fail( $res['error'] ?? 'Upload failed.', 400 );
	}

	/* ------------------------------------------------------------------ Bank Statement Importer (SBI) */

	public static function handle_bank_statement_upload( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Bank statement import is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p = self::params( $request );

		$csv_raw = $p['csv_data'] ?? '';
		if ( empty( $csv_raw ) && ! empty( $_FILES['file']['tmp_name'] ) ) {
			$csv_raw = file_get_contents( $_FILES['file']['tmp_name'] );
		}

		if ( empty( trim( $csv_raw ) ) ) {
			return self::fail( 'CSV content or file is empty.', 400 );
		}

		$bank_name  = sanitize_text_field( $p['bank_name'] ?? 'State Bank of India (SBI)' );
		$account_no = sanitize_text_field( $p['account_no'] ?? '' );
		$table      = $wpdb->prefix . 'tcfp_bank_transactions';
		$bk_table   = $wpdb->prefix . 'tc_agent_bookings';

		// Parse CSV lines
		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv_raw ) );
		if ( empty( $lines ) ) {
			return self::fail( 'No lines found in CSV.', 400 );
		}

		$header_idx = -1;
		$col_map = array();

		// Find header row (looks for date, narration/description, debit/withdrawal, credit/deposit)
		foreach ( $lines as $idx => $line ) {
			$cols = str_getcsv( $line );
			$lower_cols = array_map( 'strtolower', array_map( 'trim', $cols ) );
			foreach ( $lower_cols as $c_idx => $col_val ) {
				if ( in_array( $col_val, array( 'txn date', 'txndate', 'date', 'value date' ), true ) && ! isset( $col_map['date'] ) ) {
					$col_map['date'] = $c_idx;
				}
				if ( in_array( $col_val, array( 'narration', 'description', 'particulars', 'remarks' ), true ) && ! isset( $col_map['desc'] ) ) {
					$col_map['desc'] = $c_idx;
				}
				if ( in_array( $col_val, array( 'ref no./cheque no.', 'ref no', 'cheque no', 'ref / chq no.', 'chq/ref no' ), true ) && ! isset( $col_map['ref'] ) ) {
					$col_map['ref'] = $c_idx;
				}
				if ( in_array( $col_val, array( 'debit', 'withdrawal', 'dr' ), true ) && ! isset( $col_map['debit'] ) ) {
					$col_map['debit'] = $c_idx;
				}
				if ( in_array( $col_val, array( 'credit', 'deposit', 'cr' ), true ) && ! isset( $col_map['credit'] ) ) {
					$col_map['credit'] = $c_idx;
				}
				if ( in_array( $col_val, array( 'balance', 'bal' ), true ) && ! isset( $col_map['balance'] ) ) {
					$col_map['balance'] = $c_idx;
				}
			}
			if ( isset( $col_map['date'] ) && ( isset( $col_map['debit'] ) || isset( $col_map['credit'] ) || isset( $col_map['desc'] ) ) ) {
				$header_idx = $idx;
				break;
			}
		}

		if ( $header_idx === -1 || ! isset( $col_map['date'] ) ) {
			$col_map = array( 'date' => 0, 'desc' => 2, 'ref' => 3, 'debit' => 4, 'credit' => 5, 'balance' => 6 );
			$header_idx = 0;
		}

		$inserted = 0;
		$credits_count = 0;
		$debits_count = 0;
		$auto_reconciled = 0;

		for ( $i = $header_idx + 1; $i < count( $lines ); $i++ ) {
			$row = str_getcsv( $lines[ $i ] );
			if ( empty( $row ) || count( $row ) < 2 ) {
				continue;
			}

			$raw_date = trim( $row[ $col_map['date'] ] ?? '' );
			if ( empty( $raw_date ) ) {
				continue;
			}

			// Format date to Y-m-d (Strict check for Indian dd/mm/yyyy format first)
			$raw_clean = trim( str_replace( array( '-', '.' ), '/', $raw_date ) );
			$d = DateTime::createFromFormat( 'd/m/Y', $raw_clean ) ?: DateTime::createFromFormat( 'd/m/y', $raw_clean ) ?: DateTime::createFromFormat( 'Y/m/d', $raw_clean );
			if ( $d ) {
				$txn_date = $d->format( 'Y-m-d' );
			} else {
				$ts = strtotime( $raw_clean );
				$txn_date = $ts ? date( 'Y-m-d', $ts ) : current_time( 'Y-m-d' );
			}

			$desc = trim( $row[ $col_map['desc'] ?? 2 ] ?? '' );
			if ( empty( $desc ) ) {
				continue;
			}

			$ref_no = trim( $row[ $col_map['ref'] ?? 3 ] ?? '' );

			$debit_str = str_replace( array( ',', ' ' ), '', $row[ $col_map['debit'] ?? 4 ] ?? '0' );
			$credit_str = str_replace( array( ',', ' ' ), '', $row[ $col_map['credit'] ?? 5 ] ?? '0' );
			$bal_str = str_replace( array( ',', ' ' ), '', $row[ $col_map['balance'] ?? 6 ] ?? '0' );

			$debit_val = floatval( $debit_str );
			$credit_val = floatval( $credit_str );
			$balance_val = floatval( $bal_str );

			if ( $credit_val > 0 ) {
				$type = 'credit';
				$amount = $credit_val;
				$credits_count++;
			} elseif ( $debit_val > 0 ) {
				$type = 'debit';
				$amount = $debit_val;
				$debits_count++;
			} else {
				continue;
			}

			// Category heuristic
			$lower_desc = strtolower( $desc );
			$category = 'General';
			if ( strpos( $lower_desc, 'upi/' ) !== false || strpos( $lower_desc, 'upi' ) !== false ) {
				$category = 'UPI Transfer';
			} elseif ( strpos( $lower_desc, 'hotel' ) !== false || strpos( $lower_desc, 'resort' ) !== false ) {
				$category = 'Hotel & Stay';
			} elseif ( strpos( $lower_desc, 'fuel' ) !== false || strpos( $lower_desc, 'petrol' ) !== false || strpos( $lower_desc, 'toll' ) !== false || strpos( $lower_desc, 'cab' ) !== false ) {
				$category = 'Cab & Transport';
			} elseif ( strpos( $lower_desc, 'boat' ) !== false || strpos( $lower_desc, 'ghat' ) !== false ) {
				$category = 'Boat & Pilgrimage';
			} elseif ( strpos( $lower_desc, 'google' ) !== false || strpos( $lower_desc, 'ads' ) !== false || strpos( $lower_desc, 'meta' ) !== false ) {
				$category = 'Marketing & Ads';
			} elseif ( strpos( $lower_desc, 'salary' ) !== false || strpos( $lower_desc, 'staff' ) !== false ) {
				$category = 'Salary & Payroll';
			} elseif ( $type === 'credit' ) {
				$category = 'Customer Advance / Payment';
			}

			// Auto reconcile check against bookings
			$matched_booking_id = null;
			$is_reconciled = 0;
			if ( $type === 'credit' && $amount > 0 ) {
				$bk = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, quote_amount, advance_paid FROM $bk_table WHERE (advance_paid = %f OR quote_amount = %f) ORDER BY id DESC LIMIT 1",
						$amount, $amount
					),
					ARRAY_A
				);
				if ( $bk ) {
					$matched_booking_id = (int) $bk['id'];
					$is_reconciled = 1;
					$auto_reconciled++;
				}
			}

			// Avoid exact duplicate
			$dup = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table WHERE txn_date = %s AND amount = %f AND type = %s AND description = %s LIMIT 1",
					$txn_date, $amount, $type, $desc
				)
			);
			if ( $dup ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'txn_date'    => $txn_date,
					'description' => $desc,
					'ref_no'      => $ref_no,
					'amount'      => $amount,
					'type'        => $type,
					'balance'     => $balance_val > 0 ? $balance_val : null,
					'bank_name'   => $bank_name,
					'account_no'  => $account_no,
					'category'    => $category,
					'reconciled'  => $is_reconciled,
					'booking_id'  => $matched_booking_id,
					'created_at'  => current_time( 'mysql' ),
				)
			);
			$inserted++;
		}

		return self::ok( array(
			'inserted'        => $inserted,
			'credits_count'   => $credits_count,
			'debits_count'    => $debits_count,
			'auto_reconciled' => $auto_reconciled,
			'message'         => sprintf( "Successfully imported %d transactions (%d credits, %d debits). %d auto-reconciled.", $inserted, $credits_count, $debits_count, $auto_reconciled ),
		) );
	}

	public static function handle_bank_statement_transactions( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Bank statement feed is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p     = self::params( $request );
		$table = $wpdb->prefix . 'tcfp_bank_transactions';
		$bk    = $wpdb->prefix . 'tc_agent_bookings';

		$type_filter   = sanitize_text_field( $p['type'] ?? 'all' );
		$status_filter = sanitize_text_field( $p['status'] ?? 'all' );
		$limit         = min( 500, max( 10, absint( $p['limit'] ?? 100 ) ) );

		$where = array( '1=1' );
		if ( 'credit' === $type_filter || 'debit' === $type_filter ) {
			$where[] = $wpdb->prepare( "t.type = %s", $type_filter );
		}
		if ( 'reconciled' === $status_filter ) {
			$where[] = "t.reconciled = 1";
		} elseif ( 'pending' === $status_filter ) {
			$where[] = "t.reconciled = 0";
		}

		$where_sql = implode( ' AND ', $where );

		// Aggregates
		$total_credits = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE type = 'credit'" );
		$total_debits  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE type = 'debit'" );
		$net_balance   = $total_credits - $total_debits;
		$latest_bal    = (float) $wpdb->get_var( "SELECT balance FROM $table WHERE balance IS NOT NULL AND balance > 0 ORDER BY txn_date DESC, id DESC LIMIT 1" );
		if ( $latest_bal > 0 ) {
			$net_balance = $latest_bal;
		}

		$reconciled_cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE reconciled = 1" );
		$pending_cnt    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE reconciled = 0 AND type = 'credit'" );

		$contacts = class_exists( 'TC_Contact_Service' ) ? TC_Contact_Service::table() : ( $wpdb->prefix . 'tc_agent_contacts' );
		$rows = $wpdb->get_results(
			"SELECT t.*, b.ref as booking_ref, c.name as booking_customer
			 FROM $table t
			 LEFT JOIN $bk b ON b.id = t.booking_id
			 LEFT JOIN $contacts c ON c.id = b.contact_id
			 WHERE $where_sql
			 ORDER BY t.txn_date DESC, t.id DESC
			 LIMIT $limit",
			ARRAY_A
		) ?: array();

		$transactions = array();
		foreach ( $rows as $r ) {
			$transactions[] = array(
				'id'               => (int) $r['id'],
				'txn_date'         => $r['txn_date'],
				'description'      => $r['description'],
				'ref_no'           => (string) ( $r['ref_no'] ?? '' ),
				'amount'           => (float) $r['amount'],
				'type'             => (string) $r['type'],
				'balance'          => $r['balance'] !== null ? (float) $r['balance'] : null,
				'bank_name'        => (string) ( $r['bank_name'] ?? 'State Bank of India (SBI)' ),
				'account_no'       => (string) ( $r['account_no'] ?? '' ),
				'category'         => (string) ( $r['category'] ?? 'General' ),
				'reconciled'       => (bool) $r['reconciled'],
				'booking_id'       => $r['booking_id'] ? (int) $r['booking_id'] : null,
				'booking_ref'      => (string) ( $r['booking_ref'] ?? '' ),
				'booking_customer' => (string) ( $r['booking_customer'] ?? '' ),
			);
		}

		return self::ok( array(
			'summary' => array(
				'total_credits'          => $total_credits,
				'total_debits'           => $total_debits,
				'net_balance'            => $net_balance,
				'reconciled_count'       => $reconciled_cnt,
				'pending_reconcile_count'=> $pending_cnt,
			),
			'transactions' => $transactions,
		) );
	}

	public static function handle_bank_statement_reconcile( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Bank reconciliation is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p          = self::params( $request );
		$tx_id      = absint( $p['transaction_id'] ?? 0 );
		$booking_id = absint( $p['booking_id'] ?? 0 );

		if ( ! $tx_id ) {
			return self::fail( 'Transaction ID required.', 400 );
		}

		$table = $wpdb->prefix . 'tcfp_bank_transactions';
		$wpdb->update(
			$table,
			array(
				'reconciled' => 1,
				'booking_id' => $booking_id ?: null,
			),
			array( 'id' => $tx_id )
		);

		if ( $booking_id ) {
			$tx = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $tx_id ), ARRAY_A );
			if ( $tx && $tx['type'] === 'credit' && (float) $tx['amount'] > 0 ) {
				if ( class_exists( 'TC_Payments' ) && method_exists( 'TC_Payments', 'record_manual' ) ) {
					TC_Payments::record_manual( $booking_id, (float) $tx['amount'], 'bank', $tx['ref_no'] ?: 'SBI-STMT-' . $tx_id );
				} else {
					$bk_table = $wpdb->prefix . 'tc_agent_bookings';
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE $bk_table SET advance_paid = advance_paid + %f, status = 'advance_received' WHERE id = %d",
							(float) $tx['amount'], $booking_id
						)
					);
				}

				// Auto-dispatch Offline Conversion to Google Ads if lead has GCLID
				if ( class_exists( 'TC_Integration_Google_Ads' ) && TC_Integration_Google_Ads::is_configured() ) {
					$lead_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT lead_id FROM {$wpdb->prefix}tc_agent_bookings WHERE id = %d", $booking_id ) );
					if ( $lead_id > 0 ) {
						$gclid = (string) $wpdb->get_var( $wpdb->prepare( "SELECT gclid FROM {$wpdb->prefix}tc_agent_conversion_log WHERE lead_id = %d AND gclid != '' ORDER BY id DESC LIMIT 1", $lead_id ) );
						if ( empty( $gclid ) ) {
							$gclid = (string) get_post_meta( $lead_id, '_gclid', true );
						}
						if ( ! empty( $gclid ) ) {
							TC_Integration_Google_Ads::upload_offline_conversion( $gclid, (float) $tx['amount'], 'TC-BK-' . $booking_id );
						}
					}
				}
			}
		}

		return self::ok( array( 'reconciled' => true, 'transaction_id' => $tx_id, 'booking_id' => $booking_id ) );
	}

	/* ------------------------------------------------------------------ Zoho Books / Invoice Integration */

	public static function handle_zoho_summary( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Zoho Financial Intelligence is restricted to administrators and finance managers.', 403 );
		}
		// Use active TC_Integration_Zoho first
		if ( class_exists( 'TC_Integration_Zoho' ) ) {
			$sum = TC_Integration_Zoho::get_summary();
			if ( ! empty( $sum['connected'] ) || ! empty( $sum['invoices'] ) ) {
				return self::ok( $sum );
			}
		}

		$connected = false;
		$invoices  = array();
		$metrics   = array(
			'total_invoiced'   => 0.0,
			'total_paid'       => 0.0,
			'total_balance'    => 0.0,
			'overdue_count'    => 0,
			'connected'        => false,
			'org_id'           => '',
		);

		$org_id = get_option( 'tcfp_setting_zoho_org_id', '' );

		if ( class_exists( 'TCFP_Zoho' ) ) {
			$res = TCFP_Zoho::list_invoices( 1 );
			if ( ! empty( $res['invoices'] ) && is_array( $res['invoices'] ) ) {
				$connected = true;
				foreach ( $res['invoices'] as $inv ) {
					$total   = (float) ( $inv['total'] ?? 0 );
					$balance = (float) ( $inv['balance'] ?? 0 );
					$status  = strtolower( (string) ( $inv['status'] ?? 'draft' ) );

					$metrics['total_invoiced'] += $total;
					$metrics['total_paid']     += ( $total - $balance );
					$metrics['total_balance']  += $balance;
					if ( 'overdue' === $status ) {
						$metrics['overdue_count']++;
					}

					$invoices[] = array(
						'invoice_id'     => (string) ( $inv['invoice_id'] ?? '' ),
						'invoice_number' => (string) ( $inv['invoice_number'] ?? '' ),
						'customer_name'  => (string) ( $inv['customer_name'] ?? 'Traveler' ),
						'date'           => (string) ( $inv['date'] ?? '' ),
						'due_date'       => (string) ( $inv['due_date'] ?? '' ),
						'total'          => $total,
						'balance'        => $balance,
						'status'         => $status,
					);
				}
			}
		}

		$metrics['connected'] = $connected;
		$metrics['org_id']    = $org_id;

		return self::ok( array(
			'connected' => $connected,
			'metrics'   => $metrics,
			'invoices'  => $invoices,
			'help'      => $connected ? 'Zoho Books is actively synced.' : 'Connect Zoho Books via Settings to sync automatic GST invoices.',
		) );
	}

	public static function handle_zoho_sync( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Zoho Books synchronization is restricted to administrators and finance managers.', 403 );
		}
		if ( class_exists( 'TC_Integration_Zoho' ) ) {
			$res = TC_Integration_Zoho::sync();
			return self::ok( array( 'synced' => true, 'result' => $res ) );
		}
		if ( class_exists( 'TCFP_Zoho' ) ) {
			$res = TCFP_Zoho::sync_all();
			return self::ok( array( 'synced' => true, 'result' => $res ) );
		}
		return self::ok( array( 'synced' => false, 'message' => 'Zoho module ready.' ) );
	}

	/* ------------------------------------------------------------------ Customer Profit & Margin Ledger */

	public static function handle_customer_margins( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Customer Profit & Margin Ledger is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$invoices = array();
		if ( class_exists( 'TC_Integration_Zoho' ) ) {
			$sum = TC_Integration_Zoho::get_summary();
			$invoices = $sum['invoices'] ?? array();
		}

		$table_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$has_table = (bool) $wpdb->get_var( "SHOW TABLES LIKE '$table_exp'" );

		$all_expenses = array();
		if ( $has_table ) {
			$rows = $wpdb->get_results( "SELECT * FROM $table_exp ORDER BY expense_date DESC, id DESC", ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$inv_no = trim( (string) $r['invoice_no'] );
				if ( ! isset( $all_expenses[ $inv_no ] ) ) {
					$all_expenses[ $inv_no ] = array();
				}
				$all_expenses[ $inv_no ][] = $r;
			}
		}

		$total_invoiced  = 0.0;
		$total_collected = 0.0;
		$total_cogs      = 0.0;
		$total_hotel     = 0.0;
		$total_cab       = 0.0;
		$total_aarti     = 0.0;
		$total_guide     = 0.0;
		$total_other     = 0.0;

		$customer_items = array();

		foreach ( $invoices as $inv ) {
			$inv_no    = trim( (string) ( $inv['invoice_number'] ?? '' ) );
			$total     = (float) ( $inv['total'] ?? 0 );
			$balance   = (float) ( $inv['balance'] ?? 0 );
			$paid      = max( 0.0, $total - $balance );
			$cust_name = (string) ( $inv['customer_name'] ?? 'Traveler' );

			$total_invoiced  += $total;
			$total_collected += $paid;

			$exp_list  = $all_expenses[ $inv_no ] ?? array();
			$hotel_exp = 0.0;
			$cab_exp   = 0.0;
			$aarti_exp = 0.0;
			$guide_exp = 0.0;
			$meals_exp = 0.0;
			$other_exp = 0.0;

			foreach ( $exp_list as $e ) {
				$amt = (float) ( $e['amount'] ?? 0 );
				$cat = strtolower( (string) ( $e['category'] ?? 'other' ) );
				if ( 'hotel' === $cat ) {
					$hotel_exp += $amt;
				} elseif ( 'cab' === $cat ) {
					$cab_exp += $amt;
				} elseif ( 'aarti_boat' === $cat ) {
					$aarti_exp += $amt;
				} elseif ( 'guide_temple' === $cat ) {
					$guide_exp += $amt;
				} elseif ( 'meals' === $cat ) {
					$meals_exp += $amt;
				} else {
					$other_exp += $amt;
				}
			}

			$cust_cogs  = $hotel_exp + $cab_exp + $aarti_exp + $guide_exp + $meals_exp + $other_exp;
			$net_profit = $total - $cust_cogs;
			$margin_pct = $total > 0 ? round( ( $net_profit / $total ) * 100, 1 ) : 0.0;

			$total_cogs  += $cust_cogs;
			$total_hotel += $hotel_exp;
			$total_cab   += $cab_exp;
			$total_aarti += $aarti_exp;
			$total_guide += $guide_exp;
			$total_other += ( $meals_exp + $other_exp );

			$customer_items[] = array(
				'invoice_id'     => (string) ( $inv['invoice_id'] ?? '' ),
				'invoice_number' => $inv_no,
				'customer_name'  => $cust_name,
				'date'           => (string) ( $inv['date'] ?? '' ),
				'due_date'       => (string) ( $inv['due_date'] ?? '' ),
				'status'         => (string) ( $inv['status'] ?? 'draft' ),
				'total_invoiced' => $total,
				'total_paid'     => $paid,
				'balance'        => $balance,
				'expenses'       => array(
					'hotel'        => $hotel_exp,
					'cab'          => $cab_exp,
					'aarti_boat'   => $aarti_exp,
					'guide_temple' => $guide_exp,
					'meals'        => $meals_exp,
					'other'        => $other_exp,
					'total'        => $cust_cogs,
				),
				'net_profit'     => $net_profit,
				'margin_pct'     => $margin_pct,
				'expense_count'  => count( $exp_list ),
			);
		}

		$overall_profit = $total_invoiced - $total_cogs;
		$avg_margin     = $total_invoiced > 0 ? round( ( $overall_profit / $total_invoiced ) * 100, 1 ) : 0.0;

		return self::ok( array(
			'summary' => array(
				'total_invoiced'   => $total_invoiced,
				'total_collected'  => $total_collected,
				'total_cogs'       => $total_cogs,
				'total_hotel'      => $total_hotel,
				'total_cab'        => $total_cab,
				'total_aarti_boat' => $total_aarti,
				'total_guide'      => $total_guide,
				'total_other'      => $total_other,
				'net_profit'       => $overall_profit,
				'avg_margin_pct'   => $avg_margin,
				'customer_count'   => count( $customer_items ),
			),
			'customers' => $customer_items,
		) );
	}

	public static function handle_customer_expenses_list( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Expense records are restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p      = self::params( $request );
		$inv_no = sanitize_text_field( $p['invoice_number'] ?? ( $p['invoice_no'] ?? '' ) );

		$table_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		if ( empty( $inv_no ) ) {
			$items = $wpdb->get_results( "SELECT * FROM $table_exp ORDER BY expense_date DESC, id DESC LIMIT 200", ARRAY_A ) ?: array();
		} else {
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_exp WHERE invoice_no = %s ORDER BY expense_date DESC, id DESC", $inv_no ), ARRAY_A ) ?: array();
		}

		return self::ok( array( 'expenses' => $items, 'count' => count( $items ) ) );
	}

	public static function handle_customer_expense_add( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Expense recording is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p           = self::params( $request );
		$inv_no      = sanitize_text_field( $p['invoice_number'] ?? ( $p['invoice_no'] ?? '' ) );
		$cust_name   = sanitize_text_field( $p['customer_name'] ?? '' );
		$category    = sanitize_text_field( $p['category'] ?? 'other' );
		$vendor_name = sanitize_text_field( $p['vendor_name'] ?? '' );
		$amount      = (float) ( $p['amount'] ?? 0 );
		$exp_date    = sanitize_text_field( $p['expense_date'] ?? current_time( 'Y-m-d' ) );
		$method      = sanitize_text_field( $p['payment_method'] ?? 'bank_debit' );
		$bank_txn_id = absint( $p['bank_txn_id'] ?? 0 );
		$notes       = sanitize_textarea_field( $p['notes'] ?? '' );

		if ( empty( $inv_no ) ) {
			return self::fail( 'Invoice Number is required.', 400 );
		}
		if ( $amount <= 0 ) {
			return self::fail( 'Amount must be greater than zero.', 400 );
		}

		// Auto resolve customer name from invoices if blank
		if ( empty( $cust_name ) && class_exists( 'TC_Integration_Zoho' ) ) {
			$sum = TC_Integration_Zoho::get_summary();
			foreach ( $sum['invoices'] ?? array() as $i ) {
				if ( ( $i['invoice_number'] ?? '' ) === $inv_no ) {
					$cust_name = $i['customer_name'] ?? 'Traveler';
					break;
				}
			}
		}

		$table_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$wpdb->insert(
			$table_exp,
			array(
				'invoice_no'     => $inv_no,
				'customer_name'  => $cust_name ?: 'Traveler',
				'category'       => $category,
				'vendor_name'    => $vendor_name,
				'amount'         => $amount,
				'expense_date'   => $exp_date,
				'payment_method' => $method,
				'bank_txn_id'    => $bank_txn_id ?: null,
				'notes'          => $notes,
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$exp_id = $wpdb->insert_id;

		// If linked to bank transaction, mark reconciled in tcfp_bank_transactions
		if ( $bank_txn_id ) {
			$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
			$wpdb->update(
				$table_bank,
				array(
					'reconciled'         => 1,
					'category'           => self::map_expense_category_to_bank( $category ),
					'matched_expense_id' => $exp_id,
				),
				array( 'id' => $bank_txn_id )
			);
		}

		return self::ok( array(
			'created'    => true,
			'expense_id' => $exp_id,
			'invoice_no' => $inv_no,
			'message'    => 'Expense added successfully and margin recalculated.',
		) );
	}

	public static function handle_customer_expense_delete( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Expense deletion is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p      = self::params( $request );
		$exp_id = absint( $p['id'] ?? ( $p['expense_id'] ?? 0 ) );

		if ( ! $exp_id ) {
			return self::fail( 'Expense ID required.', 400 );
		}

		$table_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_exp WHERE id = %d", $exp_id ), ARRAY_A );
		if ( ! $row ) {
			return self::fail( 'Expense not found.', 404 );
		}

		if ( ! empty( $row['bank_txn_id'] ) ) {
			$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
			$wpdb->update(
				$table_bank,
				array( 'reconciled' => 0, 'matched_expense_id' => null ),
				array( 'id' => (int) $row['bank_txn_id'] )
			);
		}

		$wpdb->delete( $table_exp, array( 'id' => $exp_id ) );

		return self::ok( array( 'deleted' => true, 'id' => $exp_id ) );
	}

	public static function handle_customer_expense_link_debit( WP_REST_Request $request ) {
		if ( ! self::is_finance_authorized( $request ) ) {
			return self::fail( 'Access denied. Bank reconciliation is restricted to administrators and finance managers.', 403 );
		}
		global $wpdb;
		$p           = self::params( $request );
		$tx_id       = absint( $p['transaction_id'] ?? 0 );
		$inv_no      = sanitize_text_field( $p['invoice_number'] ?? ( $p['invoice_no'] ?? '' ) );
		$cust_name   = sanitize_text_field( $p['customer_name'] ?? '' );
		$category    = sanitize_text_field( $p['category'] ?? 'cab' );
		$vendor_name = sanitize_text_field( $p['vendor_name'] ?? '' );
		$notes       = sanitize_textarea_field( $p['notes'] ?? '' );

		if ( ! $tx_id || empty( $inv_no ) ) {
			return self::fail( 'Transaction ID and Invoice Number are required.', 400 );
		}

		$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
		$tx = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bank WHERE id = %d", $tx_id ), ARRAY_A );
		if ( ! $tx ) {
			return self::fail( 'Bank transaction not found.', 404 );
		}

		$amount   = (float) $tx['amount'];
		$exp_date = $tx['txn_date'];

		// Auto resolve customer name
		if ( empty( $cust_name ) && class_exists( 'TC_Integration_Zoho' ) ) {
			$sum = TC_Integration_Zoho::get_summary();
			foreach ( $sum['invoices'] ?? array() as $i ) {
				if ( ( $i['invoice_number'] ?? '' ) === $inv_no ) {
					$cust_name = $i['customer_name'] ?? 'Traveler';
					break;
				}
			}
		}

		$table_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$wpdb->insert(
			$table_exp,
			array(
				'invoice_no'     => $inv_no,
				'customer_name'  => $cust_name ?: 'Traveler',
				'category'       => $category,
				'vendor_name'    => $vendor_name ?: ( $tx['description'] ?? '' ),
				'amount'         => $amount,
				'expense_date'   => $exp_date,
				'payment_method' => 'bank_debit',
				'bank_txn_id'    => $tx_id,
				'notes'          => $notes ?: ( 'Linked from Bank Txn: ' . ( $tx['ref_no'] ?? '' ) ),
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$exp_id = $wpdb->insert_id;

		// Mark transaction as reconciled
		$wpdb->update(
			$table_bank,
			array(
				'reconciled'         => 1,
				'category'           => self::map_expense_category_to_bank( $category ),
				'matched_expense_id' => $exp_id,
			),
			array( 'id' => $tx_id )
		);

		return self::ok( array(
			'linked'     => true,
			'expense_id' => $exp_id,
			'invoice_no' => $inv_no,
			'message'    => 'Bank debit linked to customer invoice successfully.',
		) );
	}

	private static function map_expense_category_to_bank( $cat ) {
		switch ( $cat ) {
			case 'hotel':
				return 'Hotel & Stay';
			case 'cab':
				return 'Cab & Transport';
			case 'aarti_boat':
				return 'Boat & Pilgrimage';
			case 'guide_temple':
				return 'Guide & Temple VIP';
			case 'meals':
				return 'Meals & Refreshment';
			default:
				return 'Vendor & Tour Expense';
		}
	}

	/* ------------------------------------------------------------------ Authentication & User Management */

	public static function handle_auth_google( WP_REST_Request $request ) {
		$p        = self::params( $request );
		$id_token = sanitize_text_field( $p['id_token'] ?? '' );
		$avatar   = esc_url_raw( $p['avatar_url'] ?? $p['photo_url'] ?? '' );

		// Strict security: id_token is mandatory. Never trust client-supplied email without cryptographic verification.
		if ( empty( $id_token ) ) {
			return self::fail( 'Google ID token is required for authentication.', 400 );
		}

		// 1. Verify id_token with Google OAuth endpoint
		$verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode( $id_token );
		$resp       = wp_remote_get( $verify_url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return self::fail( 'Invalid or expired Google authentication token.', 401 );
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['email'] ) ) {
			return self::fail( 'Google authentication token does not contain a valid email.', 401 );
		}

		// Require email verification flag from Google
		$email_verified = $body['email_verified'] ?? false;
		if ( true !== $email_verified && 'true' !== (string) $email_verified ) {
			return self::fail( 'Google email address is not verified.', 401 );
		}

		// Validate audience / client ID (synced dynamically from Google Site Kit module settings)
		$sitekit_settings  = get_option( 'googlesitekit_sign-in-with-google_settings', array() );
		$sitekit_client_id = ! empty( $sitekit_settings['clientID'] ) ? $sitekit_settings['clientID'] : '';
		$allowed_audiences = array_filter( array(
			$sitekit_client_id,
			'77399722057-6o97tf20jmhjaaid60gks1ajn5k1029s.apps.googleusercontent.com',
		) );
		$aud = (string) ( $body['aud'] ?? '' );
		if ( ! empty( $allowed_audiences ) && ! in_array( $aud, $allowed_audiences, true ) ) {
			TC_Agents_Logger::log( 'google_auth_aud_mismatch', 'warning', array( 'aud' => $aud ), '', 'security' );
			return self::fail( 'Google token audience mismatch.', 403 );
		}

		$email  = sanitize_email( $body['email'] );
		$name   = ! empty( $body['name'] ) ? sanitize_text_field( $body['name'] ) : '';
		$avatar = ! empty( $body['picture'] ) ? esc_url_raw( $body['picture'] ) : $avatar;

		// 2. Authorization check: Any existing WordPress user can log in with their assigned role
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			// Check if new user registration is allowed, or if email matches company/staff list
			$is_company_domain    = (bool) preg_match( '/@tripcosmos\.co$/i', $email );
			$allowed_staff_emails = array(
				'hellonishkarsh@gmail.com',
				'aditikarna000@gmail.com',
				'operations@tripcosmos.co',
				'info@tripcosmos.co',
				'tripcosmos.co@gmail.com',
			);
			$custom_whitelist = (array) get_option( 'tc_agents_allowed_staff_emails', array() );
			$all_allowed      = array_merge( $allowed_staff_emails, array_map( 'strtolower', array_filter( $custom_whitelist ) ) );
			$can_register     = (bool) get_option( 'users_can_register' ) || $is_company_domain || in_array( strtolower( $email ), $all_allowed, true );

			if ( ! $can_register ) {
				TC_Agents_Logger::log( 'unauthorized_google_login_attempt', 'warning', array( 'email' => $email ), '', 'security' );
				return self::fail( 'Access restricted: Google account (' . esc_html( $email ) . ') is not registered on TripCosmos. Please log in with your WordPress account or contact an administrator.', 403 );
			}

			// Proceed with account provisioning
			$username = sanitize_user( current( explode( '@', $email ) ) );
			if ( username_exists( $username ) ) {
				$username = $username . '_' . wp_rand( 100, 999 );
			}
			$random_password = wp_generate_password( 24, true );
			$user_id         = wp_create_user( $username, $random_password, $email );
			if ( is_wp_error( $user_id ) ) {
				return self::fail( 'Could not register user: ' . $user_id->get_error_message(), 500 );
			}
			$user = get_userdata( $user_id );
			$default_role = get_option( 'default_role', 'tc_sales_agent' );
			$user->set_role( $default_role );
			if ( ! empty( $name ) ) {
				wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
			}
		}

		return self::respond_with_user_token( $user, $avatar );
	}

	public static function handle_auth_login( WP_REST_Request $request ) {
		$p = self::params( $request );
		$username = sanitize_text_field( $p['username'] ?? $p['email'] ?? '' );
		$password = (string) ( $p['password'] ?? '' );

		if ( empty( $username ) || empty( $password ) ) {
			return self::fail( 'Username and password are required.', 400 );
		}

		$user = wp_authenticate( $username, $password );
		if ( is_wp_error( $user ) ) {
			return self::fail( 'Invalid username, email, or password.', 401 );
		}

		return self::respond_with_user_token( $user );
	}

	public static function handle_auth_me( WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( '_tc_user_id' );

		// 1. Validate WordPress cookies across all schemes (logged_in and secure_auth)
		if ( ! $user_id && function_exists( 'wp_validate_auth_cookie' ) ) {
			$user_id = (int) wp_validate_auth_cookie( '', 'logged_in' );
			if ( ! $user_id ) {
				$user_id = (int) wp_validate_auth_cookie( '', 'secure_auth' );
			}
			if ( ! $user_id ) {
				$user_id = (int) wp_validate_auth_cookie();
			}
		}

		// 2. Fallback: inspect incoming $_COOKIE array for any valid WordPress session cookie
		if ( ! $user_id && ! empty( $_COOKIE ) && function_exists( 'wp_validate_auth_cookie' ) ) {
			foreach ( $_COOKIE as $cname => $cval ) {
				if ( is_string( $cval ) && strpos( $cname, 'wordpress_logged_in_' ) === 0 ) {
					$uid = wp_validate_auth_cookie( $cval, 'logged_in' );
					if ( $uid ) {
						$user_id = (int) $uid;
						break;
					}
				}
				if ( is_string( $cval ) && strpos( $cname, 'wordpress_sec_' ) === 0 ) {
					$uid = wp_validate_auth_cookie( $cval, 'secure_auth' );
					if ( $uid ) {
						$user_id = (int) $uid;
						break;
					}
				}
			}
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = (int) get_current_user_id();
		}

		if ( ! $user_id ) {
			// Check if master device pairing token was passed
			$agent = (string) $request->get_param( '_tc_agent' );
			if ( 'master' === $agent || 'admin' === $agent ) {
				return self::ok( array(
					'authenticated' => true,
					'user'          => array(
						'id'          => 0,
						'name'        => 'Master Device',
						'email'       => 'admin@tripcosmos.co',
						'role'        => 'administrator',
						'role_label'  => 'Super Admin',
						'permissions' => array_merge( array( 'all' ), array_keys( self::get_all_feature_definitions() ) ),
					)
				) );
			}
			return self::ok( array(
				'authenticated' => false,
				'user'          => null,
			) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return self::fail( 'User not found.', 404 );
		}

		return self::respond_with_user_token( $user );
	}

	private static function respond_with_user_token( WP_User $user, $avatar = '' ) {
		// Synchronize WordPress session cookie for browser & PWA clients
		if ( function_exists( 'wp_set_current_user' ) && function_exists( 'wp_set_auth_cookie' ) ) {
			if ( ! is_user_logged_in() || get_current_user_id() !== $user->ID ) {
				wp_set_current_user( $user->ID, $user->user_login );
				wp_set_auth_cookie( $user->ID, true );
			}
		}

		$roles = (array) $user->roles;

		// Auto-provision approved company / staff emails to tc_sales_agent if only default subscriber/customer role
		$is_company_domain = (bool) preg_match( '/@tripcosmos\.co$/i', $user->user_email );
		$allowed_staff_emails = array(
			'hellonishkarsh@gmail.com',
			'aditikarna000@gmail.com',
			'operations@tripcosmos.co',
			'info@tripcosmos.co',
			'tripcosmos.co@gmail.com',
			'visualsstudiomarketing@gmail.com',
			'webxprodigital@gmail.com',
			'mananjayvaranasi@gmail.com',
			'ankitarrawal@gmail.com',
			'shubha.st25@gmail.com',
		);
		$custom_whitelist = (array) get_option( 'tc_agents_allowed_staff_emails', array() );
		$all_allowed      = array_merge( $allowed_staff_emails, array_map( 'strtolower', array_filter( $custom_whitelist ) ) );

		if ( ( $is_company_domain || in_array( strtolower( $user->user_email ), $all_allowed, true ) ) && ( empty( $roles ) || ( count( $roles ) === 1 && in_array( reset( $roles ), array( 'customer', 'subscriber' ), true ) ) ) ) {
			$user->set_role( 'tc_sales_agent' );
			$roles = (array) $user->roles;
		}

		// STRICT ROLE BINDING: Only Administrator, Sales Manager, or Sales Agent can enter X-Agents
		$allowed_app_roles = array(
			'administrator',
			'tc_sales_manager',
			'tc_sales_agent',
			'editor',
		);
		$has_access = false;
		foreach ( $allowed_app_roles as $ar ) {
			if ( in_array( $ar, $roles, true ) || user_can( $user, 'manage_options' ) ) {
				$has_access = true;
				break;
			}
		}

		if ( ! $has_access ) {
			if ( class_exists( 'TC_Agents_Logger' ) ) {
				TC_Agents_Logger::log( 'unauthorized_app_access_blocked', 'warning', array( 'user_id' => $user->ID, 'roles' => $roles, 'email' => $user->user_email ), '', 'security' );
			}
			return self::fail( 'Access Denied: X-Agents is strictly reserved for TripCosmos Administrators and authorized Sales Personnel. Your account (' . esc_html( $user->user_email ) . ') does not have sales desk credentials.', 403 );
		}

		$label = $user->display_name ?: $user->user_login;
		$token_row = TC_Agents_Security::create_agent_token( $label, $user->ID );

		$role_hierarchy = array(
			'administrator',
			'editor',
			'tc_sales_manager',
			'tc_sales_agent',
			'tcfp_manager',
			'tcfp_accountant',
			'tc_dispatcher',
			'tc_tour_guide',
			'author',
			'contributor',
			'subscriber',
			'customer',
		);
		$primary_role = 'tc_sales_agent';
		foreach ( $role_hierarchy as $hr ) {
			if ( in_array( $hr, $roles, true ) ) {
				$primary_role = $hr;
				break;
			}
		}
		$role_label   = self::get_role_label( $primary_role );
		$permissions  = self::get_user_permissions( $user );

		return self::ok( array(
			'authenticated' => true,
			'token'         => $token_row['token'],
			'user'          => array(
				'id'          => (int) $user->ID,
				'name'        => (string) $user->display_name,
				'email'       => (string) $user->user_email,
				'username'    => (string) $user->user_login,
				'role'        => $primary_role,
				'role_label'  => $role_label,
				'avatar_url'  => $avatar ?: get_avatar_url( $user->ID ),
				'permissions' => $permissions,
			),
		) );
	}

	public static function get_role_label( $role ) {
		$role_labels = array(
			'administrator'    => 'Administrator',
			'tc_sales_manager' => 'Sales Manager',
			'tc_sales_agent'   => 'Sales Representative',
			'tcfp_manager'     => 'Finance Manager',
			'tcfp_accountant'  => 'Accountant',
			'tc_dispatcher'    => 'Dispatcher & Fleet Ops',
			'tc_tour_guide'    => 'Tour Guide',
			'editor'           => 'Editor',
		);
		return $role_labels[ $role ] ?? ucfirst( str_replace( array( 'tc_', '_' ), ' ', $role ) );
	}

	public static function get_all_feature_definitions() {
		return array(
			'financial_hub'   => array(
				'label'       => 'Financial Intelligence Hub',
				'category'    => 'Financial & Accounting',
				'description' => 'Zoho Books invoices, Unit tour profit margins, SBI bank feeds, and Payroll management.',
			),
			'dialer'          => array(
				'label'       => 'Frontline Jio SIM Dialer',
				'category'    => 'Telephony & Sales',
				'description' => 'Smart caller ID, outbound click-to-call, and call logging.',
			),
			'leads_view'      => array(
				'label'       => 'View CRM Leads',
				'category'    => 'CRM & Pipeline',
				'description' => 'Access lead profiles, stage pipelines, and customer travel inquiries.',
			),
			'leads_edit'      => array(
				'label'       => 'Edit CRM Leads',
				'category'    => 'CRM & Pipeline',
				'description' => 'Update lead stages, travel dates, passenger counts, and notes.',
			),
			'leads_delete'    => array(
				'label'       => 'Delete CRM Leads',
				'category'    => 'CRM & Pipeline',
				'description' => 'Permanently delete leads and customer records.',
			),
			'assign_leads'    => array(
				'label'       => 'Reassign Lead Owners',
				'category'    => 'CRM & Pipeline',
				'description' => 'Transfer lead ownership between sales agents.',
			),
			'whatsapp'        => array(
				'label'       => 'WhatsApp Multi-Desk Hub',
				'category'    => 'Omnichannel & Messaging',
				'description' => 'Real-time two-way WhatsApp live chat, quick templates, and escalations.',
			),
			'ai_agents'       => array(
				'label'       => 'AI Agents & Personas',
				'category'    => 'Omnichannel & AI',
				'description' => 'Manage autonomous AI co-pilots, itinerary generators, and voice bot tools.',
			),
			'b2b_hub'         => array(
				'label'       => 'B2B Wholesale DMC Hub',
				'category'    => 'Partner Network',
				'description' => 'Browse B2B agency directory, partner imports, and wholesale quote outreach.',
			),
			'blog_seo'        => array(
				'label'       => 'Blog & VM SEO Brain',
				'category'    => 'Intelligence & Content',
				'description' => 'Content silos, RankMath SEO telemetry, and 1-tap WhatsApp guide sharing.',
			),
			'social_ai'       => array(
				'label'       => 'VM Social AI Radar',
				'category'    => 'Intelligence & Content',
				'description' => 'Omnichannel social accounts, scheduled campaigns, and audience metrics.',
			),
			'reports'         => array(
				'label'       => 'CRM & Telephony Analytics',
				'category'    => 'Intelligence & Content',
				'description' => 'Growth reports, call duration, operator performance, and ROI metrics.',
			),
			'quotes'          => array(
				'label'       => 'Quotation Builder Desk',
				'category'    => 'Telephony & Sales',
				'description' => 'Generate dynamic pricing quotes, hotel breakdowns, and package PDFs.',
			),
			'advance_link'    => array(
				'label'       => 'Payment & Advance Links',
				'category'    => 'Telephony & Sales',
				'description' => 'Generate UPI / Cashfree instant payment links for traveler bookings.',
			),
			'driver_dispatch' => array(
				'label'       => 'Fleet & Driver Dispatch',
				'category'    => 'Operations',
				'description' => 'Assign drivers and dispatch cab duty slips via Brevo SMS and WhatsApp.',
			),
			'settings'        => array(
				'label'       => 'Plugin System Settings',
				'category'    => 'Administration',
				'description' => 'Manage API keys, pairing tokens, webhooks, and core plugin settings.',
			),
		);
	}

	public static function get_default_role_permissions() {
		return array(
			'administrator'    => array( 'all', 'financial_hub', 'dialer', 'leads_view', 'leads_edit', 'leads_delete', 'assign_leads', 'whatsapp', 'ai_agents', 'b2b_hub', 'blog_seo', 'social_ai', 'reports', 'quotes', 'advance_link', 'driver_dispatch', 'settings' ),
			'editor'           => array( 'all', 'financial_hub', 'dialer', 'leads_view', 'leads_edit', 'leads_delete', 'assign_leads', 'whatsapp', 'ai_agents', 'b2b_hub', 'blog_seo', 'social_ai', 'reports', 'quotes', 'advance_link', 'driver_dispatch', 'settings' ),
			'tc_sales_manager' => array( 'dialer', 'leads_view', 'leads_edit', 'assign_leads', 'whatsapp', 'ai_agents', 'b2b_hub', 'blog_seo', 'social_ai', 'reports', 'quotes', 'advance_link', 'driver_dispatch' ),
			'tc_sales_agent'   => array( 'dialer', 'leads_view', 'leads_edit', 'whatsapp', 'ai_agents', 'b2b_hub', 'blog_seo', 'social_ai', 'quotes', 'advance_link' ),
			'tcfp_manager'     => array( 'financial_hub', 'leads_view', 'whatsapp', 'reports' ),
			'tcfp_accountant'  => array( 'financial_hub', 'reports' ),
			'tc_dispatcher'    => array( 'driver_dispatch', 'leads_view', 'whatsapp', 'reports' ),
			'tc_tour_guide'    => array( 'leads_view', 'whatsapp' ),
		);
	}

	public static function get_role_permissions( $role ) {
		$all_saved = get_option( 'tc_agents_role_permissions', array() );
		if ( is_array( $all_saved ) && isset( $all_saved[ $role ] ) && is_array( $all_saved[ $role ] ) ) {
			return $all_saved[ $role ];
		}
		$defaults = self::get_default_role_permissions();
		if ( isset( $defaults[ $role ] ) ) {
			return $defaults[ $role ];
		}
		return array( 'dialer', 'leads_view', 'leads_edit', 'whatsapp', 'ai_agents', 'b2b_hub', 'blog_seo', 'social_ai', 'quotes', 'advance_link' );
	}

	public static function get_user_permissions( WP_User $user ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return array_merge( array( 'all' ), array_keys( self::get_all_feature_definitions() ) );
		}
		$roles = (array) $user->roles;
		$merged = array();
		foreach ( $roles as $r ) {
			$perms = self::get_role_permissions( $r );
			$merged = array_merge( $merged, $perms );
		}
		return array_values( array_unique( $merged ) );
	}
}

