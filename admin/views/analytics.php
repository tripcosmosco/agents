<?php
/**
 * Admin AI Analytics, Latency, Token Usage & Telemetry View - Modern Celestial Design.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_msg   = $wpdb->prefix . 'tc_agent_messages';
$table_convo = $wpdb->prefix . 'tc_agent_conversations';
$table_audit = $wpdb->prefix . 'tc_agent_audit_log';

// Aggregate total metrics
$total_messages    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_msg" );
$assistant_msgs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_msg WHERE role = 'assistant'" );
$total_prompt_tok  = (int) $wpdb->get_var( "SELECT SUM(prompt_tokens) FROM $table_msg" ) ?: 0;
$total_comp_tok    = (int) $wpdb->get_var( "SELECT SUM(completion_tokens) FROM $table_msg" ) ?: 0;
$total_tokens      = $total_prompt_tok + $total_comp_tok;
$avg_latency       = (int) $wpdb->get_var( "SELECT AVG(latency_ms) FROM $table_msg WHERE latency_ms > 0" ) ?: 0;

// Estimated Cost ($0.003 / 1k prompt, $0.015 / 1k completion standard Claude/GPT-4o blend)
$est_cost_usd = ( ( $total_prompt_tok / 1000 ) * 0.003 ) + ( ( $total_comp_tok / 1000 ) * 0.015 );
$est_cost_inr = $est_cost_usd * 86.5; // Current INR exchange

// Per Provider Breakdown
$provider_stats = $wpdb->get_results(
	"SELECT 
		COALESCE(NULLIF(provider_used, ''), 'unknown') as provider,
		COUNT(*) as msg_count,
		SUM(prompt_tokens) as p_tokens,
		SUM(completion_tokens) as c_tokens,
		AVG(latency_ms) as avg_lat
	 FROM $table_msg 
	 WHERE role = 'assistant'
	 GROUP BY provider 
	 ORDER BY msg_count DESC",
	ARRAY_A
) ?: array();

// Channel Usage
$channels = $wpdb->get_results(
	"SELECT channel, COUNT(*) as cnt FROM $table_convo GROUP BY channel ORDER BY cnt DESC",
	ARRAY_A
) ?: array();

// Recent Audit Log Events
$audit_events = $wpdb->get_results( "SELECT * FROM $table_audit ORDER BY created_at DESC LIMIT 20", ARRAY_A ) ?: array();
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-ana-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1260px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-ana-hero {
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
.tc-ana-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-ana-hero p {
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

.tc-kpi-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 18px;
	margin-bottom: 24px;
}
@media (max-width: 900px) {
	.tc-kpi-grid { grid-template-columns: 1fr 1fr; }
}
.tc-kpi-card-lux {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	padding: 20px;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
	display: flex;
	flex-direction: column;
	transition: all 0.2s ease;
}
.tc-kpi-card-lux:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
}
.tc-kpi-lbl {
	font-size: 11.5px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
	margin-bottom: 6px;
}
.tc-kpi-val {
	font-size: 28px;
	font-weight: 800;
	line-height: 1.1;
	margin-bottom: 4px;
}
.tc-kpi-sub {
	font-size: 12px;
	color: #94a3b8;
}

.tc-card-lux {
	background: #ffffff;
	border-radius: 16px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
	padding: 24px;
	margin-bottom: 24px;
	transition: all 0.2s ease;
}
.tc-card-header-flex {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
}
.tc-card-title {
	font-size: 17px;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tc-grid-layout {
	display: grid;
	grid-template-columns: 1.25fr 0.95fr;
	gap: 24px;
}
@media (max-width: 1080px) {
	.tc-grid-layout { grid-template-columns: 1fr; }
}

.tc-table-lux {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
	margin-top: 8px;
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

.tc-btn-secondary {
	background: #f1f5f9;
	color: #334155;
	border: 1px solid #cbd5e1;
	font-weight: 600;
	padding: 8px 14px;
	border-radius: 8px;
	cursor: pointer;
	font-size: 12.5px;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	text-decoration: none;
}
.tc-btn-secondary:hover {
	background: #e2e8f0;
	color: #0f172a;
}
</style>

<div class="wrap tc-ana-wrap">
	<!-- Hero -->
	<div class="tc-ana-hero">
		<div>
			<h1>
				<span>📈</span>
				AI Intelligence, Token &amp; Latency Analytics
				<span class="tc-badge-gold">Live Telemetry</span>
			</h1>
			<p>Monitor LLM token consumption, latency benchmarks, estimated inference costs, and real-time security guardrails audit feeds.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-routing' ) ); ?>" class="tc-btn-secondary" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff;">
				<span>🔀</span> Manage AI Routing
			</a>
		</div>
	</div>

	<!-- Top Metrics Grid -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card-lux" style="border-top: 4px solid #0284c7;">
			<span class="tc-kpi-lbl">Total AI Generations</span>
			<span class="tc-kpi-val" style="color: #0284c7;"><?php echo esc_html( number_format( $assistant_msgs ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_messages ) ); ?> total chat messages</span>
		</div>
		<div class="tc-kpi-card-lux" style="border-top: 4px solid #8b5cf6;">
			<span class="tc-kpi-lbl">Tokens Processed</span>
			<span class="tc-kpi-val" style="color: #8b5cf6;"><?php echo esc_html( number_format( $total_tokens ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_prompt_tok ) ); ?> in / <?php echo esc_html( number_format( $total_comp_tok ) ); ?> out</span>
		</div>
		<div class="tc-kpi-card-lux" style="border-top: 4px solid #10b981;">
			<span class="tc-kpi-lbl">Average Latency</span>
			<span class="tc-kpi-val" style="color: #10b981;"><?php echo esc_html( number_format( $avg_latency ) ); ?> <small style="font-size: 16px;">ms</small></span>
			<span class="tc-kpi-sub">First-token streaming response</span>
		</div>
		<div class="tc-kpi-card-lux" style="border-top: 4px solid #f59e0b;">
			<span class="tc-kpi-lbl">Estimated Cost</span>
			<span class="tc-kpi-val" style="color: #f59e0b;">₹<?php echo esc_html( number_format( $est_cost_inr, 2 ) ); ?></span>
			<span class="tc-kpi-sub">$<?php echo esc_html( number_format( $est_cost_usd, 4 ) ); ?> USD blend</span>
		</div>
	</div>

	<div class="tc-grid-layout">
		<!-- Left: Provider & Channel Breakdown -->
		<div>
			<!-- Provider Performance -->
			<div class="tc-card-lux">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>⚡</span> AI Provider Routing Telemetry
					</div>
				</div>

				<?php if ( empty( $provider_stats ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;">No AI generations recorded yet.</p>
				<?php else : ?>
					<table class="tc-table-lux">
						<thead>
							<tr>
								<th>Provider</th>
								<th>Responses</th>
								<th>Tokens (Prompt/Comp)</th>
								<th>Avg Latency</th>
								<th>Circuit Breaker</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $provider_stats as $p ) :
								$cb_key = 'tc_circuit_' . $p['provider'] . '_tripped';
								$tripped = (bool) get_transient( $cb_key );
							?>
								<tr>
									<td>
										<strong style="text-transform: uppercase; font-size: 12px; color: #0f172a;"><?php echo esc_html( $p['provider'] ); ?></strong>
									</td>
									<td><?php echo esc_html( number_format( $p['msg_count'] ) ); ?></td>
									<td>
										<small style="color: #64748b;"><?php echo esc_html( number_format( (int) $p['p_tokens'] ) ); ?> / <?php echo esc_html( number_format( (int) $p['c_tokens'] ) ); ?></small>
									</td>
									<td>
										<strong><?php echo esc_html( round( (float) $p['avg_lat'] ) ); ?> ms</strong>
									</td>
									<td>
										<?php if ( $tripped ) : ?>
											<span style="background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px;">TRIPPED</span>
										<?php else : ?>
											<span style="background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px;">HEALTHY</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Channel Distribution -->
			<div class="tc-card-lux">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>📡</span> Conversations by Channel
					</div>
				</div>
				<div style="display: flex; gap: 16px; flex-wrap: wrap;">
					<?php foreach ( $channels as $ch ) : ?>
						<div style="flex: 1; min-width: 140px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center;">
							<span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #0284c7; background: #e0f2fe; padding: 4px 10px; border-radius: 12px;">
								<?php echo esc_html( ucfirst( $ch['channel'] ) ); ?>
							</span>
							<div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 10px;">
								<?php echo esc_html( number_format( $ch['cnt'] ) ); ?>
							</div>
							<span style="font-size: 11px; color: #64748b;">conversations</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Right: Live Audit & Telemetry Feed -->
		<div>
			<div class="tc-card-lux" style="border-left: 5px solid #8b5cf6;">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>🛡️</span> Security &amp; Audit Log Telemetry
					</div>
					<span style="font-size: 11px; background: #f1f5f9; padding: 3px 8px; border-radius: 8px; font-weight: 600;">Last 20 events</span>
				</div>

				<?php if ( empty( $audit_events ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;">No security or audit events recorded.</p>
				<?php else : ?>
					<div style="max-height: 520px; overflow-y: auto;">
						<?php foreach ( $audit_events as $ev ) :
							$sev = $ev['severity'] ?: 'info';
							$sev_col = ( 'error' === $sev || 'danger' === $sev ) ? '#dc2626' : ( 'warning' === $sev ? '#d97706' : '#2563eb' );
							$sev_bg  = ( 'error' === $sev || 'danger' === $sev ) ? '#fef2f2' : ( 'warning' === $sev ? '#fffbeb' : '#eff6ff' );
						?>
							<div style="padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
									<span style="background: <?php echo esc_attr( $sev_bg ); ?>; color: <?php echo esc_attr( $sev_col ); ?>; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 8px; text-transform: uppercase;">
										<?php echo esc_html( $sev ); ?>
									</span>
									<span style="font-size: 11px; color: #94a3b8;">
										<?php echo esc_html( human_time_diff( strtotime( $ev['created_at'] ), current_time( 'timestamp' ) ) . ' ago' ); ?>
									</span>
								</div>
								<strong style="font-size: 12.5px; color: #1e293b;"><?php echo esc_html( $ev['event_type'] ); ?></strong>
								<?php if ( ! empty( $ev['details'] ) ) : ?>
									<pre style="margin: 4px 0 0 0; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; max-height: 80px; overflow: hidden; text-overflow: ellipsis; white-space: pre-wrap; font-family: monospace; color: #334155;"><?php echo esc_html( $ev['details'] ); ?></pre>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
