<?php
/**
 * Dynamic Fleet, Boat, and Darshan Inventory Ledger & Scarcity Engine.
 *
 * Tracks date-specific inventory for private Bajra boats, outstation cabs,
 * luxury tents, and VIP darshan passes. Provides 15-minute temporary holds
 * and live scarcity ticker telemetry for web and mobile.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Inventory_Service {

	public static function table_inventory() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_inventory';
	}

	public static function table_holds() {
		global $wpdb;
		return $wpdb->prefix . 'tc_agent_inventory_holds';
	}

	/**
	 * Create database tables if they do not exist.
	 */
	public static function maybe_create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$t_inv = self::table_inventory();
		$sql_inv = "CREATE TABLE $t_inv (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(50) NOT NULL DEFAULT 'bajra_boat',
			item_name varchar(191) NOT NULL,
			service_date date NOT NULL,
			total_capacity int(11) NOT NULL DEFAULT 1,
			allocated_count int(11) NOT NULL DEFAULT 0,
			hold_count int(11) NOT NULL DEFAULT 0,
			base_price decimal(10,2) NOT NULL DEFAULT 0.00,
			surge_price decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(30) NOT NULL DEFAULT 'available',
			meta_data longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type_date (item_type, service_date),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_inv );

		$t_holds = self::table_holds();
		$sql_holds = "CREATE TABLE $t_holds (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			inventory_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned DEFAULT 0,
			session_id varchar(100) NOT NULL DEFAULT '',
			slots int(11) NOT NULL DEFAULT 1,
			expires_at datetime NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY inv_status (inventory_id, status),
			KEY expires_at (expires_at)
		) $charset_collate;";
		dbDelta( $sql_holds );

		self::seed_default_inventory();
	}

	/**
	 * Seed initial high-season inventory (Dev Deepawali 2026, Magh Mela, Outstation Innova).
	 */
	public static function seed_default_inventory() {
		global $wpdb;
		$t = self::table_inventory();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
		if ( $count > 0 ) {
			return;
		}

		$now = current_time( 'mysql' );
		$seeds = array(
			array(
				'item_type'       => 'bajra_boat',
				'item_name'       => 'Dev Deepawali 2026 Private Bajra Charter (80 Pax Deck)',
				'service_date'    => '2026-11-24',
				'total_capacity'  => 5,
				'allocated_count' => 3,
				'hold_count'      => 1,
				'base_price'      => 45000.00,
				'surge_price'     => 65000.00,
				'status'          => 'filling_fast',
				'meta_data'       => wp_json_encode( array( 'remaining' => 1, 'highlight' => 'Zero Line Dashashwamedh Aarti View', 'urgency' => 'Only 1 Bajra Remaining' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array(
				'item_type'       => 'boat_ride',
				'item_name'       => 'Morning Subah-e-Banaras Sunrise Wooden Boat (Assi to Manikarnika)',
				'service_date'    => current_time( 'Y-m-d' ),
				'total_capacity'  => 12,
				'allocated_count' => 8,
				'hold_count'      => 1,
				'base_price'      => 1800.00,
				'surge_price'     => 2500.00,
				'status'          => 'available',
				'meta_data'       => wp_json_encode( array( 'remaining' => 3, 'highlight' => 'Subah-e-Banaras Cultural Rituals' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array(
				'item_type'       => 'cab_fleet',
				'item_name'       => 'Toyota Innova Crysta AC Outstation (Varanasi to Ayodhya Purvanchal)',
				'service_date'    => current_time( 'Y-m-d' ),
				'total_capacity'  => 8,
				'allocated_count' => 5,
				'hold_count'      => 1,
				'base_price'      => 6500.00,
				'surge_price'     => 7500.00,
				'status'          => 'available',
				'meta_data'       => wp_json_encode( array( 'remaining' => 2, 'highlight' => 'Uniformed Chauffeur & Tolls Included' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array(
				'item_type'       => 'darshan_pass',
				'item_name'       => 'Kashi Vishwanath Sugam Darshan & Rudrabhishek Pass Assistance',
				'service_date'    => current_time( 'Y-m-d' ),
				'total_capacity'  => 25,
				'allocated_count' => 19,
				'hold_count'      => 2,
				'base_price'      => 1500.00,
				'surge_price'     => 2100.00,
				'status'          => 'filling_fast',
				'meta_data'       => wp_json_encode( array( 'remaining' => 4, 'highlight' => 'Zero Queue Line Entrance via Gate 4' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array(
				'item_type'       => 'magh_mela_tent',
				'item_name'       => 'Prayagraj Magh Mela 2027 Luxury Swiss Cottages (Triveni Sangam)',
				'service_date'    => '2027-01-14',
				'total_capacity'  => 15,
				'allocated_count' => 9,
				'hold_count'      => 2,
				'base_price'      => 8500.00,
				'surge_price'     => 12000.00,
				'status'          => 'available',
				'meta_data'       => wp_json_encode( array( 'remaining' => 4, 'highlight' => 'Attached Bath, Satvik Meals & Boat Transfers' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
		);

		foreach ( $seeds as $seed ) {
			$wpdb->insert( $t, $seed );
		}
	}

	/**
	 * Release all expired holds.
	 */
	public static function release_expired_holds() {
		global $wpdb;
		$t_holds = self::table_holds();
		$t_inv   = self::table_inventory();
		$now     = current_time( 'mysql' );

		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, inventory_id, slots FROM $t_holds WHERE status = 'active' AND expires_at <= %s",
				$now
			),
			ARRAY_A
		) ?: array();

		foreach ( $expired as $h ) {
			$wpdb->update( $t_holds, array( 'status' => 'expired' ), array( 'id' => (int) $h['id'] ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $t_inv SET hold_count = GREATEST(0, hold_count - %d), updated_at = %s WHERE id = %d",
					(int) $h['slots'],
					$now,
					(int) $h['inventory_id']
				)
			);
		}

		return count( $expired );
	}

	/**
	 * Create a 15-minute temporary inventory hold.
	 */
	public static function create_hold( $inventory_id, $contact_id = 0, $session_id = '', $slots = 1, $duration_minutes = 15 ) {
		global $wpdb;
		self::release_expired_holds();

		$t_inv   = self::table_inventory();
		$t_holds = self::table_holds();
		$now     = current_time( 'mysql' );
		$slots   = max( 1, (int) $slots );

		$inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_inv WHERE id = %d", (int) $inventory_id ), ARRAY_A );
		if ( ! $inv ) {
			return new WP_Error( 'not_found', 'Inventory item not found' );
		}

		$avail = (int) $inv['total_capacity'] - ( (int) $inv['allocated_count'] + (int) $inv['hold_count'] );
		if ( $avail < $slots ) {
			return new WP_Error( 'sold_out', sprintf( 'Only %d slots available right now', max( 0, $avail ) ) );
		}

		$expires_at = date( 'Y-m-d H:i:s', strtotime( $now ) + ( (int) $duration_minutes * 60 ) );

		$wpdb->insert(
			$t_holds,
			array(
				'inventory_id' => (int) $inventory_id,
				'contact_id'   => (int) $contact_id,
				'session_id'   => sanitize_text_field( $session_id ),
				'slots'        => $slots,
				'expires_at'   => $expires_at,
				'status'       => 'active',
				'created_at'   => $now,
			)
		);
		$hold_id = (int) $wpdb->insert_id;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $t_inv SET hold_count = hold_count + %d, updated_at = %s WHERE id = %d",
				$slots,
				$now,
				(int) $inventory_id
			)
		);

		return array(
			'hold_id'    => $hold_id,
			'item_name'  => $inv['item_name'],
			'slots'      => $slots,
			'expires_at' => $expires_at,
			'seconds'    => (int) $duration_minutes * 60,
		);
	}

	/**
	 * Convert hold to permanent booking allocation.
	 */
	public static function convert_hold( $hold_id ) {
		global $wpdb;
		$t_holds = self::table_holds();
		$t_inv   = self::table_inventory();
		$now     = current_time( 'mysql' );

		$hold = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_holds WHERE id = %d AND status = 'active'", (int) $hold_id ), ARRAY_A );
		if ( ! $hold ) {
			return false;
		}

		$wpdb->update( $t_holds, array( 'status' => 'converted' ), array( 'id' => (int) $hold_id ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $t_inv SET hold_count = GREATEST(0, hold_count - %d), allocated_count = allocated_count + %d, updated_at = %s WHERE id = %d",
				(int) $hold['slots'],
				(int) $hold['slots'],
				$now,
				(int) $hold['inventory_id']
			)
		);

		return true;
	}

	/**
	 * Get live scarcity ticker items for website and chat widgets.
	 */
	public static function get_scarcity_feed() {
		global $wpdb;
		self::release_expired_holds();

		$t = self::table_inventory();
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY status = 'filling_fast' DESC, id ASC LIMIT 10", ARRAY_A ) ?: array();

		$feed = array();
		foreach ( $rows as $r ) {
			$avail = max( 0, (int) $r['total_capacity'] - ( (int) $r['allocated_count'] + (int) $r['hold_count'] ) );
			$feed[] = array(
				'id'          => (int) $r['id'],
				'item_type'   => $r['item_type'],
				'item_name'   => $r['item_name'],
				'date'        => $r['service_date'],
				'available'   => $avail,
				'price'       => (float) $r['base_price'],
				'surge_price' => (float) $r['surge_price'],
				'status'      => $avail <= 2 ? 'filling_fast' : ( $avail === 0 ? 'sold_out' : 'available' ),
				'badge'       => $avail <= 2 ? ( 1 === $avail ? '🔥 Last 1 Slot Left!' : "⚡ Only {$avail} Slots Left!" ) : '✅ Available',
			);
		}

		return $feed;
	}
}
