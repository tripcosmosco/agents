<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Google Gemini provider (native generativelanguage API + OpenAI-compat mapped to live models).
 */
class TC_Provider_Gemini implements TC_AI_Provider_Interface {
	const SLUG = 'gemini';
	public function get_slug() { return self::SLUG; }
	public function get_name() { return 'Google Gemini'; }
	public function get_api_key() {
		if ( class_exists( 'TC_Agents_Vault' ) ) { $v = TC_Agents_Vault::get( 'gemini_api_key', '' ); if ( ! empty( $v ) ) { return $v; } }
		return get_option( 'tc_agents_gemini_api_key', '' );
	}
	public function get_model() { $m = get_option( 'tc_agents_gemini_model', '' ); return ! empty( $m ) ? $m : 'gemini-2.0-flash'; }
	public function is_configured() { return ! empty( $this->get_api_key() ); }
	public function check_health() {
		$start = microtime( true );
		if ( ! $this->is_configured() ) { return array( 'status' => 'down', 'latency_ms' => 0, 'message' => __( 'Gemini API key missing.', 'tripcosmos-agents' ) ); }
		$res = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->get_api_key(), array( 'timeout' => 8, 'sslverify' => true ) );
		$lat = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) { return array( 'status' => 'down', 'latency_ms' => $lat, 'message' => $res->get_error_message() ); }
		$code = wp_remote_retrieve_response_code( $res );
		if ( 200 === $code ) { return array( 'status' => 'healthy', 'latency_ms' => $lat, 'message' => __( 'Online', 'tripcosmos-agents' ) ); }
		return array( 'status' => 'degraded', 'latency_ms' => $lat, 'message' => sprintf( __( 'HTTP %d', 'tripcosmos-agents' ), $code ) );
	}
	public function get_models() {
		$cached = get_transient( 'tc_models_gemini' );
		if ( is_array( $cached ) && ! empty( $cached ) ) { return $cached; }
		$models = $this->fetch_live_models();
		if ( ! empty( $models ) ) {
			set_transient( 'tc_models_gemini', $models, 12 * HOUR_IN_SECONDS );
			update_option( 'tc_agents_gemini_models_cache', $models, false );
			update_option( 'tc_agents_gemini_models_synced_at', current_time( 'mysql' ), false );
		}
		return $models;
	}
	public function fetch_live_models() {
		if ( ! $this->is_configured() ) { return array(); }
		$res = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->get_api_key(), array( 'timeout' => 10, 'sslverify' => true ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) { return array(); }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$out = array();
		foreach ( (array) ( $body['models'] ?? array() ) as $m ) {
			$name = $m['name'] ?? '';
			if ( empty( $name ) ) { continue; }
			$id = preg_replace( '#^models/#', '', $name );
			if ( stripos( $id, 'gemini' ) === false ) { continue; }
			$out[] = array( 'id' => $id, 'name' => $m['displayName'] ?? $id );
		}
		return $out;
	}
	public function chat( array $messages, array $tools = array(), array $options = array() ) {
		$start = microtime( true );
		$key   = $this->get_api_key();
		if ( empty( $key ) ) {
			return new WP_Error( 'gemini_not_configured', __( 'Gemini API key missing.', 'tripcosmos-agents' ) );
		}
		$model = $options['model'] ?? $this->get_model();
		$model_clean = preg_replace( '#^models/#', '', trim( (string) $model ) );

		$system_text = '';
		$contents    = array();

		foreach ( $messages as $m ) {
			$raw_role = $m['role'] ?? 'user';
			$content  = (string) ( $m['content'] ?? '' );

			if ( 'system' === $raw_role ) {
				if ( '' !== trim( $content ) ) {
					$system_text .= ( empty( $system_text ) ? '' : "\n\n" ) . $content;
				}
				continue;
			}

			if ( '' === trim( $content ) ) {
				if ( ! empty( $m['tool_calls'] ) ) {
					$content = '[Consulting travel tools and booking database...]';
				} else {
					continue;
				}
			}

			if ( 'tool' === $raw_role ) {
				$tool_name = $m['name'] ?? 'system_tool';
				$content   = "[Tool Output ({$tool_name})]: " . $content;
				$role      = 'user';
			} else {
				$role = ( 'assistant' === $raw_role ) ? 'model' : 'user';
			}

			// Enforce strictly alternating user/model turns as required by Gemini API
			$last_idx = count( $contents ) - 1;
			if ( $last_idx >= 0 && $contents[ $last_idx ]['role'] === $role ) {
				$contents[ $last_idx ]['parts'][0]['text'] .= "\n\n" . $content;
			} else {
				$contents[] = array(
					'role'  => $role,
					'parts' => array( array( 'text' => $content ) ),
				);
			}
		}

		if ( empty( $contents ) ) {
			$contents[] = array(
				'role'  => 'user',
				'parts' => array( array( 'text' => 'Namaste' ) ),
			);
		}

		$payload = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'temperature' => (float) ( $options['temperature'] ?? 0.7 ),
			),
		);

		if ( ! empty( $system_text ) ) {
			$payload['system_instruction'] = array(
				'parts' => array( array( 'text' => $system_text ) ),
			);
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model_clean ) . ':generateContent?key=' . $key;
		$res = wp_remote_post(
			$url,
			array(
				'timeout'   => (int) get_option( 'tc_agents_timeout_seconds', 8 ),
				'sslverify' => true,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $payload ),
			)
		);

		$lat = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'gemini_http_error', $res->get_error_message(), array( 'latency_ms' => $lat ) );
		}

		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			$msg = $body['error']['message'] ?? "Gemini HTTP {$code}";
			return new WP_Error( 'gemini_api_error', $msg, array( 'latency_ms' => $lat ) );
		}

		$text = '';
		foreach ( (array) ( $body['candidates'][0]['content']['parts'] ?? array() ) as $p ) {
			$text .= ( $p['text'] ?? '' );
		}

		if ( empty( $text ) && ! empty( $body['candidates'][0]['finishReason'] ) && 'STOP' !== $body['candidates'][0]['finishReason'] ) {
			return new WP_Error( 'gemini_safety_blocked', sprintf( __( 'Gemini filtered response (%s).', 'tripcosmos-agents' ), $body['candidates'][0]['finishReason'] ), array( 'latency_ms' => $lat ) );
		}

		return array(
			'content'           => $text,
			'tool_calls'        => array(),
			'prompt_tokens'     => $body['usageMetadata']['promptTokenCount'] ?? 0,
			'completion_tokens' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
			'latency_ms'        => $lat,
		);
	}
}
