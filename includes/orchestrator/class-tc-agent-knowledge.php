<?php
/**
 * Hybrid Vector & Full-Text Knowledge Base Engine for TripCosmos Agents.
 *
 * Implements semantic RAG (Retrieval-Augmented Generation) with chunking,
 * cosine embeddings search, full-text fallback, and catalog indexing.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Knowledge {

	/**
	 * Search the knowledge base using hybrid semantic vector and full-text retrieval.
	 *
	 * @param string $query User query or keyword phrase.
	 * @param int    $limit Max documents to return.
	 * @return array
	 */
	public static function search( $query, $limit = 3 ) {
		$query = trim( sanitize_text_field( $query ) );
		if ( '' === $query ) {
			return array();
		}

		$limit = max( 1, (int) $limit );

		// 1. Try Semantic Vector Retrieval
		$is_vector_enabled = '1' === (string) get_option( 'tc_agents_enable_vector_search', '1' );
		if ( $is_vector_enabled ) {
			$query_vector = TC_Agent_Vector_Store::generate_embedding( $query );
			if ( ! empty( $query_vector ) ) {
				$vector_matches = TC_Agent_Vector_Store::search_semantic( $query_vector, $limit, 0.42 );
				if ( ! empty( $vector_matches ) ) {
					$results = array();
					foreach ( $vector_matches as $m ) {
						$results[] = array(
							'id'          => $m['id'],
							'document_id' => $m['document_id'],
							'title'       => __( 'Semantic Match', 'tripcosmos-agents' ),
							'category'    => 'vector_rag',
							'content'     => $m['content'],
							'score'       => $m['score'],
						);
					}
					return $results;
				}
			}
		}

		// 2. Try MySQL FULLTEXT Chunk Search
		$chunk_matches = TC_Agent_Vector_Store::search_fulltext( $query, $limit );
		if ( ! empty( $chunk_matches ) ) {
			$results = array();
			foreach ( $chunk_matches as $m ) {
				$results[] = array(
					'id'          => $m['id'],
					'document_id' => $m['document_id'],
					'title'       => __( 'Fulltext Match', 'tripcosmos-agents' ),
					'category'    => 'chunk',
					'content'     => $m['content'],
					'score'       => $m['score'] ?? 0.5,
				);
			}
			return $results;
		}

		// 3. Fallback to classic document LIKE search
		global $wpdb;
		$table       = $wpdb->prefix . 'tc_agent_knowledge';
		$search_term = '%' . $wpdb->esc_like( $query ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, category, content FROM $table
				 WHERE is_active = 1 AND (title LIKE %s OR content LIKE %s OR tags LIKE %s)
				 ORDER BY id DESC LIMIT %d",
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
			$title = ! empty( $d['title'] ) ? $d['title'] : 'Knowledge Context';
			$cat   = ! empty( $d['category'] ) ? $d['category'] : 'general';
			$out  .= "\n[Reference: " . esc_html( $title ) . " (" . esc_html( $cat ) . ")]\n" . esc_html( wp_trim_words( $d['content'], 120 ) );
		}
		$out .= "\n--------------------------------------------------\n";

		return $out;
	}

	/**
	 * Add or update a knowledge document and enqueue async chunk indexing.
	 *
	 * @param array $data
	 * @param int   $id
	 * @return int Document ID.
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
			$doc_id = (int) $id;
		} else {
			$record['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $record );
			$doc_id = (int) $wpdb->insert_id;
		}

		// Enqueue async chunking and embedding
		if ( ! empty( $data['content'] ) ) {
			TC_Agents_Queue::push(
				'index_document',
				array(
					'document_id' => $doc_id,
					'content'     => $data['content'],
				)
			);
		}

		return $doc_id;
	}

	/**
	 * Split and store chunks and vectors for a document.
	 *
	 * @param int    $document_id
	 * @param string $content
	 */
	public static function index_document_chunks( $document_id, $content ) {
		$document_id = (int) $document_id;
		if ( empty( $document_id ) || empty( $content ) ) {
			return;
		}

		// Clear previous chunks
		TC_Agent_Vector_Store::delete_document_chunks( $document_id );

		// Split into overlapping semantic chunks
		$chunker = new TC_Agent_Chunker( 1100, 150 );
		$pieces  = $chunker->split( $content );

		foreach ( $pieces as $idx => $piece ) {
			$vector = array();
			if ( '1' === (string) get_option( 'tc_agents_enable_vector_search', '1' ) ) {
				$vector = TC_Agent_Vector_Store::generate_embedding( $piece );
			}
			TC_Agent_Vector_Store::store_chunk( $document_id, $idx, $piece, $vector );
		}
	}

	/**
	 * Delete a knowledge document and all its indexed chunks.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public static function delete_document( $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'tc_agent_knowledge';
		$doc_id = (int) $id;

		TC_Agent_Vector_Store::delete_document_chunks( $doc_id );
		return $wpdb->delete( $table, array( 'id' => $doc_id ) );
	}

	/**
	 * 1-Click catalog sync: import published treks, packages, and pages into Knowledge Base.
	 *
	 * @return int Number of indexed items.
	 */
	public static function sync_wordpress_catalog() {
		// Detect post types: custom tour/trek types, products, posts, pages
		$post_types = array( 'post', 'page', 'product', 'trip', 'trek', 'tour', 'package' );
		$available  = array();
		foreach ( $post_types as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$available[] = $pt;
			}
		}

		$query_args = array(
			'post_type'      => ! empty( $available ) ? $available : array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		);

		$posts = get_posts( $query_args );
		$count = 0;

		foreach ( $posts as $p ) {
			$title   = get_the_title( $p );
			$content = trim( wp_strip_all_tags( $p->post_content ) );
			if ( mb_strlen( $content ) < 80 ) {
				continue;
			}

			// Add category tag based on post type
			$category = in_array( $p->post_type, array( 'product', 'trip', 'trek', 'tour' ), true ) ? 'trek_catalog' : 'site_content';

			// Format structured metadata if available
			$meta_info = '';
			$duration  = get_post_meta( $p->ID, 'duration', true ) ?: get_post_meta( $p->ID, 'trip_duration', true );
			$altitude  = get_post_meta( $p->ID, 'altitude', true ) ?: get_post_meta( $p->ID, 'max_altitude', true );
			$difficulty = get_post_meta( $p->ID, 'difficulty', true ) ?: get_post_meta( $p->ID, 'trek_difficulty', true );

			if ( $duration || $altitude || $difficulty ) {
				$meta_info = "\n[Trek Details: Duration: " . ( $duration ?: 'N/A' ) . " | Altitude: " . ( $altitude ?: 'N/A' ) . " | Difficulty: " . ( $difficulty ?: 'Moderate' ) . "]\n";
			}

			$full_text = $title . "\n" . $meta_info . "\n" . $content;

			self::save_document(
				array(
					'title'     => $title,
					'category'  => $category,
					'content'   => $full_text,
					'tags'      => $p->post_type,
					'is_active' => 1,
				)
			);

			$count++;
		}

		return $count;
	}
}
