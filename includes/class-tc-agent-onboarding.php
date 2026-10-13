<?php
/**
 * Agent 1-Tap Onboarding & Magic Login Service.
 *
 * Handles zero-friction sales agent onboarding via WhatsApp,
 * secure hashed one-click magic login into WordPress, and zero-touch
 * pairing payload generation for the X-Agents companion app.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Onboarding {

	/**
	 * Hook initialization.
	 */
	public static function init() {
		// Intercept magic login route
		add_action( 'template_redirect', array( __CLASS__, 'handle_magic_login' ) );

		// AJAX endpoints for admin team management
		add_action( 'wp_ajax_tc_invite_agent', array( __CLASS__, 'ajax_invite_agent' ) );
		add_action( 'wp_ajax_tc_resend_agent_invite', array( __CLASS__, 'ajax_resend_invite' ) );
		add_action( 'wp_ajax_tc_get_agent_pairing_qr', array( __CLASS__, 'ajax_get_pairing_qr' ) );
	}

	/**
	 * Clean and format Indian / international phone number.
	 */
	public static function sanitize_phone( $phone ) {
		$clean = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( 10 === strlen( $clean ) ) {
			$clean = '91' . $clean;
		}
		return $clean;
	}

	/**
	 * Generate a cryptographically secure magic login token.
	 * Returns plaintext token for one-time sending, and saves sha256 hash in user meta.
	 */
	public static function generate_magic_token( $user_id, $expiry_days = 7 ) {
		$user_id = (int) $user_id;
		$token   = 'tcm_' . wp_generate_password( 40, false, false );
		$hash    = hash( 'sha256', $token );
		$exp     = time() + ( $expiry_days * DAY_IN_SECONDS );

		update_user_meta( $user_id, 'tc_magic_token_hash', $hash );
		update_user_meta( $user_id, 'tc_magic_token_exp', $exp );

		return $token;
	}

	/**
	 * Build the full magic login URL.
	 */
	public static function get_magic_login_url( $user_id, $token ) {
		return add_query_arg(
			array(
				'tc_action' => 'agent_login',
				'agent'     => (int) $user_id,
				'token'     => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Get or generate mobile API token for a specific WordPress user.
	 */
	public static function get_or_create_mobile_token( $user_id ) {
		$user_id = (int) $user_id;
		$tokens  = TC_Agents_Security::agent_tokens();

		// Check if active token exists for this user
		foreach ( $tokens as $id => $row ) {
			if ( (int) ( $row['user_id'] ?? 0 ) === $user_id ) {
				// We cannot recover the plaintext token if only hash is stored.
				// If plaintext was not saved, create a fresh token.
				break;
			}
		}

		$user  = get_userdata( $user_id );
		$label = $user ? $user->display_name : 'Agent #' . $user_id;
		$res   = TC_Agents_Security::create_agent_token( $label, $user_id );

		return $res['token'];
	}

	/**
	 * Invite a new or existing agent via WhatsApp.
	 *
	 * @param string $name          Agent Display Name.
	 * @param string $phone         Phone number with or without +91.
	 * @param string $role          'tc_sales_agent' or 'tc_sales_manager'.
	 * @param bool   $send_whatsapp Whether to dispatch message via Evolution API.
	 * @return array
	 */
	public static function invite_agent( $name, $phone, $role = TC_CRM_Access::ROLE_AGENT, $send_whatsapp = true ) {
		$name        = sanitize_text_field( $name );
		$clean_phone = self::sanitize_phone( $phone );

		if ( empty( $name ) || strlen( $clean_phone ) < 10 ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid agent name and phone number (minimum 10 digits).', 'tripcosmos-agents' ),
			);
		}

		// Ensure CRM roles exist
		TC_CRM_Access::install_roles();

		// Check if user exists by WhatsApp phone meta or username/email
		$user_id = 0;
		$matched = get_users(
			array(
				'meta_key'   => 'tc_crm_whatsapp',
				'meta_value' => '+' . $clean_phone,
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		if ( empty( $matched ) ) {
			$matched = get_users(
				array(
					'meta_key'   => 'tc_crm_whatsapp',
					'meta_value' => $clean_phone,
					'number'     => 1,
					'fields'     => 'ID',
				)
			);
		}

		if ( ! empty( $matched ) ) {
			$user_id = (int) $matched[0];
		} else {
			// Find by email or generate username
			$email = sanitize_email( 'agent.' . $clean_phone . '@tripcosmos.co' );
			$existing_user = get_user_by( 'email', $email );
			if ( $existing_user ) {
				$user_id = $existing_user->ID;
			} else {
				$username = 'agent_' . substr( $clean_phone, -6 );
				if ( username_exists( $username ) ) {
					$username .= '_' . wp_rand( 10, 99 );
				}
				$password = wp_generate_password( 24, true );
				$user_id  = wp_create_user( $username, $password, $email );

				if ( is_wp_error( $user_id ) ) {
					return array(
						'success' => false,
						'message' => $user_id->get_error_message(),
					);
				}
			}
		}

		// Update user profile
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->set_role( in_array( $role, array( TC_CRM_Access::ROLE_AGENT, TC_CRM_Access::ROLE_MANAGER ), true ) ? $role : TC_CRM_Access::ROLE_AGENT );
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => $name,
				)
			);
		}

		// Save agent metadata
		update_user_meta( $user_id, 'tc_crm_whatsapp', '+' . $clean_phone );
		update_user_meta( $user_id, 'tc_crm_rotation', '1' ); // Put into auto-assignment
		update_user_meta( $user_id, 'tc_crm_away', '0' );

		// Generate Mobile API Token & Magic Login Token
		$mobile_token = self::get_or_create_mobile_token( $user_id );
		$magic_token  = self::generate_magic_token( $user_id, 14 ); // Valid 14 days
		$magic_url    = self::get_magic_login_url( $user_id, $magic_token );

		// Prepare WhatsApp message
		$wa_status  = false;
		$wa_message = '';
		if ( $send_whatsapp && class_exists( 'TC_Integration_Evolution' ) ) {
			$role_title = ( $role === TC_CRM_Access::ROLE_MANAGER ) ? 'Sales Manager' : 'Sales AI & CRM Specialist';
			$wa_text    = "Namaste {$name}! 🙏\n\n"
				. "Welcome to the *TripCosmos.co* Executive Sales Team as *{$role_title}*.\n\n"
				. "Your mobile companion app & CRM workspace are ready for 1-tap activation:\n\n"
				. "🚀 *1-Tap Instant Login & Setup:*\n"
				. "{$magic_url}\n\n"
				. "📱 *X-Agents App Pairing Key:* `{$mobile_token}`\n\n"
				. "Tap the link above on your phone to open your workspace, view incoming pilgrim leads, and activate real-time customer care.\n\n"
				. "— *TripCosmos.co Operations Desk*";

			$res = TC_Integration_Evolution::send_message( '+' . $clean_phone, $wa_text );
			if ( ! is_wp_error( $res ) && ! empty( $res['success'] ) ) {
				$wa_status = true;
			} else {
				$wa_message = is_wp_error( $res ) ? $res->get_error_message() : 'WhatsApp dispatch queued';
			}
		}

		return array(
			'success'      => true,
			'user_id'      => $user_id,
			'name'         => $name,
			'phone'        => '+' . $clean_phone,
			'magic_url'    => $magic_url,
			'mobile_token' => $mobile_token,
			'wa_sent'      => $wa_status,
			'wa_note'      => $wa_message,
		);
	}

	/**
	 * Handle 1-Tap Magic Login Request on frontend.
	 */
	public static function handle_magic_login() {
		if ( ! isset( $_GET['tc_action'] ) || 'agent_login' !== $_GET['tc_action'] ) {
			return;
		}

		$ip = TC_Agents_Security::client_ip();
		// Anti-brute force rate limit: 12 attempts per 5 minutes per IP
		if ( ! TC_Agents_Security::rate_limit( 'magic_login', $ip, 12, 300 ) ) {
			wp_die( 'Too many login attempts. Please wait 5 minutes and try again.', 'Rate Limited', array( 'response' => 429 ) );
		}

		$user_id = absint( $_GET['agent'] ?? 0 );
		$token   = sanitize_text_field( $_GET['token'] ?? '' );

		if ( ! $user_id || empty( $token ) ) {
			wp_die( 'Invalid or incomplete login link.', 'Authentication Error', array( 'response' => 400 ) );
		}

		$stored_hash = (string) get_user_meta( $user_id, 'tc_magic_token_hash', true );
		$stored_exp  = (int) get_user_meta( $user_id, 'tc_magic_token_exp', true );
		$given_hash  = hash( 'sha256', $token );

		if ( empty( $stored_hash ) || ! hash_equals( $stored_hash, $given_hash ) ) {
			wp_die( 'This magic login link has expired or has already been used. Please request a new invite from your manager.', 'Link Invalid', array( 'response' => 403 ) );
		}

		if ( $stored_exp < time() ) {
			wp_die( 'This magic login link has expired. Please ask your administrator to send a fresh invite.', 'Link Expired', array( 'response' => 403 ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( 'User account not found.', 'Error', array( 'response' => 404 ) );
		}

		// Log into WordPress session safely
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true ); // Remember 14 days

		// Retrieve mobile app token
		$mobile_token = self::get_or_create_mobile_token( $user_id );
		$api_url      = rest_url( 'tc-agents/v1' );

		// Mobile app pairing payload
		$is_manager      = in_array( TC_CRM_Access::ROLE_MANAGER, (array) $user->roles, true ) || in_array( 'administrator', (array) $user->roles, true );
		$pairing_payload = rawurlencode(
			wp_json_encode(
				array(
					'url'        => $api_url,
					'token'      => $mobile_token,
					'name'       => $user->display_name,
					'agent_id'   => $user->ID,
					'role'       => $is_manager ? 'manager' : 'agent',
					'paired_ai'  => get_user_meta( $user->ID, 'tc_paired_ai_name', true ) ?: 'Aditi AI',
				)
			)
		);

		$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . $pairing_payload;
		$deep_link    = 'xagents://pair?payload=' . $pairing_payload;
		$crm_admin_url = admin_url( 'admin.php?page=tc-agents-contacts' );

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Welcome, <?php echo esc_html( $user->display_name ); ?> — TripCosmos Sales Companion</title>
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
			<style>
				* { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
					background: #090d16;
					color: #ffffff;
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
					padding: 24px 16px;
				}
				.tc-onboard-card {
					background: linear-gradient(145deg, #111827 0%, #1f2937 100%);
					border: 1px solid rgba(245, 158, 11, 0.3);
					border-radius: 24px;
					max-width: 480px;
					width: 100%;
					padding: 36px 28px;
					box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
					text-align: center;
				}
				.tc-avatar {
					width: 72px;
					height: 72px;
					background: linear-gradient(135deg, #f59e0b, #d97706);
					color: #000;
					border-radius: 20px;
					display: inline-flex;
					align-items: center;
					justify-content: center;
					font-size: 32px;
					font-weight: 800;
					margin-bottom: 20px;
					box-shadow: 0 8px 24px rgba(245, 158, 11, 0.35);
				}
				h1 {
					font-size: 24px;
					font-weight: 800;
					margin-bottom: 6px;
					letter-spacing: -0.5px;
				}
				.tc-role-pill {
					display: inline-block;
					background: rgba(16, 185, 129, 0.15);
					color: #10b981;
					border: 1px solid rgba(16, 185, 129, 0.3);
					padding: 4px 14px;
					border-radius: 20px;
					font-size: 12px;
					font-weight: 700;
					margin-bottom: 22px;
					text-transform: uppercase;
					letter-spacing: 0.5px;
				}
				p.desc {
					color: #9ca3af;
					font-size: 13.5px;
					line-height: 1.5;
					margin-bottom: 24px;
				}
				.tc-qr-box {
					background: #ffffff;
					padding: 14px;
					border-radius: 18px;
					display: inline-block;
					box-shadow: 0 10px 30px rgba(0,0,0,0.4);
					margin-bottom: 20px;
				}
				.tc-qr-box img {
					display: block;
					width: 200px;
					height: 200px;
					border-radius: 8px;
				}
				.tc-btn-primary {
					display: block;
					width: 100%;
					background: linear-gradient(135deg, #10b981 0%, #059669 100%);
					color: #ffffff;
					text-decoration: none;
					font-weight: 700;
					font-size: 15px;
					padding: 14px 20px;
					border-radius: 14px;
					margin-bottom: 12px;
					box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
					transition: transform 0.15s ease;
				}
				.tc-btn-primary:active { transform: scale(0.98); }
				.tc-btn-secondary {
					display: block;
					width: 100%;
					background: rgba(255, 255, 255, 0.08);
					color: #fcd34d;
					text-decoration: none;
					font-weight: 600;
					font-size: 14px;
					padding: 12px 20px;
					border-radius: 14px;
					border: 1px solid rgba(245, 158, 11, 0.3);
					margin-bottom: 16px;
				}
				.tc-token-snippet {
					background: rgba(0, 0, 0, 0.35);
					border: 1px dashed rgba(255, 255, 255, 0.2);
					padding: 8px 12px;
					border-radius: 10px;
					font-family: monospace;
					font-size: 12px;
					color: #94a3b8;
					display: flex;
					align-items: center;
					justify-content: space-between;
					margin-top: 16px;
				}
				.tc-copy-btn {
					background: transparent;
					border: none;
					color: #f59e0b;
					cursor: pointer;
					font-weight: 700;
					font-size: 12px;
				}
			</style>
		</head>
		<body>
			<div class="tc-onboard-card">
				<div class="tc-avatar">
					<?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?>
				</div>
				<h1>Namaste, <?php echo esc_html( $user->display_name ); ?>! 🙏</h1>
				<span class="tc-role-pill">
					● <?php echo $is_manager ? 'Sales Manager Workspace' : 'Official Sales Agent'; ?>
				</span>
				<p class="desc">
					You are successfully verified. Your companion app will route pilgrim bookings, outstation cab inquiries, and hotel reservations directly to your phone.
				</p>

				<div class="tc-qr-box">
					<img src="<?php echo esc_url( $qr_image_url ); ?>" alt="X-Agents Pairing QR Code" />
				</div>
				<p style="font-size: 11px; color: #94a3b8; margin-bottom: 20px;">
					Scan this code in <strong>X-Agents App</strong> or tap the button below:
				</p>

				<a href="<?php echo esc_url( $deep_link ); ?>" class="tc-btn-primary">
					📱 1-Tap Connect X-Agents App
				</a>

				<a href="<?php echo esc_url( $crm_admin_url ); ?>" class="tc-btn-secondary">
					🖥️ Continue to WordPress CRM Desk &rarr;
				</a>

				<div class="tc-token-snippet">
					<span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px;">
						Pairing Key: <?php echo esc_html( $mobile_token ); ?>
					</span>
					<button type="button" class="tc-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js( $mobile_token ); ?>'); alert('Copied secret pairing key!');">
						Copy
					</button>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * AJAX Handler: Invite a new agent with 1-click WhatsApp message.
	 */
	public static function ajax_invite_agent() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( TC_CRM_Access::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized permission.' ), 403 );
		}

		$name  = sanitize_text_field( wp_unslash( $_POST['agent_name'] ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['agent_phone'] ?? '' ) );
		$role  = sanitize_text_field( wp_unslash( $_POST['agent_role'] ?? TC_CRM_Access::ROLE_AGENT ) );

		$res = self::invite_agent( $name, $phone, $role, true );

		if ( ! empty( $res['success'] ) ) {
			wp_send_json_success( $res );
		} else {
			wp_send_json_error( $res );
		}
	}

	/**
	 * AJAX Handler: Resend WhatsApp invite to an existing agent.
	 */
	public static function ajax_resend_invite() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( TC_CRM_Access::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized permission.' ), 403 );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'Agent not found.' ) );
		}

		$phone = (string) get_user_meta( $user_id, 'tc_crm_whatsapp', true );
		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => 'Agent does not have a WhatsApp number configured in their profile.' ) );
		}

		$role = in_array( TC_CRM_Access::ROLE_MANAGER, (array) $user->roles, true ) ? TC_CRM_Access::ROLE_MANAGER : TC_CRM_Access::ROLE_AGENT;
		$res  = self::invite_agent( $user->display_name, $phone, $role, true );

		if ( ! empty( $res['success'] ) ) {
			wp_send_json_success( $res );
		} else {
			wp_send_json_error( $res );
		}
	}

	/**
	 * AJAX Handler: Get live pairing QR code payload for an agent.
	 */
	public static function ajax_get_pairing_qr() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		$user_id = absint( $_POST['user_id'] ?? 0 );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'Agent not found.' ) );
		}

		$mobile_token = self::get_or_create_mobile_token( $user_id );
		$magic_token  = self::generate_magic_token( $user_id, 14 );
		$magic_url    = self::get_magic_login_url( $user_id, $magic_token );

		$is_manager      = in_array( TC_CRM_Access::ROLE_MANAGER, (array) $user->roles, true ) || in_array( 'administrator', (array) $user->roles, true );
		$pairing_payload = rawurlencode(
			wp_json_encode(
				array(
					'url'        => rest_url( 'tc-agents/v1' ),
					'token'      => $mobile_token,
					'name'       => $user->display_name,
					'agent_id'   => $user->ID,
					'role'       => $is_manager ? 'manager' : 'agent',
					'paired_ai'  => get_user_meta( $user->ID, 'tc_paired_ai_name', true ) ?: 'Aditi AI',
				)
			)
		);
		$qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=8&data=' . $pairing_payload;

		wp_send_json_success(
			array(
				'user_id'      => $user_id,
				'name'         => $user->display_name,
				'mobile_token' => $mobile_token,
				'magic_url'    => $magic_url,
				'qr_image_url' => $qr_image_url,
			)
		);
	}
}
