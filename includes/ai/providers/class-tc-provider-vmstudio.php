<?php
/**
 * VMStudio In-House AI Router Adapter.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Provider_VMStudio implements TC_AI_Provider_Interface {

	const SLUG = 'vmstudio';

	public function get_slug() {
		return self::SLUG;
	}

	public function get_name() {
		return 'ai.vmstudio.digital';
	}

	public function get_base_url() {
		$url = get_option( 'tc_agents_vmstudio_base_url', 'https://ai.vmstudio.digital/v1' );
		return untrailingslashit( $url );
	}

	public function get_api_key() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'vmstudio_api_key' ) : get_option( 'tc_agents_vmstudio_api_key', '' );
	}

	public function is_configured() {
		return ! empty( $this->get_base_url() ) && ! empty( $this->get_api_key() );
	}

	public function check_health() {
		$start = microtime( true );

		if ( ! $this->is_configured() ) {
			return array(
				'status'     => 'down',
				'latency_ms' => 0,
				'message'    => __( 'API Key or Base URL missing.', 'tripcosmos-agents' ),
			);
		}

		$url = $this->get_base_url() . '/models';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 5,
				'sslverify' => false,
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
		if ( $code < 400 ) {
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

		if ( ! $this->is_configured() ) {
			return new WP_Error( 'vmstudio_not_configured', __( 'VMStudio AI Key is missing.', 'tripcosmos-agents' ) );
		}

		$url = $this->get_base_url() . '/chat/completions';

		$payload = array(
			'model'       => $options['model'] ?? 'gpt-4o-mini',
			'messages'    => $messages,
			'temperature' => $options['temperature'] ?? 0.7,
		);

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$timeout = (int) get_option( 'tc_agents_timeout_seconds', 8 );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $this->get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
			)
		);

		$latency = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'vmstudio_http_error', $response->get_error_message(), array( 'latency_ms' => $latency ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $body['error']['message'] ?? $body['message'] ?? "VMStudio HTTP {$code}";
			return new WP_Error( 'vmstudio_api_error', $msg, array( 'latency_ms' => $latency ) );
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
