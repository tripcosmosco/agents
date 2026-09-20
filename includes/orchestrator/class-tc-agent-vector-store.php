<?php
/**
 * Semantic Vector Store & Hybrid Search Engine for TripCosmos Agents.
 *
 * Stores float32 packed vector embeddings in MySQL and provides
 * cosine similarity ranking with full-text fallback.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Vector_Store {

	/**
	 * Pack float array into binary float32 blob.
	 *
	 * @param array<int, float> $vector
	 * @return string
	 */
	public static function pack( $vector ) {
		if ( empty( $vector ) || ! is_array( $vector ) ) {
			return '';
		}
		return pack( 'f*', ...array_map( 'floatval', $vector ) );
	}

	/**
	 * Unpack binary float32 blob into float array.
	 *
	 * @param string $blob
	 * @return array<int, float>
	 */
	public static function unpack( $blob ) {
		if ( empty( $blob ) ) {
			return array();
		}
		$values = unpack( 'f*', $blob );
		return false === $values ? array() : array_values( $values );
	}

	/**
	 * Compute cosine similarity between two float vectors.
	 *
	 * @param array<int, float> $a
	 * @param array<int, float> $b
	 * @return float Value between -1.0 and 1.0 (typically 0.0 to 1.0)
	 */
	public static function cosine( $a, $b ) {
		$length = min( count( $a ), count( $b ) );
		if ( 0 === $length ) {
			return 0.0;
		}

		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;

		for ( $i = 0; $i < $length; $i++ ) {
			$dot += $a[ $i ] * $b[ $i ];
			$na  += $a[ $i ] ** 2;
			$nb  += $b[ $i ] ** 2;
		}

		$denom = sqrt( $na ) * sqrt( $nb );
		return $denom > 0.0 ? ( $dot / $denom ) : 0.0;
	}

	/**
	 * Store a chunk and its optional embedding in the database.
	 *
	 * @param int    $document_id
	 * @param int    $index
	 * @param string $content
	 * @param array  $vector
	 * @return int Inserted row ID.
	 */
	public static function store_chunk( $document_id, $index, $content, $vector = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_kb_chunks';

		$packed = ! empty( $vector ) ? self::pack( $vector ) : null;
		$dims   = ! empty( $vector ) ? count( $vector ) : 0;
		$tokens = TC_Agent_Chunker::estimate_tokens( $content );

		$wpdb->insert(
			$table,
			array(
				'document_id' => (int) $document_id,
				'chunk_index' => (int) $index,
				'content'     => $content,
				'embedding'   => $packed,
				'dimensions'  => $dims,
				'token_count' => $tokens,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all chunks belonging to a specific document.
	 *
	 * @param int $document_id
	 */
	public static function delete_document_chunks( $document_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_kb_chunks';
		$wpdb->delete( $table, array( 'document_id' => (int) $document_id ), array( '%d' ) );
	}

	/**
	 * Generate an embedding vector via OpenAI or OpenRouter embeddings endpoint.
	 *
	 * @param string $text
	 * @return array<int, float>
	 */
	public static function generate_embedding( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}

		// Check OpenRouter, Gateway, or OpenAI API key from Vault or Options
		$openrouter_key = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'openrouter_api_key', '' ) : get_option( 'tc_agents_openrouter_api_key', '' );
		if ( empty( $openrouter_key ) ) {
			$openrouter_key = get_option( 'tc_agents_openrouter_api_key', '' );
		}

		$gateway_key = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'gateway_api_key', '' ) : get_option( 'tc_agents_gateway_api_key', '' );
		$api_key     = ! empty( $openrouter_key ) ? $openrouter_key : ( ! empty( $gateway_key ) ? $gateway_key : get_option( 'tc_agents_aipuffer_api_key', '' ) );

		if ( empty( $api_key ) ) {
			return array();
		}

		$is_openrouter = ! empty( $openrouter_key );
		$endpoint      = $is_openrouter ? 'https://openrouter.ai/api/v1/embeddings' : 'https://api.openai.com/v1/embeddings';
		$model         = get_option( 'tc_agents_embed_model', 'text-embedding-3-small' );

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		);

		if ( $is_openrouter ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = 'TripCosmos Agents';
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'model' => $model,
						'input' => mb_substr( $text, 0, 8000 ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['data'][0]['embedding'] ) ) {
			return array();
		}

		return array_map( 'floatval', $data['data'][0]['embedding'] );
	}

	/**
	 * Perform semantic cosine search across stored chunk vectors.
	 *
	 * @param array<int, float> $query_vector
	 * @param int               $limit
	 * @param float             $min_score
	 * @return array<int, array>
	 */
	public static function search_semantic( $query_vector, $limit = 4, $min_score = 0.45 ) {
		if ( empty( $query_vector ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_kb_chunks';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, document_id, chunk_index, content, embedding FROM $table WHERE dimensions > 0 LIMIT %d",
				1500
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$scored = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['embedding'] ) ) {
				continue;
			}
			$vec   = self::unpack( $row['embedding'] );
			$score = self::cosine( $query_vector, $vec );
			if ( $score >= $min_score ) {
				$row['score'] = round( $score, 4 );
				unset( $row['embedding'] );
				$scored[] = $row;
			}
		}

		usort(
			$scored,
			function ( $x, $y ) {
				return $y['score'] <=> $x['score'];
			}
		);

		return array_slice( $scored, 0, (int) $limit );
	}

	/**
	 * Full-text keyword search fallback.
	 *
	 * @param string $keyword
	 * @param int    $limit
	 * @return array<int, array>
	 */
	public static function search_fulltext( $keyword, $limit = 4 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_kb_chunks';

		$keyword = trim( sanitize_text_field( $keyword ) );
		if ( '' === $keyword ) {
			return array();
		}

		// Try MySQL FULLTEXT match first
		$query = $wpdb->prepare(
			"SELECT id, document_id, chunk_index, content, MATCH(content) AGAINST (%s IN NATURAL LANGUAGE MODE) AS score
			 FROM $table
			 WHERE MATCH(content) AGAINST (%s IN NATURAL LANGUAGE MODE)
			 ORDER BY score DESC
			 LIMIT %d",
			$keyword,
			$keyword,
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Fallback to LIKE if FULLTEXT has no matches (e.g. short words)
		if ( empty( $results ) ) {
			$search_term = '%' . $wpdb->esc_like( $keyword ) . '%';
			$results     = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, document_id, chunk_index, content, 0.50 as score
					 FROM $table
					 WHERE content LIKE %s
					 ORDER BY id DESC
					 LIMIT %d",
					$search_term,
					$limit
				),
				ARRAY_A
			);
		}

		return $results ?: array();
	}
}
