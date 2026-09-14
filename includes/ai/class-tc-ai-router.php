<?php
/**
 * AI Provider Router with Automatic Failover and Circuit Breaking.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_AI_Router {

	/**
	 * Registered provider instances.
	 *
	 * @var TC_AI_Provider_Interface[]
	 */
	private $providers = array();

	/**
	 * Singleton instance.
	 *
	 * @var TC_AI_Router|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->register_providers();
	}

	/**
	 * Instantiate and register all available provider adapters.
	 */
	private function register_providers() {
		$this->providers['aipuffer']   = new TC_Provider_AIPuffer();
		$this->providers['openrouter'] = new TC_Provider_OpenRouter();
		$this->providers['omniroute']  = new TC_Provider_Omniroute();
		$this->providers['vmstudio']   = new TC_Provider_VMStudio();
	}

	/**
	 * Get all registered providers.
	 *
	 * @return TC_AI_Provider_Interface[]
	 */
	public function get_providers() {
		return $this->providers;
	}

	/**
	 * Get a specific provider by slug.
	 *
	 * @param string $slug
	 * @return TC_AI_Provider_Interface|null
	 */
	public function get_provider( $slug ) {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Get configured priority chain of provider slugs.
	 *
	 * @return string[]
	 */
	public function get_priority_chain() {
		$chain = get_option( 'tc_agents_provider_priority', array( 'aipuffer', 'openrouter', 'omniroute', 'vmstudio' ) );
		if ( ! is_array( $chain ) || empty( $chain ) ) {
			$chain = array( 'aipuffer', 'openrouter', 'omniroute', 'vmstudio' );
		}
		return $chain;
	}

	/**
	 * Check circuit breaker status for a provider.
	 *
	 * @param string $slug
	 * @return bool True if tripped (unavailable), false if closed (healthy).
	 */
	public function is_circuit_tripped( $slug ) {
		$tripped_until = (int) get_transient( 'tc_circuit_tripped_' . $slug );
		return $tripped_until > time();
	}

	/**
	 * Record a provider failure and trip circuit breaker if threshold exceeded.
	 */
	public function record_failure( $slug, $error_message = '' ) {
		$fail_count = (int) get_transient( 'tc_fail_count_' . $slug ) + 1;
		set_transient( 'tc_fail_count_' . $slug, $fail_count, 15 * MINUTE_IN_SECONDS );

		$threshold = (int) get_option( 'tc_agents_circuit_breaker_threshold', 2 );

		if ( $fail_count >= $threshold ) {
			// Trip circuit for 5 minutes
			set_transient( 'tc_circuit_tripped_' . $slug, time() + ( 5 * MINUTE_IN_SECONDS ), 5 * MINUTE_IN_SECONDS );
			TC_Agents_Logger::log(
				'circuit_breaker_tripped',
				'warning',
				array(
					'provider'   => $slug,
					'failures'   => $fail_count,
					'threshold'  => $threshold,
					'cooldown'   => '5m',
					'last_error' => $error_message,
				)
			);
		}
	}

	/**
	 * Reset failure counter on success.
	 */
	public function record_success( $slug ) {
		delete_transient( 'tc_fail_count_' . $slug );
		delete_transient( 'tc_circuit_tripped_' . $slug );
	}

	/**
	 * Check health of all providers.
	 *
	 * @return array
	 */
	public function check_all_health() {
		$results = array();
		foreach ( $this->providers as $slug => $provider ) {
			$cached = get_transient( 'tc_health_' . $slug );
			if ( false !== $cached && is_array( $cached ) ) {
				$results[ $slug ] = $cached;
				continue;
			}

			if ( ! $provider->is_configured() ) {
				$status = array(
					'name'         => $provider->get_name(),
					'configured'   => false,
					'status'       => 'unconfigured',
					'latency_ms'   => 0,
					'message'      => __( 'Not configured', 'tripcosmos-agents' ),
					'checked_at'   => current_time( 'mysql' ),
				);
			} else {
				$health = $provider->check_health();
				$status = array(
					'name'         => $provider->get_name(),
					'configured'   => true,
					'status'       => $health['status'] ?? 'down',
					'latency_ms'   => $health['latency_ms'] ?? 0,
					'message'      => $health['message'] ?? '',
					'checked_at'   => current_time( 'mysql' ),
				);
			}

			set_transient( 'tc_health_' . $slug, $status, 60 );
			$results[ $slug ] = $status;
		}

		return $results;
	}

	/**
	 * Dispatch a chat completion with automatic failover across the priority chain.
	 *
	 * @param array  $messages Array of conversation messages.
	 * @param array  $tools    Array of tool definition schemas.
	 * @param array  $options  Configuration options (temperature, routing_override, etc.).
	 * @return array Normalized response array with 'provider_used', 'content', 'tool_calls', 'latency_ms'.
	 * @throws Exception When all configured providers fail.
	 */
	public function chat( array $messages, array $tools = array(), array $options = array() ) {
		$chain = $this->get_priority_chain();

		// Check if a specific routing override was passed (e.g. from agent persona settings)
		if ( ! empty( $options['routing_override'] ) && isset( $this->providers[ $options['routing_override'] ] ) ) {
			$chain = array_unique( array_merge( array( $options['routing_override'] ), $chain ) );
		}

		$errors_encountered = array();

		foreach ( $chain as $slug ) {
			$provider = $this->get_provider( $slug );
			if ( ! $provider ) {
				continue;
			}

			// Check configuration
			if ( ! $provider->is_configured() ) {
				continue;
			}

			// Check circuit breaker
			if ( $this->is_circuit_tripped( $slug ) ) {
				$errors_encountered[ $slug ] = 'Circuit breaker tripped (cooling down)';
				continue;
			}

			// Attempt request
			$result = $provider->chat( $messages, $tools, $options );

			if ( is_wp_error( $result ) ) {
				$err_msg = $result->get_error_message();
				$this->record_failure( $slug, $err_msg );
				$errors_encountered[ $slug ] = $err_msg;

				TC_Agents_Logger::log(
					'provider_failed',
					'warning',
					array(
						'provider' => $slug,
						'error'    => $err_msg,
						'action'   => 'Attempting next provider in fallback chain',
					)
				);
				continue; // Fall through to next provider
			}

			// Success! Record clean run
			$this->record_success( $slug );
			$result['provider_used'] = $slug;

			// If this was not the primary provider, log the failover success
			if ( $slug !== $chain[0] ) {
				TC_Agents_Logger::log(
					'failover_served',
					'info',
					array(
						'primary_requested' => $chain[0],
						'provider_served'   => $slug,
						'latency_ms'        => $result['latency_ms'] ?? 0,
					)
				);
			}

			return $result;
		}

		// If loop completes without return, all providers failed
		$all_err = wp_json_encode( $errors_encountered );
		TC_Agents_Logger::log(
			'all_providers_failed',
			'critical',
			array( 'errors' => $errors_encountered )
		);

		return new WP_Error(
			'all_providers_exhausted',
			__( 'All AI providers in the failover chain are currently unreachable. Please try again shortly.', 'tripcosmos-agents' ),
			array( 'provider_errors' => $errors_encountered )
		);
	}
}
