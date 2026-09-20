<?php
/**
 * Branded Itinerary Document & PDF Generator for TripCosmos.
 *
 * Generates print-ready, professional tour itineraries for travelers and groups
 * with TripCosmos Varanasi branding, day-by-day darshan schedules, and 1-click WhatsApp confirmation.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Itinerary_Generator {

	/**
	 * Generate and persist an itinerary document.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function generate( array $args ) {
		$name        = sanitize_text_field( $args['name'] ?? 'Valued Pilgrim' );
		$phone       = sanitize_text_field( $args['phone'] ?? '' );
		$destination = sanitize_text_field( $args['destination'] ?? 'Varanasi - Ayodhya - Prayagraj Spiritual Tour' );
		$duration    = sanitize_text_field( $args['duration'] ?? '3 Days / 2 Nights' );
		$vehicle     = sanitize_text_field( $args['vehicle'] ?? 'AC Innova Crysta (Dedicated Chauffeur)' );
		$hotel_tier  = sanitize_text_field( $args['hotel_tier'] ?? '3-Star Deluxe Hotel (Near Ghats / Ram Mandir)' );
		$pax         = sanitize_text_field( $args['pax'] ?? '4 Adults' );
		$travel_date = sanitize_text_field( $args['travel_date'] ?? 'Upcoming Dates (Flexible)' );
		$total_fare  = sanitize_text_field( $args['total_fare'] ?? '₹18,500' );
		$days_text   = $args['itinerary_text'] ?? '';

		$salt         = defined( 'NONCE_KEY' ) ? NONCE_KEY : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'tc_itinerary_salt' );
		$token        = substr( md5( $itinerary_id . $salt ), 0, 10 );

		$itinerary_data = array(
			'id'           => $itinerary_id,
			'token'        => $token,
			'name'         => $name,
			'phone'        => $phone,
			'destination'  => $destination,
			'duration'     => $duration,
			'vehicle'      => $vehicle,
			'hotel_tier'   => $hotel_tier,
			'pax'          => $pax,
			'travel_date'  => $travel_date,
			'total_fare'   => $total_fare,
			'itinerary_text'=> $days_text,
			'created_at'   => current_time( 'mysql' ),
		);

		// Store in transient for 30 days
		set_transient( 'tc_itin_' . $itinerary_id, $itinerary_data, 30 * DAY_IN_SECONDS );

		// Generate clean public link
		$view_url = add_query_arg(
			array(
				'tc_action'    => 'view_itinerary',
				'itinerary_id' => $itinerary_id,
				'token'        => $token,
			),
			home_url( '/' )
		);

		return array(
			'itinerary_id' => $itinerary_id,
			'view_url'     => $view_url,
			'summary'      => sprintf( '%s (%s) for %s', $destination, $duration, $name ),
			'total_fare'   => $total_fare,
			'vehicle'      => $vehicle,
		);
	}

	/**
	 * Hook template_redirect to render printable HTML itinerary document.
	 */
	public static function maybe_render_itinerary() {
		if ( ! isset( $_GET['tc_action'] ) || 'view_itinerary' !== $_GET['tc_action'] ) {
			return;
		}

		$itinerary_id = sanitize_text_field( $_GET['itinerary_id'] ?? '' );
		$token        = sanitize_text_field( $_GET['token'] ?? '' );

		if ( empty( $itinerary_id ) || empty( $token ) ) {
			wp_die( 'Invalid itinerary link.', 'TripCosmos Error', array( 'response' => 400 ) );
		}

		$data = get_transient( 'tc_itin_' . $itinerary_id );
		if ( empty( $data ) || ( $data['token'] ?? '' ) !== $token ) {
			wp_die( 'Itinerary has expired or is invalid. Please request a new quote.', 'TripCosmos Error', array( 'response' => 404 ) );
		}

		$clean_wa = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) );
		$wa_link  = 'https://wa.me/' . $clean_wa . '?text=' . rawurlencode( 'Namaste TripCosmos, I am viewing my itinerary ' . $data['id'] . ' for ' . $data['destination'] . ' and would like to confirm the booking.' );

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $data['destination'] ); ?> — Official Itinerary | TripCosmos</title>
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
			<style>
				:root {
					--primary: #ea580c;
					--primary-dark: #c2410c;
					--secondary: #9333ea;
					--dark: #0f172a;
					--light: #f8fafc;
					--border: #e2e8f0;
				}
				* { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
					background: #f1f5f9;
					color: #1e293b;
					line-height: 1.6;
					padding: 24px 16px;
				}
				.tc-doc {
					max-width: 820px;
					margin: 0 auto;
					background: #ffffff;
					border-radius: 20px;
					overflow: hidden;
					box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
					border: 1px solid #e2e8f0;
				}
				.tc-header {
					background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
					color: #ffffff;
					padding: 36px 40px;
					position: relative;
				}
				.tc-header::after {
					content: '';
					position: absolute;
					bottom: 0; left: 0; right: 0; height: 5px;
					background: linear-gradient(90deg, var(--primary), var(--secondary));
				}
				.tc-brand {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 24px;
				}
				.tc-brand-logo {
					font-size: 24px;
					font-weight: 800;
					letter-spacing: -0.5px;
					display: flex;
					align-items: center;
					gap: 10px;
				}
				.tc-ref-badge {
					background: rgba(255, 255, 255, 0.12);
					border: 1px solid rgba(255, 255, 255, 0.25);
					padding: 6px 14px;
					border-radius: 9999px;
					font-size: 12px;
					font-weight: 700;
					letter-spacing: 0.5px;
				}
				.tc-title {
					font-family: 'Playfair Display', serif;
					font-size: 28px;
					line-height: 1.25;
					margin-bottom: 8px;
				}
				.tc-meta-row {
					display: flex;
					gap: 24px;
					font-size: 13px;
					opacity: 0.85;
					flex-wrap: wrap;
				}
				.tc-grid-summary {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
					gap: 16px;
					padding: 24px 40px;
					background: #f8fafc;
					border-bottom: 1px solid var(--border);
				}
				.tc-summary-box {
					background: #ffffff;
					border: 1px solid var(--border);
					padding: 14px 16px;
					border-radius: 12px;
				}
				.tc-summary-label {
					font-size: 11px;
					text-transform: uppercase;
					font-weight: 700;
					color: #64748b;
					letter-spacing: 0.5px;
					margin-bottom: 4px;
				}
				.tc-summary-val {
					font-size: 14px;
					font-weight: 700;
					color: var(--dark);
				}
				.tc-body {
					padding: 36px 40px;
				}
				.tc-section-title {
					font-size: 18px;
					font-weight: 800;
					color: var(--dark);
					margin-bottom: 18px;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.tc-itinerary-content {
					white-space: pre-line;
					font-size: 14.5px;
					color: #334155;
					line-height: 1.75;
					margin-bottom: 32px;
					background: #fafafa;
					padding: 20px 24px;
					border-radius: 14px;
					border-left: 4px solid var(--primary);
				}
				.tc-inclusions-grid {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 20px;
					margin-bottom: 36px;
				}
				.tc-inc-card {
					background: #f0fdf4;
					border: 1px solid #bbf7d0;
					border-radius: 14px;
					padding: 20px;
				}
				.tc-exc-card {
					background: #fef2f2;
					border: 1px solid #fecaca;
					border-radius: 14px;
					padding: 20px;
				}
				.tc-inc-card h4 { color: #166534; font-size: 14px; margin-bottom: 10px; }
				.tc-exc-card h4 { color: #991b1b; font-size: 14px; margin-bottom: 10px; }
				.tc-inc-card ul, .tc-exc-card ul { padding-left: 18px; font-size: 13px; color: #374151; }
				.tc-inc-card li, .tc-exc-card li { margin-bottom: 6px; }
				.tc-actions-bar {
					background: #ffffff;
					border-top: 1px solid var(--border);
					padding: 24px 40px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					flex-wrap: wrap;
					gap: 16px;
				}
				.tc-price-tag {
					font-size: 12px;
					color: #64748b;
				}
				.tc-price-tag strong {
					display: block;
					font-size: 24px;
					color: var(--primary);
				}
				.tc-btn-group {
					display: flex;
					gap: 12px;
				}
				.tc-btn {
					padding: 12px 22px;
					border-radius: 12px;
					font-weight: 700;
					font-size: 14px;
					text-decoration: none;
					cursor: pointer;
					display: inline-flex;
					align-items: center;
					gap: 8px;
					border: none;
				}
				.tc-btn-primary {
					background: linear-gradient(135deg, #10b981 0%, #059669 100%);
					color: #ffffff;
					box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
				}
				.tc-btn-secondary {
					background: #f1f5f9;
					color: #334155;
				}
				@media print {
					body { background: #ffffff; padding: 0; }
					.tc-doc { box-shadow: none; border: none; max-width: 100%; }
					.tc-actions-bar, .tc-btn-group { display: none !important; }
				}
			</style>
		</head>
		<body>
			<div class="tc-doc">
				<div class="tc-header">
					<div class="tc-brand">
						<div class="tc-brand-logo">🛕 TripCosmos.co</div>
						<div class="tc-ref-badge">Ref: <?php echo esc_html( $data['id'] ); ?></div>
					</div>
					<h1 class="tc-title"><?php echo esc_html( $data['destination'] ); ?></h1>
					<div class="tc-meta-row">
						<span>👤 Guest: <strong><?php echo esc_html( $data['name'] ); ?></strong></span>
						<span>📅 Travel Date: <strong><?php echo esc_html( $data['travel_date'] ); ?></strong></span>
						<span>📍 Varanasi Headquarters</span>
					</div>
				</div>

				<div class="tc-grid-summary">
					<div class="tc-summary-box">
						<div class="tc-summary-label">Duration</div>
						<div class="tc-summary-val"><?php echo esc_html( $data['duration'] ); ?></div>
					</div>
					<div class="tc-summary-box">
						<div class="tc-summary-label">Passengers</div>
						<div class="tc-summary-val"><?php echo esc_html( $data['pax'] ); ?></div>
					</div>
					<div class="tc-summary-box">
						<div class="tc-summary-label">Dedicated Vehicle</div>
						<div class="tc-summary-val"><?php echo esc_html( $data['vehicle'] ); ?></div>
					</div>
					<div class="tc-summary-box">
						<div class="tc-summary-label">Hotel Category</div>
						<div class="tc-summary-val"><?php echo esc_html( $data['hotel_tier'] ); ?></div>
					</div>
				</div>

				<div class="tc-body">
					<div class="tc-section-title">🗺️ Day-Wise Tour Itinerary & Pilgrimage Schedule</div>
					<div class="tc-itinerary-content">
						<?php 
						if ( ! empty( $data['itinerary_text'] ) ) {
							echo esc_html( $data['itinerary_text'] );
						} else {
							echo "Day 1: Arrival in Varanasi (VNS Airport / Station). Hotel check-in. Evening Dashashwamedh Ghat Ganga Aarti reservation & private boat ride on the holy Ganga.\n\n" .
								 "Day 2: Morning Subah-e-Banaras ghat cruise. VIP Kashi Vishwanath Darshan, Kaal Bhairav, Sankat Mochan temple, and afternoon excursion to Sarnath (Dhamek Stupa & Buddhist museum).\n\n" .
								 "Day 3: Early morning outstation transfer to Ayodhya via AC Cab. Visit Shri Ram Janmabhoomi Mandir, Hanumangarhi, Kanak Bhavan, and Saryu Aarti before departure.";
						}
						?>
					</div>

					<div class="tc-inclusions-grid">
						<div class="tc-inc-card">
							<h4>✓ Package Inclusions</h4>
							<ul>
								<li>Dedicated AC cab with verified chauffeur for full itinerary</li>
								<li>Pick up & drop from Varanasi Airport (VNS) or Cantt Station</li>
								<li>All fuel costs, parking fees, and driver daily allowance</li>
								<li>Private boat ride for Dashashwamedh Ghat Evening Ganga Aarti</li>
								<li>24x7 TripCosmos on-ground pilgrimage support desk</li>
							</ul>
						</div>
						<div class="tc-exc-card">
							<h4>✕ Exclusions</h4>
							<ul>
								<li>VIP Darshan pass tickets (arranged on request at actual temple portal fee)</li>
								<li>Personal expenses, camera permits, and temple priest dakshina</li>
								<li>Meals other than hotel breakfast (unless opted in package)</li>
							</ul>
						</div>
					</div>
				</div>

				<div class="tc-actions-bar">
					<div class="tc-price-tag">
						Estimated Total Fare
						<strong><?php echo esc_html( $data['total_fare'] ); ?></strong>
					</div>
					<div class="tc-btn-group">
						<button type="button" class="tc-btn tc-btn-secondary" onclick="window.print();">
							🖨️ Print / Save PDF
						</button>
						<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener" class="tc-btn tc-btn-primary">
							💬 Confirm on WhatsApp &rarr;
						</a>
					</div>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
}
