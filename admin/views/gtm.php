<?php
/**
 * Admin Google Tag Manager & Conversion Tracking View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gtm_enabled  = (string) get_option( 'tc_agents_gtm_enabled', '1' );
$gtm_id       = (string) get_option( 'tc_agents_gtm_container_id', '' );
$lead_value   = (float) get_option( 'tc_agents_lead_conversion_value', 1500 );
$is_saved     = isset( $_GET['saved'] );
?>

<style>
.tc-gtm-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1240px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-gtm-hero {
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
}
.tc-gtm-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-gtm-hero p {
	margin: 0;
	color: #94a3b8;
	font-size: 14px;
	max-width: 680px;
	line-height: 1.5;
}
.tc-card-lux {
	background: #ffffff;
	border-radius: 16px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
	padding: 24px 28px;
	margin-bottom: 24px;
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
.tc-input-lux {
	width: 100%;
	max-width: 580px;
	padding: 10px 14px;
	border: 1.5px solid #cbd5e1;
	border-radius: 10px;
	font-size: 13.5px;
	color: #1e293b;
	background: #f8fafc;
	outline: none;
}
.tc-input-lux:focus {
	border-color: #f59e0b;
	background: #ffffff;
	box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
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
	display: inline-flex;
	align-items: center;
	gap: 8px;
}
.tc-badge-status {
	padding: 4px 10px;
	border-radius: 20px;
	font-weight: 700;
	font-size: 11px;
	text-transform: uppercase;
}
.tc-badge-online {
	background: #dcfce7;
	color: #15803d;
	border: 1px solid #86efac;
}
.tc-code-pill {
	background: #0f172a;
	color: #38bdf8;
	padding: 3px 8px;
	border-radius: 6px;
	font-family: monospace;
	font-size: 12px;
}
</style>

<div class="tc-gtm-wrap">
	<?php if ( $is_saved ) : ?>
		<div class="notice notice-success is-dismissible" style="border-radius: 10px; margin-bottom: 20px;">
			<p><strong>Success:</strong> Google Tag Manager &amp; Conversion Tracking settings saved successfully.</p>
		</div>
	<?php endif; ?>

	<div class="tc-gtm-hero">
		<div>
			<h1>
				<span>📊</span> Google Tag Manager &amp; Lead Conversion Tracking
			</h1>
			<p>
				Automate real-time conversion tracking for Google Ads, Meta Ads, and GA4 across the AI Concierge chat, customized itinerary quotes, 1-click WhatsApp bookings, and voice consultations.
			</p>
		</div>
		<div>
			<span class="tc-badge-status <?php echo ( '1' === $gtm_enabled ) ? 'tc-badge-online' : ''; ?>">
				<?php echo ( '1' === $gtm_enabled ) ? '● DataLayer Active' : '○ Disabled'; ?>
			</span>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tc_agents_admin_action', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="action" value="save_settings" />
		<!-- Preserve existing options so other settings aren't reset -->
		<input type="hidden" name="widget_enabled" value="<?php echo esc_attr( get_option( 'tc_agents_widget_enabled', '1' ) ); ?>" />
		<input type="hidden" name="widget_title" value="<?php echo esc_attr( get_option( 'tc_agents_widget_title', 'TripCosmos Travel Desk' ) ); ?>" />
		<input type="hidden" name="human_whatsapp_number" value="<?php echo esc_attr( get_option( 'tc_agents_human_whatsapp_number', '' ) ); ?>" />
		<input type="hidden" name="redirect_to_gtm" value="1" />

		<!-- 1. GTM CONTAINER & DATALAYER CONTROLS -->
		<div class="tc-card-lux">
			<div class="tc-card-title">
				<span>⚙️</span>
				GTM Container &amp; Conversion Configuration
			</div>
			<p class="tc-card-desc">
				Connect your Google Tag Manager container and configure lead value estimates passed to ad platforms.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">DataLayer Lead Tracing</th>
					<td>
						<label style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
							<input type="checkbox" name="gtm_enabled" value="1" <?php checked( '1', $gtm_enabled ); ?> />
							Enable DataLayer Event Tracing (Google Ads &amp; GA4)
						</label>
						<p class="description">Pushes standardized <code>generate_lead</code>, <code>contact</code>, <code>begin_checkout</code>, and <code>tc_user_identified</code> events to <code>window.dataLayer</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gtm_container_id">GTM Container ID</label></th>
					<td>
						<input type="text" name="gtm_container_id" id="gtm_container_id" class="tc-input-lux" placeholder="GTM-XXXXXXX" value="<?php echo esc_attr( $gtm_id ); ?>" />
						<p class="description">Optional. If entered, the plugin automatically injects the official Google Tag Manager container snippet into <code>&lt;head&gt;</code> and <code>&lt;body&gt;</code>. If your theme already includes GTM, leave this blank.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lead_conversion_value">Default Lead Value (INR)</label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 8px;">
							<span style="font-weight: 700; color: #64748b;">₹</span>
							<input type="number" min="0" step="50" name="lead_conversion_value" id="lead_conversion_value" class="tc-input-lux" style="max-width: 180px;" value="<?php echo esc_attr( $lead_value ); ?>" />
							<span style="font-size: 12px; color: #64748b;">Estimated conversion value passed into Google Ads conversion tracking.</span>
						</div>
					</td>
				</tr>
			</table>

			<div style="margin-top: 18px;">
				<button type="submit" class="tc-btn-gold">
					<span>💾</span> Save GTM Settings
				</button>
			</div>
		</div>

		<!-- 2. SETUP GUIDE & CHEAT SHEET -->
		<div class="tc-card-lux">
			<div class="tc-card-title">
				<span>🎯</span>
				Google Ads Conversion Tracking &amp; GTM Setup Guide
			</div>
			<p class="tc-card-desc">
				Follow these 4 simple steps in your <a href="https://tagmanager.google.com" target="_blank" rel="noopener noreferrer">Google Tag Manager Container ↗</a> to link Google Ads lead conversions:
			</p>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e293b;">1. Create Custom Trigger</h4>
					<p style="font-size: 12.5px; color: #64748b; margin: 0 0 10px 0;">Trigger Type: <strong>Custom Event</strong></p>
					<div style="background: #ffffff; padding: 8px 12px; border-radius: 8px; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 12.5px; color: #0284c7;">
						Event Name: generate_lead
					</div>
					<p style="font-size: 11.5px; color: #94a3b8; margin: 8px 0 0 0;">Fires on quote submissions, detected phones, WhatsApp clicks, &amp; calls.</p>
				</div>

				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e293b;">2. Define DataLayer Variables</h4>
					<p style="font-size: 12.5px; color: #64748b; margin: 0 0 10px 0;">Variable Type: <strong>Data Layer Variable</strong></p>
					<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #334155; line-height: 1.6;">
						<li><code>value</code> &rarr; Conversion value (INR)</li>
						<li><code>currency</code> &rarr; Currency code ('INR')</li>
						<li><code>lead_id</code> &rarr; Session / Contact ID</li>
						<li><code>destination</code> &rarr; Tour Package</li>
					</ul>
				</div>

				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e293b;">3. Enhanced Conversions</h4>
					<p style="font-size: 12.5px; color: #64748b; margin: 0 0 10px 0;">User-provided data for 1st-party accuracy:</p>
					<div style="background: #ffffff; padding: 8px 12px; border-radius: 8px; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 12px; color: #047857;">
						Variable: user_data
					</div>
					<p style="font-size: 11.5px; color: #94a3b8; margin: 8px 0 0 0;">Supplies email, phone, and name directly from WordPress logged-in users &amp; leads.</p>
				</div>

				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
					<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e293b;">4. Connect Google Ads Tag</h4>
					<p style="font-size: 12.5px; color: #64748b; margin: 0 0 10px 0;">Tag: <strong>Google Ads Conversion Tracking</strong></p>
					<p style="font-size: 12px; color: #334155; margin: 0; line-height: 1.5;">
						Paste your Conversion ID &amp; Label from Google Ads. Map Value to <code>{{dlv - value}}</code> and attach the <code>generate_lead</code> trigger.
					</p>
				</div>
			</div>
		</div>

		<!-- 3. LIVE EVENT REFERENCE -->
		<div class="tc-card-lux">
			<div class="tc-card-title">
				<span>📡</span>
				Active Events Dispatched to DataLayer
			</div>
			<table class="wp-list-table widefat fixed striped" style="margin-top: 14px; border: 0;">
				<thead>
					<tr>
						<th style="font-weight: 700;">Event Name</th>
						<th style="font-weight: 700;">Trigger Action</th>
						<th style="font-weight: 700;">Category</th>
						<th style="font-weight: 700;">Payload Attributes</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><span class="tc-code-pill">generate_lead</span></td>
						<td>Quote submit, Phone detected, WhatsApp click, Phone call</td>
						<td><span style="background: #ecfdf5; color: #047857; font-weight: 700; padding: 2px 7px; border-radius: 999px; font-size: 11px;">Primary Conversion</span></td>
						<td><code>value, currency, lead_id, destination, conversion_channel, user_data</code></td>
					</tr>
					<tr>
						<td><span class="tc-code-pill">tc_user_identified</span></td>
						<td>Page load with authenticated WordPress user</td>
						<td><span style="background: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 2px 7px; border-radius: 999px; font-size: 11px;">User Identity</span></td>
						<td><code>user_id, user_data: { email, phone_number, first_name }</code></td>
					</tr>
					<tr>
						<td><span class="tc-code-pill">contact</span></td>
						<td>Direct click on WhatsApp or Phone Consultation</td>
						<td><span style="background: #fef3c7; color: #b45309; font-weight: 700; padding: 2px 7px; border-radius: 999px; font-size: 11px;">Micro-Conversion</span></td>
						<td><code>method: 'whatsapp' | 'phone_call' | 'live_chat'</code></td>
					</tr>
					<tr>
						<td><span class="tc-code-pill">begin_checkout</span></td>
						<td>Click on Advance Token or Booking Payment link</td>
						<td><span style="background: #fdf2f8; color: #be185d; font-weight: 700; padding: 2px 7px; border-radius: 999px; font-size: 11px;">Funnel</span></td>
						<td><code>url, currency: 'INR', item_name: 'Tour Advance Token'</code></td>
					</tr>
				</tbody>
			</table>
		</div>
	</form>
</div>
