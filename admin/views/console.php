<?php
/**
 * Admin Master Agent Console View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$personas = $wpdb->get_results( "SELECT slug, name FROM {$wpdb->prefix}tc_agent_personas WHERE is_active = 1", ARRAY_A ) ?: array();
$kill_switch_active = TC_Agents_Guardrails::is_kill_switch_active();
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-superhero-alt"></span> Master Agent Console</h1>
			<p class="description">Command center for TripCosmos staff: supervise agents, test tool execution, draft WhatsApp replies, or query live catalogs.</p>
		</div>
		<div class="tc-header-actions">
			<?php if ( $kill_switch_active ) : ?>
				<div class="tc-badge tc-badge-danger">
					<span class="dashicons dashicons-shield"></span> EMERGENCY KILL SWITCH ACTIVE
				</div>
			<?php else : ?>
				<div class="tc-badge tc-badge-success">
					<span class="tc-indicator-dot online"></span> AGENT SYSTEM OPERATIONAL
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="tc-console-layout">
		<!-- Main Chat Workspace -->
		<div class="tc-console-chat-panel">
			<div class="tc-console-chat-header">
				<div class="tc-persona-picker">
					<label for="tc-console-agent-select"><strong>Talking to:</strong></label>
					<select id="tc-console-agent-select">
						<?php foreach ( $personas as $p ) : ?>
							<option value="<?php echo esc_attr( $p['slug'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tc-console-actions">
					<button type="button" class="button button-secondary" id="tc-clear-console"><span class="dashicons dashicons-trash"></span> Clear View</button>
				</div>
			</div>

			<div class="tc-chat-feed" id="tc-console-feed">
				<div class="tc-msg tc-msg-system">
					<div class="tc-bubble">
						👋 <strong>Master Console Ready.</strong> You are interacting directly with the agent layer in <em>staff mode</em>. Test queries like:
						<ul>
							<li><code>"Find our top 3 winter treks in Uttarakhand under 15,000 INR"</code></li>
							<li><code>"Draft a polite WhatsApp follow-up for a lead interested in Kedarkantha"</code></li>
							<li><code>"Check CRM status for contact test@example.com"</code></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="tc-console-input-area">
				<form id="tc-console-form">
					<textarea id="tc-console-input" placeholder="Type instructions or traveler scenarios..." rows="2"></textarea>
					<button type="submit" class="button button-primary" id="tc-console-send">
						<span class="dashicons dashicons-arrow-right-alt"></span> Send
					</button>
				</form>
			</div>
		</div>

		<!-- Quick Actions & Status Sidebar -->
		<div class="tc-console-sidebar">
			<div class="tc-card">
				<h3><span class="dashicons dashicons-lightbulb"></span> Quick Prompts</h3>
				<ul class="tc-quick-prompts">
					<li><a href="#" class="tc-quick-prompt" data-prompt="A family of 4 wants a 4-day tour of Varanasi, Prayagraj, and Ayodhya with hotel and AC Innova. What is the day-wise itinerary and pricing?">🛕 Kashi Ayodhya Prayagraj Tour</a></li>
					<li><a href="#" class="tc-quick-prompt" data-prompt="A traveler needs an Innova Crysta cab for Varanasi Airport pickup and outstation transfer to Ayodhya Ram Mandir. Provide the cab details and fare.">🚗 Outstation Cab (Innova/Dzire)</a></li>
					<li><a href="#" class="tc-quick-prompt" data-prompt="Explain private boat ride options and timings for evening Ganga Aarti at Dashashwamedh Ghat and morning Subah-e-Banaras.">⛵ Ganga Aarti Boat Booking</a></li>
					<li><a href="#" class="tc-quick-prompt" data-prompt="Check if contact with email info@tripcosmos.co exists in our CRM.">👤 Test CRM Contact Lookup</a></li>
				</ul>
			</div>

			<div class="tc-card">
				<h3><span class="dashicons dashicons-admin-generic"></span> Active Guardrail Status</h3>
				<table class="tc-compact-table">
					<tr>
						<td>Kill Switch:</td>
						<td><strong><?php echo $kill_switch_active ? '<span style="color:#ef4444">ENGAGED</span>' : '<span style="color:#10b981">Disengaged</span>'; ?></strong></td>
					</tr>
					<tr>
						<td>Approval Gate:</td>
						<td><?php echo '1' === get_option( 'tc_agents_require_human_approval', '1' ) ? 'Required (Safe)' : 'Relaxed'; ?></td>
					</tr>
					<tr>
						<td>Voice Telephony:</td>
						<td><?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? 'Enabled' : 'Disabled'; ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>
