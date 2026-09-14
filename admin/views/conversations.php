<?php
/**
 * Admin Conversations & Transcripts View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_convo = $wpdb->prefix . 'tc_agent_conversations';
$table_msg   = $wpdb->prefix . 'tc_agent_messages';

$filter_channel = sanitize_text_field( $_GET['channel'] ?? '' );
$filter_status  = sanitize_text_field( $_GET['status'] ?? '' );
$search_query   = sanitize_text_field( $_GET['s'] ?? '' );
$view_convo_id  = absint( $_GET['view'] ?? 0 );


// Build query
$where = array( '1=1' );
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
$view_messages = array();
if ( $view_convo_id > 0 ) {
	$view_messages = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $table_msg WHERE conversation_id = %d ORDER BY id ASC", $view_convo_id ),
		ARRAY_A
	) ?: array();
}
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-format-chat"></span> Conversations & Transcripts</h1>
			<p class="description">Unified multi-channel thread logs across Web Chat Widget, WhatsApp, Voice Calls, and Staff Console.</p>
		</div>
		<div class="tc-header-actions">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'export', 'csv' ), 'tc_export_convos' ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download"></span> Export CSV
			</a>
		</div>
	</div>

	<?php if ( $view_convo_id > 0 ) : ?>
		<!-- Detail View for Selected Conversation -->
		<div class="tc-card">
			<div class="tc-card-header">
				<h3>Conversation Transcript #<?php echo esc_html( $view_convo_id ); ?></h3>
				<a href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>" class="button button-small">&larr; Back to list</a>
			</div>
			<div class="tc-transcript-flow">
				<?php if ( empty( $view_messages ) ) : ?>
					<p>No messages found for this conversation.</p>
				<?php else : ?>
					<?php foreach ( $view_messages as $m ) : ?>
						<div class="tc-transcript-row tc-role-<?php echo esc_attr( $m['role'] ); ?>">
							<div class="tc-meta">
								<strong><?php echo esc_html( strtoupper( $m['role'] ) ); ?></strong>
								<span><?php echo esc_html( $m['created_at'] ); ?></span>
								<?php if ( ! empty( $m['provider_used'] ) ) : ?>
									<span class="tc-tag"><?php echo esc_html( $m['provider_used'] ); ?> (<?php echo esc_html( $m['latency_ms'] ); ?>ms)</span>
								<?php endif; ?>
							</div>
							<div class="tc-content">
								<?php echo nl2br( esc_html( $m['content'] ) ); ?>
							</div>
							<?php if ( ! empty( $m['tool_calls'] ) ) : ?>
								<div class="tc-tool-box">
									<small>🔧 <strong>Tool Action:</strong> <code><?php echo esc_html( $m['tool_calls'] ); ?></code></small>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Filter Bar -->
	<div class="tablenav top">
		<form method="get">
			<input type="hidden" name="page" value="tc-agents-conversations" />
			<div class="alignleft actions">
				<select name="channel">
					<option value="">All Channels</option>
					<option value="web" <?php selected( $filter_channel, 'web' ); ?>>Web Widget</option>
					<option value="whatsapp" <?php selected( $filter_channel, 'whatsapp' ); ?>>WhatsApp</option>
					<option value="voice" <?php selected( $filter_channel, 'voice' ); ?>>Voice</option>
					<option value="admin" <?php selected( $filter_channel, 'admin' ); ?>>Master Console</option>
				</select>
				<select name="status">
					<option value="">All Statuses</option>
					<option value="active" <?php selected( $filter_status, 'active' ); ?>>Active</option>
					<option value="handed_off" <?php selected( $filter_status, 'handed_off' ); ?>>Handed Off</option>
					<option value="resolved" <?php selected( $filter_status, 'resolved' ); ?>>Resolved</option>
				</select>
				<input type="submit" class="button" value="Filter" />
			</div>
			<div class="alignright actions">
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search session ID..." />
				<input type="submit" class="button" value="Search" />
			</div>
		</form>
	</div>

	<!-- Conversations Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 70px;">ID</th>
				<th>Session / Contact</th>
				<th style="width: 120px;">Channel</th>
				<th style="width: 140px;">Agent</th>
				<th style="width: 120px;">Status</th>
				<th style="width: 180px;">Started At</th>
				<th style="width: 180px;">Last Activity</th>
				<th style="width: 100px;">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $convos ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center; padding: 24px;">No conversations recorded yet.</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $convos as $c ) : ?>
					<tr>
						<td><strong>#<?php echo esc_html( $c['id'] ); ?></strong></td>
						<td>
							<code><?php echo esc_html( $c['session_id'] ); ?></code>
						</td>
						<td>
							<span class="tc-channel-badge tc-channel-<?php echo esc_attr( $c['channel'] ); ?>">
								<?php echo esc_html( ucfirst( $c['channel'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $c['agent_id'] ); ?></td>
						<td>
							<span class="tc-status-pill tc-status-<?php echo esc_attr( $c['status'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $c['status'] ) ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $c['started_at'] ); ?></td>
						<td><?php echo esc_html( $c['last_message_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'view', $c['id'] ) ); ?>" class="button button-small">View</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
