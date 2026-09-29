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
			array( '/mobile/conversations/' . $id, 'GET', 'conversation' ),
			array( '/mobile/conversations/' . $id . '/reply', 'POST', 'conversation_reply' ),
			array( '/mobile/conversations/' . $id . '/ai', 'POST', 'conversation_ai' ),
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
	}

	/* ------------------------------------------------------------------ helpers */

	private static function ok( array $data = array(), $status = 200 ) {
		return new WP_REST_Response( array_merge( array( 'ok' => true ), $data ), $status );
	}

	private static function fail( $message, $status = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => $message ), $status );
	}

	private static function params( WP_REST_Request $request ) {
		return array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
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
	private static function agent( WP_REST_Request $request ) {
		return (string) $request->get_param( '_tc_agent' );
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
		return self::ok(
			array(
				'agent'          => self::agent( $request ),
				'plugin_version' => TC_AGENTS_VERSION,
				'api_level'      => self::API_LEVEL,
				'server_time'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'stages'         => $stages,
				'booking_status' => $statuses,
				'team'           => $team,
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
				'lead_id'       => (int) $r['contact_id'],
				'name'          => $r['name'] ?: 'Traveler',
				'phone'         => (string) $r['phone'],
				'type'          => (string) ( $meta['type'] ?? '' ),
				'duration'      => (int) ( $meta['duration'] ?? 0 ),
				'recording_url' => (string) ( $meta['recording_url'] ?? '' ),
				'note'          => wp_strip_all_tags( (string) $r['content'] ),
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
		if ( 'me' === $owner ) {
			$owner = self::agent( $request );
		}
		if ( '' !== $owner && 'all' !== $owner ) {
			$where[] = ( 'unassigned' === $owner ) ? "owner = ''" : 'owner = %s';
			if ( 'unassigned' !== $owner ) {
				$args[] = $owner;
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
		if ( ! $lead_id || '' === $content || ! self::lead_or_error( $lead_id ) ) {
			return self::fail( 'lead_id and content are required.' );
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
		$args[] = min( 200, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY COALESCE(start_date, '9999-12-31') ASC, id DESC LIMIT %d", $args ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return self::ok( array( 'bookings' => array_map( array( __CLASS__, 'fmt_booking' ), $rows ) ) );
	}

	public static function handle_booking_save( WP_REST_Request $request ) {
		global $wpdb;
		$p       = self::params( $request );
		$lead_id = absint( $p['lead_id'] ?? 0 );
		$bid     = absint( $p['booking_id'] ?? 0 );
		if ( ! $lead_id || ! self::lead_or_error( $lead_id ) ) {
			return self::fail( 'lead_id is required.' );
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
		if ( ! TC_Contact_Service::can_contact( $c, 'whatsapp' ) ) {
			return self::fail( 'This customer opted out of WhatsApp messages.', 403 );
		}
		$limited = self::throttle( $request, 'wa_send', 120, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}
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
		$text = self::text( sanitize_textarea_field( self::params( $request )['message'] ?? '' ), 4000 );
		if ( '' === $text ) {
			return self::fail( 'Message is empty.' );
		}
		$limited = self::throttle( $request, 'wa_send', 120, HOUR_IN_SECONDS );
		if ( $limited ) {
			return $limited;
		}
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
		$enabled = ! empty( self::params( $request )['enabled'] );
		$conv    = TC_WhatsApp_Hub::conversation_for( self::digits( $c['phone'] ), (int) $c['id'] );
		TC_WhatsApp_Hub::set_ai( (int) $conv['id'], $enabled );
		self::audit( $request, $enabled ? 'ai_on' : 'ai_off', array( 'lead_id' => (int) $c['id'] ) );
		return self::ok( array( 'ai_enabled' => $enabled ) );
	}
}
