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
	 * 1-Click catalog sync: import published tours, packages, and pages into Knowledge Base.
	 *
	 * @return int Number of indexed items.
	 */
	public static function sync_wordpress_catalog() {
		// Detect post types: custom tour/package types, products, posts, pages
		$post_types = array( 'post', 'page', 'product', 'trip', 'tour', 'package', 'togo_trip' );
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
			$category = in_array( $p->post_type, array( 'product', 'trip', 'tour', 'package', 'togo_trip' ), true ) ? 'tour_catalog' : 'site_content';

			// Format structured metadata if available
			$meta_info = '';
			$duration  = get_post_meta( $p->ID, 'duration', true ) ?: ( get_post_meta( $p->ID, 'trip_duration', true ) ?: get_post_meta( $p->ID, 'togo_trip_duration', true ) );
			$difficulty = get_post_meta( $p->ID, 'difficulty', true ) ?: get_post_meta( $p->ID, 'togo_trip_difficulty', true );

			if ( $duration || $difficulty ) {
				$meta_info = "\n[Tour Details: Duration: " . ( $duration ?: 'N/A' ) . " | Grade: " . ( $difficulty ?: 'Family Friendly' ) . "]\n";
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

	/**
	 * Seed initial authentic knowledge articles if KB is empty.
	 *
	 * @return int Number of seeded articles.
	 */
	public static function seed_default_knowledge() {
		global $wpdb;
		$table = $wpdb->prefix . 'tc_agent_knowledge';

		$articles = array(
			array(
				'title'    => 'Shri Kashi Vishwanath Corridor & Aarti Darshan Protocol',
				'category' => 'temple_protocols',
				'tags'     => 'kashi,vishwanath,aarti,darshan,varanasi',
				'content'  => "Shri Kashi Vishwanath Jyotirlinga Temple Corridor (Varanasi):\n" .
					"• Aarti Timings: Mangala Aarti (03:00 AM - 04:00 AM), Bhog Aarti (11:15 AM - 12:20 PM), Saptarishi Aarti (07:00 PM - 08:30 PM), Shringar / Night Bhog Aarti (09:00 PM - 10:15 PM), Shayan Aarti (10:30 PM - 11:00 PM).\n" .
					"• Sugam VIP Darshan: Fast-track entry protocol available through Gate 4 (Chhattadwar / Godowlia side). Includes direct entry bypass to sanctum sanctorum.\n" .
					"• Dress Code for Sparsh Darshan (touching the Shiva Lingam): Men must wear Dhoti and Kurta (unstitched traditional lower preferred); Women must wear Saree. Western clothing, jeans, belts, leather footwear are prohibited in the inner sanctum.\n" .
					"• Electronic Items: Mobile phones, smartwatches, cameras, and leather items must be deposited in digital lockers at the facilitation center at Gate 4 prior to security check.\n" .
					"• Senior Citizens & Wheelchair: Battery golf-cart shuttle is operational from Godowlia crossing to Gate 4. Wheelchair assistance and ramp access are available throughout the corridor plaza.",
			),
			array(
				'title'    => 'Dashashwamedh Ghat Evening Ganga Aarti & Boat Guide',
				'category' => 'aarti_and_boats',
				'tags'     => 'ganga_aarti,boat,bajra,dashashwamedh,varanasi',
				'content'  => "Varanasi Ganga Aarti & Boat Cruise Guidelines:\n" .
					"• Dashashwamedh Main Aarti: Grand ritual performed daily by 7 priests with brass lamps, conch shells, and incense. Starts around 6:30 PM - 7:00 PM (Winter: Nov-Feb) and 7:00 PM - 7:30 PM (Summer: Mar-Oct). Arrive 30 minutes prior.\n" .
					"• Boat Types & Views: Private Bajra Boat (holds 10-30 guests with rooftop seating, provides front-row river view without crowd push), Motor Boat (economical shared or private option), Traditional Hand-Rowed Shikara (peaceful 2-4 guest serene ride).\n" .
					"• Morning Subah-e-Banaras Boat: Sunrise boat departs between 5:15 AM and 5:45 AM from Assi Ghat or Dashashwamedh, cruising past Manikarnika Ghat, Harishchandra Ghat, and Panchganga Ghat.",
			),
			array(
				'title'    => 'TripCosmos Outstation Cab Tariffs & Fleet Guidelines',
				'category' => 'cab_tariffs',
				'tags'     => 'cab,fare,innova,dzire,tempo_traveller,varanasi,ayodhya,prayagraj',
				'content'  => "TripCosmos Verified Chauffeur Fleet & Transparent Tariff Rules:\n" .
					"• Swift Dzire / Sedan: ₹12/km, 1-3 passengers + 2 bags. Ideal for couples and small families.\n" .
					"• Toyota Innova Crysta (Luxury SUV): ₹19/km, 4-6 passengers + 4 large bags. Captain seats, superior highway comfort, dual-AC.\n" .
					"• Tempo Traveller (12/17/20 Seater): ₹26/km, 9-16 passengers + 12 bags. Ideal for joint family pilgrimages and group yatras.\n" .
					"• Circuit Distances: Varanasi to Ayodhya (220 km one-way, ~4.5 hrs via Purvanchal expressway), Varanasi to Prayagraj Sangam (125 km one-way, ~2.5 hrs), Varanasi to Bodhgaya (260 km one-way, ~5.5 hrs).\n" .
					"• Inclusions: 100% yellow-plate commercial sanitized cabs, verified courteous local drivers, fuel, toll taxes, parking, driver daily allowance (₹400-₹500/day). Zero hidden charges at destination.",
			),
			array(
				'title'    => 'Ayodhya Shri Ram Janmabhoomi Darshan & Local Protocol',
				'category' => 'temple_protocols',
				'tags'     => 'ayodhya,ram_mandir,hanuman_garhi,saryu,darshan',
				'content'  => "Ayodhya Ram Mandir Pilgrimage Guidelines:\n" .
					"• Shri Ram Janmabhoomi Mandir Darshan: Morning darshan 6:30 AM to 12:00 PM; Afternoon/Evening darshan 2:00 PM to 9:30 PM. Sugam VIP pass or general entry through designated queue corridors.\n" .
					"• Hanuman Garhi: Traditional pilgrimage practice mandates visiting Hanuman Garhi (76 steps) first before visiting Ram Lalla.\n" .
					"• Other Key Temples: Kanak Bhavan, Dashrath Mahal, and Sita Rasoi.\n" .
					"• Saryu River Evening Aarti: Performed daily at Ram Ki Paidi at 6:30 PM. Holy snan (bath) available at Naya Ghat and Guptar Ghat.\n" .
					"• Transfers: Varanasi Airport / City to Ayodhya round-trip day excursion takes ~4.5 hours each way by AC cab.",
			),
		);

		$seeded = 0;
		foreach ( $articles as $art ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE title = %s LIMIT 1", $art['title'] ) );
			if ( ! $exists ) {
				$doc_id = self::save_document(
					array(
						'title'     => $art['title'],
						'category'  => $art['category'],
						'content'   => $art['content'],
						'tags'      => $art['tags'],
						'is_active' => 1,
					)
				);
				// Immediately index chunks synchronously for fast availability
				self::index_document_chunks( $doc_id, $art['content'] );
				$seeded++;
			}
		}

		// Also sync available WordPress catalog
		$catalog_count = self::sync_wordpress_catalog();

		return $seeded + $catalog_count;
	}
}
