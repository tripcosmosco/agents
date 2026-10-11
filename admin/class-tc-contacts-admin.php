<?php
/**
 * Contacts screen: list, detail/timeline, tasks, bookings, merge, import/export, FluentCRM sync tools.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Contacts_Admin {

	const PAGE  = 'tc-agents-contacts';
	const NONCE = 'tc_contacts_action';

	const BOOKING_STATUSES = array(
		'quoted'       => 'Quoted',
		'confirmed'    => 'Confirmed',
		'advance_paid' => 'Advance Paid',
		'paid'         => 'Fully Paid',
		'completed'    => 'Completed',
		'cancelled'    => 'Cancelled',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_tc_set_stage', array( __CLASS__, 'ajax_set_stage' ) );
		add_action( 'wp_ajax_tc_generate_itinerary', array( __CLASS__, 'ajax_generate_itinerary' ) );
		add_action( 'wp_ajax_tc_enroll_sequence', array( __CLASS__, 'ajax_enroll_sequence' ) );
		add_action( 'wp_ajax_tc_cancel_sequence', array( __CLASS__, 'ajax_cancel_sequence' ) );
		add_action( 'wp_ajax_tc_ai_suggest_reply', array( __CLASS__, 'ajax_ai_suggest_reply' ) );
		add_action( 'wp_ajax_tc_log_call', array( __CLASS__, 'ajax_log_call' ) );
		add_action( 'wp_ajax_tc_dispatch_mobile_call', array( __CLASS__, 'ajax_dispatch_mobile_call' ) );
	}

	public static function enqueue( $hook ) {
		if ( false !== strpos( (string) $hook, self::PAGE ) ) {
			wp_enqueue_style( 'tc-contacts', TC_AGENTS_URL . 'admin/css/tc-contacts.css', array(), TC_AGENTS_VERSION );
		}
	}

	public static function register_menu() {
		add_submenu_page(
			'tripcosmos-agents',
			__( 'Contacts', 'tripcosmos-agents' ),
			__( 'Contacts', 'tripcosmos-agents' ),
			TC_CRM_Access::CAP_ACCESS,
			self::PAGE,
			array( __CLASS__, 'render' ),
			3
		);
	}

	public static function render() {
		require TC_AGENTS_PATH . 'admin/views/contacts.php';
	}

	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private static function back( array $args ) {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public static function handle_actions() {
		if ( ! TC_CRM_Access::can_access() ) {
			return;
		}

		// CSV export (GET, nonce-protected link). Managers only.
		if ( isset( $_GET['page'], $_GET['tc_export'] ) && self::PAGE === $_GET['page'] ) {
			check_admin_referer( 'tc_contacts_export' );
			if ( ! TC_CRM_Access::can_manage() ) {
				wp_die( esc_html__( 'Only managers can export contacts.', 'tripcosmos-agents' ), 403 );
			}
			self::export_csv();
		}

		if ( empty( $_POST['tc_contacts_action'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE, '_tc_contacts_nonce' );

		$action = sanitize_key( $_POST['tc_contacts_action'] );
		$id     = absint( $_POST['contact_id'] ?? 0 );
		$p      = wp_unslash( $_POST );

		// Permissions: manager-only actions, then per-contact ownership for everything that targets a contact.
		$manager_only = array( 'merge', 'delete_contact', 'sync_all', 'import_fluentcrm', 'import_csv', 'save_team', 'bulk', 'save_billing' );
		if ( in_array( $action, $manager_only, true ) && ! TC_CRM_Access::can_manage() ) {
			wp_die( esc_html__( 'Only managers can do that.', 'tripcosmos-agents' ), 403 );
		}
		if ( 'complete_task' === $action && ! $id ) {
			global $wpdb;
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM {$wpdb->prefix}tc_agent_activities WHERE id = %d", absint( $p['activity_id'] ?? 0 ) ) );
			$p['__from_tasks'] = 1;
		}
		if ( ! in_array( $action, array( 'add_contact', 'sync_all', 'import_fluentcrm', 'import_csv', 'save_team', 'bulk', 'save_billing' ), true ) && ! TC_CRM_Access::can_edit( TC_Contact_Service::get( $id ) ) ) {
			wp_die( esc_html__( 'You can only work on contacts assigned to you.', 'tripcosmos-agents' ), 403 );
		}
		$return_to_leads = 'leads' === ( $p['return'] ?? '' );

		switch ( $action ) {
			case 'claim':
				$c = TC_Contact_Service::get( $id );
				if ( $c && ! (int) $c['owner_user_id'] ) {
					TC_Contact_Service::set_owner( $id, get_current_user_id() );
				}
				self::back( array( 'contact' => $id, 'msg' => 'claimed' ) );
				break;

			case 'assign':
				TC_Contact_Service::set_owner( $id, absint( $p['owner_user_id'] ?? 0 ) );
				self::back( array( 'contact' => $id, 'msg' => 'saved' ) );
				break;

			case 'save_quote':
				$bid = absint( $p['booking_id'] ?? 0 );
				if ( ! self::booking_belongs( $bid, $id ) ) {
					wp_die( 'Invalid booking', 403 );
				}
				$res = TC_Quote_Service::create(
					$bid,
					array(
						'items'          => array_values( (array) ( $p['items'] ?? array() ) ),
						'discount'       => $p['discount'] ?? 0,
						'tax_rate'       => $p['tax_rate'] ?? 0,
						'tax_inclusive'  => ! empty( $p['tax_inclusive'] ),
						'advance_amount' => $p['advance_amount'] ?? '',
						'valid_days'     => $p['valid_days'] ?? 7,
						'notes'          => $p['notes'] ?? '',
					)
				);
				if ( is_wp_error( $res ) ) {
					set_transient( 'tc_contacts_error_' . get_current_user_id(), $res->get_error_message(), 60 );
					self::back( array( 'contact' => $id, 'msg' => 'error' ) );
				}
				self::back( array( 'contact' => $id, 'msg' => 'quote' ) );
				break;

			case 'send_quote':
				$q = TC_Quote_Service::get( absint( $p['quote_id'] ?? 0 ) );
				$c = TC_Contact_Service::get( $id );
				if ( ! $q || (int) $q['contact_id'] !== $id || ! $c || ! empty( $c['dnd'] ) ) {
					wp_die( 'Invalid quote', 403 );
				}
				$b     = TC_Payments::booking( (int) $q['booking_id'] );
				$first = strtok( (string) $c['name'], ' ' ) ?: 'Traveler';
				$text  = sprintf( "Namaste %s 🙏
Your TripCosmos quote for *%s* is ready: %s

View the details, accept and pay the advance securely here:
%s", $first, $b['title'], TC_Quote_Service::money( $q['total'] ), TC_Quote_Service::url( $q ) );
				if ( 'email' === ( $p['channel'] ?? '' ) ) {
					$ok = wp_mail( $c['email'], sprintf( 'Your TripCosmos quote %s', $b['ref'] ), wp_strip_all_tags( str_replace( '*', '', $text ) ) );
					if ( $ok ) {
						TC_Quote_Service::mark_sent( (int) $q['id'], 'email' );
					}
					self::back( array( 'contact' => $id, 'msg' => $ok ? 'quote_sent' : 'error' ) );
				}
				TC_Quote_Service::mark_sent( (int) $q['id'], 'whatsapp' );
				wp_redirect( 'https://wa.me/' . preg_replace( '/\D/', '', (string) $c['phone_e164'] ) . '?text=' . rawurlencode( $text ) ); // phpcs:ignore WordPress.Security.SafeRedirect
				exit;

			case 'create_link':
				$bid = absint( $p['booking_id'] ?? 0 );
				if ( ! self::booking_belongs( $bid, $id ) ) {
					wp_die( 'Invalid booking', 403 );
				}
				$res = TC_Payments::create_cashfree_link( $bid, (float) ( $p['amount'] ?? 0 ) );
				if ( is_wp_error( $res ) ) {
					set_transient( 'tc_contacts_error_' . get_current_user_id(), $res->get_error_message(), 60 );
					self::back( array( 'contact' => $id, 'msg' => 'error' ) );
				}
				self::back( array( 'contact' => $id, 'msg' => 'link' ) );
				break;

			case 'check_payment':
				$pay = TC_Payments::get( absint( $p['payment_id'] ?? 0 ) );
				if ( ! $pay || (int) $pay['contact_id'] !== $id ) {
					wp_die( 'Invalid payment', 403 );
				}
				$st = TC_Payments::reconcile( $pay );
				self::back( array( 'contact' => $id, 'msg' => 'paid' === $st ? 'payment' : 'pay_pending' ) );
				break;

			case 'record_payment':
				$bid = absint( $p['booking_id'] ?? 0 );
				if ( ! self::booking_belongs( $bid, $id ) ) {
					wp_die( 'Invalid booking', 403 );
				}
				$ok = TC_Payments::record_manual( $bid, (float) ( $p['amount'] ?? 0 ), sanitize_key( $p['method'] ?? 'upi' ), sanitize_text_field( $p['reference'] ?? '' ) );
				self::back( array( 'contact' => $id, 'msg' => $ok ? 'payment' : 'error' ) );
				break;

			case 'save_billing':
				self::save_billing( $p );
				self::back( array( 'view' => 'billing', 'msg' => 'saved' ) );
				break;

			case 'save_team':
				self::save_team( $p );
				self::back( array( 'view' => 'team', 'msg' => 'saved' ) );
				break;

			case 'bulk':
				self::back( array_merge( array( 'msg' => 'bulk_done', 'n' => self::bulk( $p ) ), array_filter( array( 'view' => 'list' ) ) ) );
				break;

			case 'add_contact':
				$res = TC_Contact_Service::upsert(
					array(
						'name'           => $p['name'] ?? '',
						'email'          => $p['email'] ?? '',
						'phone'          => $p['phone'] ?? '',
						'owner_user_id'  => TC_CRM_Access::can_manage() && isset( $p['owner_user_id'] ) ? absint( $p['owner_user_id'] ) : get_current_user_id(),
						'source_channel' => $p['source_channel'] ?? 'manual',
						'stage'          => $p['stage'] ?? 'inquiry',
						'tags'           => $p['tags'] ?? '',
						'deal_value'     => $p['deal_value'] ?? 0,
					),
					array( 'activity' => __( 'Added manually by staff', 'tripcosmos-agents' ), 'auto_assign' => false )
				);
				if ( ! $res['id'] ) {
					self::back( array( 'msg' => 'invalid' ) );
				}
				if ( ! TC_CRM_Access::can_edit( $res['contact'] ) ) {
					self::back( array( 'msg' => 'existing_other', 'owner' => rawurlencode( (string) $res['contact']['owner'] ) ) );
				}
				if ( $return_to_leads ) {
					wp_safe_redirect( admin_url( 'admin.php?page=tc-agents-leads&saved=1' . ( 'updated' === $res['status'] ? '&duplicate=' . (int) $res['id'] : '' ) ) );
					exit;
				}
				self::back( array( 'contact' => $res['id'], 'msg' => 'updated' === $res['status'] ? 'existing' : 'created' ) );
				break;

			case 'save_contact':
				TC_Contact_Service::update(
					$id,
					array(
						'name'              => $p['name'] ?? '',
						'email'             => $p['email'] ?? '',
						'phone'             => $p['phone'] ?? '',
						'stage'             => sanitize_key( $p['stage'] ?? 'inquiry' ),
						'lost_reason'       => $p['lost_reason'] ?? '',
						'deal_value'        => $p['deal_value'] ?? 0,
						'score'             => $p['score'] ?? 50,
						'tags'              => $p['tags'] ?? '',
						'next_follow_up_at' => $p['next_follow_up_at'] ?? '',
						'whatsapp_opt_out'  => ! empty( $p['whatsapp_opt_out'] ),
						'dnd'               => ! empty( $p['dnd'] ),
					)
				);
				if ( TC_CRM_Access::can_manage() && isset( $p['owner_user_id'] ) ) {
					TC_Contact_Service::set_owner( $id, absint( $p['owner_user_id'] ) );
				}
				self::back( array( 'contact' => $id, 'msg' => 'saved' ) );
				break;

			case 'set_stage':
				TC_Contact_Service::set_stage( $id, sanitize_key( $p['stage'] ?? '' ), sanitize_text_field( $p['lost_reason'] ?? '' ) );
				if ( $return_to_leads ) {
					wp_safe_redirect( admin_url( 'admin.php?page=tc-agents-leads&saved=1' ) );
					exit;
				}
				self::back( array( 'contact' => $id, 'msg' => 'saved' ) );
				break;

						case 'log_call':
				$dir       = sanitize_key( $p['call_direction'] ?? 'outgoing' );
				$disp      = sanitize_key( $p['call_disposition'] ?? 'connected' );
				$mins      = absint( $p['duration_minutes'] ?? 0 );
				$secs      = absint( $p['duration_seconds'] ?? 0 );
				$tot_dur   = ( $mins * 60 ) + $secs;
				$note      = sanitize_textarea_field( $p['call_note'] ?? '' );
				$rec       = esc_url_raw( $p['recording_url'] ?? '' );
				$dur_label = $tot_dur > 0 ? sprintf( '%dm %02ds', floor( $tot_dur / 60 ), $tot_dur % 60 ) : '0s';
				$c_text    = sprintf( '%s call (%s, %s)%s', ucfirst( $dir ), ucfirst( $disp ), $dur_label, $note ? ' — ' . $note : '' );
				TC_Contact_Service::add_activity( $id, 'call', $c_text, array_filter( array( 'type' => $dir, 'disposition' => $disp, 'duration' => $tot_dur, 'recording_url' => $rec ) ) );
				self::back( array( 'contact' => $id, 'msg' => 'saved' ) );
				break;

			case 'add_note':
				if ( '' !== trim( (string) ( $p['note'] ?? '' ) ) ) {
					TC_Contact_Service::add_activity( $id, 'note', sanitize_textarea_field( $p['note'] ) );
				}
				self::back( array( 'contact' => $id ) );
				break;

			case 'add_task':
				if ( '' !== trim( (string) ( $p['task'] ?? '' ) ) ) {
					TC_Contact_Service::add_task( $id, sanitize_text_field( $p['task'] ), sanitize_text_field( $p['due_at'] ?? '' ) );
				}
				self::back( array( 'contact' => $id ) );
				break;

			case 'complete_task':
				TC_Contact_Service::complete_task( absint( $p['activity_id'] ?? 0 ) );
				self::back( empty( $p['__from_tasks'] ) ? array( 'contact' => $id ) : array( 'view' => 'tasks' ) );
				break;

			case 'save_booking':
				self::save_booking( $id, $p );
				self::back( array( 'contact' => $id, 'msg' => 'booking' ) );
				break;

			case 'merge':
				$other = absint( $p['merge_id'] ?? 0 );
				$ok    = TC_Contact_Service::merge( $id, $other );
				self::back( array( 'contact' => $id, 'msg' => $ok ? 'merged' : 'merge_failed' ) );
				break;

			case 'delete_contact':
				$notes = TC_Contact_Service::delete( $id, ! empty( $p['delete_crm'] ) );
				set_transient( 'tc_contacts_notice_' . get_current_user_id(), $notes, 60 );
				self::back( array( 'msg' => 'deleted' ) );
				break;

			case 'sync_contact':
				$ok = TC_Contact_Service::sync_to_fluentcrm( $id );
				self::back( array( 'contact' => $id, 'msg' => $ok ? 'synced' : 'sync_failed' ) );
				break;

			case 'sync_all':
				self::back( array( 'msg' => 'bulk', 'n' => self::sync_all() ) );
				break;

			case 'import_fluentcrm':
				self::back( array( 'msg' => 'imported', 'n' => self::import_fluentcrm( sanitize_title( $p['tag'] ?? '' ) ) ) );
				break;

			case 'import_csv':
				self::back( array( 'msg' => 'imported', 'n' => self::import_csv() ) );
				break;
		}
	}

	private static function save_booking( $contact_id, array $p ) {
		global $wpdb;
		$t      = $wpdb->prefix . 'tc_agent_bookings';
		$bid    = absint( $p['booking_id'] ?? 0 );
		$status = sanitize_key( $p['status'] ?? 'quoted' );
		$row    = array(
			'contact_id'   => (int) $contact_id,
			'title'        => sanitize_text_field( $p['title'] ?? '' ),
			'start_date'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $p['start_date'] ?? '' ) ) ? $p['start_date'] : null,
			'end_date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $p['end_date'] ?? '' ) ) ? $p['end_date'] : null,
			'pax'          => max( 1, absint( $p['pax'] ?? 1 ) ),
			'vehicle'      => sanitize_text_field( $p['vehicle'] ?? '' ),
			'driver'       => sanitize_text_field( $p['driver'] ?? '' ),
			'quote_amount' => max( 0, (float) ( $p['quote_amount'] ?? 0 ) ),
			'advance_paid' => max( 0, (float) ( $p['advance_paid'] ?? 0 ) ),
			'status'       => isset( self::BOOKING_STATUSES[ $status ] ) ? $status : 'quoted',
			'notes'        => sanitize_textarea_field( $p['notes'] ?? '' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( $bid ) {
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT status, ref FROM $t WHERE id = %d AND contact_id = %d", $bid, $contact_id ), ARRAY_A );
			if ( ! $old ) {
				return;
			}
			$wpdb->update( $t, $row, array( 'id' => $bid ) );
			if ( $old['status'] !== $row['status'] ) {
				TC_Contact_Service::add_activity( $contact_id, 'booking', sprintf( 'Booking %s: %s → %s', $old['ref'], self::BOOKING_STATUSES[ $old['status'] ] ?? $old['status'], self::BOOKING_STATUSES[ $row['status'] ] ) );
			}
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$row['currency']   = 'INR';
			$wpdb->insert( $t, $row );
			$bid = (int) $wpdb->insert_id;
			$ref = 'TC-' . gmdate( 'ymd' ) . '-' . $bid;
			$wpdb->update( $t, array( 'ref' => $ref ), array( 'id' => $bid ) );
			TC_Contact_Service::add_activity( $contact_id, 'booking', sprintf( 'Booking %s created: %s (₹%s)', $ref, $row['title'], number_format_i18n( $row['quote_amount'] ) ) );
		}

		// A confirmed or paid booking means the deal is won; keep the pipeline and deal value in step.
		$contact = TC_Contact_Service::get( $contact_id );
		if ( $contact && in_array( $row['status'], array( 'confirmed', 'advance_paid', 'paid' ), true ) && ! in_array( $contact['stage'], array( 'won', 'completed' ), true ) ) {
			TC_Contact_Service::set_stage( $contact_id, 'won' );
		} elseif ( $contact && 'completed' === $row['status'] && 'completed' !== $contact['stage'] ) {
			TC_Contact_Service::set_stage( $contact_id, 'completed' );
		}
		$total = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(quote_amount) FROM $t WHERE contact_id = %d AND status <> 'cancelled'", $contact_id ) );
		if ( $total > 0 ) {
			$wpdb->update( TC_Contact_Service::table(), array( 'deal_value' => $total ), array( 'id' => (int) $contact_id ) );
		}
	}

	/**
	 * Push contacts that were never synced or failed. Batches of 200 per click.
	 */
	private static function sync_all() {
		global $wpdb;
		$t   = TC_Contact_Service::table();
		$ids = $wpdb->get_col( "SELECT id FROM $t WHERE email <> '' AND email IS NOT NULL AND sync_status IN ('', 'error') ORDER BY id ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n   = 0;
		foreach ( $ids as $id ) {
			if ( TC_Contact_Service::sync_to_fluentcrm( (int) $id ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Import FluentCRM subscribers (optionally only those with a tag). 500 per click.
	 */
	private static function import_fluentcrm( $tag_slug ) {
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return 0;
		}
		global $wpdb;
		$t     = TC_Contact_Service::table();
		$query = \FluentCrm\App\Models\Subscriber::query();
		if ( $tag_slug ) {
			$query->whereHas(
				'tags',
				function ( $q ) use ( $tag_slug ) {
					$q->where( 'slug', $tag_slug );
				}
			);
		}
		$linked = array_map( 'intval', $wpdb->get_col( "SELECT fluentcrm_id FROM $t WHERE fluentcrm_id > 0" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $linked ) {
			$query->whereNotIn( 'id', $linked );
		}
		$n = 0;
		foreach ( $query->limit( 500 )->get() as $sub ) {
			if ( TC_Contact_Service::import_subscriber( $sub ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * CSV columns (header row, any order): name, email, phone, stage, owner, tags, source, deal_value.
	 */
	private static function import_csv() {
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) || (int) $_FILES['csv']['size'] > 2 * MB_IN_BYTES ) {
			return 0;
		}
		$fh = fopen( $_FILES['csv']['tmp_name'], 'r' );
		if ( ! $fh ) {
			return 0;
		}
		$head = fgetcsv( $fh );
		if ( ! $head ) {
			return 0;
		}
		$head = array_map( static function ( $h ) { return sanitize_key( str_replace( ' ', '_', trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ) ); }, $head );
		$n    = 0;
		while ( ( $cells = fgetcsv( $fh ) ) !== false && $n < 5000 ) {
			$r   = array_combine( array_slice( $head, 0, count( $cells ) ), array_slice( $cells, 0, count( $head ) ) ) ?: array();
			$res = TC_Contact_Service::upsert(
				array(
					'name'           => $r['name'] ?? '',
					'email'          => $r['email'] ?? '',
					'phone'          => $r['phone'] ?? ( $r['mobile'] ?? '' ),
					'stage'          => sanitize_key( $r['stage'] ?? '' ),
					'owner'          => $r['owner'] ?? '',
					'tags'           => $r['tags'] ?? '',
					'source_channel' => $r['source'] ?? 'csv_import',
					'deal_value'     => $r['deal_value'] ?? 0,
				),
				array( 'sync' => false, 'auto_assign' => false )
			);
			if ( $res['id'] ) {
				$n++;
			}
		}
		fclose( $fh );
		return $n;
	}

	private static function export_csv() {
		global $wpdb;
		$t = TC_Contact_Service::table();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="tripcosmos-contacts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out  = fopen( 'php://output', 'w' );
		$cols = array( 'id', 'name', 'email', 'phone', 'stage', 'owner', 'tags', 'source_channel', 'score', 'deal_value', 'email_status', 'whatsapp_opt_out', 'dnd', 'utm_source', 'utm_medium', 'utm_campaign', 'lost_reason', 'next_follow_up_at', 'last_contacted_at', 'fluentcrm_id', 'sync_status', 'created_at', 'updated_at' );
		fputcsv( $out, $cols );
		$last = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE id > %d ORDER BY id ASC LIMIT 500", $last ), ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$line = array();
				foreach ( $cols as $c ) {
					$v = (string) ( $r[ $c ] ?? '' );
					// Neutralise spreadsheet formula injection.
					$line[] = preg_match( '/^[=+\-@]/', $v ) && ! preg_match( '/^\+\d+$/', $v ) ? "'" . $v : $v;
				}
				fputcsv( $out, $line );
				$last = (int) $r['id'];
			}
		} while ( count( $rows ) === 500 );
		fclose( $out );
		exit;
	}

	/**
	 * Team tab: assignment mode, SLA, alert switches, and per-member settings.
	 */
	private static function save_team( array $p ) {
		$mode = sanitize_key( $p['assign_mode'] ?? 'none' );
		update_option( 'tc_agents_crm_assign_mode', in_array( $mode, array( 'none', 'round_robin', 'least_loaded' ), true ) ? $mode : 'none' );
		update_option( 'tc_agents_crm_sla_minutes', min( 1440, absint( $p['sla_minutes'] ?? 15 ) ) );
		update_option( 'tc_agents_crm_agents_see_unassigned', empty( $p['agents_see_unassigned'] ) ? '0' : '1' );
		$alerts = array();
		foreach ( array( 'assigned', 'task_due', 'sla', 'reply', 'digest' ) as $k ) {
			$alerts[ $k ] = empty( $p['alerts'][ $k ] ) ? 0 : 1;
		}
		update_option( 'tc_agents_crm_alerts', $alerts );

		foreach ( (array) ( $p['member'] ?? array() ) as $uid => $m ) {
			$uid = absint( $uid );
			$u   = get_userdata( $uid );
			if ( ! $u || ! user_can( $u, TC_CRM_Access::CAP_ACCESS ) ) {
				continue;
			}
			update_user_meta( $uid, 'tc_crm_rotation', empty( $m['rotation'] ) ? '0' : '1' );
			update_user_meta( $uid, 'tc_crm_away', empty( $m['away'] ) ? '0' : '1' );
			update_user_meta( $uid, 'tc_crm_whatsapp', sanitize_text_field( $m['whatsapp'] ?? '' ) );
			if ( user_can( $u, TC_CRM_Access::CAP_MANAGE ) ) {
				update_user_meta( $uid, 'tc_crm_manager_alerts', empty( $m['manager_alerts'] ) ? '0' : '1' );
			}
			// Role switch between agent and manager (never touches administrators).
			$role = sanitize_key( $m['role'] ?? '' );
			if ( in_array( $role, array( TC_CRM_Access::ROLE_AGENT, TC_CRM_Access::ROLE_MANAGER ), true ) && ! in_array( 'administrator', (array) $u->roles, true ) && ! in_array( $role, (array) $u->roles, true ) ) {
				$u->remove_role( TC_CRM_Access::ROLE_AGENT );
				$u->remove_role( TC_CRM_Access::ROLE_MANAGER );
				$u->add_role( $role );
			}
		}
	}

	/**
	 * Bulk actions from the contact list: assign, stage, tag.
	 */
	private static function bulk( array $p ) {
		$ids = array_filter( array_map( 'absint', (array) ( $p['ids'] ?? array() ) ) );
		$op  = sanitize_key( $p['bulk_op'] ?? '' );
		$n   = 0;
		foreach ( array_slice( $ids, 0, 500 ) as $cid ) {
			if ( 'assign' === $op ) {
				$ok = TC_Contact_Service::set_owner( $cid, absint( $p['bulk_owner'] ?? 0 ) );
			} elseif ( 'stage' === $op ) {
				$ok = TC_Contact_Service::set_stage( $cid, sanitize_key( $p['bulk_stage'] ?? '' ), '', false );
			} elseif ( 'tag' === $op && '' !== trim( (string) ( $p['bulk_tag'] ?? '' ) ) ) {
				$c  = TC_Contact_Service::get( $cid );
				$ok = $c && TC_Contact_Service::update( $cid, array( 'tags' => $c['tags'] . ',' . $p['bulk_tag'] ), false );
			} else {
				$ok = false;
			}
			$n += $ok ? 1 : 0;
		}
		return $n;
	}

	private static function booking_belongs( $booking_id, $contact_id ) {
		$b = TC_Payments::booking( (int) $booking_id );
		return $b && (int) $b['contact_id'] === (int) $contact_id;
	}

	/**
	 * Billing tab. Gateway secrets can only be changed by site administrators.
	 */
	private static function save_billing( array $p ) {
		$cur  = (array) get_option( 'tc_agents_billing', array() );
		$next = array(
			'business_name'    => sanitize_text_field( $p['business_name'] ?? '' ),
			'address'          => sanitize_textarea_field( $p['address'] ?? '' ),
			'phone'            => sanitize_text_field( $p['phone'] ?? '' ),
			'email'            => sanitize_email( $p['email'] ?? '' ),
			'gstin'            => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $p['gstin'] ?? '' ) ) ),
			'state'            => sanitize_text_field( $p['state'] ?? '' ),
			'sac'              => preg_replace( '/\D/', '', (string) ( $p['sac'] ?? '' ) ),
			'gst_enabled'      => empty( $p['gst_enabled'] ) ? 0 : 1,
			'gst_rate'         => min( 28, max( 0, (float) ( $p['gst_rate'] ?? 5 ) ) ),
			'gst_inclusive'    => empty( $p['gst_inclusive'] ) ? 0 : 1,
			'gst_split'        => empty( $p['gst_split'] ) ? 0 : 1,
			'advance_type'     => 'percent' === ( $p['advance_type'] ?? '' ) ? 'percent' : 'fixed',
			'advance_value'    => max( 0, (float) ( $p['advance_value'] ?? 1500 ) ),
			'valid_days'       => max( 1, absint( $p['valid_days'] ?? 7 ) ),
			'terms'            => sanitize_textarea_field( $p['terms'] ?? '' ),
			'invoice_prefix'   => preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $p['invoice_prefix'] ?? 'TC' ) ) ?: 'TC',
			'upi_vpa'          => preg_match( '/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', trim( (string) ( $p['upi_vpa'] ?? '' ) ) ) ? trim( $p['upi_vpa'] ) : '',
			'upi_name'         => sanitize_text_field( $p['upi_name'] ?? '' ),
			'receipt_whatsapp' => empty( $p['receipt_whatsapp'] ) ? 0 : 1,
			'cf_use_woo'       => $cur['cf_use_woo'] ?? 1,
			'cf_sandbox'       => $cur['cf_sandbox'] ?? 0,
		);
		if ( current_user_can( 'manage_options' ) ) {
			$next['cf_use_woo'] = empty( $p['cf_use_woo'] ) ? 0 : 1;
			$next['cf_sandbox'] = empty( $p['cf_sandbox'] ) ? 0 : 1;
			if ( ! empty( $p['cf_app_id'] ) ) {
				TC_Agents_Vault::put( 'cashfree_app_id', sanitize_text_field( $p['cf_app_id'] ) );
			}
			if ( ! empty( $p['cf_secret'] ) ) {
				TC_Agents_Vault::put( 'cashfree_secret', sanitize_text_field( $p['cf_secret'] ) );
			}
		}
		update_option( 'tc_agents_billing', $next );
	}

	/**
	 * AJAX endpoint to update contact stage from the Kanban Pipeline Board.
	 */
	public static function ajax_set_stage() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! TC_CRM_Access::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'tripcosmos-agents' ) ), 403 );
		}
		$id    = absint( $_POST['contact_id'] ?? 0 );
		$stage = sanitize_key( $_POST['stage'] ?? '' );
		$c     = TC_Contact_Service::get( $id );
		if ( ! $c ) {
			wp_send_json_error( array( 'message' => __( 'Contact not found', 'tripcosmos-agents' ) ), 404 );
		}
		if ( ! TC_CRM_Access::can_edit( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this contact', 'tripcosmos-agents' ) ), 403 );
		}
		$lost_reason = sanitize_text_field( $_POST['lost_reason'] ?? '' );
		$ok = TC_Contact_Service::set_stage( $id, $stage, $lost_reason );
		if ( $ok ) {
			wp_send_json_success( array(
				'contact_id' => $id,
				'stage'      => $stage,
				'message'    => sprintf( __( 'Moved to %s', 'tripcosmos-agents' ), ucfirst( $stage ) ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update stage', 'tripcosmos-agents' ) ) );
		}
	}

	/**
	 * AJAX endpoint to generate a branded travel itinerary proposal from CRM.
	 */
	public static function ajax_generate_itinerary() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! TC_CRM_Access::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'tripcosmos-agents' ) ), 403 );
		}
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		$c = TC_Contact_Service::get( $contact_id );
		if ( ! $c || ! TC_CRM_Access::can_edit( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized contact access', 'tripcosmos-agents' ) ), 403 );
		}

		if ( ! class_exists( 'TC_Itinerary_Generator' ) ) {
			require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-itinerary-generator.php';
		}

		$destination    = sanitize_text_field( $_POST['destination'] ?? 'Varanasi Spiritual Tour' );
		$duration       = sanitize_text_field( $_POST['duration'] ?? '3 Days / 2 Nights' );
		$vehicle        = sanitize_text_field( $_POST['vehicle'] ?? 'AC Innova Crysta' );
		$hotel_tier     = sanitize_text_field( $_POST['hotel_tier'] ?? '3-Star Deluxe Hotel' );
		$pax            = sanitize_text_field( $_POST['pax'] ?? '2 Adults' );
		$travel_date    = sanitize_text_field( $_POST['travel_date'] ?? 'Upcoming Dates' );
		$total_fare     = sanitize_text_field( $_POST['total_fare'] ?? '₹18,500' );
		$itinerary_text = wp_kses_post( $_POST['itinerary_text'] ?? '' );

		$res = TC_Itinerary_Generator::generate( array(
			'name'           => $c['name'] ?: __( 'Valued Pilgrim', 'tripcosmos-agents' ),
			'phone'          => $c['phone_e164'] ?: $c['phone'],
			'destination'    => $destination,
			'duration'       => $duration,
			'vehicle'        => $vehicle,
			'hotel_tier'     => $hotel_tier,
			'pax'            => $pax,
			'travel_date'    => $travel_date,
			'total_fare'     => $total_fare,
			'itinerary_text' => $itinerary_text,
		) );

		// Log into CRM activity timeline
		$log_msg = sprintf(
			"⚡ Generated Itinerary Proposal: %s (%s) · Total: %s\n🔗 View Proposal: %s",
			$res['summary'],
			$res['vehicle'],
			$res['total_fare'],
			$res['view_url']
		);
		TC_Contact_Service::add_activity( $contact_id, 'itinerary', $log_msg );

		// Auto-update deal value & stage to proposal
		$clean_val = (float) preg_replace( '/[^0-9.]/', '', $total_fare );
		if ( $clean_val > 0 ) {
			global $wpdb;
			$new_stage = in_array( $c['stage'], array( 'inquiry', 'qualified' ), true ) ? 'proposal' : $c['stage'];
			$wpdb->update(
				TC_Contact_Service::table(),
				array( 'deal_value' => $clean_val, 'stage' => $new_stage ),
				array( 'id' => $contact_id )
			);
		}

		wp_send_json_success( array(
			'itinerary_id' => $res['itinerary_id'],
			'view_url'     => $res['view_url'],
			'summary'      => $res['summary'],
			'total_fare'   => $res['total_fare'],
			'message'      => __( 'Itinerary proposal generated successfully!', 'tripcosmos-agents' ),
		) );
	}

	/**
	 * AJAX endpoint to enroll contact into an automated drip sequence.
	 */
	public static function ajax_enroll_sequence() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! TC_CRM_Access::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'tripcosmos-agents' ) ), 403 );
		}
		$contact_id  = absint( $_POST['contact_id'] ?? 0 );
		$sequence_id = absint( $_POST['sequence_id'] ?? 0 );
		$c = TC_Contact_Service::get( $contact_id );
		if ( ! $c || ! TC_CRM_Access::can_edit( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized contact access', 'tripcosmos-agents' ) ), 403 );
		}

		if ( ! class_exists( 'TC_Agent_Sequences' ) ) {
			require_once TC_AGENTS_PATH . 'includes/orchestrator/class-tc-agent-sequences.php';
		}

		global $wpdb;
		$t_seq = $wpdb->prefix . 'tc_agent_sequences';
		$seq   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_seq WHERE id = %d AND is_active = 1", $sequence_id ) );
		if ( ! $seq ) {
			wp_send_json_error( array( 'message' => __( 'Sequence not found or inactive', 'tripcosmos-agents' ) ), 404 );
		}

		$ok = TC_Agent_Sequences::enroll_contact( $contact_id, $seq->trigger_event, $seq->id );
		if ( $ok ) {
			TC_Contact_Service::add_activity(
				$contact_id,
				'system',
				sprintf( __( '⚡ Manually enrolled in automated drip sequence: %s', 'tripcosmos-agents' ), $seq->name )
			);
			wp_send_json_success( array(
				'message'       => sprintf( __( 'Enrolled in "%s"', 'tripcosmos-agents' ), $seq->name ),
				'sequence_name' => $seq->name,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Already active or could not enroll', 'tripcosmos-agents' ) ) );
		}
	}

	/**
	 * AJAX endpoint to cancel an active sequence enrollment.
	 */
	public static function ajax_cancel_sequence() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! TC_CRM_Access::can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'tripcosmos-agents' ) ), 403 );
		}
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		$c = TC_Contact_Service::get( $contact_id );
		if ( ! $c || ! TC_CRM_Access::can_edit( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized contact access', 'tripcosmos-agents' ) ), 403 );
		}

		global $wpdb;
		$t_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		$wpdb->update(
			$t_enroll,
			array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql' ) ),
			array( 'contact_id' => $contact_id, 'status' => 'active' )
		);

		TC_Contact_Service::add_activity(
			$contact_id,
			'system',
			__( '⏹️ Automated follow-up sequence paused / stopped by agent', 'tripcosmos-agents' )
		);

		wp_send_json_success( array( 'message' => __( 'Sequence paused successfully', 'tripcosmos-agents' ) ) );
	}
}
