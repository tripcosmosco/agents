<?php
/**
 * Shared security helpers: rate limiting, webhook secrets, per-agent mobile tokens.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Security {

	const TOKENS_OPTION = 'tc_agents_mobile_tokens';

	public static function init() {
		add_action( 'admin_post_tc_agents_create_token', array( __CLASS__, 'handle_create_token' ) );
		add_action( 'admin_post_tc_agents_revoke_token', array( __CLASS__, 'handle_revoke_token' ) );
	}

	/* ------------------------------------------------------------------ rate limiting */

	/**
	 * Only REMOTE_ADDR is trusted; forwarded-for headers are client-controlled.
	 */
	public static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function rl_key( $bucket, $identity ) {
		return 'tcag_rl_' . md5( $bucket . '|' . $identity );
	}

	/**
	 * Count one hit in a fixed window. Returns false once the limit is exceeded.
	 */
	public static function rate_limit( $bucket, $identity, $max, $window ) {
		$key = self::rl_key( $bucket, $identity );
		$row = get_transient( $key );
		if ( ! is_array( $row ) || ! isset( $row['n'], $row['exp'] ) || $row['exp'] <= time() ) {
			$row = array( 'n' => 0, 'exp' => time() + (int) $window );
		}
		if ( $row['n'] >= $max ) {
			return false;
		}
		++$row['n'];
		set_transient( $key, $row, max( 1, $row['exp'] - time() ) );
		return true;
	}

	/**
	 * True when the bucket is already at its limit (does not count a hit).
	 */
	public static function rate_exceeded( $bucket, $identity, $max ) {
		$row = get_transient( self::rl_key( $bucket, $identity ) );
		return is_array( $row ) && isset( $row['n'], $row['exp'] ) && $row['exp'] > time() && $row['n'] >= $max;
	}

	/* ------------------------------------------------------------------ webhook secrets */

	public static function webhook_secret( $name ) {
		$option = 'tc_agents_' . $name . '_webhook_secret';
		$secret = get_option( $option, '' );
		if ( '' === $secret ) {
			$secret = wp_generate_password( 32, false );
			update_option( $option, $secret, false );
		}
		return $secret;
	}

	/**
	 * Constant-time check of the shared secret sent by a webhook caller.
	 * Accepts x-webhook-secret, x-vapi-secret, or a token query parameter.
	 */
	public static function webhook_authorized( WP_REST_Request $request, $name ) {
		$given = $request->get_header( 'x-webhook-secret' );
		if ( ! $given ) {
			$given = $request->get_header( 'x-vapi-secret' );
		}
		if ( ! $given ) {
			$given = $request->get_param( 'token' );
		}
		return is_string( $given ) && '' !== $given && hash_equals( self::webhook_secret( $name ), $given );
	}

	/* ------------------------------------------------------------------ per-agent mobile tokens */

	public static function agent_tokens() {
		$tokens = get_option( self::TOKENS_OPTION, array() );
		return is_array( $tokens ) ? $tokens : array();
	}

	/**
	 * Create a token for one agent/device. The plaintext is returned once and only its hash is stored.
	 */
	public static function create_agent_token( $label ) {
		$label = sanitize_text_field( $label );
		if ( '' === $label ) {
			$label = 'Unnamed device';
		}
		$token  = 'tca_' . wp_generate_password( 40, false, false );
		$id     = strtolower( wp_generate_password( 8, false, false ) );
		$tokens = self::agent_tokens();

		$tokens[ $id ] = array(
			'label'     => $label,
			'hash'      => hash( 'sha256', $token ),
			'created'   => time(),
			'last_used' => 0,
		);
		update_option( self::TOKENS_OPTION, $tokens, false );

		return array( 'id' => $id, 'token' => $token );
	}

	public static function revoke_agent_token( $id ) {
		$tokens = self::agent_tokens();
		if ( ! isset( $tokens[ $id ] ) ) {
			return false;
		}
		unset( $tokens[ $id ] );
		update_option( self::TOKENS_OPTION, $tokens, false );
		return true;
	}

	/**
	 * Returns the agent label for a valid token, or false.
	 */
	public static function match_agent_token( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$hash   = hash( 'sha256', $token );
		$tokens = self::agent_tokens();
		foreach ( $tokens as $id => $row ) {
			if ( isset( $row['hash'] ) && hash_equals( (string) $row['hash'], $hash ) ) {
				if ( empty( $row['last_used'] ) || time() - (int) $row['last_used'] > 300 ) {
					$tokens[ $id ]['last_used'] = time();
					update_option( self::TOKENS_OPTION, $tokens, false );
				}
				return (string) $row['label'];
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ admin actions */

	public static function handle_create_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'tripcosmos-agents' ), 403 );
		}
		check_admin_referer( 'tc_agents_token_action' );

		$label   = isset( $_POST['agent_label'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_label'] ) ) : '';
		$created = self::create_agent_token( $label );
		set_transient( 'tcag_new_token_' . get_current_user_id(), $created, 120 );

		wp_safe_redirect( admin_url( 'admin.php?page=tc-agents-integrations&token_created=1#tc-mobile-access' ) );
		exit;
	}

	public static function handle_revoke_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'tripcosmos-agents' ), 403 );
		}
		check_admin_referer( 'tc_agents_token_action' );

		$id = isset( $_POST['token_id'] ) ? sanitize_key( wp_unslash( $_POST['token_id'] ) ) : '';
		self::revoke_agent_token( $id );

		wp_safe_redirect( admin_url( 'admin.php?page=tc-agents-integrations&token_revoked=1#tc-mobile-access' ) );
		exit;
	}
}
