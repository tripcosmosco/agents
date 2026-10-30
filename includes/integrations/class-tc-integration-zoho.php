<?php
/**
 * Zoho Books API v3 Integration.
 * Handles OAuth 2.0 token management, organization sync, metrics aggregation,
 * and invoice retrieval for TripCosmos financial operations.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_Zoho {

	const OPTION_CLIENT_ID     = 'tc_agents_zoho_client_id';
	const OPTION_CLIENT_SECRET = 'tc_agents_zoho_client_secret';
	const OPTION_REFRESH_TOKEN = 'tc_agents_zoho_refresh_token';
	const OPTION_ORG_ID        = 'tc_agents_zoho_org_id';
	const OPTION_DATACENTER    = 'tc_agents_zoho_datacenter';
	const OPTION_ACCESS_TOKEN  = 'tc_agents_zoho_access_token';
	const OPTION_TOKEN_EXPIRES = 'tc_agents_zoho_token_expires';
	const OPTION_CACHE_SUMMARY = 'tc_agents_zoho_cached_summary';
	const OPTION_LAST_SYNC     = 'tc_agents_zoho_last_sync';

	/**
	 * Get regional data center (default: zoho.in for India).
	 */
	public static function get_datacenter() {
		$dc = get_option( self::OPTION_DATACENTER, 'zoho.in' );
		return ! empty( $dc ) ? strtolower( trim( $dc ) ) : 'zoho.in';
	}

	/**
	 * Get Zoho Accounts base URL for OAuth 2.0.
	 */
	public static function get_accounts_url() {
		$dc = self::get_datacenter();
		switch ( $dc ) {
			case 'zoho.com':
				return 'https://accounts.zoho.com';
			case 'zoho.eu':
				return 'https://accounts.zoho.eu';
			case 'zoho.com.au':
				return 'https://accounts.zoho.com.au';
			case 'zoho.jp':
				return 'https://accounts.zoho.jp';
			case 'zoho.in':
			default:
				return 'https://accounts.zoho.in';
		}
	}

	/**
	 * Get Zoho Books API base URL.
	 */
	public static function get_api_base_url() {
		$dc = self::get_datacenter();
		switch ( $dc ) {
			case 'zoho.com':
				return 'https://books.zoho.com/api/v3';
			case 'zoho.eu':
				return 'https://books.zoho.eu/api/v3';
			case 'zoho.com.au':
				return 'https://books.zoho.com.au/api/v3';
			case 'zoho.jp':
				return 'https://books.zoho.jp/api/v3';
			case 'zoho.in':
			default:
				return 'https://www.zohoapis.in/books/v3';
		}
	}

	/**
	 * Client ID.
	 */
	public static function get_client_id() {
		return trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
	}

	/**
	 * Client Secret (Vault or Option).
	 */
	public static function get_client_secret() {
		$secret = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'zoho_client_secret', '' ) : '';
		if ( empty( $secret ) ) {
			$secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		}
		return trim( (string) $secret );
	}

	/**
	 * Refresh Token (Vault or Option).
	 */
	public static function get_refresh_token() {
		$token = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'zoho_refresh_token', '' ) : '';
		if ( empty( $token ) ) {
			$token = get_option( self::OPTION_REFRESH_TOKEN, '' );
		}
		return trim( (string) $token );
	}

	/**
	 * Organization ID.
	 */
	public static function get_org_id() {
		return trim( (string) get_option( self::OPTION_ORG_ID, '' ) );
	}

	/**
	 * Check if core credentials are provided.
	 */
	public static function is_configured() {
		return ! empty( self::get_client_id() )
			&& ! empty( self::get_client_secret() )
			&& ! empty( self::get_refresh_token() )
			&& ! empty( self::get_org_id() );
	}

	/**
	 * Check if token exists and is currently valid.
	 */
	public static function is_connected() {
		if ( ! self::is_configured() ) {
			return false;
		}
		$token = self::get_access_token();
		return ! is_wp_error( $token ) && ! empty( $token );
	}

	/**
	 * Retrieve or automatically refresh OAuth Access Token.
	 *
	 * @param bool $force Force fresh token fetch.
	 * @return string|WP_Error Access token string or WP_Error.
	 */
	public static function get_access_token( $force = false ) {
		$cached_token = get_option( self::OPTION_ACCESS_TOKEN, '' );
		$expires_at   = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

		// Return cached token if valid with 90-second safety margin
		if ( ! $force && ! empty( $cached_token ) && ( time() + 90 ) < $expires_at ) {
			return $cached_token;
		}

		if ( ! self::is_configured() ) {
			return new WP_Error( 'zoho_not_configured', __( 'Zoho Books credentials not configured.', 'tripcosmos-agents' ) );
		}

		$url = trailingslashit( self::get_accounts_url() ) . 'oauth/v2/token';
		$body = array(
			'refresh_token' => self::get_refresh_token(),
			'client_id'     => self::get_client_id(),
			'client_secret' => self::get_client_secret(),
			'grant_type'    => 'refresh_token',
		);

		$res = wp_remote_post(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Accept' => 'application/json',
				),
				'body'      => $body,
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['access_token'] ) ) {
			$new_token  = sanitize_text_field( $data['access_token'] );
			$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
			$expires_at = time() + $expires_in;

			update_option( self::OPTION_ACCESS_TOKEN, $new_token, false );
			update_option( self::OPTION_TOKEN_EXPIRES, $expires_at, false );

			return $new_token;
		}

		$err_msg = isset( $data['error'] ) ? (string) $data['error'] : 'Failed to retrieve access token from Zoho OAuth.';
		return new WP_Error( 'zoho_token_error', $err_msg );
	}

	/**
	 * Test Zoho Books API connection.
	 *
	 * @return array
	 */
	public static function test_connection() {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Missing one or more required Zoho credentials (Client ID, Secret, Refresh Token, Org ID).', 'tripcosmos-agents' ),
			);
		}

		$token = self::get_access_token( true );
		if ( is_wp_error( $token ) ) {
			return array(
				'success' => false,
				'message' => 'OAuth Token Error: ' . $token->get_error_message(),
			);
		}

		$org_id   = self::get_org_id();
		$base_url = self::get_api_base_url();

		// Check organization details
		$org_url = add_query_arg(
			array( 'organization_id' => $org_id ),
			trailingslashit( $base_url ) . 'organizations/' . rawurlencode( $org_id )
		);

		$res = wp_remote_get(
			$org_url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) === 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( isset( $body['code'] ) && 0 === (int) $body['code'] && ! empty( $body['organization']['name'] ) ) {
				$org_name = sanitize_text_field( $body['organization']['name'] );
				return array(
					'success'  => true,
					'org_name' => $org_name,
					'message'  => sprintf( __( 'Connected successfully to Zoho Books organization: %s', 'tripcosmos-agents' ), $org_name ),
				);
			}
		}

		// Fallback check: probe invoices endpoint
		$inv_url = add_query_arg(
			array(
				'organization_id' => $org_id,
				'per_page'        => 1,
			),
			trailingslashit( $base_url ) . 'invoices'
		);

		$res2 = wp_remote_get(
			$inv_url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $res2 ) ) {
			$code2 = wp_remote_retrieve_response_code( $res2 );
			$body2 = json_decode( wp_remote_retrieve_body( $res2 ), true );
			if ( $code2 === 200 && isset( $body2['code'] ) && 0 === (int) $body2['code'] ) {
				return array(
					'success'  => true,
					'org_name' => 'Org ' . $org_id,
					'message'  => __( 'Connected successfully to Zoho Books API!', 'tripcosmos-agents' ),
				);
			}
			if ( ! empty( $body2['message'] ) ) {
				return array(
					'success' => false,
					'message' => 'Zoho Books API Error: ' . $body2['message'],
				);
			}
		}

		return array(
			'success' => false,
			'message' => __( 'Could not verify Zoho Books connection. Check Organization ID and credentials.', 'tripcosmos-agents' ),
		);
	}

	/**
	 * Fetch invoices from Zoho Books API v3.
	 *
	 * @param int $limit Max invoices to retrieve.
	 * @return array
	 */
	public static function fetch_invoices( $limit = 25 ) {
		if ( ! self::is_configured() ) {
			return array();
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return array();
		}

		$org_id   = self::get_org_id();
		$base_url = self::get_api_base_url();

		$url = add_query_arg(
			array(
				'organization_id' => $org_id,
				'sort_column'     => 'date',
				'sort_order'      => 'D',
				'per_page'        => max( 1, min( 100, (int) $limit ) ),
			),
			trailingslashit( $base_url ) . 'invoices'
		);

		$res = wp_remote_get(
			$url,
			array(
				'timeout'   => 20,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( 200 !== $code || empty( $body['invoices'] ) || ! is_array( $body['invoices'] ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $body['invoices'] as $inv ) {
			$normalized[] = array(
				'invoice_id'     => (string) ( $inv['invoice_id'] ?? '' ),
				'invoice_number' => (string) ( $inv['invoice_number'] ?? '' ),
				'customer_name'  => (string) ( $inv['customer_name'] ?? 'Traveler' ),
				'date'           => (string) ( $inv['date'] ?? '' ),
				'due_date'       => (string) ( $inv['due_date'] ?? '' ),
				'total'          => (float) ( $inv['total'] ?? 0 ),
				'balance'        => (float) ( $inv['balance'] ?? 0 ),
				'status'         => strtolower( (string) ( $inv['status'] ?? 'draft' ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Perform live sync with Zoho Books, compute metrics, and update cache.
	 *
	 * @return array
	 */
	public static function sync() {
		if ( ! self::is_configured() ) {
			return array(
				'success'   => false,
				'connected' => false,
				'metrics'   => array(
					'total_invoiced' => 0.0,
					'total_paid'     => 0.0,
					'total_balance'  => 0.0,
					'overdue_count'  => 0,
				),
				'invoices'  => array(),
				'help'      => __( 'Zoho Books connection is pending credentials.', 'tripcosmos-agents' ),
				'message'   => __( 'Zoho Books credentials not configured.', 'tripcosmos-agents' ),
			);
		}

		$invoices = self::fetch_invoices( 30 );

		$total_invoiced = 0.0;
		$total_balance  = 0.0;
		$total_paid     = 0.0;
		$overdue_count  = 0;
		$now            = time();

		foreach ( $invoices as $inv ) {
			$tot = (float) ( $inv['total'] ?? 0 );
			$bal = (float) ( $inv['balance'] ?? 0 );
			$pd  = max( 0.0, $tot - $bal );

			$total_invoiced += $tot;
			$total_balance  += $bal;
			$total_paid     += $pd;

			$status   = strtolower( (string) ( $inv['status'] ?? '' ) );
			$due_time = ! empty( $inv['due_date'] ) ? strtotime( $inv['due_date'] ) : 0;

			if ( 'overdue' === $status || ( $bal > 0 && $due_time > 0 && $due_time < $now ) ) {
				$overdue_count++;
			}
		}

		$summary = array(
			'success'   => true,
			'connected' => true,
			'metrics'   => array(
				'total_invoiced' => round( $total_invoiced, 2 ),
				'total_paid'     => round( $total_paid, 2 ),
				'total_balance'  => round( $total_balance, 2 ),
				'overdue_count'  => $overdue_count,
			),
			'invoices'  => $invoices,
			'last_sync' => time(),
			'help'      => sprintf(
				'Zoho Books Connected • %s • Org %s',
				self::get_datacenter(),
				self::get_org_id()
			),
		);

		update_option( self::OPTION_CACHE_SUMMARY, $summary, false );
		update_option( self::OPTION_LAST_SYNC, time(), false );

		return $summary;
	}

	/**
	 * Get summary data (cached or live).
	 *
	 * @return array
	 */
	public static function get_summary() {
		if ( ! self::is_configured() ) {
			return array(
				'success'   => false,
				'connected' => false,
				'metrics'   => array(
					'total_invoiced' => 0.0,
					'total_paid'     => 0.0,
					'total_balance'  => 0.0,
					'overdue_count'  => 0,
				),
				'invoices'  => array(),
				'help'      => __( 'Zoho Books connection is pending credentials. Connect in app.', 'tripcosmos-agents' ),
				'message'   => __( 'Zoho Books credentials not configured.', 'tripcosmos-agents' ),
			);
		}

		$cached    = get_option( self::OPTION_CACHE_SUMMARY, null );
		$last_sync = (int) get_option( self::OPTION_LAST_SYNC, 0 );

		// Return cache if younger than 10 minutes and not empty
		if ( is_array( $cached ) && ! empty( $cached['connected'] ) && ( time() - $last_sync ) < 600 ) {
			return $cached;
		}

		return self::sync();
	}

	/**
	 * Save credentials directly from the mobile app or admin and verify immediately.
	 *
	 * @param array $params
	 * @return array
	 */
	public static function save_credentials( array $params ) {
		$client_id     = sanitize_text_field( $params['client_id'] ?? '' );
		$client_secret = sanitize_text_field( $params['client_secret'] ?? '' );
		$refresh_token = sanitize_text_field( $params['refresh_token'] ?? '' );
		$org_id        = sanitize_text_field( $params['org_id'] ?? '' );
		$datacenter    = sanitize_text_field( $params['datacenter'] ?? 'zoho.in' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) || empty( $org_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'All fields (Client ID, Client Secret, Refresh Token, and Organization ID) are required.', 'tripcosmos-agents' ),
			);
		}

		// Auto-exchange: if the user provided an authorization code, exchange it for a refresh token
		$final_refresh_token = $refresh_token;
		$url                 = trailingslashit( self::get_accounts_url() ) . 'oauth/v2/token';

		$exchange_res = wp_remote_post(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
				'body'      => array(
					'code'          => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => 'https://tripcosmos.co',
				),
			)
		);

		if ( ! is_wp_error( $exchange_res ) ) {
			$exchange_data = json_decode( wp_remote_retrieve_body( $exchange_res ), true );
			if ( ! empty( $exchange_data['refresh_token'] ) ) {
				$final_refresh_token = sanitize_text_field( $exchange_data['refresh_token'] );
				if ( ! empty( $exchange_data['access_token'] ) ) {
					$expires_in = isset( $exchange_data['expires_in'] ) ? (int) $exchange_data['expires_in'] : 3600;
					update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $exchange_data['access_token'] ), false );
					update_option( self::OPTION_TOKEN_EXPIRES, time() + $expires_in, false );
				}
			}
		}

		// Store options & vault
		update_option( self::OPTION_CLIENT_ID, $client_id, false );
		update_option( self::OPTION_ORG_ID, $org_id, false );
		update_option( self::OPTION_DATACENTER, $datacenter, false );

		if ( class_exists( 'TC_Agents_Vault' ) ) {
			TC_Agents_Vault::put( 'zoho_client_secret', $client_secret );
			TC_Agents_Vault::put( 'zoho_refresh_token', $final_refresh_token );
		} else {
			update_option( self::OPTION_CLIENT_SECRET, $client_secret, false );
			update_option( self::OPTION_REFRESH_TOKEN, $final_refresh_token, false );
		}

		// Purge stale access token to test fresh exchange
		delete_option( self::OPTION_ACCESS_TOKEN );
		delete_option( self::OPTION_TOKEN_EXPIRES );

		// Test connection immediately
		$test = self::test_connection();
		if ( empty( $test['success'] ) ) {
			return array(
				'success' => false,
				'message' => $test['message'] ?? __( 'Failed to authenticate with Zoho Books.', 'tripcosmos-agents' ),
			);
		}

		// Trigger initial sync
		$sync = self::sync();

		return array(
			'success'  => true,
			'message'  => $test['message'] ?? __( 'Connected to Zoho Books successfully!', 'tripcosmos-agents' ),
			'org_name' => $test['org_name'] ?? '',
			'summary'  => $sync,
		);
	}

	/**
	 * Get safe configuration status for the app (masks secrets).
	 *
	 * @return array
	 */
	public static function get_config_status() {
		$client_id = self::get_client_id();
		$masked_id = '';
		if ( ! empty( $client_id ) ) {
			$len = strlen( $client_id );
			$masked_id = $len > 8 ? substr( $client_id, 0, 4 ) . '...' . substr( $client_id, -4 ) : '••••••••';
		}

		return array(
			'success'           => true,
			'connected'         => self::is_connected(),
			'is_configured'     => self::is_configured(),
			'datacenter'        => self::get_datacenter(),
			'org_id'            => self::get_org_id(),
			'client_id_preview' => $masked_id,
			'has_refresh_token' => ! empty( self::get_refresh_token() ),
			'last_sync'         => (int) get_option( self::OPTION_LAST_SYNC, 0 ),
		);
	}

	/**
	 * Hook to intercept Zoho OAuth redirection to homepage (e.g. https://tripcosmos.co/?code=...)
	 */
	public static function init_hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_oauth_redirect' ), 1 );
	}

	public static function handle_oauth_redirect() {
		if ( empty( $_GET['code'] ) ) {
			return;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		if ( 0 !== strpos( $code, '1000.' ) ) {
			return;
		}

		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();
		$org_id        = self::get_org_id();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return;
		}

		// Exchange code for permanent refresh token
		$url = trailingslashit( self::get_accounts_url() ) . 'oauth/v2/token';
		$res = wp_remote_post(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'      => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => 'https://tripcosmos.co',
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			wp_die( '<h1>OAuth Exchange Error</h1><p>' . esc_html( $res->get_error_message() ) . '</p>' );
		}

		$raw_body = wp_remote_retrieve_body( $res );
		$data     = json_decode( $raw_body, true );

		if ( ! empty( $data['access_token'] ) ) {
			update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $data['access_token'] ), false );
			$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
			update_option( self::OPTION_TOKEN_EXPIRES, time() + $expires_in, false );
		}

		if ( ! empty( $data['refresh_token'] ) ) {
			$refresh_token = sanitize_text_field( $data['refresh_token'] );
			if ( class_exists( 'TC_Agents_Vault' ) ) {
				TC_Agents_Vault::put( 'zoho_refresh_token', $refresh_token );
			} else {
				update_option( self::OPTION_REFRESH_TOKEN, $refresh_token, false );
			}
		}

		if ( ! empty( $data['access_token'] ) || ! empty( $data['refresh_token'] ) ) {
			// Run sync immediately
			self::sync();

			$has_rf = ! empty( self::get_refresh_token() );
			$rf_notice = $has_rf 
				? 'Your permanent Refresh Token has been securely generated and saved in your encrypted vault.' 
				: 'Connected with active Access Token. Invoices and organization data are now syncing.';

			wp_die(
				'<div style="font-family:system-ui,-apple-system,sans-serif;max-width:550px;margin:80px auto;padding:36px;background:#ffffff;border-radius:18px;box-shadow:0 12px 40px rgba(0,0,0,0.1);text-align:center;border-top:6px solid #10b981;">' .
				'<div style="font-size:48px;margin-bottom:12px;">✅</div>' .
				'<h1 style="color:#0f172a;font-size:24px;margin:0 0 12px;font-weight:800;">Zoho Books Connected!</h1>' .
				'<p style="color:#475569;font-size:15px;line-height:1.6;margin:0 0 24px;">' . esc_html( $rf_notice ) . ' Invoices and financial ledger metrics are now live in your X-Agents companion app and WordPress Finance Hub.</p>' .
				'<a href="' . esc_url( admin_url( 'admin.php?page=tc-agents-finance' ) ) . '" style="display:inline-block;padding:12px 24px;background:#0f172a;color:#f59e0b;font-weight:700;text-decoration:none;border-radius:10px;">Return to Finance Hub</a>' .
				'</div>',
				'Zoho Books Connected',
				array( 'response' => 200 )
			);
		}

		$err = $data['error'] ?? ( $data['message'] ?? $raw_body );
		wp_die(
			'<h1>Zoho Authorization Notice</h1><p>Code exchange returned: <code>' . esc_html( $err ) . '</code></p><p><a href="' . esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ) . '">Return to Admin</a></p>'
		);
	}
}

TC_Integration_Zoho::init_hooks();
