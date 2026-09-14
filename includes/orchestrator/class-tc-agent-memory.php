<?php
/**
 * Cross-Session Traveler Memory Engine.
 * Inspired by VMAI Memory Engine architecture.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Memory {

	const REFRESH_INTERVAL = 4; // Refresh memory synthesis every 4 user turns

	/**
	 * Retrieve stored memory for a contact.
	 *
	 * @param int $contact_id
	 * @return object|null
	 */
	public static function get_memory( $contact_id ) {
		if ( empty( $contact_id ) ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_lead_memory';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE contact_id = %d LIMIT 1", $contact_id ) );

		return $row ?: null;
	}

	/**
	 * Compact, prompt-ready rendering of traveler memory.
	 * Injected into LLM context across sessions and channels.
	 *
	 * @param int $contact_id
	 * @return string
	 */
	public static function format_for_prompt( $contact_id ) {
		$mem = self::get_memory( $contact_id );
		if ( ! $mem ) {
			return '';
		}

		$facts       = self::decode_list( $mem->facts );
		$preferences = self::decode_list( $mem->preferences );
		$objections  = self::decode_list( $mem->objections );

		$out = "\n--- TRAVELER PERSISTENT MEMORY ---";
		if ( ! empty( $mem->summary ) ) {
			$out .= "\nSummary: " . esc_html( $mem->summary );
		}
		if ( ! empty( $facts ) ) {
			$out .= "\nKnown Facts: " . esc_html( implode( '; ', $facts ) );
		}
		if ( ! empty( $preferences ) ) {
			$out .= "\nPreferences: " . esc_html( implode( '; ', $preferences ) );
		}
		if ( ! empty( $objections ) ) {
			$out .= "\nConcerns/Objections: " . esc_html( implode( '; ', $objections ) );
		}
		if ( ! empty( $mem->next_best_action ) ) {
			$out .= "\nNext Best Action: " . esc_html( $mem->next_best_action );
		}
		$out .= "\n----------------------------------\n";

		return $out;
	}

	/**
	 * Update or append structured memory insights for a contact.
	 *
	 * @param int   $contact_id
	 * @param array $updates ['summary', 'facts', 'preferences', 'objections', 'next_best_action']
	 */
	public static function save_memory( $contact_id, array $updates ) {
		if ( empty( $contact_id ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_lead_memory';

		$existing = self::get_memory( $contact_id );

		$existing_facts = $existing ? self::decode_list( $existing->facts ) : array();
		$new_facts      = array_values( array_unique( array_merge( $existing_facts, (array) ( $updates['facts'] ?? array() ) ) ) );

		$existing_prefs = $existing ? self::decode_list( $existing->preferences ) : array();
		$new_prefs      = array_values( array_unique( array_merge( $existing_prefs, (array) ( $updates['preferences'] ?? array() ) ) ) );

		$existing_obj   = $existing ? self::decode_list( $existing->objections ) : array();
		$new_obj        = array_values( array_unique( array_merge( $existing_obj, (array) ( $updates['objections'] ?? array() ) ) ) );

		$summary = ! empty( $updates['summary'] ) ? sanitize_textarea_field( $updates['summary'] ) : ( $existing->summary ?? '' );
		$next    = ! empty( $updates['next_best_action'] ) ? sanitize_text_field( $updates['next_best_action'] ) : ( $existing->next_best_action ?? '' );

		$data = array(
			'contact_id'       => $contact_id,
			'summary'          => $summary,
			'facts'            => wp_json_encode( $new_facts ),
			'preferences'      => wp_json_encode( $new_prefs ),
			'objections'       => wp_json_encode( $new_obj ),
			'next_best_action' => $next,
			'updated_at'       => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'contact_id' => $contact_id ) );
		} else {
			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * Fast heuristic memory extraction from message turn.
	 */
	public static function extract_heuristic_insights( $contact_id, $message ) {
		if ( empty( $contact_id ) || empty( $message ) ) {
			return;
		}

		$lower   = strtolower( $message );
		$updates = array(
			'facts'       => array(),
			'preferences' => array(),
			'objections'  => array(),
		);

		// Budget extraction
		if ( preg_match( '/(?:budget|cost|around|under|below)\s*(?:of|is)?\s*(?:₹|rs\.?|inr)?\s*([0-9]+k?|[0-9,]+)/i', $message, $m ) ) {
			$updates['facts'][] = 'Budget mentioned: ' . $m[1];
		}

		// Group size extraction
		if ( preg_match( '/([0-9]+)\s*(?:people|persons|friends|travelers|pax|members)/i', $message, $m ) ) {
			$updates['facts'][] = 'Group size: ' . $m[1] . ' pax';
		}

		// Month / dates
		$months = array( 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' );
		foreach ( $months as $mo ) {
			if ( strpos( $lower, $mo ) !== false ) {
				$updates['facts'][] = 'Travel window: ' . ucfirst( $mo );
				break;
			}
		}

		// Preferences
		if ( strpos( $lower, 'beginner' ) !== false || strpos( $lower, 'first time' ) !== false ) {
			$updates['preferences'][] = 'Beginner / first-time trekker';
		}
		if ( strpos( $lower, 'snow' ) !== false ) {
			$updates['preferences'][] = 'Enthusiastic about snow treks';
		}
		if ( strpos( $lower, 'veg' ) !== false || strpos( $lower, 'jain' ) !== false ) {
			$updates['preferences'][] = 'Vegetarian / dietary requirement';
		}

		// Objections / concerns
		if ( strpos( $lower, 'altitude' ) !== false || strpos( $lower, 'ams' ) !== false || strpos( $lower, 'breath' ) !== false ) {
			$updates['objections'][] = 'Concerned about high-altitude sickness (AMS)';
		}
		if ( strpos( $lower, 'too expensive' ) !== false || strpos( $lower, 'price high' ) !== false ) {
			$updates['objections'][] = 'Price sensitivity indicated';
		}

		if ( ! empty( $updates['facts'] ) || ! empty( $updates['preferences'] ) || ! empty( $updates['objections'] ) ) {
			self::save_memory( $contact_id, $updates );
		}
	}

	private static function decode_list( $json ) {
		$arr = json_decode( (string) $json, true );
		return is_array( $arr ) ? array_values( array_filter( $arr ) ) : array();
	}
}
