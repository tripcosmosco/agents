<?php
/**
 * Google Ads API v17+ Integration.
 * Handles OAuth 2.0 token management, live campaign ad spend sync (GAQL),
 * and Offline Conversion Tracking (OCT) to optimize Google Target ROAS bidding.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Integration_Google_Ads {

	const OPTION_CUSTOMER_ID          = 'tc_agents_gads_customer_id';
	const OPTION_CONVERSION_ID        = 'tc_agents_gads_conversion_id';
	const OPTION_DEVELOPER_TOKEN      = 'tc_agents_gads_developer_token';
	const OPTION_CLIENT_ID            = 'tc_agents_gads_client_id';
	const OPTION_CLIENT_SECRET        = 'tc_agents_gads_client_secret';
	const OPTION_REFRESH_TOKEN        = 'tc_agents_gads_refresh_token';
	const OPTION_ACCESS_TOKEN         = 'tc_agents_gads_access_token';
	const OPTION_TOKEN_EXPIRES        = 'tc_agents_gads_token_expires';
	const OPTION_CONVERSION_ACTION_ID = 'tc_agents_gads_conversion_action_id';
	const OPTION_CAMPAIGNS_CACHE      = 'tc_agents_gads_campaigns_cache';
	const OPTION_LAST_SYNC            = 'tc_agents_gads_last_sync';
	const OPTION_TOTAL_30D_SPEND      = 'tc_agents_gads_30d_spend';

	/**
	 * Initialize hooks.
	 */
	public static function init_hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_oauth_redirect' ), 1 );
	}

	public static function get_conversion_id() {
		return trim( (string) get_option( self::OPTION_CONVERSION_ID, 'AW-17106475490' ) );
	}

	/**
	 * Get sanitized Google Ads Customer ID (stripping hyphens).
	 */
	public static function get_customer_id() {
		$cid = (string) get_option( self::OPTION_CUSTOMER_ID, '391-398-8878' );
		return preg_replace( '/[^0-9]/', '', $cid );
	}

	public static function get_developer_token() {
		return trim( (string) get_option( self::OPTION_DEVELOPER_TOKEN, '' ) );
	}

	public static function get_client_id() {
		return trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
	}

	public static function get_client_secret() {
		if ( class_exists( 'TC_Agents_Vault' ) && TC_Agents_Vault::has( 'gads_client_secret' ) ) {
			return (string) TC_Agents_Vault::get( 'gads_client_secret' );
		}
		return trim( (string) get_option( self::OPTION_CLIENT_SECRET, '' ) );
	}

	public static function get_refresh_token() {
		if ( class_exists( 'TC_Agents_Vault' ) && TC_Agents_Vault::has( 'gads_refresh_token' ) ) {
			return (string) TC_Agents_Vault::get( 'gads_refresh_token' );
		}
		return trim( (string) get_option( self::OPTION_REFRESH_TOKEN, '' ) );
	}

	public static function get_conversion_action_id() {
		$aid = (string) get_option( self::OPTION_CONVERSION_ACTION_ID, '' );
		return preg_replace( '/[^0-9]/', '', $aid );
	}

	/**
	 * Check if Google Ads integration is configured.
	 * Developer Token is optional (only present in MCC accounts).
	 */
	public static function is_configured() {
		return ! empty( self::get_customer_id() ) || ! empty( self::get_conversion_id() );
	}

	/**
	 * Parse and import exported Google Ads Campaign Report CSV.
	 * Allows standard Google Ads advertisers without MCC Developer Tokens
	 * to import exact campaign performance, spend, clicks and ROAS.
	 */
	public static function import_campaigns_csv( $csv_content ) {
		if ( empty( trim( $csv_content ) ) ) {
			return array( 'ok' => false, 'error' => 'CSV content is empty.' );
		}

		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv_content ) );
		if ( count( $lines ) < 2 ) {
			return array( 'ok' => false, 'error' => 'CSV file contains insufficient rows.' );
		}

		$header_idx = -1;
		$headers    = array();
		foreach ( $lines as $idx => $line ) {
			$lower = strtolower( $line );
			if ( strpos( $lower, 'campaign' ) !== false && ( strpos( $lower, 'cost' ) !== false || strpos( $lower, 'clicks' ) !== false || strpos( $lower, 'spend' ) !== false || strpos( $lower, 'impr' ) !== false ) ) {
				$header_idx = $idx;
				$headers    = str_getcsv( $line );
				break;
			}
		}

		if ( $header_idx === -1 ) {
			return array( 'ok' => false, 'error' => 'Could not detect Google Ads campaign headers (Campaign, Cost/Spend, Clicks, Impr).' );
		}

		$col_map = array();
		foreach ( $headers as $c_idx => $col ) {
			$col_clean = strtolower( trim( $col ) );
			if ( strpos( $col_clean, 'campaign' ) !== false && ! isset( $col_map['campaign'] ) ) {
				$col_map['campaign'] = $c_idx;
			} elseif ( ( strpos( $col_clean, 'cost' ) !== false || strpos( $col_clean, 'spend' ) !== false ) && ! isset( $col_map['cost'] ) ) {
				$col_map['cost'] = $c_idx;
			} elseif ( strpos( $col_clean, 'click' ) !== false && ! isset( $col_map['clicks'] ) ) {
				$col_map['clicks'] = $c_idx;
			} elseif ( ( strpos( $col_clean, 'impr' ) !== false || strpos( $col_clean, 'impression' ) !== false ) && ! isset( $col_map['impr'] ) ) {
				$col_map['impr'] = $c_idx;
			} elseif ( ( strpos( $col_clean, 'conv' ) !== false || strpos( $col_clean, 'conversion' ) !== false ) && strpos( $col_clean, 'value' ) === false && ! isset( $col_map['conv'] ) ) {
				$col_map['conv'] = $c_idx;
			} elseif ( ( strpos( $col_clean, 'value' ) !== false || strpos( $col_clean, 'revenue' ) !== false ) && ! isset( $col_map['conv_val'] ) ) {
				$col_map['conv_val'] = $c_idx;
			} elseif ( strpos( $col_clean, 'status' ) !== false && ! isset( $col_map['status'] ) ) {
				$col_map['status'] = $c_idx;
			}
		}

		$campaigns     = array();
		$total_spend   = 0.0;
		$total_clicks  = 0;
		$total_imprs   = 0;
		$total_convs   = 0;
		$total_conv_val= 0.0;

		for ( $i = $header_idx + 1; $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( empty( $line ) || 0 === strpos( $line, 'Total' ) || 0 === strpos( $line, '--' ) ) {
				continue;
			}

			$cells = str_getcsv( $line );
			if ( count( $cells ) < 2 ) {
				continue;
			}

			$name = isset( $col_map['campaign'] ) ? trim( $cells[ $col_map['campaign'] ] ?? '' ) : 'Campaign #' . $i;
			if ( empty( $name ) || 0 === strpos( $name, 'Total' ) ) {
				continue;
			}

			$cost_str = isset( $col_map['cost'] ) ? preg_replace( '/[^0-9.]/', '', $cells[ $col_map['cost'] ] ?? '0' ) : '0';
			$cost     = floatval( $cost_str );

			$clicks_str = isset( $col_map['clicks'] ) ? preg_replace( '/[^0-9]/', '', $cells[ $col_map['clicks'] ] ?? '0' ) : '0';
			$clicks     = intval( $clicks_str );

			$impr_str = isset( $col_map['impr'] ) ? preg_replace( '/[^0-9]/', '', $cells[ $col_map['impr'] ] ?? '0' ) : '0';
			$impr     = intval( $impr_str );

			$conv_str = isset( $col_map['conv'] ) ? preg_replace( '/[^0-9.]/', '', $cells[ $col_map['conv'] ] ?? '0' ) : '0';
			$conv     = floatval( $conv_str );

			$conv_val_str = isset( $col_map['conv_val'] ) ? preg_replace( '/[^0-9.]/', '', $cells[ $col_map['conv_val'] ] ?? '0' ) : '0';
			$conv_val     = floatval( $conv_val_str );

			$status = isset( $col_map['status'] ) ? trim( $cells[ $col_map['status'] ] ?? 'ENABLED' ) : 'ENABLED';

			$total_spend    += $cost;
			$total_clicks   += $clicks;
			$total_imprs    += $impr;
			$total_convs    += $conv;
			$total_conv_val += $conv_val;

			$campaigns[] = array(
				'id'          => 'CSV-' . $i,
				'name'        => $name,
				'status'      => strtoupper( $status ),
				'cost_inr'    => round( $cost, 2 ),
				'clicks'      => $clicks,
				'impressions' => $impr,
				'ctr'         => $impr > 0 ? round( ( $clicks / $impr ) * 100, 2 ) : 0.0,
				'avg_cpc'     => $clicks > 0 ? round( $cost / $clicks, 2 ) : 0.0,
				'conversions' => $conv,
				'conv_value'  => round( $conv_val, 2 ),
				'roas'        => $cost > 0 ? round( $conv_val / $cost, 2 ) : 0.0,
			);
		}

		if ( empty( $campaigns ) ) {
			return array( 'ok' => false, 'error' => 'No valid campaign rows found in CSV.' );
		}

		update_option( self::OPTION_CAMPAIGNS_CACHE, $campaigns, false );
		update_option( self::OPTION_TOTAL_30D_SPEND, $total_spend, false );
		update_option( self::OPTION_LAST_SYNC, time(), false );

		if ( $total_spend > 0 ) {
			update_option( 'tc_agents_monthly_ad_spend', $total_spend );
		}

		return array(
			'ok'          => true,
			'campaigns'   => $campaigns,
			'total_spend' => round( $total_spend, 2 ),
			'count'       => count( $campaigns ),
			'message'     => sprintf( 'Successfully imported %d Google Ads campaigns. Total spend: ₹%s', count( $campaigns ), number_format( $total_spend, 2 ) ),
		);
	}

	/**
	 * Generate 1-Click OAuth Authorization URL.
	 */
	public static function get_auth_url() {
		$client_id = self::get_client_id();
		if ( empty( $client_id ) ) {
			return '';
		}

		$redirect_uri = 'https://tripcosmos.co';
		$params = array(
			'client_id'       => $client_id,
			'redirect_uri'    => $redirect_uri,
			'response_type'   => 'code',
			'scope'           => 'https://www.googleapis.com/auth/adwords',
			'access_type'     => 'offline',
			'prompt'          => 'consent',
			'state'           => 'tc_gads_oauth',
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * Handle Google OAuth Redirect & exchange authorization code.
	 */
	public static function handle_oauth_redirect() {
		if ( empty( $_GET['code'] ) || empty( $_GET['state'] ) || 'tc_gads_oauth' !== $_GET['state'] ) {
			return;
		}

		$code          = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_die( '<h1>Google Ads OAuth Error</h1><p>Client ID or Client Secret missing in configuration.</p>' );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'      => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => 'https://tripcosmos.co',
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( '<h1>Google Ads Token Exchange Failed</h1><p>' . esc_html( $response->get_error_message() ) . '</p>' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['refresh_token'] ) ) {
			$refresh_token = sanitize_text_field( $data['refresh_token'] );
			if ( class_exists( 'TC_Agents_Vault' ) ) {
				TC_Agents_Vault::put( 'gads_refresh_token', $refresh_token );
			} else {
				update_option( self::OPTION_REFRESH_TOKEN, $refresh_token, false );
			}

			if ( ! empty( $data['access_token'] ) ) {
				update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $data['access_token'] ), false );
				$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
				update_option( self::OPTION_TOKEN_EXPIRES, time() + $expires_in, false );
			}

			// Run immediate metrics sync
			self::sync_campaigns();

			wp_die(
				'<div style="font-family:system-ui,-apple-system,sans-serif;max-width:550px;margin:80px auto;padding:36px;background:#ffffff;border-radius:18px;box-shadow:0 12px 40px rgba(0,0,0,0.1);text-align:center;border-top:6px solid #4285f4;">' .
				'<div style="font-size:48px;margin-bottom:12px;">🎯</div>' .
				'<h1 style="color:#0f172a;font-size:24px;margin:0 0 12px;font-weight:800;">Google Ads API Connected!</h1>' .
				'<p style="color:#475569;font-size:15px;line-height:1.6;margin:0 0 24px;">OAuth 2.0 credentials established. Live daily ad spend, campaign performance, and Target ROAS Offline Conversion syncing are active.</p>' .
				'<a href="' . esc_url( admin_url( 'admin.php?page=tc-agents-finance#tab-roi' ) ) . '" style="display:inline-block;padding:12px 24px;background:#0f172a;color:#f59e0b;font-weight:700;text-decoration:none;border-radius:10px;">Return to Finance Hub</a>' .
				'</div>',
				'Google Ads Connected',
				array( 'response' => 200 )
			);
		}

		$err = $data['error_description'] ?? $data['error'] ?? 'Unknown token exchange error';
		wp_die(
			'<h1>Google Ads Token Notice</h1><p>' . esc_html( $err ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ) . '">Return to Integrations</a></p>'
		);
	}

	/**
	 * Retrieve or refresh Google OAuth Access Token.
	 */
	public static function get_access_token( $force = false ) {
		$cached_token = get_option( self::OPTION_ACCESS_TOKEN, '' );
		$expires_at   = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

		if ( ! $force && ! empty( $cached_token ) && ( time() + 90 ) < $expires_at ) {
			return $cached_token;
		}

		$refresh_token = self::get_refresh_token();
		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();

		if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'not_configured', 'Google Ads OAuth credentials or refresh token missing.' );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout'   => 12,
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'      => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			$err = $data['error_description'] ?? $data['error'] ?? 'Unable to refresh Google access token.';
			return new WP_Error( 'token_refresh_failed', $err );
		}

		$access_token = sanitize_text_field( $data['access_token'] );
		$expires_in   = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;

		update_option( self::OPTION_ACCESS_TOKEN, $access_token, false );
		update_option( self::OPTION_TOKEN_EXPIRES, time() + $expires_in, false );

		return $access_token;
	}

	/**
	 * Fetch live Google Ads campaigns, 30-day spend, clicks, impressions and conversions.
	 */
	public static function sync_campaigns() {
		$cid       = self::get_customer_id();
		$dev_token = self::get_developer_token();

		if ( empty( $cid ) || empty( $dev_token ) ) {
			return array(
				'ok'    => false,
				'error' => 'Google Ads Customer ID or Developer Token missing.',
			);
		}

		$access_token = self::get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return array(
				'ok'    => false,
				'error' => $access_token->get_error_message(),
			);
		}

		$endpoint = "https://googleads.googleapis.com/v26/customers/{$cid}/googleAds:search";
		$query    = "SELECT 
			campaign.id, 
			campaign.name, 
			campaign.status, 
			metrics.cost_micros, 
			metrics.impressions, 
			metrics.clicks, 
			metrics.conversions, 
			metrics.conversions_value 
		FROM campaign 
		WHERE segments.date DURING LAST_30_DAYS 
		  AND campaign.status != 'REMOVED'";

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 20,
				'sslverify' => true,
				'headers'   => array(
					'Authorization'     => 'Bearer ' . $access_token,
					'developer-token'   => $dev_token,
					'login-customer-id' => $cid,
					'Content-Type'      => 'application/json',
				),
				'body'      => json_encode( array( 'query' => $query ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$msg = $body['error']['message'] ?? 'Google Ads API request failed with code ' . $code;
			return array(
				'ok'    => false,
				'error' => $msg,
			);
		}

		$campaigns     = array();
		$total_spend   = 0.0;
		$total_clicks  = 0;
		$total_imprs   = 0;
		$total_convs   = 0;
		$total_conv_val= 0.0;

		$results = $body['results'] ?? array();
		foreach ( $results as $row ) {
			$c = $row['campaign'] ?? array();
			$m = $row['metrics'] ?? array();

			$cost_inr = isset( $m['costMicros'] ) ? ( (float) $m['costMicros'] / 1000000.0 ) : 0.0;
			$clicks   = isset( $m['clicks'] ) ? (int) $m['clicks'] : 0;
			$imprs    = isset( $m['impressions'] ) ? (int) $m['impressions'] : 0;
			$convs    = isset( $m['conversions'] ) ? (float) $m['conversions'] : 0.0;
			$conv_val = isset( $m['conversionsValue'] ) ? (float) $m['conversionsValue'] : 0.0;

			$total_spend    += $cost_inr;
			$total_clicks   += $clicks;
			$total_imprs    += $imprs;
			$total_convs    += $convs;
			$total_conv_val += $conv_val;

			$campaigns[] = array(
				'id'          => $c['id'] ?? '',
				'name'        => $c['name'] ?? 'Unnamed Campaign',
				'status'      => $c['status'] ?? 'ENABLED',
				'cost_inr'    => round( $cost_inr, 2 ),
				'clicks'      => $clicks,
				'impressions' => $imprs,
				'ctr'         => $imprs > 0 ? round( ( $clicks / $imprs ) * 100, 2 ) : 0.0,
				'avg_cpc'     => $clicks > 0 ? round( $cost_inr / $clicks, 2 ) : 0.0,
				'conversions' => $convs,
				'conv_value'  => round( $conv_val, 2 ),
				'roas'        => $cost_inr > 0 ? round( $conv_val / $cost_inr, 2 ) : 0.0,
			);
		}

		// Update cached 30-day metrics & automated marketing budget
		update_option( self::OPTION_CAMPAIGNS_CACHE, $campaigns, false );
		update_option( self::OPTION_TOTAL_30D_SPEND, $total_spend, false );
		update_option( self::OPTION_LAST_SYNC, time(), false );

		if ( $total_spend > 0 ) {
			update_option( 'tc_agents_monthly_ad_spend', $total_spend );
		}

		return array(
			'ok'            => true,
			'campaigns'     => $campaigns,
			'total_spend'   => round( $total_spend, 2 ),
			'total_clicks'  => $total_clicks,
			'total_imprs'   => $total_imprs,
			'total_convs'   => $total_convs,
			'total_conv_val'=> round( $total_conv_val, 2 ),
			'last_sync'     => time(),
		);
	}

	/**
	 * Upload an Offline Conversion (OCT) to Google Ads for Smart Bidding / Target ROAS.
	 *
	 * @param string $gclid         Google Click ID from original session.
	 * @param float  $value         Actual confirmed booking / payment amount (INR).
	 * @param string $order_id      Unique identifier (e.g. TC-BK-1024 or SBI UTR).
	 * @param string $conversion_time Date/time of conversion (ISO or Y-m-d H:i:s).
	 * @param string $action_id     Optional specific conversion action ID.
	 * @return array
	 */
	public static function upload_offline_conversion( $gclid, $value, $order_id = '', $conversion_time = '', $action_id = '' ) {
		$cid       = self::get_customer_id();
		$dev_token = self::get_developer_token();

		if ( empty( $cid ) || empty( $dev_token ) ) {
			return array(
				'ok'    => false,
				'error' => 'Google Ads Customer ID or Developer Token missing.',
			);
		}

		if ( empty( $gclid ) ) {
			return array(
				'ok'    => false,
				'error' => 'Missing GCLID. Only ad-attributed leads can be uploaded to Google Ads OCT.',
			);
		}

		$act_id = ! empty( $action_id ) ? preg_replace( '/[^0-9]/', '', $action_id ) : self::get_conversion_action_id();
		if ( empty( $act_id ) ) {
			return array(
				'ok'    => false,
				'error' => 'Conversion Action ID not configured. Create an Offline Conversion Action in Google Ads first.',
			);
		}

		$access_token = self::get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return array(
				'ok'    => false,
				'error' => $access_token->get_error_message(),
			);
		}

		if ( empty( $conversion_time ) ) {
			$conversion_time = current_time( 'Y-m-d H:i:s' ) . '+05:30';
		} elseif ( strpos( $conversion_time, '+' ) === false ) {
			$conversion_time .= '+05:30';
		}

		$endpoint = "https://googleads.googleapis.com/v26/customers/{$cid}:uploadClickConversions";
		$payload  = array(
			'conversions'    => array(
				array(
					'conversionAction'   => "customers/{$cid}/conversionActions/{$act_id}",
					'gclid'              => $gclid,
					'conversionDateTime' => $conversion_time,
					'conversionValue'    => (float) $value,
					'currencyCode'       => 'INR',
					'orderId'            => ! empty( $order_id ) ? (string) $order_id : 'TC-' . time(),
				),
			),
			'partialFailure' => true,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Authorization'     => 'Bearer ' . $access_token,
					'developer-token'   => $dev_token,
					'login-customer-id' => $cid,
					'Content-Type'      => 'application/json',
				),
				'body'      => json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Log into conversion table
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_conversion_log';
		$wpdb->insert(
			$table,
			array(
				'event_type'  => 'google_ads_oct_upload',
				'channel'     => 'google_ads_api',
				'source'      => 'sbi_bank_or_crm_booking',
				'gclid'       => $gclid,
				'campaign'    => 'Offline Conversion',
				'value'       => (float) $value,
				'metadata'    => json_encode( array(
					'order_id'      => $order_id,
					'status_code'   => $code,
					'response_body' => $body,
				) ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);

		if ( 200 === $code && empty( $body['partialFailureError'] ) ) {
			return array(
				'ok'      => true,
				'message' => "Offline conversion of ₹{$value} successfully uploaded to Google Ads (GCLID: {$gclid})",
				'raw'     => $body,
			);
		}

		$err = $body['partialFailureError']['message'] ?? ( $body['error']['message'] ?? 'Google Ads partial failure.' );
		return array(
			'ok'      => false,
			'error'   => $err,
			'raw'     => $body,
		);
	}

	/**
	 * Get summary data for WP Admin & Mobile API.
	 */
	public static function get_summary() {
		$campaigns  = get_option( self::OPTION_CAMPAIGNS_CACHE, array() );
		$total_spend= (float) get_option( self::OPTION_TOTAL_30D_SPEND, 0.0 );
		$last_sync  = (int) get_option( self::OPTION_LAST_SYNC, 0 );

		return array(
			'configured'     => self::is_configured(),
			'customer_id'    => self::get_customer_id(),
			'has_token'      => ! empty( self::get_refresh_token() ),
			'auth_url'       => self::get_auth_url(),
			'campaigns'      => is_array( $campaigns ) ? $campaigns : array(),
			'total_30d_spend'=> $total_spend,
			'last_sync'      => $last_sync,
		);
	}
}

TC_Integration_Google_Ads::init_hooks();
