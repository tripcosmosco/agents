<?php
/**
 * Admin Integrations View - Modern Executive Design.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fluent_active      = class_exists( 'TC_Integration_FluentCRM' ) && TC_Integration_FluentCRM::is_active();
$twenty_configured  = class_exists( 'TC_Integration_TwentyCRM' ) && TC_Integration_TwentyCRM::is_configured();
$webhook_url        = rest_url( 'tc-agents/v1/whatsapp-webhook' );
$secret_token       = get_option( 'tc_agents_whatsapp_webhook_secret', '' );
$voice_enabled      = '1' === (string) get_option( 'tc_agents_voice_enabled', '0' );
$sheets_enabled     = '1' === (string) get_option( 'tc_agents_sheets_enabled', '0' );
$wa_mode            = get_option( 'tc_agents_whatsapp_mode', 'legacy' );
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-int-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1240px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-int-hero {
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
.tc-int-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-int-hero p {
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
.tc-card-header-flex {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 8px;
}
.tc-card-title {
	font-size: 18px;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 10px;
}
.tc-card-desc {
	margin: 0 0 18px 0;
	color: #64748b;
	font-size: 13px;
	line-height: 1.5;
}

.tc-status-pill {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 5px 12px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.5px;
	text-transform: uppercase;
}
.tc-status-pill.online {
	background: #ecfdf5;
	color: #059669;
	border: 1px solid #a7f3d0;
}
.tc-status-pill.down {
	background: #fef2f2;
	color: #dc2626;
	border: 1px solid #fecaca;
}
.tc-status-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}
.tc-status-pill.online .tc-status-dot { background: #10b981; }
.tc-status-pill.down .tc-status-dot { background: #ef4444; }

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
.tc-btn-secondary {
	background: #f1f5f9;
	color: #334155;
	border: 1px solid #cbd5e1;
	font-weight: 600;
	padding: 9px 18px;
	border-radius: 8px;
	cursor: pointer;
	font-size: 13px;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.tc-btn-secondary:hover {
	background: #e2e8f0;
	color: #0f172a;
}
</style>

<div class="wrap tc-int-wrap">
	<!-- Hero -->
	<div class="tc-int-hero">
		<div>
			<h1>
				<span>🔌</span>
				Integrations &amp; Multi-Channel Routing
				<span class="tc-badge-gold">Enterprise Hub</span>
			</h1>
			<p>Connect your conversational agents, lead pipelines, and automated follow-up sequences across FluentCRM, Twenty CRM, WhatsApp (Evolution API), Google Sheets, and AI Voice.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents' ) ); ?>" class="button button-secondary" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: #fff;">
				← Executive Dashboard
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; color: #065f46; font-weight: 600; display: flex; align-items: center; gap: 10px;">
			<span>✓</span> Integration parameters saved successfully. Active across all background sync workers.
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_integrations" />

		<!-- 1. FLUENT CRM (NATIVE WORDPRESS) -->
		<div class="tc-card-lux" style="border-left: 5px solid <?php echo $fluent_active ? '#10b981' : '#64748b'; ?>;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>⚡</span>
					1. FluentCRM — Native WordPress Contact Engine
				</div>
				<span class="tc-status-pill <?php echo $fluent_active ? 'online' : 'down'; ?>">
					<span class="tc-status-dot"></span>
					<?php echo $fluent_active ? 'ACTIVE & CONNECTED' : 'INACTIVE / NOT DETECTED'; ?>
				</span>
			</div>
			<p class="tc-card-desc">
				Native zero-latency bridge utilizing WordPress internal hooks. When visitors share their contact details in the live chat or WhatsApp, contact records are instantly created and tagged with <code>agent-lead</code>, <code>channel:web|whatsapp</code>, and travel interest tags.
			</p>
			<?php if ( $fluent_active ) : ?>
				<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 10px; font-size: 13px; color: #334155; display: flex; align-items: center; justify-content: space-between;">
					<span>✓ FluentCRM API is ready. Leads captured by TripCosmos AI automatically sync into your FluentCRM lists and automations.</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fluentcrm-admin' ) ); ?>" class="tc-btn-secondary" target="_blank">Open FluentCRM ↗</a>
				</div>
			<?php else : ?>
				<div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 12px 16px; border-radius: 10px; font-size: 13px; color: #92400e;">
					⚠️ FluentCRM plugin is not currently active on this WordPress instance. Leads will still be stored securely in the TripCosmos native DB tables (<code>wp_tc_agent_contacts</code>).
				</div>
			<?php endif; ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="fluentcrm_new_status">Status for new subscribers</label></th>
					<td>
						<select name="fluentcrm_new_status" id="fluentcrm_new_status">
							<?php foreach ( array( 'subscribed' => 'Subscribed', 'pending' => 'Pending (double opt-in)', 'transactional' => 'Transactional (no marketing)' ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( get_option( 'tc_agents_fluentcrm_new_status', 'subscribed' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Used only when a lead is new to FluentCRM. Existing subscribers keep their status, so an unsubscribe is never overwritten.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fluentcrm_b2b_list_id">B2B master list ID</label></th>
					<td><input type="number" min="0" name="fluentcrm_b2b_list_id" id="fluentcrm_b2b_list_id" class="tc-input-lux" value="<?php echo esc_attr( (int) get_option( 'tc_agents_fluentcrm_b2b_list_id', 0 ) ); ?>" /> <span class="description">0 = none</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="fluentcrm_city_lists">B2B city lists</label></th>
					<td>
						<textarea name="fluentcrm_city_lists" id="fluentcrm_city_lists" rows="3" class="tc-input-lux" style="font-family: monospace;"><?php echo esc_textarea( (string) get_option( 'tc_agents_fluentcrm_city_lists', '' ) ); ?></textarea>
						<p class="description">One per line: <code>bengaluru|bangalore=5</code>. The first keyword found in the agency's city adds that list.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fluentcrm_b2b_tags">B2B tags</label></th>
					<td><input type="text" name="fluentcrm_b2b_tags" id="fluentcrm_b2b_tags" class="tc-input-lux" value="<?php echo esc_attr( (string) get_option( 'tc_agents_fluentcrm_b2b_tags', 'b2b-travel-agency' ) ); ?>" /> <span class="description">Comma separated</span></td>
				</tr>
				<tr>
					<th scope="row">New FluentCRM contacts</th>
					<td><label><input type="checkbox" name="fluentcrm_import_new" value="1" <?php checked( get_option( 'tc_agents_fluentcrm_import_new', '0' ), '1' ); ?> /> Also create a TripCosmos contact when someone is added in FluentCRM (e.g. newsletter sign-ups)</label></td>
				</tr>
			</table>
		</div>

		<!-- 2. TWENTY CRM (CLOUD DEAL PIPELINE) -->
		<div class="tc-card-lux" style="border-left: 5px solid <?php echo $twenty_configured ? '#10b981' : '#f59e0b'; ?>;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>🏢</span>
					2. Twenty CRM — Cloud Deal Pipeline
				</div>
				<span class="tc-status-pill <?php echo $twenty_configured ? 'online' : 'down'; ?>">
					<span class="tc-status-dot"></span>
					<?php echo $twenty_configured ? 'CONFIGURED & READY' : 'TOKEN REQUIRED'; ?>
				</span>
			</div>
			<p class="tc-card-desc">
				Syncs qualified inquiries directly into your cloud CRM pipeline at <code>crm.vmstudio.digital</code> as Opportunities and Deals with custom metadata.
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="twentycrm_url">Twenty CRM Instance URL</label></th>
					<td>
						<input type="text" name="twentycrm_url" id="twentycrm_url" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_twentycrm_url', 'https://crm.vmstudio.digital' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="twentycrm_api_key">API Bearer Token</label></th>
					<td>
						<input type="password" name="twentycrm_api_key" id="twentycrm_api_key" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'twentycrm_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'twentycrm_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
						<p class="description">Generate an API Bearer token in your Twenty CRM Settings (<strong>Settings &gt; Developers &gt; API Keys</strong>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Connection Test</th>
					<td>
						<button type="button" class="tc-btn-secondary" id="tc-test-twentycrm-btn">
							<span class="dashicons dashicons-rest-api" style="vertical-align: middle;"></span> Test Live Twenty CRM Connection
						</button>
						<span id="tc-twentycrm-test-result" style="margin-left: 14px; font-weight: 600; font-size: 13px;"></span>
					</td>
				</tr>
			</table>
		</div>

		<!-- 3. WHATSAPP GATEWAY (DUAL-MODE) -->
		<div class="tc-card-lux" style="border-left: 5px solid #25d366;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>💬</span>
					3. WhatsApp Gateway (Evolution API &amp; Legacy Bridge)
				</div>
				<span class="tc-status-pill online">
					<span class="tc-status-dot"></span>
					MODE: <?php echo esc_html( strtoupper( $wa_mode ) ); ?>
				</span>
			</div>
			<p class="tc-card-desc">
				Dual-mode outbound and inbound WhatsApp bridge. Connects to Evolution API for production high-volume multi-device messaging or Legacy gateway.
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><label>Inbound Webhook Endpoint</label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 8px;">
							<code style="padding: 8px 12px; background: #f1f5f9; border-radius: 8px; font-size: 13px; color: #0284c7;"><?php echo esc_html( $webhook_url ); ?></code>
							<button type="button" class="tc-btn-secondary" onclick="navigator.clipboard.writeText('<?php echo esc_js( $webhook_url ); ?>'); alert('Copied webhook URL!');">Copy</button>
						</div>
						<p class="description">Configure this URL as the webhook destination in your WhatsApp gateway dashboard.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_webhook_secret">Webhook Secret Token</label></th>
					<td>
						<input type="text" name="whatsapp_webhook_secret" id="whatsapp_webhook_secret" readonly class="tc-input-lux" style="font-family: monospace;" value="<?php echo esc_attr( $secret_token ); ?>" />
						<p class="description">Validated via the <code>x-webhook-secret</code> HTTP header for incoming payloads.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_mode">Active Gateway Mode</label></th>
					<td>
						<select name="whatsapp_mode" id="whatsapp_mode" class="tc-input-lux" style="width: 320px;" onchange="document.getElementById('tc-wa-evo-box').style.display = (this.value === 'evolution' ? 'block' : 'none'); document.getElementById('tc-wa-leg-box').style.display = (this.value === 'legacy' ? 'block' : 'none');">
							<option value="legacy" <?php selected( $wa_mode, 'legacy' ); ?>>Legacy wa.vmstudio.digital</option>
							<option value="evolution" <?php selected( $wa_mode, 'evolution' ); ?>>Evolution API (Recommended Multi-Device)</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Legacy Box -->
			<div id="tc-wa-leg-box" style="display: <?php echo $wa_mode === 'legacy' ? 'block' : 'none'; ?>; border-top: 1px dashed #e2e8f0; padding-top: 16px; margin-top: 10px;">
				<h4 style="margin: 0 0 12px 0; color: #475569;">Legacy Gateway Configuration</h4>
				<table class="form-table" style="margin: 0;">
					<tr>
						<th scope="row"><label for="whatsapp_api_url">Legacy Gateway URL</label></th>
						<td>
							<input type="text" name="whatsapp_api_url" id="whatsapp_api_url" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="whatsapp_token">Legacy Bearer Token</label></th>
						<td>
							<input type="password" name="whatsapp_token" id="whatsapp_token" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'whatsapp_token' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
							<?php if ( TC_Agents_Vault::has( 'whatsapp_token' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Evolution API Box -->
			<div id="tc-wa-evo-box" style="display: <?php echo $wa_mode === 'evolution' ? 'block' : 'none'; ?>; border-top: 1px dashed #e2e8f0; padding-top: 16px; margin-top: 10px;">
				<h4 style="margin: 0 0 12px 0; color: #0284c7;">Evolution API Configuration</h4>
				<table class="form-table" style="margin: 0;">
					<tr>
						<th scope="row"><label for="evolution_base_url">Evolution Server Base URL</label></th>
						<td>
							<input type="text" name="evolution_base_url" id="evolution_base_url" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_evolution_base_url', '' ) ); ?>" placeholder="https://evolution.example.com" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="evolution_instance">Instance Name</label></th>
						<td>
							<input type="text" name="evolution_instance" id="evolution_instance" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_evolution_instance', 'tripcosmos' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="evolution_api_key">Global / Instance API Key</label></th>
						<td>
							<input type="password" name="evolution_api_key" id="evolution_api_key" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'evolution_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
							<?php if ( TC_Agents_Vault::has( 'evolution_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- 4. GOOGLE SHEETS LIVE SYNC -->
		<div class="tc-card-lux" style="border-left: 5px solid #0f9d58;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>📊</span>
					4. Google Sheets Live Inquiry Feed
				</div>
				<span class="tc-status-pill <?php echo $sheets_enabled ? 'online' : 'down'; ?>">
					<span class="tc-status-dot"></span>
					<?php echo $sheets_enabled ? 'SYNC ACTIVE' : 'DISABLED'; ?>
				</span>
			</div>
			<p class="tc-card-desc">
				Append new traveler leads and quote requests automatically to a shared Google Sheet via Apps Script Webhook.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="sheets_enabled">Enable Google Sheets Sync</label></th>
					<td>
						<label style="font-weight: 600;">
							<input type="checkbox" name="sheets_enabled" id="sheets_enabled" value="1" <?php checked( $sheets_enabled ); ?> />
							Automatically post every new lead to Google Sheet webhook
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sheets_webhook_url">Google Apps Script Webhook URL</label></th>
					<td>
						<input type="text" name="sheets_webhook_url" id="sheets_webhook_url" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_sheets_webhook_url', '' ) ); ?>" placeholder="https://script.google.com/macros/s/.../exec" />
					</td>
				</tr>
			</table>
		</div>

		<!-- 5. BREVO EMAIL MARKETING -->
		<div class="tc-card-lux" style="border-left: 5px solid #0092ff;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>✉️</span>
					5. Brevo — Transactional &amp; B2B Marketing Email
				</div>
			</div>
			<p class="tc-card-desc">
				Powers high-deliverability transactional emails, quote confirmations, and automated B2B travel agency partner pitches.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="brevo_api_key">Brevo API Key</label></th>
					<td>
						<input type="password" name="brevo_api_key" id="brevo_api_key" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'brevo_api_key' ) ?: 'xkeysib-...' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'brevo_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brevo_sender_email">Sender Email</label></th>
					<td>
						<input type="email" name="brevo_sender_email" id="brevo_sender_email" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_brevo_sender_email', get_option( 'admin_email' ) ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brevo_sender_name">Sender Name</label></th>
					<td>
						<input type="text" name="brevo_sender_name" id="brevo_sender_name" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_brevo_sender_name', 'TripCosmos' ) ); ?>" />
					</td>
				</tr>
			</table>
		</div>

		<!-- 6. GOOGLE BUSINESS B2B AGENCY IMPORT -->
		<div class="tc-card-lux" style="border-left: 5px solid #8b5cf6;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>🗺️</span>
					6. Google Places — B2B Travel Agency Discovery
				</div>
			</div>
			<p class="tc-card-desc">
				Daily automated background cron imports travel agency prospects across Indian metropolitan cities into the B2B pipeline.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="google_places_api_key">Google Places API Key</label></th>
					<td>
						<input type="password" name="google_places_api_key" id="google_places_api_key" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'google_places_api_key' ) ?: 'AIza...' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'google_places_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="b2b_import_cities">Target Cities (Comma-separated)</label></th>
					<td>
						<input type="text" name="b2b_import_cities" id="b2b_import_cities" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_b2b_import_cities', 'Varanasi, Delhi, Mumbai, Jaipur' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="b2b_auto_outreach">Automated Outreach</label></th>
					<td>
						<label style="font-weight: 600;">
							<input type="checkbox" name="b2b_auto_outreach" id="b2b_auto_outreach" value="1" <?php checked( '1', get_option( 'tc_agents_b2b_auto_outreach', '0' ) ); ?> />
							Automatically dispatch introductory B2B WhatsApp &amp; Brevo partnership invites
						</label>
						<p class="description">Last batch import executed: <code><?php echo esc_html( get_option( 'tc_agents_b2b_last_import', 'never' ) ); ?></code></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 7. VOICE TELEPHONY -->
		<div class="tc-card-lux" style="border-left: 5px solid <?php echo $voice_enabled ? '#10b981' : '#64748b'; ?>;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>📞</span>
					7. Voice Telephony &amp; AI Phone Calling
				</div>
				<span class="tc-status-pill <?php echo $voice_enabled ? 'online' : 'down'; ?>">
					<span class="tc-status-dot"></span>
					<?php echo $voice_enabled ? 'VOICE ENABLED' : 'VOICE DISABLED (SAFE)'; ?>
				</span>
			</div>
			<p class="tc-card-desc">
				Configures automated voice calling capabilities (e.g. Vapi, Retell, Twilio). Kept disabled by default for cost and volume safety.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="voice_enabled">Outbound Voice Calls</label></th>
					<td>
						<label style="font-weight: 600;">
							<input type="checkbox" name="voice_enabled" id="voice_enabled" value="1" <?php checked( $voice_enabled ); ?> />
							Allow outbound voice calls for follow-up confirmations
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_provider">Voice Platform</label></th>
					<td>
						<select name="voice_provider" id="voice_provider" class="tc-input-lux" style="width: 320px;">
							<option value="vapi" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'vapi' ); ?>>Vapi (Recommended AI Voice)</option>
							<option value="retell" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'retell' ); ?>>Retell AI</option>
							<option value="twilio" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'twilio' ); ?>>Twilio</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_api_key">Voice API Key</label></th>
					<td>
						<input type="password" name="voice_api_key" id="voice_api_key" class="tc-input-lux" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'voice_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'voice_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align: middle;"></span> <small style="color:#10b981; font-weight: 600;">Vault Encrypted</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_assistant_id">Voice Assistant ID / Model</label></th>
					<td>
						<input type="text" name="voice_assistant_id" id="voice_assistant_id" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_voice_assistant_id', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_phone_number_id">Registered Phone Number ID</label></th>
					<td>
						<input type="text" name="voice_phone_number_id" id="voice_phone_number_id" class="tc-input-lux" value="<?php echo esc_attr( get_option( 'tc_agents_voice_phone_number_id', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label>Transcript Webhook URL</label></th>
					<td>
						<code style="padding: 6px 10px; background: #f1f5f9; border-radius: 6px; font-size: 13px; color: #0284c7;"><?php echo esc_html( rest_url( 'tc-agents/v1/voice-webhook' ) ); ?></code>
					</td>
				</tr>
			</table>
		</div>

		<!-- Submit Action -->
		<div style="margin-top: 24px;">
			<button type="submit" class="tc-btn-gold">
				<span>💾</span> Save All Integration Parameters
			</button>
		</div>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var testBtn = document.getElementById('tc-test-twentycrm-btn');
	if (!testBtn) return;

	testBtn.addEventListener('click', function() {
		var resEl = document.getElementById('tc-twentycrm-test-result');
		testBtn.disabled = true;
		resEl.style.color = '#6b7280';
		resEl.textContent = 'Testing connection to Twenty CRM...';

		var data = new FormData();
		data.append('action', 'tc_test_twentycrm');
		data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');

		fetch(ajaxurl, {
			method: 'POST',
			body: data
		})
		.then(function(res) { return res.json(); })
		.then(function(response) {
			testBtn.disabled = false;
			if (response.success) {
				resEl.style.color = '#10b981';
				resEl.textContent = '✓ ' + (response.data && response.data.message ? response.data.message : 'Successfully connected to Twenty CRM!');
			} else {
				resEl.style.color = '#ef4444';
				var msg = (response.data && response.data.message) ? response.data.message : (response.message || 'Connection failed.');
				resEl.textContent = '✕ ' + msg;
			}
		})
		.catch(function(err) {
			testBtn.disabled = false;
			resEl.style.color = '#ef4444';
			resEl.textContent = '✕ Network error: ' + err.message;
		});
	});
});
</script>

<?php
// Inbound webhook secrets (created on first view so they can be copied into the provider).
foreach ( array( 'tc_agents_voice_webhook_secret', 'tc_agents_twentycrm_webhook_secret' ) as $tc_secret_opt ) {
	if ( '' === (string) get_option( $tc_secret_opt, '' ) ) {
		update_option( $tc_secret_opt, wp_generate_password( 32, false ), false );
	}
}
?>
<div class="wrap" style="max-width: 900px; margin-top: 24px;">
	<h2>Inbound Webhook Secrets</h2>
	<p class="description">Voice and TwentyCRM webhooks reject requests without this secret. Send it as the <code>X-Webhook-Secret</code> header (or append <code>?secret=&hellip;</code> to the URL if the provider cannot set headers).</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">Voice webhook</th>
			<td><code><?php echo esc_html( rest_url( 'tc-agents/v1/voice-webhook' ) ); ?></code><br /><input type="text" readonly class="regular-text" style="font-family: monospace;" value="<?php echo esc_attr( get_option( 'tc_agents_voice_webhook_secret', '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row">TwentyCRM webhook</th>
			<td><code><?php echo esc_html( rest_url( 'tc-agents/v1/twentycrm-webhook' ) ); ?></code><br /><input type="text" readonly class="regular-text" style="font-family: monospace;" value="<?php echo esc_attr( get_option( 'tc_agents_twentycrm_webhook_secret', '' ) ); ?>" /></td>
		</tr>
	</table>
</div>

<div class="wrap" style="max-width: 900px; margin-top: 24px;">

	<?php
	$tcag_new = get_transient( 'tcag_new_token_' . get_current_user_id() );
	if ( $tcag_new ) {
		delete_transient( 'tcag_new_token_' . get_current_user_id() );
	}
	$tcag_tokens = TC_Agents_Security::agent_tokens();
	?>
	<div class="tc-card" id="tc-mobile-access">
		<h3>Mobile App Access (Sales Agents app)</h3>
		<p class="description">Create one token per agent or phone and paste it into the app's Settings. Revoking a token cuts that device off immediately. API base URL: <code><?php echo esc_html( rest_url( 'tc-agents/v1/' ) ); ?></code></p>

		<?php if ( $tcag_new ) : ?>
			<div class="notice notice-warning inline">
				<p><strong>Copy this token now. It will not be shown again.</strong><br />
				<code style="font-size:14px;user-select:all;"><?php echo esc_html( $tcag_new['token'] ); ?></code></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['token_revoked'] ) ) : ?>
			<div class="notice notice-success inline"><p>Token revoked.</p></div>
		<?php endif; ?>

		<table class="widefat striped" style="margin:12px 0;">
			<thead><tr><th>Agent / device</th><th>Created</th><th>Last used</th><th></th></tr></thead>
			<tbody>
			<?php if ( empty( $tcag_tokens ) ) : ?>
				<tr><td colspan="4">No agent tokens yet.</td></tr>
			<?php else : ?>
				<?php foreach ( $tcag_tokens as $tcag_id => $tcag_row ) : ?>
					<tr>
						<td><?php echo esc_html( $tcag_row['label'] ?? '' ); ?></td>
						<td><?php echo esc_html( wp_date( 'd M Y, H:i', (int) ( $tcag_row['created'] ?? 0 ) ) ); ?></td>
						<td><?php echo ! empty( $tcag_row['last_used'] ) ? esc_html( wp_date( 'd M Y, H:i', (int) $tcag_row['last_used'] ) ) : 'Never'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Revoke this token? The device will stop working.');">
								<input type="hidden" name="action" value="tc_agents_revoke_token" />
								<input type="hidden" name="token_id" value="<?php echo esc_attr( $tcag_id ); ?>" />
								<?php wp_nonce_field( 'tc_agents_token_action' ); ?>
								<button type="submit" class="button button-link-delete">Revoke</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tc_agents_create_token" />
			<?php wp_nonce_field( 'tc_agents_token_action' ); ?>
			<input type="text" name="agent_label" class="regular-text" placeholder="Agent name or device, e.g. Santosh - Redmi" required />
			<button type="submit" class="button button-primary">Generate token</button>
		</form>
	</div>

	<div class="tc-card" id="tc-quick-links">
		<h3>Quick-action links (mobile app)</h3>
		<p class="description">These links are sent to customers by the app's one-tap actions. Leave a link empty to disable that action.</p>
		<?php if ( isset( $_GET['links_saved'] ) ) : ?>
			<div class="notice notice-success inline"><p>Links saved.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tc_agents_save_links" />
			<?php wp_nonce_field( 'tc_agents_token_action' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="brochure_url">Brochure PDF</label></th>
					<td><input type="url" name="brochure_url" id="brochure_url" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_brochure_url', '' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="cab_chart_url">Cab fare chart</label></th>
					<td><input type="url" name="cab_chart_url" id="cab_chart_url" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_cab_chart_url', '' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="booking_url">Booking page</label></th>
					<td><input type="url" name="booking_url" id="booking_url" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_booking_url', home_url( '/book' ) ) ); ?>" /></td>
				</tr>
			</table>
			<p>
				<label for="manager_phones"><strong>Manager WhatsApp numbers</strong> (one per line: <code>Name | 919XXXXXXXXX</code>). Used when an agent assigns a lead and asks to notify the manager.</label><br />
				<textarea name="manager_phones" id="manager_phones" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'tc_agents_manager_phones', '' ) ); ?></textarea>
			</p>
			<button type="submit" class="button button-primary">Save links</button>
		</form>
	</div>
</div>

<?php if ( class_exists( 'TC_WhatsApp_Hub' ) ) { TC_WhatsApp_Hub::render_admin_panel(); } ?>
