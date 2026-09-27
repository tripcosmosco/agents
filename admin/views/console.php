<?php
/**
 * Admin Master Agent Console View - High Quality Chat Edition.
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

<style>
/* Self-Contained Master Console Styles */
.tc-console-wrap {
	max-width: 1360px;
	margin: 20px 20px 40px 0;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}

.tc-console-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	background: #ffffff;
	padding: 18px 24px;
	border-radius: 12px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.tc-console-header h1 {
	margin: 0;
	font-size: 22px;
	font-weight: 800;
	color: #0b132b;
	display: flex;
	align-items: center;
	gap: 10px;
}

.tc-console-header p {
	margin: 4px 0 0 0;
	font-size: 13px;
	color: #64748b;
}

.tc-console-grid {
	display: grid;
	grid-template-columns: 1fr 340px;
	gap: 24px;
	align-items: start;
}

@media (max-width: 1024px) {
	.tc-console-grid {
		grid-template-columns: 1fr;
	}
}

/* Chat Main Panel */
.tc-chat-main-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
	display: flex;
	flex-direction: column;
}

.tc-chat-top-bar {
	background: #f8fafc;
	border-bottom: 1px solid #e2e8f0;
	padding: 12px 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.tc-feed-area {
	height: 520px;
	overflow-y: auto;
	padding: 24px;
	background: #f1f5f9;
	background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
	background-size: 20px 20px;
	display: flex;
	flex-direction: column;
	gap: 18px;
}

/* Chat Rows & Bubbles */
.tc-chat-row {
	display: flex;
	width: 100%;
}

.tc-row-user {
	justify-content: flex-end;
}

.tc-row-assistant {
	justify-content: flex-start;
}

.tc-bubble-user {
	max-width: 76%;
	background: linear-gradient(135deg, #1e3a8a 0%, #0b132b 100%);
	color: #ffffff !important;
	padding: 14px 18px;
	border-radius: 18px 18px 4px 18px;
	box-shadow: 0 4px 12px rgba(11, 19, 43, 0.15);
}

.tc-bubble-user .tc-bubble-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
	margin-bottom: 6px;
	font-size: 11px;
	color: #93c5fd;
	font-weight: 600;
}

.tc-bubble-user .tc-bubble-text {
	font-size: 14px;
	line-height: 1.55;
	color: #ffffff;
}

.tc-bubble-assistant {
	max-width: 80%;
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-left: 4px solid #f59e0b;
	padding: 16px 20px;
	border-radius: 4px 18px 18px 18px;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}

.tc-bubble-assistant .tc-bubble-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
	margin-bottom: 8px;
	flex-wrap: wrap;
}

.tc-bot-identity {
	display: flex;
	align-items: center;
	gap: 8px;
}

.tc-bot-avatar {
	width: 24px;
	height: 24px;
	background: #0b132b;
	color: #f59e0b;
	border-radius: 50%;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 700;
}

.tc-bot-name {
	font-weight: 700;
	font-size: 13px;
	color: #0f172a;
}

.tc-bot-gateway {
	background: #fef3c7;
	color: #b45309;
	font-size: 10px;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 10px;
	border: 1px solid #fde68a;
}

.tc-bot-meta {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 11px;
	color: #64748b;
}

.tc-latency-tag {
	font-weight: 700;
	font-size: 10px;
	padding: 2px 6px;
	border-radius: 8px;
}

.tc-latency-good { background: #dcfce7; color: #15803d; }
.tc-latency-warn { background: #fef9c3; color: #a16207; }
.tc-latency-bad  { background: #fee2e2; color: #b91c1c; }

.tc-bubble-assistant .tc-bubble-text {
	font-size: 14.5px;
	line-height: 1.6;
	color: #1e293b;
}

/* Chat Input Bar */
.tc-chat-input-bar {
	background: #ffffff;
	border-top: 1px solid #e2e8f0;
	padding: 16px 20px;
}

.tc-input-wrapper {
	display: flex;
	gap: 12px;
	align-items: flex-end;
}

.tc-input-wrapper textarea {
	flex: 1;
	border: 1.5px solid #cbd5e1;
	border-radius: 10px;
	padding: 12px 14px;
	font-size: 14px;
	line-height: 1.5;
	resize: none;
	height: 60px;
	transition: border-color 0.2s;
}

.tc-input-wrapper textarea:focus {
	border-color: #0b132b;
	outline: none;
	box-shadow: 0 0 0 3px rgba(11, 19, 43, 0.1);
}

.tc-btn-send {
	background: #0b132b !important;
	color: #ffffff !important;
	border: none !important;
	border-radius: 10px !important;
	padding: 0 24px !important;
	height: 60px !important;
	font-weight: 700 !important;
	font-size: 14px !important;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	transition: background 0.2s;
}

.tc-btn-send:hover {
	background: #1e293b !important;
}

/* Right Sidebar Cards */
.tc-side-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
	margin-bottom: 20px;
}

.tc-side-box h3 {
	margin: 0 0 14px 0;
	font-size: 14px;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	border-bottom: 1px solid #f1f5f9;
	padding-bottom: 10px;
}

.tc-prompts-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.tc-prompts-list li a {
	display: block;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 10px 12px;
	color: #1e293b;
	text-decoration: none;
	font-size: 12px;
	font-weight: 600;
	transition: all 0.2s;
}

.tc-prompts-list li a:hover {
	background: #fffbeb;
	border-color: #f59e0b;
	color: #b45309;
	transform: translateX(3px);
}
</style>

<div class="tc-console-wrap">
	<div class="tc-console-header">
		<div>
			<h1>
				<span class="dashicons dashicons-superhero-alt" style="color: #f59e0b; font-size: 26px; width: 26px; height: 26px;"></span>
				Master Agent Console & Simulator
			</h1>
			<p>Supervise agents, simulate traveler inquiries, test RAG tool execution, and inspect real-time responses.</p>
		</div>
		<div>
			<?php if ( $kill_switch_active ) : ?>
				<div class="tc-badge tc-badge-danger">
					<span class="dashicons dashicons-shield"></span> EMERGENCY KILL SWITCH ACTIVE
				</div>
			<?php else : ?>
				<div class="tc-badge tc-badge-success" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
					<span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
					AGENT CORE OPERATIONAL
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="tc-console-grid">
		<!-- Left: Chat Canvas -->
		<div class="tc-chat-main-card">
			<div class="tc-chat-top-bar">
				<div style="display: flex; align-items: center; gap: 10px;">
					<label for="tc-console-agent-select" style="font-weight: 700; font-size: 12px; color: #475569;">Active Agent Persona:</label>
					<select id="tc-console-agent-select" style="font-size: 12px; font-weight: 600; border-radius: 6px; padding: 4px 8px;">
						<?php foreach ( $personas as $p ) : ?>
							<option value="<?php echo esc_attr( $p['slug'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<button type="button" class="button button-small" id="tc-clear-console" style="color: #ef4444; border-color: #fca5a5;">
						<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Clear Canvas
					</button>
				</div>
			</div>

			<div class="tc-feed-area" id="tc-console-feed">
				<div class="tc-chat-row tc-row-tool" style="justify-content: center;">
					<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 14px 20px; max-width: 90%; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
						<div style="font-size: 18px; margin-bottom: 4px;">👋 <strong>Staff Supervised Console Ready</strong></div>
						<p style="margin: 0; font-size: 12px; color: #64748b;">
							Type a prompt below or pick a scenario from the sidebar to test live tool calls (`search_trips`, `calculate_cab_fare`, `check_darshan_slots`).
						</p>
					</div>
				</div>
			</div>

			<div class="tc-chat-input-bar">
				<form id="tc-console-form">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<div class="tc-input-wrapper">
						<textarea id="tc-console-input" placeholder="Type instructions or traveler scenarios (Enter to send, Shift+Enter for newline)..."></textarea>
						<button type="submit" class="tc-btn-send" id="tc-console-send">
							<span>Send</span>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Right: Quick Scenarios & Telemetry Sidebar -->
		<div>
			<div class="tc-side-box">
				<h3><span class="dashicons dashicons-lightbulb" style="color: #f59e0b;"></span> Test Scenarios</h3>
				<ul class="tc-prompts-list">
					<li>
						<a href="#" class="tc-quick-prompt" data-prompt="A family of 4 wants a 4-day tour of Varanasi, Prayagraj, and Ayodhya with hotel and AC Innova. What is the day-wise itinerary and pricing?">
							🛕 Kashi Ayodhya Prayagraj Tour
						</a>
					</li>
					<li>
						<a href="#" class="tc-quick-prompt" data-prompt="A traveler needs an Innova Crysta cab for Varanasi Airport pickup and outstation transfer to Ayodhya Ram Mandir. Provide the cab details and fare.">
							🚗 Outstation Cab (Innova/Dzire)
						</a>
					</li>
					<li>
						<a href="#" class="tc-quick-prompt" data-prompt="Explain private boat ride options and timings for evening Ganga Aarti at Dashashwamedh Ghat and morning Subah-e-Banaras.">
							⛵ Ganga Aarti Private Boat
						</a>
					</li>
					<li>
						<a href="#" class="tc-quick-prompt" data-prompt="Book VIP Darshan ticket assistance for Kashi Vishwanath Sugam Darshan and Mangla Aarti slots.">
							🎟️ VIP Darshan & Aarti Slots
						</a>
					</li>
				</ul>
			</div>

			<div class="tc-side-box">
				<h3><span class="dashicons dashicons-shield" style="color: #10b981;"></span> Guardrail Protection</h3>
				<table style="width: 100%; font-size: 12px; border-collapse: collapse;">
					<tr style="border-bottom: 1px solid #f1f5f9;">
						<td style="padding: 8px 0; color: #64748b;">Emergency Kill:</td>
						<td style="padding: 8px 0; text-align: right; font-weight: 700; color: <?php echo $kill_switch_active ? '#ef4444' : '#10b981'; ?>;">
							<?php echo $kill_switch_active ? 'ENGAGED' : 'Disengaged'; ?>
						</td>
					</tr>
					<tr style="border-bottom: 1px solid #f1f5f9;">
						<td style="padding: 8px 0; color: #64748b;">Approval Gate:</td>
						<td style="padding: 8px 0; text-align: right; font-weight: 600;">
							<?php echo '1' === get_option( 'tc_agents_require_human_approval', '1' ) ? 'Required (Safe)' : 'Relaxed'; ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; color: #64748b;">Jio Voice Bridge:</td>
						<td style="padding: 8px 0; text-align: right; font-weight: 600;">
							<?php echo '1' === get_option( 'tc_agents_voice_enabled', '0' ) ? '🟢 Active' : '⚪ Standby'; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>
