<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Brevo integration: SMTP/API transactional + push-style marketing sends.
 * Uses https://api.brevo.com/v3/smtp/email with Vault key brevo_api_key.
 */
class TC_Integration_Brevo {
	public static function get_api_key() {
		$key = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'brevo_api_key', '' ) : '';
		if ( empty( $key ) ) {
			$key = get_option( 'tc_agents_brevo_api_key', '' );
		}
		if ( empty( $key ) ) {
			$key = get_option( 'sib_api_key_v3', '' );
		}
		return $key;
	}
	public static function is_configured() { return ! empty( self::get_api_key() ); }
	public static function get_sender() {
		$email = get_option( 'tc_agents_brevo_sender_email', 'sales@tripcosmos.co' );
		$name  = get_option( 'tc_agents_brevo_sender_name', 'TripCosmos B2B Desk' );
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
		$name          = ! empty( $agency['name'] ) ? esc_html( $agency['name'] ) : 'Partner';
		$city          = ! empty( $agency['city'] ) ? esc_html( $agency['city'] ) : 'India';
		$wa_raw        = '919336116210';
		$wa_text       = rawurlencode( sprintf( 'Namaste TripCosmos DMC! We received your B2B proposal for %s (%s). Please share your net rate tariff sheet and wholesale commission slabs.', $name, $city ) );
		$wa_url        = "https://wa.me/{$wa_raw}?text={$wa_text}";
		$call_url      = 'tel:+919336116210';
		$site_url      = esc_url( home_url( '/tour-packages/' ) );
		$itinerary_url = esc_url( home_url( '/tour-packages/dev-deepawali-varanasi/' ) );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>TripCosmos &bull; B2B Wholesale DMC Partnership</title>
<!--[if mso]>
<style type="text/css">
body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif !important; }
</style>
<![endif]-->
<style>
body { margin:0; padding:0; background-color:#0b0f19; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#334155; -webkit-font-smoothing:antialiased; }
table { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }
img { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }
.wrapper { width:100%; max-width:640px; margin:28px auto; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 20px 45px rgba(0,0,0,0.45); border:1px solid #1e293b; }
.header { background:linear-gradient(135deg, #090d16 0%, #151c2e 50%, #1e293b 100%); padding:40px 36px 32px 36px; text-align:center; color:#ffffff; border-bottom:3px solid #d4af37; }
.brand-title { font-size:24px; font-weight:800; letter-spacing:0.02em; color:#ffffff; margin:0 0 6px 0; }
.brand-title span { background:linear-gradient(135deg, #f59e0b 0%, #d4af37 100%); -webkit-background-clip:text; -webkit-text-fill-color:#f59e0b; }
.badge { display:inline-block; padding:5px 14px; background:rgba(212,175,55,0.14); border:1px solid rgba(212,175,55,0.45); border-radius:999px; font-size:11px; font-weight:800; color:#fbbf24; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:14px; }
.header h2 { margin:8px 0 0 0; font-size:20px; font-weight:700; color:#ffffff; line-height:1.3; }
.header p { margin:8px 0 0 0; font-size:13.5px; color:#94a3b8; font-weight:400; line-height:1.5; }
.body-content { padding:36px 36px 28px 36px; background:#ffffff; }
.greeting { font-size:18px; font-weight:700; color:#0f172a; margin-bottom:14px; }
.intro { font-size:14.5px; line-height:1.7; color:#334155; margin-bottom:24px; }
.trust-banner { background:#f8fafc; border-left:4px solid #d4af37; padding:12px 18px; border-radius:0 10px 10px 0; margin-bottom:26px; font-size:13px; color:#475569; line-height:1.55; }
.trust-banner strong { color:#0f172a; }
.grid-table { width:100%; margin:24px 0; background:#f8fafc; border-radius:14px; border:1px solid #e2e8f0; overflow:hidden; }
.grid-table td { padding:15px 18px; border-bottom:1px solid #e2e8f0; font-size:13.5px; vertical-align:top; }
.grid-table tr:last-child td { border-bottom:none; }
.grid-table .col-title { font-weight:700; color:#0f172a; width:34%; }
.grid-table .col-desc { color:#475569; line-height:1.55; }
.cta-box { background:linear-gradient(135deg, #090d16 0%, #151c2e 100%); border:1px solid rgba(212,175,55,0.4); border-radius:16px; padding:28px 24px; text-align:center; margin:32px 0 24px 0; color:#ffffff; box-shadow:0 10px 25px rgba(0,0,0,0.25); }
.cta-box h3 { margin:0 0 8px 0; font-size:19px; font-weight:800; color:#ffffff; letter-spacing:-0.01em; }
.cta-box h3 span { color:#fbbf24; }
.cta-box p { margin:0 0 22px 0; font-size:13.5px; color:#cbd5e1; line-height:1.5; }
.btn-wa { display:inline-block; background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color:#ffffff !important; text-decoration:none; font-weight:800; font-size:15px; padding:14px 34px; border-radius:999px; box-shadow:0 6px 20px rgba(34,197,94,0.45); letter-spacing:0.01em; }
.btn-wa:hover { background:#15803d; }
.btn-call { display:inline-block; margin-top:14px; font-size:12.5px; font-weight:700; color:#38bdf8; text-decoration:none; }
.perks { margin:24px 0; padding:0; list-style:none; }
.perks li { font-size:13.5px; color:#475569; margin-bottom:10px; padding-left:26px; position:relative; line-height:1.5; }
.perks li::before { content:"✓"; position:absolute; left:0; color:#16a34a; font-weight:900; font-size:15px; top:0; }
.footer { background:#090d16; border-top:1px solid #1e293b; padding:28px 36px; text-align:center; font-size:12px; color:#94a3b8; line-height:1.65; }
.footer a { color:#fbbf24; text-decoration:none; font-weight:600; }
.footer strong { color:#ffffff; }
@media only screen and (max-width: 480px) {
  .header { padding:28px 20px 24px 20px; }
  .body-content { padding:24px 18px; }
  .grid-table .col-title { width:100%; display:block; padding-bottom:4px; }
  .grid-table .col-desc { display:block; padding-top:0; }
  .btn-wa { display:block; padding:14px 20px; font-size:14.5px; }
  .footer { padding:20px 18px; }
}
</style>
</head>
<body>
<div style="display:none;font-size:1px;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;">
Exclusive B2B Ground Handling &amp; DMC Net Tariffs: Dev Deepawali Bajra Charters, Kashi VIP Sugam Passes &amp; AC Fleet for <?php echo $name; ?>.
</div>
<div class="wrapper">
  <!-- Header -->
  <div class="header">
    <div class="badge">Verified On-Ground DMC &bull; Varanasi &bull; Ayodhya &bull; Prayagraj</div>
    <div class="brand-title"><span>TRIPCOSMOS</span> &bull; B2B PARTNER DESK</div>
    <h2>Confidential Wholesale Tour &amp; Cab Tariffs</h2>
    <p>Guaranteed Net Rates &amp; 100% White-Label Ground Handling for Travel Agencies</p>
  </div>

  <!-- Body Content -->
  <div class="body-content">
    <div class="greeting">Namaste <?php echo $name; ?> Team 🙏,</div>
    <div class="intro">
      TripCosmos is Varanasi's premier on-ground Destination Management Company (DMC). We serve as your verified <strong>100% white-label ground execution partner</strong> across Uttar Pradesh and Bihar sacred corridors, guaranteeing seamless guest experiences delivered strictly under your own agency brand.
    </div>

    <div class="trust-banner">
      <strong>Protected B2B Margins:</strong> All net tariffs include 15% to 25%+ protected retail margins for partner agencies. Airport placards, chauffeur greeting cards, and day-wise itineraries carry <strong>your agency name &amp; logo with zero TripCosmos branding</strong>.
    </div>

    <!-- Wholesale Slabs Table -->
    <table class="grid-table" cellpadding="0" cellspacing="0">
      <tr>
        <td class="col-title">🚗 Dedicated Fleet</td>
        <td class="col-desc">Toyota Innova Crysta, Swift Dzire, Ertiga &amp; 12/17/26-Seater Force Urbania &amp; Tempo Travellers. Verified yellow-plate commercial fleet with vetted, courteous chauffeurs.</td>
      </tr>
      <tr>
        <td class="col-title">🛕 Kashi VIP Darshan</td>
        <td class="col-desc">Official Sugam Darshan passes, priority sanctum corridor escort (bypassing 4-hour queues), and Vedic Shastri puja coordination.</td>
      </tr>
      <tr>
        <td class="col-title">⛵ Ganga Aarti Boats</td>
        <td class="col-desc">Private 80-seat &amp; 30-seat Bajra barges, private wooden boats, and Subah-e-Banaras sunrise musical cruises at Dashashwamedh Ghat.</td>
      </tr>
      <tr>
        <td class="col-title">🪔 Dev Deepawali 2026</td>
        <td class="col-desc">Exclusive pre-allocated Bajra boats &amp; prime Dashashwamedh Aarti viewing decks for Kartik Poornima. <a href="<?php echo $itinerary_url; ?>" target="_blank" style="color:#d97706;font-weight:700;">View 2N3D Partner Package &rarr;</a></td>
      </tr>
      <tr>
        <td class="col-title">⛺ Magh Mela 2027</td>
        <td class="col-desc">Luxury riverside Swiss tentage and Triveni Sangam Snan boats at Prayagraj with full outstation cab transfer support.</td>
      </tr>
      <tr>
        <td class="col-title">🏷️ 100% White-Label</td>
        <td class="col-desc">Your agency placard at airport/station pickup, customized itineraries with your logo, and 24/7 dedicated guest helpline.</td>
      </tr>
    </table>

    <!-- High-Converting CTA Box -->
    <div class="cta-box">
      <h3>Get Your <span>Confidential B2B Net Rate Card</span></h3>
      <p>Instant activation on WhatsApp. Discuss commission slabs, contracting rates &amp; lock festive allocations directly with our B2B trade directors.</p>
      <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="btn-wa">
        💬 Connect on WhatsApp (+91 93361 16210)
      </a>
      <br>
      <a href="<?php echo esc_attr( $call_url ); ?>" class="btn-call">
        📞 Or Call B2B Desk Direct: +91 93361 16210
      </a>
    </div>

    <ul class="perks">
      <li><strong>Net B2B Rates:</strong> Protect your retail markups with transparent wholesale contractor pricing.</li>
      <li><strong>Instant Confirmations:</strong> 2-minute response SLA via WhatsApp for custom itineraries &amp; cab bookings.</li>
      <li><strong>Zero Disruption Guarantee:</strong> 24x7 local Varanasi operations team resolving any on-ground requirement.</li>
    </ul>
  </div>

  <!-- Footer -->
  <div class="footer">
    <strong>TripCosmos Destination Management Company (DMC)</strong><br>
    Sigra &bull; Shivala, Varanasi, Uttar Pradesh 221010, India<br>
    WhatsApp: <a href="https://wa.me/919336116210">+91 93361 16210</a> &bull; Web: <a href="<?php echo $site_url; ?>">tripcosmos.co</a><br>
    <small style="color:#64748b; margin-top:10px; display:block;">You received this contracting brief because <?php echo $name; ?> is registered as a licensed travel agency or tour operator in India.</small>
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
			"Namaste %s Team 🙏,\n\nTripCosmos is Varanasi's premier on-ground Destination Management Company (DMC). We provide 100%% white-label ground execution for your guests across Varanasi, Ayodhya, Prayagraj and Gaya sacred corridors.\n\nOur Wholesale Offerings:\n• Dedicated Commercial Fleet: Innova Crysta, Swift Dzire, Force Urbania & Luxury Tempo Travellers\n• Kashi Vishwanath VIP Sugam Darshan passes & Vedic Shastri escorts\n• Private Ganga Aarti Bajra boats & Dev Deepawali 2026 pre-allocated decks\n• Prayagraj Sangam boats & Magh Mela 2027 luxury riverside tentage\n• 100%% White-Label execution under your agency branding\n\n💬 Connect on WhatsApp for Net B2B Tariffs: https://wa.me/919336116210\n📞 Direct Desk Call: +91 93361 16210\n\nTripCosmos B2B Partner Desk, Sigra, Varanasi, UP 221010",
			$name
		);
		return self::send_email( $email, $subject, $html, $text );
	}
}
