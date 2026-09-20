<?php
/**
 * Admin Integrations View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fluent_active      = TC_Integration_FluentCRM::is_active();
$twenty_configured  = class_exists( 'TC_Integration_TwentyCRM' ) && TC_Integration_TwentyCRM::is_configured();
$webhook_url        = rest_url( 'tc-agents/v1/whatsapp-webhook' );
$secret_token       = get_option( 'tc_agents_whatsapp_webhook_secret', '' );
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-admin-plugins"></span> Integrations & Tool Plumbings</h1>
			<p class="description">Connect your conversational agents directly to CRM, WhatsApp gateways, Google Sheets, and Voice telephony.</p>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Integration settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_integrations" />

		<!-- 1. Fluent CRM -->
		<div class="tc-card">
			<div class="tc-card-header">
				<h3>1. Fluent CRM (Native WordPress CRM)</h3>
				<span class="tc-indicator-badge <?php echo $fluent_active ? 'tc-status-healthy' : 'tc-status-down'; ?>">
					<span class="tc-indicator-dot <?php echo $fluent_active ? 'online' : 'down'; ?>"></span>
					<?php echo $fluent_active ? 'ACTIVE & CONNECTED' : 'NOT INSTALLED / INACTIVE'; ?>
				</span>
			</div>
			<p class="description">
				Leverages native <code>FluentCrmApi('contacts')</code> without manual API keys or raw DB manipulation.
				When a traveler shares contact details in chat, an automatic contact record is registered with tags:
				<code>agent-lead</code>, <code>channel:web|whatsapp</code>, and destination tags.
			</p>
		</div>

		<!-- 2. Twenty CRM -->
		<div class="tc-card">
			<div class="tc-card-header">
				<h3>2. Twenty CRM (crm.vmstudio.digital)</h3>
				<span class="tc-indicator-badge <?php echo $twenty_configured ? 'tc-status-healthy' : 'tc-status-down'; ?>">
					<span class="tc-indicator-dot <?php echo $twenty_configured ? 'online' : 'down'; ?>"></span>
					<?php echo $twenty_configured ? 'CONFIGURED & READY' : 'TOKEN MISSING / NOT CONFIGURED'; ?>
				</span>
			</div>
			<p class="description">REST integration pushing qualified leads directly into your cloud CRM deal pipeline at <code>crm.vmstudio.digital</code>.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="twentycrm_url">Instance URL</label></th>
					<td>
						<input type="text" name="twentycrm_url" id="twentycrm_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_twentycrm_url', 'https://crm.vmstudio.digital' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="twentycrm_api_key">API Bearer Token</label></th>
					<td>
						<input type="password" name="twentycrm_api_key" id="twentycrm_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'twentycrm_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'twentycrm_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
						<p class="description">Generate a Bearer Token in your Twenty CRM Dashboard (<strong>Settings ➔ Developers ➔ API Keys</strong>) and paste here.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Test Connection</th>
					<td>
						<button type="button" class="button button-secondary" id="tc-test-twentycrm-btn">
							<span class="dashicons dashicons-rest-api" style="vertical-align:middle;margin-top:-2px;"></span> Test Twenty CRM Connection
						</button>
						<span id="tc-twentycrm-test-result" style="margin-left:12px;font-weight:600;font-size:13px;"></span>
					</td>
				</tr>
			</table>
		</div>

		<!-- 3. WhatsApp Gateway (dual-mode) -->
		<div class="tc-card">
			<h3>3. WhatsApp Gateway (Legacy + Evolution API)</h3>
			<p class="description">Dual-mode outbound bridge. Keep legacy wa.vmstudio.digital or switch to Evolution API. One shared inbound webhook handles both.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label>Inbound Webhook Endpoint</label></th>
					<td>
						<code><?php echo esc_html( $webhook_url ); ?></code>
						<p class="description">Configure this URL in your WhatsApp gateway webhook settings (legacy or Evolution).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_webhook_secret">Webhook Secret Token</label></th>
					<td>
						<input type="text" name="whatsapp_webhook_secret" id="whatsapp_webhook_secret" readonly class="regular-text" value="<?php echo esc_attr( $secret_token ); ?>" />
						<p class="description">Pass this token in the <code>x-webhook-secret</code> header for webhook authentication.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_mode">Active Mode</label></th>
					<td>
						<select name="whatsapp_mode" id="whatsapp_mode">
							<option value="legacy" <?php selected( get_option( 'tc_agents_whatsapp_mode', 'legacy' ), 'legacy' ); ?>>Legacy wa.vmstudio.digital</option>
							<option value="evolution" <?php selected( get_option( 'tc_agents_whatsapp_mode', 'legacy' ), 'evolution' ); ?>>Evolution API</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_api_url">Legacy Outbound Gateway URL</label></th>
					<td>
						<input type="text" name="whatsapp_api_url" id="whatsapp_api_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_token">Legacy Gateway Token</label></th>
					<td>
						<input type="password" name="whatsapp_token" id="whatsapp_token" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'whatsapp_token' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'whatsapp_token' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="evolution_base_url">Evolution API Base URL</label></th>
					<td>
						<input type="text" name="evolution_base_url" id="evolution_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_evolution_base_url', '' ) ); ?>" placeholder="https://evo.yourdomain.com" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="evolution_instance">Evolution Instance Name</label></th>
					<td>
						<input type="text" name="evolution_instance" id="evolution_instance" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_evolution_instance', 'tripcosmos' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="evolution_api_key">Evolution API Key</label></th>
					<td>
						<input type="password" name="evolution_api_key" id="evolution_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'evolution_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'evolution_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- 4. Google Sheets -->
		<div class="tc-card">
			<h3>4. Google Sheets Synchronization</h3>
			<p class="description">Stream new traveler leads into a live Google Sheet in real-time.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="sheets_enabled">Enable Sheets Sync</label></th>
					<td>
						<label>
							<input type="checkbox" name="sheets_enabled" id="sheets_enabled" value="1" <?php checked( '1', get_option( 'tc_agents_sheets_enabled', '0' ) ); ?> />
							Sync captured leads to Google Sheets via Webhook / AppScript
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sheets_webhook_url">Google Sheet Webhook URL</label></th>
					<td>
						<input type="text" name="sheets_webhook_url" id="sheets_webhook_url" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_sheets_webhook_url', '' ) ); ?>" placeholder="https://script.google.com/macros/s/.../exec" />
					</td>
				</tr>
			</table>
		</div>

		<!-- 5. Brevo (email + push-style marketing) -->
		<div class="tc-card">
			<h3>5. Brevo — Transactional & B2B Marketing Email</h3>
			<p class="description">Sends B2B partnership pitches via Brevo SMTP API. FluentCRM email templates remain the B2C nurture channel; Brevo powers B2B outreach.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="brevo_api_key">Brevo API Key</label></th>
					<td>
						<input type="password" name="brevo_api_key" id="brevo_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'brevo_api_key' ) ?: 'xkeysib-...' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'brevo_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brevo_sender_email">Sender Email</label></th>
					<td><input type="email" name="brevo_sender_email" id="brevo_sender_email" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_brevo_sender_email', get_option( 'admin_email' ) ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="brevo_sender_name">Sender Name</label></th>
					<td><input type="text" name="brevo_sender_name" id="brevo_sender_name" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_brevo_sender_name', 'TripCosmos' ) ); ?>" /></td>
				</tr>
			</table>
		</div>

		<!-- 6. Google Business / B2B Agency Import -->
		<div class="tc-card">
			<h3>6. Google Business — B2B Travel Agency Import (India)</h3>
			<p class="description">Daily cron imports travel agencies via Google Places Text Search into the B2B prospect table, with optional auto-outreach via WhatsApp + Brevo.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="google_places_api_key">Google Places API Key</label></th>
					<td>
						<input type="password" name="google_places_api_key" id="google_places_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'google_places_api_key' ) ?: 'AIza...' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'google_places_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="b2b_import_cities">Import Cities (comma-separated)</label></th>
					<td><input type="text" name="b2b_import_cities" id="b2b_import_cities" class="large-text" value="<?php echo esc_attr( get_option( 'tc_agents_b2b_import_cities', 'Varanasi, Delhi, Mumbai, Jaipur' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="b2b_auto_outreach">Auto-Outreach</label></th>
					<td><label><input type="checkbox" name="b2b_auto_outreach" id="b2b_auto_outreach" value="1" <?php checked( '1', get_option( 'tc_agents_b2b_auto_outreach', '0' ) ); ?> /> Auto-send WhatsApp + Brevo pitch to newly imported agencies (rate-limited by guardrails)</label>
					<p class="description">Last import: <code><?php echo esc_html( get_option( 'tc_agents_b2b_last_import', 'never' ) ); ?></code></p></td>
				</tr>
			</table>
		</div>

		<!-- 7. Voice Telephony -->
		<div class="tc-card">
			<div class="tc-card-header">
				<h3>7. Voice Telephony & Agent Calling</h3>
				<span class="tc-indicator-badge <?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? 'tc-status-healthy' : 'tc-status-down'; ?>">
					<?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? 'VOICE ENABLED' : 'VOICE DISABLED (SAFE)'; ?>
				</span>
			</div>
			<p class="description">
				High-stakes voice calling capability. Ships <strong>disabled by default</strong> with strict daily volume caps.
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="voice_enabled">Enable Voice Telephony</label></th>
					<td>
						<label>
							<input type="checkbox" name="voice_enabled" id="voice_enabled" value="1" <?php checked( '1', get_option( 'tc_agents_voice_enabled', '0' ) ); ?> />
							<strong>Allow Outbound Voice Calling</strong> (Keep unchecked unless actively needed).
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_provider">Voice Platform</label></th>
					<td>
						<select name="voice_provider" id="voice_provider">
							<option value="vapi" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'vapi' ); ?>>Vapi (Recommended AI Voice Platform)</option>
							<option value="retell" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'retell' ); ?>>Retell AI</option>
							<option value="twilio" <?php selected( get_option( 'tc_agents_voice_provider', 'vapi' ), 'twilio' ); ?>>Twilio</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_api_key">Voice API Key</label></th>
					<td>
						<input type="password" name="voice_api_key" id="voice_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'voice_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'voice_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_assistant_id">Assistant ID / Model</label></th>
					<td>
						<input type="text" name="voice_assistant_id" id="voice_assistant_id" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_voice_assistant_id', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_phone_number_id">Phone Number ID</label></th>
					<td>
						<input type="text" name="voice_phone_number_id" id="voice_phone_number_id" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_voice_phone_number_id', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label>Transcript Webhook URL</label></th>
					<td>
						<code><?php echo esc_html( rest_url( 'tc-agents/v1/voice-webhook' ) ); ?></code>
					</td>
				</tr>
			</table>
		</div>

		<p>
			<input type="submit" class="button button-primary button-large" value="Save Integration Settings" />
		</p>
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
