<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Brevo integration: SMTP/API transactional + push-style marketing sends.
 * Uses https://api.brevo.com/v3/smtp/email with Vault key brevo_api_key.
 */
class TC_Integration_Brevo {
	public static function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'brevo_api_key', '' ) : get_option( 'tc_agents_brevo_api_key', '' );
	}
	public static function is_configured() { return ! empty( self::get_api_key() ); }
	public static function get_sender() {
		$email = get_option( 'tc_agents_brevo_sender_email', get_option( 'admin_email' ) );
		$name = get_option( 'tc_agents_brevo_sender_name', 'TripCosmos' );
		return array( 'email' => $email, 'name' => $name );
	}
	public static function send_email( $to, $subject, $html, $text = '' ) {
		if ( empty( $to ) ) { return new WP_Error( 'brevo_no_recipient', __( 'Recipient missing.', 'tripcosmos-agents' ) ); }
		$sender = self::get_sender();

		// 1. Try Brevo API if configured
		if ( self::is_configured() ) {
			$res = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
				'timeout' => 12, 'sslverify' => true,
				'headers' => array( 'api-key' => self::get_api_key(), 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body' => wp_json_encode( array( 'sender' => $sender, 'to' => array( array( 'email' => $to ) ), 'subject' => $subject, 'htmlContent' => $html, 'textContent' => $text ?: wp_strip_all_tags( $html ) ) ),
			) );
			if ( ! is_wp_error( $res ) ) {
				$code = wp_remote_retrieve_response_code( $res );
				$body = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( $code >= 200 && $code < 300 ) {
					return array( 'success' => true, 'provider' => 'brevo', 'body' => $body );
				}
			}
		}

		// 2. High-reliability fallback to WordPress native wp_mail with HTML headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_attr( $sender['name'] ) . ' <' . esc_attr( $sender['email'] ) . '>',
			'Reply-To: TripCosmos Varanasi Desk <' . esc_attr( $sender['email'] ) . '>',
		);
		$ok = wp_mail( $to, $subject, $html, $headers );
		if ( $ok ) {
			return array( 'success' => true, 'provider' => 'wp_mail' );
		}

		return new WP_Error( 'email_dispatch_failed', __( 'Could not deliver email via Brevo or wp_mail.', 'tripcosmos-agents' ) );
	}
	/**
	 * Send Transactional SMS via Brevo (Driver dispatch / OTP / Booking confirmation).
	 */
	public static function send_sms( $to, $content, $sender = 'TRIPCO' ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'brevo_not_configured', __( 'Brevo API key missing.', 'tripcosmos-agents' ) );
		}
		if ( empty( $to ) ) {
			return new WP_Error( 'brevo_no_recipient', __( 'Recipient missing.', 'tripcosmos-agents' ) );
		}

		$clean_phone = preg_replace( '/[^\d]/', '', $to );
		if ( strlen( $clean_phone ) === 10 ) {
			$clean_phone = '91' . $clean_phone;
		}

		$res = wp_remote_post(
			'https://api.brevo.com/v3/transactionalSMS/sms',
			array(
				'timeout'   => 12,
				'sslverify' => true,
				'headers'   => array(
					'api-key'      => self::get_api_key(),
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'      => wp_json_encode(
					array(
						'sender'    => substr( $sender, 0, 11 ),
						'recipient' => '+' . $clean_phone,
						'content'   => $content,
						'type'      => 'transactional',
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'body' => $body );
		}

		$error_msg = ! empty( $body['message'] ) ? $body['message'] : sprintf( __( 'Brevo SMS HTTP %d', 'tripcosmos-agents' ), $code );
		return new WP_Error( 'brevo_sms_error', $error_msg );
	}

	/**
	 * Build responsive luxury B2B Wholesale Partnership Email HTML.
	 */
	public static function build_b2b_html( $agency ) {
		$name      = ! empty( $agency['name'] ) ? esc_html( $agency['name'] ) : 'Partner';
		$city      = ! empty( $agency['city'] ) ? esc_html( $agency['city'] ) : 'India';
		$wa_raw    = '919336116210';
		$wa_text   = rawurlencode( sprintf( 'Namaste TripCosmos DMC! We received your B2B proposal for %s (%s). Please share your net rate tariff sheet and wholesale commission slabs.', $name, $city ) );
		$wa_url    = "https://wa.me/{$wa_raw}?text={$wa_text}";
		$call_url  = 'tel:+919336116210';
		$site_url  = esc_url( home_url( '/tour-packages/' ) );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TripCosmos B2B Wholesale Partnership</title>
<style>
body { margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased; }
table { border-collapse:collapse; }
img { border:0; outline:none; text-decoration:none; }
.wrapper { width:100%; max-width:640px; margin:24px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(15,23,42,0.1); border:1px solid #e2e8f0; }
.header { background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #312e81 100%); padding:36px 32px; text-align:center; color:#ffffff; }
.header .badge { display:inline-block; padding:5px 14px; background:rgba(245,158,11,0.22); border:1px solid rgba(245,158,11,0.5); border-radius:999px; font-size:12px; font-weight:700; color:#fbbf24; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px; }
.header h1 { margin:0; font-size:24px; font-weight:800; line-height:1.25; color:#ffffff; }
.header p { margin:8px 0 0 0; font-size:14px; color:#cbd5e1; }
.body-content { padding:32px 32px 24px 32px; }
.greeting { font-size:17px; font-weight:700; color:#0f172a; margin-bottom:16px; }
.intro { font-size:15px; line-height:1.65; color:#334155; margin-bottom:24px; }
.grid-table { width:100%; margin:20px 0; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; }
.grid-table td { padding:14px 16px; border-bottom:1px solid #e2e8f0; font-size:14px; vertical-align:top; }
.grid-table tr:last-child td { border-bottom:none; }
.grid-table .col-title { font-weight:700; color:#0f172a; width:34%; }
.grid-table .col-desc { color:#475569; }
.cta-box { background:linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border:1px solid #86efac; border-radius:14px; padding:24px; text-align:center; margin:28px 0; }
.cta-box h3 { margin:0 0 8px 0; font-size:18px; font-weight:800; color:#065f46; }
.cta-box p { margin:0 0 20px 0; font-size:14px; color:#047857; line-height:1.5; }
.btn-wa { display:inline-block; background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color:#ffffff !important; text-decoration:none; font-weight:800; font-size:16px; padding:15px 32px; border-radius:999px; box-shadow:0 6px 18px rgba(34,197,94,0.38); letter-spacing:-0.01em; }
.btn-wa:hover { background:#15803d; }
.btn-call { display:inline-block; margin-top:12px; font-size:13px; font-weight:600; color:#0284c7; text-decoration:none; }
.perks { margin:24px 0; padding:0; list-style:none; }
.perks li { font-size:13.5px; color:#475569; margin-bottom:10px; padding-left:24px; position:relative; line-height:1.45; }
.perks li::before { content:"✓"; position:absolute; left:0; color:#16a34a; font-weight:800; font-size:16px; top:-1px; }
.footer { background:#f8fafc; border-top:1px solid #e2e8f0; padding:24px 32px; text-align:center; font-size:12px; color:#64748b; line-height:1.6; }
.footer a { color:#ea580c; text-decoration:none; font-weight:600; }
@media only screen and (max-width: 480px) {
  .header { padding:24px 20px; }
  .body-content { padding:20px 18px; }
  .grid-table .col-title { width:100%; display:block; padding-bottom:4px; }
  .grid-table .col-desc { display:block; padding-top:0; }
  .btn-wa { display:block; padding:14px 20px; font-size:15px; }
}
</style>
</head>
<body>
<div class="wrapper">
  <!-- Header -->
  <div class="header">
    <div class="badge">Verified On-Ground DMC • Varanasi</div>
    <h1>B2B Wholesale Tour &amp; Cab Tariffs</h1>
    <p>Exclusive Agency Slabs for Varanasi, Ayodhya, Prayagraj &amp; Gaya Circuits</p>
  </div>

  <!-- Body Content -->
  <div class="body-content">
    <div class="greeting">Namaste <?php echo $name; ?> Team 🙏,</div>
    <div class="intro">
      TripCosmos is Varanasi's premier on-ground Destination Management Company (DMC). We serve as your reliable <strong>100% white-label ground execution partner</strong> across Uttar Pradesh and Bihar sacred corridors, guaranteeing seamless guest experiences under your agency brand.
    </div>

    <!-- Wholesale Slabs Table -->
    <table class="grid-table" cellpadding="0" cellspacing="0">
      <tr>
        <td class="col-title">🚗 Dedicated Fleet</td>
        <td class="col-desc">Innova Crysta, Ertiga, Swift Dzire &amp; 17/26-Seater Luxury Force Urbania/Tempo Travellers. Verified commercial yellow plates &amp; disciplined drivers.</td>
      </tr>
      <tr>
        <td class="col-title">🛕 Kashi VIP Darshan</td>
        <td class="col-desc">Official Sugam Darshan passes, priority corridor queue assistance, and Rudrabhishek / Mangla Aarti coordination.</td>
      </tr>
      <tr>
        <td class="col-title">⛵ Ganga Aarti Boats</td>
        <td class="col-desc">Private 80-seat &amp; 30-seat Bajra barges, private motorboats, and Subah-e-Banaras sunrise cruise with live Shehnai.</td>
      </tr>
      <tr>
        <td class="col-title">🪔 Dev Deepawali 2026</td>
        <td class="col-desc">Exclusive pre-allotted Bajra boats and prime Ganga-facing rooftop hospitality for November 24, 2026.</td>
      </tr>
      <tr>
        <td class="col-title">⛺ Magh Mela 2027</td>
        <td class="col-desc">Luxury tentage and Triveni Sangam Snan boats at Prayagraj with full outstation transfer support.</td>
      </tr>
      <tr>
        <td class="col-title">🏷️ White-Label Service</td>
        <td class="col-desc">Your agency placard at airport/station pickup, customized itineraries with your logo, and 24/7 dedicated guest helpline.</td>
      </tr>
    </table>

    <!-- High-Converting WhatsApp CTA Box -->
    <div class="cta-box">
      <h3>Get Your Net Wholesale Tariff Sheet</h3>
      <p>Instant activation on WhatsApp. Discuss commission slabs, contracting rates &amp; lock festive allotments with our B2B desk.</p>
      <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="btn-wa">
        💬 Chat on WhatsApp (+91 93361 16210)
      </a>
      <br>
      <a href="<?php echo esc_attr( $call_url ); ?>" class="btn-call">
        📞 Or Call B2B Desk: +91 93361 16210
      </a>
    </div>

    <ul class="perks">
      <li><strong>Net B2B Rates:</strong> Protect your retail markups with transparent wholesale contractor pricing.</li>
      <li><strong>Instant Confirmations:</strong> 2-minute response SLA via WhatsApp for custom itineraries &amp; cab bookings.</li>
      <li><strong>Zero Disruption Guarantee:</strong> 24x7 local Varanasi support team resolving any on-ground requirement.</li>
    </ul>
  </div>

  <!-- Footer -->
  <div class="footer">
    <strong>TripCosmos Destination Management Company</strong><br>
    Sigra, Varanasi, Uttar Pradesh 221010, India<br>
    WhatsApp: <a href="https://wa.me/919336116210">+91 93361 16210</a> &bull; Web: <a href="<?php echo $site_url; ?>">tripcosmos.co</a><br>
    <small style="color:#94a3b8; margin-top:8px; display:block;">You received this contracting brief because <?php echo $name; ?> is registered as a licensed travel agency or tour operator.</small>
  </div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Push B2B outreach: send to agency contact, log outcome.
	 */
	public static function send_b2b_outreach( $agency ) {
		$email = $agency['email'] ?? '';
		if ( empty( $email ) ) { return new WP_Error( 'no_email', __( 'Agency has no email.', 'tripcosmos-agents' ) ); }
		$name = $agency['name'] ?? 'Partner';
		$subject = sprintf( __( 'B2B Wholesale Tour & Cab Tariffs for %s — TripCosmos Varanasi DMC', 'tripcosmos-agents' ), $name );
		$html = self::build_b2b_html( $agency );
		$text = sprintf(
			"Namaste %s Team 🙏,\n\nTripCosmos is Varanasi's premier on-ground Destination Management Company (DMC). We provide 100%% white-label execution for your guests across Varanasi, Ayodhya, Prayagraj and Gaya circuits.\n\nOur Wholesale Offerings:\n• Dedicated Fleet: Innova Crysta, Ertiga, Urbania & Luxury Tempo Travellers\n• Kashi Vishwanath VIP Sugam Darshan passes & protocol coordination\n• Private Ganga Aarti Bajra boats & Dev Deepawali 2026 allotments\n• Prayagraj Sangam boats & Magh Mela 2027 luxury tents\n\n📲 Chat on WhatsApp for Net B2B Tariffs: https://wa.me/919336116210\n📞 Direct Desk Call: +91 93361 16210\n\nTripCosmos B2B Desk, Sigra, Varanasi, UP 221010",
			$name
		);
		return self::send_email( $email, $subject, $html, $text );
	}
}
