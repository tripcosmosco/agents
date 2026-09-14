<?php
/**
 * Deal Math & Revenue Protection Engine.
 * Inspired by VMAI Deal Math architecture.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Deal_Math {

	/**
	 * Default maximum permitted discount percentage (margin protection).
	 */
	const DEFAULT_DISCOUNT_CEILING = 10;

	/**
	 * Retrieve maximum allowed discount ceiling from settings.
	 *
	 * @return int
	 */
	public static function get_discount_ceiling() {
		return (int) get_option( 'tc_agents_discount_ceiling', self::DEFAULT_DISCOUNT_CEILING );
	}

	/**
	 * Validate if an agent-offered or customer-requested discount is within authorized bounds.
	 *
	 * @param float $base_price
	 * @param float $requested_price
	 * @return array ['allowed' => bool, 'discount_pct' => float, 'capped_price' => float, 'reason' => string]
	 */
	public static function validate_discount( $base_price, $requested_price ) {
		if ( $base_price <= 0 ) {
			return array(
				'allowed'      => true,
				'discount_pct' => 0,
				'capped_price' => $requested_price,
				'reason'       => 'Base price not set.',
			);
		}

		$discount_amount = max( 0, $base_price - $requested_price );
		$discount_pct    = ( $discount_amount / $base_price ) * 100;
		$ceiling         = self::get_discount_ceiling();

		if ( $discount_pct > $ceiling ) {
			$min_allowed_price = $base_price * ( ( 100 - $ceiling ) / 100 );
			return array(
				'allowed'           => false,
				'discount_pct'      => round( $discount_pct, 1 ),
				'ceiling'           => $ceiling,
				'min_allowed_price' => round( $min_allowed_price ),
				'reason'            => sprintf(
					__( 'Requested discount of %.1f%% exceeds authorized maximum of %d%%. Minimum allowable price is ₹%s.', 'tripcosmos-agents' ),
					$discount_pct,
					$ceiling,
					number_format( $min_allowed_price )
				),
			);
		}

		return array(
			'allowed'      => true,
			'discount_pct' => round( $discount_pct, 1 ),
			'ceiling'      => $ceiling,
			'capped_price' => $requested_price,
			'reason'       => 'Authorized.',
		);
	}

	/**
	 * Calculate approved group tier discount.
	 *
	 * @param int $pax Number of travelers.
	 * @return array
	 */
	public static function get_group_discount( $pax ) {
		$pax = max( 1, (int) $pax );

		if ( $pax < 4 ) {
			return array( 'pax' => $pax, 'discount_pct' => 0, 'label' => 'Standard Rate' );
		}
		if ( $pax >= 4 && $pax <= 7 ) {
			return array( 'pax' => $pax, 'discount_pct' => 5, 'label' => 'Small Group Discount (5% Off)' );
		}
		if ( $pax >= 8 && $pax <= 14 ) {
			return array( 'pax' => $pax, 'discount_pct' => 10, 'label' => 'Expedition Group Tier (10% Off)' );
		}

		return array( 'pax' => $pax, 'discount_pct' => 10, 'label' => 'Large Group Tier (10% Max Automated Discount - Specialist Review Required)' );
	}

	/**
	 * Calculate weighted pipeline revenue: sum of (deal_value * probability).
	 */
	public static function weighted_forecast( array $deals ) {
		$total = 0.0;
		foreach ( $deals as $d ) {
			$val  = floatval( $d['value'] ?? 0 );
			$prob = min( 100, max( 0, intval( $d['probability'] ?? 50 ) ) ) / 100;
			$total += ( $val * $prob );
		}
		return round( $total, 2 );
	}
}
