<?php
/**
 * Contacts: list, detail (timeline, tasks, bookings), duplicates, follow-ups, report.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$t_contacts = TC_Contact_Service::table();
$t_act      = $wpdb->prefix . 'tc_agent_activities';
$t_book     = $wpdb->prefix . 'tc_agent_bookings';
$stages     = TC_Contact_Service::stages();
$contact_id = absint( $_GET['contact'] ?? 0 );
$view       = sanitize_key( $_GET['view'] ?? 'list' );
$msg        = sanitize_key( $_GET['msg'] ?? '' );
$fluent_on  = class_exists( 'TC_Integration_FluentCRM' ) && TC_Integration_FluentCRM::is_active();
$now        = current_time( 'mysql' );

$tc_nonce = static function () {
	wp_nonce_field( TC_Contacts_Admin::NONCE, '_tc_contacts_nonce' );
};
$tc_dt = static function ( $v ) {
	return $v ? esc_html( mysql2date( 'j M Y, H:i', $v ) ) : '—';
};

$is_mgr   = TC_CRM_Access::can_manage();
$see_all  = TC_CRM_Access::can_view_all();
$me       = get_current_user_id();
$team     = TC_CRM_Access::team();
$tc_owner_select = static function ( $name, $selected, $allow_none = true ) use ( $team ) {
	echo '<select name="' . esc_attr( $name ) . '">';
	if ( $allow_none ) {
		echo '<option value="0">' . esc_html__( '— Unassigned —', 'tripcosmos-agents' ) . '</option>';
	}
	foreach ( $team as $u ) {
		echo '<option value="' . (int) $u->ID . '" ' . selected( (int) $selected, (int) $u->ID, false ) . '>' . esc_html( $u->display_name . ( TC_CRM_Access::is_away( $u->ID ) ? ' (away)' : '' ) ) . '</option>';
	}
	echo '</select>';
};

$tc_err   = get_transient( 'tc_contacts_error_' . $me );
if ( $tc_err ) {
	delete_transient( 'tc_contacts_error_' . $me );
}
$messages = array(
	'error'          => array( 'error', $tc_err ? (string) $tc_err : __( 'That did not work. Please try again.', 'tripcosmos-agents' ) ),
	'quote'          => array( 'success', __( 'Quote saved. Preview it, then send it on WhatsApp or email.', 'tripcosmos-agents' ) ),
	'quote_sent'     => array( 'success', __( 'Quote emailed.', 'tripcosmos-agents' ) ),
	'link'           => array( 'success', __( 'Payment link created. Copy it or send it on WhatsApp from the Payments list.', 'tripcosmos-agents' ) ),
	'payment'        => array( 'success', __( 'Payment recorded. The booking and pipeline are updated, and the customer got a receipt.', 'tripcosmos-agents' ) ),
	'pay_pending'    => array( 'info', __( 'Cashfree has not reported this payment as paid yet.', 'tripcosmos-agents' ) ),
	'claimed'        => array( 'success', __( 'Lead claimed. It is now assigned to you.', 'tripcosmos-agents' ) ),
	/* translators: %s: owner name */
	'existing_other' => array( 'warning', sprintf( __( 'This person is already a contact owned by %s. Ask them or a manager to reassign it.', 'tripcosmos-agents' ), sanitize_text_field( rawurldecode( (string) ( $_GET['owner'] ?? '' ) ) ) ?: __( 'another team member', 'tripcosmos-agents' ) ) ),
	/* translators: %d: count */
	'bulk_done'      => array( 'success', sprintf( __( 'Updated %d contacts.', 'tripcosmos-agents' ), absint( $_GET['n'] ?? 0 ) ) ),
	'created'      => array( 'success', __( 'Contact created.', 'tripcosmos-agents' ) ),
	'existing'     => array( 'info', __( 'A contact with that email or phone already existed, so it was updated instead of duplicated.', 'tripcosmos-agents' ) ),
	'invalid'      => array( 'error', __( 'A valid phone number or email is required.', 'tripcosmos-agents' ) ),
	'saved'        => array( 'success', __( 'Contact saved.', 'tripcosmos-agents' ) ),
	'booking'      => array( 'success', __( 'Booking saved.', 'tripcosmos-agents' ) ),
	'merged'       => array( 'success', __( 'Contacts merged.', 'tripcosmos-agents' ) ),
	'merge_failed' => array( 'error', __( 'Merge failed: check the contact ID.', 'tripcosmos-agents' ) ),
	'deleted'      => array( 'success', __( 'Contact deleted.', 'tripcosmos-agents' ) ),
	'synced'       => array( 'success', __( 'Synced to FluentCRM.', 'tripcosmos-agents' ) ),
	'sync_failed'  => array( 'warning', __( 'Not synced. See the sync status on this contact.', 'tripcosmos-agents' ) ),
	/* translators: %d: count */
	'bulk'         => array( 'success', sprintf( __( '%d contacts synced to FluentCRM (up to 200 per run; click again for more).', 'tripcosmos-agents' ), absint( $_GET['n'] ?? 0 ) ) ),
	/* translators: %d: count */
	'imported'     => array( 'success', sprintf( __( '%d contacts imported or updated.', 'tripcosmos-agents' ), absint( $_GET['n'] ?? 0 ) ) ),
);
?>
<div class="wrap tc-agents-wrap tc-cx-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Contacts', 'tripcosmos-agents' ); ?></h1>
	<a href="#tc-add-contact" class="page-title-action" onclick="document.getElementById('tc-add-contact').style.display='block';return false;"><?php esc_html_e( 'Add Contact', 'tripcosmos-agents' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( isset( $messages[ $msg ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $messages[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $msg ][1] ); ?></p></div>
	<?php endif; ?>
	<?php
	$tc_notes = get_transient( 'tc_contacts_notice_' . get_current_user_id() );
	if ( $tc_notes ) :
		delete_transient( 'tc_contacts_notice_' . get_current_user_id() );
		?>
		<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Remove these copies manually:', 'tripcosmos-agents' ); ?></strong></p><ul style="list-style: disc; margin-left: 20px;"><?php foreach ( (array) $tc_notes as $n ) : ?><li><?php echo esc_html( $n ); ?></li><?php endforeach; ?></ul></div>
	<?php endif; ?>

	<ul class="subsubsub">
		<?php
		$tabs = array(
			'mine'  => __( 'My Contacts', 'tripcosmos-agents' ),
			'list'  => $see_all ? __( 'All Contacts', 'tripcosmos-agents' ) : __( 'Mine + Unassigned', 'tripcosmos-agents' ),
			'board' => __( 'Pipeline Board', 'tripcosmos-agents' ),
			'tasks' => __( 'Follow-ups', 'tripcosmos-agents' ),
		);
		if ( $is_mgr ) {
			$tabs += array(
				'duplicates' => __( 'Duplicates', 'tripcosmos-agents' ),
				'report'     => __( 'Report', 'tripcosmos-agents' ),
				'team'       => __( 'Team', 'tripcosmos-agents' ),
				'billing'    => __( 'Billing', 'tripcosmos-agents' ),
				'tools'      => __( 'Import / Sync', 'tripcosmos-agents' ),
			);
		}
		if ( ! $is_mgr && in_array( $view, array( 'duplicates', 'report', 'team', 'tools', 'billing' ), true ) ) {
			$view = 'mine';
		}
		$last = array_key_last( $tabs );
		foreach ( $tabs as $k => $label ) :
			?>
			<li><a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'view' => $k ) ) ); ?>" class="<?php echo ( ! $contact_id && $view === $k ) ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?></a><?php echo $k !== $last ? ' |' : ''; ?></li>
		<?php endforeach; ?>
	</ul>
	<br class="clear" />

	<!-- Add contact -->
	<div id="tc-add-contact" class="card" style="display:none; max-width: 720px;">
		<h2><?php esc_html_e( 'Add Contact', 'tripcosmos-agents' ); ?></h2>
		<form method="post">
			<?php $tc_nonce(); ?>
			<input type="hidden" name="tc_contacts_action" value="add_contact" />
			<table class="form-table" role="presentation">
				<tr><th><label for="ac-name"><?php esc_html_e( 'Name', 'tripcosmos-agents' ); ?></label></th><td><input id="ac-name" name="name" class="regular-text" required /></td></tr>
				<tr><th><label for="ac-phone"><?php esc_html_e( 'Phone', 'tripcosmos-agents' ); ?></label></th><td><input id="ac-phone" name="phone" class="regular-text" placeholder="+91 98765 43210" /></td></tr>
				<tr><th><label for="ac-email"><?php esc_html_e( 'Email', 'tripcosmos-agents' ); ?></label></th><td><input id="ac-email" type="email" name="email" class="regular-text" /></td></tr>
				<?php if ( $is_mgr ) : ?><tr><th><?php esc_html_e( 'Owner', 'tripcosmos-agents' ); ?></th><td><?php $tc_owner_select( 'owner_user_id', $me ); ?></td></tr><?php endif; ?>
				<tr><th><label for="ac-stage"><?php esc_html_e( 'Stage', 'tripcosmos-agents' ); ?></label></th><td><select id="ac-stage" name="stage"><?php foreach ( $stages as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th><label for="ac-tags"><?php esc_html_e( 'Tags', 'tripcosmos-agents' ); ?></label></th><td><input id="ac-tags" name="tags" class="regular-text" placeholder="kashi-darshan, family" /></td></tr>
			</table>
			<?php submit_button( __( 'Save Contact', 'tripcosmos-agents' ) ); ?>
		</form>
	</div>

<?php

/* ------------------------------------------------------------------ KANBAN PIPELINE BOARD */
if ( 'board' === $view ) :
	$f_owner = isset( $_GET['owner'] ) && '' !== $_GET['owner'] ? absint( $_GET['owner'] ) : null;
	$b_where = array( TC_CRM_Access::scope_sql() );
	if ( null !== $f_owner ) {
		$b_where[] = $wpdb->prepare( 'owner_user_id = %d', $f_owner );
	}
	$b_where_sql = implode( ' AND ', $b_where );
	$all_leads = $wpdb->get_results( "SELECT * FROM $t_contacts WHERE $b_where_sql ORDER BY updated_at DESC LIMIT 300", ARRAY_A ) ?: array();

	// Group by stage
	$grouped = array();
	$board_stages = array(
		'inquiry'     => array( 'title' => __( 'Inquiry', 'tripcosmos-agents' ), 'dot' => 'inquiry' ),
		'qualified'   => array( 'title' => __( 'Qualified', 'tripcosmos-agents' ), 'dot' => 'qualified' ),
		'proposal'    => array( 'title' => __( 'Proposal Sent', 'tripcosmos-agents' ), 'dot' => 'proposal' ),
		'negotiation' => array( 'title' => __( 'Negotiation', 'tripcosmos-agents' ), 'dot' => 'negotiation' ),
		'won'         => array( 'title' => __( 'Won & Booked', 'tripcosmos-agents' ), 'dot' => 'won' ),
		'lost'        => array( 'title' => __( 'Lost', 'tripcosmos-agents' ), 'dot' => 'lost' ),
	);
	foreach ( array_keys( $board_stages ) as $sk ) {
		$grouped[ $sk ] = array();
	}
	foreach ( $all_leads as $lead ) {
		$st = $lead['stage'] ?? 'inquiry';
		if ( ! isset( $grouped[ $st ] ) ) {
			$st = 'inquiry';
		}
		$grouped[ $st ][] = $lead;
	}
	?>
	<!-- Filter & Pipeline Metrics Bar -->
	<div class="tc-crm-card" style="margin-bottom:14px; padding:14px 18px;">
		<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
			<div style="display:flex; align-items:center; gap:12px;">
				<h3 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">
					📊 <?php esc_html_e( 'Interactive Deal Pipeline', 'tripcosmos-agents' ); ?>
				</h3>
				<span class="tc-pill-metric tc-pill-indigo"><?php echo count( $all_leads ); ?> <?php esc_html_e( 'Total Deals', 'tripcosmos-agents' ); ?></span>
			</div>
			<div style="display:flex; align-items:center; gap:10px;">
				<?php if ( $see_all ) : ?>
					<form method="get" style="display:flex; align-items:center; gap:6px;">
						<input type="hidden" name="page" value="<?php echo esc_attr( TC_Contacts_Admin::PAGE ); ?>" />
						<input type="hidden" name="view" value="board" />
						<select name="owner" onchange="this.form.submit();" style="font-size:12.5px;">
							<option value=""><?php esc_html_e( 'All Team Deals', 'tripcosmos-agents' ); ?></option>
							<option value="<?php echo (int) $me; ?>" <?php selected( $f_owner, $me ); ?>><?php esc_html_e( 'My Deals Only', 'tripcosmos-agents' ); ?></option>
							<option value="0" <?php selected( $f_owner, 0 ); ?>><?php esc_html_e( 'Unassigned Deals', 'tripcosmos-agents' ); ?></option>
							<?php foreach ( $team as $tu ) : if ( (int) $tu->ID !== $me ) : ?>
								<option value="<?php echo (int) $tu->ID; ?>" <?php selected( $f_owner, (int) $tu->ID ); ?>><?php echo esc_html( $tu->display_name ); ?></option>
							<?php endif; endforeach; ?>
						</select>
					</form>
				<?php endif; ?>
				<span style="font-size:12px; color:#64748b;">✨ <?php esc_html_e( 'Drag & drop cards to move stages', 'tripcosmos-agents' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Kanban Board -->
	<div class="tc-kanban-wrap">
		<div class="tc-kanban-board">
			<?php
			foreach ( $board_stages as $stage_key => $sinfo ) :
				$cards    = $grouped[ $stage_key ];
				$stage_val = array_reduce( $cards, static function ( $carry, $item ) { return $carry + (float) ( $item['deal_value'] ?? 0 ); }, 0.0 );
				?>
				<div class="tc-kanban-col" data-stage="<?php echo esc_attr( $stage_key ); ?>" ondragover="tcKanbanDragOver(event)" ondragleave="tcKanbanDragLeave(event)" ondrop="tcKanbanDrop(event, '<?php echo esc_attr( $stage_key ); ?>')">
					<div class="tc-kanban-col-head">
						<div class="tc-kanban-col-title">
							<span class="tc-col-dot tc-col-dot-<?php echo esc_attr( $sinfo['dot'] ); ?>"></span>
							<span><?php echo esc_html( $sinfo['title'] ); ?></span>
						</div>
						<div class="tc-kanban-col-meta">
							<span class="tc-kanban-count"><?php echo count( $cards ); ?></span>
							<?php if ( $stage_val > 0 ) : ?>
								<span class="tc-kanban-val">₹<?php echo esc_html( number_format_i18n( $stage_val ) ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="tc-kanban-cards">
						<?php
						foreach ( $cards as $lead ) :
							$lid        = (int) $lead['id'];
							$lname      = $lead['name'] ?: __( 'Traveler', 'tripcosmos-agents' );
							$lval       = (float) ( $lead['deal_value'] ?? 0 );
							$lphone     = preg_replace( '/\D/', '', (string) ( $lead['phone_e164'] ?: $lead['phone'] ) );
							$ltags      = TC_Contact_Service::parse_tags( $lead['tags'] );
							$lowner     = $lead['owner'] ?: __( 'Unassigned', 'tripcosmos-agents' );
							$lead_owner_initial = strtoupper( mb_substr( $lowner, 0, 1 ) );
							$overdue    = $lead['next_follow_up_at'] && $lead['next_follow_up_at'] < $now;
							?>
							<div class="tc-kanban-card" id="tc-lead-card-<?php echo $lid; ?>" draggable="true" ondragstart="tcKanbanDragStart(event, <?php echo $lid; ?>)" ondragend="tcKanbanDragEnd(event)">
								<div class="tc-kcard-head">
									<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $lid ) ) ); ?>" class="tc-kcard-name">
										<?php echo esc_html( $lname ); ?>
									</a>
									<?php if ( $lval > 0 ) : ?>
										<span class="tc-kcard-val">₹<?php echo esc_html( number_format_i18n( $lval ) ); ?></span>
									<?php endif; ?>
								</div>

								<div class="tc-kcard-contact">
									<?php if ( $lead['phone'] ) : ?>
										<span>📱 <?php echo esc_html( $lead['phone'] ); ?></span>
									<?php elseif ( $lead['email'] ) : ?>
										<span>✉️ <?php echo esc_html( $lead['email'] ); ?></span>
									<?php endif; ?>
								</div>

								<?php if ( $ltags ) : ?>
									<div class="tc-kcard-tags">
										<?php foreach ( array_slice( $ltags, 0, 3 ) as $ltag ) : ?>
											<span class="tc-kcard-tag"><?php echo esc_html( $ltag ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<?php if ( $lead['next_follow_up_at'] ) : ?>
									<div style="font-size:11px; margin-bottom:6px; <?php echo $overdue ? 'color:#dc2626;font-weight:700;' : 'color:#64748b;'; ?>">
										⏰ <?php echo $overdue ? esc_html__( 'Overdue: ', 'tripcosmos-agents' ) : ''; ?><?php echo esc_html( mysql2date( 'j M, H:i', $lead['next_follow_up_at'] ) ); ?>
									</div>
								<?php endif; ?>

								<div class="tc-kcard-footer">
									<div class="tc-kcard-owner">
										<span class="tc-kcard-owner-avatar"><?php echo esc_html( $lead_owner_initial ); ?></span>
										<span><?php echo esc_html( $lowner ); ?></span>
									</div>
									<div class="tc-kcard-actions">
										<?php if ( $lphone ) : ?>
											<a href="<?php echo esc_url( 'https://wa.me/' . $lphone . '?text=' . rawurlencode( 'Namaste ' . strtok( $lname, ' ' ) . ', regarding your TripCosmos travel inquiry:' ) ); ?>" target="_blank" class="tc-kcard-btn tc-kcard-btn-wa" title="WhatsApp">
												💬
											</a>
										<?php endif; ?>
										<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $lid ) ) ); ?>" class="tc-kcard-btn" title="View Details">
											&rarr;
										</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Toast -->
	<div id="tc-crm-toast" class="tc-crm-toast">
		<span id="tc-toast-text"></span>
	</div>

	<script>
	var tcDraggedId = null;

	function tcKanbanDragStart(e, id) {
		tcDraggedId = id;
		e.target.classList.add('is-dragging');
		e.dataTransfer.setData('text/plain', id);
		e.dataTransfer.effectAllowed = 'move';
	}

	function tcKanbanDragEnd(e) {
		e.target.classList.remove('is-dragging');
		document.querySelectorAll('.tc-kanban-col').forEach(function(c) {
			c.classList.remove('is-dragover');
		});
	}

	function tcKanbanDragOver(e) {
		e.preventDefault();
		e.currentTarget.classList.add('is-dragover');
	}

	function tcKanbanDragLeave(e) {
		e.currentTarget.classList.remove('is-dragover');
	}

	function tcKanbanDrop(e, targetStage) {
		e.preventDefault();
		e.currentTarget.classList.remove('is-dragover');
		if (!tcDraggedId) return;

		var card = document.getElementById('tc-lead-card-' + tcDraggedId);
		if (!card) return;

		var cardsContainer = e.currentTarget.querySelector('.tc-kanban-cards');
		if (cardsContainer) {
			cardsContainer.appendChild(card);
		}

		var contactId = tcDraggedId;
		tcDraggedId = null;

		var lostReason = '';
		if (targetStage === 'lost') {
			lostReason = prompt('Reason for marking deal as lost (optional):') || '';
		}

		// AJAX request to persist stage
		var formData = new FormData();
		formData.append('action', 'tc_set_stage');
		formData.append('nonce', '<?php echo wp_create_nonce( TC_Contacts_Admin::NONCE ); ?>');
		formData.append('contact_id', contactId);
		formData.append('stage', targetStage);
		formData.append('lost_reason', lostReason);

		fetch(ajaxurl, {
			method: 'POST',
			body: formData
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res.success) {
				tcShowToast(res.data.message || 'Stage updated');
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error updating stage');
				window.location.reload();
			}
		})
		.catch(function(err) {
			console.error(err);
			window.location.reload();
		});
	}

	function tcShowToast(msg) {
		var toast = document.getElementById('tc-crm-toast');
		var text = document.getElementById('tc-toast-text');
		if (!toast || !text) return;
		text.innerText = '✓ ' + msg;
		toast.classList.add('is-show');
		setTimeout(function() {
			toast.classList.remove('is-show');
		}, 3000);
	}
	</script>
</div>
	<?php
	return;
endif;
?>

<?php
/* ------------------------------------------------------------------ DETAIL */
if ( $contact_id ) :
	require __DIR__ . '/partials/contact-detail.php';
	return;
endif;

/* ------------------------------------------------------------------ FOLLOW-UPS */
if ( 'tasks' === $view ) :
	$tasks = $wpdb->get_results(
		"SELECT a.*, c.name, c.phone, c.owner FROM $t_act a INNER JOIN $t_contacts c ON c.id = a.contact_id WHERE a.type = 'task' AND a.done_at IS NULL AND " . ( $see_all ? '1=1' : $wpdb->prepare( 'c.owner_user_id = %d', $me ) ) . ' ORDER BY a.due_at ASC LIMIT 300', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	) ?: array();
	?>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Due', 'tripcosmos-agents' ); ?></th><th><?php esc_html_e( 'Task', 'tripcosmos-agents' ); ?></th><th><?php esc_html_e( 'Contact', 'tripcosmos-agents' ); ?></th><th><?php esc_html_e( 'Owner', 'tripcosmos-agents' ); ?></th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $tasks as $task ) : ?>
			<tr>
				<td style="<?php echo $task['due_at'] < $now ? 'color:#b91c1c;font-weight:600;' : ''; ?>"><?php echo $tc_dt( $task['due_at'] ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( $task['content'] ); ?></td>
				<td><a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $task['contact_id'] ) ) ); ?>"><?php echo esc_html( $task['name'] ); ?></a><br /><small><?php echo esc_html( $task['phone'] ); ?></small></td>
				<td><?php echo esc_html( $task['owner'] ?: '—' ); ?></td>
				<td>
					<form method="post">
						<?php $tc_nonce(); ?>
						<input type="hidden" name="tc_contacts_action" value="complete_task" />
						<input type="hidden" name="activity_id" value="<?php echo (int) $task['id']; ?>" />
						<button class="button button-small"><?php esc_html_e( 'Done', 'tripcosmos-agents' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $tasks ) : ?><tr><td colspan="5"><?php esc_html_e( 'No open follow-ups. 🎉', 'tripcosmos-agents' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
</div>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ DUPLICATES */
if ( 'duplicates' === $view ) :
	$groups = TC_Contact_Service::find_duplicates();
	?>
	<p class="description"><?php esc_html_e( 'Contacts sharing the same phone number or email. Merging keeps the oldest record and moves conversations, timeline, bookings and sequences onto it.', 'tripcosmos-agents' ); ?></p>
	<?php if ( ! $groups ) : ?><p><?php esc_html_e( 'No duplicates found.', 'tripcosmos-agents' ); ?></p><?php endif; ?>
	<?php foreach ( $groups as $g ) : $keep = $g[0]; ?>
		<table class="widefat striped" style="margin-bottom:16px;">
			<tbody>
			<?php foreach ( $g as $i => $r ) : ?>
				<tr>
					<td style="width:60px;">#<?php echo (int) $r['id']; ?></td>
					<td><a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $r['id'] ) ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a></td>
					<td><?php echo esc_html( $r['phone'] ); ?></td>
					<td><?php echo esc_html( $r['email'] ); ?></td>
					<td><?php echo esc_html( $stages[ $r['stage'] ] ?? $r['stage'] ); ?></td>
					<td><?php echo $tc_dt( $r['created_at'] ); // phpcs:ignore ?></td>
					<td>
						<?php if ( 0 === $i ) : ?>
							<strong><?php esc_html_e( 'Keep', 'tripcosmos-agents' ); ?></strong>
						<?php else : ?>
							<form method="post">
								<?php $tc_nonce(); ?>
								<input type="hidden" name="tc_contacts_action" value="merge" />
								<input type="hidden" name="contact_id" value="<?php echo (int) $keep['id']; ?>" />
								<input type="hidden" name="merge_id" value="<?php echo (int) $r['id']; ?>" />
								<button class="button button-small"><?php echo esc_html( sprintf( __( 'Merge into #%d', 'tripcosmos-agents' ), $keep['id'] ) ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
</div>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ REPORT */
if ( 'report' === $view ) :
	$by_stage  = $wpdb->get_results( "SELECT stage, COUNT(*) n, SUM(deal_value) v FROM $t_contacts GROUP BY stage", OBJECT_K ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$won       = (int) ( $by_stage['won']->n ?? 0 ) + (int) ( $by_stage['completed']->n ?? 0 );
	$lost      = (int) ( $by_stage['lost']->n ?? 0 );
	$total_dec = $won + $lost;
	$win_rate  = $total_dec > 0 ? round( 100 * $won / $total_dec ) : 0;
	$open_pipe = (float) $wpdb->get_var( "SELECT SUM(deal_value) FROM $t_contacts WHERE stage NOT IN ('won','completed','lost')" ) ?: 0;
	$open_cnt  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_contacts WHERE stage NOT IN ('won','completed','lost')" ) ?: 0;
	$lost_why  = $wpdb->get_results( "SELECT IF(lost_reason = '', '(no reason given)', lost_reason) r, COUNT(*) n FROM $t_contacts WHERE stage = 'lost' GROUP BY r ORDER BY n DESC LIMIT 10", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sources   = $wpdb->get_results( "SELECT IF(utm_source <> '', CONCAT(utm_source, IF(utm_campaign <> '', CONCAT(' / ', utm_campaign), '')), source_channel) s, COUNT(*) n, SUM(stage IN ('won','completed')) w, SUM(IF(stage IN ('won','completed'), deal_value, 0)) rev FROM $t_contacts GROUP BY s ORDER BY n DESC LIMIT 15", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$avg_days  = $wpdb->get_var( "SELECT AVG(DATEDIFF(a.created_at, c.created_at)) FROM $t_act a INNER JOIN $t_contacts c ON c.id = a.contact_id WHERE a.type = 'stage_change' AND a.meta LIKE '%to%won%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$overdue   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_act WHERE type = 'task' AND done_at IS NULL AND due_at < %s", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$unsynced  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_contacts WHERE sync_status = 'error'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$bookings  = $wpdb->get_row( "SELECT COUNT(*) n, SUM(quote_amount) q, SUM(advance_paid) a FROM $t_book WHERE status NOT IN ('cancelled','quoted')", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$reps      = $wpdb->get_results( "SELECT owner_user_id, COUNT(*) total_leads, SUM(stage IN ('won','completed')) won_leads, SUM(IF(stage IN ('won','completed'), deal_value, 0)) won_val FROM $t_contacts WHERE owner_user_id > 0 GROUP BY owner_user_id ORDER BY won_val DESC LIMIT 8", ARRAY_A ) ?: array();

	// Funnel progression counts & values
	$f_inq_n   = (int) ( $by_stage['inquiry']->n ?? 0 );
	$f_inq_v   = (float) ( $by_stage['inquiry']->v ?? 0 );
	$f_qua_n   = (int) ( $by_stage['qualified']->n ?? 0 );
	$f_qua_v   = (float) ( $by_stage['qualified']->v ?? 0 );
	$f_pro_n   = (int) ( $by_stage['proposal']->n ?? 0 );
	$f_pro_v   = (float) ( $by_stage['proposal']->v ?? 0 );
	$f_neg_n   = (int) ( $by_stage['negotiation']->n ?? 0 );
	$f_neg_v   = (float) ( $by_stage['negotiation']->v ?? 0 );
	$f_won_n   = $won;
	$f_won_v   = (float) ( ( $by_stage['won']->v ?? 0 ) + ( $by_stage['completed']->v ?? 0 ) );
	$tot_vol   = max( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_contacts" ) );
	?>

	<!-- Executive Analytics & Funnel Header -->
	<div class="tc-analytics-cockpit">
		<!-- KPI Ribbon -->
		<div class="tc-kpi-deck">
			<div class="tc-kpi-card tc-kpi-accent">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Pipeline Win Rate', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">🎯</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val"><?php echo (int) $win_rate; ?>%</span>
					<span class="tc-kpi-sub"><?php echo esc_html( sprintf( __( '%d won of %d closed', 'tripcosmos-agents' ), $won, $total_dec ) ); ?></span>
				</div>
				<div class="tc-kpi-bar-bg"><div class="tc-kpi-bar-fill" style="width:<?php echo (int) $win_rate; ?>%;"></div></div>
			</div>

			<div class="tc-kpi-card">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Active Pipeline Value', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">💼</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val">₹<?php echo esc_html( number_format_i18n( $open_pipe ) ); ?></span>
					<span class="tc-kpi-sub"><?php echo esc_html( sprintf( __( '%d deals in progress', 'tripcosmos-agents' ), $open_cnt ) ); ?></span>
				</div>
			</div>

			<div class="tc-kpi-card">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Booked Revenue', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">📈</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val">₹<?php echo esc_html( number_format_i18n( (float) ( $bookings['q'] ?? 0 ) ) ); ?></span>
					<span class="tc-kpi-sub"><?php echo esc_html( sprintf( __( '%d confirmed itineraries', 'tripcosmos-agents' ), (int) ( $bookings['n'] ?? 0 ) ) ); ?></span>
				</div>
			</div>

			<div class="tc-kpi-card">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Advance Cash Collected', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">💰</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val" style="color:#059669;">₹<?php echo esc_html( number_format_i18n( (float) ( $bookings['a'] ?? 0 ) ) ); ?></span>
					<span class="tc-kpi-sub"><?php esc_html_e( 'Secured upfront deposits', 'tripcosmos-agents' ); ?></span>
				</div>
			</div>

			<div class="tc-kpi-card">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Sales Cycle Velocity', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">⚡</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val"><?php echo null !== $avg_days ? esc_html( round( (float) $avg_days, 1 ) ) . ' ' . esc_html__( 'days', 'tripcosmos-agents' ) : '—'; ?></span>
					<span class="tc-kpi-sub"><?php esc_html_e( 'Inquiry to Won close', 'tripcosmos-agents' ); ?></span>
				</div>
			</div>

			<div class="tc-kpi-card">
				<div class="tc-kpi-top">
					<span class="tc-kpi-title"><?php esc_html_e( 'Urgent Follow-ups', 'tripcosmos-agents' ); ?></span>
					<span class="tc-kpi-icon">⏰</span>
				</div>
				<div class="tc-kpi-main">
					<span class="tc-kpi-val" style="color:<?php echo $overdue > 0 ? '#e11d48' : '#64748b'; ?>;"><?php echo (int) $overdue; ?></span>
					<span class="tc-kpi-sub"><a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'view' => 'tasks' ) ) ); ?>"><?php esc_html_e( 'View overdue tasks &rarr;', 'tripcosmos-agents' ); ?></a></span>
				</div>
			</div>
		</div>

		<!-- Conversion Funnel Section -->
		<div class="tc-funnel-panel">
			<div class="tc-funnel-header">
				<div>
					<h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><?php esc_html_e( 'Full-Funnel Pipeline Velocity & Conversion Drop-Off', 'tripcosmos-agents' ); ?></h3>
					<p style="margin:3px 0 0 0; font-size:12px; color:#64748b;"><?php esc_html_e( 'Stage-by-stage progression from initial pilgrim inquiry to signed and deposited tour.', 'tripcosmos-agents' ); ?></p>
				</div>
				<div class="tc-funnel-legend">
					<span>Total Leads Analyzed: <strong><?php echo (int) $tot_vol; ?></strong></span>
				</div>
			</div>

			<div class="tc-funnel-chart">
				<?php
				$funnel_stages = array(
					array( 'key' => 'inquiry', 'label' => __( '1. Inquiries', 'tripcosmos-agents' ), 'n' => $f_inq_n, 'v' => $f_inq_v, 'color' => '#64748b' ),
					array( 'key' => 'qualified', 'label' => __( '2. Qualified', 'tripcosmos-agents' ), 'n' => $f_qua_n, 'v' => $f_qua_v, 'color' => '#0284c7' ),
					array( 'key' => 'proposal', 'label' => __( '3. Proposal Sent', 'tripcosmos-agents' ), 'n' => $f_pro_n, 'v' => $f_pro_v, 'color' => '#8b5cf6' ),
					array( 'key' => 'negotiation', 'label' => __( '4. Negotiation', 'tripcosmos-agents' ), 'n' => $f_neg_n, 'v' => $f_neg_v, 'color' => '#f59e0b' ),
					array( 'key' => 'won', 'label' => __( '5. Won & Booked', 'tripcosmos-agents' ), 'n' => $f_won_n, 'v' => $f_won_v, 'color' => '#10b981' ),
				);

				foreach ( $funnel_stages as $fs ) :
					$pct = round( ( $fs['n'] / $tot_vol ) * 100 );
				?>
					<div class="tc-funnel-row">
						<div class="tc-funnel-label-col">
							<strong><?php echo esc_html( $fs['label'] ); ?></strong>
						</div>
						<div class="tc-funnel-bar-col">
							<div class="tc-funnel-bar-track">
								<div class="tc-funnel-bar-fill" style="width:<?php echo max( 6, min( 100, $pct ) ); ?>%; background:<?php echo esc_attr( $fs['color'] ); ?>;">
									<span class="tc-funnel-bar-text"><?php echo (int) $fs['n']; ?> leads (<?php echo (int) $pct; ?>%)</span>
								</div>
							</div>
						</div>
						<div class="tc-funnel-val-col">
							₹<?php echo esc_html( number_format_i18n( $fs['v'] ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Two Column Deep Attribution & Leaderboard -->
		<div class="tc-analytics-grid">
			<!-- Traffic & Campaign Attribution -->
			<div class="tc-analytics-box">
				<div class="tc-analytics-box-header">
					<h4>📊 <?php esc_html_e( 'Marketing Channel & Campaign Attribution', 'tripcosmos-agents' ); ?></h4>
				</div>
				<table class="tc-analytics-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source / Campaign', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Inbound', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Won', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Conv %', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Revenue', 'tripcosmos-agents' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sources as $s ) : 
							$src_cnt = (int) $s['n'];
							$src_won = (int) $s['w'];
							$src_pct = $src_cnt > 0 ? round( 100 * $src_won / $src_cnt ) : 0;
						?>
							<tr>
								<td>
									<div style="font-weight:600; color:#1e293b;"><?php echo esc_html( $s['s'] ?: __( 'Direct / Unspecified', 'tripcosmos-agents' ) ); ?></div>
									<div class="tc-mini-progress"><div class="tc-mini-progress-fill" style="width:<?php echo (int) $src_pct; ?>%;"></div></div>
								</td>
								<td style="text-align:center; font-weight:600;"><?php echo (int) $src_cnt; ?></td>
								<td style="text-align:center; color:#059669; font-weight:700;"><?php echo (int) $src_won; ?></td>
								<td style="text-align:center;"><span class="tc-chip-pct"><?php echo (int) $src_pct; ?>%</span></td>
								<td style="text-align:right; font-weight:600;">₹<?php echo esc_html( number_format_i18n( (float) ( $s['rev'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Sales Rep Leaderboard -->
			<div class="tc-analytics-box">
				<div class="tc-analytics-box-header">
					<h4>🏆 <?php esc_html_e( 'Sales Rep Closing Leaderboard', 'tripcosmos-agents' ); ?></h4>
				</div>
				<table class="tc-analytics-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Advisor', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Assigned', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Won', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Close Rate', 'tripcosmos-agents' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Closed ₹', 'tripcosmos-agents' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php 
						$ranks = array( '🥇', '🥈', '🥉', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣' );
						$i = 0;
						foreach ( $reps as $rep ) : 
							$u_info  = get_userdata( (int) $rep['owner_user_id'] );
							$u_name  = $u_info ? $u_info->display_name : sprintf( 'Agent #%d', (int) $rep['owner_user_id'] );
							$r_tot   = (int) $rep['total_leads'];
							$r_won   = (int) $rep['won_leads'];
							$r_pct   = $r_tot > 0 ? round( 100 * $r_won / $r_tot ) : 0;
							$r_icon  = $ranks[ $i ] ?? '⭐';
							$i++;
						?>
							<tr>
								<td>
									<div style="display:flex; align-items:center; gap:8px;">
										<span style="font-size:16px;"><?php echo esc_html( $r_icon ); ?></span>
										<div>
											<strong style="color:#0f172a;"><?php echo esc_html( $u_name ); ?></strong>
										</div>
									</div>
								</td>
								<td style="text-align:center; font-weight:600;"><?php echo (int) $r_tot; ?></td>
								<td style="text-align:center; color:#059669; font-weight:700;"><?php echo (int) $r_won; ?></td>
								<td style="text-align:center;"><span class="tc-chip-pct"><?php echo (int) $r_pct; ?>%</span></td>
								<td style="text-align:right; font-weight:700; color:#0f172a;">₹<?php echo esc_html( number_format_i18n( (float) $rep['won_val'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $reps ) : ?>
							<tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No closed deals recorded yet.', 'tripcosmos-agents' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Lost Reasons Breakdown -->
				<div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0;">
					<h5 style="margin:0 0 10px 0; font-size:13px; color:#475569;"><?php esc_html_e( 'Lost Deals Pareto Analysis', 'tripcosmos-agents' ); ?></h5>
					<?php foreach ( $lost_why as $lw ) : 
						$l_cnt = (int) $lw['n'];
						$l_pct = $lost > 0 ? round( ( $l_cnt / $lost ) * 100 ) : 0;
					?>
						<div style="margin-bottom:8px;">
							<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
								<span style="color:#334155; font-weight:500;"><?php echo esc_html( $lw['r'] ); ?></span>
								<span style="color:#64748b;"><?php echo (int) $l_cnt; ?> (<?php echo (int) $l_pct; ?>%)</span>
							</div>
							<div style="background:#f1f5f9; height:6px; border-radius:3px; overflow:hidden;">
								<div style="background:#f43f5e; width:<?php echo (int) $l_pct; ?>%; height:100%;"></div>
							</div>
						</div>
					<?php endforeach; ?>
					<?php if ( ! $lost_why ) : ?>
						<p style="font-size:12px; color:#94a3b8; margin:0;"><?php esc_html_e( 'No lost deals recorded.', 'tripcosmos-agents' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
	<?php
	return;
endif;


/* ------------------------------------------------------------------ TOOLS */
if ( 'tools' === $view ) :
	$counts = $wpdb->get_row( "SELECT COUNT(*) total, SUM(sync_status = 'synced') synced, SUM(sync_status = 'error') errors, SUM(sync_status = '' AND email <> '' AND email IS NOT NULL) pending, SUM(sync_status = 'skipped_no_email') no_email FROM $t_contacts", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	?>
	<div class="card" style="max-width:760px;">
		<h3><?php esc_html_e( 'FluentCRM sync', 'tripcosmos-agents' ); ?></h3>
		<?php if ( ! $fluent_on ) : ?>
			<p><?php esc_html_e( 'FluentCRM is not active.', 'tripcosmos-agents' ); ?></p>
		<?php else : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: counts */
						__( '%1$d contacts · %2$d synced · %3$d waiting · %4$d errors · %5$d phone-only (kept local, no email to sync)', 'tripcosmos-agents' ),
						$counts['total'],
						$counts['synced'],
						$counts['pending'],
						$counts['errors'],
						$counts['no_email']
					)
				);
				?>
			</p>
			<form method="post" style="display:inline-block; margin-right:12px;">
				<?php $tc_nonce(); ?>
				<input type="hidden" name="tc_contacts_action" value="sync_all" />
				<button class="button button-primary"><?php esc_html_e( 'Push waiting & failed contacts to FluentCRM', 'tripcosmos-agents' ); ?></button>
			</form>
			<hr />
			<form method="post">
				<?php $tc_nonce(); ?>
				<input type="hidden" name="tc_contacts_action" value="import_fluentcrm" />
				<p><?php esc_html_e( 'Import FluentCRM subscribers that are not yet TripCosmos contacts (500 per click).', 'tripcosmos-agents' ); ?></p>
				<label><?php esc_html_e( 'Only with tag (slug, optional):', 'tripcosmos-agents' ); ?> <input name="tag" placeholder="tripcosmos-lead" /></label>
				<button class="button"><?php esc_html_e( 'Import from FluentCRM', 'tripcosmos-agents' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<div class="card" style="max-width:760px;">
		<h3><?php esc_html_e( 'CSV', 'tripcosmos-agents' ); ?></h3>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( TC_Contacts_Admin::url( array( 'tc_export' => 1 ) ), 'tc_contacts_export' ) ); ?>"><?php esc_html_e( 'Export all contacts (CSV)', 'tripcosmos-agents' ); ?></a></p>
		<form method="post" enctype="multipart/form-data">
			<?php $tc_nonce(); ?>
			<input type="hidden" name="tc_contacts_action" value="import_csv" />
			<p><?php esc_html_e( 'Import a CSV with a header row. Recognised columns: name, email, phone, stage, owner, tags, source, deal_value. Existing contacts (same email or phone) are updated, not duplicated. Max 2 MB / 5,000 rows.', 'tripcosmos-agents' ); ?></p>
			<input type="file" name="csv" accept=".csv,text/csv" required />
			<button class="button"><?php esc_html_e( 'Import CSV', 'tripcosmos-agents' ); ?></button>
		</form>
	</div>
</div>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ BILLING */
if ( 'billing' === $view && $is_mgr ) :
	$bs     = TC_Quote_Service::settings();
	$cf     = TC_Payments::cashfree_credentials();
	$raw    = (array) get_option( 'tc_agents_billing', array() );
	$is_adm = current_user_can( 'manage_options' );
	$woo_cf = get_option( 'woocommerce_cashfree_settings' );
	$field  = static function ( $name, $label, $value, $type = 'text', $help = '', $attrs = '' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" ' . $attrs . ' />' . ( $help ? '<small class="tc-cx-muted">' . esc_html( $help ) . '</small>' : '' ) . '</label>'; // phpcs:ignore
	};
	?>
	<form method="post" class="tc-cx" style="max-width:980px;">
		<?php $tc_nonce(); ?>
		<input type="hidden" name="tc_contacts_action" value="save_billing" />

		<section class="tc-cx-card" style="margin-top:12px;">
			<h3><?php esc_html_e( 'Online payments: Cashfree', 'tripcosmos-agents' ); ?>
				<span class="tc-cx-pill <?php echo TC_Payments::cashfree_ready() ? 'tc-stage-won' : 'tc-cx-pill-red'; ?>" style="margin-left:8px;"><?php echo TC_Payments::cashfree_ready() ? esc_html( sprintf( __( 'Ready · %1$s · %2$s keys', 'tripcosmos-agents' ), $cf['sandbox'] ? 'TEST' : 'LIVE', 'woocommerce' === $cf['source'] ? 'WooCommerce' : 'own' ) ) : esc_html__( 'Not configured', 'tripcosmos-agents' ); ?></span>
			</h3>
			<p class="tc-cx-muted"><?php esc_html_e( 'Customers pay by UPI, card or net banking on Cashfree\'s page; the booking is updated automatically once Cashfree confirms.', 'tripcosmos-agents' ); ?></p>
			<?php if ( $is_adm ) : ?>
				<label class="tc-cx-toggle"><input type="checkbox" name="cf_use_woo" value="1" <?php checked( ! isset( $raw['cf_use_woo'] ) || ! empty( $raw['cf_use_woo'] ) ); ?> /> <?php echo is_array( $woo_cf ) && ! empty( $woo_cf['app_id'] ) ? esc_html__( 'Use the Cashfree keys already saved in WooCommerce (recommended)', 'tripcosmos-agents' ) : esc_html__( 'Use WooCommerce Cashfree keys (none found)', 'tripcosmos-agents' ); ?></label>
				<div class="tc-cx-form" style="margin-top:10px;">
					<?php $field( 'cf_app_id', __( 'Or: own App ID', 'tripcosmos-agents' ), '', 'password', TC_Agents_Vault::hint( 'cashfree_app_id' ), 'autocomplete="new-password"' ); ?>
					<?php $field( 'cf_secret', __( 'Own Secret Key', 'tripcosmos-agents' ), '', 'password', TC_Agents_Vault::hint( 'cashfree_secret' ), 'autocomplete="new-password"' ); ?>
				</div>
				<label class="tc-cx-toggle"><input type="checkbox" name="cf_sandbox" value="1" <?php checked( ! empty( $raw['cf_sandbox'] ) ); ?> /> <?php esc_html_e( 'Own keys are TEST (sandbox) keys', 'tripcosmos-agents' ); ?></label>
			<?php else : ?>
				<p class="tc-cx-muted"><?php esc_html_e( 'Only a site administrator can change gateway keys.', 'tripcosmos-agents' ); ?></p>
			<?php endif; ?>
			<p style="margin-bottom:0;"><strong><?php esc_html_e( 'Webhook URL', 'tripcosmos-agents' ); ?>:</strong> <code><?php echo esc_html( rest_url( 'tc-agents/v1/cashfree-webhook' ) ); ?></code><br /><small class="tc-cx-muted"><?php esc_html_e( 'Payment links already tell Cashfree to call this. Adding it in the Cashfree dashboard (Developers → Webhooks, Payment Links) is optional; the return page and "Check status" also confirm payments.', 'tripcosmos-agents' ); ?></small></p>
		</section>

		<section class="tc-cx-card" style="margin-top:12px;">
			<h3><?php esc_html_e( 'UPI (direct to your account)', 'tripcosmos-agents' ); ?></h3>
			<div class="tc-cx-form">
				<?php $field( 'upi_vpa', __( 'UPI ID', 'tripcosmos-agents' ), $bs['upi_vpa'], 'text', __( 'e.g. tripcosmos@okhdfcbank. Shown as a QR on quotes; staff mark these payments received manually.', 'tripcosmos-agents' ) ); ?>
				<?php $field( 'upi_name', __( 'Payee name', 'tripcosmos-agents' ), $bs['upi_name'] ); ?>
			</div>
		</section>

		<section class="tc-cx-card" style="margin-top:12px;">
			<h3><?php esc_html_e( 'Business details (shown on quotes & receipts)', 'tripcosmos-agents' ); ?></h3>
			<div class="tc-cx-form">
				<?php $field( 'business_name', __( 'Business name', 'tripcosmos-agents' ), $bs['business_name'] ); ?>
				<?php $field( 'phone', __( 'WhatsApp / phone', 'tripcosmos-agents' ), $bs['phone'] ); ?>
				<?php $field( 'email', __( 'Email', 'tripcosmos-agents' ), $bs['email'], 'email' ); ?>
				<?php $field( 'invoice_prefix', __( 'Invoice prefix', 'tripcosmos-agents' ), $bs['invoice_prefix'], 'text', __( 'Numbers look like TC/2026-27/0001 and restart each April.', 'tripcosmos-agents' ) ); ?>
				<label class="tc-cx-full"><span><?php esc_html_e( 'Address', 'tripcosmos-agents' ); ?></span><textarea name="address" rows="2"><?php echo esc_textarea( $bs['address'] ); ?></textarea></label>
			</div>
		</section>

		<section class="tc-cx-card" style="margin-top:12px;">
			<h3><?php esc_html_e( 'GST', 'tripcosmos-agents' ); ?></h3>
			<label class="tc-cx-toggle"><input type="checkbox" name="gst_enabled" value="1" <?php checked( $bs['gst_enabled'], 1 ); ?> /> <?php esc_html_e( 'Add GST to new quotes by default', 'tripcosmos-agents' ); ?></label>
			<div class="tc-cx-form" style="margin-top:10px;">
				<?php $field( 'gstin', 'GSTIN', $bs['gstin'], 'text', __( 'With a GSTIN, paid bookings get a "Tax Invoice"; without one, a "Payment Receipt".', 'tripcosmos-agents' ) ); ?>
				<?php $field( 'state', __( 'State', 'tripcosmos-agents' ), $bs['state'] ); ?>
				<?php $field( 'gst_rate', __( 'Default GST %', 'tripcosmos-agents' ), $bs['gst_rate'], 'number', '', 'step="0.5" min="0" max="28"' ); ?>
				<?php $field( 'sac', __( 'SAC code', 'tripcosmos-agents' ), $bs['sac'], 'text', __( 'Confirm the rate and SAC for your services with your CA.', 'tripcosmos-agents' ) ); ?>
			</div>
			<label class="tc-cx-toggle"><input type="checkbox" name="gst_inclusive" value="1" <?php checked( $bs['gst_inclusive'], 1 ); ?> /> <?php esc_html_e( 'Prices you type already include GST', 'tripcosmos-agents' ); ?></label>
			<label class="tc-cx-toggle"><input type="checkbox" name="gst_split" value="1" <?php checked( $bs['gst_split'], 1 ); ?> /> <?php esc_html_e( 'Show as CGST + SGST (same-state customers). Untick for IGST.', 'tripcosmos-agents' ); ?></label>
		</section>

		<section class="tc-cx-card" style="margin-top:12px;">
			<h3><?php esc_html_e( 'Quote defaults', 'tripcosmos-agents' ); ?></h3>
			<div class="tc-cx-form">
				<label><span><?php esc_html_e( 'Advance to confirm', 'tripcosmos-agents' ); ?></span>
					<span class="tc-cx-row"><input type="number" name="advance_value" min="0" step="1" value="<?php echo esc_attr( $bs['advance_value'] ); ?>" style="width:120px;" />
					<select name="advance_type" style="width:auto;"><option value="fixed" <?php selected( $bs['advance_type'], 'fixed' ); ?>>₹ <?php esc_html_e( 'fixed', 'tripcosmos-agents' ); ?></option><option value="percent" <?php selected( $bs['advance_type'], 'percent' ); ?>>% <?php esc_html_e( 'of total', 'tripcosmos-agents' ); ?></option></select></span>
				</label>
				<?php $field( 'valid_days', __( 'Quote valid for (days)', 'tripcosmos-agents' ), $bs['valid_days'], 'number', '', 'min="1"' ); ?>
				<label class="tc-cx-full"><span><?php esc_html_e( 'Terms shown on every quote', 'tripcosmos-agents' ); ?></span><textarea name="terms" rows="4"><?php echo esc_textarea( $bs['terms'] ); ?></textarea></label>
			</div>
			<label class="tc-cx-toggle"><input type="checkbox" name="receipt_whatsapp" value="1" <?php checked( ! isset( $bs['receipt_whatsapp'] ) || ! empty( $bs['receipt_whatsapp'] ) ); ?> /> <?php esc_html_e( 'Send the customer a WhatsApp receipt when a payment is received', 'tripcosmos-agents' ); ?></label>
		</section>
		<p><button class="tc-cx-btn tc-cx-btn-primary"><?php esc_html_e( 'Save billing settings', 'tripcosmos-agents' ); ?></button></p>
	</form>
</div>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ TEAM */
if ( 'team' === $view && $is_mgr ) :
	$mode      = (string) get_option( 'tc_agents_crm_assign_mode', 'none' );
	$alerts    = (array) get_option( 'tc_agents_crm_alerts', array( 'assigned' => 1, 'task_due' => 1, 'sla' => 1, 'reply' => 1, 'digest' => 1 ) );
	$since30   = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 30 * DAY_IN_SECONDS );
	$unassigned = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_contacts WHERE owner_user_id = 0 AND stage NOT IN ('won','completed','lost')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$first_resp = "SELECT contact_id, MIN(created_at) first_at FROM $t_act WHERE user_id > 0 GROUP BY contact_id";
	?>
	<?php if ( ! $team ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'No team members yet.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $unassigned ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( _n( '%d open lead has no owner.', '%d open leads have no owner.', $unassigned, 'tripcosmos-agents' ), $unassigned ) ); ?> <a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'view' => 'list', 'filter' => 'unassigned' ) ) ); ?>"><?php esc_html_e( 'Assign them', 'tripcosmos-agents' ); ?></a></p></div>
	<?php endif; ?>
	<form method="post">
		<?php $tc_nonce(); ?>
		<input type="hidden" name="tc_contacts_action" value="save_team" />
		<div class="tc-cx-card" style="margin:12px 0;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:10px;">
				<h3 style="margin:0;"><?php esc_html_e( 'Team members', 'tripcosmos-agents' ); ?></h3>
				<div style="display:flex; gap:8px;">
					<button type="button" class="button button-primary" id="tc-open-invite-modal" style="background:#10b981; border-color:#059669; font-weight:700;">
						⚡ 1-Tap Invite via WhatsApp
					</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( '+ Manual WP User', 'tripcosmos-agents' ); ?></a>
				</div>
			</div>
			<p class="tc-cx-muted"><?php esc_html_e( 'Add a user with the role "TripCosmos Sales Agent" (works own + unassigned leads) or "TripCosmos Sales Manager" (sees everything, assigns, imports, merges). They only see the CRM screens, not site settings.', 'tripcosmos-agents' ); ?></p>
			<div style="overflow-x:auto;">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Member', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'Role', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( '🤖 Paired AI Agent', 'tripcosmos-agents' ); ?></th>
					<th title="<?php esc_attr_e( 'Receives new leads automatically', 'tripcosmos-agents' ); ?>"><?php esc_html_e( 'Auto-assign', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'Away', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'WhatsApp for alerts', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'Open', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'Overdue', 'tripcosmos-agents' ); ?></th>
					<th><?php esc_html_e( 'Won (30d)', 'tripcosmos-agents' ); ?></th>
					<th title="<?php esc_attr_e( 'Average time from lead arrival to the first action by a person, leads from the last 30 days', 'tripcosmos-agents' ); ?>"><?php esc_html_e( '1st response', 'tripcosmos-agents' ); ?></th>
					<th style="min-width:130px;"><?php esc_html_e( '1-Tap Login / App', 'tripcosmos-agents' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( $team as $u ) :
					$uid   = (int) $u->ID;
					$stats = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT
								(SELECT COUNT(*) FROM $t_contacts WHERE owner_user_id = %d AND stage NOT IN ('won','completed','lost')) open_n,
								(SELECT COUNT(*) FROM $t_act a INNER JOIN $t_contacts c ON c.id = a.contact_id WHERE c.owner_user_id = %d AND a.type = 'task' AND a.done_at IS NULL AND a.due_at < %s) overdue_n,
								(SELECT COUNT(*) FROM $t_act a INNER JOIN $t_contacts c ON c.id = a.contact_id WHERE c.owner_user_id = %d AND a.type = 'stage_change' AND a.meta LIKE %s AND a.created_at >= %s) won_n,
								(SELECT COALESCE(SUM(c.deal_value),0) FROM $t_contacts c WHERE c.owner_user_id = %d AND c.stage IN ('won','completed') AND c.updated_at >= %s) won_v,
								(SELECT AVG(TIMESTAMPDIFF(MINUTE, c.created_at, f.first_at)) FROM $t_contacts c INNER JOIN ($first_resp) f ON f.contact_id = c.id WHERE c.owner_user_id = %d AND c.created_at >= %s) resp_min", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$uid, $uid, $now, $uid, '%"to":"won"%', $since30, $uid, $since30, $uid, $since30
						),
						ARRAY_A
					);
					$is_admin = in_array( 'administrator', (array) $u->roles, true );
					$resp     = null !== $stats['resp_min'] ? (int) round( (float) $stats['resp_min'] ) : null;
					?>
					<tr>
						<td><strong><?php echo esc_html( $u->display_name ); ?></strong><br /><small class="tc-cx-muted"><?php echo esc_html( $u->user_email ); ?></small></td>
						<td>
							<?php if ( $is_admin ) : ?>
								<?php esc_html_e( 'Administrator', 'tripcosmos-agents' ); ?>
							<?php else : ?>
								<select name="member[<?php echo $uid; ?>][role]" style="width:auto;">
									<option value="<?php echo esc_attr( TC_CRM_Access::ROLE_AGENT ); ?>" <?php selected( in_array( TC_CRM_Access::ROLE_AGENT, (array) $u->roles, true ) ); ?>><?php esc_html_e( 'Sales Agent', 'tripcosmos-agents' ); ?></option>
									<option value="<?php echo esc_attr( TC_CRM_Access::ROLE_MANAGER ); ?>" <?php selected( in_array( TC_CRM_Access::ROLE_MANAGER, (array) $u->roles, true ) ); ?>><?php esc_html_e( 'Sales Manager', 'tripcosmos-agents' ); ?></option>
								</select>
							<?php endif; ?>
							<?php if ( user_can( $u, TC_CRM_Access::CAP_MANAGE ) ) : $pref = (string) get_user_meta( $uid, 'tc_crm_manager_alerts', true ); ?>
								<br /><label style="font-size:12px;"><input type="checkbox" name="member[<?php echo $uid; ?>][manager_alerts]" value="1" <?php checked( '' === $pref ? in_array( TC_CRM_Access::ROLE_MANAGER, (array) $u->roles, true ) : '1' === $pref ); ?> /> <?php esc_html_e( 'Manager alerts', 'tripcosmos-agents' ); ?></label>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" name="member[<?php echo $uid; ?>][ai_agent]" value="<?php echo esc_attr( (string) ( get_user_meta( $uid, 'tc_paired_ai_name', true ) ?: 'Aditi AI' ) ); ?>" placeholder="e.g. Aditi AI" style="width:110px; font-weight:600; color:#0f766e; background:#f0fdfa; border:1px solid #99f6e4; border-radius:6px; padding:3px 8px;" title="Dedicated AI Co-Pilot assigned to this sales specialist" />
						</td>
						<td><input type="checkbox" name="member[<?php echo $uid; ?>][rotation]" value="1" <?php checked( TC_CRM_Access::in_rotation( $u ) ); ?> /></td>
						<td><input type="checkbox" name="member[<?php echo $uid; ?>][away]" value="1" <?php checked( TC_CRM_Access::is_away( $uid ) ); ?> /></td>
						<td><input type="tel" name="member[<?php echo $uid; ?>][whatsapp]" value="<?php echo esc_attr( (string) get_user_meta( $uid, 'tc_crm_whatsapp', true ) ); ?>" placeholder="+91…" style="min-width:140px;" /></td>
						<td><a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'view' => 'list', 'owner' => $uid ) ) ); ?>"><?php echo (int) $stats['open_n']; ?></a></td>
						<td style="<?php echo (int) $stats['overdue_n'] ? 'color:#b91c1c;font-weight:600;' : ''; ?>"><?php echo (int) $stats['overdue_n']; ?></td>
						<td><?php echo (int) $stats['won_n']; ?><?php echo (float) $stats['won_v'] > 0 ? '<br /><small>₹' . esc_html( number_format_i18n( (float) $stats['won_v'] ) ) . '</small>' : ''; ?></td>
						<td><?php echo null === $resp ? '—' : esc_html( $resp < 60 ? sprintf( __( '%d min', 'tripcosmos-agents' ), $resp ) : sprintf( __( '%.1f h', 'tripcosmos-agents' ), $resp / 60 ) ); ?></td>
						<td>
							<div style="display:flex; gap:6px; align-items:center;">
								<button type="button" class="button button-small tc-send-magic-wa-btn" data-user-id="<?php echo $uid; ?>" data-user-name="<?php echo esc_attr( $u->display_name ); ?>" style="color:#059669; border-color:#10b981; font-weight:600;" title="Send 1-Tap Magic Login link to Agent on WhatsApp">
									📲 Invite WA
								</button>
								<button type="button" class="button button-small tc-show-agent-qr-btn" data-user-id="<?php echo $uid; ?>" data-user-name="<?php echo esc_attr( $u->display_name ); ?>" title="View App Pairing QR code">
									📱 QR
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>

		<div class="tc-cx-card" style="margin:12px 0; max-width:900px;">
			<h3><?php esc_html_e( 'Lead assignment', 'tripcosmos-agents' ); ?></h3>
			<p><label><input type="radio" name="assign_mode" value="none" <?php checked( $mode, 'none' ); ?> /> <?php esc_html_e( 'Manual: new leads stay unassigned and managers are alerted', 'tripcosmos-agents' ); ?></label></p>
			<p><label><input type="radio" name="assign_mode" value="round_robin" <?php checked( $mode, 'round_robin' ); ?> /> <?php esc_html_e( 'Round-robin: take turns among members with Auto-assign on who are not Away', 'tripcosmos-agents' ); ?></label></p>
			<p><label><input type="radio" name="assign_mode" value="least_loaded" <?php checked( $mode, 'least_loaded' ); ?> /> <?php esc_html_e( 'Least busy: give each new lead to the member with the fewest open leads', 'tripcosmos-agents' ); ?></label></p>
			<p><label><input type="checkbox" name="agents_see_unassigned" value="1" <?php checked( TC_CRM_Access::agents_see_unassigned() ); ?> /> <?php esc_html_e( 'Agents can see and claim unassigned leads', 'tripcosmos-agents' ); ?></label></p>
		</div>

		<div class="tc-cx-card" style="margin:12px 0; max-width:900px;">
			<h3><?php esc_html_e( 'Alerts', 'tripcosmos-agents' ); ?></h3>
			<p class="tc-cx-muted"><?php esc_html_e( 'Sent by email, and on WhatsApp to members who have a WhatsApp number above (not while Away).', 'tripcosmos-agents' ); ?></p>
			<?php
			foreach ( array(
				'assigned' => __( 'Lead assigned to me (and "new unassigned lead" to managers)', 'tripcosmos-agents' ),
				'task_due' => __( 'Follow-up due', 'tripcosmos-agents' ),
				'reply'    => __( 'My lead replied on WhatsApp (max once per 30 min per lead)', 'tripcosmos-agents' ),
				'sla'      => __( 'New lead not answered in time (owner + managers)', 'tripcosmos-agents' ),
				'digest'   => __( 'Morning digest at 09:00 (today\'s and overdue follow-ups)', 'tripcosmos-agents' ),
			) as $k => $label ) :
				?>
				<p><label><input type="checkbox" name="alerts[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $alerts[ $k ] ) ); ?> /> <?php echo esc_html( $label ); ?></label></p>
			<?php endforeach; ?>
			<p><label><?php esc_html_e( 'First-response target (minutes)', 'tripcosmos-agents' ); ?> <input type="number" name="sla_minutes" min="0" max="1440" value="<?php echo esc_attr( (int) get_option( 'tc_agents_crm_sla_minutes', 15 ) ); ?>" style="width:90px;" /></label> <span class="tc-cx-muted"><?php esc_html_e( '0 turns the SLA alert off.', 'tripcosmos-agents' ); ?></span></p>
		</div>
		<button class="tc-cx-btn tc-cx-btn-primary"><?php esc_html_e( 'Save team settings', 'tripcosmos-agents' ); ?></button>
	</form>
</div>

<!-- Modal 1: 1-Tap Invite Sales Agent via WhatsApp -->
<div id="tc-invite-agent-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:99999; backdrop-filter:blur(4px); align-items:center; justify-content:center;">
	<div style="background:#ffffff; border-radius:18px; max-width:500px; width:90%; padding:28px; box-shadow:0 20px 40px rgba(0,0,0,0.3); position:relative;">
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
			<h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
				<span>⚡</span> 1-Tap Invite Sales Agent via WhatsApp
			</h3>
			<button type="button" class="button-link" onclick="document.getElementById('tc-invite-agent-modal').style.display='none';" style="font-size:22px; text-decoration:none; color:#64748b;">&times;</button>
		</div>
		<p style="font-size:13px; color:#64748b; margin-bottom:18px; line-height:1.4;">
			Registers the agent into WordPress, issues an API token, and dispatches a 1-tap magic login link directly to their WhatsApp phone number.
		</p>
		<form id="tc-invite-agent-form">
			<div style="margin-bottom:14px;">
				<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Agent Full Name *</label>
				<input type="text" id="tc-inv-name" class="regular-text" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1;" placeholder="e.g. Aditi Sharma" required />
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Email Address * (for account & notifications)</label>
				<input type="email" id="tc-inv-email" class="regular-text" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1;" placeholder="e.g. aditi@tripcosmos.co" required />
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">WhatsApp Number (Optional, add now or later for telephony/SIM)</label>
				<input type="tel" id="tc-inv-phone" class="regular-text" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1;" placeholder="+91 93361 16210 (Optional)" />
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">🤖 Paired AI Sales Co-Pilot</label>
				<input type="text" id="tc-inv-ai" class="regular-text" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; font-weight:600; color:#0f766e; background:#f0fdfa;" value="Aditi AI" placeholder="e.g. Aditi AI" />
			</div>
			<div style="margin-bottom:18px;">
				<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">CRM Role</label>
				<select id="tc-inv-role" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1;">
					<option value="tc_sales_agent">TripCosmos Sales Agent (Own + Unassigned leads)</option>
					<option value="tc_sales_manager">TripCosmos Sales Manager (All leads + team oversight)</option>
				</select>
			</div>
			<div id="tc-inv-status" style="display:none; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-bottom:14px; line-height:1.4;"></div>
			<div style="display:flex; justify-content:flex-end; gap:10px;">
				<button type="button" class="button" onclick="document.getElementById('tc-invite-agent-modal').style.display='none';">Cancel</button>
				<button type="submit" id="tc-inv-submit-btn" class="button button-primary" style="background:#10b981; border-color:#059669; font-weight:700;">
					🚀 Create Agent & Pair AI Co-Pilot
				</button>
			</div>
		</form>
	</div>
</div>

<!-- Modal 2: Agent App Pairing QR Modal -->
<div id="tc-agent-qr-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:99999; backdrop-filter:blur(4px); align-items:center; justify-content:center;">
	<div style="background:#ffffff; border-radius:18px; max-width:440px; width:90%; padding:26px; box-shadow:0 20px 40px rgba(0,0,0,0.3); text-align:center; position:relative;">
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
			<h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;" id="tc-qr-modal-title">Agent App Pairing QR</h3>
			<button type="button" class="button-link" onclick="document.getElementById('tc-agent-qr-modal').style.display='none';" style="font-size:22px; text-decoration:none; color:#64748b;">&times;</button>
		</div>
		<p style="font-size:12.5px; color:#64748b; margin-bottom:14px;">
			Scan this code inside the <strong>X-Agents Mobile App</strong> to pair this phone directly to this agent's CRM desk.
		</p>
		<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:16px; display:inline-block; margin-bottom:16px;">
			<img id="tc-qr-modal-img" src="" alt="App Pairing QR" style="width:200px; height:200px; display:block; border-radius:8px;" />
		</div>
		<div style="margin-bottom:14px; text-align:left;">
			<label style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Magic Login Link:</label>
			<div style="display:flex; gap:6px; margin-top:4px;">
				<input type="text" id="tc-qr-modal-magic-url" readonly style="flex:1; font-size:11.5px; padding:4px 8px; border-radius:6px; border:1px solid #cbd5e1; background:#f1f5f9;" />
				<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('tc-qr-modal-magic-url').value); alert('Copied magic login link!');">Copy</button>
			</div>
		</div>
		<div style="margin-bottom:18px; text-align:left;">
			<label style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Mobile Pairing Key:</label>
			<div style="display:flex; gap:6px; margin-top:4px;">
				<input type="text" id="tc-qr-modal-token" readonly style="flex:1; font-size:11.5px; padding:4px 8px; border-radius:6px; border:1px solid #cbd5e1; background:#f1f5f9; font-family:monospace;" />
				<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('tc-qr-modal-token').value); alert('Copied pairing key!');">Copy</button>
			</div>
		</div>
		<button type="button" class="button" onclick="document.getElementById('tc-agent-qr-modal').style.display='none';">Close</button>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var invModal = document.getElementById('tc-invite-agent-modal');
	var qrModal = document.getElementById('tc-agent-qr-modal');
	var openInvBtn = document.getElementById('tc-open-invite-modal');
	var invForm = document.getElementById('tc-invite-agent-form');
	var invStatus = document.getElementById('tc-inv-status');
	var invSubmitBtn = document.getElementById('tc-inv-submit-btn');

	if (openInvBtn && invModal) {
		openInvBtn.addEventListener('click', function() {
			invModal.style.display = 'flex';
			if (invStatus) invStatus.style.display = 'none';
		});
	}

	if (invForm) {
		invForm.addEventListener('submit', function(e) {
			e.preventDefault();
			var name = document.getElementById('tc-inv-name').value.trim();
			var email = document.getElementById('tc-inv-email').value.trim();
			var phone = document.getElementById('tc-inv-phone').value.trim();
			var role = document.getElementById('tc-inv-role').value;
			var pairedAi = document.getElementById('tc-inv-ai') ? document.getElementById('tc-inv-ai').value.trim() : 'Aditi AI';

			if (!name || (!email && !phone)) return;

			invSubmitBtn.disabled = true;
			invSubmitBtn.textContent = '⏳ Creating Account...';
			invStatus.style.display = 'block';
			invStatus.style.background = '#eff6ff';
			invStatus.style.color = '#1e3a8a';
			invStatus.textContent = 'Creating agent workspace and pairing AI Co-Pilot...';

			var data = new FormData();
			data.append('action', 'tc_invite_agent');
			data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');
			data.append('agent_name', name);
			data.append('agent_email', email);
			data.append('agent_phone', phone);
			data.append('agent_role', role);
			data.append('paired_ai', pairedAi);

			fetch(ajaxurl, { method: 'POST', body: data })
			.then(function(res) { return res.json(); })
			.then(function(res) {
				invSubmitBtn.disabled = false;
				invSubmitBtn.textContent = '🚀 Create Agent & Pair AI Co-Pilot';
				if (res.success && res.data) {
					invStatus.style.background = '#ecfdf5';
					invStatus.style.color = '#065f46';
					var waText = res.data.wa_sent ? '✓ WhatsApp Invite Sent Successfully!' : '⚠️ Account created, WhatsApp queued/check Evolution.';
					invStatus.innerHTML = '<strong>' + waText + '</strong><br />Magic Login URL generated. Refreshing team table...';
					setTimeout(function() { window.location.reload(); }, 2000);
				} else {
					invStatus.style.background = '#fef2f2';
					invStatus.style.color = '#991b1b';
					invStatus.textContent = (res.data && res.data.message) ? res.data.message : 'Error inviting agent.';
				}
			})
			.catch(function(err) {
				invSubmitBtn.disabled = false;
				invSubmitBtn.textContent = '🚀 Send WhatsApp Invite';
				invStatus.style.background = '#fef2f2';
				invStatus.style.color = '#991b1b';
				invStatus.textContent = 'Network error: ' + err.message;
			});
		});
	}

	// Resend WA invite button
	document.querySelectorAll('.tc-send-magic-wa-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var uid = this.getAttribute('data-user-id');
			var uname = this.getAttribute('data-user-name');
			if (!confirm('Send 1-Tap Magic Login WhatsApp invite to ' + uname + '?')) return;

			var oldText = this.textContent;
			this.textContent = '⏳ Sending...';
			this.disabled = true;

			var data = new FormData();
			data.append('action', 'tc_resend_agent_invite');
			data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');
			data.append('user_id', uid);

			var self = this;
			fetch(ajaxurl, { method: 'POST', body: data })
			.then(function(res) { return res.json(); })
			.then(function(res) {
				self.textContent = oldText;
				self.disabled = false;
				if (res.success && res.data) {
					alert(res.data.wa_sent ? '✓ WhatsApp Magic Link dispatched to ' + uname + '!' : 'Notice: Magic link created. Note: ' + (res.data.wa_note || ''));
				} else {
					alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Could not send WhatsApp invite.'));
				}
			})
			.catch(function(err) {
				self.textContent = oldText;
				self.disabled = false;
				alert('Network error: ' + err.message);
			});
		});
	});

	// Show Agent QR button
	document.querySelectorAll('.tc-show-agent-qr-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var uid = this.getAttribute('data-user-id');
			var uname = this.getAttribute('data-user-name');

			var data = new FormData();
			data.append('action', 'tc_get_agent_pairing_qr');
			data.append('nonce', '<?php echo wp_create_nonce( 'tc_agents_admin_nonce' ); ?>');
			data.append('user_id', uid);

			fetch(ajaxurl, { method: 'POST', body: data })
			.then(function(res) { return res.json(); })
			.then(function(res) {
				if (res.success && res.data) {
					document.getElementById('tc-qr-modal-title').textContent = res.data.name + ' — App Pairing';
					document.getElementById('tc-qr-modal-img').src = res.data.qr_image_url;
					document.getElementById('tc-qr-modal-magic-url').value = res.data.magic_url;
					document.getElementById('tc-qr-modal-token').value = res.data.mobile_token;
					qrModal.style.display = 'flex';
				} else {
					alert('Error loading pairing QR.');
				}
			})
			.catch(function(err) {
				alert('Network error: ' + err.message);
			});
		});
	});
});
</script>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ LIST */
$per_page = 50;
$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$f_stage  = sanitize_key( $_GET['stage'] ?? '' );
$f_owner  = isset( $_GET['owner'] ) && '' !== $_GET['owner'] ? absint( $_GET['owner'] ) : null;
$f_filter = sanitize_key( $_GET['filter'] ?? '' );
$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

$where = array( TC_CRM_Access::scope_sql() );
if ( 'mine' === $view ) {
	$where[] = $wpdb->prepare( 'owner_user_id = %d', $me );
}
if ( isset( $stages[ $f_stage ] ) ) {
	$where[] = $wpdb->prepare( 'stage = %s', $f_stage );
}
if ( null !== $f_owner ) {
	$where[] = $wpdb->prepare( 'owner_user_id = %d', $f_owner );
}
if ( '' !== $search ) {
	$like    = '%' . $wpdb->esc_like( $search ) . '%';
	$digits  = preg_replace( '/\D/', '', $search );
	$where[] = strlen( $digits ) >= 6
		? $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR phone_e164 LIKE %s)', $like, $like, $like, '%' . $wpdb->esc_like( substr( $digits, -10 ) ) . '%' )
		: $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR tags LIKE %s)', $like, $like, $like, $like );
}
switch ( $f_filter ) {
	case 'overdue':
		$where[] = $wpdb->prepare( 'next_follow_up_at IS NOT NULL AND next_follow_up_at < %s', $now );
		break;
	case 'unassigned':
		$where[] = "owner_user_id = 0 AND stage NOT IN ('won','completed','lost')";
		break;
	case 'sync_error':
		$where[] = "sync_status = 'error'";
		break;
	case 'opted_out':
		$where[] = '(whatsapp_opt_out = 1 OR dnd = 1)';
		break;
	case 'stale':
		$where[] = $wpdb->prepare( "stage NOT IN ('won','completed','lost') AND COALESCE(last_contacted_at, updated_at) < %s", gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 7 * DAY_IN_SECONDS ) );
		break;
}
$where_sql = implode( ' AND ', $where );
$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_contacts WHERE $where_sql" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_contacts WHERE $where_sql ORDER BY updated_at DESC LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
	<form method="get" style="display:flex; gap:8px; flex-wrap:wrap; margin:10px 0;">
		<input type="hidden" name="page" value="<?php echo esc_attr( TC_Contacts_Admin::PAGE ); ?>" />
		<input type="hidden" name="view" value="<?php echo esc_attr( 'mine' === $view ? 'mine' : 'list' ); ?>" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email, phone or tag', 'tripcosmos-agents' ); ?>" style="min-width:240px;" />
		<select name="stage"><option value=""><?php esc_html_e( 'All stages', 'tripcosmos-agents' ); ?></option><?php foreach ( $stages as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_stage, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<?php if ( $see_all && 'mine' !== $view ) : ?>
			<select name="owner">
				<option value=""><?php esc_html_e( 'All owners', 'tripcosmos-agents' ); ?></option>
				<option value="0" <?php selected( $f_owner, 0 ); ?>><?php esc_html_e( 'Unassigned', 'tripcosmos-agents' ); ?></option>
				<?php foreach ( $team as $u ) : ?><option value="<?php echo (int) $u->ID; ?>" <?php selected( $f_owner, (int) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option><?php endforeach; ?>
			</select>
		<?php endif; ?>
		<select name="filter">
			<option value=""><?php esc_html_e( 'Any status', 'tripcosmos-agents' ); ?></option>
			<option value="overdue" <?php selected( $f_filter, 'overdue' ); ?>><?php esc_html_e( 'Follow-up overdue', 'tripcosmos-agents' ); ?></option>
			<option value="stale" <?php selected( $f_filter, 'stale' ); ?>><?php esc_html_e( 'Open, no contact in 7 days', 'tripcosmos-agents' ); ?></option>
			<option value="unassigned" <?php selected( $f_filter, 'unassigned' ); ?>><?php esc_html_e( 'Open & unassigned', 'tripcosmos-agents' ); ?></option>
			<option value="opted_out" <?php selected( $f_filter, 'opted_out' ); ?>><?php esc_html_e( 'Opted out / DND', 'tripcosmos-agents' ); ?></option>
			<option value="sync_error" <?php selected( $f_filter, 'sync_error' ); ?>><?php esc_html_e( 'FluentCRM sync error', 'tripcosmos-agents' ); ?></option>
		</select>
		<button class="button"><?php esc_html_e( 'Filter', 'tripcosmos-agents' ); ?></button>
		<span style="align-self:center; color:#64748b;"><?php echo esc_html( sprintf( _n( '%s contact', '%s contacts', $total, 'tripcosmos-agents' ), number_format_i18n( $total ) ) ); ?></span>
	</form>

	<form method="post" id="tc-bulk-form">
	<?php if ( $is_mgr && $rows ) : ?>
		<?php $tc_nonce(); ?>
		<input type="hidden" name="tc_contacts_action" value="bulk" />
		<div class="tc-cx-row" style="margin-bottom:8px;">
			<select name="bulk_op" style="width:auto;" onchange="document.querySelectorAll('.tc-bulk-arg').forEach(function(e){e.style.display=e.dataset.op===this.value?'':'none';}.bind(this));">
				<option value=""><?php esc_html_e( 'Bulk action…', 'tripcosmos-agents' ); ?></option>
				<option value="assign"><?php esc_html_e( 'Assign to', 'tripcosmos-agents' ); ?></option>
				<option value="stage"><?php esc_html_e( 'Move to stage', 'tripcosmos-agents' ); ?></option>
				<option value="tag"><?php esc_html_e( 'Add tag', 'tripcosmos-agents' ); ?></option>
			</select>
			<span class="tc-bulk-arg" data-op="assign" style="display:none;"><?php $tc_owner_select( 'bulk_owner', 0 ); ?></span>
			<span class="tc-bulk-arg" data-op="stage" style="display:none;"><select name="bulk_stage" style="width:auto;"><?php foreach ( $stages as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></span>
			<span class="tc-bulk-arg" data-op="tag" style="display:none;"><input type="text" name="bulk_tag" placeholder="dev-deepawali-2026" style="width:200px;" /></span>
			<button class="button"><?php esc_html_e( 'Apply to selected', 'tripcosmos-agents' ); ?></button>
		</div>
	<?php endif; ?>
	<table class="widefat striped">
		<thead><tr>
			<?php if ( $is_mgr ) : ?><td class="check-column" style="padding-left:8px;"><input type="checkbox" onclick="document.querySelectorAll('#tc-bulk-form input[name=&quot;ids[]&quot;]').forEach(function(b){b.checked=this.checked;}.bind(this));" /></td><?php endif; ?>
			<th><?php esc_html_e( 'Contact', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'Stage', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'Owner', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'Source', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'Next follow-up', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'Last contacted', 'tripcosmos-agents' ); ?></th>
			<th><?php esc_html_e( 'CRM', 'tripcosmos-agents' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<?php if ( $is_mgr ) : ?><th class="check-column" style="padding-left:8px;"><input type="checkbox" name="ids[]" value="<?php echo (int) $r['id']; ?>" /></th><?php endif; ?>
				<td>
					<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $r['id'] ) ) ); ?>"><strong><?php echo esc_html( $r['name'] ?: __( 'Traveler', 'tripcosmos-agents' ) ); ?></strong></a>
					<?php if ( $r['dnd'] || $r['whatsapp_opt_out'] ) : ?><span class="tc-badge tc-badge-danger" title="<?php esc_attr_e( 'Opted out / do not contact', 'tripcosmos-agents' ); ?>">🔒</span><?php endif; ?>
					<br /><small><?php echo esc_html( trim( $r['phone'] . ' ' . $r['email'] ) ); ?></small>
				</td>
				<td><?php echo esc_html( $stages[ $r['stage'] ] ?? $r['stage'] ); ?><?php echo (float) $r['deal_value'] > 0 ? '<br /><small>₹' . esc_html( number_format_i18n( (float) $r['deal_value'] ) ) . '</small>' : ''; ?></td>
				<td><?php echo $r['owner'] ? esc_html( $r['owner'] ) : '<span style="color:#b45309;">' . esc_html__( 'Unassigned', 'tripcosmos-agents' ) . '</span>'; ?></td>
				<td><?php echo esc_html( $r['utm_source'] ?: $r['source_channel'] ); ?></td>
				<td style="<?php echo ( $r['next_follow_up_at'] && $r['next_follow_up_at'] < $now ) ? 'color:#b91c1c;font-weight:600;' : ''; ?>"><?php echo $tc_dt( $r['next_follow_up_at'] ); // phpcs:ignore ?></td>
				<td><?php echo $tc_dt( $r['last_contacted_at'] ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( 'synced' === $r['sync_status'] ? '✓' : ( 'error' === $r['sync_status'] ? '⚠' : ( 'skipped_no_email' === $r['sync_status'] ? __( 'local', 'tripcosmos-agents' ) : '—' ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="8"><?php echo 'mine' === $view ? esc_html__( 'No contacts assigned to you yet. Claim unassigned leads from "Mine + Unassigned".', 'tripcosmos-agents' ) : esc_html__( 'No contacts match.', 'tripcosmos-agents' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
	</form>
	<?php
	$pages = (int) ceil( $total / $per_page );
	if ( $pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			)
		) . '</div></div>';
	}
	?>
</div>
