<?php
/**
 * OpenRouter Provider Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Provider_OpenRouter implements TC_AI_Provider_Interface {

	const SLUG = 'openrouter';
	const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

	public function get_slug() {
		return self::SLUG;
	}

	public function get_name() {
		return 'OpenRouter';
	}

	public function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'openrouter_api_key' ) : get_option( 'tc_agents_openrouter_api_key', '' );
	}

	public function get_model() {
		$model = get_option( 'tc_agents_openrouter_model', '' );
		return ! empty( $model ) ? $model : 'anthropic/claude-3.5-sonnet';
	}

	public function is_configured() {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Live OpenRouter catalogue (/api/v1/models), cached 12h.
	 *
	 * @return array[] Each: ['id','name']
	 */
	public function get_models() {
		$cached = get_transient( 'tc_models_openrouter' );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		$models = $this->fetch_live_models();
		if ( ! empty( $models ) ) {
			set_transient( 'tc_models_openrouter', $models, 12 * HOUR_IN_SECONDS );
			update_option( 'tc_agents_openrouter_models_cache', $models, false );
			update_option( 'tc_agents_openrouter_models_synced_at', current_time( 'mysql' ), false );
		}
		return $models;
	}

	public function fetch_live_models() {
		if ( ! $this->is_configured() ) {
			return array();
		}
		$res = wp_remote_get(
			'https://openrouter.ai/api/v1/models',
			array(
				'timeout'   => 10,
				'sslverify' => true,
				'headers'   => array( 'Authorization' => 'Bearer ' . $this->get_api_key() ),
			)
		);
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 400 ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out  = array();
		foreach ( (array) ( $body['data'] ?? array() ) as $m ) {
			if ( empty( $m['id'] ) ) {
				continue;
			}
			$out[] = array( 'id' => $m['id'], 'name' => $m['name'] ?? $m['id'] );
		}
		return $out;
	}

	public function check_health() {
		$start = microtime( true );

		if ( ! $this->is_configured() ) {
			return array(
				'status'     => 'down',
				'latency_ms' => 0,
				'message'    => __( 'API Key not configured.', 'tripcosmos-agents' ),
			);
		}

		$response = wp_remote_get(
			'https://openrouter.ai/api/v1/auth/key',
			array(
				'timeout'   => 5,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $this->get_api_key(),
				),
			)
		);

		$latency = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'status'     => 'down',
				'latency_ms' => $latency,
				'message'    => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return array(
				'status'     => 'healthy',
				'latency_ms' => $latency,
				'message'    => __( 'Online', 'tripcosmos-agents' ),
			);
		}

		return array(
			'status'     => 'degraded',
			'latency_ms' => $latency,
			'message'    => sprintf( __( 'HTTP %d', 'tripcosmos-agents' ), $code ),
		);
	}

	public function chat( array $messages, array $tools = array(), array $options = array() ) {
		$start_time = microtime( true );
		$key        = $this->get_api_key();

		if ( empty( $key ) ) {
			return new WP_Error( 'openrouter_not_configured', __( 'OpenRouter API Key is missing.', 'tripcosmos-agents' ) );
		}

		$payload = array(
			'model'       => $options['model'] ?? $this->get_model(),
			'messages'    => $messages,
			'temperature' => $options['temperature'] ?? 0.7,
		);

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$timeout = (int) get_option( 'tc_agents_timeout_seconds', 8 );

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout'   => $timeout,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'TripCosmos Agents',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		$latency = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'openrouter_http_error', $response->get_error_message(), array( 'latency_ms' => $latency ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $body['error']['message'] ?? "OpenRouter HTTP {$code}";
			return new WP_Error( 'openrouter_api_error', $msg, array( 'latency_ms' => $latency ) );
		}

		$choice     = $body['choices'][0]['message'] ?? array();
		$content    = $choice['content'] ?? '';
		$tool_calls = $choice['tool_calls'] ?? array();

		return array(
			'content'           => $content,
			'tool_calls'        => $tool_calls,
			'prompt_tokens'     => $body['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
			'latency_ms'        => $latency,
		);
	}
}
