<?php
/**
 * Admin Follow-Up Sequences & Drip Campaigns View.
 * Inspired by VM Sales OS & VMAI Sequences Modules.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_seq     = $wpdb->prefix . 'tc_agent_sequences';
$table_enroll  = $wpdb->prefix . 'tc_agent_sequence_enrollments';
$table_contact = $wpdb->prefix . 'tc_agent_contacts';

$edit_id = absint( $_GET['edit'] ?? 0 );

// Load sequences
$sequences = $wpdb->get_results( "SELECT * FROM $table_seq ORDER BY id DESC", ARRAY_A ) ?: array();

// If editing
$edit_seq = null;
$edit_steps = array();
if ( $edit_id > 0 ) {
	$edit_seq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_seq WHERE id = %d", $edit_id ), ARRAY_A );
	if ( $edit_seq ) {
		$edit_steps = json_decode( $edit_seq['steps_json'] ?? '[]', true ) ?: array();
	}
}

// Load active enrollments
$enrollments = $wpdb->get_results(
	"SELECT e.*, s.name as seq_name, s.channel as seq_channel, c.name as contact_name, c.phone as contact_phone, c.email as contact_email 
	 FROM $table_enroll e 
	 LEFT JOIN $table_seq s ON e.sequence_id = s.id 
	 LEFT JOIN $table_contact c ON e.contact_id = c.id 
	 ORDER BY e.next_run_at ASC LIMIT 50",
	ARRAY_A
) ?: array();

// Trigger events map
$trigger_events = array(
	'inquiry_abandoned'  => __( 'Inquiry Abandoned (No message for 2 hours)', 'tripcosmos-agents' ),
	'proposal_sent'      => __( 'Custom Proposal / Itinerary Sent', 'tripcosmos-agents' ),
	'no_reply_24h'       => __( 'Traveler Inactive for 24 Hours', 'tripcosmos-agents' ),
	'post_trip_feedback' => __( 'Post-Trip Review & Feedback Request', 'tripcosmos-agents' ),
);
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-controls-repeat"></span> <?php esc_html_e( 'Automated Follow-Up Sequences', 'tripcosmos-agents' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Nurture cold leads and re-engage abandoned tour & cab inquiries automatically via WhatsApp and multi-channel drips.', 'tripcosmos-agents' ); ?></p>
		</div>
		<div class="tc-header-actions">
			<a href="#seq-builder-card" class="button button-primary" onclick="document.getElementById('seq-builder-card').scrollIntoView({behavior: 'smooth'});">
				<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'Create Sequence', 'tripcosmos-agents' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sequence saved successfully.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Sequence deleted.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['enrolled'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact manually enrolled into drip sequence.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>

	<div class="tc-two-col-layout">
		<!-- Left: Existing Sequences & Enrollments -->
		<div>
			<!-- Sequences Overview -->
			<div class="tc-card">
				<div class="tc-card-header">
					<h3><?php esc_html_e( 'Configured Follow-Up Sequences', 'tripcosmos-agents' ); ?> (<?php echo count( $sequences ); ?>)</h3>
				</div>

				<?php if ( empty( $sequences ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No sequences configured yet. Use the builder on the right to set up automated follow-ups.', 'tripcosmos-agents' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Sequence Name', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Channel', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Trigger Event', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Steps', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Status', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'tripcosmos-agents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sequences as $s ) :
								$steps = json_decode( $s['steps_json'] ?? '[]', true ) ?: array();
								$active_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_enroll WHERE sequence_id = %d AND status = 'active'", $s['id'] ) );
							?>
								<tr>
									<td>
										<strong><?php echo esc_html( $s['name'] ); ?></strong><br>
										<small style="color: #64748b;"><?php echo esc_html( $active_count ); ?> <?php esc_html_e( 'active enrollments', 'tripcosmos-agents' ); ?></small>
									</td>
									<td><span class="tc-channel-badge tc-channel-<?php echo esc_attr( $s['channel'] ); ?>"><?php echo esc_html( ucfirst( $s['channel'] ) ); ?></span></td>
									<td><small><code><?php echo esc_html( $s['trigger_event'] ); ?></code></small></td>
									<td><span class="tc-tag"><?php echo count( $steps ); ?> <?php esc_html_e( 'steps', 'tripcosmos-agents' ); ?></span></td>
									<td>
										<?php if ( ! empty( $s['is_active'] ) ) : ?>
											<span class="tc-badge tc-badge-success" style="padding: 2px 6px; font-size: 10px;"><?php esc_html_e( 'Active', 'tripcosmos-agents' ); ?></span>
										<?php else : ?>
											<span class="tc-badge tc-badge-danger" style="padding: 2px 6px; font-size: 10px;"><?php esc_html_e( 'Paused', 'tripcosmos-agents' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-sequences&edit=' . $s['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'tripcosmos-agents' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this sequence?', 'tripcosmos-agents' ); ?>');">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="delete_sequence" />
											<input type="hidden" name="sequence_id" value="<?php echo esc_attr( $s['id'] ); ?>" />
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'tripcosmos-agents' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Live Enrollments Queue -->
			<div class="tc-card">
				<div class="tc-card-header">
					<h3><?php esc_html_e( 'Active Follow-Up Enrollments Queue', 'tripcosmos-agents' ); ?></h3>
				</div>

				<?php if ( empty( $enrollments ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;"><?php esc_html_e( 'No active enrollments currently in queue.', 'tripcosmos-agents' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Traveler Contact', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Sequence', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Step', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Next Run', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Status', 'tripcosmos-agents' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'tripcosmos-agents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $enrollments as $en ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $en['contact_name'] ?: __( 'Traveler', 'tripcosmos-agents' ) ); ?></strong><br>
										<small style="color: #64748b;"><?php echo esc_html( $en['contact_phone'] ?: $en['contact_email'] ); ?></small>
									</td>
									<td><?php echo esc_html( $en['seq_name'] ); ?></td>
									<td><span class="tc-tag"><?php esc_html_e( 'Step', 'tripcosmos-agents' ); ?> <?php echo esc_html( $en['current_step'] + 1 ); ?></span></td>
									<td>
										<small><?php echo esc_html( $en['next_run_at'] ); ?></small>
									</td>
									<td>
										<span class="tc-status-pill tc-status-<?php echo esc_attr( $en['status'] ); ?>"><?php echo esc_html( ucfirst( $en['status'] ) ); ?></span>
									</td>
									<td>
										<?php if ( 'active' === $en['status'] ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;">
												<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
												<input type="hidden" name="tc_agents_action" value="cancel_enrollment" />
												<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $en['id'] ); ?>" />
												<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Cancel follow-ups for this traveler?', 'tripcosmos-agents' ); ?>');"><?php esc_html_e( 'Cancel', 'tripcosmos-agents' ); ?></button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right: Create / Edit Sequence Builder -->
		<div id="seq-builder-card">
			<div class="tc-card" style="position: sticky; top: 32px;">
				<div class="tc-card-header">
					<h3><?php echo $edit_seq ? esc_html__( 'Edit Follow-Up Sequence', 'tripcosmos-agents' ) : esc_html__( 'Create Follow-Up Sequence', 'tripcosmos-agents' ); ?></h3>
					<?php if ( $edit_seq ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-sequences' ) ); ?>" class="button button-small"><?php esc_html_e( 'Cancel', 'tripcosmos-agents' ); ?></a>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="tc-seq-form">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="save_sequence" />
					<input type="hidden" name="sequence_id" value="<?php echo esc_attr( $edit_seq['id'] ?? 0 ); ?>" />

					<p>
						<label for="tc_seq_name"><strong><?php esc_html_e( 'Sequence Name', 'tripcosmos-agents' ); ?></strong></label><br>
						<input type="text" id="tc_seq_name" name="name" value="<?php echo esc_attr( $edit_seq['name'] ?? '' ); ?>" class="regular-text" required style="width: 100%;" placeholder="e.g. 24-Hour Tour & Cab Inquiry Nurture" />
					</p>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
						<p>
							<label for="tc_seq_channel"><strong><?php esc_html_e( 'Channel', 'tripcosmos-agents' ); ?></strong></label><br>
							<select id="tc_seq_channel" name="channel" style="width: 100%;">
								<option value="whatsapp" <?php selected( $edit_seq['channel'] ?? 'whatsapp', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'tripcosmos-agents' ); ?></option>
								<option value="web" <?php selected( $edit_seq['channel'] ?? '', 'web' ); ?>><?php esc_html_e( 'Web Widget', 'tripcosmos-agents' ); ?></option>
							</select>
						</p>

						<p>
							<label for="tc_seq_trigger"><strong><?php esc_html_e( 'Trigger Event', 'tripcosmos-agents' ); ?></strong></label><br>
							<select id="tc_seq_trigger" name="trigger_event" style="width: 100%;">
								<?php foreach ( $trigger_events as $tkey => $tlabel ) : ?>
									<option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $edit_seq['trigger_event'] ?? 'inquiry_abandoned', $tkey ); ?>><?php echo esc_html( $tlabel ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					</div>

					<p>
						<label>
							<input type="checkbox" name="is_active" value="1" <?php checked( $edit_seq ? (bool) $edit_seq['is_active'] : true ); ?> />
							<strong><?php esc_html_e( 'Active (Dispatcher Enabled)', 'tripcosmos-agents' ); ?></strong>
						</label>
					</p>

					<div class="tc-card-header" style="margin-top: 18px; margin-bottom: 8px;">
						<h4 style="margin:0;"><?php esc_html_e( 'Drip Steps Builder', 'tripcosmos-agents' ); ?></h4>
						<button type="button" class="button button-small" onclick="addStepRow()"><?php esc_html_e( '+ Add Step', 'tripcosmos-agents' ); ?></button>
					</div>

					<div id="tc-steps-container">
						<?php
						$steps_to_render = ! empty( $edit_steps ) ? $edit_steps : array(
							array( 'delay_hours' => 2, 'message' => "Namaste {name}! 🙏 Our travel specialists at TripCosmos noticed you were planning a trip to {destination}. Would you like us to share our day-wise itinerary, cab fare options (Dzire/Innova Crysta), and Kashi Vishwanath darshan guidelines?" ),
							array( 'delay_hours' => 24, 'message' => "Namaste {name}! Quick follow-up from TripCosmos Varanasi desk. Are your travel dates confirmed? We can pre-book your hotel near the ghats and reserve your evening Ganga Aarti boat ride." ),
						);

						foreach ( $steps_to_render as $idx => $step ) :
						?>
							<div class="tc-step-row" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
									<strong><?php esc_html_e( 'Step', 'tripcosmos-agents' ); ?> <span class="step-num"><?php echo $idx + 1; ?></span></strong>
									<button type="button" class="button-link button-link-delete" onclick="this.closest('.tc-step-row').remove(); reindexSteps();" style="font-size: 11px;">Remove</button>
								</div>
								<div style="margin-bottom: 6px;">
									<label style="font-size: 11px; font-weight:600;"><?php esc_html_e( 'Delay after previous event (Hours):', 'tripcosmos-agents' ); ?></label>
									<input type="number" name="step_delays[]" value="<?php echo esc_attr( $step['delay_hours'] ?? 2 ); ?>" min="1" max="720" style="width: 80px;" required />
								</div>
								<div>
									<label style="font-size: 11px; font-weight:600;"><?php esc_html_e( 'Message Template ({name}, {deal_value}):', 'tripcosmos-agents' ); ?></label>
									<textarea name="step_messages[]" rows="3" style="width: 100%; font-size: 12px;" required><?php echo esc_textarea( $step['message'] ?? '' ); ?></textarea>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<p style="margin-top: 16px;">
						<button type="submit" class="button button-primary" style="width: 100%;">
							<?php echo $edit_seq ? esc_html__( 'Update Sequence', 'tripcosmos-agents' ) : esc_html__( 'Save & Activate Sequence', 'tripcosmos-agents' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
function addStepRow() {
	const container = document.getElementById('tc-steps-container');
	const count = container.querySelectorAll('.tc-step-row').length + 1;
	const div = document.createElement('div');
	div.className = 'tc-step-row';
	div.style = 'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px;';
	div.innerHTML = `
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
			<strong>Step <span class="step-num">${count}</span></strong>
			<button type="button" class="button-link button-link-delete" onclick="this.closest('.tc-step-row').remove(); reindexSteps();" style="font-size: 11px;">Remove</button>
		</div>
		<div style="margin-bottom: 6px;">
			<label style="font-size: 11px; font-weight:600;">Delay after previous event (Hours):</label>
			<input type="number" name="step_delays[]" value="24" min="1" max="720" style="width: 80px;" required />
		</div>
		<div>
			<label style="font-size: 11px; font-weight:600;">Message Template ({name}, {deal_value}):</label>
			<textarea name="step_messages[]" rows="3" style="width: 100%; font-size: 12px;" required placeholder="Hi {name}, ..."></textarea>
		</div>
	`;
	container.appendChild(div);
}

function reindexSteps() {
	const rows = document.querySelectorAll('.tc-step-row');
	rows.forEach((r, i) => {
		const span = r.querySelector('.step-num');
		if (span) span.textContent = i + 1;
	});
}
</script>
