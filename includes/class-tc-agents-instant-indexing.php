<?php
/**
 * Instant Search Indexing & IndexNow Multi-Engine Dispatcher.
 *
 * Programmatically pushes commercial landing pages, silo hubs, and salvage redirects
 * directly to Google, Microsoft Bing, Yandex, and Naver via the IndexNow protocol
 * and Google Indexing API (using Rank Math Instant Indexing engine when present).
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Instant_Indexing {

	const INDEXNOW_API_ENDPOINT = 'https://api.indexnow.org/indexnow';
	const DEFAULT_HOST          = 'tripcosmos.co';

	/**
	 * High-priority commercial URLs that funnel search traffic into bookings.
	 */
	const PRIORITY_URLS = array(
		'https://tripcosmos.co/',
		'https://tripcosmos.co/tour-packages/',
		'https://tripcosmos.co/tour-packages/magh-mela-tent-booking-2027/',
		'https://tripcosmos.co/complete-varanasi-travel-plan-2/',
		'https://tripcosmos.co/best-time-to-visit-naimisharanya-2/',
		'https://tripcosmos.co/premium-varanasi-experience-2/',
	);

	/**
	 * Register REST routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'tc-agents/v1',
			'/mobile/instant-index',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_instant_index' ),
				'permission_callback' => array( 'TC_Agents_Mobile_API', 'verify_token' ),
			)
		);
	}

	/**
	 * REST handler for instant indexing.
	 */
	public static function handle_instant_index( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_params(), (array) $request->get_json_params() );
		$urls   = ! empty( $params['urls'] ) ? (array) $params['urls'] : self::PRIORITY_URLS;

		$sanitized_urls = array();
		foreach ( $urls as $u ) {
			$u = esc_url_raw( trim( (string) $u ) );
			if ( ! empty( $u ) && filter_var( $u, FILTER_VALIDATE_URL ) ) {
				$sanitized_urls[] = $u;
			}
		}

		if ( empty( $sanitized_urls ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'No valid URLs provided for indexing.',
				),
				400
			);
		}

		$results = self::submit_batch( $sanitized_urls );

		// Flush LiteSpeed Cache tags for submitted URLs
		foreach ( $sanitized_urls as $u ) {
			if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_purge_url', $u );
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => count( $sanitized_urls ),
				'urls'    => $sanitized_urls,
				'results' => $results,
			),
			200
		);
	}

	/**
	 * Submit batch of URLs to all available indexing channels.
	 *
	 * @param array $urls Array of absolute URLs.
	 * @return array Multi-channel submission results.
	 */
	public static function submit_batch( array $urls ) {
		$results = array(
			'indexnow'  => null,
			'rankmath'  => null,
			'timestamp' => current_time( 'mysql' ),
		);

		// 1. Submit via IndexNow Protocol (Bing, Yandex, Seznam, Naver)
		$results['indexnow'] = self::submit_indexnow( $urls );

		// 2. Submit via Rank Math Instant Indexing API if active
		$results['rankmath'] = self::submit_rankmath( $urls );

		return $results;
	}

	/**
	 * Submit to IndexNow API.
	 */
	private static function submit_indexnow( array $urls ) {
		// Retrieve IndexNow key from Rank Math options or fallback key
		$rm_options = get_option( 'rank-math-options-instant-indexing', array() );
		$key        = ! empty( $rm_options['bing_api_key'] ) ? $rm_options['bing_api_key'] : get_option( 'tc_agents_indexnow_key', 'tripcosmos2026' );
		$host       = wp_parse_url( home_url(), PHP_URL_HOST ) ?: self::DEFAULT_HOST;

		$payload = array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => array_values( $urls ),
		);

		$res = wp_remote_post(
			self::INDEXNOW_API_ENDPOINT,
			array(
				'method'      => 'POST',
				'timeout'     => 15,
				'redirection' => 5,
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'User-Agent'   => 'TripCosmos-Instant-Indexer/1.0',
				),
				'body'        => wp_json_encode( $payload ),
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'status' => 'error',
				'error'  => $res->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $res );
		return array(
			'status'    => ( $code >= 200 && $code < 300 ) ? 'success' : 'submitted',
			'http_code' => $code,
			'message'   => ( 200 === $code ) ? 'OK' : ( ( 202 === $code ) ? 'Accepted by IndexNow' : 'HTTP ' . $code ),
		);
	}

	/**
	 * Submit to Rank Math Fast Indexing / Instant Indexing internal API.
	 */
	private static function submit_rankmath( array $urls ) {
		// If Rank Math Instant Indexing is active
		if ( class_exists( '\RankMath\Instant_Indexing\Api' ) ) {
			try {
				$api = \RankMath\Instant_Indexing\Api::get();
				if ( method_exists( $api, 'submit_urls' ) ) {
					$url_string = implode( "\n", $urls );
					$api->submit_urls( $url_string, 'URL_UPDATED' );
					return array(
						'status'  => 'success',
						'engine'  => 'RankMath_Api_Direct',
						'message' => 'Submitted via Rank Math Instant Indexing API class.',
					);
				}
			} catch ( Exception $e ) {
				return array(
					'status' => 'error',
					'error'  => $e->getMessage(),
				);
			}
		}

		return array(
			'status'  => 'fallback',
			'message' => 'IndexNow direct submission active.',
		);
	}
}
