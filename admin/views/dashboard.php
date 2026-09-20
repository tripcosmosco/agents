<?php
/**
 * Admin Executive Dashboard View.
 * Inspired by VMAI & VM Sales OS Executive Dashboards.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_convo = $wpdb->prefix . 'tc_agent_conversations';
$table_msg   = $wpdb->prefix . 'tc_agent_messages';
$table_leads = $wpdb->prefix . 'tc_agent_contacts';

// Compute real-time KPIs
$total_convos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo" );
$active_convos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE status = 'active'" );
$total_leads  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads" );
$won_leads    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads WHERE stage = 'won'" );
$total_msgs   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_msg" );

// Pipeline value & win rate
$pipeline_val = (float) $wpdb->get_var( "SELECT SUM(deal_value) FROM $table_leads WHERE stage NOT IN ('won', 'lost')" ) ?: 0.0;
$won_val      = (float) $wpdb->get_var( "SELECT SUM(deal_value) FROM $table_leads WHERE stage = 'won'" ) ?: 0.0;
$decided      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads WHERE stage IN ('won', 'lost')" );
$win_rate     = $decided > 0 ? round( ( $won_leads / $decided ) * 100 ) : 0;

// Channel counts
$web_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'web'" );
$wa_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'whatsapp'" );
$voice_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'voice'" );

// Recent activity feed
$recent_convos = $wpdb->get_results( "SELECT * FROM $table_convo ORDER BY last_message_at DESC LIMIT 8", ARRAY_A ) ?: array();
$kill_switch     = TC_Agents_Guardrails::is_kill_switch_active();
$widget_enabled  = '1' === (string) get_option( 'tc_agents_widget_enabled', '1' );
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-dashboard"></span> TripCosmos Agents — Executive Dashboard</h1>
			<p class="description">Real-time operational command center: active chats, lead pipelines, multi-channel metrics, and revenue forecasts.</p>
		</div>
		<div class="tc-header-actions" style="display: flex; align-items: center; gap: 10px;">
			<button type="button" id="tc-quick-toggle-widget" class="button <?php echo $widget_enabled ? 'button-secondary' : 'button-primary'; ?>" style="font-weight: 600;">
				<?php echo $widget_enabled ? '🟢 Chatbot: Active (Click to Disable)' : '🔴 Chatbot: Disabled (Click to Enable)'; ?>
			</button>
			<?php if ( $kill_switch ) : ?>
				<span class="tc-badge tc-badge-danger">🚨 KILL SWITCH ENGAGED</span>
			<?php else : ?>
				<span class="tc-badge tc-badge-success"><span class="tc-indicator-dot online"></span> ALL SYSTEMS OPERATIONAL</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Top Metric KPI Cards -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card">
			<span class="tc-kpi-title">Active Conversations</span>
			<span class="tc-kpi-value" style="color: #0284c7;"><?php echo esc_html( number_format( $active_convos ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_convos ) ); ?> total across all channels</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-title">Total Captured Leads</span>
			<span class="tc-kpi-value" style="color: #10b981;"><?php echo esc_html( number_format( $total_leads ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( $won_leads ); ?> closed / won expeditions</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-title">Open Pipeline Value</span>
			<span class="tc-kpi-value" style="color: #f59e0b;">₹<?php echo esc_html( number_format( $pipeline_val ) ); ?></span>
			<span class="tc-kpi-sub">Won Revenue: ₹<?php echo esc_html( number_format( $won_val ) ); ?></span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-title">Inquiry Win Rate</span>
			<span class="tc-kpi-value" style="color: #8b5cf6;"><?php echo esc_html( $win_rate ); ?>%</span>
			<span class="tc-kpi-sub">Decided Inquiries (Won vs Lost)</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-title">AI Messages Served</span>
			<span class="tc-kpi-value" style="color: #0f172a;"><?php echo esc_html( number_format( $total_msgs ) ); ?></span>
			<span class="tc-kpi-sub">Multi-provider failover protected</span>
		</div>
	</div>

	<!-- Channel Breakdown & Quick Actions -->
	<div class="tc-two-col-layout" style="margin-top: 24px;">
		<!-- Left: Recent Conversations Timeline -->
		<div class="tc-col-main">
			<div class="tc-card">
				<div class="tc-card-header">
					<h3>Recent Multi-Channel Conversations</h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-conversations' ) ); ?>" class="button button-small">View All &rarr;</a>
				</div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Session</th>
							<th>Channel</th>
							<th>Status</th>
							<th>Last Activity</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $recent_convos ) ) : ?>
							<tr><td colspan="5" style="text-align:center; padding: 20px;">No recent conversations.</td></tr>
						<?php else : ?>
							<?php foreach ( $recent_convos as $rc ) : ?>
								<tr>
									<td><code><?php echo esc_html( $rc['session_id'] ); ?></code></td>
									<td>
										<span class="tc-channel-badge tc-channel-<?php echo esc_attr( $rc['channel'] ); ?>">
											<?php echo esc_html( ucfirst( $rc['channel'] ) ); ?>
										</span>
									</td>
									<td>
										<span class="tc-status-pill tc-status-<?php echo esc_attr( $rc['status'] ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $rc['status'] ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( human_time_diff( strtotime( $rc['last_message_at'] ) ) . ' ago' ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-conversations&view=' . $rc['id'] ) ); ?>" class="button button-small">View Thread</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Right: Channel Share & Fast Navigation -->
		<div class="tc-col-side">
			<div class="tc-card">
				<h3>Conversations by Channel</h3>
				<div class="tc-channel-bars">
					<div class="tc-bar-row">
						<span>🌐 Web Chat Widget</span>
						<strong><?php echo esc_html( $web_count ); ?></strong>
					</div>
					<div class="tc-bar-row">
						<span>💬 WhatsApp Bridge</span>
						<strong><?php echo esc_html( $wa_count ); ?></strong>
					</div>
					<div class="tc-bar-row">
						<span>📞 Voice Telephony</span>
						<strong><?php echo esc_html( $voice_count ); ?></strong>
					</div>
				</div>
			</div>

			<div class="tc-card">
				<h3>Command Shortcuts</h3>
				<ul class="tc-quick-prompts">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tripcosmos-agents' ) ); ?>">💬 Open Master Console</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>">📊 Review Lead Pipeline & Deals</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>">📚 Manage Knowledge Base & FAQs</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-routing' ) ); ?>">⚡ Check Provider Health Pings</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>
