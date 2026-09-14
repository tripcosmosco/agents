<?php
/**
 * Admin AI Analytics, Latency, Token Usage & Telemetry View.
 * Inspired by VM Sales OS & VMAI Intelligence Telemetry.
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

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'AI Intelligence, Token & Latency Analytics', 'tripcosmos-agents' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Monitor LLM token consumption, failover latencies, estimated costs, and real-time security telemetry.', 'tripcosmos-agents' ); ?></p>
		</div>
		<div class="tc-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-routing' ) ); ?>" class="button">
				<span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span> <?php esc_html_e( 'Manage AI Routing', 'tripcosmos-agents' ); ?>
			</a>
		</div>
	</div>

	<!-- Top Metrics Grid -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Total AI Generations', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #0284c7;"><?php echo esc_html( number_format( $assistant_msgs ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_messages ) ); ?> <?php esc_html_e( 'total chat turns', 'tripcosmos-agents' ); ?></span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Total Tokens Processed', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #8b5cf6;"><?php echo esc_html( number_format( $total_tokens ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_prompt_tok ) ); ?> in / <?php echo esc_html( number_format( $total_comp_tok ) ); ?> out</span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Average Latency', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #10b981;"><?php echo esc_html( number_format( $avg_latency ) ); ?> ms</span>
			<span class="tc-kpi-sub"><?php esc_html_e( 'First-token streaming response', 'tripcosmos-agents' ); ?></span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Estimated LLM Cost', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #f59e0b;">₹<?php echo esc_html( number_format( $est_cost_inr, 2 ) ); ?></span>
			<span class="tc-kpi-sub">$<?php echo esc_html( number_format( $est_cost_usd, 4 ) ); ?> USD</span>
		</div>
	</div>

	<div class="tc-two-col-layout">
		<!-- Left: Provider & Channel Breakdown -->
		<div>
			<!-- Provider Performance -->
			<div class="tc-card">
				<div class="tc-card-header">
					<h3><?php esc_html_e( 'AI Provider Routing Telemetry', 'tripcosmos-agents' ); ?></h3>
				</div>

				<?php if ( empty( $provider_stats ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No AI generations recorded yet.', 'tripcosmos-agents' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Provider', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Responses', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Tokens (Prompt/Comp)', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Avg Latency', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Health', 'tripcosmos-agents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $provider_stats as $p ) :
								$cb_key = 'tc_circuit_' . $p['provider'] . '_tripped';
								$tripped = (bool) get_transient( $cb_key );
							?>
								<tr>
									<td>
										<strong style="text-transform: uppercase; font-size: 12px;"><?php echo esc_html( $p['provider'] ); ?></strong>
									</td>
									<td><?php echo esc_html( number_format( $p['msg_count'] ) ); ?></td>
									<td>
										<small><?php echo esc_html( number_format( (int) $p['p_tokens'] ) ); ?> / <?php echo esc_html( number_format( (int) $p['c_tokens'] ) ); ?></small>
									</td>
									<td>
										<strong><?php echo esc_html( round( (float) $p['avg_lat'] ) ); ?> ms</strong>
									</td>
									<td>
										<?php if ( $tripped ) : ?>
											<span class="tc-badge tc-badge-danger" style="font-size: 10px; padding: 2px 6px;">TRIPPED</span>
										<?php else : ?>
											<span class="tc-badge tc-badge-success" style="font-size: 10px; padding: 2px 6px;">HEALTHY</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Channel Distribution -->
			<div class="tc-card">
				<div class="tc-card-header">
					<h3><?php esc_html_e( 'Conversations by Channel', 'tripcosmos-agents' ); ?></h3>
				</div>
				<div style="display: flex; gap: 16px; flex-wrap: wrap;">
					<?php foreach ( $channels as $ch ) : ?>
						<div style="flex: 1; min-width: 140px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center;">
							<span class="tc-channel-badge tc-channel-<?php echo esc_attr( $ch['channel'] ); ?>" style="font-size: 12px; padding: 4px 8px;">
								<?php echo esc_html( ucfirst( $ch['channel'] ) ); ?>
							</span>
							<div style="font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 8px;">
								<?php echo esc_html( number_format( $ch['cnt'] ) ); ?>
							</div>
							<span style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'conversations', 'tripcosmos-agents' ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Right: Live Audit & Telemetry Feed -->
		<div>
			<div class="tc-card">
				<div class="tc-card-header">
					<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Audit & Guardrails Telemetry', 'tripcosmos-agents' ); ?></h3>
					<span class="tc-tag"><?php esc_html_e( 'Last 20 events', 'tripcosmos-agents' ); ?></span>
				</div>

				<?php if ( empty( $audit_events ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No security or audit events recorded.', 'tripcosmos-agents' ); ?></p>
				<?php else : ?>
					<div class="tc-audit-feed" style="max-height: 520px; overflow-y: auto;">
						<?php foreach ( $audit_events as $ev ) :
							$sev = $ev['severity'] ?: 'info';
						?>
							<div class="tc-feed-item" style="padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
									<span class="tc-severity-badge tc-severity-<?php echo esc_attr( $sev ); ?>">
										<?php echo esc_html( strtoupper( $sev ) ); ?>
									</span>
									<span style="font-size: 11px; color: #94a3b8;">
										<?php echo esc_html( human_time_diff( strtotime( $ev['created_at'] ), current_time( 'timestamp' ) ) . ' ago' ); ?>
									</span>
								</div>
								<strong style="font-size: 12px; color: #1e293b;"><?php echo esc_html( $ev['event_type'] ); ?></strong>
								<?php if ( ! empty( $ev['details'] ) ) : ?>
									<pre style="margin: 4px 0 0 0; padding: 6px; background: #f8fafc; border-radius: 4px; font-size: 11px; max-height: 80px; overflow: hidden; text-overflow: ellipsis; white-space: pre-wrap;"><?php echo esc_html( $ev['details'] ); ?></pre>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
