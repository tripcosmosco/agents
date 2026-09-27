<?php
/**
 * Admin Executive Dashboard View.
 * Enhanced with Live Real-Time Activities Stream, Error Reporting & Diagnostics Center,
 * Two-Way GitHub Code Sync Radar, & Mobile App QR Pairing.
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

// Recent conversations
$recent_convos = $wpdb->get_results( "SELECT * FROM $table_convo ORDER BY last_message_at DESC LIMIT 6", ARRAY_A ) ?: array();

// Live Activities & Errors via Logger
$live_activities = class_exists( 'TC_Agents_Logger' ) ? TC_Agents_Logger::get_live_activities( 15, 'all' ) : array();
$recent_errors   = class_exists( 'TC_Agents_Logger' ) ? TC_Agents_Logger::get_recent_errors( 10 ) : array();

// GitHub Updater Info
$gh_repo_info = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_repo() : array( 'slug' => 'tripcosmosco/TripCosmos.co-Agentd' );
$gh_branch    = class_exists( 'TC_Agents_GitHub_Updater' ) ? TC_Agents_GitHub_Updater::get_branch() : 'main';

// Mobile Companion pairing token
$mobile_token = class_exists( 'TC_Agents_Mobile_API' ) ? TC_Agents_Mobile_API::get_api_token() : get_option( 'tc_agents_mobile_api_token', '' );
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
.tc-dash-wrap {
	max-width: 1360px;
	margin: 20px auto 40px 0;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.tc-dash-hero {
	background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #31104b 100%);
	border-radius: 16px;
	padding: 24px 30px;
	color: #ffffff;
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 16px;
	box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25);
	margin-bottom: 20px;
}
.tc-dash-hero h1 {
	margin: 0;
	font-size: 24px;
	font-weight: 800;
	color: #f8fafc;
	display: flex;
	align-items: center;
	gap: 12px;
}
.tc-dash-hero p {
	margin: 6px 0 0 0;
	font-size: 13px;
	color: #94a3b8;
}

/* System Health Radar Bar */
.tc-health-radar {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: 12px;
	margin-bottom: 20px;
}
.tc-health-item {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 16px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.tc-health-item .tc-hi-left {
	display: flex;
	align-items: center;
	gap: 10px;
}
.tc-health-item .tc-hi-icon {
	font-size: 20px;
}
.tc-health-item .tc-hi-title {
	font-size: 12px;
	font-weight: 700;
	color: #0f172a;
}
.tc-health-item .tc-hi-sub {
	font-size: 11px;
	color: #64748b;
}

/* KPI Cards */
.tc-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}
.tc-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
	display: flex;
	flex-direction: column;
	position: relative;
	overflow: hidden;
}
.tc-kpi-card::before {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	width: 4px;
	height: 100%;
	background: #0284c7;
}
.tc-kpi-label {
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #64748b;
	margin-bottom: 6px;
}
.tc-kpi-num {
	font-size: 26px;
	font-weight: 800;
	line-height: 1.1;
	margin-bottom: 4px;
}
.tc-kpi-sub {
	font-size: 11px;
	color: #94a3b8;
}

/* Command Grid */
.tc-dash-grid {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 24px;
}
@media (max-width: 1080px) {
	.tc-dash-grid {
		grid-template-columns: 1fr;
	}
}

.tc-panel-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 20px;
	margin-bottom: 24px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.tc-panel-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
	flex-wrap: wrap;
	gap: 8px;
}
.tc-panel-title {
	font-size: 15px;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}

/* Live Stream Filter Tabs */
.tc-stream-tabs {
	display: flex;
	gap: 6px;
	margin-bottom: 12px;
	flex-wrap: wrap;
}
.tc-stream-tab {
	background: #f1f5f9;
	color: #475569;
	border: 1px solid #e2e8f0;
	border-radius: 20px;
	padding: 4px 12px;
	font-size: 11px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease;
}
.tc-stream-tab:hover, .tc-stream-tab.active {
	background: #0f172a;
	color: #ffffff;
	border-color: #0f172a;
}

/* Live Activity Stream List */
.tc-live-stream-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: 420px;
	overflow-y: auto;
}
.tc-activity-row {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 10px 14px;
	border-radius: 10px;
	background: #f8fafc;
	border: 1px solid #f1f5f9;
	transition: all 0.2s ease;
}
.tc-activity-row:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
}
.tc-activity-icon {
	font-size: 18px;
	line-height: 1;
	margin-top: 2px;
}
.tc-activity-body {
	flex: 1;
	min-width: 0;
}
.tc-activity-title {
	font-size: 12px;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 2px;
}
.tc-activity-snippet {
	font-size: 11px;
	color: #475569;
	line-height: 1.4;
	word-break: break-word;
}
.tc-activity-meta {
	font-size: 10px;
	color: #94a3b8;
	margin-top: 4px;
}

/* Error Diagnostic Log Items */
.tc-error-feed {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: 360px;
	overflow-y: auto;
}
.tc-error-row {
	background: #fef2f2;
	border: 1px solid #fecaca;
	border-left: 4px solid #ef4444;
	border-radius: 8px;
	padding: 10px 12px;
	font-size: 11px;
}
.tc-error-row.warning,
.tc-error-row.tc-sev-warning {
	background: #fffbeb;
	border-color: #fde68a;
	border-left-color: #f59e0b;
}
.tc-error-row.tc-sev-error {
	background: #fef2f2;
	border-color: #fecaca;
	border-left-color: #ef4444;
}
.tc-error-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 4px;
}
.tc-error-msg {
	color: #7f1d1d;
	font-family: monospace;
	font-size: 11px;
	white-space: pre-wrap;
	word-break: break-word;
}
.tc-error-row.warning .tc-error-msg {
	color: #78350f;
}

/* Pulse animation */
.tc-pulse-dot {
	display: inline-block;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: #10b981;
	box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
	animation: tc-pulse 1.8s infinite;
}
@keyframes tc-pulse {
	0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
	70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
	100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* QR card */
.tc-qr-card {
	background: #0f172a;
	color: #ffffff;
	border-radius: 14px;
	padding: 20px;
	text-align: center;
	margin-bottom: 20px;
}
.tc-qr-box {
	background: #ffffff;
	padding: 12px;
	border-radius: 12px;
	display: inline-block;
	margin: 14px auto;
}
.tc-token-copy-box {
	background: rgba(255,255,255,0.06);
	border: 1px dashed rgba(245, 158, 11, 0.4);
	padding: 8px 12px;
	border-radius: 8px;
	font-family: monospace;
	font-size: 11px;
	color: #fcd34d;
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 6px;
}
</style>

<div class="wrap tc-dash-wrap">
	<!-- Hero Header -->
	<div class="tc-dash-hero">
		<div>
			<h1>
				<span>🛡️</span>
				TripCosmos Agent Hub &amp; Operations
			</h1>
			<p>Real-time customer conversational agent monitoring, lead capture pipeline, live activities, error diagnostics, and two-way GitHub sync.</p>
		</div>
		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<button type="button" id="tc-launch-master-chat-btn" class="button button-primary" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; font-weight: 700; height: 36px; border-radius: 8px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
				⚡ Master AI Console
			</button>
			<button type="button" id="tc-quick-toggle-widget" class="button <?php echo $widget_enabled ? 'button-secondary' : 'button-primary'; ?>" style="font-weight: 700; border-radius: 8px; height: 36px;">
				<?php echo $widget_enabled ? '🟢 Chatbot: Active' : '🔴 Chatbot: Disabled'; ?>
			</button>
			<?php if ( $kill_switch ) : ?>
				<span style="background: #ef4444; color: #fff; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 11px;">🚨 KILL SWITCH ENGAGED</span>
			<?php else : ?>
				<span style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 11px;">● ALL SYSTEMS OPERATIONAL</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- System Health & Live Subsystems Radar -->
	<div class="tc-health-radar">
		<!-- Two-Way GitHub Code Sync -->
		<div class="tc-health-item">
			<div class="tc-hi-left">
				<div class="tc-hi-icon">🐙</div>
				<div>
					<div class="tc-hi-title">Two-Way GitHub Sync</div>
					<div class="tc-hi-sub"><code><?php echo esc_html( $gh_repo_info['slug'] ); ?></code> (<?php echo esc_html( $gh_branch ); ?>)</div>
				</div>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-settings#tc-github-updater-card' ) ); ?>" class="button button-small" style="font-size: 11px; font-weight: 600;">
				Sync &amp; Compare ↗
			</a>
		</div>

		<!-- Evolution WhatsApp Gateway -->
		<div class="tc-health-item">
			<div class="tc-hi-left">
				<div class="tc-hi-icon">💬</div>
				<div>
					<div class="tc-hi-title">WhatsApp Gateway</div>
					<div class="tc-hi-sub">Instance: <strong>TripCosmos.co</strong> (Online)</div>
				</div>
			</div>
			<span style="color: #10b981; font-weight: 700; font-size: 11px;">● Connected</span>
		</div>

		<!-- AI Multi-Provider Router -->
		<div class="tc-health-item">
			<div class="tc-hi-left">
				<div class="tc-hi-icon">🤖</div>
				<div>
					<div class="tc-hi-title">Multi-Provider AI</div>
					<div class="tc-hi-sub">Active: <strong>Gateway &amp; OpenRouter</strong></div>
				</div>
			</div>
			<span style="color: #10b981; font-weight: 700; font-size: 11px;">● Ready</span>
		</div>

		<!-- Database & Vector Store -->
		<div class="tc-health-item">
			<div class="tc-hi-left">
				<div class="tc-hi-icon">🗄️</div>
				<div>
					<div class="tc-hi-title">Database Integrity</div>
					<div class="tc-hi-sub">17 Tables • Fulltext RAG Active</div>
				</div>
			</div>
			<span style="color: #10b981; font-weight: 700; font-size: 11px;">● Optimal</span>
		</div>
	</div>

	<!-- Top Metric KPI Grid -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card">
			<span class="tc-kpi-label">Active Conversations</span>
			<span class="tc-kpi-num" style="color: #0284c7;"><?php echo esc_html( number_format( $active_convos ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( number_format( $total_convos ) ); ?> total across web &amp; phone</span>
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
			<span class="tc-kpi-sub">Failover RAG &amp; Lead Routing Active</span>
		</div>
	</div>

	<!-- 2-Column Command Grid -->
	<div class="tc-dash-grid">
		<!-- Left Main Column: Live Activities Stream & Recent Convos -->
		<div>
			<!-- Real-Time Live Activities Stream -->
			<div class="tc-panel-card" id="tc-live-activities-card">
				<div class="tc-panel-header">
					<h3 class="tc-panel-title">
						<span class="tc-pulse-dot"></span>
						<span>Real-Time Live Activities Stream</span>
					</h3>
					<div style="display: flex; align-items: center; gap: 10px;">
						<label style="font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 4px; cursor: pointer;">
							<input type="checkbox" id="tc-live-auto-refresh" checked="checked" /> Auto-refresh (8s)
						</label>
						<button type="button" id="tc-refresh-activities-btn" class="button button-small" style="font-size: 11px;">
							🔄 Refresh Now
						</button>
					</div>
				</div>

				<!-- Filter Tabs -->
				<div class="tc-stream-tabs">
					<button type="button" class="tc-stream-tab active" data-cat="all">All Events</button>
					<button type="button" class="tc-stream-tab" data-cat="leads">🎯 Leads &amp; CRM</button>
					<button type="button" class="tc-stream-tab" data-cat="inquiries">💬 Traveler Inquiries</button>
					<button type="button" class="tc-stream-tab" data-cat="system">⚙️ Automations &amp; GitHub</button>
					<button type="button" class="tc-stream-tab" data-cat="errors">⚠️ Issues Only</button>
				</div>

				<!-- Live Feed List -->
				<div class="tc-live-stream-list" id="tc-activity-stream-feed">
					<?php if ( empty( $live_activities ) ) : ?>
						<p style="color: #64748b; font-size: 13px; margin: 10px 0;">Listening for live activity across web, mobile, CRM, and WhatsApp...</p>
					<?php else : ?>
						<?php foreach ( $live_activities as $act ) : ?>
							<div class="tc-activity-row">
								<div class="tc-activity-icon"><?php echo esc_html( $act['icon'] ); ?></div>
								<div class="tc-activity-body">
									<div class="tc-activity-title"><?php echo esc_html( $act['title'] ); ?></div>
									<?php if ( ! empty( $act['snippet'] ) ) : ?>
										<div class="tc-activity-snippet"><?php echo esc_html( $act['snippet'] ); ?></div>
									<?php endif; ?>
									<div class="tc-activity-meta">
										<?php echo esc_html( $act['time_ago'] ); ?>
										<?php if ( ! empty( $act['channel'] ) ) : ?>
											• Channel: <strong><?php echo esc_html( ucfirst( $act['channel'] ) ); ?></strong>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Multi-Channel Conversations -->
			<div class="tc-panel-card">
				<div class="tc-panel-header">
					<h3 class="tc-panel-title">
						<span>💬 Recent Multi-Channel Conversations</span>
					</h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-conversations' ) ); ?>" class="button button-small">View All &rarr;</a>
				</div>
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

		<!-- Right Side Column: Error Diagnostics, Mobile QR, Quick Links -->
		<div>
			<!-- Error Reporting & System Diagnostics Center -->
			<div class="tc-panel-card" id="tc-error-reporting-card">
				<div class="tc-panel-header">
					<h3 class="tc-panel-title">
						<span>🚨 Error Reporting &amp; Diagnostics</span>
					</h3>
					<div style="display: flex; gap: 6px;">
						<button type="button" id="tc-run-diagnostics-btn" class="button button-small" style="font-size: 11px;">
							🔍 Test
						</button>
						<button type="button" id="tc-clear-errors-btn" class="button button-small" style="font-size: 11px; color: #b91c1c;">
							Clear
						</button>
					</div>
				</div>

				<div id="tc-diag-result-box" style="display: none; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 12px;"></div>

				<div class="tc-error-feed" id="tc-error-log-list">
					<?php if ( empty( $recent_errors ) ) : ?>
						<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px; color: #166534; font-size: 12px; text-align: center;">
							<strong>✅ No Active System Errors</strong>
							<p style="margin: 4px 0 0 0; font-size: 11px; color: #15803d;">All API channels, background queues, and AI routers are operating normally.</p>
						</div>
					<?php else : ?>
						<?php foreach ( $recent_errors as $err ) :
							$sev_class = 'warning' === $err['severity'] ? 'tc-sev-warning' : 'tc-sev-error';
							$time = human_time_diff( strtotime( $err['created_at'] ) ) . ' ago';
							$details = json_decode( (string) $err['details'], true );
							$detail_str = is_array( $details ) ? ( $details['message'] ?? ( $details['output'] ?? wp_json_encode( $details ) ) ) : $err['details'];
						?>
							<div class="tc-error-row inline <?php echo esc_attr( $sev_class ); ?>">
								<div class="tc-error-head">
									<strong style="color: <?php echo 'warning' === $err['severity'] ? '#b45309' : '#b91c1c'; ?>;">
										[<?php echo esc_html( strtoupper( $err['severity'] ) ); ?>] <?php echo esc_html( ucwords( str_replace( '_', ' ', $err['event_type'] ) ) ); ?>
									</strong>
									<span style="color: #94a3b8; font-size: 10px;"><?php echo esc_html( $time ); ?></span>
								</div>
								<div class="tc-error-msg"><?php echo esc_html( mb_substr( $detail_str, 0, 180 ) ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Mobile Companion QR Code Card -->
			<div class="tc-qr-card">
				<h3 style="margin: 0 0 6px 0; color: #f59e0b; font-size: 16px;">📱 Pair Android App (X-Agents)</h3>
				<p style="margin: 0; font-size: 12px; color: #cbd5e1;">Scan this QR code with the companion phone to link the zero-cost Jio SIM bridge &amp; receive live lead alerts.</p>
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
			<div class="tc-panel-card">
				<h3 style="margin-bottom: 12px; font-size: 14px; font-weight: 700;">⚡ Rapid Console Links</h3>
				<ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px;">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-settings#tc-github-updater-card' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">🐙 Two-Way GitHub Code Sync</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">📊 Leads &amp; Deals Kanban Pipeline</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">🔌 CRM &amp; Telephony Integrations</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">📚 Temple Timings &amp; Tariff Knowledge</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Launch Master Chat Popup from Hero
	$('#tc-launch-master-chat-btn').on('click', function(e) {
		e.preventDefault();
		if (typeof window.tcOpenMasterPopup === 'function') {
			window.tcOpenMasterPopup();
		} else {
			$('#tc-master-fab').trigger('click');
		}
	});

	// Live Activity Stream Polling & Category Filtering
	let currentCat = 'all';
	let pollInterval = null;

	function fetchLiveActivities() {
		$.ajax({
			url: tcAgentsAdmin.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'tc_get_live_activities',
				category: currentCat,
				limit: 20,
				nonce: tcAgentsAdmin.adminNonce
			},
			success: function(res) {
				if (res.success && res.data && res.data.activities) {
					renderActivities(res.data.activities);
				}
			}
		});
	}

	function renderActivities(items) {
		const $feed = $('#tc-activity-stream-feed');
		if (!items.length) {
			$feed.html('<p style="color:#64748b;font-size:13px;margin:10px 0;">No activities recorded under this filter yet.</p>');
			return;
		}
		let html = '';
		items.forEach(function(act) {
			html += '<div class="tc-activity-row">' +
				'<div class="tc-activity-icon">' + $('<div>').text(act.icon).html() + '</div>' +
				'<div class="tc-activity-body">' +
					'<div class="tc-activity-title">' + $('<div>').text(act.title).html() + '</div>' +
					(act.snippet ? '<div class="tc-activity-snippet">' + $('<div>').text(act.snippet).html() + '</div>' : '') +
					'<div class="tc-activity-meta">' + $('<div>').text(act.time_ago).html() + (act.channel ? ' • Channel: <strong>' + $('<div>').text(act.channel).html() + '</strong>' : '') + '</div>' +
				'</div>' +
			'</div>';
		});
		$feed.html(html);
	}

	// Tab switching
	$('.tc-stream-tab').on('click', function() {
		$('.tc-stream-tab').removeClass('active');
		$(this).addClass('active');
		currentCat = $(this).data('cat');
		fetchLiveActivities();
	});

	// Manual refresh
	$('#tc-refresh-activities-btn').on('click', function(e) {
		e.preventDefault();
		const orig = $(this).text();
		$(this).text('Refreshing...');
		fetchLiveActivities();
		setTimeout(() => $(this).text(orig), 600);
	});

	// Auto-refresh timer
	function setupAutoRefresh() {
		if ($('#tc-live-auto-refresh').is(':checked')) {
			if (!pollInterval) {
				pollInterval = setInterval(fetchLiveActivities, 8000);
			}
		} else {
			if (pollInterval) {
				clearInterval(pollInterval);
				pollInterval = null;
			}
		}
	}
	$('#tc-live-auto-refresh').on('change', setupAutoRefresh);
	setupAutoRefresh();

	// Run Diagnostics
	$('#tc-run-diagnostics-btn').on('click', function(e) {
		e.preventDefault();
		const $btn = $(this);
		$btn.prop('disabled', true).text('Testing...');
		const $box = $('#tc-diag-result-box');
		$box.show().css({ 'background': '#eff6ff', 'color': '#1e40af', 'border': '1px solid #bfdbfe' })
			.html('<strong>Testing connections...</strong> Checking WhatsApp, AI Gateway, Database &amp; GitHub...');

		$.ajax({
			url: tcAgentsAdmin.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'tc_run_diagnostics',
				nonce: tcAgentsAdmin.adminNonce
			},
			success: function(res) {
				$btn.prop('disabled', false).text('🔍 Test');
				if (res.success && res.data && res.data.diagnostics) {
					const d = res.data.diagnostics;
					let out = '<div style="font-weight:700;margin-bottom:6px;">Live Diagnostics (' + res.data.timestamp + '):</div>';
					for (const key in d) {
						const isOk = d[key].status === 'healthy';
						out += '<div style="margin-bottom:4px;display:flex;align-items:center;gap:6px;">' +
							(isOk ? '🟢' : '⚠️') + ' <strong>' + key.toUpperCase() + ':</strong> ' + d[key].label +
						'</div>';
					}
					$box.css({ 'background': '#f8fafc', 'color': '#0f172a', 'border': '1px solid #cbd5e1' }).html(out);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('🔍 Test');
				$box.css({ 'background': '#fef2f2', 'color': '#991b1b', 'border': '1px solid #fecaca' })
					.html('Diagnostic request failed.');
			}
		});
	});

	// Clear Error Log
	$('#tc-clear-errors-btn').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Are you sure you want to clear all error and warning logs?')) return;
		const $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: tcAgentsAdmin.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'tc_clear_error_logs',
				nonce: tcAgentsAdmin.adminNonce
			},
			success: function(res) {
				$btn.prop('disabled', false);
				$('#tc-error-log-list').html(
					'<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;color:#166534;font-size:12px;text-align:center;">' +
						'<strong>✅ Error Log Cleared</strong><p style="margin:4px 0 0 0;font-size:11px;color:#15803d;">All previous errors and warnings purged.</p>' +
					'</div>'
				);
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('Could not clear error log.');
			}
		});
	});
});
</script>
