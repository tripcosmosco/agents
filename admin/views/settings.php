<?php
/**
 * Admin Settings View - Modernized High-End Experience.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$widget_enabled  = (string) get_option( 'tc_agents_widget_enabled', '1' );
$is_widget_live  = ( '1' === $widget_enabled );
$mobile_token    = class_exists( 'TC_Agents_Mobile_API' ) ? TC_Agents_Mobile_API::get_api_token() : get_option( 'tc_agents_mobile_api_token', '' );
$api_base_url    = rest_url( 'tc-agents/v1' );

// Build mobile pairing payload for QR code
$qr_payload = rawurlencode( wp_json_encode( array(
	'url'   => $api_base_url,
	'token' => $mobile_token,
	'name'  => 'TripCosmos Live CRM',
) ) );
$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=' . $qr_payload;
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-settings-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1240px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-settings-hero {
	background: linear-gradient(135deg, #0a0f1d 0%, #111e38 60%, #1e1b4b 100%);
	border-radius: 20px;
	padding: 28px 36px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(10, 15, 29, 0.25);
	margin-bottom: 28px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	border: 1px solid rgba(245, 158, 11, 0.2);
	position: relative;
	overflow: hidden;
}
.tc-settings-hero::before {
	content: '';
	position: absolute;
	top: -50px;
	right: -50px;
	width: 240px;
	height: 240px;
	background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, transparent 70%);
	pointer-events: none;
}
.tc-settings-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-settings-hero p {
	margin: 0;
	color: #94a3b8;
	font-size: 14px;
	max-width: 650px;
	line-height: 1.5;
}
.tc-badge-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #0f172a;
	font-weight: 800;
	font-size: 11px;
	padding: 4px 10px;
	border-radius: 20px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.tc-card-lux {
	background: #ffffff;
	border-radius: 16px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
	padding: 24px 28px;
	margin-bottom: 24px;
	transition: all 0.2s ease;
}
.tc-card-lux:hover {
	box-shadow: 0 8px 26px rgba(0, 0, 0, 0.07);
	border-color: #cbd5e1;
}
.tc-card-title {
	font-size: 18px;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 6px 0;
	display: flex;
	align-items: center;
	gap: 10px;
}
.tc-card-desc {
	margin: 0 0 20px 0;
	color: #64748b;
	font-size: 13px;
	line-height: 1.5;
}

.tc-input-lux, select.tc-input-lux, textarea.tc-input-lux {
	width: 100%;
	max-width: 580px;
	padding: 10px 14px;
	border: 1.5px solid #cbd5e1;
	border-radius: 10px;
	font-size: 13.5px;
	color: #1e293b;
	background: #f8fafc;
	transition: all 0.2s ease;
	outline: none;
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02);
}
.tc-input-lux:focus, select.tc-input-lux:focus, textarea.tc-input-lux:focus {
	border-color: #f59e0b;
	background: #ffffff;
	box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

.tc-grid-2 {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 24px;
}
@media (max-width: 900px) {
	.tc-grid-2 { grid-template-columns: 1fr; }
}

.tc-mobile-pairing-box {
	background: linear-gradient(135deg, #091428 0%, #152744 100%);
	border-radius: 16px;
	padding: 24px;
	color: #ffffff;
	border: 1px solid rgba(245, 158, 11, 0.3);
	display: flex;
	flex-wrap: wrap;
	gap: 24px;
	align-items: center;
}
.tc-qr-card {
	background: #ffffff;
	padding: 12px;
	border-radius: 14px;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
	display: inline-flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
}
.tc-qr-card img {
	width: 150px;
	height: 150px;
	display: block;
	border-radius: 8px;
}
.tc-qr-caption {
	margin-top: 8px;
	font-size: 11px;
	font-weight: 700;
	color: #0f172a;
}
.tc-token-field {
	background: rgba(0, 0, 0, 0.35);
	border: 1px dashed rgba(245, 158, 11, 0.4);
	padding: 10px 14px;
	border-radius: 8px;
	font-family: monospace;
	font-size: 13px;
	color: #fcd34d;
	display: flex;
	align-items: center;
	justify-content: space-between;
	max-width: 460px;
	margin-top: 6px;
}
.tc-btn-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #ffffff !important;
	border: none;
	font-weight: 700;
	padding: 12px 26px;
	border-radius: 10px;
	cursor: pointer;
	box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	text-decoration: none;
}
.tc-btn-gold:hover {
	transform: translateY(-1px);
	box-shadow: 0 6px 18px rgba(245, 158, 11, 0.4);
	color: #ffffff;
}
.tc-btn-outline {
	background: transparent;
	color: #cbd5e1;
	border: 1px solid rgba(255, 255, 255, 0.2);
	font-weight: 600;
	padding: 7px 14px;
	border-radius: 8px;
	cursor: pointer;
	font-size: 12px;
	transition: all 0.2s ease;
}
.tc-btn-outline:hover {
	background: rgba(255, 255, 255, 0.1);
	color: #ffffff;
	border-color: #ffffff;
}
</style>

<div class="wrap tc-settings-wrap">
	<!-- Hero Header -->
	<div class="tc-settings-hero">
		<div>
			<h1>
				<span>⚙️</span>
				System &amp; Companion Settings
				<span class="tc-badge-gold">Production Desk</span>
			</h1>
			<p>Configure the frontend lead-capturing AI concierge, mobile companion pairing for field staff, WhatsApp notification routing, and GitHub auto-updates.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents' ) ); ?>" class="tc-btn-outline">
				← Executive Dashboard
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; color: #065f46; font-weight: 600; display: flex; align-items: center; gap: 10px;">
			<span>✓</span> Settings saved successfully. Changes are now active across web and mobile APIs.
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-settings' ) ); ?>">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_settings" />

		<!-- 0. UNIVERSAL INDUSTRY PRESETS (1-CLICK BUSINESS SETUP) -->
		<?php
		$presets = class_exists( 'TC_Agents_Presets' ) ? TC_Agents_Presets::get_presets() : array();
		$active_preset = get_option( 'tc_agents_active_preset', 'travel' );
		?>
		<div class="tc-card-lux" style="border-left: 5px solid #8b5cf6;">
			<div class="tc-card-title">
				<span>🌐</span>
				Universal Industry Presets
				<span class="tc-badge-gold" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; font-size: 10px;">1-Click Mode</span>
			</div>
			<p class="tc-card-desc">
				Transform TripCosmos Agents into a tailored AI platform for any business. Switching an industry preset automatically installs curated personas, custom prompts, appropriate tool mappings, and branded greetings.
			</p>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-top: 18px;">
				<?php foreach ( $presets as $p_id => $p ) : 
					$is_curr = ( $p_id === $active_preset );
				?>
					<div style="background: <?php echo $is_curr ? 'rgba(139, 92, 246, 0.08)' : '#f8fafc'; ?>; border: 1.5px solid <?php echo $is_curr ? '#8b5cf6' : '#e2e8f0'; ?>; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
						<?php if ( $is_curr ) : ?>
							<span style="position: absolute; top: 10px; right: 10px; background: #8b5cf6; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 7px; border-radius: 10px; text-transform: uppercase;">Active</span>
						<?php endif; ?>
						<div>
							<div style="font-size: 22px; margin-bottom: 6px;">
								<span class="dashicons <?php echo esc_attr( $p['icon'] ); ?>" style="font-size: 22px; width: 22px; height: 22px; color: <?php echo esc_attr( $p['primary_color'] ); ?>;"></span>
							</div>
							<div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
								<?php echo esc_html( $p['title'] ); ?>
							</div>
							<div style="font-size: 11.5px; color: #64748b; line-height: 1.4; margin-bottom: 14px;">
								<?php echo esc_html( $p['tagline'] ); ?>
							</div>
						</div>
						<div>
							<button type="button" 
								class="tc-btn-apply-preset" 
								data-preset="<?php echo esc_attr( $p_id ); ?>" 
								data-title="<?php echo esc_attr( $p['title'] ); ?>"
								style="width: 100%; border-radius: 8px; font-size: 12px; font-weight: 700; padding: 7px 12px; cursor: pointer; border: 1px solid <?php echo $is_curr ? '#8b5cf6' : '#cbd5e1'; ?>; background: <?php echo $is_curr ? '#8b5cf6' : '#ffffff'; ?>; color: <?php echo $is_curr ? '#ffffff' : '#334155'; ?>;">
								<?php echo $is_curr ? '✓ Current Mode' : 'Apply Preset'; ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- 1. ANDROID COMPANION APP PAIRING CARD -->
		<div class="tc-card-lux" style="border-left: 5px solid #f59e0b;">
			<div class="tc-card-title">
				<span>📱</span>
				X-Agents Android Companion Pairing
				<span class="tc-badge-gold" style="font-size: 10px;">Zero-Touch Sync</span>
			</div>
			<p class="tc-card-desc">
				Pair the executive Android app with this WordPress instance to enable real-time visitor alerts, 1-tap Jio auto-dialer, instant push notifications on newly captured leads, and live WhatsApp conversation feeds.
			</p>

			<div class="tc-mobile-pairing-box">
				<div class="tc-qr-card">
					<img src="<?php echo esc_url( $qr_image_url ); ?>" alt="Companion App Pairing QR Code" />
					<div class="tc-qr-caption">Scan in Android App</div>
				</div>

				<div style="flex: 1; min-width: 280px;">
					<div style="font-size: 15px; font-weight: 700; color: #ffffff; margin-bottom: 4px;">
						Pairing Credentials
					</div>
					<div style="color: #94a3b8; font-size: 12.5px; margin-bottom: 14px;">
						Use the QR code above or manually enter these parameters into <strong>Settings &gt; Server Sync</strong> in the mobile app:
					</div>

					<div style="margin-bottom: 10px;">
						<label style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700;">Rest API Base URL</label>
						<div class="tc-token-field">
							<span id="tc-api-url-text"><?php echo esc_html( $api_base_url ); ?></span>
							<button type="button" class="tc-btn-outline" onclick="navigator.clipboard.writeText('<?php echo esc_js( $api_base_url ); ?>'); alert('Copied API URL to clipboard!');">Copy</button>
						</div>
					</div>

					<div style="margin-bottom: 14px;">
						<label style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700;">Secret Mobile Pairing Token</label>
						<div class="tc-token-field">
							<input type="password" id="tc-mobile-token-val" name="mobile_api_token" value="<?php echo esc_attr( $mobile_token ); ?>" style="background: transparent; border: none; color: #fcd34d; font-family: monospace; font-size: 13px; outline: none; width: 280px;" />
							<div style="display: flex; gap: 6px;">
								<button type="button" class="tc-btn-outline" onclick="var el=document.getElementById('tc-mobile-token-val'); el.type = (el.type==='password'?'text':'password');">Reveal</button>
								<button type="button" class="tc-btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('tc-mobile-token-val').value); alert('Copied secret token to clipboard!');">Copy</button>
							</div>
						</div>
					</div>

					<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
						<label style="color: #94a3b8; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
							<input type="checkbox" name="regenerate_mobile_token" value="1" />
							Generate a new random 32-character secret token upon save
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- 2. FRONTEND CHATBOT VISIBILITY TOGGLE -->
		<div class="tc-card-lux" style="border-left: 5px solid <?php echo $is_widget_live ? '#10b981' : '#ef4444'; ?>; background: <?php echo $is_widget_live ? '#f0fdf4' : '#fef2f2'; ?>;">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
				<div>
					<div class="tc-card-title">
						<span>💬</span>
						Frontend AI Chatbot Visibility
						<span style="font-size: 11px; padding: 4px 10px; border-radius: 12px; font-weight: 700; background: <?php echo $is_widget_live ? '#dcfce7; color: #15803d;' : '#fee2e2; color: #b91c1c;'; ?>">
							<?php echo $is_widget_live ? '● ACTIVE & VISIBLE ON LIVE SITE' : '○ DISABLED & HIDDEN'; ?>
						</span>
					</div>
					<p class="tc-card-desc" style="margin: 0; max-width: 650px;">
						Controls whether the floating AI assistant, quotation request modal, and voice consultation bubble are injected into your live pages. When disabled, all frontend scripts and telemetry pings are unhooked.
					</p>
				</div>
				<div style="display: flex; align-items: center; gap: 14px;">
					<button type="button" class="button button-large <?php echo $is_widget_live ? 'button-secondary' : 'button-primary'; ?>" id="tc-quick-toggle-widget-btn" style="height: 42px; font-weight: 700; border-radius: 8px;">
						<?php echo $is_widget_live ? '🔴 Click to Disable Chatbot' : '🟢 Click to Enable Chatbot'; ?>
					</button>
					<input type="hidden" name="widget_enabled" id="widget_enabled_input" value="<?php echo esc_attr( $widget_enabled ); ?>" />
				</div>
			</div>
		</div>

		<!-- 3. BRAND IDENTITY & CUSTOMIZATION -->
		<div class="tc-card-lux">
			<div class="tc-card-title">
				<span>🎨</span>
				Frontend Chatbot Identity &amp; Aesthetic
			</div>
			<p class="tc-card-desc">
				Customize the profile picture, assistant name, subtitle, greeting message, proactive teaser bubble, and brand gradients.
			</p>

			<table class="form-table">
				<!-- Bot Avatar -->
				<tr>
					<th scope="row"><label for="tc_widget_avatar">Assistant Profile Photo</label></th>
					<td>
						<?php 
						$bot_avatar = get_option( 'tc_agents_bot_avatar', '' ); 
						$bot_avatar_emoji = get_option( 'tc_agents_bot_avatar_emoji', '🛕' );
						?>
						<div style="display: flex; align-items: center; gap: 18px; margin-bottom: 10px;">
							<div id="tc-bot-avatar-preview" style="width: 64px; height: 64px; border-radius: 18px; border: 2px solid #cbd5e1; background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(147,51,234,0.2)); display: flex; align-items: center; justify-content: center; font-size: 30px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
								<?php if ( ! empty( $bot_avatar ) ) : ?>
									<img src="<?php echo esc_url( $bot_avatar ); ?>" alt="Bot Avatar" style="width: 100%; height: 100%; object-fit: cover;" />
								<?php else : ?>
									<span id="tc-bot-avatar-emoji-preview"><?php echo esc_html( $bot_avatar_emoji ); ?></span>
								<?php endif; ?>
							</div>
							<div>
								<input type="hidden" name="widget_avatar" id="tc_widget_avatar" value="<?php echo esc_attr( $bot_avatar ); ?>" />
								<button type="button" class="button button-secondary" id="tc-upload-bot-avatar" style="border-radius: 8px;">
									<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span> Select Image from Media Library
								</button>
								<button type="button" class="button button-link-delete" id="tc-remove-bot-avatar" style="<?php echo empty( $bot_avatar ) ? 'display:none;' : ''; ?> margin-left: 8px;">
									Remove Image
								</button>
								<p class="description" style="margin-top: 6px;">Upload a 1:1 square photo (e.g. guide portrait or company mascot).</p>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_avatar_emoji">Fallback Avatar Emoji</label></th>
					<td>
						<input type="text" name="widget_avatar_emoji" id="widget_avatar_emoji" class="tc-input-lux" style="width: 90px; text-align: center; font-size: 20px;" value="<?php echo esc_attr( $bot_avatar_emoji ); ?>" maxlength="4" />
						<span class="description" style="margin-left: 10px;">Shown if no custom avatar photo is selected (e.g. 🛕, 👳, 🕉️, 🚗, ✈️).</span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_title">Assistant Display Name</label></th>
					<td>
						<input type="text" name="widget_title" id="widget_title" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ) ); ?>" placeholder="e.g. TripCosmos Travel Desk" />
						<p class="description">Displayed in the header bar and voice consultation modal.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_subtitle">Status Line / Presence</label></th>
					<td>
						<input type="text" name="widget_subtitle" id="widget_subtitle" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_widget_subtitle', 'Online • Varanasi Desk ✓' ) ); ?>" placeholder="e.g. Online • Varanasi Desk ✓" />
						<p class="description">Shown under the title with pulsing green status.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_greeting">Welcome Greeting Message</label></th>
					<td>
						<textarea name="widget_greeting" id="widget_greeting" rows="3" class="tc-input-lux"><?php echo esc_textarea( get_option( 'tc_agents_widget_greeting', 'Namaste! 🙏 Welcome to TripCosmos — your Varanasi spiritual & tour guide. Looking for Kashi Vishwanath darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova/Dzire), hotel bookings, or evening Ganga Aarti boat rides? How can I assist you today?' ) ); ?></textarea>
						<p class="description">Initial proactive message presented when visitors open the conversation.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="launcher_teaser_text">Floating Teaser Balloon</label></th>
					<td>
						<input type="text" name="launcher_teaser_text" id="launcher_teaser_text" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_launcher_teaser_text', 'Planning Varanasi, Ayodhya, or Prayagraj? Ask our AI Concierge for tours, cabs & ghat hotels!' ) ); ?>" />
						<p class="description">Floating callout badge beside the chat bubble on frontend pages.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_side">Widget Screen Position</label></th>
					<td>
						<?php $side = get_option( 'tc_agents_widget_side', 'right' ); ?>
						<select name="widget_side" id="widget_side" class="tc-input-lux" style="width: 260px;">
							<option value="right" <?php selected( 'right', $side ); ?>>Bottom Right Corner (Standard)</option>
							<option value="left" <?php selected( 'left', $side ); ?>>Bottom Left Corner</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_primary_color">Brand Primary Color</label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 12px;">
							<input type="color" name="widget_primary_color" id="widget_primary_color" value="<?php echo esc_attr( get_option( 'tc_agents_widget_primary_color', '#ea580c' ) ); ?>" style="width: 44px; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;" />
							<span class="description">Primary radiant accent used for buttons, badges, and launcher glow (default: <code>#ea580c</code>).</span>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="widget_secondary_color">Brand Secondary Gradient</label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 12px;">
							<input type="color" name="widget_secondary_color" id="widget_secondary_color" value="<?php echo esc_attr( get_option( 'tc_agents_widget_secondary_color', '#9333ea' ) ); ?>" style="width: 44px; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;" />
							<span class="description">Gradient blend color for headers and active accents (default: <code>#9333ea</code>).</span>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="starter_prompts">Quick Starter Prompt Chips</label></th>
					<td>
						<?php 
						$default_starters = "🛕 Kashi Ayodhya Tour | Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package\n🚗 Outstation Cabs | Book outstation cab Dzire or Innova Crysta for Varanasi to Ayodhya\n⛵ Ganga Aarti Boat | Book private boat for evening Ganga Aarti at Dashashwamedh Ghat\n🏨 Ghat Hotels | Best ghat-view hotels in Varanasi near Kashi Vishwanath";
						$starters = get_option( 'tc_agents_starter_prompts', $default_starters );
						?>
						<textarea name="starter_prompts" id="starter_prompts" rows="4" class="tc-input-lux"><?php echo esc_textarea( $starters ); ?></textarea>
						<p class="description">One chip per line: <code>Chip Label | Prompt sent to agent</code></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 4. HUMAN ESCALATION & WHATSAPP ROUTING -->
		<div class="tc-card-lux">
			<div class="tc-card-title">
				<span>📱</span>
				Human Escalation &amp; WhatsApp Routing
			</div>
			<p class="tc-card-desc">
				When customers request direct human booking, urgent outstation cabs, or emergency assistance, leads route immediately to these channels.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="human_whatsapp_number">Official Team WhatsApp</label></th>
					<td>
						<input type="text" name="human_whatsapp_number" id="human_whatsapp_number" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_human_whatsapp_number', '' ) ); ?>" />
						<p class="description">Include country code (e.g. <code>+91XXXXXXXXXX</code>) for instant WhatsApp handoff.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="retention_days">Chat Retention (days)</label></th>
					<td>
						<input type="number" min="0" name="retention_days" id="retention_days" class="tc-input-lux" value="<?php echo esc_attr( (int) get_option( 'tc_agents_retention_days', 0 ) ); ?>" />
						<p class="description">Conversations with no activity for this many days are deleted automatically (minimum 30). <code>0</code> keeps everything.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="human_notification_email">Staff Alert Email</label></th>
					<td>
						<input type="email" name="human_notification_email" id="human_notification_email" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) ) ); ?>" />
						<p class="description">Receives instant email notifications upon high-value lead capture.</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 5. TWO-WAY GITHUB CODE SYNCHRONIZER & UPDATES -->
		<?php
		$gh_repo_info = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_repo() : array( 'slug' => 'tripcosmosco/TripCosmos.co-Agentd', 'owner' => 'tripcosmosco', 'repo' => 'TripCosmos.co-Agentd' );
		$gh_branch    = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_branch() : 'main';
		?>
		<div class="tc-card-lux" id="tc-github-updater-card">
			<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
				<div>
					<div class="tc-card-title">
						<span>🔄</span>
						Two-Way GitHub Code Synchronization
					</div>
					<p class="tc-card-desc" style="margin: 0; max-width: 680px;">
						Bidirectionally sync code between this WordPress site and GitHub. The system detects which code is newer, allowing you to <strong>push updates to GitHub</strong> when you customize code on WordPress, or <strong>pull &amp; update from GitHub</strong> when remote releases are published.
					</p>
				</div>
				<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
					<button type="button" id="tc-check-github-sync-btn" class="button button-secondary" style="border-radius: 8px; font-weight: 600; padding: 6px 14px; height: auto;">
						<span class="dashicons dashicons-randomize" style="margin-top: 2px;"></span> Compare Versions
					</button>
					<button type="button" id="tc-push-github-btn" class="button button-primary" style="display: none; border-radius: 8px; font-weight: 700; background: #0284c7; border-color: #0369a1; padding: 6px 16px; height: auto;">
						<span class="dashicons dashicons-upload" style="margin-top: 2px;"></span> Push to GitHub
					</button>
					<button type="button" id="tc-perform-github-update-btn" class="button button-primary" style="display: none; border-radius: 8px; font-weight: 700; background: #16a34a; border-color: #15803d; padding: 6px 16px; height: auto;">
						<span class="dashicons dashicons-download" style="margin-top: 2px;"></span> Update from GitHub
					</button>
				</div>
			</div>

			<!-- Comparison Display Card -->
			<div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 18px 24px; margin-bottom: 20px;">
				<!-- Local WordPress -->
				<div style="text-align: left;">
					<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700; margin-bottom: 4px;">
						🌐 WordPress Site (Local)
					</div>
					<div style="display: flex; align-items: center; gap: 10px;">
						<span style="font-size: 20px; font-weight: 800; color: #f8fafc;" id="tc-local-version-label">
							v<?php echo esc_html( TC_AGENTS_VERSION ); ?>
						</span>
						<span class="tc-badge-gold" style="font-size: 11px; padding: 3px 8px;">Active</span>
					</div>
					<div style="font-size: 12px; color: #64748b; margin-top: 4px;">
						Directory: <code>plugins/tripcosmos-agents</code>
					</div>
				</div>

				<!-- Center Sync Direction Indicator -->
				<div style="text-align: center; padding: 0 12px;">
					<div id="tc-sync-direction-badge" style="background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 6px 14px; font-size: 12px; font-weight: 600; color: #cbd5e1; display: inline-flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-leftright" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
						<span id="tc-sync-direction-text">Click "Compare Versions"</span>
					</div>
				</div>

				<!-- Remote GitHub -->
				<div style="text-align: right;">
					<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700; margin-bottom: 4px;">
						🐙 GitHub Repository (Remote)
					</div>
					<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
						<span style="font-size: 20px; font-weight: 800; color: #93c5fd;" id="tc-remote-version-label">
							--
						</span>
						<span style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 600;" id="tc-remote-branch-label">
							<?php echo esc_html( $gh_branch ); ?>
						</span>
					</div>
					<div style="font-size: 12px; color: #64748b; margin-top: 4px;">
						<a href="https://github.com/<?php echo esc_attr( $gh_repo_info['slug'] ); ?>" target="_blank" rel="noopener noreferrer" style="color: #60a5fa; text-decoration: none;">
							<?php echo esc_html( $gh_repo_info['slug'] ); ?> ↗
						</a>
					</div>
				</div>
			</div>

			<!-- Dynamic Status and Feedback Box -->
			<div id="tc-github-update-status-box" style="display: none; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;"></div>

			<!-- Commit Details Box (Populated on Check) -->
			<div id="tc-github-commit-card" style="display: none; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 12px;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
					<strong style="color: #e2e8f0;">Latest Remote Commit on GitHub:</strong>
					<a id="tc-commit-link" href="#" target="_blank" rel="noopener noreferrer" style="color: #38bdf8; font-family: monospace; font-weight: 700; text-decoration: none;"></a>
				</div>
				<div id="tc-commit-message" style="color: #cbd5e1; font-family: monospace; font-size: 12px; background: rgba(0,0,0,0.3); padding: 8px 12px; border-radius: 6px; white-space: pre-wrap; margin-bottom: 6px;"></div>
				<div style="display: flex; justify-content: space-between; color: #64748b; font-size: 11px;">
					<span id="tc-commit-author"></span>
					<span id="tc-commit-date"></span>
				</div>
			</div>

			<table class="form-table" style="margin-top: 0;">
				<tr>
					<th scope="row"><label for="github_repo">GitHub Repository</label></th>
					<td>
						<input type="text" name="github_repo" id="github_repo" class="tc-input-lux" value="<?php echo esc_attr( $gh_repo_info['slug'] ); ?>" placeholder="owner/repository (e.g. tripcosmosco/TripCosmos.co-Agentd)" style="max-width: 460px;" />
						<p class="description">Format: <code>owner/repository-name</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="github_branch">Git Branch</label></th>
					<td>
						<input type="text" name="github_branch" id="github_branch" class="tc-input-lux" value="<?php echo esc_attr( $gh_branch ); ?>" placeholder="main" style="max-width: 200px;" />
						<p class="description">Branch to push to and track (default: <code>main</code>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="github_token">GitHub Personal Access Token</label></th>
					<td>
						<input type="password" name="github_token" id="github_token" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'github_token' ) ?: 'ghp_••••••••••••••••••••' ); ?>" autocomplete="new-password" style="max-width: 460px;" />
						<?php if ( TC_Agents_Vault::has( 'github_token' ) ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle; margin-left: 6px;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small>
						<?php endif; ?>
						<p class="description" style="margin-top: 6px; line-height: 1.5;">
							To push or pull private repositories, generate a token on GitHub at <a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer">GitHub &rarr; Settings &rarr; Developer Settings &rarr; Personal access tokens</a>.<br />
							<strong style="color: #f59e0b;">Required permissions for two-way sync:</strong><br />
							&bull; <strong>Fine-grained token:</strong> Under <em>Repository permissions</em>, set <strong>Contents: Read and write</strong>.<br />
							&bull; <strong>Classic token:</strong> Check the <strong><code>repo</code></strong> scope (full control of private repositories).
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Submit Action -->
		<div style="margin-top: 24px;">
			<button type="submit" class="tc-btn-gold">
				<span>💾</span> Save All Settings &amp; Sync Mobile
			</button>
		</div>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var toggleBtn = document.getElementById('tc-quick-toggle-widget-btn');
	var enabledInput = document.getElementById('widget_enabled_input');
	if (toggleBtn && enabledInput) {
		toggleBtn.addEventListener('click', function() {
			var isCurrent = (enabledInput.value === '1');
			enabledInput.value = isCurrent ? '0' : '1';
			this.form.submit();
		});
	}

	// Avatar media uploader
	var uploadBtn = document.getElementById('tc-upload-bot-avatar');
	var removeBtn = document.getElementById('tc-remove-bot-avatar');
	var avatarInput = document.getElementById('tc_widget_avatar');
	var previewBox = document.getElementById('tc-bot-avatar-preview');
	var emojiFallback = document.getElementById('widget_avatar_emoji');

	if (uploadBtn && typeof wp !== 'undefined' && wp.media) {
		uploadBtn.addEventListener('click', function(e) {
			e.preventDefault();
			var frame = wp.media({
				title: 'Select Chatbot Avatar Photo',
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				avatarInput.value = attachment.url;
				previewBox.innerHTML = '<img src="' + attachment.url + '" style="width:100%;height:100%;object-fit:cover;" />';
				if (removeBtn) removeBtn.style.display = 'inline-block';
			});
			frame.open();
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function(e) {
			e.preventDefault();
			avatarInput.value = '';
			var emoji = emojiFallback ? emojiFallback.value : '🛕';
			previewBox.innerHTML = '<span>' + emoji + '</span>';
			this.style.display = 'none';
		});
	}
});
</script>
