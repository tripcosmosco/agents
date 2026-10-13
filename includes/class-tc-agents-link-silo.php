<?php
/**
 * Automated Internal Link Silo & PageRank Funnel Engine.
 *
 * Scans all published blog articles on the fly and contextually injects high-equity
 * internal links pointing to primary commercial tour packages and cab tariffs,
 * completely solving the "Island Post" syndrome across 2,235 articles.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Link_Silo {

	/**
	 * Register hooks for single posts and pages.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'inject_silo_links' ), 25 );
	}

	/**
	 * Main content filter: injects contextual internal links & commercial conversion callout.
	 *
	 * @param string $content Post HTML content.
	 * @return string
	 */
	public static function inject_silo_links( $content ) {
		// Only run on front-end single posts (not in feed, admin, or excerpt)
		if ( ! is_singular( 'post' ) || is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// 1. Defined Link Silo Targets & Anchor Keyword Rotations
		$silo_rules = array(
			// Target A: Dev Deepawali & Ganga Boat Charters
			array(
				'keywords' => array( 'Dev Deepawali boat booking', 'Dev Deepawali', 'Ganga Aarti boat ride', 'Dashashwamedh Ghat boat', 'Subah-e-Banaras boat' ),
				'url'      => home_url( '/tours/' ),
				'title'    => 'Dev Deepawali Boat Booking & Private Bajra Charters',
			),
			// Target B: Kashi Vishwanath VIP Sugam Darshan
			array(
				'keywords' => array( 'Sugam Darshan passes', 'Sugam Darshan', 'VIP Darshan Kashi Vishwanath', 'Kashi Vishwanath VIP pass', 'Rudrabhishek pooja' ),
				'url'      => home_url( '/tours/' ),
				'title'    => 'Kashi Vishwanath Sugam Darshan VIP Passes & Priest Assistance',
			),
			// Target C: Ayodhya Ram Mandir & Innova Outstation Cabs
			array(
				'keywords' => array( 'Varanasi to Ayodhya cab', 'Innova Crysta cab', 'Ayodhya Ram Mandir tour', 'Purvanchal Expressway taxi', 'outstation cab Varanasi' ),
				'url'      => home_url( '/' ),
				'title'    => 'Private AC Cabs from Varanasi to Ayodhya & Prayagraj',
			),
			// Target D: Prayagraj Magh Mela Tent Booking
			array(
				'keywords' => array( 'Magh Mela tent booking', 'Magh Mela', 'Triveni Sangam holy dip', 'Prayagraj Sangam boat' ),
				'url'      => home_url( '/tour-packages/magh-mela-tent-booking-2027/' ),
				'title'    => 'Magh Mela Luxury Swiss Tents & Triveni Sangam Pilgrimage',
			),
		);

		$injected_count = 0;
		$max_injections = 3; // Maximum 3 contextual links per article to preserve editorial naturalness

		foreach ( $silo_rules as $rule ) {
			if ( $injected_count >= $max_injections ) {
				break;
			}

			foreach ( $rule['keywords'] as $keyword ) {
				// Don't link if the keyword already contains a link or is inside an HTML tag/heading
				$pattern = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . preg_quote( $keyword, '/' ) . ')\b/i';

				if ( preg_match( $pattern, $content ) ) {
					$replacement = sprintf(
						'<a href="%s" class="tc-silo-link" title="%s">$1</a>',
						esc_url( $rule['url'] ),
						esc_attr( $rule['title'] )
					);

					// Replace only the first occurrence
					$new_content = preg_replace( $pattern, $replacement, $content, 1 );
					if ( $new_content && $new_content !== $content ) {
						$content = $new_content;
						$injected_count++;
						break; // Move to the next silo rule once one keyword from this rule matches
					}
				}
			}
		}

		// 2. Append High-Converting Commercial Callout Card at end of post
		$callout_html = self::render_silo_callout();
		return $content . $callout_html;
	}

	/**
	 * Generates high-converting commercial banner for all 2,235 articles.
	 *
	 * @return string
	 */
	private static function render_silo_callout() {
		$post_title = get_the_title();
		$wa_num     = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( empty( $wa_num ) ) {
			$wa_num = '919336116210';
		}
		$wa_msg = sprintf(
			"Namaste TripCosmos! 🙏 I was reading your article on '%s' and want to check available tour packages, VIP Sugam Darshan passes, and cab tariffs.",
			$post_title
		);
		$wa_url = "https://wa.me/{$wa_num}?text=" . rawurlencode( $wa_msg );

		ob_start();
		?>
		<div class="tc-silo-callout-box" style="margin: 36px 0 24px; padding: 22px 24px; border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: #ffffff; border: 1px solid rgba(245, 158, 11, 0.4); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16); font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
			<div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 12px;">
				<span style="font-size: 28px; line-height: 1;">🛕</span>
				<div>
					<h4 style="margin: 0 0 6px; font-size: 17px; font-weight: 800; color: #f59e0b; letter-spacing: -0.01em;">Planning Your Varanasi &amp; Ayodhya Pilgrimage?</h4>
					<p style="margin: 0; font-size: 13.5px; line-height: 1.55; color: #e2e8f0;">
						TripCosmos guarantees verified <strong>Kashi Vishwanath Sugam Darshan passes</strong> (zero line waiting), private <strong>Dev Deepawali &amp; Ganga Aarti Bajra boats</strong>, and verified <strong>Toyota Innova Crysta cabs</strong> with trusted local chauffeurs.
					</p>
				</div>
			</div>
			<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px;">
				<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #ffffff; text-decoration: none; font-weight: 700; font-size: 12.5px; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.35);">
					<span>💬 Get Instant WhatsApp Quote &amp; Itinerary</span>
				</a>
				<a href="<?php echo esc_url( home_url( '/tours/' ) ); ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px; background: rgba(255, 255, 255, 0.12); color: #f8fafc; text-decoration: none; font-weight: 600; font-size: 12.5px; border: 1px solid rgba(255, 255, 255, 0.2);">
					<span>Explore Verified Tour Packages &rarr;</span>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
