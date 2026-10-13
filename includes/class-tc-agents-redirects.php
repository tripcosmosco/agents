<?php
/**
 * Smart 404 Auto-Redirect & Traffic Salvage Engine.
 *
 * Intercepts 404 requests caused by deleted duplicate posts, outdated campaign URLs,
 * and broken organic links, permanently 301 redirecting search crawlers and visitors
 * directly into active commercial tour booking and pilgrimage landing pages.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Redirects {

	/**
	 * Register template_redirect hook.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_404_salvage' ), 1 );
	}

	/**
	 * Intercept 404 and execute 301 Permanent Redirect to relevant commercial page.
	 */
	public static function handle_404_salvage() {
		if ( ! is_404() ) {
			return;
		}

		$raw_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path    = trim( (string) parse_url( $raw_uri, PHP_URL_PATH ), '/' );
		$lower   = strtolower( $path );

		if ( empty( $lower ) ) {
			return;
		}

		$target_url = null;

		// 1. Exact High-Value Keyword Mappings (Known 404 leaks from deleted duplicates)
		$exact_map = array(
			'tours'                              => home_url( '/tour-packages/' ),
			'varanasi-boat-tour'                 => home_url( '/tour-packages/' ),
			'lahartara-sarovar-lake'             => home_url( '/' ),
			'budget-travel-tips-for-rajasthan'   => home_url( '/' ),
			'varanasi-tour-package'              => home_url( '/tour-packages/' ),
			'complete-varanasi-travel-plan'      => home_url( '/complete-varanasi-travel-plan-2/' ),
			'best-time-to-visit-naimisharanya'   => home_url( '/best-time-to-visit-naimisharanya-2/' ),
			'premium-varanasi-experience'        => home_url( '/premium-varanasi-experience-2/' ),
			'varanasi-to-ayodhya-cab-fare'       => home_url( '/' ),
			'kashi-vishwanath-vip-darshan'       => home_url( '/tour-packages/' ),
			'dev-deepawali-boat-booking'         => home_url( '/tour-packages/' ),
		);

		if ( isset( $exact_map[ $lower ] ) ) {
			$target_url = $exact_map[ $lower ];
		}

		// 2. Pattern & Intent-Based Fallbacks for Deleted Clusters
		if ( ! $target_url ) {
			if ( strpos( $lower, 'boat' ) !== false || strpos( $lower, 'bajra' ) !== false || strpos( $lower, 'aarti' ) !== false ) {
				$target_url = home_url( '/tours/' );
			} elseif ( strpos( $lower, 'ayodhya' ) !== false || strpos( $lower, 'ram-mandir' ) !== false || strpos( $lower, 'janmabhoomi' ) !== false ) {
				$target_url = home_url( '/tours/' );
			} elseif ( strpos( $lower, 'kashi' ) !== false || strpos( $lower, 'vishwanath' ) !== false || strpos( $lower, 'darshan' ) !== false ) {
				$target_url = home_url( '/tours/' );
			} elseif ( strpos( $lower, 'prayagraj' ) !== false || strpos( $lower, 'sangam' ) !== false || strpos( $lower, 'magh-mela' ) !== false ) {
				$target_url = home_url( '/tours/' );
			} elseif ( strpos( $lower, 'bodhgaya' ) !== false || strpos( $lower, 'gaya' ) !== false || strpos( $lower, 'pind-daan' ) !== false ) {
				$target_url = home_url( '/tours/' );
			} elseif ( strpos( $lower, 'cab' ) !== false || strpos( $lower, 'taxi' ) !== false || strpos( $lower, 'innova' ) !== false || strpos( $lower, 'dzire' ) !== false || strpos( $lower, 'tempo' ) !== false ) {
				$target_url = home_url( '/' );
			} elseif ( 0 === strpos( $lower, 'tours/' ) || 0 === strpos( $lower, 'tour/' ) || 0 === strpos( $lower, 'package/' ) ) {
				$target_url = home_url( '/tours/' );
			}
		}

		if ( $target_url ) {
			// Log 404 salvage in plugin telemetry
			if ( class_exists( 'TC_Agents_Logger' ) ) {
				TC_Agents_Logger::log(
					'404_salvaged',
					'info',
					array(
						'original_path' => $raw_uri,
						'redirected_to' => $target_url,
						'user_agent'    => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
						'ip'            => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
					)
				);
			}

			// Perform 301 Moved Permanently redirect
			status_header( 301 );
			header( 'X-Redirect-Agent: TripCosmos-404-Salvage' );
			wp_safe_redirect( $target_url, 301 );
			exit;
		}
	}
}
