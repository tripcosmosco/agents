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
$mobile_token    = get_option( 'tc_agents_mobile_api_token', 'a9P3cHjoyDq5Fkm0ucODllxnuuy35rXJ' );
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

		<!-- 2. WHATSAPP GATEWAY (EVOLUTION API) & LIVE PAIRING QR -->
		<div class="tc-card-lux" style="border-left: 5px solid #10b981;" id="tc-wa-gateway-card">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
				<div class="tc-card-title" style="margin: 0;">
					<span>💬</span>
					WhatsApp Gateway &amp; Live QR Reconnect (+91 93361 16210)
					<span class="tc-badge-gold" style="font-size: 10px; background: #0284c7; color: #fff;">Evolution API</span>
				</div>
				<span id="tc-evo-status-badge" style="background: #fee2e2; color: #991b1b; padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
					<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444;"></span> Checking Evolution Status...
				</span>
			</div>
			<p class="tc-card-desc">
				Central WhatsApp messaging engine powered by Evolution API (Instance: <code>TripCosmos.co</code> on <code>wa.vmstudio.digital</code>). Sends instant quotations, automated booking confirmations, and pilgrim customer care.
			</p>

			<div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 12.5px; color: #1e3a8a; line-height: 1.5;">
				<strong>Why does WhatsApp disconnect?</strong> Baileys multi-device sessions disconnect when:
				<ul style="margin: 4px 0 0 16px; padding: 0;">
					<li>1. The host phone (+91 93361 16210) is powered off, lacks internet, or WhatsApp logged out idle devices.</li>
					<li>2. The linked session was removed from WhatsApp &gt; Linked Devices on your phone.</li>
					<li>3. Evolution API socket at <code>wa.vmstudio.digital</code> restarted or encountered a timeout.</li>
				</ul>
				<div style="margin-top: 6px;"><em>Click <strong>"Generate QR Code to Scan"</strong> below to generate a new live pairing QR and reconnect in 5 seconds.</em></div>
			</div>

			<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px;">
				<button type="button" class="button button-primary" id="tc-btn-evo-qr" style="background: #10b981; border-color: #059669; font-weight: 700; height: 36px;">
					📱 Generate QR Code to Scan
				</button>
				<button type="button" class="button button-secondary" id="tc-btn-evo-check" style="font-weight: 600; height: 36px;">
					🔄 Check Live Status
				</button>
				<button type="button" class="button button-secondary" id="tc-btn-evo-restart" style="color: #b91c1c; border-color: #fca5a5; font-weight: 600; height: 36px;">
					⚡ Force Socket Reset &amp; Fresh QR
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>" class="button button-secondary" style="height: 36px; line-height: 34px; text-decoration: none;">
					⚙️ Full Integrations Settings &rarr;
				</a>
				<a href="https://wa.vmstudio.digital/manager" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 36px; line-height: 34px; text-decoration: none;">
					🌐 Open Evolution Manager UI &rarr;
				</a>
			</div>

			<div id="tc-evo-qr-container" style="display: none; text-align: center; padding: 24px; background: #ffffff; border-radius: 14px; border: 2px dashed #0284c7; margin-top: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
				<div id="tc-evo-qr-spinner" style="display: none; padding: 20px; font-size: 14px; color: #0284c7; font-weight: 700;">
					⏳ Generating live pairing QR from Evolution server...
				</div>
				<div id="tc-evo-qr-content" style="display: none;">
					<div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
						Scan with WhatsApp on Office Phone (+91 93361 16210)
					</div>
					<p style="font-size: 12.5px; color: #64748b; margin: 0 0 16px 0;">
						Open WhatsApp on your phone &rarr; Tap Settings or (⋮) &rarr; <strong>Linked Devices</strong> &rarr; <strong>Link a Device</strong> &rarr; Scan this code:
					</p>
					<img id="tc-evo-qr-img" src="" alt="WhatsApp QR Code" style="width: 250px; height: 250px; border-radius: 12px; border: 3px solid #10b981; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: inline-block;" />
					<div id="tc-evo-pairing-box" style="margin-top: 12px; font-size: 13.5px; font-weight: 700; color: #1e3a8a;"></div>
					<div style="margin-top: 12px; font-size: 11.5px; color: #94a3b8;">
						⏳ Auto-detecting scan... This page will update automatically once connected.
					</div>
				</div>
			</div>
		</div>

		<!-- 3. FRONTEND CHATBOT VISIBILITY TOGGLE -->
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

		<!-- 5. GOOGLE TAG MANAGER & CONVERSION TRACKING -->
		<div class="tc-card-lux" id="tc-gtm-settings-card">
			<div class="tc-card-title">
				<span>📊</span>
				Google Tag Manager (GTM) &amp; Lead Conversion Tracking
			</div>
			<p class="tc-card-desc">
				Track high-intent pilgrimage leads and ad conversions across Google Ads, Meta Ads, and GA4 directly from the AI Concierge, WhatsApp CTAs, and instant quote submissions.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">GTM Lead Tracing</th>
					<td>
						<label style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
							<input type="checkbox" name="gtm_enabled" value="1" <?php checked( '1', (string) get_option( 'tc_agents_gtm_enabled', '1' ) ); ?> />
							Enable DataLayer Event Tracing (Google Ads &amp; GA4 Conversions)
						</label>
						<p class="description">Automatically pushes <code>generate_lead</code>, <code>contact</code>, <code>begin_checkout</code>, and <code>tc_agent_*</code> events into <code>window.dataLayer</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gtm_container_id">GTM Container ID</label></th>
					<td>
						<input type="text" name="gtm_container_id" id="gtm_container_id" class="tc-input-lux" placeholder="GTM-XXXXXXX" value="<?php echo esc_attr( get_option( 'tc_agents_gtm_container_id', '' ) ); ?>" />
						<p class="description">Optional. If entered, the plugin automatically injects the Google Tag Manager container snippet in <code>&lt;head&gt;</code> and <code>&lt;body&gt;</code>. If your theme already includes GTM, leave this blank.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lead_conversion_value">Default Lead Value (INR)</label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 8px;">
							<span style="font-weight: 700; color: #64748b;">₹</span>
							<input type="number" min="0" step="50" name="lead_conversion_value" id="lead_conversion_value" class="tc-input-lux" style="max-width: 180px;" value="<?php echo esc_attr( (float) get_option( 'tc_agents_lead_conversion_value', 1500 ) ); ?>" />
							<span style="font-size: 12px; color: #64748b;">Estimated value sent to Google Ads conversion tag</span>
						</div>
					</td>
				</tr>
			</table>

			<!-- Interactive GTM Setup Guide Box -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-top: 14px;">
				<h4 style="margin: 0 0 10px 0; font-size: 13.5px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span>🎯</span> <strong>Recommended Google Tag Manager Setup for Google Ads:</strong>
				</h4>
				<ul style="margin: 0; padding-left: 20px; font-size: 12.5px; color: #475569; line-height: 1.6;">
					<li><strong>Trigger:</strong> Create a Trigger of type <code>Custom Event</code> with Event Name: <code>generate_lead</code>.</li>
					<li><strong>DataLayer Variables:</strong>
						<code>dlv - value</code> (Maps to <code>value</code>),
						<code>dlv - currency</code> (Maps to <code>currency</code>),
						<code>dlv - destination</code> (Maps to <code>destination</code>),
						<code>dlv - conversion_method</code> (Maps to <code>conversion_method</code>).
					</li>
					<li><strong>Google Ads Enhanced Conversions:</strong> When a WordPress user or lead is identified, <code>user_data</code> (email, phone, first name) is automatically provided to Google Tag Manager for 1st-party enhanced conversion accuracy.</li>
					<li><strong>Tag:</strong> Connect to your <strong>Google Ads Conversion Tracking</strong> tag with Conversion ID &amp; Conversion Label from Google Ads.</li>
				</ul>
			</div>
		</div>

		<!-- 6. TRIPCOSMOS GITHUB APPS & RELEASES ECOSYSTEM -->
		<?php
		$tc_github_apps = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_tripcosmos_apps() : array();
		$has_gh_token   = class_exists( 'TC_Agents_Vault' ) && TC_Agents_Vault::has( 'github_token' );
		?>
		<div class="tc-card-lux" id="tc-github-updater-card">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
				<div>
					<div class="tc-card-title">
						<span>ðŸ™</span>
						TripCosmos GitHub Apps &amp; Releases Hub
						<span class="tc-badge-gold" style="font-size: 10px;">@tripcosmosco</span>
					</div>
					<p class="tc-card-desc" style="margin: 0;">
						Directly monitor, sync, and manage releases for strictly official TripCosmos mobile applications and backend repositories on GitHub.
					</p>
				</div>
				<div style="display: flex; align-items: center; gap: 10px;">
					<button type="button" id="tc-check-all-github-apps-btn" class="button button-secondary" style="border-radius: 8px; font-weight: 600;">
						<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Check All Apps Status
					</button>
				</div>
			</div>

			<!-- Visual TripCosmos App Selector Cards -->
			<div class="tc-gh-apps-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
				<?php foreach ( $tc_github_apps as $ak => $app_meta ) : 
					$app_status = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_app_status( $ak, false ) : null;
				?>
					<div class="tc-gh-app-card <?php echo $ak === 'x_agents' ? 'is-active' : ''; ?>" data-app="<?php echo esc_attr( $ak ); ?>" style="background: rgba(15, 23, 42, 0.6); border: 2px solid <?php echo $ak === 'x_agents' ? '#f59e0b' : '#334155'; ?>; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s ease;">
						<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
							<div style="font-size: 26px; line-height: 1;"><?php echo esc_html( $app_meta['icon'] ); ?></div>
							<span style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;">
								<?php echo esc_html( $app_meta['badge'] ); ?>
							</span>
						</div>
						<div style="font-size: 15px; font-weight: 700; color: #ffffff; margin-bottom: 4px;">
							<?php echo esc_html( $app_meta['name'] ); ?>
						</div>
						<div style="font-size: 11.5px; color: #94a3b8; line-height: 1.4; margin-bottom: 12px; min-height: 32px;">
							<?php echo esc_html( $app_meta['tagline'] ); ?>
						</div>
						<div style="font-size: 11px; font-family: monospace; color: #38bdf8; display: flex; align-items: center; justify-content: space-between; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.08);">
							<span><?php echo esc_html( $app_meta['owner'] . '/' . $app_meta['repo'] ); ?></span>
							<span style="color: #a855f7;">branch: <?php echo esc_html( $app_meta['branch'] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Active App Detail Card -->
			<div id="tc-gh-active-app-panel" style="background: #0b1329; border: 1px solid #1e293b; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
				<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #1e293b; padding-bottom: 14px;">
					<div>
						<h3 id="tc-gh-panel-title" style="margin: 0; color: #f8fafc; font-size: 16px; display: flex; align-items: center; gap: 8px;">
							<span>ðŸ“±</span> X-Agents Mobile &amp; Telephony
						</h3>
						<p id="tc-gh-panel-repo" style="margin: 4px 0 0; color: #94a3b8; font-size: 12.5px;">
							Repository: <a id="tc-gh-panel-repo-link" href="https://github.com/tripcosmosco/agents-app" target="_blank" style="color: #38bdf8; text-decoration: none;">github.com/tripcosmosco/agents-app â†—</a>
						</p>
					</div>
					<div style="display: flex; gap: 8px; align-items: center;">
						<button type="button" id="tc-check-single-app-btn" class="button button-secondary" style="border-radius: 6px; font-weight: 600;">
							<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Sync Live Info
						</button>
						<a id="tc-download-apk-btn" href="<?php echo esc_url( home_url( '/apps/x-agents-latest.apk' ) ); ?>" class="button button-primary" style="background: #f59e0b; border-color: #d97706; font-weight: 700; border-radius: 6px;">
							<span>âš¡</span> Download Latest APK
						</a>
						<button type="button" id="tc-perform-github-update-btn" class="button button-primary" style="display: none; background: #16a34a; border-color: #15803d; font-weight: 700; border-radius: 6px;">
							<span>ðŸš€</span> Update Plugin Now
						</button>
					</div>
				</div>

				<div id="tc-github-update-status-box" style="display: none; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; line-height: 1.5;"></div>

				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
					<div style="background: #070d1e; padding: 12px 14px; border-radius: 8px; border: 1px solid #1e293b;">
						<div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Latest Commit</div>
						<div id="tc-gh-commit-sha" style="font-family: monospace; font-size: 12.5px; color: #38bdf8; font-weight: 700;">Loading...</div>
						<div id="tc-gh-commit-msg" style="color: #cbd5e1; font-size: 11.5px; margin-top: 4px; line-height: 1.3;">Fetching recent commit...</div>
					</div>

					<div style="background: #070d1e; padding: 12px 14px; border-radius: 8px; border: 1px solid #1e293b;">
						<div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Release Tag</div>
						<div id="tc-gh-release-tag" style="font-size: 13px; color: #10b981; font-weight: 700;">Latest Main Build</div>
						<div id="tc-gh-release-time" style="color: #94a3b8; font-size: 11.5px; margin-top: 4px;">Synced via GitHub Actions</div>
					</div>

					<div style="background: #070d1e; padding: 12px 14px; border-radius: 8px; border: 1px solid #1e293b;">
						<div style="color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Ecosystem Status</div>
						<div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
							<span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
							<span style="color: #10b981; font-weight: 700; font-size: 12.5px;">TripCosmos Verified</span>
						</div>
						<div style="color: #94a3b8; font-size: 11.5px; margin-top: 4px;">Strictly organization scoped</div>
					</div>
				</div>
			</div>

			<!-- GitHub Token Configuration -->
			<table class="form-table" style="margin-top: 0;">
				<tr>
					<th scope="row"><label for="github_token">GitHub Access Token (PAT)</label></th>
					<td>
						<input type="password" name="github_token" id="github_token" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'github_token' ) ?: 'gho_â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢' ); ?>" autocomplete="new-password" />
						<?php if ( $has_gh_token ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle; margin-left: 6px;"></span>
							<small style="color:#10b981; font-weight: 700;">Vault Encrypted &bull; 5,000 req/hr Active</small>
						<?php else : ?>
							<span class="description" style="margin-left: 6px;">Used to authenticate against private repositories &amp; prevent API rate limits.</span>
						<?php endif; ?>
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

	// Evolution WhatsApp Gateway Link & QR Scanner
	var evoBadge = document.getElementById('tc-evo-status-badge');
	var evoQrBtn = document.getElementById('tc-btn-evo-qr');
	var evoCheckBtn = document.getElementById('tc-btn-evo-check');
	var evoRestartBtn = document.getElementById('tc-btn-evo-restart');
	var evoQrContainer = document.getElementById('tc-evo-qr-container');
	var evoQrSpinner = document.getElementById('tc-evo-qr-spinner');
	var evoQrContent = document.getElementById('tc-evo-qr-content');
	var evoQrImg = document.getElementById('tc-evo-qr-img');
	var evoPairingBox = document.getElementById('tc-evo-pairing-box');
	var evoPollTimer = null;

	function checkEvoStatus() {
		if (!evoBadge) return;
		var data = new FormData();
		data.append('action', 'tc_evolution_status');
		data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');

		fetch(ajaxurl, { method: 'POST', body: data })
		.then(function(res) { return res.json(); })
		.then(function(res) {
			if (res.success && res.data) {
				if (res.data.online) {
					evoBadge.style.background = '#d1fae5';
					evoBadge.style.color = '#065f46';
					evoBadge.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span> 🟢 Online & Connected (' + res.data.instance + ')';
					if (evoQrContainer) evoQrContainer.style.display = 'none';
					if (evoPollTimer) clearInterval(evoPollTimer);
				} else {
					evoBadge.style.background = '#fee2e2';
					evoBadge.style.color = '#991b1b';
					evoBadge.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span> 🔴 Disconnected (' + (res.data.state || 'close') + ')';
				}
			}
		}).catch(function() {});
	}

	function loadEvoQr(action) {
		if (evoQrBtn) evoQrBtn.disabled = true;
		if (evoRestartBtn) evoRestartBtn.disabled = true;
		if (evoQrContainer) evoQrContainer.style.display = 'block';
		if (evoQrSpinner) evoQrSpinner.style.display = 'block';
		if (evoQrContent) evoQrContent.style.display = 'none';

		var data = new FormData();
		data.append('action', action || 'tc_evolution_qr');
		data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');

		fetch(ajaxurl, { method: 'POST', body: data })
		.then(function(res) { return res.json(); })
		.then(function(res) {
			if (evoQrBtn) evoQrBtn.disabled = false;
			if (evoRestartBtn) evoRestartBtn.disabled = false;
			if (evoQrSpinner) evoQrSpinner.style.display = 'none';

			if (res.success && res.data && res.data.qr) {
				if (evoQrContent) evoQrContent.style.display = 'block';
				if (evoQrImg) evoQrImg.src = res.data.qr;
				if (evoPairingBox) {
					evoPairingBox.textContent = res.data.pairing_code ? 'Pairing Code: ' + res.data.pairing_code : '';
				}
				if (evoPollTimer) clearInterval(evoPollTimer);
				evoPollTimer = setInterval(checkEvoStatus, 3500);
			} else if (res.success && res.data && res.data.state === 'open') {
				alert('WhatsApp is already connected and online!');
				checkEvoStatus();
			} else {
				var errMsg = (res.data && res.data.message) ? res.data.message : 'Could not generate QR code. Check Evolution API server & credentials.';
				alert('Notice: ' + errMsg);
			}
		})
		.catch(function(err) {
			if (evoQrBtn) evoQrBtn.disabled = false;
			if (evoRestartBtn) evoRestartBtn.disabled = false;
			if (evoQrSpinner) evoQrSpinner.style.display = 'none';
			alert('Network Error: ' + err.message);
		});
	}

	if (evoCheckBtn) evoCheckBtn.addEventListener('click', checkEvoStatus);
	if (evoQrBtn) evoQrBtn.addEventListener('click', function() { loadEvoQr('tc_evolution_qr'); });
	if (evoRestartBtn) evoRestartBtn.addEventListener('click', function() {
		if (confirm('Restart Evolution container session and generate fresh QR?')) {
			loadEvoQr('tc_evolution_reconnect');
		}
	});

	// Initial check
	checkEvoStatus();
});
</script>