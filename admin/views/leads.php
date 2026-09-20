<?php
/**
 * Admin Leads & Deals Pipeline View (Kanban Board + CRM Integration).
 * Inspired by VM Sales OS Deals Pipeline.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_contacts = $wpdb->prefix . 'tc_agent_contacts';
$table_memory   = $wpdb->prefix . 'tc_agent_lead_memory';

// Filter parameters
$selected_channel = sanitize_text_field( $_GET['channel'] ?? '' );
$search_query     = sanitize_text_field( $_GET['s'] ?? '' );
$view_mode        = sanitize_text_field( $_GET['view'] ?? 'kanban' );

// Build query
$where_clauses = array( '1=1' );
if ( ! empty( $selected_channel ) ) {
	$where_clauses[] = $wpdb->prepare( 'source_channel = %s', $selected_channel );
}
if ( ! empty( $search_query ) ) {
	$like = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where_clauses[] = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR phone LIKE %s)', $like, $like, $like );
}

$where_sql = implode( ' AND ', $where_clauses );
$leads = $wpdb->get_results( "SELECT * FROM $table_contacts WHERE $where_sql ORDER BY updated_at DESC", ARRAY_A ) ?: array();

// Summary metrics
$total_leads  = count( $leads );
$total_pipe   = 0.0;
$won_pipe     = 0.0;
$won_count    = 0;
$stages = array(
	'inquiry'     => array( 'label' => __( 'Inquiry / Exploration', 'tripcosmos-agents' ), 'color' => '#3b82f6', 'items' => array(), 'total' => 0.0 ),
	'qualified'   => array( 'label' => __( 'Qualified Interest', 'tripcosmos-agents' ), 'color' => '#8b5cf6', 'items' => array(), 'total' => 0.0 ),
	'proposal'    => array( 'label' => __( 'Custom Itinerary / Proposal', 'tripcosmos-agents' ), 'color' => '#f59e0b', 'items' => array(), 'total' => 0.0 ),
	'negotiation' => array( 'label' => __( 'Group Pricing & Discount', 'tripcosmos-agents' ), 'color' => '#ec4899', 'items' => array(), 'total' => 0.0 ),
	'won'         => array( 'label' => __( 'Booking Won / Deposit Paid', 'tripcosmos-agents' ), 'color' => '#10b981', 'items' => array(), 'total' => 0.0 ),
	'lost'        => array( 'label' => __( 'Lost / Inactive', 'tripcosmos-agents' ), 'color' => '#64748b', 'items' => array(), 'total' => 0.0 ),
);

foreach ( $leads as $lead ) {
	$st = $lead['stage'] ?? 'inquiry';
	if ( ! isset( $stages[ $st ] ) ) {
		$st = 'inquiry';
	}
	$stages[ $st ]['items'][] = $lead;
	$val = (float) ( $lead['deal_value'] ?? 0.0 );
	$stages[ $st ]['total'] += $val;

	if ( 'won' === $st ) {
		$won_pipe += $val;
		$won_count++;
	} else {
		$total_pipe += $val;
	}
}

$conversion_rate = $total_leads > 0 ? round( ( $won_count / $total_leads ) * 100, 1 ) : 0;
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Leads & Deals Pipeline', 'tripcosmos-agents' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage spiritual tours, outstation cab quotes, hotel bookings, automated scores, and traveler memory across web, WhatsApp, and voice.', 'tripcosmos-agents' ); ?></p>
		</div>
		<div class="tc-header-actions">
			<a href="#new-lead-modal" class="button button-primary" onclick="document.getElementById('tc-lead-modal').style.display='flex';">
				<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'Add New Lead', 'tripcosmos-agents' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Lead record updated successfully.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>

	<!-- Metrics Overview -->
	<div class="tc-kpi-grid">
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Open Pipeline Value', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #0ea5e9;">₹<?php echo esc_html( number_format( $total_pipe, 2 ) ); ?></span>
			<span class="tc-kpi-sub"><?php esc_html_e( 'Active non-won inquiries', 'tripcosmos-agents' ); ?></span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Closed Bookings Won', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #10b981;">₹<?php echo esc_html( number_format( $won_pipe, 2 ) ); ?></span>
			<span class="tc-kpi-sub"><?php echo esc_html( $won_count ); ?> <?php esc_html_e( 'confirmed travelers', 'tripcosmos-agents' ); ?></span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Total Leads Tracked', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value"><?php echo esc_html( number_format( $total_leads ) ); ?></span>
			<span class="tc-kpi-sub"><?php esc_html_e( 'Across all channels', 'tripcosmos-agents' ); ?></span>
		</div>
		<div class="tc-kpi-card">
			<span class="tc-kpi-title"><?php esc_html_e( 'Pipeline Conversion Rate', 'tripcosmos-agents' ); ?></span>
			<span class="tc-kpi-value" style="color: #8b5cf6;"><?php echo esc_html( $conversion_rate ); ?>%</span>
			<span class="tc-kpi-sub"><?php esc_html_e( 'Inquiry-to-booking ratio', 'tripcosmos-agents' ); ?></span>
		</div>
	</div>

	<!-- Filter and View Controls -->
	<div class="tc-card" style="padding: 14px 20px; margin-bottom: 20px;">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
			<input type="hidden" name="page" value="tc-agents-leads" />
			<div style="flex: 1; min-width: 200px;">
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search traveler name, email, or phone...', 'tripcosmos-agents' ); ?>" style="width: 100%;" />
			</div>
			<div>
				<select name="channel" onchange="this.form.submit();">
					<option value=""><?php esc_html_e( 'All Channels', 'tripcosmos-agents' ); ?></option>
					<option value="web" <?php selected( $selected_channel, 'web' ); ?>><?php esc_html_e( 'Web Widget', 'tripcosmos-agents' ); ?></option>
					<option value="whatsapp" <?php selected( $selected_channel, 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'tripcosmos-agents' ); ?></option>
					<option value="voice" <?php selected( $selected_channel, 'voice' ); ?>><?php esc_html_e( 'Voice Call', 'tripcosmos-agents' ); ?></option>
				</select>
			</div>
			<div>
				<select name="view" onchange="this.form.submit();">
					<option value="kanban" <?php selected( $view_mode, 'kanban' ); ?>><?php esc_html_e( 'Kanban Board', 'tripcosmos-agents' ); ?></option>
					<option value="table" <?php selected( $view_mode, 'table' ); ?>><?php esc_html_e( 'Table List', 'tripcosmos-agents' ); ?></option>
				</select>
			</div>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'tripcosmos-agents' ); ?></button>
			<?php if ( ! empty( $selected_channel ) || ! empty( $search_query ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-leads' ) ); ?>" class="button-link"><?php esc_html_e( 'Reset Filters', 'tripcosmos-agents' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( 'table' === $view_mode ) : ?>
		<!-- Tabular View -->
		<div class="tc-card">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Traveler / Contact', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'Stage', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'Deal Value', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'AI Score', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'CRMs', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'tripcosmos-agents' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'tripcosmos-agents' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $leads ) ) : ?>
						<tr><td colspan="8" style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No leads found matching criteria.', 'tripcosmos-agents' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $leads as $l ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $l['name'] ?: __( 'Anonymous Traveler', 'tripcosmos-agents' ) ); ?></strong><br>
									<small style="color: #64748b;"><?php echo esc_html( $l['email'] ?: '' ); ?> <?php echo esc_html( $l['phone'] ? '• ' . $l['phone'] : '' ); ?></small>
								</td>
								<td><span class="tc-channel-badge tc-channel-<?php echo esc_attr( $l['source_channel'] ); ?>"><?php echo esc_html( ucfirst( $l['source_channel'] ) ); ?></span></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
										<input type="hidden" name="tc_agents_action" value="update_lead_stage" />
										<input type="hidden" name="lead_id" value="<?php echo esc_attr( $l['id'] ); ?>" />
										<select name="stage" onchange="this.form.submit();" style="font-size: 12px; padding: 2px 6px;">
											<?php foreach ( $stages as $st_key => $st_data ) : ?>
												<option value="<?php echo esc_attr( $st_key ); ?>" <?php selected( $l['stage'], $st_key ); ?>><?php echo esc_html( $st_data['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</form>
								</td>
								<td><strong>₹<?php echo esc_html( number_format( (float) $l['deal_value'], 2 ) ); ?></strong></td>
								<td>
									<span class="tc-score-badge" style="background: <?php echo $l['score'] >= 70 ? '#dcfce7' : ( $l['score'] >= 40 ? '#fef3c7' : '#fee2e2' ); ?>; color: <?php echo $l['score'] >= 70 ? '#15803d' : ( $l['score'] >= 40 ? '#b45309' : '#991b1b' ); ?>; font-weight:700; padding: 3px 8px; border-radius: 12px; font-size: 11px;">
										<?php echo esc_html( $l['score'] ); ?> / 100
									</span>
								</td>
								<td>
									<?php if ( ! empty( $l['fluentcrm_id'] ) ) : ?>
										<span class="tc-tag" title="FluentCRM ID">CRM #<?php echo esc_html( $l['fluentcrm_id'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $l['twentycrm_id'] ) ) : ?>
										<span class="tc-tag" title="Twenty CRM">Twenty</span>
									<?php endif; ?>
								</td>
								<td><small style="color: #64748b;"><?php echo esc_html( human_time_diff( strtotime( $l['updated_at'] ), current_time( 'timestamp' ) ) . ' ago' ); ?></small></td>
								<td>
									<button type="button" class="button button-small" onclick="viewMemory(<?php echo esc_attr( $l['id'] ); ?>, '<?php echo esc_js( $l['name'] ?: 'Traveler' ); ?>')"><?php esc_html_e( 'Memory', 'tripcosmos-agents' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<!-- Kanban Board View -->
		<div class="tc-kanban-board">
			<?php foreach ( $stages as $st_key => $st_data ) : ?>
				<div class="tc-kanban-col" data-stage="<?php echo esc_attr( $st_key ); ?>">
					<div class="tc-kanban-col-header" style="border-top-color: <?php echo esc_attr( $st_data['color'] ); ?>;">
						<div class="tc-kanban-col-title">
							<span><?php echo esc_html( $st_data['label'] ); ?></span>
							<span class="tc-kanban-count"><?php echo count( $st_data['items'] ); ?></span>
						</div>
						<div class="tc-kanban-col-val">
							₹<?php echo esc_html( number_format( $st_data['total'], 0 ) ); ?>
						</div>
					</div>

					<div class="tc-kanban-cards" data-stage="<?php echo esc_attr( $st_key ); ?>">
						<?php if ( empty( $st_data['items'] ) ) : ?>
							<div class="tc-kanban-empty"><?php esc_html_e( 'No leads in this stage (drag cards here)', 'tripcosmos-agents' ); ?></div>
						<?php else : ?>
							<?php foreach ( $st_data['items'] as $card ) : ?>
								<div class="tc-kanban-card" draggable="true" data-lead-id="<?php echo esc_attr( $card['id'] ); ?>" data-stage="<?php echo esc_attr( $st_key ); ?>" data-val="<?php echo esc_attr( $card['deal_value'] ); ?>">
									<div class="tc-card-top">
										<span class="tc-channel-badge tc-channel-<?php echo esc_attr( $card['source_channel'] ); ?>"><?php echo esc_html( ucfirst( $card['source_channel'] ) ); ?></span>
										<span class="tc-score-badge" style="background: <?php echo $card['score'] >= 70 ? '#dcfce7' : ( $card['score'] >= 40 ? '#fef3c7' : '#fee2e2' ); ?>; color: <?php echo $card['score'] >= 70 ? '#15803d' : ( $card['score'] >= 40 ? '#b45309' : '#991b1b' ); ?>; font-weight:700; padding: 2px 6px; border-radius: 8px; font-size: 10px;">
											⚡ <?php echo esc_html( $card['score'] ); ?>
										</span>
									</div>
									<div class="tc-card-name">
										<strong><?php echo esc_html( $card['name'] ?: __( 'Anonymous Explorer', 'tripcosmos-agents' ) ); ?></strong>
									</div>
									<?php if ( ! empty( $card['phone'] ) || ! empty( $card['email'] ) ) : ?>
										<div class="tc-card-contact">
											<?php if ( ! empty( $card['phone'] ) ) : ?>
												<div>📱 <?php echo esc_html( $card['phone'] ); ?></div>
											<?php endif; ?>
											<?php if ( ! empty( $card['email'] ) ) : ?>
												<div>✉️ <?php echo esc_html( $card['email'] ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<div class="tc-card-price">
										₹<?php echo esc_html( number_format( (float) $card['deal_value'], 2 ) ); ?>
									</div>

									<div class="tc-card-footer" style="display: flex; gap: 6px; align-items: center;">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 0; flex: 1;">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="update_lead_stage" />
											<input type="hidden" name="lead_id" value="<?php echo esc_attr( $card['id'] ); ?>" />
											<select name="stage" onchange="this.form.submit();" class="tc-stage-quick-select" style="width: 100%; font-size: 11px;">
												<?php foreach ( $stages as $k => $d ) : ?>
													<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st_key, $k ); ?>><?php echo esc_html( $d['label'] ); ?></option>
												<?php endforeach; ?>
											</select>
										</form>

										<?php if ( ! empty( $card['phone'] ) ) : ?>
											<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $card['phone'] ) ); ?>?text=<?php echo rawurlencode( 'Namaste ' . ( $card['name'] ?: 'Traveler' ) . ', this is TripCosmos (Varanasi) regarding your tour & cab inquiry!' ); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e( 'Chat on WhatsApp', 'tripcosmos-agents' ); ?>" style="background: #22c55e; color: #fff; border-color: #16a34a; padding: 0 6px; font-weight: bold; line-height: 24px; height: 26px;">
												WA
											</a>
										<?php endif; ?>

										<button type="button" class="button button-small" onclick="viewMemory(<?php echo esc_attr( $card['id'] ); ?>, '<?php echo esc_js( $card['name'] ?: 'Traveler' ); ?>')" title="<?php esc_attr_e( 'View Traveler AI Memory Engine', 'tripcosmos-agents' ); ?>">
											🧠
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<!-- Modal: Traveler Memory Engine Viewer -->
<div id="tc-memory-modal" class="tc-modal-overlay" style="display: none;">
	<div class="tc-modal-dialog">
		<div class="tc-modal-header">
			<h3 id="tc-modal-title"><?php esc_html_e( 'Traveler AI Memory & Context', 'tripcosmos-agents' ); ?></h3>
			<button type="button" class="tc-modal-close" onclick="document.getElementById('tc-memory-modal').style.display='none';">&times;</button>
		</div>
		<div class="tc-modal-body" id="tc-modal-content">
			<p style="text-align: center; color: #64748b;"><?php esc_html_e( 'Loading traveler memory...', 'tripcosmos-agents' ); ?></p>
		</div>
	</div>
</div>

<!-- Modal: Add New Lead -->
<div id="tc-lead-modal" class="tc-modal-overlay" style="display: none;">
	<div class="tc-modal-dialog">
		<div class="tc-modal-header">
			<h3><?php esc_html_e( 'Add New Lead / Booking Inquiry', 'tripcosmos-agents' ); ?></h3>
			<button type="button" class="tc-modal-close" onclick="document.getElementById('tc-lead-modal').style.display='none';">&times;</button>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
			<input type="hidden" name="tc_agents_action" value="save_lead" />
			<div class="tc-modal-body">
				<p>
					<label><strong><?php esc_html_e( 'Full Name', 'tripcosmos-agents' ); ?></strong></label><br>
					<input type="text" name="name" class="regular-text" required style="width: 100%;" />
				</p>
				<p>
					<label><strong><?php esc_html_e( 'WhatsApp / Phone Number', 'tripcosmos-agents' ); ?></strong></label><br>
					<input type="text" name="phone" placeholder="+91 98765 43210" class="regular-text" style="width: 100%;" />
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Email Address', 'tripcosmos-agents' ); ?></strong></label><br>
					<input type="email" name="email" class="regular-text" style="width: 100%;" />
				</p>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
					<p>
						<label><strong><?php esc_html_e( 'Initial Stage', 'tripcosmos-agents' ); ?></strong></label><br>
						<select name="stage" style="width: 100%;">
							<?php foreach ( $stages as $k => $d ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $d['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label><strong><?php esc_html_e( 'Source Channel', 'tripcosmos-agents' ); ?></strong></label><br>
						<select name="source_channel" style="width: 100%;">
							<option value="web"><?php esc_html_e( 'Web Widget', 'tripcosmos-agents' ); ?></option>
							<option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'tripcosmos-agents' ); ?></option>
							<option value="voice"><?php esc_html_e( 'Voice Call', 'tripcosmos-agents' ); ?></option>
						</select>
					</p>
				</div>
				<p>
					<label><strong><?php esc_html_e( 'Deal / Estimated Package Value (INR)', 'tripcosmos-agents' ); ?></strong></label><br>
					<input type="number" step="100" name="deal_value" value="0.00" class="regular-text" style="width: 100%;" />
				</p>
			</div>
			<div class="tc-modal-footer">
				<button type="button" class="button" onclick="document.getElementById('tc-lead-modal').style.display='none';"><?php esc_html_e( 'Cancel', 'tripcosmos-agents' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Lead', 'tripcosmos-agents' ); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
function viewMemory(contactId, name) {
	const modal = document.getElementById('tc-memory-modal');
	const content = document.getElementById('tc-modal-content');
	const title = document.getElementById('tc-modal-title');
	title.textContent = '🧠 Memory Engine: ' + name;
	content.innerHTML = '<p style="text-align: center; color: #64748b;">Loading traveler context...</p>';
	modal.style.display = 'flex';

	fetch(ajaxurl + '?action=tc_get_lead_memory&contact_id=' + contactId + '&_nonce=' + '<?php echo esc_js( wp_create_nonce( 'tc_get_lead_memory' ) ); ?>')
		.then(r => r.json())
		.then(res => {
			if (res.success && res.data) {
				const d = res.data;
				let html = '<div class="tc-memory-view">';
				html += '<div class="tc-memory-section"><strong>Summary:</strong><p>' + (d.summary || '<em>No synthesis generated yet.</em>') + '</p></div>';
				
				html += '<div class="tc-memory-section"><strong>Known Facts:</strong><ul>';
				if (d.facts && d.facts.length) {
					d.facts.forEach(f => html += '<li>' + escapeHtml(f) + '</li>');
				} else {
					html += '<li><em>No facts recorded.</em></li>';
				}
				html += '</ul></div>';

				html += '<div class="tc-memory-section"><strong>Traveler Preferences:</strong><ul>';
				if (d.preferences && d.preferences.length) {
					d.preferences.forEach(p => html += '<li>' + escapeHtml(p) + '</li>');
				} else {
					html += '<li><em>No preferences captured yet.</em></li>';
				}
				html += '</ul></div>';

				html += '<div class="tc-memory-section"><strong>Concerns & Objections:</strong><ul>';
				if (d.objections && d.objections.length) {
					d.objections.forEach(o => html += '<li>' + escapeHtml(o) + '</li>');
				} else {
					html += '<li><em>None noted.</em></li>';
				}
				html += '</ul></div>';

				html += '<div class="tc-memory-section highlight"><strong>Next Best Action:</strong><p>🎯 ' + (d.next_best_action || '<em>Continue inquiry exploration.</em>') + '</p></div>';
				html += '</div>';
				content.innerHTML = html;
			} else {
				content.innerHTML = '<p style="color: #64748b;">No memory record found for this contact yet. As they chat with AI agents, facts and preferences are automatically recorded here.</p>';
			}
		})
		.catch(e => {
			content.innerHTML = '<p style="color: #ef4444;">Failed to load memory context.</p>';
		});
}

function escapeHtml(str) {
	if (!str) return '';
	return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
