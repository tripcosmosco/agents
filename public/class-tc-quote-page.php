<?php
/**
 * Customer-facing quote / receipt page at /?tc_quote=TOKEN (and /?tc_invoice=TOKEN).
 * Standalone, mobile-first, printable. No login needed; the 128-bit token is the credential.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Quote_Page {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
	}

	public static function maybe_render() {
		$token   = isset( $_GET['tc_quote'] ) ? (string) $_GET['tc_quote'] : ( isset( $_GET['tc_invoice'] ) ? (string) $_GET['tc_invoice'] : '' );
		$invoice = isset( $_GET['tc_invoice'] );
		if ( '' === $token ) {
			return;
		}
		// Never serve these from a page cache: balances and payment status change.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'tripcosmos quote page' );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );

		$q = TC_Quote_Service::get_by_token( $token );
		if ( ! $q ) {
			status_header( 404 );
			self::shell( __( 'Quote not found', 'tripcosmos-agents' ), '<div class="box"><h1>' . esc_html__( 'This link is not valid', 'tripcosmos-agents' ) . '</h1><p>' . esc_html__( 'Please ask our team to send you a fresh link.', 'tripcosmos-agents' ) . '</p></div>' );
			exit;
		}

		$notice = '';
		// Actions from the page.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['tc_q_action'] ) ) {
			if ( ! wp_verify_nonce( (string) ( $_POST['_tcq'] ?? '' ), 'tc_quote_' . $q['id'] ) ) {
				$notice = __( 'This page expired. Please try again.', 'tripcosmos-agents' );
			} elseif ( 'accept' === $_POST['tc_q_action'] ) {
				if ( TC_Quote_Service::accept( $q, sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ) {
					wp_safe_redirect( add_query_arg( 'accepted', 1, TC_Quote_Service::url( $q ) ) );
					exit;
				}
				$notice = __( 'This quote can no longer be accepted. Please contact us for an updated quote.', 'tripcosmos-agents' );
			} elseif ( 'pay' === $_POST['tc_q_action'] ) {
				$amounts = TC_Payments::amounts( (int) $q['booking_id'] );
				$amount  = 'balance' === ( $_POST['what'] ?? '' ) ? $amounts['balance'] : ( $amounts['advance_due'] > 0 ? $amounts['advance_due'] : $amounts['balance'] );
				if ( in_array( $q['status'], array( 'draft', 'sent', 'viewed' ), true ) && TC_Quote_Service::is_open( $q ) ) {
					TC_Quote_Service::accept( $q, sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // paying = accepting
				}
				$p = TC_Payments::create_cashfree_link( (int) $q['booking_id'], $amount );
				if ( ! is_wp_error( $p ) && $p['link_url'] ) {
					wp_redirect( esc_url_raw( $p['link_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- Cashfree hosted page.
					exit;
				}
				$notice = __( 'Online payment is unavailable right now. Please use UPI below or contact us.', 'tripcosmos-agents' );
			}
			$q = TC_Quote_Service::get( (int) $q['id'] );
		}

		// Back from Cashfree: confirm server-side.
		if ( isset( $_GET['tc_paid'] ) ) {
			$p = TC_Payments::get( absint( $_GET['tc_paid'] ) );
			if ( $p && (int) $p['booking_id'] === (int) $q['booking_id'] ) {
				$st     = TC_Payments::reconcile( $p );
				$notice = 'paid' === $st ? __( '✅ Payment received. Thank you! A receipt is below.', 'tripcosmos-agents' ) : __( 'We have not received confirmation of this payment yet. If money was deducted it will reflect in a few minutes.', 'tripcosmos-agents' );
			}
		}
		if ( isset( $_GET['accepted'] ) ) {
			$notice = __( '✅ Thank you for accepting. Pay the advance below to confirm your booking.', 'tripcosmos-agents' );
		}

		TC_Quote_Service::mark_viewed( $q );
		$q = TC_Quote_Service::get( (int) $q['id'] );
		self::render( $q, $invoice, $notice );
		exit;
	}

	private static function render( array $q, $invoice, $notice ) {
		global $wpdb;
		$s        = TC_Quote_Service::settings();
		$b        = TC_Payments::booking( (int) $q['booking_id'] );
		$c        = TC_Contact_Service::get( (int) $q['contact_id'] );
		$amounts  = TC_Payments::amounts( (int) $q['booking_id'] );
		$items    = json_decode( (string) $q['items'], true ) ?: array();
		$payments = array_filter( TC_Payments::for_booking( (int) $q['booking_id'] ), static function ( $p ) { return 'paid' === $p['status']; } );
		$latest   = TC_Quote_Service::current_for_booking( (int) $q['booking_id'] );
		$m        = array( 'TC_Quote_Service', 'money' );
		$rate     = (float) $q['tax_rate'];
		$is_tax   = $invoice && $rate > 0 && '' !== trim( (string) $s['gstin'] );
		$open     = TC_Quote_Service::is_open( $q );
		$nonce    = wp_create_nonce( 'tc_quote_' . $q['id'] );
		$wa       = preg_replace( '/\D/', '', (string) $s['phone'] );
		if ( empty( $wa ) ) {
			$wa = '919336116210';
		}
		$title    = $invoice ? ( $is_tax ? __( 'Tax Invoice', 'tripcosmos-agents' ) : __( 'Payment Receipt', 'tripcosmos-agents' ) ) : sprintf( __( 'Quotation %1$s · v%2$d', 'tripcosmos-agents' ), $b['ref'], $q['version'] );

		ob_start();
		?>
		<header class="top">
			<div class="brand">
				<?php if ( get_site_icon_url() ) : ?><img src="<?php echo esc_url( get_site_icon_url( 96 ) ); ?>" alt="" width="48" height="48" /><?php endif; ?>
				<div><strong><?php echo esc_html( $s['business_name'] ); ?></strong><br /><small><?php echo esc_html( trim( $s['address'] ) ); ?></small><?php if ( $s['gstin'] ) : ?><br /><small>GSTIN: <?php echo esc_html( $s['gstin'] ); ?></small><?php endif; ?></div>
			</div>
			<div class="doc">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $invoice ) : ?>
					<div><?php echo esc_html( $b['invoice_no'] ? $b['invoice_no'] : '—' ); ?> · <?php echo esc_html( $b['invoice_date'] ? mysql2date( 'j M Y', $b['invoice_date'] ) : '' ); ?></div>
				<?php else : ?>
					<div><?php echo esc_html( sprintf( __( 'Issued %s', 'tripcosmos-agents' ), mysql2date( 'j M Y', $q['created_at'] ) ) ); ?><?php if ( $q['valid_until'] ) : ?> · <?php echo esc_html( sprintf( __( 'Valid until %s', 'tripcosmos-agents' ), mysql2date( 'j M Y', $q['valid_until'] ) ) ); ?><?php endif; ?></div>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $notice ) : ?><div class="notice"><?php echo esc_html( $notice ); ?></div><?php endif; ?>
		<?php if ( ! $invoice && 'superseded' === $q['status'] && $latest && (int) $latest['id'] !== (int) $q['id'] ) : ?>
			<div class="notice warn"><?php esc_html_e( 'There is a newer version of this quote.', 'tripcosmos-agents' ); ?> <a href="<?php echo esc_url( TC_Quote_Service::url( $latest ) ); ?>"><?php esc_html_e( 'View latest', 'tripcosmos-agents' ); ?></a></div>
		<?php elseif ( ! $invoice && ! $open && 'accepted' !== $q['status'] ) : ?>
			<div class="notice warn"><?php esc_html_e( 'This quote has expired. Please contact us for updated prices.', 'tripcosmos-agents' ); ?></div>
		<?php endif; ?>

		<section class="box two">
			<div>
				<small class="label"><?php esc_html_e( 'Prepared for', 'tripcosmos-agents' ); ?></small>
				<strong><?php echo esc_html( $c['name'] ?? '' ); ?></strong><br />
				<small><?php echo esc_html( trim( ( $c['phone_e164'] ?? '' ) . '  ' . ( $c['email'] ?? '' ) ) ); ?></small>
			</div>
			<div>
				<small class="label"><?php esc_html_e( 'Trip', 'tripcosmos-agents' ); ?></small>
				<strong><?php echo esc_html( $b['title'] ); ?></strong><br />
				<small><?php echo esc_html( implode( ' · ', array_filter( array( $b['start_date'] ? mysql2date( 'j M Y', $b['start_date'] ) . ( $b['end_date'] ? ' – ' . mysql2date( 'j M Y', $b['end_date'] ) : '' ) : '', $b['pax'] ? sprintf( _n( '%d traveller', '%d travellers', (int) $b['pax'], 'tripcosmos-agents' ), (int) $b['pax'] ) : '', $b['vehicle'] ) ) ) ); ?></small>
			</div>
		</section>

		<section class="box">
			<table class="items">
				<thead><tr><th><?php esc_html_e( 'Description', 'tripcosmos-agents' ); ?></th><th class="n"><?php esc_html_e( 'Qty', 'tripcosmos-agents' ); ?></th><th class="n"><?php esc_html_e( 'Rate', 'tripcosmos-agents' ); ?></th><th class="n"><?php esc_html_e( 'Amount', 'tripcosmos-agents' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $items as $it ) : ?>
					<tr><td><?php echo esc_html( $it['desc'] ); ?></td><td class="n"><?php echo esc_html( rtrim( rtrim( number_format( (float) $it['qty'], 2, '.', '' ), '0' ), '.' ) ); ?></td><td class="n"><?php echo esc_html( $m( $it['price'] ) ); ?></td><td class="n"><?php echo esc_html( $m( $it['amount'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<table class="totals">
				<tr><td><?php esc_html_e( 'Subtotal', 'tripcosmos-agents' ); ?></td><td class="n"><?php echo esc_html( $m( $q['subtotal'] ) ); ?></td></tr>
				<?php if ( (float) $q['discount'] > 0 ) : ?><tr><td><?php esc_html_e( 'Discount', 'tripcosmos-agents' ); ?></td><td class="n">− <?php echo esc_html( $m( $q['discount'] ) ); ?></td></tr><?php endif; ?>
				<?php if ( $rate > 0 ) : ?>
					<tr class="muted"><td><?php esc_html_e( 'Taxable value', 'tripcosmos-agents' ); ?><?php echo $s['sac'] ? ' · SAC ' . esc_html( $s['sac'] ) : ''; ?></td><td class="n"><?php echo esc_html( $m( (float) $q['total'] - (float) $q['tax_amount'] ) ); ?></td></tr>
					<?php if ( ! empty( $s['gst_split'] ) ) : ?>
						<tr class="muted"><td>CGST <?php echo esc_html( rtrim( rtrim( number_format( $rate / 2, 2 ), '0' ), '.' ) ); ?>%</td><td class="n"><?php echo esc_html( $m( round( (float) $q['tax_amount'] / 2, 2 ) ) ); ?></td></tr>
						<tr class="muted"><td>SGST <?php echo esc_html( rtrim( rtrim( number_format( $rate / 2, 2 ), '0' ), '.' ) ); ?>%</td><td class="n"><?php echo esc_html( $m( (float) $q['tax_amount'] - round( (float) $q['tax_amount'] / 2, 2 ) ) ); ?></td></tr>
					<?php else : ?>
						<tr class="muted"><td>IGST <?php echo esc_html( rtrim( rtrim( number_format( $rate, 2 ), '0' ), '.' ) ); ?>%</td><td class="n"><?php echo esc_html( $m( $q['tax_amount'] ) ); ?></td></tr>
					<?php endif; ?>
				<?php endif; ?>
				<tr class="grand"><td><?php esc_html_e( 'Total', 'tripcosmos-agents' ); ?><?php echo ( $rate > 0 && (int) $q['tax_inclusive'] ) ? ' <small>(' . esc_html__( 'incl. GST', 'tripcosmos-agents' ) . ')</small>' : ''; ?></td><td class="n"><?php echo esc_html( $m( $q['total'] ) ); ?></td></tr>
				<?php if ( $amounts['paid'] > 0 ) : ?>
					<tr><td><?php esc_html_e( 'Paid', 'tripcosmos-agents' ); ?></td><td class="n">− <?php echo esc_html( $m( $amounts['paid'] ) ); ?></td></tr>
					<tr class="grand"><td><?php esc_html_e( 'Balance', 'tripcosmos-agents' ); ?></td><td class="n"><?php echo esc_html( $m( $amounts['balance'] ) ); ?></td></tr>
				<?php endif; ?>
			</table>
			<?php if ( $q['notes'] ) : ?><p class="notes"><?php echo nl2br( esc_html( $q['notes'] ) ); ?></p><?php endif; ?>
		</section>

		<?php if ( $payments ) : ?>
			<section class="box">
				<h2><?php esc_html_e( 'Payments received', 'tripcosmos-agents' ); ?></h2>
				<table class="items">
					<?php foreach ( $payments as $p ) : ?>
						<tr><td><?php echo esc_html( mysql2date( 'j M Y', $p['paid_at'] ) ); ?></td><td><?php echo esc_html( ucfirst( $p['method'] ?: $p['provider'] ) ); ?><?php echo $p['reference'] ? ' · ' . esc_html( $p['reference'] ) : ''; ?></td><td class="n"><?php echo esc_html( $m( $p['amount'] ) ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( ! $invoice && $amounts['balance'] > 0 && ( $open || 'accepted' === $q['status'] ) ) : ?>
			<section class="box pay no-print">
				<?php if ( $open && 'accepted' !== $q['status'] ) : ?>
					<form method="post" class="inline">
						<input type="hidden" name="_tcq" value="<?php echo esc_attr( $nonce ); ?>" />
						<input type="hidden" name="tc_q_action" value="accept" />
						<button class="btn ghost"><?php esc_html_e( 'Accept quote', 'tripcosmos-agents' ); ?></button>
					</form>
				<?php endif; ?>
				<?php if ( TC_Payments::cashfree_ready() && $c && strlen( preg_replace( '/\D/', '', (string) $c['phone_e164'] ) ) >= 10 ) : ?>
					<?php if ( $amounts['advance_due'] > 0 ) : ?>
						<form method="post" class="inline">
							<input type="hidden" name="_tcq" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="tc_q_action" value="pay" />
							<input type="hidden" name="what" value="advance" />
							<button class="btn"><?php echo esc_html( sprintf( __( 'Pay advance %s to confirm', 'tripcosmos-agents' ), $m( $amounts['advance_due'] ) ) ); ?></button>
						</form>
					<?php endif; ?>
					<form method="post" class="inline">
						<input type="hidden" name="_tcq" value="<?php echo esc_attr( $nonce ); ?>" />
						<input type="hidden" name="tc_q_action" value="pay" />
						<input type="hidden" name="what" value="balance" />
						<button class="btn <?php echo $amounts['advance_due'] > 0 ? 'ghost' : ''; ?>"><?php echo esc_html( sprintf( __( 'Pay full %s', 'tripcosmos-agents' ), $m( $amounts['balance'] ) ) ); ?></button>
					</form>
					<p class="small"><?php esc_html_e( 'Secure payment by Cashfree: UPI, cards, net banking.', 'tripcosmos-agents' ); ?></p>
				<?php endif; ?>
				<?php
				$upi_amt = $amounts['advance_due'] > 0 ? $amounts['advance_due'] : $amounts['balance'];
				$upi     = TC_Payments::upi_link( $upi_amt, 'Booking ' . $b['ref'] );
				if ( $upi ) :
					?>
					<div class="upi">
						<div id="tc-qr" aria-label="UPI QR code"></div>
						<div>
							<strong><?php echo esc_html( sprintf( __( 'Or pay %s by UPI', 'tripcosmos-agents' ), $m( $upi_amt ) ) ); ?></strong><br />
							<small><?php echo esc_html( $s['upi_vpa'] ); ?></small><br />
							<a class="btn ghost small-btn" href="<?php echo esc_attr( $upi ); ?>"><?php esc_html_e( 'Open UPI app', 'tripcosmos-agents' ); ?></a>
							<p class="small"><?php echo esc_html( sprintf( __( 'After paying by UPI, send the screenshot to us on WhatsApp with reference %s.', 'tripcosmos-agents' ), $b['ref'] ) ); ?></p>
						</div>
					</div>
					<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
					<script>try{var qr=qrcode(0,'M');qr.addData(<?php echo wp_json_encode( $upi ); ?>);qr.make();document.getElementById('tc-qr').innerHTML=qr.createSvgTag({cellSize:4,margin:2});}catch(e){}</script>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! $invoice && $s['terms'] ) : ?>
			<section class="box terms"><h2><?php esc_html_e( 'Terms', 'tripcosmos-agents' ); ?></h2><?php echo wp_kses_post( wpautop( esc_html( $s['terms'] ) ) ); ?></section>
		<?php endif; ?>

		<footer class="no-print">
			<button class="btn ghost" onclick="window.print()"><?php esc_html_e( 'Download / Print', 'tripcosmos-agents' ); ?></button>
			<?php if ( ! $invoice && $b['invoice_no'] ) : ?><a class="btn ghost" href="<?php echo esc_url( TC_Quote_Service::url( $q, true ) ); ?>"><?php echo esc_html( $is_tax || ( $rate > 0 && $s['gstin'] ) ? __( 'Tax invoice', 'tripcosmos-agents' ) : __( 'Receipt', 'tripcosmos-agents' ) ); ?></a><?php endif; ?>
			<?php if ( $invoice ) : ?><a class="btn ghost" href="<?php echo esc_url( TC_Quote_Service::url( $q ) ); ?>"><?php esc_html_e( 'View quote', 'tripcosmos-agents' ); ?></a><?php endif; ?>
			<?php if ( $wa ) : ?><a class="btn ghost" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( sprintf( 'Hello TripCosmos, about my booking %s', $b['ref'] ) ) ); ?>"><?php esc_html_e( 'Questions? WhatsApp us', 'tripcosmos-agents' ); ?></a><?php endif; ?>
		</footer>
		<?php
		self::shell( $title, ob_get_clean() );
	}

	private static function shell( $title, $body ) {
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title><?php echo esc_html( $title . ' · ' . get_bloginfo( 'name' ) ); ?></title>
<style>
:root{--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc;--accent:#4f46e5;--ok:#047857}
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:var(--ink);font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Noto Sans",sans-serif}
main{max-width:820px;margin:0 auto;padding:16px}
.top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.brand{display:flex;gap:12px;align-items:center}.brand img{border-radius:10px}
.doc{text-align:right}.doc h1{margin:0;font-size:20px}.doc div{color:var(--muted);font-size:13px}
.box{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.box h2{font-size:15px;margin:0 0 8px}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.label{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em}
table{width:100%;border-collapse:collapse}.items th{text-align:left;font-size:12px;color:var(--muted);font-weight:600;border-bottom:1px solid var(--line);padding:6px 4px}
.items td{padding:8px 4px;border-bottom:1px solid var(--line);vertical-align:top}.n{text-align:right;white-space:nowrap}
.totals{margin-top:10px;margin-left:auto;max-width:360px}.totals td{padding:4px}.muted td{color:var(--muted);font-size:13px}.grand td{font-weight:700;font-size:17px;border-top:1px solid var(--line);padding-top:8px}
.notice{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:10px;padding:10px 14px;margin-bottom:12px}.notice.warn{background:#fffbeb;border-color:#fde68a;color:#78350f}
.btn{display:inline-block;border:0;border-radius:8px;background:var(--accent);color:#fff;padding:12px 18px;font-size:15px;font-weight:600;text-decoration:none;cursor:pointer;margin:4px 6px 4px 0}
.btn.ghost{background:#fff;color:var(--ink);border:1px solid #cbd5e1}.small-btn{padding:8px 12px;font-size:13px}
form.inline{display:inline}.small{color:var(--muted);font-size:12px;margin:6px 0 0}
.upi{display:flex;gap:14px;align-items:center;margin-top:14px;padding-top:14px;border-top:1px dashed var(--line)}#tc-qr svg{display:block;width:140px;height:140px}
.notes{color:var(--muted);font-size:13px;white-space:normal}.terms{font-size:13px;color:#334155}
footer{text-align:center;padding:8px 0 24px}
@media (max-width:560px){.two{grid-template-columns:1fr}.doc{text-align:left}.upi{flex-direction:column;align-items:flex-start}.btn{width:100%;text-align:center}}
@media print{body{background:#fff}.no-print{display:none!important}.box,.top{border:0;padding:8px 0}main{padding:0}}
</style>
</head>
<body><main><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></main></body>
</html>
		<?php
	}
}
