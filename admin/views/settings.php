<?php
/**
 * Admin Settings View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-admin-generic"></span> General Settings</h1>
			<p class="description">Configure frontend chat widget appearance, human escalation routing, and site defaults.</p>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_settings" />

		<!-- Frontend Chat Widget -->
		<div class="tc-card">
			<h3>Frontend Floating Chat Widget</h3>
			<p class="description">Customize the customer-facing chat assistant on TripCosmos.co (Togo theme & Elementor Pro compatible).</p>
			<table class="form-table">
				<tr>
					<th scope="row">Enable Chat Widget</th>
					<td>
						<label>
							<input type="checkbox" name="widget_enabled" value="1" <?php checked( '1', get_option( 'tc_agents_widget_enabled', '1' ) ); ?> />
							Display floating chat bubble on live site
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="widget_title">Assistant Name / Header</label></th>
					<td>
						<input type="text" name="widget_title" id="widget_title" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_widget_title', 'TripCosmos Travel Assistant' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="widget_greeting">Welcome Message</label></th>
					<td>
						<textarea name="widget_greeting" id="widget_greeting" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'tc_agents_widget_greeting', 'Hi there! Looking for an unforgettable trek or adventure package? How can I help you plan today?' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="widget_primary_color">Theme Accent Color</label></th>
					<td>
						<input type="color" name="widget_primary_color" id="widget_primary_color" value="<?php echo esc_attr( get_option( 'tc_agents_widget_primary_color', '#0ea5e9' ) ); ?>" />
						<span class="description">Hex color matching brand guidelines (e.g. <code>#0ea5e9</code>).</span>
					</td>
				</tr>
			</table>
		</div>

		<!-- Human Escalation Contacts -->
		<div class="tc-card">
			<h3>Human Escalation Routing</h3>
			<p class="description">When travelers ask to speak to a person or require complex custom itineraries, requests route directly to these contacts.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="human_whatsapp_number">Official Team WhatsApp</label></th>
					<td>
						<input type="text" name="human_whatsapp_number" id="human_whatsapp_number" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_human_whatsapp_number', '+919876543210' ) ); ?>" />
						<p class="description">Phone number with country code (e.g. <code>+919876543210</code>) used to generate instant one-click WhatsApp handoff links.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="human_notification_email">Staff Notification Email</label></th>
					<td>
						<input type="email" name="human_notification_email" id="human_notification_email" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) ) ); ?>" />
						<p class="description">Email address notified when human handoffs or emergency alerts trigger.</p>
					</td>
				</tr>
			</table>
		</div>

		<p>
			<input type="submit" class="button button-primary button-large" value="Save Settings" />
		</p>
	</form>
</div>
