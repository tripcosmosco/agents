<?php
/**
 * Admin Integrations View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fluent_active = TC_Integration_FluentCRM::is_active();
$webhook_url   = rest_url( 'tc-agents/v1/whatsapp-webhook' );
$secret_token  = get_option( 'tc_agents_whatsapp_webhook_secret', '' );
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

	<form method="post">
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
			<h3>2. Twenty CRM (crm.vmstudio.digital)</h3>
			<p class="description">REST integration pushing qualified leads directly into your cloud CRM deal pipeline.</p>
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
					</td>
				</tr>
			</table>
		</div>

		<!-- 3. WhatsApp Gateway -->
		<div class="tc-card">
			<h3>3. WhatsApp Gateway (wa.vmstudio.digital)</h3>
			<p class="description">Inbound webhook receiver and outbound messaging bridge connecting your official WhatsApp business numbers.</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label>Inbound Webhook Endpoint</label></th>
					<td>
						<code><?php echo esc_html( $webhook_url ); ?></code>
						<p class="description">Configure this URL in your WhatsApp gateway (wa.vmstudio.digital) webhook settings.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label>Webhook Secret Token</label></th>
					<td>
						<input type="text" readonly class="regular-text" value="<?php echo esc_attr( $secret_token ); ?>" />
						<p class="description">Pass this token in the <code>x-webhook-secret</code> header for webhook authentication.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_api_url">Outbound Gateway URL</label></th>
					<td>
						<input type="text" name="whatsapp_api_url" id="whatsapp_api_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_whatsapp_api_url', 'https://wa.vmstudio.digital' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_token">Gateway Authorization Token</label></th>
					<td>
						<input type="password" name="whatsapp_token" id="whatsapp_token" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'whatsapp_token' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
						<?php if ( TC_Agents_Vault::has( 'whatsapp_token' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
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

		<!-- 5. Voice Telephony -->
		<div class="tc-card">
			<div class="tc-card-header">
				<h3>5. Voice Telephony & Agent Calling</h3>
				<span class="tc-indicator-badge <?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? 'tc-status-healthy' : 'tc-status-down'; ?>">
					<?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? 'VOICE ENABLED' : 'VOICE DISABLED (SAFE)'; ?>
				</span>
			</div>
			<p class="description">
				High-stakes voice calling capability. Ships <strong>disabled by default</strong> with strict daily volume caps.
			</p>
			<table class="form-table">
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
