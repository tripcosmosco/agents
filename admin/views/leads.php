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
$where_clauses = array( TC_CRM_Access::scope_sql() );
if ( ! empty( $selected_channel ) ) {
	$where_clauses[] = $wpdb->prepare( 'source_channel = %s', $selected_channel );
}
if ( ! empty( $search_query ) ) {
	$like = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where_clauses[] = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR phone LIKE %s)', $like, $like, $like );
}

$where_sql = implode( ' AND ', $where_clauses );
$leads = $wpdb->get_results( "SELECT * FROM $table_contacts WHERE $where_sql ORDER BY updated_at DESC LIMIT 1000", ARRAY_A ) ?: array();
$all_sequences = $wpdb->get_results( "SELECT id, name, channel FROM {$wpdb->prefix}tc_agent_sequences WHERE is_active = 1", ARRAY_A ) ?: array();

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
	'completed'   => array( 'label' => __( 'Trip Completed', 'tripcosmos-agents' ), 'color' => '#0d9488', 'items' => array(), 'total' => 0.0 ),
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

	if ( 'won' === $st || 'completed' === $st ) {
		$won_pipe += $val;
		$won_count++;
	} elseif ( 'lost' !== $st ) {
		$total_pipe += $val;
	}
}

$conversion_rate = $total_leads > 0 ? round( ( $won_count / $total_leads ) * 100, 1 ) : 0;
?>

<style>
/* Self-Contained Leads & Kanban CRM Styles */
.tc-kanban-board {
	display: flex;
	gap: 16px;
	overflow-x: auto;
	padding-bottom: 24px;
	margin-top: 20px;
}
.tc-kanban-col {
	flex: 0 0 290px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	display: flex;
	flex-direction: column;
	max-height: 820px;
}
.tc-kanban-col-header {
	padding: 14px 16px;
	background: #ffffff;
	border-radius: 12px 12px 0 0;
	border-top: 4px solid #3b82f6;
	border-bottom: 1px solid #e2e8f0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.tc-kanban-col-title {
	font-weight: 700;
	font-size: 13px;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
}
.tc-kanban-count {
	background: #f1f5f9;
	color: #475569;
	font-size: 11px;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 10px;
}
.tc-kanban-col-val {
	font-weight: 800;
	font-size: 13px;
	color: #d97706;
}
.tc-kanban-cards {
	padding: 12px;
	overflow-y: auto;
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: 180px;
}
.tc-kanban-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 14px;
	box-shadow: 0 2px 6px rgba(0,0,0,0.03);
	cursor: grab;
	transition: all 0.2s ease;
}
.tc-kanban-card:hover {
	border-color: #f59e0b;
	box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
	transform: translateY(-2px);
}
.tc-kanban-card.is-dragging {
	opacity: 0.5;
	border-color: #0b132b;
}
.tc-card-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}
.tc-card-name {
	font-size: 14px;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 6px;
}
.tc-card-contact {
	font-size: 11px;
	color: #64748b;
	margin-bottom: 10px;
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.tc-card-price {
	font-size: 16px;
	font-weight: 800;
	color: #d97706;
	margin-bottom: 10px;
}
</style>

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

	<?php if ( ! empty( $_GET['duplicate'] ) ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo wp_kses_post( sprintf( __( 'That phone/email already belonged to <a href="%s">an existing contact</a>, so it was updated instead of creating a duplicate.', 'tripcosmos-agents' ), esc_url( TC_Contacts_Admin::url( array( 'contact' => absint( $_GET['duplicate'] ) ) ) ) ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Lead record updated successfully.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['enrolled'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact enrolled into automated drip sequence.', 'tripcosmos-agents' ); ?></p></div>
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
									<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $l['id'] ) ) ); ?>"><strong><?php echo esc_html( $l['name'] ?: __( 'Anonymous Traveler', 'tripcosmos-agents' ) ); ?></strong></a><?php if ( ! empty( $l['owner'] ) ) : ?> <span class="tc-tag" title="<?php esc_attr_e( 'Owner', 'tripcosmos-agents' ); ?>">👤 <?php echo esc_html( $l['owner'] ); ?></span><?php else : ?> <span class="tc-tag" style="opacity:.7;"><?php esc_html_e( 'Unassigned', 'tripcosmos-agents' ); ?></span><?php endif; ?><br>
									<small style="color: #64748b;"><?php echo esc_html( $l['email'] ?: '' ); ?> <?php echo esc_html( $l['phone'] ? '• ' . $l['phone'] : '' ); ?></small>
								</td>
								<td><span class="tc-channel-badge tc-channel-<?php echo esc_attr( $l['source_channel'] ); ?>"><?php echo esc_html( ucfirst( $l['source_channel'] ) ); ?></span></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( TC_Contacts_Admin::NONCE, '_tc_contacts_nonce' ); ?>
										<input type="hidden" name="tc_contacts_action" value="set_stage" />
										<input type="hidden" name="return" value="leads" />
										<input type="hidden" name="lost_reason" value="" />
										<input type="hidden" name="contact_id" value="<?php echo esc_attr( $l['id'] ); ?>" />
										<select name="stage" onchange="if(this.value==='lost'){var r=prompt('<?php echo esc_js( __( 'Why was this lead lost?', 'tripcosmos-agents' ) ); ?>','');if(r===null){this.form.reset();return;}this.form.lost_reason.value=r;}this.form.submit();" style="font-size: 12px; padding: 2px 6px;">
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
									<?php if ( ! empty( $all_sequences ) && TC_CRM_Access::can_manage() ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline-block; margin-left: 4px;">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="manual_enroll_lead" />
											<input type="hidden" name="contact_id" value="<?php echo esc_attr( $l['id'] ); ?>" />
											<input type="hidden" name="redirect_page" value="tc-agents-leads" />
											<select name="sequence_id" onchange="if(this.value){ this.form.submit(); }" style="font-size: 11px; padding: 2px 4px; height: 26px;" title="<?php esc_attr_e( 'Enroll into follow-up drip sequence', 'tripcosmos-agents' ); ?>">
												<option value=""><?php esc_html_e( '+ Drip', 'tripcosmos-agents' ); ?></option>
												<?php foreach ( $all_sequences as $seq_opt ) : ?>
													<option value="<?php echo esc_attr( $seq_opt['id'] ); ?>"><?php echo esc_html( $seq_opt['name'] ); ?></option>
												<?php endforeach; ?>
											</select>
										</form>
									<?php endif; ?>
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
										<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $card['id'] ) ) ); ?>"><strong><?php echo esc_html( $card['name'] ?: __( 'Anonymous Explorer', 'tripcosmos-agents' ) ); ?></strong></a><br /><small style="color:#64748b;">👤 <?php echo esc_html( $card['owner'] ?: __( 'Unassigned', 'tripcosmos-agents' ) ); ?></small>
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
											<?php wp_nonce_field( TC_Contacts_Admin::NONCE, '_tc_contacts_nonce' ); ?>
											<input type="hidden" name="tc_contacts_action" value="set_stage" />
											<input type="hidden" name="return" value="leads" />
											<input type="hidden" name="lost_reason" value="" />
											<input type="hidden" name="contact_id" value="<?php echo esc_attr( $card['id'] ); ?>" />
											<select name="stage" onchange="if(this.value==='lost'){var r=prompt('<?php echo esc_js( __( 'Why was this lead lost?', 'tripcosmos-agents' ) ); ?>','');if(r===null){this.form.reset();return;}this.form.lost_reason.value=r;}this.form.submit();" class="tc-stage-quick-select" style="width: 100%; font-size: 11px;">
												<?php foreach ( $stages as $k => $d ) : ?>
													<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st_key, $k ); ?>><?php echo esc_html( $d['label'] ); ?></option>
												<?php endforeach; ?>
											</select>
										</form>

										<?php if ( ! empty( $card['phone'] ) ) : ?>
											<?php
											$clean_lead_phone = preg_replace( '/[^0-9]/', '', $card['phone'] );
											$token_msg = rawurlencode( "Namaste " . ( $card['name'] ?: 'Traveler' ) . "! 🙏\n\nYour TripCosmos tour & cab slot is reserved (ref TC-" . $card['id'] . ").\nTo lock in your dates, a ₹1,500 advance token is needed. Reply to this message and we will share the payment details.\n\nBalance is payable upon vehicle arrival in Varanasi." );
											?>
											<a href="https://wa.me/<?php echo esc_attr( $clean_lead_phone ); ?>?text=<?php echo rawurlencode( 'Namaste ' . ( $card['name'] ?: 'Traveler' ) . ', this is TripCosmos (Varanasi) regarding your tour & cab inquiry!' ); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e( 'Chat on WhatsApp', 'tripcosmos-agents' ); ?>" style="background: #22c55e; color: #fff; border-color: #16a34a; padding: 0 6px; font-weight: bold; line-height: 24px; height: 26px;">
												WA
											</a>
											<a href="https://wa.me/<?php echo esc_attr( $clean_lead_phone ); ?>?text=<?php echo esc_attr( $token_msg ); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e( 'Send ₹1,500 Advance Token Link via WhatsApp', 'tripcosmos-agents' ); ?>" style="background: #fef3c7; color: #b45309; border-color: #fde68a; padding: 0 6px; font-weight: 700; line-height: 24px; height: 26px; font-size: 11px;">
												💳 ₹1.5k
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
			<?php wp_nonce_field( TC_Contacts_Admin::NONCE, '_tc_contacts_nonce' ); ?>
			<input type="hidden" name="tc_contacts_action" value="add_contact" />
			<input type="hidden" name="return" value="leads" />
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
