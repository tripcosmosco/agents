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
		return class_exists( 'WPAICG_Plugin' ) || defined( 'WPAICG_VERSION' ) || class_exists( '\\WPAICG\\WP_AI_Content_Generator' ) || post_type_exists( 'wpaicg_chatbots' ) || post_type_exists( 'aipkit_chatbot' );
	}

	public function is_configured() {
		return ! empty( $this->get_base_url() ) || $this->is_local_installed() || ! empty( $this->get_api_key() );
	}

	/**
	 * Get configured Bot ID.
	 */
	public function get_bot_id() {
		$bot = get_option( 'tc_agents_aipuffer_bot_id', '' );
		if ( empty( $bot ) ) {
			$bot = get_option( 'vmai_aipuffer_bot_id', '' );
		}
		return (string) $bot;
	}

	/**
	 * Retrieve cached bots list.
	 *
	 * @return array[] Each: ['id' => string, 'name' => string]
	 */
	public static function get_cached_bots() {
		$cached = get_option( 'tc_agents_aipuffer_bots_cache', array() );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Discover chatbots from local WordPress install or remote AI Puffer/AIPKit endpoint.
	 * Mirrors pattern from ai-link-genius-pro and vmai-sales-agent.
	 *
	 * @param string|null $url Optional base URL override.
	 * @param string|null $key Optional API key override.
	 * @return array[] Array of ['id' => string, 'name' => string]
	 */
	public static function discover_bots( $url = null, $key = null ) {
		$instance = new self();
		$url      = ! empty( $url ) ? untrailingslashit( $url ) : $instance->get_base_url();
		$key      = ! empty( $key ) ? $key : $instance->get_api_key();

		$local_bots  = self::discover_local_bots();
		$remote_bots = array();

		$site_base = untrailingslashit( site_url() );
		$is_remote = ! empty( $url ) && ( strtolower( $url ) !== strtolower( $site_base ) );

		if ( $is_remote || empty( $local_bots ) ) {
			$remote_bots = self::discover_remote_bots( $url ?: $site_base, $key );
		}

		$all = array_merge( $local_bots, $remote_bots );

		// Deduplicate by ID
		$unique = array();
		foreach ( $all as $bot ) {
			if ( ! empty( $bot['id'] ) ) {
				$unique[ (string) $bot['id'] ] = $bot;
			}
		}

		$bots = array_values( $unique );

		if ( ! empty( $bots ) ) {
			update_option( 'tc_agents_aipuffer_bots_cache', $bots, false );
			update_option( 'tc_agents_aipuffer_bots_synced_at', current_time( 'mysql' ), false );
		}

		return $bots;
	}

	/**
	 * Discover local bots from CPTs, options, or tables.
	 */
	private static function discover_local_bots() {
		global $wpdb;
		$bots = array();

		// 1. CPT Discovery: AIPKit / WPAICG / AI Power
		$cpt_types = array( 'aipkit_chatbot', 'wpaicg_chatbot', 'wpaicg_chatbots', 'ai_power_bot' );
		foreach ( $cpt_types as $pt ) {
			$posts = get_posts( array(
				'post_type'      => $pt,
				'posts_per_page' => 50,
				'post_status'    => 'any',
			) );
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $p ) {
					$bots[] = array(
						'id'   => (string) $p->ID,
						'name' => $p->post_title ? $p->post_title . ' (Local AI Power)' : 'Bot #' . $p->ID,
					);
				}
			}
		}

		// 2. Legacy WPAICG database table discovery
		$table_name = $wpdb->prefix . 'wpaicg_chatbot';
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $table_exists === $table_name ) {
			$rows = $wpdb->get_results( "SELECT id, name FROM {$table_name} ORDER BY id DESC LIMIT 50", ARRAY_A );
			if ( ! empty( $rows ) ) {
				foreach ( $rows as $r ) {
					$bots[] = array(
						'id'   => (string) $r['id'],
						'name' => ( $r['name'] ?? 'Bot #' . $r['id'] ) . ' (Local Table)',
					);
				}
			}
		}

		// 3. Meow Apps AI Engine options
		$mwai_opts = get_option( 'mwai_options' );
		if ( ! empty( $mwai_opts['chatbots'] ) && is_array( $mwai_opts['chatbots'] ) ) {
			foreach ( $mwai_opts['chatbots'] as $b ) {
				if ( ! empty( $b['id'] ) ) {
					$bots[] = array(
						'id'   => (string) $b['id'],
						'name' => ( $b['name'] ?? $b['id'] ) . ' (AI Engine)',
					);
				}
			}
		}

		// 4. WPAICG BotStorage class if present
		if ( class_exists( '\\WPAICG\\Chat\\Storage\\BotStorage' ) ) {
			try {
				$storage = new \WPAICG\Chat\Storage\BotStorage();
				if ( method_exists( $storage, 'get_chatbots' ) ) {
					$storage_bots = $storage->get_chatbots( false );
					if ( is_array( $storage_bots ) ) {
						foreach ( $storage_bots as $sb ) {
							$bid = is_object( $sb ) ? ( $sb->ID ?? '' ) : ( $sb['id'] ?? '' );
							$bname = is_object( $sb ) ? ( $sb->post_title ?? '' ) : ( $sb['name'] ?? '' );
							if ( $bid ) {
								$bots[] = array(
									'id'   => (string) $bid,
									'name' => $bname ?: 'Bot #' . $bid,
								);
							}
						}
					}
				}
			} catch ( \Throwable $e ) {}
		}

		return $bots;
	}

	/**
	 * Discover remote bots via REST endpoints.
	 */
	private static function discover_remote_bots( $url, $key = '' ) {
		$url = untrailingslashit( $url );
		if ( empty( $url ) ) {
			return array();
		}

		$endpoints = array(
			'/wp-json/aipkit/v1/chat/list',
			'/wp-json/wpaicg/v1/chat/list',
			'/wp-json/aipuffer/v1/bots',
			'/wp-json/aipuffer/v1/chat/list',
			'/wp-json/mwai/v1/bots',
		);

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( ! empty( $key ) ) {
			$headers['Authorization'] = 'Bearer ' . $key;
			$headers['X-API-KEY']     = $key;
		}

		foreach ( $endpoints as $path ) {
			$target = $url . $path;
			if ( strpos( $url, '?rest_route=' ) !== false ) {
				$target = rtrim( $url, '/' ) . '&aipkit_route=' . rawurlencode( $path );
			}

			$res = wp_remote_get( $target, array(
				'timeout'   => 10,
				'sslverify' => false,
				'headers'   => $headers,
			) );

			if ( is_wp_error( $res ) ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $res );
			if ( $code >= 400 ) {
				continue;
			}

			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			$list = $data['bots'] ?? $data['data'] ?? ( $data['chatbots'] ?? array() );

			if ( is_array( $list ) && ! empty( $list ) ) {
				$found = array();
				foreach ( $list as $b ) {
					$id   = $b['id'] ?? $b['ID'] ?? $b['bot_id'] ?? '';
					$name = $b['name'] ?? $b['post_title'] ?? $b['title'] ?? ( 'Bot #' . $id );
					if ( '' !== (string) $id ) {
						$found[] = array(
							'id'   => (string) $id,
							'name' => $name . ' (Remote AIPKit)',
						);
					}
				}
				if ( ! empty( $found ) ) {
					return $found;
				}
			}
		}

		return array();
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

		// Path 1 (primary, mirrors VMAI): bot chat bridge POST {base}/wp-json/aipkit/v1/chat/{bot_id}/message {messages}.
		$bot_id = $options['bot_id'] ?? get_option( 'tc_agents_aipuffer_bot_id', get_option( 'vmai_aipuffer_bot_id', '' ) );
		if ( ! empty( $bot_id ) ) {
			$url = $base . '/wp-json/aipkit/v1/chat/' . rawurlencode( $bot_id ) . '/message';
			if ( strpos( $base, '?rest_route=' ) !== false ) {
				$url = rtrim( $base, '/' ) . '&aipkit_route=/chat/' . rawurlencode( $bot_id ) . '/message';
			}
			$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
			if ( ! empty( $key ) ) { $headers['Authorization'] = 'Bearer ' . $key; }
			$timeout = (int) get_option( 'tc_agents_timeout_seconds', 8 );

			// Extract last user message to support bots expecting single 'message' or 'prompt' string
			$last_user_msg = '';
			for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
				if ( ( $messages[ $i ]['role'] ?? '' ) === 'user' ) {
					$last_user_msg = (string) ( $messages[ $i ]['content'] ?? '' );
					break;
				}
			}

			$bridge_payload = array(
				'message'  => $last_user_msg,
				'prompt'   => $last_user_msg,
				'messages' => $messages,
			);

			$response = wp_remote_post( $url, array( 'timeout' => $timeout, 'sslverify' => false, 'headers' => $headers, 'body' => wp_json_encode( $bridge_payload ) ) );
			$latency = (int) round( ( microtime( true ) - $start_time ) * 1000 );
			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				$content = $body['reply'] ?? $body['data']['reply'] ?? $body['content'] ?? $body['response'] ?? $body['answer'] ?? $body['text'] ?? $body['choices'][0]['message']['content'] ?? '';
				if ( '' !== $content ) {
					return array( 'content' => $content, 'tool_calls' => $body['choices'][0]['message']['tool_calls'] ?? array(), 'prompt_tokens' => 0, 'completion_tokens' => 0, 'latency_ms' => $latency );
				}
			}
			// Fall through to generate endpoint if bot bridge fails.
		}

		// Path 2: AIPKit generate endpoint /wp-json/aipkit/v1/generate.
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
