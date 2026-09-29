<?php
/**
 * Admin Follow-Up Sequences & Drip Campaigns View - Modern Celestial Design.
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
	'inquiry_abandoned'    => __( 'Abandoned Chat Inquiry (Speed-to-Lead)', 'tripcosmos-agents' ),
	'fluentform_submitted'  => __( 'Fluent Forms Lead Submitted (Instant Quote)', 'tripcosmos-agents' ),
	'pre_arrival'           => __( 'Booking Confirmed / Pre-Arrival Concierge', 'tripcosmos-agents' ),
	'post_trip_feedback'   => __( 'Post-Trip Google Review & Referral Engine', 'tripcosmos-agents' ),
	'b2b_outreach'         => __( 'B2B Travel Partner Outreach & White-Label DMC', 'tripcosmos-agents' ),
	'proposal_sent'        => __( 'Custom Proposal / Itinerary Sent', 'tripcosmos-agents' ),
	'no_reply_24h'         => __( 'Traveler Inactive for 24 Hours', 'tripcosmos-agents' ),
);

$all_contacts = $wpdb->get_results( "SELECT id, name, phone, email FROM $table_contact ORDER BY id DESC LIMIT 100", ARRAY_A ) ?: array();
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-seq-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1260px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-seq-hero {
	background: linear-gradient(135deg, #0a0f1d 0%, #111e38 60%, #1e1b4b 100%);
	border-radius: 20px;
	padding: 28px 36px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(10, 15, 29, 0.25);
	margin-bottom: 28px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	border: 1px solid rgba(245, 158, 11, 0.2);
}
.tc-seq-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-seq-hero p {
	margin: 0;
	color: #94a3b8;
	font-size: 14px;
	max-width: 650px;
	line-height: 1.5;
}
.tc-badge-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #0f172a;
	font-weight: 800;
	font-size: 11px;
	padding: 4px 10px;
	border-radius: 20px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.tc-card-lux {
	background: #ffffff;
	border-radius: 16px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
	padding: 24px;
	margin-bottom: 24px;
	transition: all 0.2s ease;
}
.tc-card-lux:hover {
	box-shadow: 0 8px 26px rgba(0, 0, 0, 0.07);
	border-color: #cbd5e1;
}
.tc-card-header-flex {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
}
.tc-card-title {
	font-size: 17px;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tc-grid-layout {
	display: grid;
	grid-template-columns: 1.25fr 0.95fr;
	gap: 24px;
}
@media (max-width: 1080px) {
	.tc-grid-layout { grid-template-columns: 1fr; }
}

.tc-table-lux {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
	margin-top: 8px;
}
.tc-table-lux th {
	background: #f8fafc;
	color: #475569;
	font-weight: 700;
	text-align: left;
	padding: 10px 14px;
	border-bottom: 1.5px solid #e2e8f0;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.tc-table-lux td {
	padding: 12px 14px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.tc-table-lux tr:hover td {
	background: #f8fafc;
}

.tc-badge-pill {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 700;
}
.tc-badge-wa { background: #dcfce7; color: #15803d; }
.tc-badge-em { background: #e0f2fe; color: #0369a1; }
.tc-badge-active { background: #dcfce7; color: #15803d; }
.tc-badge-paused { background: #fee2e2; color: #b91c1c; }

.tc-input-lux, select.tc-input-lux, textarea.tc-input-lux {
	width: 100%;
	padding: 9px 12px;
	border: 1.5px solid #cbd5e1;
	border-radius: 8px;
	font-size: 13px;
	color: #1e293b;
	background: #f8fafc;
	outline: none;
	transition: all 0.2s ease;
}
.tc-input-lux:focus, select.tc-input-lux:focus, textarea.tc-input-lux:focus {
	border-color: #f59e0b;
	background: #ffffff;
	box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

.tc-btn-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #ffffff !important;
	border: none;
	font-weight: 700;
	padding: 10px 20px;
	border-radius: 8px;
	cursor: pointer;
	box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
	text-decoration: none;
}
.tc-btn-gold:hover {
	transform: translateY(-1px);
	box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
	color: #ffffff;
}
.tc-btn-secondary {
	background: #f1f5f9;
	color: #334155;
	border: 1px solid #cbd5e1;
	font-weight: 600;
	padding: 8px 14px;
	border-radius: 8px;
	cursor: pointer;
	font-size: 12.5px;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	text-decoration: none;
}
.tc-btn-secondary:hover {
	background: #e2e8f0;
	color: #0f172a;
}
</style>

<div class="wrap tc-seq-wrap">
	<!-- Hero -->
	<div class="tc-seq-hero">
		<div>
			<h1>
				<span>🔁</span>
				Automated Follow-Up Sequences
				<span class="tc-badge-gold">Smart Nurture</span>
			</h1>
			<p>Nurture captured inquiries, recover abandoned website chats, and dispatch pre-arrival darshan &amp; cab preparation guides via automated multi-step WhatsApp &amp; Email drips.</p>
		</div>
		<div style="display: flex; gap: 10px; align-items: center;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 0;">
				<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
				<input type="hidden" name="tc_agents_action" value="run_sequences_now" />
				<button type="submit" class="tc-btn-secondary" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff;">
					<span>⚡</span> Run Due Steps Now
				</button>
			</form>
			<a href="#seq-builder-card" class="tc-btn-gold" onclick="document.getElementById('seq-builder-card').scrollIntoView({behavior: 'smooth'});">
				<span>+</span> Create Sequence
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065f46; font-weight: 600;">
			✓ Sequence configuration updated and active.
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #92400e; font-weight: 600;">
			⚠️ Sequence removed.
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['enrolled'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065f46; font-weight: 600;">
			✓ Contact manually enrolled into follow-up sequence.
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['ran'] ) ) : ?>
		<div style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #0369a1; font-weight: 600;">
			ℹ️ Dispatched <?php echo absint( $_GET['ran'] ); ?> due sequence steps successfully.
		</div>
	<?php endif; ?>

	<div class="tc-grid-layout">
		<!-- Left: Configured Sequences & Active Enrollments -->
		<div>
			<!-- Configured Sequences -->
			<div class="tc-card-lux">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>📋</span> Configured Drip Sequences (<?php echo count( $sequences ); ?>)
					</div>
				</div>

				<?php if ( empty( $sequences ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 24px;">No sequences configured yet. Use the builder on the right.</p>
				<?php else : ?>
					<table class="tc-table-lux">
						<thead>
							<tr>
								<th>Sequence Name</th>
								<th>Channel</th>
								<th>Trigger</th>
								<th>Steps</th>
								<th>Status</th>
								<th>Actions</th>
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
										<small style="color: #64748b;"><?php echo esc_html( $active_count ); ?> active enrollments</small>
									</td>
									<td>
										<span class="tc-badge-pill <?php echo 'whatsapp' === $s['channel'] ? 'tc-badge-wa' : 'tc-badge-em'; ?>">
											<?php echo esc_html( ucfirst( $s['channel'] ) ); ?>
										</span>
									</td>
									<td><small><code><?php echo esc_html( $s['trigger_event'] ); ?></code></small></td>
									<td><span style="font-weight: 600;"><?php echo count( $steps ); ?> steps</span></td>
									<td>
										<span class="tc-badge-pill <?php echo ! empty( $s['is_active'] ) ? 'tc-badge-active' : 'tc-badge-paused'; ?>">
											<?php echo ! empty( $s['is_active'] ) ? 'Active' : 'Paused'; ?>
										</span>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-sequences&edit=' . $s['id'] ) ); ?>" class="button button-small">Edit</a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Delete this sequence?');">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="delete_sequence" />
											<input type="hidden" name="sequence_id" value="<?php echo esc_attr( $s['id'] ); ?>" />
											<button type="submit" class="button button-small button-link-delete">Del</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Live Enrollments Queue -->
			<div class="tc-card-lux">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>⏳</span> Active Enrollments Queue (<?php echo count( $enrollments ); ?>)
					</div>
				</div>

				<?php if ( empty( $enrollments ) ) : ?>
					<p style="text-align: center; color: #64748b; padding: 20px;">No active contacts waiting in the queue.</p>
				<?php else : ?>
					<table class="tc-table-lux">
						<thead>
							<tr>
								<th>Traveler</th>
								<th>Sequence</th>
								<th>Step</th>
								<th>Next Run</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $enrollments as $en ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $en['contact_name'] ?: 'Traveler' ); ?></strong><br>
										<small style="color: #64748b;"><?php echo esc_html( $en['contact_phone'] ?: $en['contact_email'] ); ?></small>
									</td>
									<td><?php echo esc_html( $en['seq_name'] ); ?></td>
									<td><span style="font-weight: 700; color: #0284c7;">Step <?php echo esc_html( $en['current_step'] + 1 ); ?></span></td>
									<td><small><?php echo esc_html( $en['next_run_at'] ); ?></small></td>
									<td>
										<?php if ( 'active' === $en['status'] ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;">
												<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
												<input type="hidden" name="tc_agents_action" value="cancel_enrollment" />
												<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $en['id'] ); ?>" />
												<button type="submit" class="button button-small" onclick="return confirm('Cancel follow-ups for this traveler?');">Cancel</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Quick Enroll Lead Form -->
			<div class="tc-card-lux" style="border-left: 4px solid #f59e0b;">
				<div class="tc-card-title" style="margin-bottom: 12px;">
					<span>🎯</span> Quick Enroll Lead into Sequence
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="manual_enroll_lead" />
					<input type="hidden" name="redirect_page" value="tc-agents-sequences" />
					<div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: flex-end;">
						<div>
							<label style="font-size: 11px; font-weight: 700; color: #64748b; display: block; margin-bottom: 4px;">SELECT TRAVELER / CONTACT</label>
							<select name="contact_id" required class="tc-input-lux">
								<option value="">-- Choose Contact --</option>
								<?php foreach ( $all_contacts as $ac ) : ?>
									<option value="<?php echo esc_attr( $ac['id'] ); ?>">
										<?php echo esc_html( $ac['name'] ?: 'Traveler #' . $ac['id'] ); ?> (<?php echo esc_html( $ac['phone'] ?: $ac['email'] ?: 'No Phone/Email' ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label style="font-size: 11px; font-weight: 700; color: #64748b; display: block; margin-bottom: 4px;">SELECT DRIP SEQUENCE</label>
							<select name="sequence_id" required class="tc-input-lux">
								<option value="">-- Choose Sequence --</option>
								<?php foreach ( $sequences as $sq ) : ?>
									<option value="<?php echo esc_attr( $sq['id'] ); ?>">
										<?php echo esc_html( $sq['name'] ); ?> (<?php echo esc_html( ucfirst( $sq['channel'] ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<button type="submit" class="tc-btn-gold" style="padding: 10px 16px;">Enroll Lead</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<!-- Right: Create / Edit Sequence Builder -->
		<div id="seq-builder-card">
			<div class="tc-card-lux" style="position: sticky; top: 32px; border-left: 5px solid #2563eb;">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>🛠️</span>
						<?php echo $edit_seq ? 'Edit Follow-Up Sequence' : 'Create Follow-Up Sequence'; ?>
					</div>
					<?php if ( $edit_seq ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-sequences' ) ); ?>" class="tc-btn-secondary" style="padding: 4px 10px; font-size: 11px;">Cancel Edit</a>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="tc-seq-form">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="save_sequence" />
					<input type="hidden" name="sequence_id" value="<?php echo esc_attr( $edit_seq['id'] ?? 0 ); ?>" />

					<div style="margin-bottom: 14px;">
						<label for="tc_seq_name" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Sequence Name</label>
						<input type="text" id="tc_seq_name" name="name" value="<?php echo esc_attr( $edit_seq['name'] ?? '' ); ?>" class="tc-input-lux" required placeholder="e.g. 24-Hour Tour & Cab Inquiry Nurture" />
					</div>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
						<div>
							<label for="tc_seq_channel" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Channel</label>
							<select id="tc_seq_channel" name="channel" class="tc-input-lux">
								<option value="whatsapp" <?php selected( $edit_seq['channel'] ?? 'whatsapp', 'whatsapp' ); ?>>WhatsApp (Evolution)</option>
								<option value="email" <?php selected( $edit_seq['channel'] ?? '', 'email' ); ?>>Email (Brevo / SMTP)</option>
								<option value="web" <?php selected( $edit_seq['channel'] ?? '', 'web' ); ?>>Web Widget</option>
							</select>
						</div>

						<div>
							<label for="tc_seq_trigger" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Trigger Event</label>
							<select id="tc_seq_trigger" name="trigger_event" class="tc-input-lux">
								<?php foreach ( $trigger_events as $tkey => $tlabel ) : ?>
									<option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $edit_seq['trigger_event'] ?? 'inquiry_abandoned', $tkey ); ?>><?php echo esc_html( $tlabel ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div style="margin-bottom: 16px;">
						<label style="font-weight: 600; font-size: 13px; color: #1e293b; cursor: pointer;">
							<input type="checkbox" name="is_active" value="1" <?php checked( $edit_seq ? (bool) $edit_seq['is_active'] : true ); ?> />
							Active (Dispatcher Enabled)
						</label>
					</div>

					<div class="tc-card-header-flex" style="border-top: 1px dashed #cbd5e1; padding-top: 14px; margin-bottom: 10px;">
						<div style="font-weight: 700; font-size: 13px; color: #0f172a;">Drip Steps Timeline</div>
						<button type="button" class="tc-btn-secondary" onclick="addStepRow()" style="padding: 4px 10px; font-size: 11px;">+ Add Step</button>
					</div>

					<div id="tc-steps-container">
						<?php
						$steps_to_render = ! empty( $edit_steps ) ? $edit_steps : array(
							array( 'delay_hours' => 0.25, 'subject' => 'Quick question regarding your tour inquiry - TripCosmos', 'message' => "Namaste {name}! 🙏 Our travel specialists at TripCosmos noticed you were planning a trip to {destination}. Would you like us to share our day-wise itinerary, cab fare options (Dzire/Innova Crysta), and Kashi Vishwanath darshan guidelines?" ),
							array( 'delay_hours' => 24, 'subject' => 'Varanasi Darshan & Cab Planning Follow-up', 'message' => "Namaste {name}! Quick follow-up from TripCosmos Varanasi desk. Are your travel dates confirmed? We can pre-book your hotel near the ghats and reserve your evening Ganga Aarti boat ride." ),
						);

						foreach ( $steps_to_render as $idx => $step ) :
						?>
							<div class="tc-step-row" style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
									<strong style="color: #0f172a; font-size: 12.5px;">Step <span class="step-num"><?php echo $idx + 1; ?></span></strong>
									<button type="button" class="button-link button-link-delete" onclick="this.closest('.tc-step-row').remove(); reindexSteps();" style="font-size: 11px;">Remove</button>
								</div>
								<div style="display: grid; grid-template-columns: 110px 1fr; gap: 8px; margin-bottom: 6px;">
									<div>
										<label style="font-size: 11px; font-weight: 700; color: #64748b; display: block;">Delay (Hours):</label>
										<input type="number" name="step_delays[]" value="<?php echo esc_attr( $step['delay_hours'] ?? 2 ); ?>" step="0.25" min="0.05" max="720" class="tc-input-lux" required title="0.25 = 15 mins, 1 = 1 hour, 24 = 1 day" />
									</div>
									<div>
										<label style="font-size: 11px; font-weight: 700; color: #64748b; display: block;">Subject (for Email):</label>
										<input type="text" name="step_subjects[]" value="<?php echo esc_attr( $step['subject'] ?? '' ); ?>" class="tc-input-lux" placeholder="e.g. Your Custom Itinerary & Cab Quote" />
									</div>
								</div>
								<div>
									<label style="font-size: 11px; font-weight: 700; color: #64748b; display: block; margin-bottom: 2px;">Template ({name}, {deal_value}, {destination}):</label>
									<textarea name="step_messages[]" rows="3" class="tc-input-lux" required><?php echo esc_textarea( $step['message'] ?? '' ); ?></textarea>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div style="margin-top: 18px;">
						<button type="submit" class="tc-btn-gold" style="width: 100%; justify-content: center;">
							<?php echo $edit_seq ? 'Update Sequence' : 'Save & Activate Sequence'; ?>
						</button>
					</div>
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
	div.style = 'background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 12px;';
	div.innerHTML = `
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
			<strong style="color: #0f172a; font-size: 12.5px;">Step <span class="step-num">${count}</span></strong>
			<button type="button" class="button-link button-link-delete" onclick="this.closest('.tc-step-row').remove(); reindexSteps();" style="font-size: 11px;">Remove</button>
		</div>
		<div style="display: grid; grid-template-columns: 110px 1fr; gap: 8px; margin-bottom: 6px;">
			<div>
				<label style="font-size: 11px; font-weight:700; color:#64748b; display:block;">Delay (Hours):</label>
				<input type="number" name="step_delays[]" value="24" step="0.25" min="0.05" max="720" class="tc-input-lux" required title="0.25 = 15 mins, 1 = 1 hour, 24 = 1 day" />
			</div>
			<div>
				<label style="font-size: 11px; font-weight:700; color:#64748b; display:block;">Subject (for Email):</label>
				<input type="text" name="step_subjects[]" value="" class="tc-input-lux" placeholder="e.g. Travel Tip: Evening Ganga Aarti" />
			</div>
		</div>
		<div>
			<label style="font-size: 11px; font-weight:700; color:#64748b; display:block; margin-bottom: 2px;">Template ({name}, {deal_value}, {destination}):</label>
			<textarea name="step_messages[]" rows="3" class="tc-input-lux" required placeholder="Namaste {name}, ..."></textarea>
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
