<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Canonical OpenAI-compatible gateway (consolidates aipuffer/omniroute/vmstudio).
 */
class TC_Provider_Gateway implements TC_AI_Provider_Interface {
	const SLUG = 'gateway';
	public function get_slug() { return self::SLUG; }
	public function get_name() { return 'AI Gateway (OpenAI-Compatible)'; }
	public function get_base_url() {
		$url = get_option( 'tc_agents_gateway_base_url', '' );
		if ( ! empty( $url ) ) { return untrailingslashit( $url ); }
		foreach ( array( 'tc_agents_vmstudio_base_url', 'tc_agents_omniroute_base_url', 'tc_agents_aipuffer_base_url', 'vmai_ai_base_url' ) as $opt ) {
			$legacy = get_option( $opt, '' );
			if ( ! empty( $legacy ) ) { return untrailingslashit( $legacy ); }
		}
		return 'https://ai.vmstudio.digital/v1';
	}
	public function get_api_key() {
		if ( class_exists( 'TC_Agents_Vault' ) ) {
			foreach ( array( 'gateway_api_key', 'vmstudio_api_key', 'omniroute_api_key', 'aipuffer_api_key' ) as $k ) {
				$v = TC_Agents_Vault::get( $k, '' );
				if ( ! empty( $v ) ) { return $v; }
			}
		}
		return get_option( 'tc_agents_gateway_api_key', '' );
	}
	public function get_model() {
		$model = get_option( 'tc_agents_gateway_model', '' );
		if ( ! empty( $model ) ) { return $model; }
		foreach ( array( 'tc_agents_vmstudio_model', 'tc_agents_omniroute_model', 'tc_agents_aipuffer_model' ) as $opt ) {
			$legacy = get_option( $opt, '' );
			if ( ! empty( $legacy ) ) { return $legacy; }
		}
		return 'default';
	}
	public function is_configured() { return ! empty( $this->get_base_url() ) && ! empty( $this->get_api_key() ); }
	public function check_health() {
		$start = microtime( true );
		if ( ! $this->is_configured() ) {
			return array( 'status' => 'down', 'latency_ms' => 0, 'message' => __( 'Gateway Base URL or API Key missing.', 'tripcosmos-agents' ) );
		}
		$res = wp_remote_get( $this->get_base_url() . '/models', array( 'timeout' => 5, 'sslverify' => false, 'headers' => array( 'Authorization' => 'Bearer ' . $this->get_api_key() ) ) );
		$lat = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) { return array( 'status' => 'down', 'latency_ms' => $lat, 'message' => $res->get_error_message() ); }
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 400 ) { return array( 'status' => 'healthy', 'latency_ms' => $lat, 'message' => __( 'Online', 'tripcosmos-agents' ) ); }
		return array( 'status' => 'degraded', 'latency_ms' => $lat, 'message' => sprintf( __( 'HTTP %d', 'tripcosmos-agents' ), $code ) );
	}
	public function get_models() {
		$cached = get_transient( 'tc_models_gateway' );
		if ( is_array( $cached ) && ! empty( $cached ) ) { return $cached; }
		$models = $this->fetch_live_models();
		if ( ! empty( $models ) ) {
			set_transient( 'tc_models_gateway', $models, 12 * HOUR_IN_SECONDS );
			update_option( 'tc_agents_gateway_models_cache', $models, false );
			update_option( 'tc_agents_gateway_models_synced_at', current_time( 'mysql' ), false );
		}
		return $models;
	}
	public function fetch_live_models() {
		if ( ! $this->is_configured() ) { return array(); }
		$res = wp_remote_get( $this->get_base_url() . '/models', array( 'timeout' => 10, 'sslverify' => false, 'headers' => array( 'Authorization' => 'Bearer ' . $this->get_api_key() ) ) );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 400 ) { return array(); }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$list = $body['data'] ?? $body['models'] ?? array();
		$out = array();
		foreach ( (array) $list as $m ) {
			if ( is_string( $m ) ) { $out[] = array( 'id' => $m, 'name' => $m ); }
			elseif ( is_array( $m ) && ! empty( $m['id'] ) ) { $out[] = array( 'id' => $m['id'], 'name' => $m['name'] ?? $m['id'] ); }
		}
		return $out;
	}
	public function chat( array $messages, array $tools = array(), array $options = array() ) {
		$start = microtime( true );
		if ( ! $this->is_configured() ) { return new WP_Error( 'gateway_not_configured', __( 'AI Gateway Base URL or API Key missing.', 'tripcosmos-agents' ) ); }
		$url = $this->get_base_url() . '/chat/completions';
		$payload = array( 'model' => $options['model'] ?? $this->get_model(), 'messages' => $messages, 'temperature' => $options['temperature'] ?? 0.7 );
		if ( ! empty( $tools ) ) { $payload['tools'] = $tools; }
		$timeout = (int) get_option( 'tc_agents_timeout_seconds', 8 );
		$res = wp_remote_post( $url, array( 'timeout' => $timeout, 'sslverify' => false, 'headers' => array( 'Authorization' => 'Bearer ' . $this->get_api_key(), 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ) ) );
		$lat = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) { return new WP_Error( 'gateway_http_error', $res->get_error_message(), array( 'latency_ms' => $lat ) ); }
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) { $msg = $body['error']['message'] ?? $body['message'] ?? "Gateway HTTP {$code}"; return new WP_Error( 'gateway_api_error', $msg, array( 'latency_ms' => $lat ) ); }
		$choice = $body['choices'][0]['message'] ?? array();
		return array( 'content' => $choice['content'] ?? '', 'tool_calls' => $choice['tool_calls'] ?? array(), 'prompt_tokens' => $body['usage']['prompt_tokens'] ?? 0, 'completion_tokens' => $body['usage']['completion_tokens'] ?? 0, 'latency_ms' => $lat );
	}
}
