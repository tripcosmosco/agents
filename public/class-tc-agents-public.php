<?php
/**
 * Frontend Public Chat Widget Handler.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Public {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_widget_markup' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_in_post_whatsapp_cta' ), 20 );
		add_shortcode( 'tripcosmos_chat', array( __CLASS__, 'render_shortcode' ) );

		if ( '1' === (string) get_option( 'tc_agents_gtm_enabled', '1' ) ) {
			$gtm_id = trim( (string) get_option( 'tc_agents_gtm_container_id', '' ) );

			// Prevent duplicate GTM snippet if Google Site Kit or another plugin is already managing GTM
			$sitekit_gtm = get_option( 'googlesitekit_tagmanager_settings' );
			$sitekit_active = is_array( $sitekit_gtm ) && ! empty( $sitekit_gtm['useSnippet'] ) && ! empty( $sitekit_gtm['containerID'] );

			if ( ! empty( $gtm_id ) && ! $sitekit_active ) {
				add_action( 'wp_head', array( __CLASS__, 'render_gtm_head' ), 1 );
				add_action( 'wp_body_open', array( __CLASS__, 'render_gtm_body' ), 1 );
			}
		}
	}

	/**
	 * Enqueue public styles and scripts.
	 */
	public static function enqueue_assets() {
		// Universal Google Ads & GTM Conversion Tracking Bridge (fires across all pages)
		$bridge_file = TC_AGENTS_PATH . 'public/js/tc-gads-bridge.js';
		$bridge_ver  = file_exists( $bridge_file ) ? TC_AGENTS_VERSION . '.' . filemtime( $bridge_file ) : TC_AGENTS_VERSION;
		wp_enqueue_script( 'tc-gads-bridge-js', TC_AGENTS_URL . 'public/js/tc-gads-bridge.js', array(), $bridge_ver, true );
		wp_localize_script(
			'tc-gads-bridge-js',
			'tcGadsConfig',
			array(
				'conversionId'  => 'AW-17106475490',
				'formLabel'     => 'bLh-CM-swOoaEOKzgN0_',
				'waLabel'       => 'MOptCICRn-caEOKzgN0_',
				'phoneLabel'    => '7sk4CLrQst4aEOKzgN0_',
				'purchaseLabel' => 'EEn8CIqNoOcaEOKzgN0_',
				'leadValue'     => 1500,
			)
		);
		// Only enqueue if widget is enabled
		if ( '1' !== (string) get_option( 'tc_agents_widget_enabled', '1' ) ) {
			return;
		}

		$css_file = TC_AGENTS_PATH . 'public/css/tc-chat-widget.css';
		$js_file  = TC_AGENTS_PATH . 'public/js/tc-chat-widget.js';
		$css_ver  = file_exists( $css_file ) ? TC_AGENTS_VERSION . '.' . filemtime( $css_file ) : TC_AGENTS_VERSION;
		$js_ver   = file_exists( $js_file )  ? TC_AGENTS_VERSION . '.' . filemtime( $js_file )  : TC_AGENTS_VERSION;

		wp_enqueue_style(
			'tc-chat-widget-css',
			TC_AGENTS_URL . 'public/css/tc-chat-widget.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'tc-chat-widget-js',
			TC_AGENTS_URL . 'public/js/tc-chat-widget.js',
			array(),
			$js_ver,
			true
		);

		$clean_wa = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( empty( $clean_wa ) ) {
			$clean_wa = '919336116210';
		}

		$wa_attr_file = TC_AGENTS_PATH . 'public/js/tc-wa-attribution.js';
		$wa_attr_ver  = file_exists( $wa_attr_file ) ? TC_AGENTS_VERSION . '.' . filemtime( $wa_attr_file ) : TC_AGENTS_VERSION;
		wp_enqueue_script( 'tc-wa-attribution-js', TC_AGENTS_URL . 'public/js/tc-wa-attribution.js', array(), $wa_attr_ver, true );
		wp_localize_script(
			'tc-wa-attribution-js',
			'tcWaAttr',
			array(
				'clickUrl'    => esc_url_raw( rest_url( 'tc-agents/v1/wa-click' ) ),
				'number'      => $clean_wa,
				'defaultText' => 'Hi TripCosmos!',
			)
		);

				$default_greeting = "Namaste! 🙏 Welcome to TripCosmos — Varanasi's premier pilgrimage & tour desk.\n\nLooking for Kashi Vishwanath VIP darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova Crysta / Swift Dzire), hotel bookings, or private Ganga Aarti boat rides? How can I assist you today?";

		// Contextual Dev Diwali Detection
		$is_dev_diwali = false;
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== stripos( $req_uri, 'dev-diwali' ) || false !== stripos( $req_uri, 'dev-deepawali' ) ) {
			$is_dev_diwali = true;
		} elseif ( is_singular() ) {
			$p_slug  = get_post_field( 'post_name', get_the_ID() );
			$p_title = get_the_title();
			if ( false !== stripos( $p_slug, 'dev-diwali' ) || false !== stripos( $p_slug, 'dev-deepawali' ) || false !== stripos( $p_title, 'Dev Diwali' ) || false !== stripos( $p_title, 'Dev Deepawali' ) ) {
				$is_dev_diwali = true;
			}
		}

		if ( $is_dev_diwali ) {
			$default_greeting = "Namaste! 🙏 Planning your Dev Diwali 2026 trip in Varanasi?\n\nPrivate boat permits, Bajra seating & Ghat hotels sell out 90 days early. Would you like to check live boat availability, VIP Ganga Aarti passes, or 2N/3D full tour packages?";
		}
		$greeting         = get_option( 'tc_agents_widget_greeting', $default_greeting );
		$current_user_data = null;

		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			$f_name = ! empty( $u->first_name ) ? $u->first_name : $u->display_name;
			$u_phone = get_user_meta( $u->ID, 'billing_phone', true ) ?: get_user_meta( $u->ID, 'phone', true ) ?: '';
			$current_user_data = array(
				'id'        => $u->ID,
				'name'      => $u->display_name,
				'firstName' => $f_name,
				'email'     => $u->user_email,
				'phone'     => $u_phone,
			);
						if ( preg_match( '/\.co|admin|tripcosmos/i', $f_name ) ) {
				$f_name = '';
			}
			$salutation = ! empty( $f_name ) ? "Namaste, " . esc_html( $f_name ) . " Ji! 🙏" : "Namaste! 🙏";
			if ( $is_dev_diwali ) {
				$greeting = $salutation . " Planning your Dev Diwali 2026 trip in Varanasi?\n\nPrivate boat permits, Bajra seating & Ghat hotels sell out 90 days early. How can I assist you with boat booking, Aarti passes, or 2N/3D packages today?";
			} else {
				$greeting = $salutation . " Welcome back to TripCosmos.\n\nAre you planning an upcoming pilgrimage or looking for verified cab fares, hotel stays, or VIP Darshan passes today?";
			}
		}

		wp_localize_script(
			'tc-chat-widget-js',
			'tcChatWidget',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'tc-agents/v1/chat' ) ),
				'leadCaptureUrl' => esc_url_raw( rest_url( 'tc-agents/v1/lead-capture' ) ),
				'historyUrl'     => esc_url_raw( rest_url( 'tc-agents/v1/conversations' ) ),
				'title'          => get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ),
				'greeting'       => $greeting,
				'primaryColor'   => get_option( 'tc_agents_widget_primary_color', '#ea580c' ),
				'whatsappNumber' => $clean_wa,
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'triggerCallUrl' => esc_url_raw( rest_url( 'tc-agents/v1/trigger-call' ) ),
				'voiceEnabled'   => '1' === (string) get_option( 'tc_agents_voice_enabled', '0' ),
				'agentSlug'      => 'tripcosmos-guide',
				'siteUrl'        => home_url(),
				'scarcityFeed'   => class_exists( 'TC_Inventory_Service' ) ? TC_Inventory_Service::get_scarcity_feed() : array(),
				'currentUser'    => $current_user_data,
				'gtmEnabled'     => '1' === (string) get_option( 'tc_agents_gtm_enabled', '1' ),
				'leadValue'      => (float) get_option( 'tc_agents_lead_conversion_value', 1500 ),
				'gadsConversionId' => get_option( 'tc_agents_gads_conversion_id', 'AW-17106475490' ),
				'gadsConversionLabel' => get_option( 'tc_agents_gads_conversion_label', 'bLh-CM-swOoaEOKzgN0_' ),
				'gadsConversionActionId' => get_option( 'tc_agents_gads_conversion_action_id', '' ),
			)
		);
	}

	/**
	 * Render HTML skeleton for floating widget.
	 */
	public static function render_widget_markup() {
		if ( '1' !== (string) get_option( 'tc_agents_widget_enabled', '1' ) ) {
			return;
		}

		// Contextual Dev Diwali Detection
		$is_dev_diwali = false;
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== stripos( $req_uri, 'dev-diwali' ) || false !== stripos( $req_uri, 'dev-deepawali' ) ) {
			$is_dev_diwali = true;
		} elseif ( is_singular() ) {
			$p_slug  = get_post_field( 'post_name', get_the_ID() );
			$p_title = get_the_title();
			if ( false !== stripos( $p_slug, 'dev-diwali' ) || false !== stripos( $p_slug, 'dev-deepawali' ) || false !== stripos( $p_title, 'Dev Diwali' ) || false !== stripos( $p_title, 'Dev Deepawali' ) ) {
				$is_dev_diwali = true;
			}
		}

		$title         = get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' );
		$subtitle      = get_option( 'tc_agents_widget_subtitle', 'Online • Varanasi Desk ✓' );
		$avatar_url    = get_option( 'tc_agents_bot_avatar', '' );
		$avatar_emoji  = get_option( 'tc_agents_bot_avatar_emoji', '🛕' );
		$default_greet = $is_dev_diwali
			? "Namaste! 🙏 Planning your Dev Diwali 2026 trip in Varanasi?\n\nPrivate boat permits, Bajra seating & Ghat hotels sell out 90 days early. How can I assist you with boat booking, Aarti passes, or 2N/3D packages today?"
			: "Namaste! 🙏 Welcome to TripCosmos — Varanasi's premier pilgrimage & tour desk.\n\nLooking for Kashi Vishwanath VIP darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova Crysta / Swift Dzire), hotel bookings, or private Ganga Aarti boat rides? How can I assist you today?";
		$greeting      = get_option( 'tc_agents_widget_greeting', $default_greet );
		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			$f_name = ! empty( $u->first_name ) ? $u->first_name : $u->display_name;
			if ( preg_match( '/\.co|admin|tripcosmos/i', $f_name ) ) {
				$f_name = '';
			}
			$salutation = ! empty( $f_name ) ? "Namaste, " . esc_html( $f_name ) . " Ji! 🙏" : "Namaste! 🙏";
			if ( $is_dev_diwali ) {
				$greeting = $salutation . " Planning your Dev Diwali 2026 trip in Varanasi?\n\nPrivate boat permits, Bajra seating & Ghat hotels sell out 90 days early. How can I assist you with boat booking, Aarti passes, or 2N/3D packages today?";
			} else {
				$greeting = $salutation . " Welcome back to TripCosmos.\n\nAre you planning an upcoming pilgrimage or looking for verified cab fares, hotel stays, or VIP Darshan passes today?";
			}
		}
		$teaser_text   = $is_dev_diwali
			? 'Namaste! 🙏 Heading to Varanasi for Dev Diwali 2026? Ghat hotels & Aarti boats are booking up fast. Let me check live rates & availability for you!'
			: get_option( 'tc_agents_launcher_teaser_text', 'Namaste! 🙏 Planning your trip to Varanasi or Ayodhya? I can check live cab fares, VIP Darshan passes & hotel availability for you right now.' );
		$side          = get_option( 'tc_agents_widget_side', 'right' );
		$primary_color = get_option( 'tc_agents_widget_primary_color', '#ea580c' );
		$second_color  = get_option( 'tc_agents_widget_secondary_color', '#9333ea' );
		$wa_num        = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( empty( $wa_num ) ) {
			$wa_num = '919336116210';
		}
		$clean_phone   = '+' . $wa_num;
		$direct_wa     = "https://wa.me/{$wa_num}?text=" . rawurlencode( 'Hi TripCosmos, I am browsing your site and would like help planning a Varanasi tour or outstation cab.' );

		// Custom Starter Prompts

		$default_starters = "🛕 Kashi Ayodhya Tour | Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package\n🚗 Outstation Cabs | Book outstation cab Dzire or Innova Crysta for Varanasi to Ayodhya\n⛵ Ganga Aarti Boat | Book private boat for evening Ganga Aarti at Dashashwamedh Ghat\n🏨 Ghat Hotels | Best ghat-view hotels in Varanasi near Kashi Vishwanath";
		if ( $is_dev_diwali ) {
			$default_starters = "🪔 Dev Diwali Boat | Book private motorboat or Bajra for Dev Diwali 2026\n📋 2N/3D Tour Package | Check complete Dev Diwali 2026 itinerary and pricing\n🏨 Ghat View Hotels | Available hotels near Dashashwamedh Ghat for Dev Diwali\n🚗 Airport Cab | Pre-book AC Innova or Sedan for Dev Diwali arrival";
		}
		$raw_starters     = get_option( 'tc_agents_starter_prompts', $default_starters );
		$starter_lines    = array_filter( array_map( 'trim', explode( "\n", $raw_starters ) ) );

		$side_class = ( 'left' === $side ) ? ' tc-side-left' : '';
		?>
		<!-- TripCosmos AI Agent Floating Widget -->
		<div id="tc-agent-widget-root" class="tc-widget-container<?php echo esc_attr( $side_class ); ?>" style="--tc-primary-color: <?php echo esc_attr( $primary_color ); ?>; --tc-secondary-color: <?php echo esc_attr( $second_color ); ?>;" aria-live="polite">

			<!-- Proactive Sales Outreach Card -->
			<div id="tc-widget-teaser" class="tc-widget-teaser" style="display: none;" role="status">
				<div class="tc-teaser-header">
					<div class="tc-teaser-profile">
						<div class="tc-teaser-avatar-wrap">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="Travel Specialist" width="36" height="36" loading="lazy" />
							<?php else : ?>
								<span><?php echo esc_html( $avatar_emoji ); ?></span>
							<?php endif; ?>
							<span class="tc-teaser-live-dot"></span>
						</div>
						<div>
							<div class="tc-teaser-meta-name"><?php echo esc_html( $title ); ?></div>
							<div class="tc-teaser-meta-status">Online • Replies instantly</div>
						</div>
					</div>
					<button type="button" class="tc-teaser-close" id="tc-teaser-close" aria-label="Dismiss message">&times;</button>
				</div>
				<div class="tc-teaser-text" id="tc-teaser-body">
					<?php echo esc_html( $teaser_text ); ?>
				</div>
				<div class="tc-teaser-chips">
					<?php if ( $is_dev_diwali ) : ?>
						<button type="button" class="tc-teaser-chip" data-query="Book private motorboat or Bajra for Dev Diwali 2026">🪔 Dev Diwali Boat</button>
						<button type="button" class="tc-teaser-chip" data-query="Check complete Dev Diwali 2026 itinerary and pricing">📋 2N/3D Package</button>
						<button type="button" class="tc-teaser-chip" data-query="Available hotels near Dashashwamedh Ghat for Dev Diwali">🏨 Ghat Hotels</button>
					<?php else : ?>
						<button type="button" class="tc-teaser-chip" data-query="Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package">🛕 4D Tour</button>
						<button type="button" class="tc-teaser-chip" data-query="Check outstation cab fares for Varanasi to Ayodhya">🚗 Cab Fares</button>
						<button type="button" class="tc-teaser-chip" data-query="How to book VIP Sugam Darshan for Kashi Vishwanath?">🕉️ VIP Darshan</button>
					<?php endif; ?>
				</div>
				<button type="button" class="tc-teaser-action-btn" id="tc-teaser-open-btn">
					<span>💬 Chat with Specialist</span>
					<span>&rarr;</span>
				</button>
			</div>

			<!-- High-Engaging Floating Action Callout Pill -->
			<div id="tc-fab-callout" class="tc-fab-callout" role="button" aria-label="<?php esc_attr_e( 'Get Free Trip Itinerary', 'tripcosmos-agents' ); ?>" tabindex="0">
				<span class="tc-callout-icon"><?php echo $is_dev_diwali ? '🪔' : '✨'; ?></span>
				<span class="tc-callout-text">
					<strong><?php echo $is_dev_diwali ? 'Dev Diwali Boat Rates' : 'Free Trip Itinerary'; ?></strong>
					<span class="tc-callout-sub">&bull; Talk to AI</span>
				</span>
				<span class="tc-callout-arrow">&rarr;</span>
			</div>

			<!-- Floating Trigger Button (Sleek 50px Saffron FAB) -->
			<button id="tc-widget-trigger" class="tc-widget-fab" aria-label="Open TripCosmos AI Chat" type="button">
				<span class="tc-fab-icon-chat">
					<svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
					</svg>
				</span>
				<span class="tc-fab-icon-close" style="display: none;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</span>
				<span class="tc-status-pulse"></span>
				<span class="tc-fab-badge" id="tc-fab-badge" style="display: none;">1</span>
			</button>

			<!-- Chat Window Dialog -->
			<div id="tc-widget-window" class="tc-widget-window" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="tc-widget-heading">
				<!-- Header -->
				<div class="tc-widget-header">
					<div class="tc-header-stripe"></div>
					<div class="tc-header-info">
						<div class="tc-avatar-circle">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" width="40" height="40" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;" />
							<?php else : ?>
								<?php echo esc_html( $avatar_emoji ); ?>
							<?php endif; ?>
						</div>
						<div>
							<h3 id="tc-widget-heading"><?php echo esc_html( $title ); ?></h3>
							<span class="tc-online-status"><span class="tc-dot"></span> <?php echo esc_html( trim( preg_replace( "/[â€¢Â·\s]+$/u", "", $subtitle ) ) ); ?></span>
						</div>
					</div>
					<div class="tc-header-controls">
						<a href="<?php echo esc_url( $direct_wa ); ?>" target="_blank" rel="noopener" class="tc-btn-icon tc-btn-wa" title="<?php esc_attr_e( 'Chat on WhatsApp', 'tripcosmos-agents' ); ?>" aria-label="Chat on WhatsApp">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.18-.545-1.898-.787-3.13-2.73-3.224-2.855-.094-.127-.775-1.03-.775-1.965 0-.934.489-1.393.663-1.583.175-.19.38-.238.508-.238.127 0 .254.002.365.008.117.006.273-.044.428.328.16.383.548 1.339.596 1.436.048.098.08.212.015.339-.064.127-.095.207-.19.317-.095.111-.2.248-.286.333-.095.096-.195.2-.084.391.111.19.493.813 1.056 1.314.726.645 1.338.845 1.53.94.19.096.302.08.413-.048.111-.127.476-.556.603-.746.127-.19.254-.159.429-.095.174.064 1.111.524 1.302.619.19.096.317.143.365.223.048.079.048.461-.096.866z"/>
							</svg>
						</a>
						<a href="tel:<?php echo esc_attr( $clean_phone ); ?>" class="tc-btn-icon tc-btn-phone" title="<?php esc_attr_e( 'Call Reservations Desk', 'tripcosmos-agents' ); ?>" aria-label="Call Reservations Desk">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
								<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
							</svg>
						</a>
						<button id="tc-widget-restart" class="tc-btn-icon" title="<?php esc_attr_e( 'Start fresh conversation', 'tripcosmos-agents' ); ?>" aria-label="Restart chat" type="button">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
								<path d="M21 3v5h-5"></path>
								<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
								<path d="M3 21v-5h5"></path>
							</svg>
						</button>
						<button id="tc-widget-close" class="tc-btn-icon tc-btn-close" aria-label="Close Chat Window" type="button">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
					</div>
				</div>

				<!-- Multilingual Pilgrim Language Switcher -->
				<div class="tc-lang-picker-bar" role="toolbar" aria-label="<?php esc_attr_e( 'Select Chat Language', 'tripcosmos-agents' ); ?>">
					<span class="tc-lang-picker-title">🌐 Language:</span>
					<div class="tc-lang-pills">
						<button type="button" class="tc-lang-pill active" data-lang="en" title="English">EN</button>
						<button type="button" class="tc-lang-pill" data-lang="hi" title="हिन्दी (Hindi)">हिन्दी</button>
						<button type="button" class="tc-lang-pill" data-lang="gu" title="ગુજરાતી (Gujarati)">ગુજરાતી</button>
						<button type="button" class="tc-lang-pill" data-lang="te" title="తెలుగు (Telugu)">తెలుగు</button>
					</div>
				</div>

				<!-- Tab 1: Messages Stream -->
				<div id="tc-widget-messages" class="tc-panel tc-messages-container active">
					<!-- Welcome Message -->
					<div class="tc-chat-bubble tc-bubble-bot">
						<div class="tc-bubble-text"><?php echo nl2br( esc_html( $greeting ) ); ?></div>
					</div>

					<!-- Quick Starter Prompts -->
					<div class="tc-chips-container" id="tc-starter-chips">
						<?php foreach ( $starter_lines as $starter_line ) : ?>
							<?php 
							$parts = explode( '|', $starter_line, 2 );
							$chip_label = trim( $parts[0] );
							$chip_query = isset( $parts[1] ) ? trim( $parts[1] ) : $chip_label;
							?>
							<button type="button" class="tc-chip" data-query="<?php echo esc_attr( $chip_query ); ?>"><?php echo esc_html( $chip_label ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Tab 2: Fast Lead Capture Quote Panel -->
				<div id="tc-widget-quote-panel" class="tc-panel tc-quote-panel">
					<div class="tc-quote-heading">
						<h4>Custom Itinerary & Group Quote</h4>
						<p>Get personalized itinerary, VIP darshan passes & cab fare in 15 mins.</p>
					</div>
					<form id="tc-quote-form" class="tc-quote-form">
						<div class="tc-form-row">
							<div class="tc-form-field">
								<label for="tc-q-name">Your Full Name *</label>
								<input type="text" id="tc-q-name" required placeholder="e.g. Rahul Sharma" autocomplete="name" />
							</div>
							<div class="tc-form-field">
								<label for="tc-q-phone">WhatsApp Number *</label>
								<input type="tel" id="tc-q-phone" required placeholder="+91 98765 43210" autocomplete="tel" />
							</div>
						</div>
						<div class="tc-form-row">
							<div class="tc-form-field">
								<label for="tc-q-email">Email (Optional)</label>
								<input type="email" id="tc-q-email" placeholder="rahul@example.com" autocomplete="email" />
							</div>
							<div class="tc-form-field">
								<label for="tc-q-month">Travel Dates / Month</label>
								<input type="text" id="tc-q-month" placeholder="e.g. Next week / Nov" />
							</div>
						</div>
						<div class="tc-form-field">
							<label for="tc-q-destination">Destination / Circuit</label>
							<select id="tc-q-destination">
								<option value="Varanasi (Kashi Darshan &amp; Ganga Aarti)">Varanasi (Kashi Darshan &amp; Ganga Aarti)</option>
								<option value="Dev Deepawali Special 2026 (Varanasi)" <?php echo $is_dev_diwali ? 'selected' : ''; ?>>Dev Deepawali Special 2026 (Varanasi)</option>
								<option value="Varanasi - Ayodhya - Prayagraj (3 Holy Cities)">Varanasi - Ayodhya - Prayagraj (3 Holy Cities)</option>
								<option value="Varanasi - Ayodhya (Ram Janmabhoomi)">Varanasi - Ayodhya (Ram Janmabhoomi)</option>
								<option value="Varanasi - Prayagraj (Triveni Sangam)">Varanasi - Prayagraj (Triveni Sangam)</option>
								<option value="Varanasi - Bodhgaya - Gaya (Pind Daan Yatra)">Varanasi - Bodhgaya - Gaya (Pind Daan)</option>
								<option value="Complete Spiritual Circuit (Kashi-Prayag-Ayodhya-Gaya)">Complete Spiritual Circuit (Kashi-Prayag-Ayodhya-Gaya)</option>
								<option value="Varanasi Airport &amp; Outstation Cab Rental">Varanasi Airport &amp; Outstation Cab Rental</option>
								<option value="Custom Itinerary / Other">Custom Itinerary / Other</option>
							</select>
						</div>
						<div class="tc-form-field">
							<label for="tc-q-group">Travel Group / Requirement</label>
							<select id="tc-q-group">
								<option value="Family Tour (2-4 Pax)">Family Tour (2-4 Pax)</option>
								<option value="Senior Citizen Pilgrimage">Senior Citizen Pilgrimage Yatra</option>
								<option value="Outstation Cab Only (Dzire/Innova/Tempo)">Outstation Cab Only (Dzire / Innova / Tempo)</option>
								<option value="Group / Yatra (10+ Pax)">Group Yatra / Spiritual Tour (10+ Pax)</option>
								<option value="Solo / Couple Package">Solo / Couple Package</option>
							</select>
						</div>
						<button type="submit" id="tc-quote-submit" class="tc-quote-submit-btn">
							&#9889; Get Custom Itinerary &amp; Cab Fare
						</button>
					</form>
					<div id="tc-quote-success" class="tc-quote-success" style="display: none;">
						<div class="tc-success-icon">✓</div>
						<h5>Inquiry Synced Successfully!</h5>
						<p>Our Varanasi travel desk will reach out on WhatsApp. Switching you back to live guide chat...</p>
					</div>
				</div>

				<!-- Tab 3: Hands-free Interactive WebCall Mode -->
				<div id="tc-widget-voice-panel" class="tc-panel tc-voice-panel">
					<div class="tc-voice-container">
						<div class="tc-voice-avatar-wrap">
							<div class="tc-voice-ring" id="tc-voice-ring">
								<?php if ( ! empty( $avatar_url ) ) : ?>
									<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" width="80" height="80" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" />
								<?php else : ?>
									<?php echo esc_html( $avatar_emoji ); ?>
								<?php endif; ?>
							</div>
						</div>
						<h4 id="tc-voice-agent-title"><?php echo esc_html( $title ); ?></h4>
						<p id="tc-voice-status">Tap microphone to talk with TripCosmos AI</p>
						<canvas id="tc-voice-visualizer" width="280" height="60"></canvas>
						<div id="tc-voice-live-transcript" class="tc-voice-transcript">"Namaste! Ask me about Varanasi ghats, Dev Diwali boat rates, Ayodhya darshan, or cab fares..."</div>

						<div class="tc-voice-controls">
							<button type="button" id="tc-voice-toggle-btn" class="tc-voice-call-btn">
								<span class="tc-voice-btn-icon">🎙️</span>
								<span class="tc-voice-btn-label">Start Voice Call</span>
							</button>
						</div>

						<!-- Direct Phone Call Trigger Box -->
						<div class="tc-voice-phone-box">
							<div class="tc-voice-phone-title">Prefer an actual call on your mobile?</div>
							<div class="tc-voice-phone-input-row">
								<input type="tel" id="tc-direct-phone-input" placeholder="+91 98765 43210" autocomplete="tel" />
								<button type="button" id="tc-btn-trigger-mobile-call" class="tc-btn-trigger-call">📞 Call Me</button>
							</div>
							<div id="tc-phone-call-status" class="tc-phone-call-status" style="display: none;"></div>
						</div>
					</div>
				</div>

				<!-- Tab 4: Saved Itineraries & Past Chat History -->
				<div id="tc-widget-history-panel" class="tc-panel tc-history-panel">
					<div class="tc-history-header">
						<h4>📜 Saved Itineraries & Chats</h4>
						<button type="button" id="tc-clear-history-btn" class="tc-clear-history-btn" title="Clear History">Clear All</button>
					</div>
					<div id="tc-history-list" class="tc-history-list">
						<div class="tc-history-empty">
							<span style="font-size: 28px;">🛕</span>
							<p>No past chat transcripts yet. Ask a question or request an itinerary to start saving your journey history!</p>
						</div>
					</div>
				</div>

				<!-- Footer: Chat Input + Bottom Nav + Branding -->
				<div class="tc-widget-footer">
					<!-- In-Widget WhatsApp Direct Handoff Strip -->
					<div class="tc-widget-wa-bar" id="tc-widget-wa-bar">
						<a href="<?php echo esc_url( $direct_wa ); ?>" target="_blank" rel="noopener" class="tc-wa-bar-link" id="tc-wa-bar-link">
							<span class="tc-wa-bar-icon">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
									<path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.18-.545-1.898-.787-3.13-2.73-3.224-2.855-.094-.127-.775-1.03-.775-1.965 0-.934.489-1.393.663-1.583.175-.19.38-.238.508-.238.127 0 .254.002.365.008.117.006.273-.044.428.328.16.383.548 1.339.596 1.436.048.098.08.212.015.339-.064.127-.095.207-.19.317-.095.111-.2.248-.286.333-.095.096-.195.2-.084.391.111.19.493.813 1.056 1.314.726.645 1.338.845 1.53.94.19.096.302.08.413-.048.111-.127.476-.556.603-.746.127-.19.254-.159.429-.095.174.064 1.111.524 1.302.619.19.096.317.143.365.223.048.079.048.461-.096.866z"/>
								</svg>
							</span>
							<span class="tc-wa-bar-text">Prefer WhatsApp? <strong>Chat on WhatsApp &rarr;</strong></span>
							<span class="tc-wa-badge-live">Instant</span>
						</a>
					</div>
					<!-- Input Bar (Only active on chat tab) -->
					<div id="tc-widget-input-area" class="tc-widget-input-area">
						<form id="tc-widget-form" autocomplete="off">
							<input type="text" id="tc-widget-input" placeholder="Ask about Varanasi, Ayodhya, cabs, hotels, aarti boat..." aria-label="Type your message" />
							<button type="button" id="tc-widget-mic" class="tc-btn-mic" title="Speak message" aria-label="Voice input">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
									<path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
									<line x1="12" y1="19" x2="12" y2="23"></line>
									<line x1="8" y1="23" x2="16" y2="23"></line>
								</svg>
							</button>
							<button type="submit" id="tc-widget-send" aria-label="Send Message">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<line x1="22" y1="2" x2="11" y2="13"></line>
									<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
								</svg>
							</button>
						</form>
					</div>

					<!-- Bottom Navigation Bar (Footer Nav) -->
					<nav class="tc-widget-tabs" id="tc-widget-nav" role="tablist" aria-label="Chat sections">
						<button type="button" class="tc-tab-btn active" data-tab="chat" role="tab" aria-selected="true">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">💬</span>
							<span class="tc-tab-label">Trip Planner AI</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="quote" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">📋</span>
							<span class="tc-tab-label">Free Quotation</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="voice" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">🎙️</span>
							<span class="tc-tab-label">Call</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="history" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">📜</span>
							<span class="tc-tab-label">Chats</span>
						</button>
					</nav>
				</div>
			</div>
		</div>
		<!-- Instant Zero-Delay Click Stub -->
		<script id="tc-widget-instant-click">
		(function(){
			window.tcToggleWidget = window.tcToggleWidget || function(forceState) {
				var root = document.getElementById('tc-agent-widget-root');
				var win = document.getElementById('tc-widget-window');
				var fab = document.getElementById('tc-widget-trigger');
				var isOpen = root ? root.classList.contains('tc-window-open') : false;
				var nextState = (typeof forceState === 'boolean') ? forceState : !isOpen;
				if (root) root.classList.toggle('tc-window-open', nextState);
				if (win) win.style.setProperty('display', nextState ? 'flex' : 'none', 'important');
				if (fab) fab.classList.toggle('active', nextState);
				document.body.classList.toggle('tc-chat-open', nextState);
				window._tcPendingOpen = nextState;
			};
			window.tcOpenChatWidget = window.tcOpenChatWidget || function() { window.tcToggleWidget(true); };
			window.tcCloseChatWidget = window.tcCloseChatWidget || function() { window.tcToggleWidget(false); };

			document.addEventListener('click', function(e){
				var target = e.target;
				if (!target) return;
				var trigger = target.closest('#tc-widget-trigger, #tc-fab-callout, .tc-widget-fab, #tc-sticky-open-ai, .tc-open-chat-trigger, #tc-teaser-open-btn');
				if (!trigger) return;
				e.preventDefault();
				if (trigger.id === 'tc-widget-trigger' || trigger.classList.contains('tc-widget-fab')) {
					window.tcToggleWidget();
				} else {
					window.tcOpenChatWidget();
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render inline embedded chat shortcode [tripcosmos_chat agent="slug" title="Title"].
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'agent' => 'tripcosmos-guide',
				'title' => get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ),
			),
			$atts,
			'tripcosmos_chat'
		);

		ob_start();
		$title = sanitize_text_field( $atts['title'] );
		$agent = sanitize_text_field( $atts['agent'] );
		?>
		<div class="tc-embedded-chat-wrap" data-agent="<?php echo esc_attr( $agent ); ?>">
			<div class="tc-card" style="max-width: 580px; margin: 20px auto; border-radius: 18px; overflow: hidden; padding: 0; box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12); border: 1px solid rgba(245, 158, 11, 0.3);">
				<div style="background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); color: #fff; padding: 16px 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
					<span style="font-size: 22px;">🛕</span> <?php echo esc_html( $title ); ?>
				</div>
				<div style="padding: 18px 20px; background: #ffffff; font-size: 14px; color: #1e293b; line-height: 1.6;">
					<?php echo esc_html( get_option( 'tc_agents_widget_greeting', 'Namaste! 🙏 Welcome to TripCosmos — your Varanasi spiritual & tour guide. Looking for Kashi Vishwanath darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova/Dzire), hotel bookings, or evening Ganga Aarti boat rides? How can I assist you today?' ) ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output GTM container snippet in <head>.
	 */
	public static function render_gtm_head() {
		$gtm_id = esc_attr( trim( (string) get_option( 'tc_agents_gtm_container_id', '' ) ) );
		if ( empty( $gtm_id ) ) {
			return;
		}
		?>
<!-- Google Tag Manager (TripCosmos Agents) -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo $gtm_id; ?>');</script>
<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * Output GTM noscript container snippet immediately after <body>.
	 */
	public static function render_gtm_body() {
		$gtm_id = esc_attr( trim( (string) get_option( 'tc_agents_gtm_container_id', '' ) ) );
		if ( empty( $gtm_id ) ) {
			return;
		}
		?>
<!-- Google Tag Manager (noscript) (TripCosmos Agents) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo $gtm_id; ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * Inject high-converting WhatsApp & Call action boxes into all 2,200+ blog posts.
	 *
	 * @param string $content Post HTML.
	 * @return string Modified content with conversion cards.
	 */
	public static function inject_in_post_whatsapp_cta( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$title   = get_the_title();
		$wa_num  = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '' ) );
		if ( empty( $wa_num ) ) {
			$wa_num = '919336116210';
		}
		$phone = '+919336116210';

		$is_dev_diwali = ( stripos( $title, 'Dev Diwali' ) !== false || stripos( $title, 'Dev Deepawali' ) !== false );
		$is_boat       = ( stripos( $title, 'Boat' ) !== false || stripos( $title, 'Cruise' ) !== false || stripos( $title, 'Bajra' ) !== false );
		$is_cab        = ( stripos( $title, 'Cab' ) !== false || stripos( $title, 'Tempo' ) !== false || stripos( $title, 'Traveller' ) !== false );

		if ( $is_dev_diwali ) {
			$badge_text  = '🪔 Dev Deepawali 2026 Special Advance Desk';
			$cta_heading = 'Reserve Dev Deepawali Boat, Bajra & VIP Passes';
			$cta_desc    = 'Boat permits & reserved ghat seating sell out 90 days before the festival. Talk directly with our Kashi local coordinators on WhatsApp for live pricing, boat options & transparent net rates.';
			$pre_msg     = "Namaste TripCosmos! 🙏 I am reading '{$title}'. Please share available boat options (Shared Motor Boat / Bajra / Luxury Cruise) and prices for Dev Deepawali 2026.";
		} elseif ( $is_boat ) {
			$badge_text  = '⛵ Official Ganga Boat & Aarti Desk';
			$cta_heading = 'Book Private Morning & Aarti Boat Rides';
			$cta_desc    = 'Avoid chaotic ghat bargaining. Lock in verified CNG motorboats, Maharaja Bajras, and reserved Dashashwamedh Aarti spots with verified local boat captains.';
			$pre_msg     = "Namaste TripCosmos! 🙏 I want to book a boat ride in Varanasi ('{$title}'). Please share availability and rates.";
		} elseif ( $is_cab ) {
			$badge_text  = '🚗 Verified Outstation & Local Fleet Desk';
			$cta_heading = 'Get Instant Cab / Tempo Traveller Net Rates';
			$cta_desc    = 'Clean commercial AC Sedans, Innova Crysta, and 12-26 seater Tempo Travellers for Kashi, Ayodhya, Prayagraj, Gaya, and Naimisharanya with polite local chauffeurs.';
			$pre_msg     = "Namaste TripCosmos! 🙏 I need verified vehicle rates for '{$title}'. Please share vehicle options and instant quote.";
		} else {
			$badge_text  = '🛕 Verified Kashi & Ayodhya Pilgrimage Desk';
			$cta_heading = 'Plan Your Yatra with Local Varanasi Experts';
			$cta_desc    = 'VIP Sugam Darshan passes, verified outstation cabs, Ganga aarti boat allocations, and clean hotel stays — fully coordinated by our on-ground team.';
			$pre_msg     = "Namaste TripCosmos! 🙏 I am planning a trip based on '{$title}'. Please share complete itinerary and cost breakdown.";
		}

		$wa_link = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $pre_msg );

		$card_html = '
		<div class="tc-in-post-cta" style="margin: 32px 0; padding: 22px 24px; border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: #ffffff; box-shadow: 0 16px 32px rgba(15, 23, 42, 0.22); border: 1.5px solid rgba(245, 158, 11, 0.45); font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
				<span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.18); border: 1px solid rgba(245, 158, 11, 0.5); color: #fde047; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;">
					' . esc_html( $badge_text ) . '
				</span>
				<span style="font-size: 12px; color: #94a3b8; font-weight: 500;">
					🟢 Online • Replies in &lt; 2 Mins
				</span>
			</div>
			<div role="heading" aria-level="3" style="font-size: 19px; font-weight: 800; color: #ffffff; margin: 0 0 8px 0; line-height: 1.35;">
				' . esc_html( $cta_heading ) . '
			</div>
			<p style="font-size: 14px; color: #cbd5e1; margin: 0 0 18px 0; line-height: 1.6;">
				' . esc_html( $cta_desc ) . '
			</p>
			<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
				<a href="' . esc_url( $wa_link ) . '" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #22c55e; color: #ffffff; font-weight: 800; font-size: 14.5px; padding: 12px 20px; border-radius: 10px; text-decoration: none; box-shadow: 0 6px 18px rgba(34, 197, 94, 0.35);">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.18-.545-1.898-.787-3.13-2.73-3.224-2.855-.094-.127-.775-1.03-.775-1.965 0-.934.489-1.393.663-1.583.175-.19.38-.238.508-.238.127 0 .254.002.365.008.117.006.273-.044.428.328.16.383.548 1.339.596 1.436.048.098.08.212.015.339-.064.127-.095.207-.19.317-.095.111-.2.248-.286.333-.095.096-.195.2-.084.391.111.19.493.813 1.056 1.314.726.645 1.338.845 1.53.94.19.096.302.08.413-.048.111-.127.476-.556.603-.746.127-.19.254-.159.429-.095.174.064 1.111.524 1.302.619.19.096.317.143.365.223.048.079.048.461-.096.866z"/></svg>
					<span>Chat on WhatsApp</span>
				</a>
				<a href="tel:' . esc_attr( $phone ) . '" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: rgba(255, 255, 255, 0.12); color: #ffffff; font-weight: 700; font-size: 14px; padding: 12px 18px; border-radius: 10px; text-decoration: none; border: 1px solid rgba(255, 255, 255, 0.25);">
					<span>📞 Call Desk (+91 93361 16210)</span>
				</a>
			</div>
			<div style="margin-top: 12px; font-size: 11.5px; color: #94a3b8; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
				<span>✓ No Advance for Itinerary</span>
				<span>✓ 100% Verified Fleet & Drivers</span>
				<span>✓ Direct Kashi Local Coordination</span>
			</div>
		</div>';

		if ( false !== strpos( $content, 'tc-in-post-cta' ) ) {
			return $content;
		}

		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $parts ) > 6 ) {
			$new_content = '';
			for ( $i = 0; $i < count( $parts ); $i++ ) {
				$new_content .= $parts[ $i ];
				if ( 5 === $i ) {
					$new_content .= $card_html;
				}
			}
			return $new_content;
		}

		return $content . $card_html;
	}
}
