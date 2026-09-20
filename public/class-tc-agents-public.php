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

		$clean_wa = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) );

		wp_localize_script(
			'tc-chat-widget-js',
			'tcChatWidget',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'tc-agents/v1/chat' ) ),
				'leadCaptureUrl' => esc_url_raw( rest_url( 'tc-agents/v1/lead-capture' ) ),
				'historyUrl'     => esc_url_raw( rest_url( 'tc-agents/v1/conversations' ) ),
				'title'          => get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ),
				'greeting'       => get_option( 'tc_agents_widget_greeting', 'Namaste! 🙏 Welcome to TripCosmos — your Varanasi spiritual & tour guide. Looking for Kashi Vishwanath darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova/Dzire), hotel bookings, or evening Ganga Aarti boat rides? How can I assist you today?' ),
				'primaryColor'   => get_option( 'tc_agents_widget_primary_color', '#ea580c' ),
				'whatsappNumber' => $clean_wa,
				'triggerCallUrl' => esc_url_raw( rest_url( 'tc-agents/v1/trigger-call' ) ),
				'voiceEnabled'   => '1' === (string) get_option( 'tc_agents_voice_enabled', '0' ),
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

		$title         = get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' );
		$subtitle      = get_option( 'tc_agents_widget_subtitle', 'Online • Varanasi Desk ✓' );
		$avatar_url    = get_option( 'tc_agents_bot_avatar', '' );
		$avatar_emoji  = get_option( 'tc_agents_bot_avatar_emoji', '🛕' );
		$greeting      = get_option( 'tc_agents_widget_greeting', 'Namaste! 🙏 Welcome to TripCosmos — your Varanasi spiritual & tour guide. Looking for Kashi Vishwanath darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova/Dzire), hotel bookings, or evening Ganga Aarti boat rides? How can I assist you today?' );
		$teaser_text   = get_option( 'tc_agents_launcher_teaser_text', 'Planning Varanasi, Ayodhya, or Prayagraj? Ask our AI Concierge for tours, cabs & ghat hotels!' );
		$side          = get_option( 'tc_agents_widget_side', 'right' );
		$primary_color = get_option( 'tc_agents_widget_primary_color', '#ea580c' );
		$second_color  = get_option( 'tc_agents_widget_secondary_color', '#9333ea' );
		$wa_num        = preg_replace( '/[^0-9]/', '', get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) );
		$direct_wa     = "https://wa.me/{$wa_num}?text=" . rawurlencode( 'Hi TripCosmos, I am browsing your site and would like help planning a Varanasi tour or outstation cab.' );

		// Custom Starter Prompts
		$default_starters = "🛕 Kashi Ayodhya Tour | Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package\n🚗 Outstation Cabs | Book outstation cab Dzire or Innova Crysta for Varanasi to Ayodhya\n⛵ Ganga Aarti Boat | Book private boat for evening Ganga Aarti at Dashashwamedh Ghat\n🏨 Ghat Hotels | Best ghat-view hotels in Varanasi near Kashi Vishwanath";
		$raw_starters     = get_option( 'tc_agents_starter_prompts', $default_starters );
		$starter_lines    = array_filter( array_map( 'trim', explode( "\n", $raw_starters ) ) );

		$side_class = ( 'left' === $side ) ? ' tc-side-left' : '';
		?>
		<!-- TripCosmos AI Agent Floating Widget -->
		<div id="tc-agent-widget-root" class="tc-widget-container<?php echo esc_attr( $side_class ); ?>" style="--tc-primary-color: <?php echo esc_attr( $primary_color ); ?>; --tc-secondary-color: <?php echo esc_attr( $second_color ); ?>;" aria-live="polite">

			<!-- Proactive Teaser Balloon -->
			<div id="tc-widget-teaser" class="tc-widget-teaser" style="display: none;" role="status">
				<button type="button" class="tc-teaser-close" id="tc-teaser-close" aria-label="Dismiss proactive tip">&times;</button>
				<div class="tc-teaser-body" id="tc-teaser-body">
					<span class="tc-teaser-icon">
						<?php if ( ! empty( $avatar_url ) ) : ?>
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="Bot Avatar" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover; vertical-align: middle;" />
						<?php else : ?>
							<?php echo esc_html( $avatar_emoji ); ?>
						<?php endif; ?>
					</span>
					<span class="tc-teaser-text"><?php echo esc_html( $teaser_text ); ?></span>
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
					<div class="tc-header-stripe"></div>
					<div class="tc-header-info">
						<div class="tc-avatar-circle">
							<?php if ( ! empty( $avatar_url ) ) : ?>
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;" />
							<?php else : ?>
								<?php echo esc_html( $avatar_emoji ); ?>
							<?php endif; ?>
						</div>
						<div>
							<h3 id="tc-widget-heading"><?php echo esc_html( $title ); ?></h3>
							<span class="tc-online-status"><span class="tc-dot"></span> <?php echo esc_html( $subtitle ); ?></span>
						</div>
					</div>
					<div class="tc-header-controls">
						<a href="<?php echo esc_url( $direct_wa ); ?>" target="_blank" rel="noopener" class="tc-btn-icon" title="Chat on WhatsApp" aria-label="Chat on WhatsApp">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.072-2.18-.545-1.898-.787-3.13-2.73-3.224-2.855-.094-.127-.775-1.03-.775-1.965 0-.934.489-1.393.663-1.583.175-.19.38-.238.508-.238.127 0 .254.002.365.008.117.006.273-.044.428.328.16.383.548 1.339.596 1.436.048.098.08.212.015.339-.064.127-.095.207-.19.317-.095.111-.2.248-.286.333-.095.096-.195.2-.084.391.111.19.493.813 1.056 1.314.726.645 1.338.845 1.53.94.19.096.302.08.413-.048.111-.127.476-.556.603-.746.127-.19.254-.159.429-.095.174.064 1.111.524 1.302.619.19.096.317.143.365.223.048.079.048.461-.096.866z"/>
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
						<button id="tc-widget-close" class="tc-btn-icon" aria-label="Close Chat Window" type="button">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
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
								<label for="tc-q-destination">Destination / Circuit</label>
								<input type="text" id="tc-q-destination" placeholder="e.g. Varanasi, Ayodhya, Prayagraj, Bodhgaya" />
							</div>
							<div class="tc-form-field">
								<label for="tc-q-month">Travel Dates / Month</label>
								<input type="text" id="tc-q-month" placeholder="e.g. Next week / Oct" />
							</div>
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
							⚡ Get Custom Itinerary & Cab Fare
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
									<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" />
								<?php else : ?>
									<?php echo esc_html( $avatar_emoji ); ?>
								<?php endif; ?>
							</div>
						</div>
						<h4 id="tc-voice-agent-title"><?php echo esc_html( $title ); ?></h4>
						<p id="tc-voice-status">Tap microphone to start live voice call</p>
						<canvas id="tc-voice-visualizer" width="280" height="60"></canvas>
						<div id="tc-voice-live-transcript" class="tc-voice-transcript">"Namaste! Ask me about Kashi darshan, Ayodhya, cabs, or hotel bookings..."</div>

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
							<span class="tc-tab-label">Guide</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="quote" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">📋</span>
							<span class="tc-tab-label">Quote & Cab</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="voice" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">🎙️</span>
							<span class="tc-tab-label">WebCall</span>
						</button>
						<button type="button" class="tc-tab-btn" data-tab="history" role="tab" aria-selected="false">
							<span class="tc-tab-bar"></span>
							<span class="tc-tab-icon">📜</span>
							<span class="tc-tab-label">History</span>
						</button>
					</nav>

					<!-- Micro-footer Brand -->
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
}
