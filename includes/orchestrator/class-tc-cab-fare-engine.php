<?php
/**
 * Outstation Cab & Circuit Fare Engine for TripCosmos.
 * Calculates exact realistic cab tariffs, distances, and toll allowances across UP & Bihar spiritual circuits.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Cab_Fare_Engine {

	/**
	 * Canonical route distance matrix (one-way km).
	 */
	const DISTANCES = array(
		'varanasi_ayodhya'    => 220,
		'varanasi_prayagraj'  => 125,
		'varanasi_bodhgaya'   => 250,
		'varanasi_chitrakoot' => 260,
		'varanasi_lucknow'    => 310,
		'varanasi_gorakhpur'  => 200,
		'ayodhya_prayagraj'   => 165,
		'ayodhya_lucknow'     => 135,
		'prayagraj_chitrakoot'=> 130,
		'varanasi_airport'    => 28,
		'ayodhya_airport'     => 15,
	);

	/**
	 * Available vehicle tariffs (INR per km and daily minimums).
	 */
	const FLEET = array(
		'sedan' => array(
			'name'       => 'AC Sedan (Swift Dzire / Toyota Etios)',
			'rate_per_km'=> 12.00,
			'capacity'   => '4 Passengers + Driver',
			'luggage'    => '2 Large + 2 Small Bags',
			'daily_min'  => 250,
			'driver_da'  => 400.00,
			'local_8h80k'=> 1800.00,
			'airport_vns'=> 1100.00,
		),
		'ertiga' => array(
			'name'       => 'Maruti Ertiga / XL6 (Budget MUV)',
			'rate_per_km'=> 15.00,
			'capacity'   => '6 Passengers + Driver',
			'luggage'    => '3 Medium Bags',
			'daily_min'  => 250,
			'driver_da'  => 400.00,
			'local_8h80k'=> 2400.00,
			'airport_vns'=> 1500.00,
		),
		'innova' => array(
			'name'       => 'Toyota Innova Crysta (Luxury SUV)',
			'rate_per_km'=> 19.00,
			'capacity'   => '6-7 Passengers + Driver',
			'luggage'    => '4 Large + 2 Small Bags',
			'daily_min'  => 250,
			'driver_da'  => 450.00,
			'local_8h80k'=> 3000.00,
			'airport_vns'=> 2000.00,
		),
		'tempo_12' => array(
			'name'       => 'Force Tempo Traveller (12 Seater)',
			'rate_per_km'=> 25.00,
			'capacity'   => '12 Passengers + Chauffeur',
			'luggage'    => 'Ample dedicated luggage boot',
			'daily_min'  => 250,
			'driver_da'  => 500.00,
			'local_8h80k'=> 4500.00,
			'airport_vns'=> 3000.00,
		),
		'tempo_17' => array(
			'name'       => 'Force Tempo Traveller (17 Seater / 20 Seater)',
			'rate_per_km'=> 28.00,
			'capacity'   => '17-20 Passengers + Chauffeur',
			'luggage'    => 'Heavy luggage carrier on roof',
			'daily_min'  => 300,
			'driver_da'  => 600.00,
			'local_8h80k'=> 5500.00,
			'airport_vns'=> 3800.00,
		),
		'tempo_26' => array(
			'name'       => 'Mahindra / Force Luxury Mini-Bus (26 Seater)',
			'rate_per_km'=> 35.00,
			'capacity'   => '26 Passengers + Chauffeur',
			'luggage'    => 'Full underbelly and roof carrier',
			'daily_min'  => 300,
			'driver_da'  => 700.00,
			'local_8h80k'=> 7000.00,
			'airport_vns'=> 5000.00,
		),
	);

	/**
	 * Calculate accurate cab fare for any circuit route.
	 *
	 * @param string $origin
	 * @param string $destination
	 * @param string $vehicle_type sedan|ertiga|innova|tempo_12|tempo_17|tempo_26
	 * @param int    $days
	 * @param bool   $is_round_trip
	 * @return array
	 */
	public static function calculate( $origin, $destination, $vehicle_type = 'innova', $days = 1, $is_round_trip = true ) {
		$origin_clean = strtolower( trim( $origin ) );
		$dest_clean   = strtolower( trim( $destination ) );
		$days         = max( 1, (int) $days );
		$vehicle_key  = isset( self::FLEET[ $vehicle_type ] ) ? $vehicle_type : 'innova';
		$fleet_info   = self::FLEET[ $vehicle_key ];

		// 1. Check for airport transfer
		if ( false !== strpos( $dest_clean, 'airport' ) || false !== strpos( $origin_clean, 'airport' ) ) {
			return array(
				'route'         => 'Airport Transfer',
				'vehicle'       => $fleet_info['name'],
				'capacity'      => $fleet_info['capacity'],
				'total_estimate'=> $fleet_info['airport_vns'],
				'breakdown'     => array(
					'base_fare'   => $fleet_info['airport_vns'],
					'driver_da'   => 0,
					'toll_parking'=> 'Included',
				),
				'inclusions'    => 'Chilled AC, Chauffeur with Name Placard at Arrival Terminal, Flight Monitoring, Tolls & Parking.',
				'notes'         => 'Doorstep drop to your hotel or ghat in Varanasi.',
			);
		}

		// 2. Check for Varanasi Local 8h/80km
		if ( false !== strpos( $dest_clean, 'local' ) || false !== strpos( $dest_clean, 'sightseeing' ) || ( false !== strpos( $dest_clean, 'darshan' ) && empty( $destination ) ) ) {
			$base = $fleet_info['local_8h80k'] * $days;
			return array(
				'route'         => sprintf( 'Varanasi Local Sightseeing & Temple Darshan (%d Day%s - 8h/80km)', $days, $days > 1 ? 's' : '' ),
				'vehicle'       => $fleet_info['name'],
				'capacity'      => $fleet_info['capacity'],
				'total_estimate'=> $base,
				'breakdown'     => array(
					'base_fare'   => $base,
					'driver_da'   => 0,
					'extra_km'    => '₹' . $fleet_info['rate_per_km'] . '/km beyond 80km',
					'extra_hour'  => '₹150/hour beyond 8 hours',
				),
				'inclusions'    => 'Fuel, Uniformed Chauffeur, Sightseeing (Kashi Vishwanath Corridor, Kaal Bhairav, Sankat Mochan, Sarnath, Dashashwamedh Ghat for Ganga Aarti).',
				'notes'         => 'AC available throughout. Parking at ghats as per actuals.',
			);
		}

		// 3. Outstation Distance Lookup
		$est_one_way_km = self::lookup_distance( $origin_clean, $dest_clean );
		$total_km       = $is_round_trip ? ( $est_one_way_km * 2 ) : $est_one_way_km;

		// Apply minimum daily chargeable kilometers
		$min_chargeable_km = $fleet_info['daily_min'] * $days;
		$billable_km       = max( $total_km, $min_chargeable_km );

		$km_charge   = $billable_km * $fleet_info['rate_per_km'];
		$da_charge   = $fleet_info['driver_da'] * $days;
		$toll_est    = self::estimate_tolls( $origin_clean, $dest_clean, $is_round_trip );
		$total_price = $km_charge + $da_charge;

		return array(
			'route'             => sprintf( '%s to %s (%s)', ucwords( $origin ), ucwords( $destination ), $is_round_trip ? 'Round Trip' : 'One Way' ),
			'vehicle'           => $fleet_info['name'],
			'capacity'          => $fleet_info['capacity'],
			'luggage'           => $fleet_info['luggage'],
			'distance_km'       => $total_km,
			'billable_km'       => $billable_km,
			'days'              => $days,
			'total_estimate'    => $total_price,
			'breakdown'         => array(
				'km_rate'         => '₹' . $fleet_info['rate_per_km'] . '/km',
				'km_charge'       => $km_charge,
				'driver_allowance'=> $da_charge . ' (₹' . $fleet_info['driver_da'] . '/day)',
				'estimated_tolls' => '₹' . $toll_est . ' (approximate expressway tolls)',
			),
			'inclusions'        => 'Dedicated AC Vehicle, Chauffeur Allowance, Fuel, Multi-Day Itinerary Flexibility.',
			'exclusions'        => 'Toll Tax, Parking Slips, State Permit (if entering Bihar for Bodhgaya).',
			'booking_note'      => 'Instant booking available with ₹1,000 token advance via TripCosmos Varanasi desk.',
		);
	}

	/**
	 * Distance lookup between circuit cities.
	 */
	private static function lookup_distance( $origin, $dest ) {
		$combined = $origin . '_' . $dest;
		$reverse  = $dest . '_' . $origin;

		foreach ( self::DISTANCES as $key => $km ) {
			if ( false !== strpos( $combined, $key ) || false !== strpos( $reverse, $key ) ) {
				return $km;
			}
		}

		// Circuit combinations
		if ( ( false !== strpos( $origin, 'varanasi' ) || false !== strpos( $dest, 'varanasi' ) ) && ( false !== strpos( $origin, 'ayodhya' ) || false !== strpos( $dest, 'ayodhya' ) ) ) {
			return 220;
		}
		if ( ( false !== strpos( $origin, 'varanasi' ) || false !== strpos( $dest, 'varanasi' ) ) && ( false !== strpos( $origin, 'prayag' ) || false !== strpos( $dest, 'prayag' ) || false !== strpos( $origin, 'allahabad' ) || false !== strpos( $dest, 'allahabad' ) ) ) {
			return 125;
		}
		if ( ( false !== strpos( $origin, 'varanasi' ) || false !== strpos( $dest, 'varanasi' ) ) && ( false !== strpos( $origin, 'bodhgaya' ) || false !== strpos( $dest, 'bodhgaya' ) || false !== strpos( $origin, 'gaya' ) || false !== strpos( $dest, 'gaya' ) ) ) {
			return 250;
		}
		if ( ( false !== strpos( $origin, 'varanasi' ) || false !== strpos( $dest, 'varanasi' ) ) && ( false !== strpos( $origin, 'chitrakoot' ) || false !== strpos( $dest, 'chitrakoot' ) ) ) {
			return 260;
		}
		if ( ( false !== strpos( $origin, 'varanasi' ) || false !== strpos( $dest, 'varanasi' ) ) && ( false !== strpos( $origin, 'lucknow' ) || false !== strpos( $dest, 'lucknow' ) ) ) {
			return 310;
		}

		// Default fallback circuit distance
		return 200;
	}

	/**
	 * Estimate expressway & highway tolls.
	 */
	private static function estimate_tolls( $origin, $dest, $round_trip ) {
		$factor = $round_trip ? 2 : 1;
		if ( false !== strpos( $origin, 'ayodhya' ) || false !== strpos( $dest, 'ayodhya' ) ) {
			return 250 * $factor;
		}
		if ( false !== strpos( $origin, 'prayag' ) || false !== strpos( $dest, 'prayag' ) ) {
			return 140 * $factor;
		}
		if ( false !== strpos( $origin, 'lucknow' ) || false !== strpos( $dest, 'lucknow' ) ) {
			return 400 * $factor;
		}
		return 200 * $factor;
	}
}
