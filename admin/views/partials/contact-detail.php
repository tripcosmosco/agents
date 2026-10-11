<?php
/**
 * Contact detail page. Included from views/contacts.php with $contact_id, $stages, $tc_nonce, $tc_dt, $t_book, $fluent_on, $now in scope.
 * Closes the page's .wrap div.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$c = TC_Contact_Service::get( $contact_id );
if ( ! $c ) {
	echo '<div class="tc-cx"><p>' . esc_html__( 'Contact not found. It may have been merged or deleted.', 'tripcosmos-agents' ) . ' <a href="' . esc_url( TC_Contacts_Admin::url() ) . '">' . esc_html__( 'Back to contacts', 'tripcosmos-agents' ) . '</a></p></div></div>';
	return;
}

if ( ! TC_CRM_Access::can_edit( $c ) ) {
	echo '<div class="tc-cx"><p>' . esc_html__( 'This contact is assigned to another team member.', 'tripcosmos-agents' ) . ' <a href="' . esc_url( TC_Contacts_Admin::url() ) . '">' . esc_html__( 'Back to contacts', 'tripcosmos-agents' ) . '</a></p></div></div>';
	return;
}
$is_mgr     = TC_CRM_Access::can_manage();
$meta       = json_decode( (string) $c['meta_data'], true ) ?: array();
$activities = TC_Contact_Service::activities( $contact_id, 200 );
$bookings   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_book WHERE contact_id = %d ORDER BY id DESC", $contact_id ), ARRAY_A ) ?: array();
$t_msg      = $wpdb->prefix . 'tc_agent_messages';
$convos     = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT cv.id, cv.channel, cv.status, cv.started_at, cv.last_message_at,
			(SELECT COUNT(*) FROM $t_msg m WHERE m.conversation_id = cv.id AND m.role IN ('user','assistant')) AS msgs,
			(SELECT m2.content FROM $t_msg m2 WHERE m2.conversation_id = cv.id AND m2.role = 'user' ORDER BY m2.id DESC LIMIT 1) AS last_user
		FROM {$wpdb->prefix}tc_agent_conversations cv WHERE cv.contact_id = %d ORDER BY cv.last_message_at DESC LIMIT 20",
		$contact_id
	),
	ARRAY_A
) ?: array();
$open_tasks = array_values( array_filter( $activities, static function ( $a ) { return 'task' === $a['type'] && empty( $a['done_at'] ); } ) );
usort( $open_tasks, static function ( $x, $y ) { return strcmp( (string) $x['due_at'], (string) $y['due_at'] ); } );
$issues     = TC_Contact_Service::quality_issues( $c );
$wa_digits  = preg_replace( '/\D/', '', (string) ( $c['phone_e164'] ?: '' ) );
$can_wa     = $wa_digits && ! $c['whatsapp_opt_out'] && ! $c['dnd'];
$name       = $c['name'] ?: __( 'Traveler', 'tripcosmos-agents' );
$initials   = strtoupper( implode( '', array_map( static function ( $w ) { return mb_substr( $w, 0, 1 ); }, array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) ) ) );
$stage_keys = array_keys( $stages );
$open_keys  = array( 'inquiry', 'qualified', 'proposal', 'negotiation', 'won', 'completed' );
$cur_index  = array_search( $c['stage'], $open_keys, true );
$ago        = static function ( $v ) {
	return $v ? sprintf( __( '%s ago', 'tripcosmos-agents' ), human_time_diff( strtotime( get_gmt_from_date( $v ) ), time() ) ) : '—';
};

// Timeline = activities + conversations (+ creation, for contacts created before the timeline existed).
$feed = array();
foreach ( $activities as $a ) {
	if ( 'task' === $a['type'] && empty( $a['done_at'] ) ) {
		continue; // shown in the tasks card
	}
	$act_meta = ! empty( $a['meta'] ) ? ( is_array( $a['meta'] ) ? $a['meta'] : json_decode( (string) $a['meta'], true ) ) : array();
	$feed[]   = array(
		'at'   => $a['created_at'],
		'type' => $a['type'],
		'html' => wp_kses_post( nl2br( (string) $a['content'] ) ),
		'user' => (int) $a['user_id'],
		'done' => $a['done_at'],
		'meta' => $act_meta,
	);
}
foreach ( $convos as $cv ) {
	$feed[] = array(
		'at'   => $cv['started_at'],
		'type' => 'conversation',
		'html' => sprintf(
			'<a href="%1$s">%2$s</a> · %3$s%4$s',
			esc_url( admin_url( 'admin.php?page=tc-agents-conversations&view=' . (int) $cv['id'] ) ),
			esc_html( sprintf( __( '%s conversation', 'tripcosmos-agents' ), ucfirst( $cv['channel'] ) ) ),
			esc_html( sprintf( _n( '%d message', '%d messages', (int) $cv['msgs'], 'tripcosmos-agents' ), (int) $cv['msgs'] ) ),
			$cv['last_user'] ? '<div class="tc-cx-quote">“' . esc_html( wp_trim_words( wp_strip_all_tags( $cv['last_user'] ), 24 ) ) . '”</div>' : ''
		),
		'user' => 0,
		'done' => null,
	);
}
if ( ! in_array( 'system', wp_list_pluck( $activities, 'type' ), true ) ) {
	$feed[] = array( 'at' => $c['created_at'], 'type' => 'system', 'html' => esc_html( sprintf( __( 'Contact created from %s', 'tripcosmos-agents' ), $c['source_channel'] ) ), 'user' => 0, 'done' => null );
}
usort( $feed, static function ( $x, $y ) { return strcmp( (string) $y['at'], (string) $x['at'] ); } );

$type_meta = array(
	'note'         => array( '📝', __( 'Note', 'tripcosmos-agents' ) ),
	'task'         => array( '✅', __( 'Task done', 'tripcosmos-agents' ) ),
	'stage_change' => array( '➡️', __( 'Stage', 'tripcosmos-agents' ) ),
	'call'         => array( '📞', __( 'Call', 'tripcosmos-agents' ) ),
	'whatsapp'     => array( '💬', __( 'WhatsApp', 'tripcosmos-agents' ) ),
	'email'        => array( '✉️', __( 'Email', 'tripcosmos-agents' ) ),
	'form'         => array( '📋', __( 'Inquiry', 'tripcosmos-agents' ) ),
	'booking'      => array( '🧾', __( 'Booking', 'tripcosmos-agents' ) ),
	'consent'      => array( '🔒', __( 'Consent', 'tripcosmos-agents' ) ),
	'sync'         => array( '🔄', __( 'Sync', 'tripcosmos-agents' ) ),
	'conversation' => array( '🗨️', __( 'Chat', 'tripcosmos-agents' ) ),
	'system'       => array( '•', __( 'System', 'tripcosmos-agents' ) ),
);

$source_label = $c['utm_source'] ? $c['utm_source'] . ( $c['utm_campaign'] ? ' / ' . $c['utm_campaign'] : '' ) : $c['source_channel'];
$tomorrow     = gmdate( 'Y-m-d\T10:00', strtotime( '+1 day', current_time( 'timestamp' ) ) );
?>
<div class="tc-cx">
	<a class="tc-cx-back" href="<?php echo esc_url( TC_Contacts_Admin::url() ); ?>">&larr; <?php esc_html_e( 'All contacts', 'tripcosmos-agents' ); ?></a>

	<!-- Header -->
	<section class="tc-cx-card tc-cx-head">
		<div class="tc-cx-id">
			<div class="tc-cx-avatar tc-stage-<?php echo esc_attr( $c['stage'] ); ?>"><?php echo esc_html( $initials ?: '?' ); ?></div>
			<div>
				<h2><?php echo esc_html( $name ); ?> <span class="tc-cx-muted">#<?php echo (int) $c['id']; ?></span></h2>
				<div class="tc-cx-pills">
					<span class="tc-cx-pill tc-stage-<?php echo esc_attr( $c['stage'] ); ?>"><?php echo esc_html( $stages[ $c['stage'] ] ?? $c['stage'] ); ?></span>
					<?php if ( $c['dnd'] ) : ?><span class="tc-cx-pill tc-cx-pill-red"><?php esc_html_e( 'Do not contact', 'tripcosmos-agents' ); ?></span><?php endif; ?>
					<?php if ( $c['whatsapp_opt_out'] ) : ?><span class="tc-cx-pill tc-cx-pill-red"><?php esc_html_e( 'WhatsApp opt-out', 'tripcosmos-agents' ); ?></span><?php endif; ?>
					<?php if ( in_array( $c['email_status'], TC_Contact_Service::EMAIL_BLOCKED_STATUSES, true ) ) : ?><span class="tc-cx-pill tc-cx-pill-red"><?php echo esc_html( sprintf( __( 'Email %s', 'tripcosmos-agents' ), $c['email_status'] ) ); ?></span><?php endif; ?>
					<?php foreach ( TC_Contact_Service::parse_tags( $c['tags'] ) as $tag ) : ?><span class="tc-cx-pill tc-cx-pill-tag"><?php echo esc_html( $tag ); ?></span><?php endforeach; ?>
				</div>
				<div class="tc-cx-muted tc-cx-line">
					<?php echo esc_html( implode( '  ·  ', array_filter( array( $c['phone_e164'] ?: $c['phone'], $c['email'], sprintf( __( 'via %s', 'tripcosmos-agents' ), $source_label ) ) ) ) ); ?>
				</div>
			</div>
		</div>
		<div class="tc-cx-actions">
			<?php if ( $can_wa ) : ?><a class="tc-cx-btn tc-cx-btn-wa" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $wa_digits . '?text=' . rawurlencode( 'Namaste ' . strtok( $name, ' ' ) . ', this is TripCosmos (Varanasi).' ) ); ?>">💬 WhatsApp</a><?php endif; ?>
			<?php if ( $wa_digits && ! $c['dnd'] ) : ?>
				<button type="button" class="tc-cx-btn tc-btn-mobile-call" onclick="tcDispatchMobileCall(<?php echo (int) $c['id']; ?>, '<?php echo esc_js( $c['phone_e164'] ?: $c['phone'] ); ?>', this)" title="<?php esc_attr_e( 'Trigger outbound call on your Jio SIM Android Companion', 'tripcosmos-agents' ); ?>">
					📱 <?php esc_html_e( 'Jio SIM Call', 'tripcosmos-agents' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $wa_digits && ! $c['dnd'] ) : ?><a class="tc-cx-btn" href="<?php echo esc_url( 'tel:+' . $wa_digits ); ?>">📞 <?php esc_html_e( 'Call', 'tripcosmos-agents' ); ?></a><?php endif; ?>
			<?php if ( TC_Contact_Service::is_real_email( $c['email'] ) && ! $c['dnd'] ) : ?><a class="tc-cx-btn" href="<?php echo esc_url( 'mailto:' . $c['email'] ); ?>">✉️ <?php esc_html_e( 'Email', 'tripcosmos-agents' ); ?></a><?php endif; ?>
		</div>
		<dl class="tc-cx-stats">
			<div><dt><?php esc_html_e( 'Deal value', 'tripcosmos-agents' ); ?></dt><dd>₹<?php echo esc_html( number_format_i18n( (float) $c['deal_value'] ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Lead score', 'tripcosmos-agents' ); ?></dt><dd><?php echo (int) $c['score']; ?><span class="tc-cx-meter"><i style="width:<?php echo (int) $c['score']; ?>%"></i></span></dd></div>
			<div><dt><?php esc_html_e( 'Owner', 'tripcosmos-agents' ); ?></dt><dd>
				<?php if ( $is_mgr ) : ?>
					<form method="post" class="tc-cx-owner">
						<?php $tc_nonce(); ?>
						<input type="hidden" name="tc_contacts_action" value="assign" />
						<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
						<?php $tc_owner_select( 'owner_user_id', (int) $c['owner_user_id'] ); ?>
						<button class="tc-cx-btn" style="min-height:30px;padding:0 10px;"><?php esc_html_e( 'Assign', 'tripcosmos-agents' ); ?></button>
					</form>
				<?php elseif ( ! (int) $c['owner_user_id'] ) : ?>
					<form method="post" class="tc-cx-inline">
						<?php $tc_nonce(); ?>
						<input type="hidden" name="tc_contacts_action" value="claim" />
						<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
						<span class="tc-cx-muted"><?php esc_html_e( 'Unassigned', 'tripcosmos-agents' ); ?></span> <button class="tc-cx-btn tc-cx-btn-primary" style="min-height:28px;padding:0 10px;"><?php esc_html_e( 'Claim', 'tripcosmos-agents' ); ?></button>
					</form>
				<?php else : ?>
					<?php echo esc_html( $c['owner'] ); ?>
				<?php endif; ?>
			</dd></div>
			<div><dt><?php esc_html_e( 'Last contacted', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( $ago( $c['last_contacted_at'] ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Next follow-up', 'tripcosmos-agents' ); ?></dt><dd class="<?php echo ( $c['next_follow_up_at'] && $c['next_follow_up_at'] < $now ) ? 'tc-cx-overdue' : ''; ?>"><?php echo $c['next_follow_up_at'] ? esc_html( mysql2date( 'j M, H:i', $c['next_follow_up_at'] ) ) : '—'; ?></dd></div>
			<div><dt><?php esc_html_e( 'Created', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( mysql2date( 'j M Y', $c['created_at'] ) ); ?></dd></div>
		</dl>
	</section>

	<!-- Stage bar -->
	<form method="post" class="tc-cx-stagebar" id="tc-stage-form">
		<?php $tc_nonce(); ?>
		<input type="hidden" name="tc_contacts_action" value="set_stage" />
		<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
		<input type="hidden" name="lost_reason" value="" />
		<?php foreach ( $open_keys as $i => $k ) : ?>
			<button type="submit" name="stage" value="<?php echo esc_attr( $k ); ?>" class="tc-cx-step <?php echo ( false !== $cur_index && $i < $cur_index ) ? 'is-done' : ''; ?> <?php echo $k === $c['stage'] ? 'is-current' : ''; ?>" <?php disabled( $k, $c['stage'] ); ?>><?php echo esc_html( $stages[ $k ] ); ?></button>
		<?php endforeach; ?>
		<button type="submit" name="stage" value="lost" class="tc-cx-step tc-cx-step-lost <?php echo 'lost' === $c['stage'] ? 'is-current' : ''; ?>" <?php disabled( 'lost', $c['stage'] ); ?>
			onclick="var r=prompt('<?php echo esc_js( __( 'Why was this lead lost? (e.g. Price too high, Booked elsewhere, Trip postponed, No response)', 'tripcosmos-agents' ) ); ?>','');if(r===null){return false;}this.form.lost_reason.value=r;">
			<?php echo esc_html( $stages['lost'] ); ?><?php echo ( 'lost' === $c['stage'] && $c['lost_reason'] ) ? ': ' . esc_html( $c['lost_reason'] ) : ''; ?>
		</button>
	</form>

	<?php if ( $issues ) : ?>
		<div class="tc-cx-issues">
			<?php foreach ( $issues as $iss ) : ?>
				<div class="tc-cx-issue tc-cx-issue-<?php echo esc_attr( $iss['level'] ); ?>">
					<?php echo esc_html( $iss['text'] ); ?>
					<?php if ( ! empty( $iss['dup_id'] ) && $is_mgr ) : ?>
						<a href="<?php echo esc_url( TC_Contacts_Admin::url( array( 'contact' => $iss['dup_id'] ) ) ); ?>"><?php esc_html_e( 'View', 'tripcosmos-agents' ); ?></a> ·
						<form method="post" class="tc-cx-inline" onsubmit="return confirm('<?php echo esc_js( sprintf( __( 'Merge contact #%d into this one? It will be deleted after its history is moved here.', 'tripcosmos-agents' ), $iss['dup_id'] ) ); ?>');">
							<?php $tc_nonce(); ?>
							<input type="hidden" name="tc_contacts_action" value="merge" />
							<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
							<input type="hidden" name="merge_id" value="<?php echo (int) $iss['dup_id']; ?>" />
							<button class="button-link"><?php esc_html_e( 'Merge into this contact', 'tripcosmos-agents' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="tc-cx-grid">
		<div class="tc-cx-col">
			<!-- Tasks -->
			<section class="tc-cx-card">
				<h3><?php esc_html_e( 'Follow-ups', 'tripcosmos-agents' ); ?> <?php if ( $open_tasks ) : ?><span class="tc-cx-count"><?php echo count( $open_tasks ); ?></span><?php endif; ?></h3>
				<?php foreach ( $open_tasks as $task ) : ?>
					<form method="post" class="tc-cx-task <?php echo $task['due_at'] < $now ? 'is-overdue' : ''; ?>">
						<?php $tc_nonce(); ?>
						<input type="hidden" name="tc_contacts_action" value="complete_task" />
						<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
						<input type="hidden" name="activity_id" value="<?php echo (int) $task['id']; ?>" />
						<button class="tc-cx-check" title="<?php esc_attr_e( 'Mark done', 'tripcosmos-agents' ); ?>" aria-label="<?php esc_attr_e( 'Mark done', 'tripcosmos-agents' ); ?>"></button>
						<span class="tc-cx-task-text"><?php echo esc_html( $task['content'] ); ?></span>
						<span class="tc-cx-task-due"><?php echo esc_html( mysql2date( 'D j M, H:i', $task['due_at'] ) ); ?><?php echo $task['due_at'] < $now ? ' · ' . esc_html__( 'overdue', 'tripcosmos-agents' ) : ''; ?></span>
					</form>
				<?php endforeach; ?>
				<?php if ( ! $open_tasks ) : ?><p class="tc-cx-muted"><?php esc_html_e( 'No follow-up scheduled. Leads with a next step convert far better. Add one below.', 'tripcosmos-agents' ); ?></p><?php endif; ?>
				<form method="post" class="tc-cx-addtask">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="add_task" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<input type="text" name="task" placeholder="<?php esc_attr_e( 'Next step, e.g. Send Innova quote for 4 pax', 'tripcosmos-agents' ); ?>" required />
					<div class="tc-cx-row">
						<input type="datetime-local" name="due_at" value="<?php echo esc_attr( $tomorrow ); ?>" required />
						<span class="tc-cx-presets">
							<?php
							$ts = current_time( 'timestamp' );
							foreach ( array(
								__( 'In 2h', 'tripcosmos-agents' )     => gmdate( 'Y-m-d\TH:i', $ts + 2 * HOUR_IN_SECONDS ),
								__( 'Tomorrow', 'tripcosmos-agents' )  => $tomorrow,
								__( '3 days', 'tripcosmos-agents' )    => gmdate( 'Y-m-d\T10:00', strtotime( '+3 days', $ts ) ),
								__( 'Next week', 'tripcosmos-agents' ) => gmdate( 'Y-m-d\T10:00', strtotime( '+7 days', $ts ) ),
							) as $label => $val ) :
								?>
								<button type="button" class="tc-cx-chip" onclick="this.form.due_at.value='<?php echo esc_js( $val ); ?>';"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</span>
					</div>
					<button class="tc-cx-btn tc-cx-btn-primary"><?php esc_html_e( 'Add follow-up', 'tripcosmos-agents' ); ?></button>
				</form>
			</section>

			<!-- Details -->
			<section class="tc-cx-card">
				<h3><?php esc_html_e( 'Details', 'tripcosmos-agents' ); ?></h3>
				<form method="post" class="tc-cx-form">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="save_contact" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<input type="hidden" name="stage" value="<?php echo esc_attr( $c['stage'] ); ?>" />
					<input type="hidden" name="lost_reason" value="<?php echo esc_attr( $c['lost_reason'] ); ?>" />
					<label class="tc-cx-full"><span><?php esc_html_e( 'Name', 'tripcosmos-agents' ); ?></span><input type="text" name="name" value="<?php echo esc_attr( $c['name'] ); ?>" /></label>
					<label><span><?php esc_html_e( 'Phone', 'tripcosmos-agents' ); ?></span><input type="tel" name="phone" value="<?php echo esc_attr( $c['phone'] ); ?>" placeholder="+91 98765 43210" /></label>
					<label><span><?php esc_html_e( 'Email', 'tripcosmos-agents' ); ?></span><input type="email" name="email" value="<?php echo esc_attr( $c['email'] ); ?>" /></label>
					<label><span><?php esc_html_e( 'Deal value (₹)', 'tripcosmos-agents' ); ?></span><input type="number" name="deal_value" step="100" min="0" value="<?php echo esc_attr( (float) $c['deal_value'] ); ?>" /></label>
					<label><span><?php esc_html_e( 'Lead score (0–100)', 'tripcosmos-agents' ); ?></span><input type="number" name="score" min="0" max="100" value="<?php echo esc_attr( $c['score'] ); ?>" /></label>
					<label class="tc-cx-full"><span><?php esc_html_e( 'Tags', 'tripcosmos-agents' ); ?></span><input type="text" name="tags" value="<?php echo esc_attr( str_replace( ',', ', ', (string) $c['tags'] ) ); ?>" placeholder="kashi-darshan, family, ayodhya" /></label>
					<input type="hidden" name="next_follow_up_at" value="<?php echo $c['next_follow_up_at'] ? esc_attr( mysql2date( 'Y-m-d\TH:i', $c['next_follow_up_at'] ) ) : ''; ?>" />
					<fieldset class="tc-cx-full tc-cx-consent">
						<legend><?php esc_html_e( 'Consent', 'tripcosmos-agents' ); ?></legend>
						<label class="tc-cx-toggle"><input type="checkbox" name="whatsapp_opt_out" value="1" <?php checked( $c['whatsapp_opt_out'], 1 ); ?> /> <?php esc_html_e( 'Opted out of WhatsApp', 'tripcosmos-agents' ); ?></label>
						<label class="tc-cx-toggle"><input type="checkbox" name="dnd" value="1" <?php checked( $c['dnd'], 1 ); ?> /> <?php esc_html_e( 'Do not contact (stops all automated messages)', 'tripcosmos-agents' ); ?></label>
						<?php if ( $c['consent_at'] ) : ?><small class="tc-cx-muted"><?php echo esc_html( sprintf( __( 'Marketing consent given %1$s via %2$s', 'tripcosmos-agents' ), mysql2date( 'j M Y', $c['consent_at'] ), $c['consent_source'] ) ); ?></small><?php endif; ?>
					</fieldset>
					<div class="tc-cx-full"><button class="tc-cx-btn tc-cx-btn-primary"><?php esc_html_e( 'Save changes', 'tripcosmos-agents' ); ?></button></div>
				</form>
			</section>

			<!-- Trip interest + source -->
			<section class="tc-cx-card">
				<h3><?php esc_html_e( 'Trip interest & source', 'tripcosmos-agents' ); ?></h3>
				<dl class="tc-cx-kv">
					<?php
					$kv = array(
						__( 'Destination', 'tripcosmos-agents' )  => $meta['destination'] ?? '',
						__( 'Travel month', 'tripcosmos-agents' ) => $meta['travel_month'] ?? '',
						__( 'Group size', 'tripcosmos-agents' )   => $meta['group_size'] ?? '',
						__( 'Requirements', 'tripcosmos-agents' ) => $meta['requirements'] ?? '',
						__( 'Channel', 'tripcosmos-agents' )      => $c['source_channel'],
						__( 'Campaign', 'tripcosmos-agents' )     => trim( implode( ' / ', array_filter( array( $c['utm_source'], $c['utm_medium'], $c['utm_campaign'] ) ) ) ),
						__( 'Referrer', 'tripcosmos-agents' )     => $c['referrer'],
						__( 'Landing page', 'tripcosmos-agents' ) => $c['landing_page'],
					);
					$shown = 0;
					foreach ( $kv as $k => $v ) :
						if ( '' === (string) ( is_scalar( $v ) ? $v : '' ) ) {
							continue;
						}
						$shown++;
						?>
						<dt><?php echo esc_html( $k ); ?></dt><dd><?php echo esc_html( (string) $v ); ?></dd>
					<?php endforeach; ?>
				</dl>
				<?php if ( $shown <= 1 ) : ?><p class="tc-cx-muted"><?php esc_html_e( 'No trip details captured yet. They fill in automatically when the traveler shares them in chat or a form.', 'tripcosmos-agents' ); ?></p><?php endif; ?>
			</section>

			
		<!-- AI Itinerary & Proposal Generator Card -->
		
		<!-- Automated Drip Sequences Widget -->
		<?php
		$t_enroll = $wpdb->prefix . 'tc_agent_sequence_enrollments';
		$t_seq    = $wpdb->prefix . 'tc_agent_sequences';
		$active_enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT e.*, s.name as seq_name, s.channel, s.steps_json FROM $t_enroll e INNER JOIN $t_seq s ON s.id = e.sequence_id WHERE e.contact_id = %d AND e.status = 'active' ORDER BY e.id DESC LIMIT 1", $contact_id ), ARRAY_A );
		$available_seqs    = $wpdb->get_results( "SELECT id, name, trigger_event, channel FROM $t_seq WHERE is_active = 1 ORDER BY id ASC", ARRAY_A ) ?: array();
		?>
		<section class="tc-seq-card">
			<div class="tc-seq-header">
				<h3 class="tc-seq-title">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
					<?php esc_html_e( 'Automated WhatsApp & Drip Cadence', 'tripcosmos-agents' ); ?>
				</h3>
				<span style="font-size:11.5px; font-weight:700; color:#4f46e5; background:#eef2ff; padding:2px 8px; border-radius:12px;">
					🤖 <?php esc_html_e( 'Stop-on-Reply Protection', 'tripcosmos-agents' ); ?>
				</span>
			</div>

			<?php if ( $active_enrollment ) :
				$steps = json_decode( (string) $active_enrollment['steps_json'], true ) ?: array();
				$step_num = (int) $active_enrollment['current_step'] + 1;
				$total_steps = count( $steps );
				?>
				<div class="tc-seq-active-box">
					<div style="display:flex; align-items:center; gap:10px;">
						<div class="tc-seq-pulse"></div>
						<div>
							<strong style="color:#15803d; font-size:13.5px;">
								<?php echo esc_html( $active_enrollment['seq_name'] ); ?>
							</strong>
							<div style="font-size:12px; color:#166534; margin-top:2px;">
								<?php echo esc_html( sprintf( __( 'Step %1$d of %2$d · Next run: %3$s', 'tripcosmos-agents' ), $step_num, $total_steps, mysql2date( 'j M, H:i', $active_enrollment['next_run_at'] ) ) ); ?>
							</div>
						</div>
					</div>
					<button type="button" class="tc-cx-btn tc-cx-btn-danger" onclick="tcCancelSequence()" style="font-size:12px; padding:0 10px; min-height:28px;">
						⏹️ <?php esc_html_e( 'Pause Cadence', 'tripcosmos-agents' ); ?>
					</button>
				</div>
			<?php else : ?>
				<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px;">
					<select id="tc-enroll-seq-select" style="max-width:320px; font-size:13px;">
						<option value=""><?php esc_html_e( '— Select a Drip Cadence to Enroll —', 'tripcosmos-agents' ); ?></option>
						<?php foreach ( $available_seqs as $as ) : ?>
							<option value="<?php echo (int) $as['id']; ?>">
								<?php echo esc_html( $as['name'] ); ?> (<?php echo esc_html( ucfirst( $as['channel'] ) ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="tc-crm-btn tc-crm-btn-primary" onclick="tcEnrollSequence()" style="height:34px; padding:0 14px; font-size:12.5px;">
						⚡ <?php esc_html_e( 'Enroll Traveler', 'tripcosmos-agents' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</section>

		<script>
		function tcEnrollSequence() {
			var sel = document.getElementById('tc-enroll-seq-select');
			if (!sel || !sel.value) {
				alert('Please select a cadence to enroll.');
				return;
			}
			var formData = new FormData();
			formData.append('action', 'tc_enroll_sequence');
			formData.append('nonce', '<?php echo wp_create_nonce( TC_Contacts_Admin::NONCE ); ?>');
			formData.append('contact_id', '<?php echo (int) $c['id']; ?>');
			formData.append('sequence_id', sel.value);

			fetch(ajaxurl, { method: 'POST', body: formData })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					alert(res.data.message || 'Enrolled successfully!');
					window.location.reload();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Error enrolling');
				}
			});
		}

		function tcCancelSequence() {
			if (!confirm('Are you sure you want to pause/stop this automated sequence for this contact?')) return;
			var formData = new FormData();
			formData.append('action', 'tc_cancel_sequence');
			formData.append('nonce', '<?php echo wp_create_nonce( TC_Contacts_Admin::NONCE ); ?>');
			formData.append('contact_id', '<?php echo (int) $c['id']; ?>');

			fetch(ajaxurl, { method: 'POST', body: formData })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					alert(res.data.message || 'Sequence paused');
					window.location.reload();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Error pausing sequence');
				}
			});
		}
		</script>

		<section class="tc-itin-card">
			<div class="tc-itin-header">
				<h3 class="tc-itin-title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
					<?php esc_html_e( 'AI Itinerary & Custom Proposal Builder', 'tripcosmos-agents' ); ?>
				</h3>
				<span style="font-size:12px; font-weight:700; color:#ea580c; background:#fff7ed; padding:3px 10px; border-radius:20px;">
					⚡ <?php esc_html_e( 'Fast CPQ Engine', 'tripcosmos-agents' ); ?>
				</span>
			</div>

			<p style="font-size:12.5px; color:#64748b; margin:0 0 12px 0;">
				<?php esc_html_e( 'Generate a branded, mobile-ready travel itinerary with VIP Darshan schedules, hotel tiers, and pricing. Generates an instant link to send via WhatsApp or email.', 'tripcosmos-agents' ); ?>
			</p>

			<!-- Quick Circuit Presets -->
			<div class="tc-itin-presets">
				<button type="button" class="tc-itin-preset-chip" onclick="tcApplyItinPreset('kashi_3d')">
					🕉️ <?php esc_html_e( 'Varanasi Spiritual (3D/2N)', 'tripcosmos-agents' ); ?>
				</button>
				<button type="button" class="tc-itin-preset-chip" onclick="tcApplyItinPreset('kashi_ayodhya_4d')">
					🚩 <?php esc_html_e( 'Kashi + Ayodhya Ram Mandir (4D/3N)', 'tripcosmos-agents' ); ?>
				</button>
				<button type="button" class="tc-itin-preset-chip" onclick="tcApplyItinPreset('complete_tirtha_5d')">
					🌊 <?php esc_html_e( 'Varanasi - Prayagraj - Ayodhya (5D/4N)', 'tripcosmos-agents' ); ?>
				</button>
				<button type="button" class="tc-itin-preset-chip" onclick="tcApplyItinPreset('buddhist_4d')">
					☸️ <?php esc_html_e( 'Sarnath & Bodh Gaya (4D/3N)', 'tripcosmos-agents' ); ?>
				</button>
			</div>

			<form id="tc-itin-builder-form" onsubmit="tcSubmitItinerary(event)">
				<div class="tc-itin-form-grid">
					<label>
						<span><?php esc_html_e( 'Destination / Package', 'tripcosmos-agents' ); ?></span>
						<input type="text" id="tc-itin-dest" name="destination" value="Varanasi - Ayodhya Spiritual Tour" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Duration', 'tripcosmos-agents' ); ?></span>
						<input type="text" id="tc-itin-duration" name="duration" value="3 Days / 2 Nights" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Dedicated Vehicle', 'tripcosmos-agents' ); ?></span>
						<select id="tc-itin-vehicle" name="vehicle">
							<option value="AC Innova Crysta (Dedicated Chauffeur)">AC Innova Crysta (Chauffeur)</option>
							<option value="AC Maruti Ertiga (Dedicated Chauffeur)">AC Ertiga (Chauffeur)</option>
							<option value="AC Sedan (Dzire / Etios)">AC Sedan (Dzire / Etios)</option>
							<option value="AC Tempo Traveller (12/17-Seater Luxury)">AC Luxury Tempo Traveller</option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Hotel Category', 'tripcosmos-agents' ); ?></span>
						<select id="tc-itin-hotel" name="hotel_tier">
							<option value="3-Star Deluxe Hotel (Near Ghats / Prime Location)">3-Star Deluxe Hotel (Near Ghats)</option>
							<option value="4-Star Heritage Haveli (Riverside View)">4-Star Heritage Haveli (Riverside)</option>
							<option value="5-Star Luxury Palace (Taj / BrijRama Palace)">5-Star Luxury Palace (Taj / BrijRama)</option>
							<option value="Comfort Homestay (Clean & Family Friendly)">Comfort Homestay (Family Friendly)</option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Pax / Travellers', 'tripcosmos-agents' ); ?></span>
						<input type="text" id="tc-itin-pax" name="pax" value="2 Adults" />
					</label>
					<label>
						<span><?php esc_html_e( 'Travel Dates', 'tripcosmos-agents' ); ?></span>
						<input type="text" id="tc-itin-dates" name="travel_date" value="Upcoming Dates (Flexible)" />
					</label>
					<label>
						<span><?php esc_html_e( 'Total Package Fare (₹)', 'tripcosmos-agents' ); ?></span>
						<input type="text" id="tc-itin-fare" name="total_fare" value="₹24,500" required />
					</label>
				</div>

				<label style="display:block; margin-bottom:4px;">
					<span style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:4px;">
						<?php esc_html_e( 'Day-by-Day Sightseeing & Darshan Plan', 'tripcosmos-agents' ); ?>
					</span>
					<textarea id="tc-itin-text" name="itinerary_text" rows="5" class="tc-itin-textarea" required>Day 1: Arrival in Varanasi. Private pickup from Airport/Railway Station by your dedicated chauffeur. Check-in to your Deluxe Hotel. Evening private hand-rowed boat ride along the sacred Ghats to witness the world-famous Grand Ganga Aarti at Dashashwamedh Ghat with reserved front-row seating.

Day 2: Early morning Subah-e-Banaras sunrise boat ride with flute music and morning rituals. Special VIP Sugam Darshan at Shri Kashi Vishwanath Jyotirlinga Temple, followed by visits to Annapurna Devi and Kaal Bhairav Mandir (Kotwal of Kashi). Post-lunch excursion to Sarnath (Dhamek Stupa, Deer Park, Archaeological Museum).

Day 3: Morning darshan at Sankat Mochan Hanuman Temple, Durga Kund, and Tulsi Manas Mandir. Explore authentic Banarasi silk weaving artisan workshops. Check-out and private chauffeur transfer to Varanasi Airport/Railway Station for your onward journey with divine blessings.</textarea>
				</label>

				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
					<button type="submit" id="tc-itin-submit-btn" class="tc-crm-btn tc-crm-btn-primary">
						🚀 <?php esc_html_e( 'Generate & Publish Proposal Link', 'tripcosmos-agents' ); ?>
					</button>
					<span id="tc-itin-loading" style="display:none; font-size:13px; color:#ea580c; font-weight:600;">
						⏳ <?php esc_html_e( 'Generating branded proposal…', 'tripcosmos-agents' ); ?>
					</span>
				</div>
			</form>

			<!-- Result Box -->
			<div id="tc-itin-result" class="tc-itin-result-banner">
				<div>
					<strong style="color:#065f46; font-size:14px; display:block;">
						✅ <?php esc_html_e( 'Itinerary Proposal Ready!', 'tripcosmos-agents' ); ?>
					</strong>
					<span id="tc-itin-result-summary" style="font-size:12.5px; color:#047857;"></span>
				</div>
				<div style="display:flex; gap:8px;">
					<a id="tc-itin-result-view" href="#" target="_blank" class="tc-cx-btn" style="background:#ffffff; color:#0f172a; font-weight:600;">
						🔗 <?php esc_html_e( 'View Proposal', 'tripcosmos-agents' ); ?>
					</a>
					<a id="tc-itin-result-wa" href="#" target="_blank" class="tc-cx-btn tc-cx-btn-wa">
						💬 <?php esc_html_e( 'Share on WhatsApp', 'tripcosmos-agents' ); ?> &rarr;
					</a>
				</div>
			</div>
		</section>

		<script>
		function tcApplyItinPreset(preset) {
			var d = document.getElementById('tc-itin-dest');
			var dur = document.getElementById('tc-itin-duration');
			var f = document.getElementById('tc-itin-fare');
			var t = document.getElementById('tc-itin-text');
			if (!d || !dur || !f || !t) return;

			if (preset === 'kashi_3d') {
				d.value = 'Varanasi Divine Kashi Yatra';
				dur.value = '3 Days / 2 Nights';
				f.value = '₹18,500';
				t.value = 'Day 1: Arrival in Varanasi. Chauffeur pickup and hotel check-in. Evening private boat ride on Dashashwamedh Ghat for the majestic Ganga Aarti with reserved front-row seating.\n\nDay 2: Early morning Subah-e-Banaras sunrise boat ride. VIP Sugam Darshan at Shri Kashi Vishwanath Jyotirlinga, Annapurna Temple, and Kaal Bhairav Mandir. Afternoon visit to sacred Sarnath (Dhamek Stupa & Deer Park).\n\nDay 3: Sankat Mochan Hanuman Temple, Durga Kund, and Tulsi Manas Mandir. Local Banarasi silk artisan workshop visit, followed by airport/railway transfer with divine memories.';
			} else if (preset === 'kashi_ayodhya_4d') {
				d.value = 'Kashi & Ayodhya Ram Mandir Sacred Circuit';
				dur.value = '4 Days / 3 Nights';
				f.value = '₹28,500';
				t.value = 'Day 1: Varanasi arrival, chauffeur pickup, check-in. Evening Ganga Aarti boat ride.\n\nDay 2: Sunrise boat ride, VIP Kashi Vishwanath Darshan & Kaal Bhairav. Drive to Ayodhya (3.5 hrs) via Purvanchal Expressway in dedicated AC Innova. Hotel check-in and evening Saryu River Aarti.\n\nDay 3: Sacred Darshan at Shri Ram Janmabhoomi Mandir, Hanuman Garhi, and Kanak Bhavan. Visit Dashrath Mahal and Ram ki Paidi.\n\nDay 4: Morning Aarti, return scenic drive to Varanasi Airport or Ayodhya Maharishi Valmiki Airport for departure.';
			} else if (preset === 'complete_tirtha_5d') {
				d.value = 'Complete Spiritual Tirtha (Varanasi - Prayagraj - Ayodhya)';
				dur.value = '5 Days / 4 Nights';
				f.value = '₹39,500';
				t.value = 'Day 1: Varanasi arrival, hotel check-in, private evening Ganga Aarti boat cruise.\n\nDay 2: VIP Kashi Vishwanath Darshan, Annapurna & Kaal Bhairav. Afternoon Sarnath tour.\n\nDay 3: Scenic morning drive to Prayagraj (2.5 hrs). Holy bath at Triveni Sangam (Ganga-Yamuna-Saraswati), Bade Hanuman Mandir, and Anand Bhavan. Proceed to Ayodhya for overnight stay.\n\nDay 4: Shri Ram Janmabhoomi Mandir VIP Darshan, Hanuman Garhi, Kanak Bhavan, and sunset Saryu Aarti.\n\nDay 5: Leisure breakfast, souvenir shopping, and airport transfer.';
			} else if (preset === 'buddhist_4d') {
				d.value = 'Sacred Buddhist Pilgrimage (Sarnath & Bodh Gaya)';
				dur.value = '4 Days / 3 Nights';
				f.value = '₹32,000';
				t.value = 'Day 1: Arrival in Varanasi. Sarnath pilgrimage where Lord Buddha delivered his first sermon. Visit Dhamek Stupa, Chaukhandi Stupa, and Mulagandha Kuti Vihar.\n\nDay 2: Scenic drive to Bodh Gaya (5 hrs) in dedicated AC vehicle. Check-in and evening meditation at the sacred Mahabodhi Temple.\n\nDay 3: Visit the sacred Bodhi Tree, Great Buddha Statue (80ft), Royal Bhutan Monastery, and Thai Monastery.\n\nDay 4: Return drive to Varanasi Airport for onward journey.';
			}
		}

		function tcSubmitItinerary(e) {
			e.preventDefault();
			var btn = document.getElementById('tc-itin-submit-btn');
			var loader = document.getElementById('tc-itin-loading');
			var resBanner = document.getElementById('tc-itin-result');
			btn.disabled = true;
			loader.style.display = 'inline';
			resBanner.style.display = 'none';

			var formData = new FormData(document.getElementById('tc-itin-builder-form'));
			formData.append('action', 'tc_generate_itinerary');
			formData.append('nonce', '<?php echo wp_create_nonce( TC_Contacts_Admin::NONCE ); ?>');
			formData.append('contact_id', '<?php echo (int) $c['id']; ?>');

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				loader.style.display = 'none';
				if (res.success && res.data) {
					resBanner.style.display = 'flex';
					document.getElementById('tc-itin-result-summary').innerText = res.data.summary + ' · ' + res.data.total_fare;
					document.getElementById('tc-itin-result-view').href = res.data.view_url;

					var travelerName = <?php echo wp_json_encode( strtok( $name, ' ' ) ); ?>;
					var waPhone = <?php echo wp_json_encode( $wa_digits ); ?>;
					var waText = 'Namaste ' + travelerName + ', here is your personalized tour itinerary & proposal from TripCosmos:\n\n' + res.data.summary + '\nPackage Total: ' + res.data.total_fare + '\n\n👉 View your complete Day-by-Day Itinerary & Booking Details:\n' + res.data.view_url + '\n\nPlease review and let us know if you would like any customized adjustments!';
					document.getElementById('tc-itin-result-wa').href = 'https://wa.me/' + waPhone + '?text=' + encodeURIComponent(waText);
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Error generating itinerary');
				}
			})
			.catch(function(err) {
				btn.disabled = false;
				loader.style.display = 'none';
				console.error(err);
				alert('Connection error');
			});
		}
		</script>

		<!-- Bookings -->
			<section class="tc-cx-card">
				<h3><?php esc_html_e( 'Bookings', 'tripcosmos-agents' ); ?> <?php if ( $bookings ) : ?><span class="tc-cx-count"><?php echo count( $bookings ); ?></span><?php endif; ?></h3>
				<?php foreach ( $bookings as $bi => $b ) : ?>
					<details class="tc-cx-booking" <?php echo ( 0 === $bi && ! in_array( $b['status'], array( 'completed', 'cancelled' ), true ) ) ? 'open' : ''; ?>>
						<summary>
							<strong><?php echo esc_html( $b['ref'] ); ?></strong>
							<span><?php echo esc_html( $b['title'] ); ?></span>
							<span class="tc-cx-pill tc-bk-<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( TC_Contacts_Admin::BOOKING_STATUSES[ $b['status'] ] ?? $b['status'] ); ?></span>
							<span class="tc-cx-muted">₹<?php echo esc_html( number_format_i18n( (float) $b['quote_amount'] ) ); ?><?php echo (float) $b['advance_paid'] > 0 ? ' · ' . esc_html( sprintf( __( '₹%s paid', 'tripcosmos-agents' ), number_format_i18n( (float) $b['advance_paid'] ) ) ) : ''; ?><?php echo $b['start_date'] ? ' · ' . esc_html( mysql2date( 'j M Y', $b['start_date'] ) ) : ''; ?></span>
						</summary>
						<?php include __DIR__ . '/booking-billing.php'; ?>
						<details class="tc-bill-edit-trip"><summary class="tc-cx-muted"><?php esc_html_e( 'Edit trip details', 'tripcosmos-agents' ); ?></summary>
							<?php $tc_booking = $b; include __DIR__ . '/booking-form.php'; ?>
						</details>
					</details>
				<?php endforeach; ?>
				<details class="tc-cx-booking tc-cx-newbooking" <?php echo $bookings ? '' : 'open'; ?>>
					<summary><?php esc_html_e( '+ New booking / quote', 'tripcosmos-agents' ); ?></summary>
					<?php $tc_booking = array(); include __DIR__ . '/booking-form.php'; ?>
				</details>
			</section>
		</div>

		<div class="tc-cx-col">
			
		<!-- Two-Way WhatsApp Quick Reply Messenger -->
		<?php if ( $can_wa ) : ?>
			<div class="tc-wa-box-card">
				<div class="tc-wa-box-header">
					<div class="tc-wa-box-title">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
						<span><?php esc_html_e( 'Direct WhatsApp Quick Messenger', 'tripcosmos-agents' ); ?></span>
					</div>
					<div>
						<select id="tc-wa-template-picker" class="tc-wa-templates-select" onchange="tcApplyWaTemplate(this)">
							<option value=""><?php esc_html_e( '⚡ Insert Quick Template…', 'tripcosmos-agents' ); ?></option>
							<option value="greeting"><?php esc_html_e( '👋 Welcome & Greeting', 'tripcosmos-agents' ); ?></option>
							<option value="itinerary"><?php esc_html_e( '📍 Varanasi 3D/2N Itinerary', 'tripcosmos-agents' ); ?></option>
							<option value="followup"><?php esc_html_e( '📑 Proposal Follow-Up', 'tripcosmos-agents' ); ?></option>
							<option value="payment"><?php esc_html_e( '💳 Advance Payment Confirmation', 'tripcosmos-agents' ); ?></option>
						</select>
					</div>
				</div>
				<div class="tc-ai-copilot-bar">
					<div class="tc-ai-chips">
						<span class="tc-ai-label">✨ <?php esc_html_e( 'AI Copilot:', 'tripcosmos-agents' ); ?></span>
						<button type="button" class="tc-ai-chip" onclick="tcGenerateAiReply('friendly')">👋 <?php esc_html_e( 'Smart Greeting', 'tripcosmos-agents' ); ?></button>
						<button type="button" class="tc-ai-chip" onclick="tcGenerateAiReply('closer')">🔥 <?php esc_html_e( 'Deal Closer', 'tripcosmos-agents' ); ?></button>
						<button type="button" class="tc-ai-chip" onclick="tcGenerateAiReply('pilgrimage')">🕉️ <?php esc_html_e( 'Sacred Tour', 'tripcosmos-agents' ); ?></button>
						<button type="button" class="tc-ai-chip" onclick="tcGenerateAiReply('pricing')">💰 <?php esc_html_e( 'Price Breakdown', 'tripcosmos-agents' ); ?></button>
					</div>
					<button type="button" id="tc-ai-reply-btn" class="tc-ai-generate-btn" onclick="tcGenerateAiReply('friendly')">
						<span class="tc-ai-btn-text">✨ <?php esc_html_e( 'Draft AI Reply', 'tripcosmos-agents' ); ?></span>
						<span class="tc-ai-btn-spinner" style="display:none;">⏳ <?php esc_html_e( 'Thinking…', 'tripcosmos-agents' ); ?></span>
					</button>
				</div>
				<textarea id="tc-wa-message-text" rows="3" class="tc-wa-textarea" placeholder="<?php esc_attr_e( 'Type a WhatsApp message or pick a quick template above…', 'tripcosmos-agents' ); ?>">Namaste <?php echo esc_attr( strtok( $name, ' ' ) ); ?>, thank you for contacting TripCosmos. How may we assist with your Varanasi travel plans?</textarea>
				<div class="tc-wa-box-footer">
					<span style="font-size:12px; color:#64748b;">
						📱 <?php echo esc_html( $c['phone_e164'] ?: $c['phone'] ); ?>
					</span>
					<div style="display:flex; gap:8px;">
						<button type="button" class="tc-cx-btn tc-cx-btn-wa" onclick="tcSendWhatsAppMsg()">
							💬 <?php esc_html_e( 'Send on WhatsApp', 'tripcosmos-agents' ); ?> &rarr;
						</button>
					</div>
				</div>
			</div>

			<script>
			function tcApplyWaTemplate(select) {
				var val = select.value;
				var name = <?php echo wp_json_encode( strtok( $name, ' ' ) ); ?>;
				var text = document.getElementById('tc-wa-message-text');
				if (!text || !val) return;

				if (val === 'greeting') {
					text.value = 'Namaste ' + name + ', warm greetings from TripCosmos! I am your dedicated travel coordinator for Varanasi. How can we make your trip memorable?';
				} else if (val === 'itinerary') {
					text.value = 'Namaste ' + name + ', here is our curated Varanasi experience: VIP Kashi Vishwanath Darshan, private Subah-e-Banaras sunrise boat ride, and evening Ganga Aarti with reserved front-row seating. Shall we customize this for your dates?';
				} else if (val === 'followup') {
					text.value = 'Namaste ' + name + ', following up on the itinerary and quote we shared earlier. Do you have any questions or preferences on hotel categories?';
				} else if (val === 'payment') {
					text.value = 'Namaste ' + name + ', your reservation dates are tentatively blocked. You can securely confirm your booking with the 20% advance via our verified payment link.';
				}
			}

			function tcSendWhatsAppMsg() {
				var text = document.getElementById('tc-wa-message-text').value;
				var phone = <?php echo wp_json_encode( $wa_digits ); ?>;
				if (!text || !phone) return;
				var url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(text);
				window.open(url, '_blank');
			}
			</script>
		<?php endif; ?>

		<!-- Timeline -->
			<section class="tc-cx-card">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
					<h3 style="margin:0;"><?php esc_html_e( 'Activity & Interaction Timeline', 'tripcosmos-agents' ); ?></h3>
					<div class="tc-activity-switcher">
						<button type="button" id="tc-btn-tab-note" class="tc-act-tab-btn active" onclick="tcSwitchActTab('note', this)">📝 <?php esc_html_e( 'Add Note', 'tripcosmos-agents' ); ?></button>
						<button type="button" id="tc-btn-tab-call" class="tc-act-tab-btn" onclick="tcSwitchActTab('call', this)">📞 <?php esc_html_e( 'Log Call', 'tripcosmos-agents' ); ?></button>
					</div>
				</div>

				<!-- Note Form -->
				<form method="post" id="tc-act-note-form" class="tc-cx-note">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="add_note" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<textarea name="note" rows="3" placeholder="<?php esc_attr_e( 'Log a note, traveler preference or operational detail…', 'tripcosmos-agents' ); ?>" required></textarea>
					<button class="tc-cx-btn"><?php esc_html_e( 'Add note', 'tripcosmos-agents' ); ?></button>
				</form>

				<!-- Log Call Form -->
				<form method="post" id="tc-act-call-form" class="tc-cx-call-log-form" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:16px;">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="log_call" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-bottom:10px;">
						<div>
							<label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px; text-transform:uppercase;"><?php esc_html_e( 'Direction', 'tripcosmos-agents' ); ?></label>
							<select name="call_direction" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; height:34px; font-size:13px;">
								<option value="outgoing">📤 <?php esc_html_e( 'Outgoing Call', 'tripcosmos-agents' ); ?></option>
								<option value="incoming">📥 <?php esc_html_e( 'Incoming Call', 'tripcosmos-agents' ); ?></option>
							</select>
						</div>
						<div>
							<label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px; text-transform:uppercase;"><?php esc_html_e( 'Outcome', 'tripcosmos-agents' ); ?></label>
							<select name="call_disposition" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; height:34px; font-size:13px;">
								<option value="connected">✅ <?php esc_html_e( 'Connected / Spoke', 'tripcosmos-agents' ); ?></option>
								<option value="busy">⏳ <?php esc_html_e( 'Busy / Call Back', 'tripcosmos-agents' ); ?></option>
								<option value="missed">📵 <?php esc_html_e( 'No Answer / Missed', 'tripcosmos-agents' ); ?></option>
								<option value="voicemail">📼 <?php esc_html_e( 'Left Voicemail', 'tripcosmos-agents' ); ?></option>
							</select>
						</div>
						<div>
							<label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px; text-transform:uppercase;"><?php esc_html_e( 'Duration', 'tripcosmos-agents' ); ?></label>
							<div style="display:flex; gap:6px;">
								<input type="number" name="duration_minutes" min="0" max="180" placeholder="Mins" style="width:65px; border-radius:6px; border:1px solid #cbd5e1; height:34px; font-size:13px; text-align:center;" />
								<input type="number" name="duration_seconds" min="0" max="59" placeholder="Secs" style="width:65px; border-radius:6px; border:1px solid #cbd5e1; height:34px; font-size:13px; text-align:center;" />
							</div>
						</div>
					</div>
					<div style="margin-bottom:10px;">
						<textarea name="call_note" rows="2" placeholder="<?php esc_attr_e( 'Call summary: key topics discussed, agreements made, next steps…', 'tripcosmos-agents' ); ?>" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; font-size:13px;" required></textarea>
					</div>
					<div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
						<input type="url" name="recording_url" placeholder="<?php esc_attr_e( 'Optional Audio Recording Link (e.g. https://.../call.mp3)', 'tripcosmos-agents' ); ?>" style="flex:1; min-width:240px; border-radius:6px; border:1px solid #cbd5e1; height:34px; font-size:12px; padding:0 8px;" />
						<button type="submit" class="tc-cx-btn" style="background:#0f172a; color:#fff; font-weight:600; min-height:34px; padding:0 16px;">📞 <?php esc_html_e( 'Save Call Record', 'tripcosmos-agents' ); ?></button>
					</div>
				</form>
				<ol class="tc-cx-feed">
					<?php foreach ( $feed as $item ) : $tm = $type_meta[ $item['type'] ] ?? array( '•', ucfirst( $item['type'] ) ); ?>
						<li class="tc-cx-ev tc-ev-<?php echo esc_attr( $item['type'] ); ?>">
							<span class="tc-cx-ev-icon" aria-hidden="true"><?php echo esc_html( $tm[0] ); ?></span>
							<div style="flex:1;">
								<div class="tc-cx-ev-meta">
									<strong><?php echo esc_html( $tm[1] ); ?></strong> · 
									<span title="<?php echo esc_attr( mysql2date( 'j M Y, H:i', $item['at'] ) ); ?>"><?php echo esc_html( $ago( $item['at'] ) ); ?></span>
									<?php echo $item['user'] ? ' · ' . esc_html( get_the_author_meta( 'display_name', $item['user'] ) ) : ''; ?>
									<?php if ( 'call' === $item['type'] && ! empty( $item['meta']['disposition'] ) ) : ?>
										<span class="tc-call-disp-pill tc-disp-<?php echo esc_attr( $item['meta']['disposition'] ); ?>">
											<?php echo esc_html( ucfirst( $item['meta']['disposition'] ) ); ?>
										</span>
									<?php endif; ?>
									<?php if ( 'call' === $item['type'] && ! empty( $item['meta']['duration'] ) ) : 
										$d = (int) $item['meta']['duration']; ?>
										<span class="tc-call-dur-pill">⏱️ <?php echo esc_html( sprintf( '%dm %02ds', floor( $d / 60 ), $d % 60 ) ); ?></span>
									<?php endif; ?>
								</div>
								<div class="tc-cx-ev-body">
									<?php echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above. ?>
									<?php 
									$rec_url = $item['meta']['recording_url'] ?? $item['meta']['audio_url'] ?? '';
									if ( ! empty( $rec_url ) ) : ?>
										<div class="tc-audio-playback-box" style="margin-top:8px; background:#f1f5f9; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0;">
											<div style="display:flex; align-items:center; gap:8px;">
												<audio controls preload="metadata" src="<?php echo esc_url( $rec_url ); ?>" style="flex:1; height:32px;"></audio>
												<a href="<?php echo esc_url( $rec_url ); ?>" download target="_blank" style="padding:4px 8px; font-size:12px; border:1px solid #cbd5e1; border-radius:4px; text-decoration:none; color:#475569;" title="<?php esc_attr_e( 'Download recording', 'tripcosmos-agents' ); ?>">⬇️</a>
											</div>
										</div>
									<?php endif; ?>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>

			<!-- CRM -->
			<section class="tc-cx-card">
				<h3><?php esc_html_e( 'CRM sync', 'tripcosmos-agents' ); ?></h3>
				<dl class="tc-cx-kv">
					<dt>FluentCRM</dt>
					<dd>
						<?php
						$sync_labels = array( 'synced' => __( 'Synced', 'tripcosmos-agents' ), 'error' => __( 'Error', 'tripcosmos-agents' ), 'skipped_no_email' => __( 'Not synced: no email', 'tripcosmos-agents' ), '' => __( 'Not synced yet', 'tripcosmos-agents' ) );
						echo esc_html( $sync_labels[ $c['sync_status'] ] ?? $c['sync_status'] );
						if ( $c['fluentcrm_id'] ) {
							echo ' · <a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/subscribers/' . (int) $c['fluentcrm_id'] ) ) . '">' . esc_html( sprintf( __( 'Open #%d', 'tripcosmos-agents' ), $c['fluentcrm_id'] ) ) . ' ↗</a>';
						}
						?>
						<?php if ( $c['sync_error'] ) : ?><br /><span class="tc-cx-err"><?php echo esc_html( $c['sync_error'] ); ?></span><?php endif; ?>
					</dd>
					<?php if ( $c['email_status'] ) : ?><dt><?php esc_html_e( 'Email status', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( $c['email_status'] ); ?></dd><?php endif; ?>
					<?php if ( $c['last_synced_at'] ) : ?><dt><?php esc_html_e( 'Last sync', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( $ago( $c['last_synced_at'] ) ); ?></dd><?php endif; ?>
					<?php if ( $c['twentycrm_id'] ) : ?><dt>TwentyCRM</dt><dd><?php echo esc_html( $c['twentycrm_id'] ); ?></dd><?php endif; ?>
				</dl>
				<?php if ( $fluent_on && TC_Contact_Service::is_real_email( $c['email'] ) ) : ?>
					<form method="post">
						<?php $tc_nonce(); ?>
						<input type="hidden" name="tc_contacts_action" value="sync_contact" />
						<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
						<button class="tc-cx-btn"><?php esc_html_e( 'Sync to FluentCRM now', 'tripcosmos-agents' ); ?></button>
					</form>
				<?php endif; ?>
			</section>

			<!-- Danger zone -->
			<?php if ( $is_mgr ) : ?>
			<details class="tc-cx-card tc-cx-danger">
				<summary><?php esc_html_e( 'Merge or delete', 'tripcosmos-agents' ); ?></summary>
				<form method="post" class="tc-cx-row" onsubmit="return confirm('<?php echo esc_js( __( 'Merge the other contact into this one? The other contact will be deleted after its history is moved here.', 'tripcosmos-agents' ) ); ?>');">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="merge" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<label><?php esc_html_e( 'Merge contact #', 'tripcosmos-agents' ); ?> <input type="number" name="merge_id" min="1" required style="width:90px;" /></label>
					<button class="tc-cx-btn"><?php esc_html_e( 'into this one', 'tripcosmos-agents' ); ?></button>
				</form>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this contact, its activity, bookings and conversations? This cannot be undone.', 'tripcosmos-agents' ) ); ?>');">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="delete_contact" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<?php if ( $c['fluentcrm_id'] ) : ?><label class="tc-cx-toggle"><input type="checkbox" name="delete_crm" value="1" /> <?php esc_html_e( 'Also delete from FluentCRM (for data-erasure requests)', 'tripcosmos-agents' ); ?></label><?php endif; ?>
					<button class="tc-cx-btn tc-cx-btn-danger"><?php esc_html_e( 'Delete contact', 'tripcosmos-agents' ); ?></button>
				</form>
			</details>
			<?php endif; ?>
		</div>
	</div>
</div>
</div>
