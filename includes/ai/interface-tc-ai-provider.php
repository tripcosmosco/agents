<?php
/**
 * Common AI Provider Interface.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TC_AI_Provider_Interface {

	/**
	 * Unique slug for the provider.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Human-readable display name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Whether the provider has minimum required configuration (keys/URLs).
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Perform a lightweight health check ping.
	 *
	 * @return array ['status' => 'healthy'|'degraded'|'down', 'latency_ms' => int, 'message' => string]
	 */
	public function check_health();

	/**
	 * Execute a chat completion with messages and optional tool definitions.
	 *
	 * @param array $messages Array of message objects [['role' => 'user', 'content' => '...']]
	 * @param array $tools    Array of tool definition schemas (OpenAI format).
	 * @param array $options  Additional parameters (temperature, max_tokens, etc.).
	 * @return array ['content' => string, 'tool_calls' => array, 'prompt_tokens' => int, 'completion_tokens' => int, 'latency_ms' => int]|WP_Error
	 */
	public function chat( array $messages, array $tools = array(), array $options = array() );
}
