<?php
/**
 * TripCosmos Agents - Lead Memory Engine
 *
 * Provides durable per-contact memory and contextual recall across chat sessions,
 * WhatsApp interactions, phone calls, and quotation adjustments.
 *
 * Automatically injects remembered facts (destinations, dates, group size, budget,
 * hotel preferences, special darshan requirements, dietary restrictions) into
 * AI sales assistant prompts.
 *
 * Ported from VM Sales OS intelligence layer.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Lead_Memory {

	const META_KEY = 'tc_lead_memory';

	/**
	 * Get all remembered facts for a contact.
	 *
	 * @param int $contact_id
	 * @return array
	 */
	public static function get_all( $contact_id ) {
		$contact_id = (int) $contact_id;
		if ( ! $contact_id ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_contacts';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT meta_data FROM $table WHERE id = %d", $contact_id ), ARRAY_A );
		if ( ! $row || empty( $row['meta_data'] ) ) {
			return array();
		}

		$meta = json_decode( (string) $row['meta_data'], true );
		return is_array( $meta ) && isset( $meta[ self::META_KEY ] ) && is_array( $meta[ self::META_KEY ] )
			? $meta[ self::META_KEY ]
			: array();
	}

	/**
	 * Remember a specific fact or preference for a contact.
	 *
	 * @param int    $contact_id
	 * @param string $key
	 * @param mixed  $value
	 * @return bool
	 */
	public static function remember( $contact_id, $key, $value ) {
		$contact_id = (int) $contact_id;
		if ( ! $contact_id || empty( $key ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_contacts';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT meta_data FROM $table WHERE id = %d", $contact_id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}

		$meta = json_decode( (string) $row['meta_data'], true ) ?: array();
		if ( ! isset( $meta[ self::META_KEY ] ) || ! is_array( $meta[ self::META_KEY ] ) ) {
			$meta[ self::META_KEY ] = array();
		}

		$clean_key = sanitize_key( $key );
		$meta[ self::META_KEY ][ $clean_key ] = array(
			'value'      => is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value,
			'updated_at' => current_time( 'mysql' ),
		);

		return false !== $wpdb->update(
			$table,
			array(
				'meta_data'  => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $contact_id )
		);
	}

	/**
	 * Recall a specific fact for a contact.
	 *
	 * @param int    $contact_id
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function recall( $contact_id, $key, $default = null ) {
		$memory    = self::get_all( $contact_id );
		$clean_key = sanitize_key( $key );
		return isset( $memory[ $clean_key ]['value'] ) ? $memory[ $clean_key ]['value'] : $default;
	}

	/**
	 * Format remembered context into a clean markdown block for AI prompts.
	 *
	 * @param int $contact_id
	 * @return string
	 */
	public static function summarize_for_prompt( $contact_id ) {
		$memory = self::get_all( $contact_id );
		if ( empty( $memory ) ) {
			return '';
		}

		$lines = array();
		$lines[] = "### Remembered Traveler Context (Durable Memory):";
		foreach ( $memory as $k => $item ) {
			$val = is_scalar( $item['value'] ) ? (string) $item['value'] : wp_json_encode( $item['value'] );
			$label = ucwords( str_replace( '_', ' ', $k ) );
			$lines[] = "- **{$label}**: {$val}";
		}

		return implode( "\n", $lines ) . "\n";
	}
}
