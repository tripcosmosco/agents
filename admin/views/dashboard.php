<?php
/**
 * Admin Executive Dashboard View.
 * Upgraded with Self-Contained High-Res Theme, Live Visitor Radar, & Mobile App QR Pairing.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_convo  = $wpdb->prefix . 'tc_agent_conversations';
$table_msg    = $wpdb->prefix . 'tc_agent_messages';
$table_leads  = $wpdb->prefix . 'tc_agent_contacts';
$table_audit  = $wpdb->prefix . 'tc_agent_audit_log';

// Compute real-time KPIs
$total_convos  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo" );
$active_convos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE status = 'active'" );
$total_leads   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads" );
$won_leads     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads WHERE stage = 'won'" );
$total_msgs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_msg" );

// Pipeline value & win rate
$pipeline_val  = (float) $wpdb->get_var( "SELECT SUM(deal_value) FROM $table_leads WHERE stage NOT IN ('won', 'lost')" ) ?: 0.0;
$won_val       = (float) $wpdb->get_var( "SELECT SUM(deal_value) FROM $table_leads WHERE stage = 'won'" ) ?: 0.0;
$decided       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_leads WHERE stage IN ('won', 'lost')" );
$win_rate      = $decided > 0 ? round( ( $won_leads / $decided ) * 100 ) : 0;

// Channel counts
$web_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'web'" );
$wa_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'whatsapp'" );
$voice_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_convo WHERE channel = 'voice'" );

// Recent activity feed
$recent_convos = $wpdb->get_results( "SELECT * FROM $table_convo ORDER BY last_message_at DESC LIMIT 6", ARRAY_A ) ?: array();

// Live visitor telemetry events from audit log
$recent_visitors = $wpdb->get_results(
	"SELECT details, created_at FROM {$table_audit} WHERE event_type = 'visitor_landing' ORDER BY id DESC LIMIT 5",
	ARRAY_A
) ?: array();

// Mobile Companion pairing token
$mobile_token = class_exists( 'TC_Agents_Mobile_API' ) ? TC_Agents_Mobile_API::get_api_token() : get_option( 'tc_agents_mobile_api_token', 'a9P3cHjoyDq5Fkm0ucODllxnuuy35rXJ' );
$rest_base_url = rest_url( 'tc-agents/v1' );
$pairing_payload = wp_json_encode( array(
	'app'      => 'X-Agents',
	'base_url' => $rest_base_url,
	'token'    => $mobile_token,
) );
$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode( $pairing_payload );

$kill_switch    = TC_Agents_Guardrails::is_kill_switch_active();
$widget_enabled = '1' === (string) get_option( 'tc_agents_widget_enabled', '1' );
?>

<style>
/* Self-Contained Executive Dashboard Theme */
.tc-dash-wrap {
	margin: 20px 20px 0 0;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
}
.tc-dash-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: linear-gradient(135deg, #0b132b 0%, #1e1b4b 100%);
	padding: 24px 28px;
	border-radius: 18px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(11, 19, 43, 0.15);
	margin-bottom: 24px;
}
.tc-dash-header h1 {
	margin: 0 0 6px 0;
	font-size: 24px;
	font-weight: 800;
	color: #f8fafc;
	display: flex;
	align-items: center;
	gap: 12px;
}
.tc-dash-header p {
	margin: 0;
	color: #94a3b8;
	font-size: 13.5px;
}
.tc-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}
.tc-kpi-card {
	background: #ffffff;
	border-radius: 16px;
	padding: 20px 22px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
	display: flex;
	flex-direction: column;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.tc-kpi-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.tc-kpi-label {
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
	margin-bottom: 8px;
}
.tc-kpi-num {
	font-size: 28px;
	font-weight: 800;
	line-height: 1.1;
	margin-bottom: 6px;
}
.tc-kpi-sub {
	font-size: 12px;
	color: #94a3b8;
	font-weight: 500;
}
.tc-dash-grid {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 20px;
}
@media (max-width: 960px) {
	.tc-dash-grid {
		grid-template-columns: 1fr;
	}
}
.tc-panel-card {
	background: #ffffff;
	border-radius: 16px;
	padding: 24px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
	margin-bottom: 20px;
}
.tc-panel-card h3 {
	margin: 0 0 16px 0;
	font-size: 16px;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.tc-visitor-feed {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.tc-visitor-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	background: #f8fafc;
	border-radius: 12px;
	border: 1px solid #edf2f7;
}
.tc-visitor-icon {
	width: 32px;
	height: 32px;
	border-radius: 8px;
	background: #e0f2fe;
	color: #0284c7;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
}
.tc-qr-card {
	background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
	color: #ffffff;
	border-radius: 16px;
	padding: 24px;
	text-align: center;
	border: 1px solid rgba(245, 158, 11, 0.3);
}
.tc-qr-box {
	background: #ffffff;
	padding: 12px;
	border-radius: 12px;
	display: inline-block;
	margin: 14px auto;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}
.tc-token-copy-box {
	background: rgba(255, 255, 255, 0.08);
	border: 1px solid rgba(255, 255, 255, 0.15);
	border-radius: 8px;
	padding: 8px 12px;
	font-family: monospace;
	font-size: 11px;
	color: #f59e0b;
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 12px;
}
</style>

<div class="wrap tc-dash-wrap">
	<!-- Executive Header -->
	<div class="tc-dash-header">
		<div>
			<h1>🛕 TripCosmos Agents — Executive Command Center</h1>
			<p>Live Multi-Source Telephony, Active Website Lead Pipeline, and Zero-Cost Jio SIM Bridge.</p>
		</div>
		<div style="display: flex; align-items: center; gap: 12px;">
			<button type="button" id="tc-quick-toggle-widget" class="button <?php echo $widget_enabled ? 'button-secondary' : 'button-primary'; ?>" style="font-weight: 700; border-radius: 8px; height: 38px;">
				<?php echo $widget_enabled ? '🟢 Chatbot: Active' : '🔴 Chatbot: Disabled'; ?>
			</button>
			<?php if ( $kill_switch ) : ?>
				<span style="background: #ef4444; color: #fff; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 11px;">🚨 KILL SWITCH ENGAGED</span>
			<?php else : ?>
				<span style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 11px;">● ALL SYSTEMS OPERATIONAL</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Top Metric KPI Grid -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card">
			<span class="tc-kpi-label">Active Conversations</span>
			<span class="tc-kpi-num" style="color: #0284c7;"><?php echo esc_html( number_format( $active_convos ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_convos ) ); ?> total across web & phone</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-label">Captured Leads</span>
			<span class="tc-kpi-num" style="color: #10b981;"><?php echo esc_html( number_format( $total_leads ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( $won_leads ); ?> closed / won pilgrimage deals</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-label">Open Pipeline Value</span>
			<span class="tc-kpi-num" style="color: #ea580c;">₹<?php echo esc_html( number_format( $pipeline_val ) ); ?></span>
			<span class="tc-kpi-sub">Won Revenue: ₹<?php echo esc_html( number_format( $won_val ) ); ?></span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-label">Inquiry Win Rate</span>
			<span class="tc-kpi-num" style="color: #8b5cf6;"><?php echo esc_html( $win_rate ); ?>%</span>
			<span class="tc-kpi-sub">Decided Inquiries (Won vs Lost)</span>
		</div>

		<div class="tc-kpi-card">
			<span class="tc-kpi-label">AI Messages Processed</span>
			<span class="tc-kpi-num" style="color: #0f172a;"><?php echo esc_html( number_format( $total_msgs ) ); ?></span>
			<span class="tc-kpi-sub">Failover RAG & Lead Routing Active</span>
		</div>
	</div>

	<!-- 2-Column Command Grid -->
	<div class="tc-dash-grid">
		<!-- Left Main Column: Recent Threads & Live Radar -->
		<div>
			<!-- Live Website Presence Radar -->
			<div class="tc-panel-card">
				<h3>
					<span>👁️ Real-Time Website Visitor Radar</span>
					<span style="font-size: 11px; font-weight: 600; color: #10b981;">● Live Tracking Active</span>
				</h3>
				<?php if ( empty( $recent_visitors ) ) : ?>
					<p style="color: #64748b; font-size: 13px; margin: 0;">Waiting for website visitors to land on tour packages...</p>
				<?php else : ?>
					<div class="tc-visitor-feed">
						<?php foreach ( $recent_visitors as $rv ) :
							$d = json_decode( (string) $rv['details'], true ) ?: array();
							$title = ! empty( $d['page_title'] ) ? $d['page_title'] : 'TripCosmos Pilgrimage Tour';
							$time = human_time_diff( strtotime( $rv['created_at'] ) ) . ' ago';
						?>
							<div class="tc-visitor-row">
								<div class="tc-visitor-icon">👁️</div>
								<div style="flex: 1; min-width: 0;">
									<div style="font-weight: 600; font-size: 13px; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
										<?php echo esc_html( $title ); ?>
									</div>
									<div style="font-size: 11px; color: #64748b;">
										Browsing via Web • <?php echo esc_html( $time ); ?>
									</div>
								</div>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>" class="button button-small" style="font-size: 11px;">View In CRM</a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Recent Multi-Channel Conversations -->
			<div class="tc-panel-card">
				<h3>
					<span>💬 Recent Multi-Channel Conversations</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-conversations' ) ); ?>" class="button button-small">View All &rarr;</a>
				</h3>
				<table class="wp-list-table widefat fixed striped" style="border: 0; box-shadow: none;">
					<thead>
						<tr>
							<th style="font-weight: 700;">Session</th>
							<th style="font-weight: 700;">Channel</th>
							<th style="font-weight: 700;">Status</th>
							<th style="font-weight: 700;">Last Activity</th>
							<th style="font-weight: 700;">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $recent_convos ) ) : ?>
							<tr><td colspan="5" style="text-align:center; padding: 20px; color: #64748b;">No recent conversations.</td></tr>
						<?php else : ?>
							<?php foreach ( $recent_convos as $rc ) : ?>
								<tr>
									<td><code><?php echo esc_html( substr( $rc['session_id'], 0, 16 ) ); ?>...</code></td>
									<td>
										<span style="background: #e2e8f0; color: #334155; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600;">
											<?php echo esc_html( ucfirst( $rc['channel'] ) ); ?>
										</span>
									</td>
									<td>
										<span style="color: #10b981; font-weight: 700; font-size: 11px;">
											● <?php echo esc_html( ucfirst( str_replace( '_', ' ', $rc['status'] ) ) ); ?>
										</span>
									</td>
									<td style="color: #64748b; font-size: 12px;"><?php echo esc_html( human_time_diff( strtotime( $rc['last_message_at'] ) ) . ' ago' ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-conversations&view=' . $rc['id'] ) ); ?>" class="button button-small" style="font-weight: 600;">View Thread</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Right Side Column: Mobile App Pairing & Shortcuts -->
		<div>
			<!-- Mobile Companion QR Code Card -->
			<div class="tc-qr-card">
				<h3 style="margin: 0 0 6px 0; color: #f59e0b; font-size: 16px;">📱 Pair Android App (X-Agents)</h3>
				<p style="margin: 0; font-size: 12px; color: #cbd5e1;">Scan this QR code with the companion phone to link the zero-cost Jio SIM bridge & receive live lead alerts.</p>
				<div class="tc-qr-box">
					<img src="<?php echo esc_url( $qr_code_url ); ?>" alt="X-Agents QR Code" style="width: 150px; height: 150px; display: block;" />
				</div>
				<div style="font-size: 11px; color: #94a3b8;">X-Mobile-Token:</div>
				<div class="tc-token-copy-box">
					<span><?php echo esc_html( substr( $mobile_token, 0, 12 ) . '...' . substr( $mobile_token, -6 ) ); ?></span>
					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( $mobile_token ); ?>'); alert('API Token copied to clipboard!');">Copy</button>
				</div>
			</div>

			<!-- Quick Shortcuts -->
			<div class="tc-panel-card" style="margin-top: 20px;">
				<h3 style="margin-bottom: 12px;">⚡ Rapid Console Links</h3>
				<ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px;">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tripcosmos-agents' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">💬 Master AI Agent Test Console</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">📊 Leads & Deals Kanban Pipeline</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">🔌 CRM & Telephony Integrations</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">📚 Temple Timings & Tariff Knowledge</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>
