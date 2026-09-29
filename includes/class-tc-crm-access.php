<?php
/**
 * CRM roles & permissions.
 *
 * Capabilities:
 *   tc_crm_access    use Contacts / Leads, work own + unassigned contacts
 *   tc_crm_view_all  see and edit every contact
 *   tc_crm_manage    assign/reassign, merge, delete, import/export, team settings
 *
 * Roles: TripCosmos Sales Agent (access), TripCosmos Sales Manager (all three). Administrators get all three.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_CRM_Access {

	const CAP_ACCESS   = 'tc_crm_access';
	const CAP_VIEW_ALL = 'tc_crm_view_all';
	const CAP_MANAGE   = 'tc_crm_manage';

	const ROLE_AGENT   = 'tc_sales_agent';
	const ROLE_MANAGER = 'tc_sales_manager';

	/**
	 * Create roles and grant caps. Idempotent; runs on activation / upgrade.
	 */
	public static function install_roles() {
		$base = array( 'read' => true, 'upload_files' => true );
		if ( ! get_role( self::ROLE_AGENT ) ) {
			add_role( self::ROLE_AGENT, __( 'TripCosmos Sales Agent', 'tripcosmos-agents' ), $base );
		}
		if ( ! get_role( self::ROLE_MANAGER ) ) {
			add_role( self::ROLE_MANAGER, __( 'TripCosmos Sales Manager', 'tripcosmos-agents' ), $base );
		}
		get_role( self::ROLE_AGENT )->add_cap( self::CAP_ACCESS );
		foreach ( array( self::ROLE_MANAGER, 'administrator' ) as $r ) {
			$role = get_role( $r );
			if ( $role ) {
				$role->add_cap( self::CAP_ACCESS );
				$role->add_cap( self::CAP_VIEW_ALL );
				$role->add_cap( self::CAP_MANAGE );
			}
		}
	}

	public static function can_access() {
		return current_user_can( self::CAP_ACCESS ) || current_user_can( 'manage_options' );
	}

	public static function can_view_all() {
		return current_user_can( self::CAP_VIEW_ALL ) || current_user_can( 'manage_options' );
	}

	public static function can_manage() {
		return current_user_can( self::CAP_MANAGE ) || current_user_can( 'manage_options' );
	}

	/**
	 * Agents may also see (and claim) unassigned contacts unless a manager turns that off.
	 */
	public static function agents_see_unassigned() {
		return '0' !== (string) get_option( 'tc_agents_crm_agents_see_unassigned', '1' );
	}

	/**
	 * SQL condition restricting contacts to what the current user may see.
	 *
	 * @param string $alias Table alias prefix, e.g. 'c.'.
	 */
	public static function scope_sql( $alias = '' ) {
		global $wpdb;
		if ( self::can_view_all() ) {
			return '1=1';
		}
		$me = get_current_user_id();
		return self::agents_see_unassigned()
			? $wpdb->prepare( "({$alias}owner_user_id = %d OR {$alias}owner_user_id = 0)", $me )
			: $wpdb->prepare( "{$alias}owner_user_id = %d", $me );
	}

	/**
	 * @param array|null $contact Contact row.
	 */
	public static function can_edit( $contact ) {
		if ( ! $contact || ! self::can_access() ) {
			return false;
		}
		if ( self::can_view_all() ) {
			return true;
		}
		$owner = (int) ( $contact['owner_user_id'] ?? 0 );
		return $owner === get_current_user_id() || ( 0 === $owner && self::agents_see_unassigned() );
	}

	/**
	 * Users who can work leads (for owner pickers, rotation and alerts).
	 *
	 * @return WP_User[]
	 */
	public static function team( $cap = self::CAP_ACCESS ) {
		return get_users(
			array(
				'capability' => $cap,
				'orderby'    => 'display_name',
				'fields'     => 'all',
			)
		);
	}

	/**
	 * Whether a user takes part in automatic lead assignment. Sales agents do by default; managers and
	 * admins only when switched on in the Team tab.
	 */
	public static function in_rotation( WP_User $u ) {
		$flag = get_user_meta( $u->ID, 'tc_crm_rotation', true );
		if ( '' !== $flag ) {
			return '1' === $flag;
		}
		return in_array( self::ROLE_AGENT, (array) $u->roles, true );
	}

	public static function is_away( $user_id ) {
		return '1' === (string) get_user_meta( (int) $user_id, 'tc_crm_away', true );
	}
}
