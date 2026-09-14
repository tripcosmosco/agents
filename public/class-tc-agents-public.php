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
		add_shortcode( 'tripcosmos_chat', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Enqueue public styles and scripts.
	 */
	public static function enqueue_assets() {
		// Only enqueue if widget is enabled
		if ( '1' !== (string) get_option( 'tc_agents_widget_enabled', '1' ) ) {
			return;
		}

		wp_enqueue_style(
			'tc-chat-widget-css',
			TC_AGENTS_URL . 'public/css/tc-chat-widget.css',
			array(),
			TC_AGENTS_VERSION
		);

		wp_enqueue_script(
			'tc-chat-widget-js',
			TC_AGENTS_URL . 'public/js/tc-chat-widget.js',
			array(),
			TC_AGENTS_VERSION,
			true
		);

		$clean_wa = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) );

		wp_localize_script(
			'tc-chat-widget-js',
			'tcChatWidget',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'tc-agents/v1/chat' ) ),
				'leadCaptureUrl' => esc_url_raw( rest_url( 'tc-agents/v1/lead-capture' ) ),
				'historyUrl'     => esc_url_raw( rest_url( 'tc-agents/v1/conversations' ) ),
				'title'          => get_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' ),
				'greeting'       => get_option( 'tc_agents_widget_greeting', 'Hi there! Looking for an unforgettable trek or adventure package? How can I help you plan today?' ),
				'primaryColor'   => get_option( 'tc_agents_widget_primary_color', '#0ea5e9' ),
				'whatsappNumber' => $clean_wa,
				'siteUrl'        => home_url(),
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

		$title        = get_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' );
		$greeting     = get_option( 'tc_agents_widget_greeting', 'Hi there! Looking for an unforgettable trek or adventure package? How can I help you plan today?' );
		$color        = get_option( 'tc_agents_widget_primary_color', '#0ea5e9' );
		$wa_num       = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) );
		$direct_wa    = "https://wa.me/{$wa_num}?text=" . rawurlencode( 'Hi TripCosmos, I am browsing your site and would like help planning a trip.' );
		?>
		<!-- TripCosmos AI Agent Floating Widget -->
		<div id="tc-agent-widget-root" class="tc-widget-container" style="--tc-primary-color: <?php echo esc_attr( $color ); ?>;" aria-live="polite">

			<!-- Proactive Teaser Balloon -->
			<div id="tc-widget-teaser" class="tc-widget-teaser" style="display: none;" role="status">
				<button type="button" class="tc-teaser-close" id="tc-teaser-close" aria-label="Dismiss proactive tip">&times;</button>
				<div class="tc-teaser-body" id="tc-teaser-body">
					<span class="tc-teaser-icon">🏔️</span>
					<span class="tc-teaser-text">Planning a Himalayan trek? Ask our AI guide for routes & live group discounts!</span>
				</div>
			</div>

			<!-- Floating Trigger Button -->
			<button id="tc-widget-trigger" class="tc-widget-fab" aria-label="Open TripCosmos AI Chat" type="button">
				<span class="tc-fab-icon-chat">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
					</svg>
				</span>
				<span class="tc-fab-icon-close" style="display: none;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</span>
				<span class="tc-status-pulse"></span>
			</button>

			<!-- Chat Window Dialog -->
			<div id="tc-widget-window" class="tc-widget-window" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="tc-widget-heading">
				<!-- Header -->
				<div class="tc-widget-header">
					<div class="tc-header-info">
						<div class="tc-avatar-circle">🏔️</div>
						<div>
							<h3 id="tc-widget-heading"><?php echo esc_html( $title ); ?></h3>
							<span class="tc-online-status"><span class="tc-dot"></span> Online &bull; Official Guide</span>
						</div>
					</div>
					<div class="tc-header-controls">
						<a href="<?php echo esc_url( $direct_wa ); ?>" target="_blank" rel="noopener" class="tc-btn-icon" title="Chat on WhatsApp" aria-label="Chat on WhatsApp">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.18-.545-1.898-.787-3.13-2.73-3.224-2.855-.094-.127-.775-1.03-.775-1.965 0-.934.489-1.393.663-1.583.175-.19.38-.238.508-.238.127 0 .254.002.365.008.117.006.273-.044.428.328.16.383.548 1.339.596 1.436.048.098.08.212.015.339-.064.127-.095.207-.19.317-.095.111-.2.248-.286.333-.095.096-.195.2-.084.391.111.19.493.813 1.056 1.314.726.645 1.338.845 1.53.94.19.096.302.08.413-.048.111-.127.476-.556.603-.746.127-.19.254-.159.429-.095.174.064 1.111.524 1.302.619.19.096.317.143.365.223.048.079.048.461-.096.866z"/>
							</svg>
						</a>
						<button id="tc-widget-close" class="tc-btn-icon" aria-label="Close Chat Window" type="button">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
					</div>
				</div>

				<!-- Tabs Navigation -->
				<div class="tc-widget-tabs">
					<button type="button" class="tc-tab-btn active" data-tab="chat">💬 Live Guide</button>
					<button type="button" class="tc-tab-btn" data-tab="quote">📋 Get Fast Itinerary</button>
				</div>

				<!-- Tab 1: Messages Stream -->
				<div id="tc-widget-messages" class="tc-messages-container">
					<!-- Welcome Message -->
					<div class="tc-chat-bubble tc-bubble-bot">
						<div class="tc-bubble-text"><?php echo nl2br( esc_html( $greeting ) ); ?></div>
					</div>

					<!-- Quick Starter Prompts -->
					<div class="tc-chips-container" id="tc-starter-chips">
						<button type="button" class="tc-chip" data-query="Recommend top Himalayan treks for beginners">🏔️ Beginner Treks</button>
						<button type="button" class="tc-chip" data-query="What are the best winter snow treks in Uttarakhand?">❄️ Winter Treks</button>
						<button type="button" class="tc-chip" data-query="How do I get a custom group tour quotation?">👥 Group Booking</button>
					</div>
				</div>

				<!-- Tab 2: Fast Lead Capture Quote Panel -->
				<div id="tc-widget-quote-panel" class="tc-quote-panel" style="display: none;">
					<div class="tc-quote-heading">
						<h4>Custom Itinerary & Group Quote</h4>
						<p>Tell us what you are looking for and our expedition team will personalize routes and pricing.</p>
					</div>
					<form id="tc-quote-form" class="tc-quote-form">
						<div class="tc-form-field">
							<label for="tc-q-name">Your Full Name *</label>
							<input type="text" id="tc-q-name" required placeholder="e.g. Rahul Sharma" autocomplete="name" />
						</div>
						<div class="tc-form-field">
							<label for="tc-q-phone">WhatsApp Number *</label>
							<input type="tel" id="tc-q-phone" required placeholder="+91 98765 43210" autocomplete="tel" />
						</div>
						<div class="tc-form-field">
							<label for="tc-q-email">Email Address</label>
							<input type="email" id="tc-q-email" placeholder="rahul@example.com" autocomplete="email" />
						</div>
						<div class="tc-form-row">
							<div class="tc-form-field">
								<label for="tc-q-destination">Destination / Trek</label>
								<input type="text" id="tc-q-destination" placeholder="e.g. Kedarkantha, Spiti" />
							</div>
							<div class="tc-form-field">
								<label for="tc-q-month">Month</label>
								<input type="text" id="tc-q-month" placeholder="e.g. Dec / Jan" />
							</div>
						</div>
						<div class="tc-form-field">
							<label for="tc-q-group">Travel Group Size</label>
							<select id="tc-q-group">
								<option value="Solo Traveler">Solo Traveler</option>
								<option value="2-4 Pax">Small Group (2-4 Pax)</option>
								<option value="5-10 Pax (Group Discount)">Group (5-10 Pax &bull; Group Discount)</option>
								<option value="10+ Pax (Corporate / Large)">Large Group / Corporate (10+ Pax)</option>
							</select>
						</div>
						<button type="submit" id="tc-q-submit" class="tc-quote-submit-btn">
							⚡ Get Custom Itinerary & Pricing
						</button>
					</form>
					<div id="tc-quote-success" class="tc-quote-success" style="display: none;">
						<div class="tc-success-icon">✓</div>
						<h5>Inquiry Synced Successfully!</h5>
						<p>Our expedition lead will reach out on WhatsApp. Switching you back to live guide chat...</p>
					</div>
				</div>

				<!-- Footer Input -->
				<div class="tc-widget-footer">
					<form id="tc-widget-form" autocomplete="off">
						<input type="text" id="tc-widget-input" placeholder="Ask about treks, itineraries, pricing..." aria-label="Type your message" />
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
					<div class="tc-footer-subtext">
						<span>⚡ Powered by TripCosmos AI</span>
						<a href="<?php echo esc_url( $direct_wa ); ?>" target="_blank" rel="noopener">Talk to human &rarr;</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render inline embedded chat shortcode [tripcosmos_chat agent="slug" title="Title"].
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'agent' => 'tripcosmos-guide',
				'title' => get_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' ),
			),
			$atts,
			'tripcosmos_chat'
		);

		ob_start();
		$title = sanitize_text_field( $atts['title'] );
		$agent = sanitize_text_field( $atts['agent'] );
		?>
		<div class="tc-embedded-chat-wrap" data-agent="<?php echo esc_attr( $agent ); ?>">
			<div class="tc-card" style="max-width: 580px; margin: 20px auto; border-radius: 16px; overflow: hidden; padding: 0;">
				<div style="background: #0f172a; color: #fff; padding: 14px 18px; font-weight: 700;">
					🏔️ <?php echo esc_html( $title ); ?>
				</div>
				<div style="padding: 16px; background: #f8fafc; font-size: 13.5px;">
					<?php echo esc_html( get_option( 'tc_agents_widget_greeting', 'Hi! How can I help you plan your Himalayan expedition?' ) ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
