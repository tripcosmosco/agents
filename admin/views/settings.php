<?php
/**
 * Admin Settings View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$widget_enabled = (string) get_option( 'tc_agents_widget_enabled', '1' );
$is_widget_live = ( '1' === $widget_enabled );
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-admin-generic"></span> General Settings</h1>
			<p class="description">Configure frontend chat widget visibility, appearance, human escalation routing, and site defaults.</p>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_settings" />

		<!-- 1. Master Frontend Chatbot Enable / Disable Toggle -->
		<div class="tc-card" style="border-left: 4px solid <?php echo $is_widget_live ? '#10b981' : '#ef4444'; ?>; background: <?php echo $is_widget_live ? '#f0fdf4' : '#fef2f2'; ?>; transition: all 0.25s ease;">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
				<div>
					<h2 style="margin: 0 0 6px 0; font-size: 18px; display: flex; align-items: center; gap: 10px; color: #0f172a;">
						<span style="font-size: 24px;">💬</span>
						Frontend Chatbot Visibility
						<span id="tc-bot-status-badge" class="tc-indicator-badge <?php echo $is_widget_live ? 'tc-status-healthy' : 'tc-status-down'; ?>" style="font-size: 11px;">
							<span class="tc-indicator-dot <?php echo $is_widget_live ? 'online' : 'down'; ?>"></span>
							<span id="tc-bot-status-text"><?php echo $is_widget_live ? 'ACTIVE & VISIBLE ON WEBSITE' : 'DISABLED & HIDDEN FROM VISITORS'; ?></span>
						</span>
					</h2>
					<p style="margin: 0; color: #475569; font-size: 13px; max-width: 650px;">
						Control whether the floating AI assistant, quotation form, and voice consultation bubble appear on your live website. When disabled, all widget assets and markup are completely removed from the frontend.
					</p>
				</div>
				<div style="display: flex; align-items: center; gap: 14px;">
					<button type="button" class="button button-large <?php echo $is_widget_live ? 'button-secondary' : 'button-primary'; ?>" id="tc-quick-toggle-widget-btn" style="height: 42px; font-weight: 700; font-size: 13px; border-radius: 8px;">
						<?php echo $is_widget_live ? '🔴 Click to Disable Chatbot' : '🟢 Click to Enable Chatbot'; ?>
					</button>
					<input type="hidden" name="widget_enabled" id="widget_enabled_input" value="<?php echo esc_attr( $widget_enabled ); ?>" />
				</div>
			</div>
		</div>

		<!-- 2. Frontend Chat Widget Customization -->
		<div class="tc-card">
			<h3>🎨 Frontend Chatbot Customization & Identity</h3>
			<p class="description">Customize the profile picture/avatar, assistant name, subtitle, proactive teaser, brand gradient colors, and starter quick prompts.</p>
			
			<table class="form-table">

				<!-- Bot Profile Image / Avatar -->
				<tr>
					<th scope="row"><label for="tc_widget_avatar">Chatbot Profile Image (Avatar)</label></th>
					<td>
						<?php 
						$bot_avatar = get_option( 'tc_agents_bot_avatar', '' ); 
						$bot_avatar_emoji = get_option( 'tc_agents_bot_avatar_emoji', '🛕' );
						?>
						<div style="display: flex; align-items: center; gap: 18px; margin-bottom: 10px;">
							<div id="tc-bot-avatar-preview" style="width: 60px; height: 60px; border-radius: 16px; border: 2px solid #cbd5e1; background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(147,51,234,0.2)); display: flex; align-items: center; justify-content: center; font-size: 28px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
								<?php if ( ! empty( $bot_avatar ) ) : ?>
									<img src="<?php echo esc_url( $bot_avatar ); ?>" alt="Bot Avatar" style="width: 100%; height: 100%; object-fit: cover;" />
								<?php else : ?>
									<span id="tc-bot-avatar-emoji-preview"><?php echo esc_html( $bot_avatar_emoji ); ?></span>
								<?php endif; ?>
							</div>
							<div>
								<input type="hidden" name="widget_avatar" id="tc_widget_avatar" value="<?php echo esc_attr( $bot_avatar ); ?>" />
								<button type="button" class="button button-secondary" id="tc-upload-bot-avatar">
									<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span> Select Image from Media Library
								</button>
								<button type="button" class="button button-link-delete" id="tc-remove-bot-avatar" style="<?php echo empty( $bot_avatar ) ? 'display:none;' : ''; ?> margin-left: 8px;">
									Remove Image
								</button>
								<p class="description" style="margin-top: 6px;">Upload a custom bot profile photo or company mascot (square 1:1 ratio PNG/JPG recommended).</p>
							</div>
						</div>
					</td>
				</tr>

				<!-- Fallback Emoji -->
				<tr>
					<th scope="row"><label for="widget_avatar_emoji">Fallback Avatar Emoji</label></th>
					<td>
						<input type="text" name="widget_avatar_emoji" id="widget_avatar_emoji" class="small-text" style="text-align: center; font-size: 18px;" value="<?php echo esc_attr( $bot_avatar_emoji ); ?>" maxlength="4" />
						<span class="description">Displayed if no profile image is uploaded (e.g. 🛕, 👳, 🕉️, 🚗, ✈️, 🤖).</span>
					</td>
				</tr>

				<!-- Chatbot Name -->
				<tr>
					<th scope="row"><label for="widget_title">Chatbot Name / Title</label></th>
					<td>
						<input type="text" name="widget_title" id="widget_title" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ) ); ?>" placeholder="e.g. TripCosmos Travel Desk" />
						<p class="description">Displayed prominently in the header bar and voice call screen.</p>
					</td>
				</tr>

				<!-- Status / Subtitle -->
				<tr>
					<th scope="row"><label for="widget_subtitle">Status Line / Subtitle</label></th>
					<td>
						<input type="text" name="widget_subtitle" id="widget_subtitle" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_widget_subtitle', 'Online • Varanasi Desk ✓' ) ); ?>" placeholder="e.g. Online • Varanasi Desk ✓" />
						<p class="description">Small status line under the bot title with green pulsing indicator.</p>
					</td>
				</tr>

				<!-- Welcome Message / Greeting -->
				<tr>
					<th scope="row"><label for="widget_greeting">Welcome Greeting Message</label></th>
					<td>
						<textarea name="widget_greeting" id="widget_greeting" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'tc_agents_widget_greeting', 'Namaste! 🙏 Welcome to TripCosmos — your Varanasi spiritual & tour guide. Looking for Kashi Vishwanath darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova/Dzire), hotel bookings, or evening Ganga Aarti boat rides? How can I assist you today?' ) ); ?></textarea>
						<p class="description">Initial message rendered inside the chat stream when first opened.</p>
					</td>
				</tr>

				<!-- Proactive Teaser -->
				<tr>
					<th scope="row"><label for="launcher_teaser_text">Proactive Teaser Callout</label></th>
					<td>
						<input type="text" name="launcher_teaser_text" id="launcher_teaser_text" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_launcher_teaser_text', 'Planning Varanasi, Ayodhya, or Prayagraj? Ask our AI Concierge for tours, cabs & ghat hotels!' ) ); ?>" />
						<p class="description">Text shown in floating teaser balloon beside the chat bubble to attract visitors.</p>
					</td>
				</tr>

				<!-- Position / Placement -->
				<tr>
					<th scope="row"><label for="widget_side">Widget Screen Position</label></th>
					<td>
						<?php $side = get_option( 'tc_agents_widget_side', 'right' ); ?>
						<select name="widget_side" id="widget_side">
							<option value="right" <?php selected( 'right', $side ); ?>>Bottom Right Corner (Standard)</option>
							<option value="left" <?php selected( 'left', $side ); ?>>Bottom Left Corner</option>
						</select>
					</td>
				</tr>

				<!-- Gradient Colors -->
				<tr>
					<th scope="row"><label for="widget_primary_color">Brand Primary Accent Color</label></th>
					<td>
						<input type="color" name="widget_primary_color" id="widget_primary_color" value="<?php echo esc_attr( get_option( 'tc_agents_widget_primary_color', '#ea580c' ) ); ?>" />
						<span class="description">Used for primary radiant gradient buttons, FAB glow, and active tabs (e.g. <code>#ea580c</code>).</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="widget_secondary_color">Brand Secondary Gradient Color</label></th>
					<td>
						<input type="color" name="widget_secondary_color" id="widget_secondary_color" value="<?php echo esc_attr( get_option( 'tc_agents_widget_secondary_color', '#9333ea' ) ); ?>" />
						<span class="description">Used for rich dual-tone gradient transition (e.g. <code>#9333ea</code> or <code>#0284c7</code>).</span>
					</td>
				</tr>

				<!-- Quick Starter Prompts -->
				<tr>
					<th scope="row"><label for="starter_prompts">Quick Starter Prompt Chips</label></th>
					<td>
						<?php 
						$default_starters = "🛕 Kashi Ayodhya Tour | Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package\n🚗 Outstation Cabs | Book outstation cab Dzire or Innova Crysta for Varanasi to Ayodhya\n⛵ Ganga Aarti Boat | Book private boat for evening Ganga Aarti at Dashashwamedh Ghat\n🏨 Ghat Hotels | Best ghat-view hotels in Varanasi near Kashi Vishwanath";
						$starters = get_option( 'tc_agents_starter_prompts', $default_starters );
						?>
						<textarea name="starter_prompts" id="starter_prompts" rows="4" class="large-text"><?php echo esc_textarea( $starters ); ?></textarea>
						<p class="description">One chip per line in the format: <code>Chip Label | Message sent to agent</code> (or just the query text).</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Human Escalation Contacts -->
		<div class="tc-card">
			<h3>📱 Human Escalation & WhatsApp Routing</h3>
			<p class="description">When travelers ask to speak to a person or book direct tours, requests route directly to these contacts.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="human_whatsapp_number">Official Team WhatsApp</label></th>
					<td>
						<input type="text" name="human_whatsapp_number" id="human_whatsapp_number" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) ); ?>" />
						<p class="description">Phone number with country code (e.g. <code>+919876543210</code>) used for the top header WhatsApp icon and instant handoff button.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="human_notification_email">Staff Notification Email</label></th>
					<td>
						<input type="email" name="human_notification_email" id="human_notification_email" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) ) ); ?>" />
						<p class="description">Email address notified when quotes or emergency human alerts trigger.</p>
					</td>
				</tr>
			</table>
		</div>

		<p>
			<input type="submit" class="button button-primary button-large" value="Save All Settings" />
		</p>
	</form>
</div>
