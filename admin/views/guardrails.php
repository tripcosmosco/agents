<?php
/**
 * Admin Guardrails & Kill Switch View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kill_switch_active = TC_Agents_Guardrails::is_kill_switch_active();
$logs               = TC_Agents_Logger::get_logs( array( 'limit' => 40 ) );
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-shield"></span> Safety Guardrails & Emergency Controls</h1>
			<p class="description">Defensive mechanisms protecting TripCosmos.co against unsupervised runaway execution, excessive API charges, and inadvertent customer contact.</p>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Safety guardrails updated.</p></div>
	<?php endif; ?>

	<!-- EMERGENCY KILL SWITCH CARD -->
	<div class="tc-card tc-kill-switch-card <?php echo $kill_switch_active ? 'active' : ''; ?>">
		<div class="tc-kill-switch-header">
			<div>
				<h2 style="margin: 0; color: <?php echo $kill_switch_active ? '#b91c1c' : '#1e293b'; ?>;">
					<span class="dashicons dashicons-warning" style="font-size: 28px; width: 28px; height: 28px;"></span>
					EMERGENCY KILL SWITCH
				</h2>
				<p style="margin: 4px 0 0 0;">
					Instantly freezes all outbound agent activities across <strong>all channels</strong> (Web chat, WhatsApp, and Voice calls).
					Checked before every outgoing action, even if LLM providers are misbehaving.
				</p>
			</div>
			<div>
				<button type="button" class="button button-large tc-kill-btn <?php echo $kill_switch_active ? 'button-secondary' : 'button-danger'; ?>" id="tc-toggle-kill-switch" data-current="<?php echo $kill_switch_active ? '1' : '0'; ?>">
					<?php echo $kill_switch_active ? 'DISENGAGE KILL SWITCH (RESUME)' : '🚨 ENGAGE EMERGENCY KILL SWITCH'; ?>
				</button>
			</div>
		</div>
		<div class="tc-kill-switch-status">
			Current Status: <strong><?php echo $kill_switch_active ? '<span style="color:#ef4444;">ACTIVE — ALL AGENTS HALTED</span>' : '<span style="color:#10b981;">DISENGAGED — AGENTS ACTIVE</span>'; ?></strong>
		</div>
	</div>

	<!-- Guardrails Configuration -->
	<div class="tc-card">
		<h3>Velocity & Channel Quota Controls</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-guardrails' ) ); ?>">
			<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
			<input type="hidden" name="tc_agents_action" value="save_guardrails" />

			<table class="form-table">
				<tr>
					<th scope="row"><label for="rate_limit_hourly">Hourly Rate Limit (per visitor/session)</label></th>
					<td>
						<input type="number" min="5" max="200" name="rate_limit_hourly" id="rate_limit_hourly" value="<?php echo esc_attr( get_option( 'tc_agents_rate_limit_hourly', 30 ) ); ?>" />
						<p class="description">Maximum messages a single web visitor or phone number can send within 1 hour before temporary throttling.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_daily_limit">WhatsApp Daily Send Quota</label></th>
					<td>
						<input type="number" min="10" max="2000" name="whatsapp_daily_limit" id="whatsapp_daily_limit" value="<?php echo esc_attr( get_option( 'tc_agents_whatsapp_daily_limit', 100 ) ); ?>" />
						<p class="description">Maximum outbound WhatsApp messages the agent system can dispatch across all contacts per calendar day.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_daily_limit">Voice Calls Daily Limit</label></th>
					<td>
						<input type="number" min="1" max="100" name="voice_daily_limit" id="voice_daily_limit" value="<?php echo esc_attr( get_option( 'tc_agents_voice_daily_limit', 10 ) ); ?>" />
						<p class="description">Hard daily cap on outbound automated phone calls (defaults low at 10/day for safety).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="discount_ceiling">Maximum Discount Ceiling (%)</label></th>
					<td>
						<input type="number" min="0" max="50" name="discount_ceiling" id="discount_ceiling" value="<?php echo esc_attr( get_option( 'tc_agents_discount_ceiling', 10 ) ); ?>" />
						<p class="description">Hard margin protection cap (e.g. 10%). The AI agent is strictly barred from offering discounts higher than this ceiling.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Voice Capability Master Toggle</th>
					<td>
						<label>
							<input type="checkbox" name="voice_enabled" value="1" <?php checked( '1', get_option( 'tc_agents_voice_enabled', '0' ) ); ?> />
							Enable Voice calling functionality (Keep unchecked unless actively needed).
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Human-in-the-Loop Gating</th>
					<td>
						<label>
							<input type="checkbox" name="require_human_approval" value="1" <?php checked( '1', get_option( 'tc_agents_require_human_approval', '1' ) ); ?> />
							<strong>Mandatory Human Approval</strong>: Enforce draft-for-approval on all bookings, payments, and itinerary cancellations.
						</label>
					</td>
				</tr>
			</table>

			<p>
				<input type="submit" class="button button-primary button-large" value="Save Guardrail Settings" />
			</p>
		</form>
	</div>

	<!-- Audit Log Table -->
	<div class="tc-card">
		<div class="tc-card-header">
			<h3>Agent Audit & Safety Event Log</h3>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 70px;">ID</th>
					<th style="width: 130px;">Severity</th>
					<th style="width: 180px;">Event Type</th>
					<th style="width: 100px;">Channel</th>
					<th>Details</th>
					<th style="width: 180px;">Timestamp</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 20px;">No audit events recorded yet.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $l ) : ?>
						<tr>
							<td>#<?php echo esc_html( $l['id'] ); ?></td>
							<td>
								<span class="tc-severity-badge tc-severity-<?php echo esc_attr( $l['severity'] ); ?>">
									<?php echo esc_html( strtoupper( $l['severity'] ) ); ?>
								</span>
							</td>
							<td><code><?php echo esc_html( $l['event_type'] ); ?></code></td>
							<td><?php echo esc_html( $l['channel'] ?: 'system' ); ?></td>
							<td>
								<small class="tc-json-preview"><?php echo esc_html( $l['details'] ); ?></small>
							</td>
							<td><?php echo esc_html( $l['created_at'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
