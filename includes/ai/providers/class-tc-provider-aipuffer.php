<?php
/**
 * AI Puffer / AIPKit Provider Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Provider_AIPuffer implements TC_AI_Provider_Interface {

	const SLUG = 'aipuffer';

	public function get_slug() {
		return self::SLUG;
	}

	public function get_name() {
		return 'AI Puffer (AIPKit)';
	}

	/**
	 * Retrieve base URL, checking local plugin options first or reusing existing vmai options.
	 */
	public function get_base_url() {
		$url = get_option( 'tc_agents_aipuffer_base_url', '' );
		if ( empty( $url ) ) {
			$url = get_option( 'vmai_ai_base_url', '' );
		}
		if ( empty( $url ) && $this->is_local_installed() ) {
			$url = site_url();
		}
		return untrailingslashit( $url );
	}

	/**
	 * Retrieve API key, checking local options or inherited vmai options.
	 */
	public function get_api_key() {
		$key = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'aipuffer_api_key' ) : get_option( 'tc_agents_aipuffer_api_key', '' );
		if ( empty( $key ) ) {
			$key = get_option( 'vmai_ai_api_key', '' );
			if ( class_exists( 'VMAI_Encryption' ) && method_exists( 'VMAI_Encryption', 'decrypt' ) && ! empty( $key ) ) {
				$key = VMAI_Encryption::decrypt( $key );
			}
		}
		return $key;
	}


	/**
	 * Check if AI Puffer / AI Power is locally installed on the WordPress instance.
	 */
	public function is_local_installed() {
		return class_exists( 'WPAICG_Plugin' ) || defined( 'WPAICG_VERSION' ) || class_exists( '\\WPAICG\\WP_AI_Content_Generator' );
	}

	public function is_configured() {
		return ! empty( $this->get_base_url() ) || $this->is_local_installed();
	}

	public function check_health() {
		$start = microtime( true );

		if ( ! $this->is_configured() ) {
			return array(
				'status'     => 'down',
				'latency_ms' => 0,
				'message'    => __( 'AI Puffer is not configured. Missing Base URL or API Key.', 'tripcosmos-agents' ),
			);
		}

		$base = $this->get_base_url();
		if ( empty( $base ) ) {
			$base = site_url();
		}

		$endpoint = $base . '/wp-json/aipkit/v1/health';
		$headers  = array(
			'Accept' => 'application/json',
		);
		$key      = $this->get_api_key();
		if ( ! empty( $key ) ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'headers'   => $headers,
			)
		);

		$latency = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			// Fallback: If health endpoint 404s, try a dummy handshake on root rest
			$root_resp = wp_remote_get( $base . '/wp-json/wp/v2/', array( 'timeout' => 4, 'sslverify' => false ) );
			if ( ! is_wp_error( $root_resp ) && wp_remote_retrieve_response_code( $root_resp ) < 500 ) {
				return array(
					'status'     => 'healthy',
					'latency_ms' => $latency,
					'message'    => __( 'AI Puffer host reachable.', 'tripcosmos-agents' ),
				);
			}

			return array(
				'status'     => 'down',
				'latency_ms' => $latency,
				'message'    => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 500 ) {
			return array(
				'status'     => 'down',
				'latency_ms' => $latency,
				'message'    => sprintf( __( 'Server error HTTP %d', 'tripcosmos-agents' ), $code ),
			);
		}

		return array(
			'status'     => 'healthy',
			'latency_ms' => $latency,
			'message'    => __( 'Online', 'tripcosmos-agents' ),
		);
	}

	public function chat( array $messages, array $tools = array(), array $options = array() ) {
		$start_time = microtime( true );
		$base       = $this->get_base_url();
		$key        = $this->get_api_key();

		if ( empty( $base ) ) {
			$base = site_url();
		}

		// AIPKit endpoint: /wp-json/aipkit/v1/generate or fallback /chat
		$url = $base . '/wp-json/aipkit/v1/generate';
		if ( strpos( $base, '?rest_route=' ) !== false ) {
			$url = rtrim( $base, '/' ) . '&aipkit_route=/generate';
		}

		$payload = array(
			'provider'    => $options['provider'] ?? 'openai',
			'model'       => $options['model'] ?? 'gpt-4o-mini',
			'messages'    => $messages,
			'temperature' => $options['temperature'] ?? 0.7,
		);

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( ! empty( $key ) ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$timeout = (int) get_option( 'tc_agents_timeout_seconds', 8 );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => false,
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
			)
		);

		$latency = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aipuffer_http_error', $response->get_error_message(), array( 'latency_ms' => $latency ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $body['message'] ?? $body['error'] ?? "AI Puffer HTTP {$code}";
			return new WP_Error( 'aipuffer_api_error', $msg, array( 'latency_ms' => $latency ) );
		}

		$content    = '';
		$tool_calls = array();

		// Parse AIPKit/OpenAI response format
		if ( isset( $body['choices'][0]['message'] ) ) {
			$choice     = $body['choices'][0]['message'];
			$content    = $choice['content'] ?? '';
			$tool_calls = $choice['tool_calls'] ?? array();
		} elseif ( isset( $body['reply'] ) ) {
			$content = $body['reply'];
		} elseif ( isset( $body['data']['reply'] ) ) {
			$content = $body['data']['reply'];
		} elseif ( isset( $body['content'] ) ) {
			$content = $body['content'];
		}

		return array(
			'content'           => $content,
			'tool_calls'        => $tool_calls,
			'prompt_tokens'     => $body['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
			'latency_ms'        => $latency,
		);
	}
}
