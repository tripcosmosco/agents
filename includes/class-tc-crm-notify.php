<?php
/**
 * Staff alerts: lead assigned, follow-up due, new lead not answered in time (SLA), customer replied,
 * and a morning digest. Delivered by email and, when the staff member has a WhatsApp number, WhatsApp.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_CRM_Notify {

	const CRON_TICK   = 'tc_crm_alerts_tick';
	const CRON_DIGEST = 'tc_crm_morning_digest';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::CRON_TICK, array( __CLASS__, 'tick' ) );
		add_action( self::CRON_DIGEST, array( __CLASS__, 'morning_digest' ) );
		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_event( time() + 120, 'tc_crm_5min', self::CRON_TICK );
		}
		if ( ! wp_next_scheduled( self::CRON_DIGEST ) ) {
			// Next 09:00 in the site's timezone.
			$tz   = wp_timezone();
			$next = new DateTime( 'today 09:00', $tz );
			if ( $next->getTimestamp() <= time() ) {
				$next->modify( '+1 day' );
			}
			wp_schedule_event( $next->getTimestamp(), 'daily', self::CRON_DIGEST );
		}
	}

	public static function schedules( $s ) {
		$s['tc_crm_5min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes (TripCosmos CRM alerts)' );
		return $s;
	}

	public static function enabled( $event ) {
		$on = (array) get_option( 'tc_agents_crm_alerts', array( 'assigned' => 1, 'task_due' => 1, 'sla' => 1, 'reply' => 1, 'digest' => 1 ) );
		return ! empty( $on[ $event ] );
	}

	/**
	 * Send one alert to one staff member.
	 */
	public static function send( $user_id, $subject, $text, $contact_id = 0 ) {
		$u = get_userdata( (int) $user_id );
		if ( ! $u ) {
			return;
		}
		$link = $contact_id ? admin_url( 'admin.php?page=tc-agents-contacts&contact=' . (int) $contact_id ) : admin_url( 'admin.php?page=tc-agents-contacts' );
		$body = $text . "\n\n" . $link;

		if ( '0' !== (string) get_user_meta( $u->ID, 'tc_crm_alert_email', true ) && is_email( $u->user_email ) ) {
			wp_mail( $u->user_email, '[TripCosmos] ' . $subject, $body );
		}
		$wa = preg_replace( '/\D/', '', (string) get_user_meta( $u->ID, 'tc_crm_whatsapp', true ) );
		if ( $wa && ! TC_CRM_Access::is_away( $u->ID ) && class_exists( 'TC_Integration_WhatsApp' ) ) {
			TC_Integration_WhatsApp::send_message( $wa, "🔔 *{$subject}*\n{$text}\n{$link}" );
		}
	}

	/**
	 * Alert every manager (e.g. unassigned lead, SLA breach).
	 */
	public static function send_managers( $subject, $text, $contact_id = 0, $except = 0 ) {
		foreach ( TC_CRM_Access::team( TC_CRM_Access::CAP_MANAGE ) as $m ) {
			$pref  = (string) get_user_meta( $m->ID, 'tc_crm_manager_alerts', true );
			$wants = '' === $pref ? in_array( TC_CRM_Access::ROLE_MANAGER, (array) $m->roles, true ) : '1' === $pref;
			if ( (int) $m->ID !== (int) $except && $wants ) {
				self::send( $m->ID, $subject, $text, $contact_id );
			}
		}
	}

	public static function contact_line( array $c ) {
		$meta = json_decode( (string) ( $c['meta_data'] ?? '' ), true ) ?: array();
		return trim( ( $c['name'] ?: 'Traveler' ) . ' · ' . ( $c['phone'] ?: $c['email'] ) . ( ! empty( $meta['destination'] ) ? ' · ' . $meta['destination'] : '' ) );
	}

	public static function on_assigned( $contact_id, $user_id, $by_user_id ) {
		if ( ! self::enabled( 'assigned' ) || (int) $user_id === (int) $by_user_id ) {
			return;
		}
		$c = TC_Contact_Service::get( $contact_id );
		if ( $c ) {
			self::send( $user_id, __( 'New lead assigned to you', 'tripcosmos-agents' ), self::contact_line( $c ), $contact_id );
		}
	}

	/**
	 * A contact wrote in (WhatsApp). Tell the owner, at most once per 30 minutes per contact.
	 */
	public static function on_customer_reply( $contact_id, $text ) {
		if ( ! self::enabled( 'reply' ) ) {
			return;
		}
		$c = TC_Contact_Service::get( $contact_id );
		if ( ! $c || ! (int) $c['owner_user_id'] ) {
			return;
		}
		$key = 'tc_crm_reply_' . (int) $contact_id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
		self::send( (int) $c['owner_user_id'], sprintf( __( '%s replied on WhatsApp', 'tripcosmos-agents' ), $c['name'] ?: 'Traveler' ), '“' . wp_trim_words( wp_strip_all_tags( (string) $text ), 30 ) . '”', $contact_id );
	}

	/**
	 * Runs every 5 minutes: due follow-ups and first-response SLA.
	 */
	public static function tick() {
		global $wpdb;
		$t_act = $wpdb->prefix . 'tc_agent_activities';
		$t_c   = TC_Contact_Service::table();
		$now   = current_time( 'mysql' );

		// 1. Follow-ups that just came due.
		if ( self::enabled( 'task_due' ) ) {
			$due = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.id, a.content, a.contact_id, c.owner_user_id, c.name, c.phone, c.email, c.meta_data FROM $t_act a INNER JOIN $t_c c ON c.id = a.contact_id
					 WHERE a.type = 'task' AND a.done_at IS NULL AND a.notified_at IS NULL AND a.due_at <= %s LIMIT 50",
					$now
				),
				ARRAY_A
			) ?: array();
			foreach ( $due as $d ) {
				$wpdb->update( $t_act, array( 'notified_at' => $now ), array( 'id' => (int) $d['id'] ) );
				$text = $d['content'] . "\n" . self::contact_line( $d );
				if ( (int) $d['owner_user_id'] ) {
					self::send( (int) $d['owner_user_id'], __( 'Follow-up due', 'tripcosmos-agents' ), $text, (int) $d['contact_id'] );
				} else {
					self::send_managers( __( 'Follow-up due (unassigned lead)', 'tripcosmos-agents' ), $text, (int) $d['contact_id'] );
				}
			}
		}

		// 2. First-response SLA: new leads with no human action within N minutes (checked for 24h).
		$sla = (int) get_option( 'tc_agents_crm_sla_minutes', 15 );
		if ( self::enabled( 'sla' ) && $sla > 0 ) {
			$late = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.* FROM $t_c c
					 WHERE c.created_at <= %s AND c.created_at >= %s AND c.stage NOT IN ('won','completed','lost')
					   AND c.meta_data NOT LIKE %s
					   AND NOT EXISTS (SELECT 1 FROM $t_act a WHERE a.contact_id = c.id AND a.user_id > 0)
					 LIMIT 20",
					gmdate( 'Y-m-d H:i:s', strtotime( $now ) - $sla * MINUTE_IN_SECONDS ),
					gmdate( 'Y-m-d H:i:s', strtotime( $now ) - DAY_IN_SECONDS ),
					'%"sla_alerted"%'
				),
				ARRAY_A
			) ?: array();
			foreach ( $late as $c ) {
				$meta                = json_decode( (string) $c['meta_data'], true ) ?: array();
				$meta['sla_alerted'] = $now;
				$wpdb->update( $t_c, array( 'meta_data' => wp_json_encode( $meta ) ), array( 'id' => (int) $c['id'] ) );
				$subject = sprintf( __( 'Lead waiting %d+ minutes with no response', 'tripcosmos-agents' ), $sla );
				if ( (int) $c['owner_user_id'] ) {
					self::send( (int) $c['owner_user_id'], $subject, self::contact_line( $c ), (int) $c['id'] );
				}
				self::send_managers( $subject, self::contact_line( $c ) . ( (int) $c['owner_user_id'] ? ' · ' . sprintf( __( 'Owner: %s', 'tripcosmos-agents' ), $c['owner'] ) : ' · ' . __( 'Unassigned', 'tripcosmos-agents' ) ), (int) $c['id'], (int) $c['owner_user_id'] );
			}
		}
	}

	/**
	 * 09:00 digest to each team member: today's follow-ups, overdue, open leads.
	 */
	public static function morning_digest() {
		if ( ! self::enabled( 'digest' ) ) {
			return;
		}
		global $wpdb;
		$t_act = $wpdb->prefix . 'tc_agent_activities';
		$t_c   = TC_Contact_Service::table();
		$today = current_time( 'Y-m-d' );
		foreach ( TC_CRM_Access::team() as $u ) {
			if ( TC_CRM_Access::is_away( $u->ID ) ) {
				continue;
			}
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						(SELECT COUNT(*) FROM $t_act a INNER JOIN $t_c c ON c.id = a.contact_id WHERE c.owner_user_id = %d AND a.type = 'task' AND a.done_at IS NULL AND DATE(a.due_at) = %s) AS today,
						(SELECT COUNT(*) FROM $t_act a INNER JOIN $t_c c ON c.id = a.contact_id WHERE c.owner_user_id = %d AND a.type = 'task' AND a.done_at IS NULL AND DATE(a.due_at) < %s) AS overdue,
						(SELECT COUNT(*) FROM $t_c WHERE owner_user_id = %d AND stage NOT IN ('won','completed','lost')) AS open_leads",
					$u->ID,
					$today,
					$u->ID,
					$today,
					$u->ID
				),
				ARRAY_A
			);
			if ( ! $row || ( 0 === (int) $row['today'] && 0 === (int) $row['overdue'] ) ) {
				continue;
			}
			self::send(
				$u->ID,
				__( 'Your day in TripCosmos CRM', 'tripcosmos-agents' ),
				sprintf( __( "Follow-ups today: %1\$d\nOverdue: %2\$d\nOpen leads: %3\$d", 'tripcosmos-agents' ), $row['today'], $row['overdue'], $row['open_leads'] )
			);
		}
	}
}
