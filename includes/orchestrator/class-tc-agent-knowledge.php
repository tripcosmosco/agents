<?php
/**
 * Knowledge Base & Policy Retrieval Engine.
 * Inspired by VM Sales OS Knowledge Module.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Knowledge {

	/**
	 * Search the knowledge base for matching documents, FAQs, and policies.
	 *
	 * @param string $query Keyword search string.
	 * @param int    $limit Max documents to return.
	 * @return array
	 */
	public static function search( $query, $limit = 3 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_knowledge';

		if ( empty( $query ) ) {
			return array();
		}

		$search_term = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, category, content FROM $table WHERE is_active = 1 AND (title LIKE %s OR content LIKE %s OR tags LIKE %s) ORDER BY id DESC LIMIT %d",
				$search_term,
				$search_term,
				$search_term,
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Retrieve compact knowledge snippet for system prompt injection.
	 *
	 * @param string $user_message
	 * @return string
	 */
	public static function get_relevant_context( $user_message ) {
		$docs = self::search( $user_message, 2 );
		if ( empty( $docs ) ) {
			return '';
		}

		$out = "\n--- OFFICIAL KNOWLEDGE BASE & POLICY REFERENCE ---";
		foreach ( $docs as $d ) {
			$out .= "\n[Document: " . esc_html( $d['title'] ) . " (" . esc_html( $d['category'] ) . ")]\n" . esc_html( wp_trim_words( $d['content'], 80 ) );
		}
		$out .= "\n--------------------------------------------------\n";

		return $out;
	}

	/**
	 * Add or update a knowledge document.
	 */
	public static function save_document( array $data, $id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_knowledge';

		$record = array(
			'title'      => sanitize_text_field( $data['title'] ?? '' ),
			'category'   => sanitize_text_field( $data['category'] ?? 'faq' ),
			'content'    => wp_kses_post( $data['content'] ?? '' ),
			'tags'       => sanitize_text_field( $data['tags'] ?? '' ),
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'updated_at' => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $record, array( 'id' => (int) $id ) );
			return $id;
		} else {
			$record['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $record );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete a knowledge document.
	 */
	public static function delete_document( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_knowledge';
		return $wpdb->delete( $table, array( 'id' => (int) $id ) );
	}
}
