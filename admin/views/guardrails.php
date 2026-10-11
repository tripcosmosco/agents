<?php
/**
 * Admin Guardrails & Kill Switch View - Modern Celestial Design.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kill_switch_active = TC_Agents_Guardrails::is_kill_switch_active();
$logs               = TC_Agents_Logger::get_logs( array( 'limit' => 40 ) );
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-grd-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1240px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-grd-hero {
	background: linear-gradient(135deg, #0a0f1d 0%, #111e38 60%, #1e1b4b 100%);
	border-radius: 20px;
	padding: 28px 36px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(10, 15, 29, 0.25);
	margin-bottom: 24px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	border: 1px solid rgba(245, 158, 11, 0.2);
}
.tc-grd-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-grd-hero p {
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
	margin: 0 0 18px 0;
	color: #64748b;
	font-size: 13px;
	line-height: 1.5;
}

.tc-kill-box {
	border-radius: 16px;
	padding: 24px;
	margin-bottom: 24px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 20px;
	transition: all 0.3s ease;
}
.tc-kill-box.safe {
	background: #f0fdf4;
	border: 2px solid #86efac;
}
.tc-kill-box.tripped {
	background: #fef2f2;
	border: 2px solid #fca5a5;
}

.tc-input-lux, select.tc-input-lux, textarea.tc-input-lux {
	width: 100%;
	max-width: 420px;
	padding: 9px 12px;
	border: 1.5px solid #cbd5e1;
	border-radius: 8px;
	font-size: 13px;
	color: #1e293b;
	background: #f8fafc;
	outline: none;
	transition: all 0.2s ease;
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
	padding: 12px 24px;
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

.tc-table-lux {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}
.tc-table-lux th {
	background: #f8fafc;
	color: #475569;
	font-weight: 700;
	text-align: left;
	padding: 10px 14px;
	border-bottom: 1.5px solid #e2e8f0;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.tc-table-lux td {
	padding: 12px 14px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.tc-table-lux tr:hover td {
	background: #f8fafc;
}
</style>

<div class="wrap tc-grd-wrap">
	<!-- Hero -->
	<div class="tc-grd-hero">
		<div>
			<h1>
				<span>🛡️</span>
				Safety Guardrails &amp; Emergency Controls
				<span class="tc-badge-gold">Defense Layer</span>
			</h1>
			<p>Protect TripCosmos against unsupervised runaway execution, excessive API charges, unapproved discounts, and inadvertent customer communications.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents' ) ); ?>" class="button button-secondary" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff;">
				← Executive Dashboard
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065f46; font-weight: 600;">
			✓ Guardrail parameters updated successfully.
		</div>
	<?php endif; ?>

	<!-- EMERGENCY KILL SWITCH -->
	<div class="tc-kill-box <?php echo $kill_switch_active ? 'tripped' : 'safe'; ?>">
		<div>
			<h2 style="margin: 0 0 6px 0; font-size: 19px; color: <?php echo $kill_switch_active ? '#b91c1c' : '#15803d'; ?>; display: flex; align-items: center; gap: 8px;">
				<span>⚠️</span>
				EMERGENCY KILL SWITCH
				<span style="font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 800; background: <?php echo $kill_switch_active ? '#fee2e2; color: #b91c1c;' : '#dcfce7; color: #15803d;'; ?>">
					<?php echo $kill_switch_active ? 'HALTED' : 'ARMED & HEALTHY'; ?>
				</span>
			</h2>
			<p style="margin: 0; color: #475569; font-size: 13px; max-width: 650px;">
				Instantly freezes all outbound agent activities across all channels (Web chat, WhatsApp, and Voice calls). Evaluated before every dispatch, guaranteeing absolute containment.
			</p>
		</div>
		<div>
			<button type="button" class="button button-large tc-kill-btn" id="tc-toggle-kill-switch" data-current="<?php echo $kill_switch_active ? '1' : '0'; ?>" style="font-weight: 700; height: 44px; padding: 0 20px; border-radius: 10px; background: <?php echo $kill_switch_active ? '#ffffff; border-color: #cbd5e1; color: #0f172a;' : '#dc2626; border-color: #b91c1c; color: #ffffff;'; ?>">
				<?php echo $kill_switch_active ? 'DISENGAGE KILL SWITCH (RESUME)' : '🚨 ENGAGE EMERGENCY KILL SWITCH'; ?>
			</button>
		</div>
	</div>

	<!-- Guardrails Configuration -->
	<div class="tc-card-lux">
		<div class="tc-card-title">
			<span>⚡</span> Velocity &amp; Quota Protection Controls
		</div>
		<p class="tc-card-desc">Hard limits to prevent loop runaway, spam bombing, and margin dilution.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-guardrails' ) ); ?>">
			<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
			<input type="hidden" name="tc_agents_action" value="save_guardrails" />

			<table class="form-table">
				<tr>
					<th scope="row"><label for="rate_limit_hourly">Hourly Rate Limit (Per Visitor/Session)</label></th>
					<td>
						<input type="number" min="5" max="200" name="rate_limit_hourly" id="rate_limit_hourly" value="<?php echo esc_attr( get_option( 'tc_agents_rate_limit_hourly', 30 ) ); ?>" class="tc-input-lux" />
						<p class="description">Max messages a single visitor or WhatsApp number can send per hour before temporary throttle.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_daily_limit">WhatsApp Daily Send Quota</label></th>
					<td>
						<input type="number" min="10" max="2000" name="whatsapp_daily_limit" id="whatsapp_daily_limit" value="<?php echo esc_attr( get_option( 'tc_agents_whatsapp_daily_limit', 100 ) ); ?>" class="tc-input-lux" />
						<p class="description">Hard cap on daily outbound WhatsApp messages dispatched across all contacts.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="voice_daily_limit">Voice Calls Daily Limit</label></th>
					<td>
						<input type="number" min="1" max="100" name="voice_daily_limit" id="voice_daily_limit" value="<?php echo esc_attr( get_option( 'tc_agents_voice_daily_limit', 10 ) ); ?>" class="tc-input-lux" />
						<p class="description">Hard daily volume cap on automated AI outbound phone calls.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="discount_ceiling">Maximum Discount Ceiling (%)</label></th>
					<td>
						<input type="number" min="0" max="50" name="discount_ceiling" id="discount_ceiling" value="<?php echo esc_attr( get_option( 'tc_agents_discount_ceiling', 10 ) ); ?>" class="tc-input-lux" />
						<p class="description">Absolute margin protection: The AI is barred from offering discounts higher than this ceiling.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Voice Master Toggle</th>
					<td>
						<label style="font-weight: 600;">
							<input type="checkbox" name="voice_enabled" value="1" <?php checked( '1', get_option( 'tc_agents_voice_enabled', '0' ) ); ?> />
							Enable outbound Voice calling functionality (Default: Disabled for safety).
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Human-in-the-Loop Gating</th>
					<td>
						<label style="font-weight: 600;">
							<input type="checkbox" name="require_human_approval" value="1" <?php checked( '1', get_option( 'tc_agents_require_human_approval', '1' ) ); ?> />
							Enforce manual review before confirming bookings, charges, or cancellations.
						</label>
					</td>
				</tr>
			</table>

			<div style="margin-top: 18px;">
				<button type="submit" class="tc-btn-gold">
					<span>💾</span> Save Guardrail Settings
				</button>
			</div>
		</form>
	</div>

	<!-- Audit Log Table -->
	<div class="tc-card-lux">
		<div class="tc-card-title" style="margin-bottom: 14px;">
			<span>📜</span> Security, Safety &amp; Guardrail Events Feed
		</div>

		<table class="tc-table-lux">
			<thead>
				<tr>
					<th style="width: 70px;">ID</th>
					<th style="width: 120px;">Severity</th>
					<th style="width: 180px;">Event Type</th>
					<th style="width: 100px;">Channel</th>
					<th>Details</th>
					<th style="width: 180px;">Timestamp</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 24px; color: #64748b;">No security audit events recorded.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $l ) :
						$sev = $l['severity'] ?: 'info';
						$sev_col = ( 'error' === $sev || 'danger' === $sev ) ? '#dc2626' : ( 'warning' === $sev ? '#d97706' : '#2563eb' );
						$sev_bg  = ( 'error' === $sev || 'danger' === $sev ) ? '#fef2f2' : ( 'warning' === $sev ? '#fffbeb' : '#eff6ff' );
					?>
						<tr>
							<td>#<?php echo esc_html( $l['id'] ); ?></td>
							<td>
								<span style="background: <?php echo esc_attr( $sev_bg ); ?>; color: <?php echo esc_attr( $sev_col ); ?>; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 8px; text-transform: uppercase;">
									<?php echo esc_html( $sev ); ?>
								</span>
							</td>
							<td><code><?php echo esc_html( $l['event_type'] ); ?></code></td>
							<td><?php echo esc_html( $l['channel'] ?: 'system' ); ?></td>
							<td>
								<small style="font-family: monospace; font-size: 11.5px; color: #475569;"><?php echo esc_html( $l['details'] ); ?></small>
							</td>
							<td><small style="color: #64748b;"><?php echo esc_html( $l['created_at'] ); ?></small></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
