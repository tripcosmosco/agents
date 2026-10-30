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

// Live active visitors (from transient map + audit log fallback)
$active_transient = get_transient( 'tc_live_active_visitors' );
$radar_visitors   = array();
$seen_sessions    = array();

if ( is_array( $active_transient ) && ! empty( $active_transient ) ) {
	$now_ts = time();
	foreach ( $active_transient as $vkey => $vdata ) {
		$seen_ts = isset( $vdata['last_seen_ts'] ) ? (int) $vdata['last_seen_ts'] : 0;
		if ( ( $now_ts - $seen_ts ) <= 900 ) {
			$radar_visitors[] = array(
				'is_online'  => true,
				'details'    => $vdata,
				'created_at' => isset( $vdata['last_seen_at'] ) ? $vdata['last_seen_at'] : current_time( 'mysql' ),
			);
			if ( ! empty( $vdata['session_id'] ) ) {
				$seen_sessions[ $vdata['session_id'] ] = true;
			}
		}
	}
}

if ( count( $radar_visitors ) < 6 ) {
	$recent_logs = $wpdb->get_results(
		"SELECT details, created_at FROM {$table_audit} WHERE event_type = 'visitor_landing' ORDER BY id DESC LIMIT 8",
		ARRAY_A
	) ?: array();

	foreach ( $recent_logs as $rl ) {
		$d = json_decode( (string) $rl['details'], true ) ?: array();
		$sess = ! empty( $d['session_id'] ) ? $d['session_id'] : '';
		if ( ! empty( $sess ) && ! empty( $seen_sessions[ $sess ] ) ) {
			continue;
		}
		if ( ! empty( $sess ) ) {
			$seen_sessions[ $sess ] = true;
		}
		$radar_visitors[] = array(
			'is_online'  => false,
			'details'    => $d,
			'created_at' => $rl['created_at'],
		);
		if ( count( $radar_visitors ) >= 6 ) {
			break;
		}
	}
}
$recent_visitors = $radar_visitors;

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
									<?php echo ! empty( $visitor_name ) ? "&#128100;" : ( $is_online ? "&#128994;" : "&#128065;" ); ?>
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
							$d = is_array( $rv['details'] ) ? $rv['details'] : ( json_decode( (string) $rv['details'], true ) ?: array() );
							$title = ! empty( $d['page_title'] ) ? $d['page_title'] : 'TripCosmos Pilgrimage Tour';
							$page_url = ! empty( $d['page_url'] ) ? $d['page_url'] : '';
							$visitor_name = ! empty( $d['visitor_name'] ) ? sanitize_text_field( $d['visitor_name'] ) : ( ! empty( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '' );
							$visitor_phone = ! empty( $d['visitor_phone'] ) ? sanitize_text_field( $d['visitor_phone'] ) : ( ! empty( $d['phone'] ) ? sanitize_text_field( $d['phone'] ) : '' );
							$city = ! empty( $d['city'] ) ? sanitize_text_field( $d['city'] ) : '';
							$country = ! empty( $d['country'] ) ? sanitize_text_field( $d['country'] ) : '';
							$device = ! empty( $d['device'] ) ? sanitize_text_field( $d['device'] ) : '';
							$is_online = ! empty( $rv['is_online'] );
							$time = human_time_diff( strtotime( $rv['created_at'] ) ) . ' ago';
							$geo_parts = array_filter( array( $city, $country ) );
							$geo_str = ! empty( $geo_parts ) ? implode( ', ', $geo_parts ) : 'Web';
						?>
							<div class="tc-visitor-row" style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #f1f5f9; background: <?php echo $is_online ? '#f0fdf4' : 'transparent'; ?>;">
								<div class="tc-visitor-icon" style="font-size: 18px; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; background: <?php echo $is_online ? '#dcfce7' : '#f1f5f9'; ?>; border-radius: 50%;">
									<?php echo ! empty( $visitor_name ) ? '&#128100;' : ( $is_online ? '&#128994;' : '&#128065;' ); ?>
								</div>
								<div style="flex: 1; min-width: 0;">
									<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
										<?php if ( ! empty( $visitor_name ) ) : ?>
											<strong style="font-size: 13.5px; color: #0f172a;">&#128100; <?php echo esc_html( $visitor_name ); ?></strong>
											<span style="font-size: 10px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 7px; border-radius: 999px;">
												WP User Aware
											</span>
										<?php else : ?>
											<span style="font-size: 13px; font-weight: 600; color: #334155;">Anonymous Visitor</span>
										<?php endif; ?>

										<?php if ( $is_online ) : ?>
											<span style="font-size: 10px; font-weight: 800; background: #bbf7d0; color: #15803d; padding: 2px 6px; border-radius: 6px;">&#9679; ONLINE NOW</span>
										<?php endif; ?>

										<?php if ( ! empty( $visitor_phone ) ) : ?>
											<a href="tel:<?php echo esc_attr( $visitor_phone ); ?>" style="font-size: 11.5px; font-weight: 600; color: #ea580c; text-decoration: none;">
												&#128222; <?php echo esc_html( $visitor_phone ); ?>
											</a>
										<?php endif; ?>
									</div>

									<div style="font-size: 12.5px; color: #475569; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
										<?php if ( ! empty( $page_url ) ) : ?>
											<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" style="color: #0284c7; text-decoration: none;" title="<?php echo esc_attr( $title ); ?>">
												&#128196; <?php echo esc_html( $title ); ?> &nearr;
											</a>
										<?php else : ?>
											<span>&#128196; <?php echo esc_html( $title ); ?></span>
										<?php endif; ?>
									</div>

									<div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">&#128205; <?php echo esc_html( $geo_str ); ?> <?php if ( $device ) : ?>&bull; <?php echo esc_html( $device ); ?><?php endif; ?> &bull; <?php echo esc_html( $time ); ?></div>
								</div>

								<div style="display: flex; gap: 6px;">
									<?php if ( ! empty( $visitor_phone ) ) : ?>
										<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $visitor_phone ) ); ?>" target="_blank" class="button button-small" style="background: #25D366; color: #fff; border: 0; font-weight: 600; font-size: 11px;">WhatsApp</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>" class="button button-small" style="font-size: 11px;">CRM</a>
								</div>
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
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-finance' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left; font-weight: 700; color: #b45309; border-color: #fcd34d; background: #fffbeb;">💰 Financial Intelligence Hub (SBI, Zoho, ROI)</a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: left;">📚 Temple Timings & Tariff Knowledge</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>
