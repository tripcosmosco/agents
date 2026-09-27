<?php
/**
 * Admin Conversations & Transcripts View - Ultra Premium Chat CRM Edition.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tc_format_chat_markdown' ) ) {
	/**
	 * Parse markdown formatting from LLM responses into safe, structured HTML.
	 * Converts **bold**, *italics*, bullet lists, and raw URLs into formatted elements.
	 */
	function tc_format_chat_markdown( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		// 1. Sanitize HTML first to prevent XSS
		$safe = esc_html( (string) $content );

		// 2. Bold: **text** or __text__
		$safe = preg_replace( '/\*\*(.+?)\*\*/s', '<strong style="color: inherit; font-weight: 700;">$1</strong>', $safe );
		$safe = preg_replace( '/__(.+?)__/s', '<strong style="color: inherit; font-weight: 700;">$1</strong>', $safe );

		// 3. Italic: *text* (when not preceded or followed by *)
		$safe = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $safe );

		// 4. Inline code: `code`
		$safe = preg_replace( '/`([^`]+)`/', '<code style="background: rgba(0,0,0,0.06); padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: monospace;">$1</code>', $safe );

		// 5. Markdown Links: [label](https://...) -> convert to placeholders first
		$links = array();
		$safe  = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/',
			function( $m ) use ( &$links ) {
				$idx           = count( $links );
				$links[ $idx ] = '<a href="' . esc_url( $m[2] ) . '" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; font-weight: 600;">' . esc_html( $m[1] ) . ' ↗</a>';
				return "%%TC_MD_LINK_{$idx}%%";
			},
			$safe
		);

		// 6. Bare URLs: https://... -> clickable link (avoiding attributes)
		$safe = preg_replace_callback(
			'/(https?:\/\/[^\s<"\'\)]+)/i',
			function( $matches ) {
				$url      = $matches[1];
				$trailing = '';
				if ( preg_match( '/([.,;:]+)$/', $url, $punct ) ) {
					$trailing = $punct[1];
					$url      = substr( $url, 0, -strlen( $trailing ) );
				}
				return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; word-break: break-all; font-weight: 600;">' . esc_html( $url ) . ' ↗</a>' . $trailing;
			},
			$safe
		);

		// Restore original formatted markdown links
		foreach ( $links as $idx => $link_html ) {
			$safe = str_replace( "%%TC_MD_LINK_{$idx}%%", $link_html, $safe );
		}

		// 7. Bullet lists: "- item" or "• item"
		$lines = explode( "\n", $safe );
		$output = array();
		$in_list = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( preg_match( '/^[-*•]\s+(.+)$/', $trimmed, $m ) ) {
				if ( ! $in_list ) {
					$output[] = '<ul style="margin: 8px 0; padding-left: 20px; list-style-type: disc;">';
					$in_list = true;
				}
				$output[] = '<li style="margin-bottom: 4px; line-height: 1.5;">' . $m[1] . '</li>';
			} else {
				if ( $in_list ) {
					$output[] = '</ul>';
					$in_list = false;
				}
				$output[] = $line;
			}
		}
		if ( $in_list ) {
			$output[] = '</ul>';
		}

		$result = implode( "\n", $output );

		// 8. Convert remaining newlines to <br>
		$result = preg_replace( '/\n(?!\s*<\/?ul|\s*<li)/', "<br />\n", $result );
		$result = str_replace( array( "</ul><br />\n", "</li><br />\n" ), array( "</ul>\n", "</li>\n" ), $result );

		return $result;
	}
}


global $wpdb;
$table_convo = $wpdb->prefix . 'tc_agent_conversations';
$table_msg   = $wpdb->prefix . 'tc_agent_messages';

$filter_channel = sanitize_text_field( $_GET['channel'] ?? '' );
$filter_status  = sanitize_text_field( $_GET['status'] ?? '' );
$search_query   = sanitize_text_field( $_GET['s'] ?? '' );
$view_convo_id  = absint( $_GET['view'] ?? 0 );

// Handle Status & Agent updates from CRM Sidebar
$notice_msg = '';
if ( isset( $_POST['tc_update_convo_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tc_update_convo_nonce'] ) ), 'tc_update_convo_action' ) ) {
	$new_status   = sanitize_text_field( $_POST['tc_convo_status'] ?? '' );
	$new_agent    = sanitize_text_field( $_POST['tc_convo_agent'] ?? '' );
	$target_id    = absint( $_POST['tc_target_convo_id'] ?? 0 );

	if ( $target_id > 0 && in_array( $new_status, array( 'active', 'handed_off', 'resolved' ), true ) ) {
		$wpdb->update(
			$table_convo,
			array(
				'status'   => $new_status,
				'agent_id' => ! empty( $new_agent ) ? $new_agent : 'default',
			),
			array( 'id' => $target_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$notice_msg = 'Conversation #' . $target_id . ' status updated to ' . ucfirst( str_replace( '_', ' ', $new_status ) ) . '.';
	}
}

// Build query for conversations list
$where  = array( '1=1' );
$params = array();
if ( ! empty( $filter_channel ) ) {
	$where[]  = 'channel = %s';
	$params[] = $filter_channel;
}
if ( ! empty( $filter_status ) ) {
	$where[]  = 'status = %s';
	$params[] = $filter_status;
}
if ( ! empty( $search_query ) ) {
	$where[]  = 'session_id LIKE %s';
	$params[] = '%' . $wpdb->esc_like( $search_query ) . '%';
}

$where_sql = implode( ' AND ', $where );
$query     = "SELECT * FROM $table_convo WHERE $where_sql ORDER BY id DESC LIMIT 50";
$convos    = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ) : $wpdb->get_results( $query, ARRAY_A );

// If viewing a specific conversation
$current_convo = null;
$view_messages = array();
$avg_latency   = 0;
$total_tokens  = 0;

if ( $view_convo_id > 0 ) {
	$current_convo = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_convo WHERE id = %d", $view_convo_id ),
		ARRAY_A
	);

	if ( $current_convo ) {
		$view_messages = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table_msg WHERE conversation_id = %d ORDER BY id ASC", $view_convo_id ),
			ARRAY_A
		) ?: array();

		// Calculate metrics
		$latencies = array();
		foreach ( $view_messages as $vm ) {
			if ( ! empty( $vm['latency_ms'] ) && (int) $vm['latency_ms'] > 0 ) {
				$latencies[] = (int) $vm['latency_ms'];
			}
			$total_tokens += ( (int) ( $vm['prompt_tokens'] ?? 0 ) + (int) ( $vm['completion_tokens'] ?? 0 ) );
		}
		if ( ! empty( $latencies ) ) {
			$avg_latency = round( array_sum( $latencies ) / count( $latencies ) );
		}
	}
}
?>

<style>
/* Self-contained Premium Chat & CRM Styles */
.tc-wrap {
	max-width: 1360px;
	margin: 20px 20px 40px 0;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}

.tc-top-bar {
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

.tc-top-title h1 {
	margin: 0;
	font-size: 22px;
	font-weight: 800;
	color: #0b132b;
	display: flex;
	align-items: center;
	gap: 10px;
}

.tc-top-title p {
	margin: 4px 0 0 0;
	font-size: 13px;
	color: #64748b;
}

/* 2-Column Chat Layout */
.tc-chat-layout {
	display: flex;
	gap: 24px;
	align-items: flex-start;
	margin-bottom: 30px;
}

@media (max-width: 1024px) {
	.tc-chat-layout {
		flex-direction: column;
	}
	.tc-chat-sidebar {
		width: 100% !important;
	}
}

/* Main Chat Canvas */
.tc-chat-canvas {
	flex: 1;
	min-width: 0;
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.tc-canvas-header {
	background: #f8fafc;
	border-bottom: 1px solid #e2e8f0;
	padding: 14px 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.tc-canvas-title {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 15px;
	font-weight: 700;
	color: #0f172a;
}

/* Chat Message Thread */
.tc-messages-container {
	padding: 24px 24px;
	background: #f1f5f9;
	background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
	background-size: 20px 20px;
	display: flex;
	flex-direction: column;
	gap: 18px;
	max-height: 720px;
	overflow-y: auto;
}

/* Message Rows */
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

.tc-row-tool {
	justify-content: center;
}

/* Speech Bubbles */
.tc-bubble-user {
	max-width: 72%;
	background: linear-gradient(135deg, #1e3a8a 0%, #0b132b 100%);
	color: #ffffff !important;
	padding: 14px 18px;
	border-radius: 18px 18px 4px 18px;
	box-shadow: 0 4px 12px rgba(11, 19, 43, 0.15);
	position: relative;
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
	line-height: 1.6;
	color: #ffffff;
}

.tc-bubble-user .tc-bubble-text strong {
	color: #ffffff !important;
	font-weight: 700;
}

.tc-bubble-user .tc-bubble-text a {
	color: #93c5fd !important;
	text-decoration: underline;
	font-weight: 600;
}

.tc-bubble-user .tc-bubble-text li {
	color: #ffffff;
}

.tc-bubble-text {
	font-size: 14px;
	line-height: 1.6;
	color: #1e293b;
	word-break: break-word;
}

.tc-bubble-text strong {
	font-weight: 700;
	color: #0f172a;
}

.tc-bubble-text ul {
	margin: 8px 0 10px 20px !important;
	padding: 0 !important;
	list-style-type: disc !important;
}

.tc-bubble-text li {
	margin-bottom: 5px !important;
	line-height: 1.55;
	color: #1e293b;
}

.tc-bubble-text a {
	color: #2563eb !important;
	text-decoration: underline !important;
	font-weight: 600;
	word-break: break-all;
}

.tc-bubble-text a:hover {
	color: #1d4ed8 !important;
}


.tc-bubble-assistant {
	max-width: 76%;
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

.tc-latency-good {
	background: #dcfce7;
	color: #15803d;
}

.tc-latency-warn {
	background: #fef9c3;
	color: #a16207;
}

.tc-latency-bad {
	background: #fee2e2;
	color: #b91c1c;
}

.tc-bubble-assistant .tc-bubble-text {
	font-size: 14.5px;
	line-height: 1.6;
	color: #1e293b;
}

/* Tool Execution Box inside Assistant */
.tc-tool-execution-banner {
	margin-top: 12px;
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 8px;
	padding: 8px 12px;
	font-size: 12px;
}

.tc-tool-execution-banner summary {
	cursor: pointer;
	color: #b45309;
	font-weight: 600;
}

.tc-tool-execution-banner pre {
	margin-top: 8px;
	background: #0b132b;
	color: #38bdf8;
	padding: 10px;
	border-radius: 6px;
	font-size: 11px;
	overflow-x: auto;
}

/* Tool Response Block */
.tc-tool-response-card {
	width: 88%;
	background: #ffffff;
	border: 1.5px dashed #cbd5e1;
	border-radius: 14px;
	padding: 16px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.tc-tool-response-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
	font-size: 12px;
	color: #475569;
}

.tc-tool-title-tag {
	background: #ecfdf5;
	color: #047857;
	border: 1px solid #a7f3d0;
	padding: 3px 9px;
	border-radius: 6px;
	font-weight: 700;
	font-size: 11px;
}

/* Found Pilgrimage Cards Grid */
.tc-tours-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 12px;
	margin-bottom: 8px;
}

.tc-tour-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	overflow: hidden;
	transition: all 0.2s ease;
	display: flex;
	flex-direction: column;
}

.tc-tour-card:hover {
	border-color: #f59e0b;
	box-shadow: 0 4px 12px rgba(245, 158, 11, 0.12);
	transform: translateY(-2px);
}

.tc-tour-thumb {
	height: 105px;
	background-size: cover;
	background-position: center;
	position: relative;
	background-color: #0b132b;
}

.tc-tour-duration-badge {
	position: absolute;
	bottom: 6px;
	right: 6px;
	background: rgba(11, 19, 43, 0.9);
	color: #ffffff;
	font-size: 10px;
	font-weight: 700;
	padding: 2px 6px;
	border-radius: 4px;
}

.tc-tour-body {
	padding: 10px 12px;
	flex: 1;
	display: flex;
	flex-direction: column;
}

.tc-tour-dest {
	font-size: 10px;
	font-weight: 700;
	color: #f59e0b;
	text-transform: uppercase;
	margin-bottom: 3px;
}

.tc-tour-title {
	font-size: 13px;
	font-weight: 700;
	margin: 0 0 6px 0;
	line-height: 1.35;
}

.tc-tour-title a {
	color: #0f172a;
	text-decoration: none;
}

.tc-tour-title a:hover {
	color: #f59e0b;
}

.tc-tour-footer {
	margin-top: auto;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-top: 8px;
	border-top: 1px solid #f1f5f9;
}

.tc-tour-price {
	font-size: 14px;
	font-weight: 800;
	color: #d97706;
}

.tc-tour-cta {
	background: #f8fafc;
	border: 1px solid #cbd5e1;
	border-radius: 4px;
	padding: 2px 7px;
	font-size: 11px;
	font-weight: 600;
	color: #334155;
	text-decoration: none;
}

.tc-tour-cta:hover {
	background: #0b132b;
	color: #ffffff;
	border-color: #0b132b;
}

/* CRM Sidebar */
.tc-chat-sidebar {
	width: 320px;
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	gap: 16px;
	position: sticky;
	top: 32px;
}

.tc-side-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
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

.tc-side-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 12px;
	margin-bottom: 10px;
}

.tc-side-label {
	color: #64748b;
	font-weight: 500;
}

.tc-side-val {
	font-weight: 600;
	color: #1e293b;
}

/* Status & Channel Badges */
.tc-pill-channel {
	font-size: 11px;
	font-weight: 700;
	padding: 3px 8px;
	border-radius: 6px;
	text-transform: uppercase;
}

.tc-pill-web {
	background: #e0f2fe;
	color: #0369a1;
	border: 1px solid #bae6fd;
}

.tc-pill-whatsapp {
	background: #dcfce7;
	color: #15803d;
	border: 1px solid #bbf7d0;
}

.tc-pill-voice {
	background: #fef3c7;
	color: #b45309;
	border: 1px solid #fde68a;
}

.tc-pill-status {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	font-weight: 700;
	padding: 3px 9px;
	border-radius: 12px;
}

.tc-pill-active {
	background: #dcfce7;
	color: #15803d;
	border: 1px solid #86efac;
}

.tc-pill-handed_off {
	background: #fef9c3;
	color: #a16207;
	border: 1px solid #fde047;
}

.tc-pill-resolved {
	background: #e0e7ff;
	color: #3730a3;
	border: 1px solid #c7d2fe;
}

/* Quick Action Buttons */
.tc-action-btn-whatsapp {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	background: #25D366;
	color: #ffffff !important;
	font-size: 13px;
	font-weight: 700;
	padding: 10px;
	border-radius: 8px;
	text-decoration: none;
	transition: background 0.2s;
	margin-top: 6px;
}

.tc-action-btn-whatsapp:hover {
	background: #1ebc57;
}

.tc-telemetry-row {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 6px;
	text-align: center;
	margin-top: 10px;
}

.tc-tel-stat {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	padding: 8px 2px;
}

.tc-tel-stat strong {
	display: block;
	font-size: 15px;
	color: #0b132b;
}

.tc-tel-stat small {
	font-size: 10px;
	color: #64748b;
}
</style>

<div class="tc-wrap">
	<?php if ( ! empty( $notice_msg ) ) : ?>
		<div class="notice notice-success is-dismissible" style="border-left-color: #10b981; margin-bottom: 20px;">
			<p><strong><?php echo esc_html( $notice_msg ); ?></strong></p>
		</div>
	<?php endif; ?>

	<div class="tc-top-bar">
		<div class="tc-top-title">
			<h1>
				<span class="dashicons dashicons-format-chat" style="color: #f59e0b; font-size: 26px; width: 26px; height: 26px;"></span>
				Conversations & CRM Transcripts
			</h1>
			<p>Live omnichannel threads across Web Chatbot, WhatsApp, Jio 5G Telephony, and Master Console.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'export', 'csv' ), 'tc_export_convos' ) ); ?>" class="button button-secondary" style="font-weight: 600;">
				<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Export CSV
			</a>
		</div>
	</div>

	<?php if ( $view_convo_id > 0 && $current_convo ) : ?>
		<!-- Detail View for Selected Conversation: 2-Column Chat Layout -->
		<div class="tc-chat-layout">
			<!-- Left: Interactive Chat Stream -->
			<div class="tc-chat-canvas">
				<div class="tc-canvas-header">
					<div class="tc-canvas-title">
						<a href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>" class="button button-small" style="font-weight: 600;">&larr; Back to list</a>
						<span>Conversation #<?php echo esc_html( $view_convo_id ); ?></span>
						<div class="tc-chat-view-toggles" style="display:inline-flex; background:#e2e8f0; border-radius:8px; padding:2px; margin-left:10px;">
							<button type="button" class="tc-view-toggle-btn active" onclick="tcToggleChatView('all', this)" style="border:none; background:#fff; border-radius:6px; font-size:11px; font-weight:700; color:#0f172a; padding:3px 9px; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,0.06);">All Stream</button>
							<button type="button" class="tc-view-toggle-btn" onclick="tcToggleChatView('chat', this)" style="border:none; background:transparent; border-radius:6px; font-size:11px; font-weight:600; color:#64748b; padding:3px 9px; cursor:pointer;">💬 Traveler Chat Only</button>
						</div>
						<span class="tc-pill-channel tc-pill-<?php echo esc_attr( $current_convo['channel'] ); ?>">
							<?php echo esc_html( strtoupper( $current_convo['channel'] ) ); ?>
						</span>
						<span class="tc-pill-status tc-pill-<?php echo esc_attr( $current_convo['status'] ); ?>">
							● <?php echo esc_html( ucfirst( str_replace( '_', ' ', $current_convo['status'] ) ) ); ?>
						</span>
					</div>
					<div style="font-size: 12px; color: #64748b;">
						Started: <strong><?php echo esc_html( $current_convo['started_at'] ); ?></strong>
					</div>
				</div>

				<div class="tc-messages-container">
					<?php if ( empty( $view_messages ) ) : ?>
						<p style="text-align: center; color: #64748b; margin: 40px 0;">No messages recorded yet for this session.</p>
					<?php else : ?>
						<?php foreach ( $view_messages as $m ) : ?>
							<?php
							$role = strtolower( trim( $m['role'] ) );
							$is_user = ( 'user' === $role );
							$is_assistant = ( 'assistant' === $role );
							$is_tool = ( 'tool' === $role );
							?>

							<?php if ( $is_user ) : ?>
								<!-- User / Traveler Bubble (Aligned Right) -->
								<div class="tc-chat-row tc-row-user">
									<div class="tc-bubble-user">
										<div class="tc-bubble-header">
											<span>👤 Traveler</span>
											<span><?php echo esc_html( substr( $m['created_at'], 11, 8 ) ); ?></span>
										</div>
										<div class="tc-bubble-text">
											<?php echo tc_format_chat_markdown( $m['content'] ); ?>
										</div>
									</div>
								</div>

							<?php elseif ( $is_assistant ) : ?>
								<!-- AI Assistant Bubble (Aligned Left) -->
								<div class="tc-chat-row tc-row-assistant">
									<div class="tc-bubble-assistant">
										<div class="tc-bubble-header">
											<div class="tc-bot-identity">
												<span class="tc-bot-avatar">AI</span>
												<span class="tc-bot-name">TripCosmos AI Agent</span>
												<?php if ( ! empty( $m['provider_used'] ) ) : ?>
													<span class="tc-bot-gateway"><?php echo esc_html( $m['provider_used'] ); ?></span>
												<?php endif; ?>
											</div>
											<div class="tc-bot-meta">
												<?php if ( ! empty( $m['latency_ms'] ) ) : ?>
													<?php
													$lat = (int) $m['latency_ms'];
													$lat_class = $lat < 3000 ? 'tc-latency-good' : ( $lat < 6000 ? 'tc-latency-warn' : 'tc-latency-bad' );
													?>
													<span class="tc-latency-tag <?php echo esc_attr( $lat_class ); ?>">
														⏱️ <?php echo esc_html( number_format( $lat / 1000, 1 ) ); ?>s
													</span>
												<?php endif; ?>
												<span><?php echo esc_html( substr( $m['created_at'], 11, 8 ) ); ?></span>
											</div>
										</div>
										<div class="tc-bubble-text">
											<?php echo tc_format_chat_markdown( $m['content'] ); ?>
										</div>

										<?php if ( ! empty( $m['tool_calls'] ) ) : ?>
											<?php
											$tool_data = json_decode( $m['tool_calls'], true );
											$fn_name = $tool_data['function']['name'] ?? ( $tool_data['name'] ?? 'function_call' );
											$fn_args = $tool_data['function']['arguments'] ?? ( $tool_data['arguments'] ?? '' );
											if ( is_string( $fn_args ) ) {
												$decoded_args = json_decode( $fn_args, true );
												if ( is_array( $decoded_args ) ) {
													$fn_args = $decoded_args;
												}
											}
											?>
											<details class="tc-tool-execution-banner">
												<summary>⚡ Autonomous Action Executed: <strong><?php echo esc_html( $fn_name ); ?>()</strong></summary>
												<pre><?php echo esc_html( is_array( $fn_args ) ? wp_json_encode( $fn_args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $fn_args ); ?></pre>
											</details>
										<?php endif; ?>
									</div>
								</div>

							<?php elseif ( $is_tool ) : ?>
								<!-- Tool Results: Structured Executive Cards with Collapsible Diagnostics -->
								<?php
								$tool_payload = json_decode( $m['content'], true );
								$has_trips    = ( is_array( $tool_payload ) && ! empty( $tool_payload['trips'] ) && is_array( $tool_payload['trips'] ) );
								$has_cab_fare = ( is_array( $tool_payload ) && ( ! empty( $tool_payload['estimated_fare'] ) || ! empty( $tool_payload['total_estimate'] ) || ! empty( $tool_payload['distance_km'] ) || ! empty( $tool_payload['all_vehicle_options'] ) ) );
								?>
								<div class="tc-chat-row tc-row-tool">
									<div class="tc-tool-response-card" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
										<div class="tc-tool-response-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
											<span class="tc-tool-title-tag" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; padding:3px 10px; border-radius:6px; font-weight:700; font-size:11px;">
												<?php 
												if ( $has_cab_fare ) {
													echo '🚗 VEHICLE & ROUTE FARE ESTIMATE';
												} elseif ( $has_trips ) {
													echo '📍 ' . count( $tool_payload['trips'] ) . ' TOURS FOUND';
												} else {
													echo '⚡ AUTONOMOUS ACTION COMPLETED';
												}
												?>
											</span>
											<span style="font-size:11px; color:#94a3b8;"><?php echo esc_html( substr( $m['created_at'], 11, 8 ) ); ?></span>
										</div>

										<?php if ( $has_cab_fare ) : 
											$route_title = $tool_payload['route'] ?? ( trim( ($tool_payload['origin'] ?? '') . ' → ' . ($tool_payload['destination'] ?? '') ) ?: 'Custom Itinerary Transfer' );
											$dist_km     = $tool_payload['distance_km'] ?? $tool_payload['total_billable_km'] ?? '—';
											$fare_val    = $tool_payload['estimated_fare'] ?? ( '₹' . number_format_i18n( (float) ($tool_payload['total_estimate'] ?? 0) ) );
											$veh_name    = $tool_payload['vehicle'] ?? $tool_payload['vehicle_name'] ?? 'Dedicated AC Vehicle';
										?>
											<!-- Rich Transport Quote Card -->
											<div class="tc-cab-quote-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:12px;">
												<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
													<div>
														<h4 style="margin:0; font-size:15px; color:#0f172a; font-weight:700;">🛣️ <?php echo esc_html( $route_title ); ?></h4>
														<div style="font-size:12px; color:#64748b; margin-top:2px;">
															<span>📏 <?php echo esc_html( $dist_km ); ?> km</span> · 
															<span><?php echo ! empty( $tool_payload['is_round_trip'] ) ? '🔄 Round Trip' : '➡️ One Way'; ?></span> · 
															<span>⏱️ <?php echo (int) ($tool_payload['days'] ?? 1); ?> Day</span>
														</div>
													</div>
													<div style="text-align:right;">
														<div style="font-size:20px; font-weight:800; color:#059669;"><?php echo esc_html( $fare_val ); ?></div>
														<div style="font-size:11px; color:#64748b;"><?php echo esc_html( $veh_name ); ?></div>
													</div>
												</div>

												<?php if ( ! empty( $tool_payload['all_vehicle_options'] ) && is_array( $tool_payload['all_vehicle_options'] ) ) : ?>
													<div style="margin-top:10px; border-top:1px solid #e2e8f0; padding-top:10px;">
														<div style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">Available Vehicle Fleet Tiers:</div>
														<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:8px;">
															<?php foreach ( $tool_payload['all_vehicle_options'] as $v_opt ) : 
																$v_title = $v_opt['vehicle'] ?? ucfirst( $v_opt['vehicle_type'] ?? 'Cab' );
																$v_cap   = $v_opt['capacity'] ?? '';
																$v_est   = isset( $v_opt['total_estimate'] ) ? '₹' . number_format_i18n( (float) $v_opt['total_estimate'] ) : '';
															?>
																<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px; display:flex; justify-content:space-between; align-items:center;">
																	<div>
																		<strong style="font-size:12px; color:#1e293b; display:block;"><?php echo esc_html( $v_title ); ?></strong>
																		<?php if ( $v_cap ) : ?><span style="font-size:10px; color:#64748b;"><?php echo esc_html( $v_cap ); ?></span><?php endif; ?>
																	</div>
																	<span style="font-size:13px; font-weight:700; color:#0f172a;"><?php echo esc_html( $v_est ); ?></span>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												<?php endif; ?>

												<?php if ( ! empty( $tool_payload['inclusions'] ) ) : ?>
													<div style="margin-top:8px; font-size:11px; color:#059669; line-height:1.4;">
														<strong>Inclusions:</strong> <?php echo esc_html( $tool_payload['inclusions'] ); ?>
													</div>
												<?php endif; ?>
											</div>

											<details style="margin-top:8px; font-size:11px; color:#64748b;">
												<summary style="cursor:pointer; font-weight:600; padding:2px 0;">🔍 Inspect Raw Technical Payload</summary>
												<pre style="background:#0b132b; color:#38bdf8; padding:10px; border-radius:6px; overflow-x:auto; margin-top:4px; font-size:11px;"><?php echo esc_html( wp_json_encode( $tool_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
											</details>

										<?php elseif ( $has_trips ) : ?>
											<!-- Trips Grid -->
											<div class="tc-tours-grid">
												<?php foreach ( $tool_payload['trips'] as $trip ) : ?>
													<div class="tc-tour-card">
														<?php if ( ! empty( $trip['thumbnail'] ) ) : ?>
															<div class="tc-tour-thumb" style="background-image: url('<?php echo esc_url( $trip['thumbnail'] ); ?>');">
																<span class="tc-tour-duration-badge"><?php echo esc_html( $trip['duration'] ?? 'Flexible' ); ?></span>
															</div>
														<?php endif; ?>
														<div class="tc-tour-body">
															<span class="tc-tour-dest"><?php echo esc_html( $trip['destination'] ?? 'Pilgrimage Circuit' ); ?></span>
															<h4 class="tc-tour-title">
																<?php if ( ! empty( $trip['url'] ) ) : ?>
																	<a href="<?php echo esc_url( $trip['url'] ); ?>" target="_blank" rel="noopener">
																		<?php echo esc_html( $trip['title'] ); ?> &nearr;
																	</a>
																<?php else : ?>
																	<?php echo esc_html( $trip['title'] ); ?>
																<?php endif; ?>
															</h4>
															<div class="tc-tour-footer">
																<span class="tc-tour-price"><?php echo esc_html( $trip['price'] ?? 'On Request' ); ?></span>
																<?php if ( ! empty( $trip['url'] ) ) : ?>
																	<a href="<?php echo esc_url( $trip['url'] ); ?>" target="_blank" rel="noopener" class="tc-tour-cta">View Tour</a>
																<?php endif; ?>
															</div>
														</div>
													</div>
												<?php endforeach; ?>
											</div>
											<details style="margin-top: 10px; font-size: 11px; color: #64748b;">
												<summary style="cursor: pointer; font-weight:600;">🔍 Inspect Raw Technical Payload</summary>
												<pre style="background: #0b132b; color: #38bdf8; padding: 10px; border-radius: 6px; overflow-x: auto; margin-top: 4px; font-size: 11px;"><?php echo esc_html( wp_json_encode( $tool_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
											</details>
										<?php else : ?>
											<!-- General Autonomous Tool Execution Result -->
											<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:8px;">
												<div style="display:flex; align-items:center; gap:8px; font-size:13px; color:#334155; font-weight:600;">
													<span>⚡ Execution Status:</span>
													<span style="color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700;">Success / Ready</span>
												</div>
											</div>
											<details open style="margin-top: 6px; font-size: 11px; color: #64748b;">
												<summary style="cursor: pointer; font-weight:600;">🔍 Inspect Technical Data Payload</summary>
												<pre style="background: #0b132b; color: #38bdf8; padding: 10px; border-radius: 6px; font-size: 11px; overflow-x: auto; margin-top: 4px;"><?php echo esc_html( is_array( $tool_payload ) ? wp_json_encode( $tool_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : $m['content'] ); ?></pre>
											</details>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Right: Customer Dossier & CRM Sidebar -->
			<div class="tc-chat-sidebar">
				<div class="tc-side-box">
					<h3><span class="dashicons dashicons-id-alt" style="color: #f59e0b;"></span> Traveler Dossier</h3>
					<div class="tc-side-item">
						<span class="tc-side-label">Session ID:</span>
						<code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
							<?php echo esc_html( substr( $current_convo['session_id'], 0, 16 ) ); ?>...
						</code>
					</div>
					<div class="tc-side-item">
						<span class="tc-side-label">Inbound Source:</span>
						<span class="tc-pill-channel tc-pill-<?php echo esc_attr( $current_convo['channel'] ); ?>">
							<?php echo esc_html( ucfirst( $current_convo['channel'] ) ); ?>
						</span>
					</div>
					<div class="tc-side-item">
						<span class="tc-side-label">Assigned Staff:</span>
						<strong style="color: #0b132b;"><?php echo esc_html( $current_convo['agent_id'] ); ?></strong>
					</div>
					<div class="tc-side-item">
						<span class="tc-side-label">Last Message:</span>
						<span style="font-size: 11px; color: #64748b;"><?php echo esc_html( $current_convo['last_message_at'] ); ?></span>
					</div>

					<form method="post" style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
						<?php wp_nonce_field( 'tc_update_convo_action', 'tc_update_convo_nonce' ); ?>
						<input type="hidden" name="tc_target_convo_id" value="<?php echo esc_attr( $current_convo['id'] ); ?>" />
						<label for="tc_convo_status" style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 6px;">
							Lead Stage / Status:
						</label>
						<select name="tc_convo_status" id="tc_convo_status" style="width: 100%; border-radius: 6px; font-size: 12px; padding: 4px 8px;">
							<option value="active" <?php selected( $current_convo['status'], 'active' ); ?>>🟢 Active AI Handling</option>
							<option value="handed_off" <?php selected( $current_convo['status'], 'handed_off' ); ?>>🟡 Handed Off to Staff</option>
							<option value="resolved" <?php selected( $current_convo['status'], 'resolved' ); ?>>🔵 Resolved / Booked</option>
						</select>
						<button type="submit" class="button button-primary" style="width: 100%; margin-top: 8px; font-weight: 600; background: #0b132b; border-color: #0b132b;">
							Update Stage
						</button>
					</form>
				</div>

				<div class="tc-side-box">
					<h3><span class="dashicons dashicons-share-alt" style="color: #10b981;"></span> 1-Click WhatsApp</h3>
					<?php
					$convo_dest = '';
					$wa_phone   = '';
					if ( ! empty( $current_convo['contact_id'] ) ) {
						$c_row = TC_Contact_Service::get( $current_convo['contact_id'] );
						if ( $c_row ) {
							$convo_dest = $c_row['destination_interest'] ?? '';
							$wa_phone   = preg_replace( '/\D/', '', (string) ($c_row['phone_e164'] ?: $c_row['phone']) );
						}
					}
					$wa_subj = $convo_dest ? sprintf( __( 'your %s pilgrimage travel inquiry', 'tripcosmos-agents' ), $convo_dest ) : __( 'your recent travel inquiry', 'tripcosmos-agents' );
					$wa_text = rawurlencode( sprintf( "Namaste! Regarding %s with TripCosmos (Ref #%d), our dedicated coordinator is ready to assist you.", $wa_subj, $current_convo['id'] ) );
					$wa_url  = $wa_phone ? 'https://wa.me/' . $wa_phone . '?text=' . $wa_text : 'https://web.whatsapp.com/send?text=' . $wa_text;
					?>
					<p style="font-size: 12px; color: #64748b; margin: 0 0 10px 0;">
						<?php echo esc_html( sprintf( __( 'Send instant WhatsApp follow-up regarding Ref #%d.', 'tripcosmos-agents' ), $current_convo['id'] ) ); ?>
					</p>
					<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tc-action-btn-whatsapp">
						<span class="dashicons dashicons-whatsapp"></span>
						<?php esc_html_e( 'Open in WhatsApp Web', 'tripcosmos-agents' ); ?>
					</a>
				</div>

				<div class="tc-side-box">
					<h3><span class="dashicons dashicons-chart-area" style="color: #6366f1;"></span> Conversation Telemetry</h3>
					<div class="tc-telemetry-row">
						<div class="tc-tel-stat">
							<strong><?php echo esc_html( count( $view_messages ) ); ?></strong>
							<small>Msgs</small>
						</div>
						<div class="tc-tel-stat">
							<strong><?php echo esc_html( $avg_latency > 0 ? number_format( $avg_latency / 1000, 1 ) . 's' : 'N/A' ); ?></strong>
							<small>Avg Speed</small>
						</div>
						<div class="tc-tel-stat">
							<strong><?php echo esc_html( number_format( $total_tokens ) ); ?></strong>
							<small>Tokens</small>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Filter Bar -->
	<div class="tablenav top" style="margin-top: 24px;">
		<form method="get">
			<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
			<input type="hidden" name="page" value="tc-agents-conversations" />
			<div class="alignleft actions">
				<select name="channel">
					<option value="">All Inbound Channels</option>
					<option value="web" <?php selected( $filter_channel, 'web' ); ?>>🌐 Web Chat Widget</option>
					<option value="whatsapp" <?php selected( $filter_channel, 'whatsapp' ); ?>>💬 WhatsApp</option>
					<option value="voice" <?php selected( $filter_channel, 'voice' ); ?>>📞 Jio 5G Telephony</option>
					<option value="admin" <?php selected( $filter_channel, 'admin' ); ?>>💻 Master Console</option>
				</select>
				<select name="status">
					<option value="">All Statuses</option>
					<option value="active" <?php selected( $filter_status, 'active' ); ?>>🟢 Active AI</option>
					<option value="handed_off" <?php selected( $filter_status, 'handed_off' ); ?>>🟡 Handed Off</option>
					<option value="resolved" <?php selected( $filter_status, 'resolved' ); ?>>🔵 Resolved</option>
				</select>
				<input type="submit" class="button button-secondary" value="Filter" />
			</div>
			<div class="alignright actions">
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search session ID or guest..." />
				<input type="submit" class="button" value="Search" />
			</div>
		</form>
	</div>

	<!-- Conversations Table -->
	<table class="wp-list-table widefat fixed striped" style="border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
		<thead>
			<tr>
				<th style="width: 70px;">ID</th>
				<th>Session / Traveler ID</th>
				<th style="width: 130px;">Channel</th>
				<th style="width: 140px;">Specialist</th>
				<th style="width: 140px;">Status</th>
				<th style="width: 170px;">Started At</th>
				<th style="width: 170px;">Last Activity</th>
				<th style="width: 120px; text-align: center;">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $convos ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center; padding: 36px; color: #64748b;">
						No conversations recorded yet.
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $convos as $c ) : ?>
					<tr>
						<td><strong style="color: #0b132b;">#<?php echo esc_html( $c['id'] ); ?></strong></td>
						<td>
							<code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #334155; font-size: 12px;">
								<?php echo esc_html( $c['session_id'] ); ?>
							</code>
						</td>
						<td>
							<span class="tc-pill-channel tc-pill-<?php echo esc_attr( $c['channel'] ); ?>">
								<?php echo esc_html( ucfirst( $c['channel'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $c['agent_id'] ); ?></td>
						<td>
							<span class="tc-pill-status tc-pill-<?php echo esc_attr( $c['status'] ); ?>">
								● <?php echo esc_html( ucfirst( str_replace( '_', ' ', $c['status'] ) ) ); ?>
							</span>
						</td>
						<td style="color: #64748b; font-size: 13px;"><?php echo esc_html( $c['started_at'] ); ?></td>
						<td style="color: #64748b; font-size: 13px;"><?php echo esc_html( $c['last_message_at'] ); ?></td>
						<td style="text-align: center;">
							<a href="<?php echo esc_url( add_query_arg( 'view', $c['id'] ) ); ?>" class="button button-small" style="font-weight: 600; background: #0b132b; color: #ffffff; border-color: #0b132b;">
								View Thread &rarr;
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>


<script>
function tcToggleChatView(mode, btn) {
	var rows = document.querySelectorAll('.tc-row-tool');
	var toolBanners = document.querySelectorAll('.tc-tool-execution-banner');
	var btns = document.querySelectorAll('.tc-view-toggle-btn');
	btns.forEach(function(b) {
		b.style.background = 'transparent';
		b.style.color = '#64748b';
		b.classList.remove('active');
	});
	btn.style.background = '#ffffff';
	btn.style.color = '#0f172a';
	btn.classList.add('active');

	rows.forEach(function(r) {
		r.style.display = (mode === 'chat') ? 'none' : 'flex';
	});
	toolBanners.forEach(function(tb) {
		tb.style.display = (mode === 'chat') ? 'none' : 'block';
	});
}
</script>
