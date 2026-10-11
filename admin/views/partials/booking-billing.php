<?php
/**
 * Quote & payments panel for one booking. Expects $b (booking row), $c (contact), $tc_nonce.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tc_set     = TC_Quote_Service::settings();
$tc_quotes  = TC_Quote_Service::for_booking( (int) $b['id'] );
$tc_cur     = TC_Quote_Service::current_for_booking( (int) $b['id'] );
$tc_pays    = TC_Payments::for_booking( (int) $b['id'] );
$tc_amt     = TC_Payments::amounts( (int) $b['id'] );
$tc_money   = array( 'TC_Quote_Service', 'money' );
$tc_items   = $tc_cur ? ( json_decode( (string) $tc_cur['items'], true ) ?: array() ) : array( array( 'desc' => $b['title'], 'qty' => 1, 'price' => (float) $b['quote_amount'] ?: '' ) );
$tc_wa      = preg_replace( '/\D/', '', (string) $c['phone_e164'] );
$tc_can_wa  = $tc_wa && ! $c['whatsapp_opt_out'] && ! $c['dnd'];
$tc_first   = strtok( (string) $c['name'], ' ' ) ?: 'Traveler';
$tc_qstatus = array( 'draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'accepted' => 'Accepted', 'superseded' => 'Old version' );
?>
<div class="tc-bill">
	<?php if ( $tc_cur ) : ?>
		<div class="tc-bill-summary">
			<div>
				<strong><?php echo esc_html( sprintf( 'Quote v%d', $tc_cur['version'] ) ); ?></strong>
				<span class="tc-cx-pill tc-q-<?php echo esc_attr( $tc_cur['status'] ); ?>"><?php echo esc_html( $tc_qstatus[ $tc_cur['status'] ] ?? $tc_cur['status'] ); ?></span>
				<?php if ( 'accepted' !== $tc_cur['status'] && $tc_cur['valid_until'] && $tc_cur['valid_until'] < current_time( 'Y-m-d' ) ) : ?><span class="tc-cx-pill tc-cx-pill-red"><?php esc_html_e( 'Expired', 'tripcosmos-agents' ); ?></span><?php endif; ?>
				<div class="tc-cx-muted" style="font-size:12px;">
					<?php
					echo esc_html(
						implode(
							' · ',
							array_filter(
								array(
									$tc_cur['sent_at'] ? sprintf( 'sent %s', mysql2date( 'j M H:i', $tc_cur['sent_at'] ) ) : '',
									$tc_cur['viewed_at'] ? sprintf( 'opened %s', mysql2date( 'j M H:i', $tc_cur['viewed_at'] ) ) : '',
									$tc_cur['accepted_at'] ? sprintf( 'accepted %s', mysql2date( 'j M H:i', $tc_cur['accepted_at'] ) ) : '',
								)
							)
						)
					);
					?>
				</div>
			</div>
			<dl class="tc-bill-nums">
				<div><dt><?php esc_html_e( 'Total', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( $tc_money( $tc_amt['total'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Paid', 'tripcosmos-agents' ); ?></dt><dd class="ok"><?php echo esc_html( $tc_money( $tc_amt['paid'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Balance', 'tripcosmos-agents' ); ?></dt><dd><?php echo esc_html( $tc_money( $tc_amt['balance'] ) ); ?></dd></div>
			</dl>
		</div>
		<div class="tc-cx-row" style="margin:8px 0 12px;">
			<a class="tc-cx-btn" target="_blank" href="<?php echo esc_url( TC_Quote_Service::url( $tc_cur ) ); ?>"><?php esc_html_e( 'Preview', 'tripcosmos-agents' ); ?> ↗</a>
			<button type="button" class="tc-cx-btn" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js( TC_Quote_Service::url( $tc_cur ) ); ?>');this.textContent='<?php echo esc_js( __( 'Copied ✓', 'tripcosmos-agents' ) ); ?>';"><?php esc_html_e( 'Copy link', 'tripcosmos-agents' ); ?></button>
			<?php if ( $tc_wa && ! $c['dnd'] ) : ?>
				<form method="post" target="_blank" class="tc-cx-inline">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="send_quote" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<input type="hidden" name="quote_id" value="<?php echo (int) $tc_cur['id']; ?>" />
					<input type="hidden" name="channel" value="whatsapp" />
					<button class="tc-cx-btn tc-cx-btn-wa">💬 <?php esc_html_e( 'Send on WhatsApp', 'tripcosmos-agents' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( TC_Contact_Service::is_real_email( $c['email'] ) && ! $c['dnd'] ) : ?>
				<form method="post" class="tc-cx-inline">
					<?php $tc_nonce(); ?>
					<input type="hidden" name="tc_contacts_action" value="send_quote" />
					<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
					<input type="hidden" name="quote_id" value="<?php echo (int) $tc_cur['id']; ?>" />
					<input type="hidden" name="channel" value="email" />
					<button class="tc-cx-btn">✉️ <?php esc_html_e( 'Email quote', 'tripcosmos-agents' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $b['invoice_no'] ) : ?><a class="tc-cx-btn" target="_blank" href="<?php echo esc_url( TC_Quote_Service::url( $tc_cur, true ) ); ?>">🧾 <?php echo esc_html( $b['invoice_no'] ); ?></a><?php endif; ?>
		</div>
	<?php endif; ?>

	<details class="tc-bill-editor" <?php echo $tc_cur ? '' : 'open'; ?>>
		<summary><?php echo $tc_cur ? esc_html__( 'Revise quote (creates a new version)', 'tripcosmos-agents' ) : esc_html__( 'Build quote', 'tripcosmos-agents' ); ?></summary>
		<form method="post" class="tc-q-form">
			<?php $tc_nonce(); ?>
			<input type="hidden" name="tc_contacts_action" value="save_quote" />
			<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
			<input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>" />
			<table class="tc-q-lines">
				<thead><tr><th><?php esc_html_e( 'Item', 'tripcosmos-agents' ); ?></th><th style="width:70px;"><?php esc_html_e( 'Qty', 'tripcosmos-agents' ); ?></th><th style="width:120px;"><?php esc_html_e( 'Rate (₹)', 'tripcosmos-agents' ); ?></th><th style="width:30px;"></th></tr></thead>
				<tbody>
				<?php foreach ( array_merge( $tc_items, array( array( 'desc' => '', 'qty' => 1, 'price' => '' ) ) ) as $i => $it ) : ?>
					<tr>
						<td><input type="text" name="items[<?php echo (int) $i; ?>][desc]" value="<?php echo esc_attr( $it['desc'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Innova Crysta, Varanasi–Ayodhya round trip', 'tripcosmos-agents' ); ?>" list="tc-q-suggest" /></td>
						<td><input type="number" step="0.5" min="0" name="items[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $it['qty'] ); ?>" /></td>
						<td><input type="number" step="1" min="0" name="items[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( $it['price'] ); ?>" /></td>
						<td><button type="button" class="button-link" title="<?php esc_attr_e( 'Remove', 'tripcosmos-agents' ); ?>" onclick="this.closest('tr').remove();">✕</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<datalist id="tc-q-suggest">
				<option value="Kashi Vishwanath VIP darshan (per person)"><option value="Ganga Aarti private boat"><option value="Sunrise boat ride"><option value="Hotel room per night"><option value="Swift Dzire per day (local)"><option value="Innova Crysta outstation"><option value="Tour guide per day"><option value="Airport / station transfer">
			</datalist>
			<button type="button" class="tc-cx-chip" onclick="var tb=this.form.querySelector('tbody'),r=tb.lastElementChild.cloneNode(true),n=tb.children.length;r.querySelectorAll('input').forEach(function(x){x.name=x.name.replace(/\[\d+\]/,'['+n+']');x.value=x.type==='number'&&x.name.indexOf('qty')>-1?1:'';});tb.appendChild(r);">+ <?php esc_html_e( 'Add line', 'tripcosmos-agents' ); ?></button>
			<div class="tc-q-opts">
				<label><span><?php esc_html_e( 'Discount (₹)', 'tripcosmos-agents' ); ?></span><input type="number" min="0" step="1" name="discount" value="<?php echo esc_attr( $tc_cur ? (float) $tc_cur['discount'] : 0 ); ?>" /></label>
				<label><span><?php esc_html_e( 'GST %', 'tripcosmos-agents' ); ?></span><input type="number" min="0" max="28" step="0.5" name="tax_rate" value="<?php echo esc_attr( $tc_cur ? (float) $tc_cur['tax_rate'] : ( $tc_set['gst_enabled'] ? (float) $tc_set['gst_rate'] : 0 ) ); ?>" /></label>
				<label class="tc-cx-toggle" style="align-self:end;"><input type="checkbox" name="tax_inclusive" value="1" <?php checked( $tc_cur ? (int) $tc_cur['tax_inclusive'] : (int) $tc_set['gst_inclusive'], 1 ); ?> /> <?php esc_html_e( 'Prices include GST', 'tripcosmos-agents' ); ?></label>
				<label><span><?php esc_html_e( 'Advance to confirm (₹)', 'tripcosmos-agents' ); ?></span><input type="number" min="0" step="1" name="advance_amount" value="<?php echo esc_attr( $tc_cur ? (float) $tc_cur['advance_amount'] : '' ); ?>" placeholder="<?php echo esc_attr( 'percent' === $tc_set['advance_type'] ? $tc_set['advance_value'] . '%' : $tc_set['advance_value'] ); ?>" /></label>
				<label><span><?php esc_html_e( 'Valid for (days)', 'tripcosmos-agents' ); ?></span><input type="number" min="1" name="valid_days" value="<?php echo esc_attr( (int) $tc_set['valid_days'] ); ?>" /></label>
			</div>
			<label class="tc-q-notes"><span><?php esc_html_e( 'Note to customer (inclusions, exclusions, pickup…)', 'tripcosmos-agents' ); ?></span><textarea name="notes" rows="2"><?php echo esc_textarea( $tc_cur ? (string) $tc_cur['notes'] : '' ); ?></textarea></label>
			<button class="tc-cx-btn tc-cx-btn-primary"><?php echo $tc_cur ? esc_html__( 'Save new version', 'tripcosmos-agents' ) : esc_html__( 'Create quote', 'tripcosmos-agents' ); ?></button>
		</form>
	</details>

	<?php if ( $tc_cur ) : ?>
		<div class="tc-bill-pay">
			<h4><?php esc_html_e( 'Payments', 'tripcosmos-agents' ); ?></h4>
			<?php if ( $tc_pays ) : ?>
				<table class="tc-bill-table">
					<?php foreach ( $tc_pays as $p ) : ?>
						<tr>
							<td><?php echo esc_html( $tc_money( $p['amount'] ) ); ?></td>
							<td><?php echo esc_html( 'cashfree' === $p['provider'] ? 'Cashfree link' : ucfirst( $p['method'] ?: $p['provider'] ) ); ?><?php echo $p['reference'] && 'cashfree' !== $p['provider'] ? ' · ' . esc_html( $p['reference'] ) : ''; ?></td>
							<td><span class="tc-cx-pill tc-pay-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( ucfirst( $p['status'] ) ); ?></span></td>
							<td class="tc-cx-muted"><?php echo esc_html( mysql2date( 'j M H:i', $p['paid_at'] ?: $p['created_at'] ) ); ?></td>
							<td>
								<?php if ( 'created' === $p['status'] && $p['link_url'] ) : ?>
									<button type="button" class="button-link" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js( $p['link_url'] ); ?>');this.textContent='<?php echo esc_js( __( 'Copied ✓', 'tripcosmos-agents' ) ); ?>';"><?php esc_html_e( 'Copy link', 'tripcosmos-agents' ); ?></button>
									<?php if ( $tc_wa && ! $c['dnd'] ) : ?> · <a target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $tc_wa . '?text=' . rawurlencode( sprintf( "Namaste %s 🙏\nHere is your secure payment link for %s (%s):\n%s", $tc_first, $tc_money( $p['amount'] ), $b['ref'], $p['link_url'] ) ) ); ?>"><?php esc_html_e( 'WhatsApp', 'tripcosmos-agents' ); ?></a><?php endif; ?>
									· <form method="post" class="tc-cx-inline"><?php $tc_nonce(); ?><input type="hidden" name="tc_contacts_action" value="check_payment" /><input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" /><input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>" /><button class="button-link"><?php esc_html_e( 'Check status', 'tripcosmos-agents' ); ?></button></form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
			<?php if ( $tc_amt['balance'] > 0 ) : ?>
				<div class="tc-bill-actions">
					<?php if ( TC_Payments::cashfree_ready() ) : ?>
						<form method="post" class="tc-cx-row">
							<?php $tc_nonce(); ?>
							<input type="hidden" name="tc_contacts_action" value="create_link" />
							<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
							<input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>" />
							<input type="number" name="amount" min="1" step="1" value="<?php echo esc_attr( $tc_amt['advance_due'] > 0 ? $tc_amt['advance_due'] : $tc_amt['balance'] ); ?>" style="width:130px;" />
							<button class="tc-cx-btn"><?php esc_html_e( 'Create Cashfree payment link', 'tripcosmos-agents' ); ?></button>
						</form>
					<?php endif; ?>
					<details>
						<summary class="tc-cx-muted" style="cursor:pointer;margin-top:8px;"><?php esc_html_e( 'Record a payment received by UPI / cash / bank', 'tripcosmos-agents' ); ?></summary>
						<form method="post" class="tc-cx-row" style="margin-top:8px;" onsubmit="return confirm('<?php echo esc_js( __( 'Record this payment as received? The customer gets a WhatsApp receipt.', 'tripcosmos-agents' ) ); ?>');">
							<?php $tc_nonce(); ?>
							<input type="hidden" name="tc_contacts_action" value="record_payment" />
							<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
							<input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>" />
							<input type="number" name="amount" min="1" step="1" required placeholder="₹" style="width:110px;" />
							<select name="method" style="width:auto;"><option value="upi">UPI</option><option value="cash"><?php esc_html_e( 'Cash', 'tripcosmos-agents' ); ?></option><option value="bank"><?php esc_html_e( 'Bank transfer', 'tripcosmos-agents' ); ?></option><option value="card"><?php esc_html_e( 'Card (POS)', 'tripcosmos-agents' ); ?></option></select>
							<input type="text" name="reference" placeholder="<?php esc_attr_e( 'UTR / reference', 'tripcosmos-agents' ); ?>" style="width:170px;" />
							<button class="tc-cx-btn"><?php esc_html_e( 'Record', 'tripcosmos-agents' ); ?></button>
						</form>
					</details>
				</div>
			<?php else : ?>
				<p class="tc-cx-muted">✅ <?php esc_html_e( 'Fully paid.', 'tripcosmos-agents' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( count( $tc_quotes ) > 1 ) : ?>
			<p class="tc-cx-muted" style="font-size:12px;"><?php esc_html_e( 'Versions:', 'tripcosmos-agents' ); ?>
				<?php foreach ( $tc_quotes as $vq ) : ?><a target="_blank" href="<?php echo esc_url( TC_Quote_Service::url( $vq ) ); ?>">v<?php echo (int) $vq['version']; ?> (<?php echo esc_html( $tc_money( $vq['total'] ) . ', ' . ( $tc_qstatus[ $vq['status'] ] ?? $vq['status'] ) ); ?>)</a> <?php endforeach; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
