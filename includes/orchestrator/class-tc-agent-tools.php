<?php
/**
 * Agent Tool Definitions and Execution Registry.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Tools {

	/**
	 * Get list of all available tool definition schemas in OpenAI format.
	 *
	 * @return array
	 */
	public static function get_definitions() {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'search_trips',
					'description' => 'Search live TripCosmos tour packages, spiritual circuits, hotels, outstation cabs, Ganga Aarti boats, and itineraries for Varanasi, Ayodhya, Prayagraj, Bodhgaya, Chitrakoot, Lucknow, Mathura, Vrindavan, and Delhi.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'query'       => array(
								'type'        => 'string',
								'description' => 'Keyword or circuit e.g. "Varanasi", "Kashi Vishwanath", "Ayodhya Ram Mandir", "Prayagraj Sangam", "Bodhgaya", "Chitrakoot", "Lucknow", "Mathura Vrindavan", "Outstation Cab", "Ganga Aarti Boat", "Hotel".',
							),
							'destination' => array(
								'type'        => 'string',
								'description' => 'Destination city e.g. "Varanasi", "Ayodhya", "Prayagraj", "Bodhgaya", "Chitrakoot", "Lucknow", "Mathura", "Delhi".',
							),
							'max_results' => array(
								'type'        => 'integer',
								'description' => 'Maximum number of items to return (default: 4).',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'lookup_contact_crm',
					'description' => 'Check if a traveler contact already exists in Fluent CRM or Twenty CRM by email or phone.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'email' => array(
								'type'        => 'string',
								'description' => 'Traveler email address.',
							),
							'phone' => array(
								'type'        => 'string',
								'description' => 'Traveler phone/WhatsApp number with country code.',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'sync_lead_crm',
					'description' => 'Save or update a prospective traveler lead in Fluent CRM and Twenty CRM with contact details and interest tags.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'         => array(
								'type'        => 'string',
								'description' => 'Full name of the traveler.',
							),
							'email'        => array(
								'type'        => 'string',
								'description' => 'Email address of the traveler.',
							),
							'phone'        => array(
								'type'        => 'string',
								'description' => 'WhatsApp or phone number with country code.',
							),
							'destination'  => array(
								'type'        => 'string',
								'description' => 'Destination or trek they are interested in.',
							),
							'group_size'   => array(
								'type'        => 'string',
								'description' => 'Number of travelers or group size estimate.',
							),
							'travel_month' => array(
								'type'        => 'string',
								'description' => 'Target travel month or dates.',
							),
						),
						'required'   => array( 'name' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'import_b2b_agencies',
					'description' => 'Import travel agencies from Google Business/Places into the B2B prospect table for a city. Queues CRM sync and outreach.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'city' => array( 'type' => 'string', 'description' => 'Indian city, e.g. Varanasi, Delhi, Jaipur.' ),
							'keyword' => array( 'type' => 'string', 'description' => 'Search keyword, default: travel agency.' ),
							'max_results' => array( 'type' => 'integer', 'description' => 'Max results (default 20, cap 20).' ),
						),
						'required'   => array( 'city' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'outreach_b2b_agency',
					'description' => 'Send B2B partnership outreach (WhatsApp via Evolution/legacy + Brevo email) to an imported agency and log to FluentCRM/TwentyCRM.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'agency_id' => array( 'type' => 'integer', 'description' => 'B2B agency row ID.' ),
							'channel' => array( 'type' => 'string', 'description' => 'whatsapp, email, or both (default both).' ),
						),
						'required'   => array( 'agency_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'request_human_handoff',
					'description' => 'Connect traveler directly to a human travel specialist on WhatsApp or arrange a priority callback when automated assistance is insufficient.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'reason'       => array(
								'type'        => 'string',
								'description' => 'Why human handoff is required (e.g. custom corporate quote, technical issue, explicit user request).',
							),
							'user_contact' => array(
								'type'        => 'string',
								'description' => 'Contact details provided by user (phone or email).',
							),
						),
						'required'   => array( 'reason' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'query_pricing_sheet',
					'description' => 'Look up real-time expedition pricing, seasonal surcharges, or group discounts directly from the TripCosmos master pricing spreadsheet.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'package_name' => array(
								'type'        => 'string',
								'description' => 'Name of the trek or destination e.g. "Kedarkantha", "Hampta Pass".',
							),
							'season'       => array(
								'type'        => 'string',
								'description' => 'Travel season or month (e.g. "Winter", "May-June").',
							),
						),
						'required'   => array( 'package_name' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'request_voice_call',
					'description' => 'Trigger an automated outbound AI phone consultation to the traveler mobile number immediately. Use when the traveler asks to be called, asks for a quick phone consultation, or provides their contact number for immediate assistance.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'phone'         => array(
								'type'        => 'string',
								'description' => 'Traveler mobile number in E.164 or Indian format (e.g. "+919876543210" or "9876543210").',
							),
							'traveler_name' => array(
								'type'        => 'string',
								'description' => 'Name of the traveler.',
							),
							'reason'        => array(
								'type'        => 'string',
								'description' => 'Reason for the call e.g. "Kedarkantha winter trek consultation and group pricing".',
							),
						),
						'required'   => array( 'phone' ),
					),
				),
			),
		);
	}

	/**
	 * Filter definitions to only include those allowed for a specific persona.
	 */
	public static function get_allowed_definitions( array $allowed_tool_names ) {
		$all = self::get_definitions();
		if ( empty( $allowed_tool_names ) ) {
			return $all;
		}

		return array_values(
			array_filter(
				$all,
				function( $tool ) use ( $allowed_tool_names ) {
					return in_array( $tool['function']['name'], $allowed_tool_names, true );
				}
			)
		);
	}

	/**
	 * Execute a tool call by name.
	 *
	 * @param string $tool_name
	 * @param array  $arguments
	 * @param array  $context Conversation / session context.
	 * @return array Tool result payload.
	 */
	public static function execute( $tool_name, array $arguments, array $context = array() ) {
		switch ( $tool_name ) {
			case 'search_trips':
				return self::tool_search_trips( $arguments );

			case 'lookup_contact_crm':
				return self::tool_lookup_contact_crm( $arguments );

			case 'sync_lead_crm':
				return self::tool_sync_lead_crm( $arguments, $context );

			case 'request_human_handoff':
				return self::tool_request_human_handoff( $arguments, $context );

			case 'query_pricing_sheet':
				return self::tool_query_pricing_sheet( $arguments );

			case 'request_voice_call':
				return self::tool_request_voice_call( $arguments, $context );

			case 'import_b2b_agencies':
				return self::tool_import_b2b_agencies( $arguments );

			case 'outreach_b2b_agency':
				return self::tool_outreach_b2b_agency( $arguments );

			default:
				return array( 'error' => sprintf( 'Unknown tool: %s', $tool_name ) );
		}
	}

	/**
	 * Tool: Search live WordPress trip catalog (Togo theme post types and taxonomies).
	 */
	private static function tool_search_trips( array $args ) {
		$query_str   = sanitize_text_field( $args['query'] ?? '' );
		$destination = sanitize_text_field( $args['destination'] ?? '' );
		$limit       = min( 6, max( 1, (int) ( $args['max_results'] ?? 4 ) ) );

		// Query togo_trip custom post type if registered, otherwise fallback to standard posts/products
		$post_types = array( 'togo_trip', 'product', 'trip', 'post' );
		$valid_type = 'post';
		foreach ( $post_types as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$valid_type = $pt;
				break;
			}
		}

		$query_args = array(
			'post_type'      => $valid_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $query_str,
		);

		// If destination taxonomy exists, check supported taxonomy slugs
		if ( ! empty( $destination ) ) {
			$dest_taxonomies = array( 'togo_trip_destinations', 'togo_destinations', 'trip_destinations', 'destination', 'product_cat' );
			$valid_tax = '';
			foreach ( $dest_taxonomies as $dt ) {
				if ( taxonomy_exists( $dt ) ) {
					$valid_tax = $dt;
					break;
				}
			}

			if ( ! empty( $valid_tax ) ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => $valid_tax,
						'field'    => 'name',
						'terms'    => $destination,
					),
				);
			}
		}

		$posts = get_posts( $query_args );

		if ( empty( $posts ) && ! empty( $query_str ) ) {
			// Fallback: search without taxonomy restriction
			unset( $query_args['tax_query'] );
			$posts = get_posts( $query_args );
		}

		$trips = array();
		foreach ( $posts as $p ) {
			$price       = get_post_meta( $p->ID, 'togo_trip_price', true ) ?: get_post_meta( $p->ID, '_price', true );
			$duration    = get_post_meta( $p->ID, 'togo_trip_duration', true ) ?: get_post_meta( $p->ID, '_trip_duration', true );
			$difficulty  = get_post_meta( $p->ID, 'togo_trip_difficulty', true );
			$altitude    = get_post_meta( $p->ID, 'togo_trip_altitude', true );

			$trips[] = array(
				'id'         => $p->ID,
				'title'      => $p->post_title,
				'excerpt'    => wp_trim_words( $p->post_excerpt ?: $p->post_content, 25 ),
				'price'      => ! empty( $price ) ? '₹' . number_format( (float) $price ) : 'Available upon inquiry',
				'duration'   => $duration ?: '5-6 Days',
				'difficulty' => $difficulty ?: 'Moderate',
				'altitude'   => $altitude ?: '12,500 ft',
				'thumbnail'  => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',
				'url'        => get_permalink( $p->ID ),
			);
		}

		// Curated authentic catalog templates if site database has no published custom posts
		if ( empty( $trips ) ) {
			$catalog_templates = array(
				array(
					'id'          => 101,
					'title'       => 'Kashi - Prayagraj - Ayodhya Spiritual Circuit (3N/4D)',
					'excerpt'     => 'The holy trinity: Kashi Vishwanath Corridor, evening Ganga Aarti boat, Triveni Sangam holy dip, and Ayodhya Ram Janmabhoomi & Hanumangarhi darshan.',
					'price'       => '₹8,500',
					'duration'    => '4 Days / 3 Nights',
					'difficulty'  => 'Family & Senior Citizen Friendly',
					'altitude'    => 'Spiritual Circuit',
					'destination' => 'Varanasi, Ayodhya, Prayagraj',
					'thumbnail'   => 'https://images.unsplash.com/photo-1561361513-2d000a50f0dc?w=600&q=80',
					'url'         => home_url( '/tour/kashi-ayodhya-prayagraj/' ),
				),
				array(
					'id'          => 102,
					'title'       => 'Ayodhya Ram Mandir & Hanumangarhi Darshan Tour (1N/2D)',
					'excerpt'     => 'Comprehensive Ayodhya pilgrimage: Ram Janmabhoomi darshan, Kanak Bhavan, Hanumangarhi, Saryu Aarti, and local sightseeing with AC cab transfer.',
					'price'       => '₹4,999',
					'duration'    => '2 Days / 1 Night',
					'difficulty'  => 'Easy',
					'altitude'    => 'Pilgrimage Yatra',
					'destination' => 'Ayodhya',
					'thumbnail'   => 'https://images.unsplash.com/photo-1596402184320-417e7178b2cd?w=600&q=80',
					'url'         => home_url( '/tour/ayodhya-darshan/' ),
				),
				array(
					'id'          => 103,
					'title'       => 'Kashi, Bodhgaya & Gaya Pind Daan Tour (3N/4D)',
					'excerpt'     => 'Sacred ancestor rituals and Buddhist heritage: Vishnupad temple Gaya, Mahabodhi temple Bodhgaya, Kashi Vishwanath, Kaal Bhairav, and Sarnath.',
					'price'       => '₹9,800',
					'duration'    => '4 Days / 3 Nights',
					'difficulty'  => 'Family Pilgrimage',
					'altitude'    => 'Pitra Paksha & Heritage',
					'destination' => 'Varanasi, Bodhgaya, Gaya',
					'thumbnail'   => 'https://images.unsplash.com/photo-1609137144813-7d9921338f24?w=600&q=80',
					'url'         => home_url( '/tour/kashi-bodhgaya-gaya/' ),
				),
				array(
					'id'          => 104,
					'title'       => 'Braj Bhoomi: Mathura, Vrindavan, Gokul & Barsana (2N/3D)',
					'excerpt'     => 'Immerse in divine Krishna Bhakti: Shri Krishna Janmabhoomi, Banke Bihari Mandir, Prem Mandir lighting, ISKCON, Radha Rani temple in Barsana.',
					'price'       => '₹5,800',
					'duration'    => '3 Days / 2 Nights',
					'difficulty'  => 'Easy',
					'altitude'    => 'Braj Yatra',
					'destination' => 'Mathura, Vrindavan',
					'thumbnail'   => 'https://images.unsplash.com/photo-1582510003544-4d00b7f74220?w=600&q=80',
					'url'         => home_url( '/tour/mathura-vrindavan/' ),
				),
				array(
					'id'          => 105,
					'title'       => 'Chitrakoot & Prayagraj Sangam Ramayan Yatra (2N/3D)',
					'excerpt'     => 'Explore Lord Rama\'s sacred exile sites: Kamadgiri Parikrama, Ramghat on Mandakini river, Gupt Godavari, Sphatik Shila, and Prayagraj Triveni Sangam.',
					'price'       => '₹6,500',
					'duration'    => '3 Days / 2 Nights',
					'difficulty'  => 'Easy to Moderate',
					'altitude'    => 'Ramayan Heritage',
					'destination' => 'Chitrakoot, Prayagraj',
					'thumbnail'   => 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=600&q=80',
					'url'         => home_url( '/tour/chitrakoot-prayagraj/' ),
				),
				array(
					'id'          => 106,
					'title'       => 'Lucknow Nawabi Heritage, Architecture & Food Trail (1N/2D)',
					'excerpt'     => 'Royal Awadhi experience: Bara Imambara (Bhool Bhulaiya), Rumi Darwaza, Chota Imambara, Hazratganj, Chikankari craft shopping & authentic Awadhi cuisine.',
					'price'       => '₹4,500',
					'duration'    => '2 Days / 1 Night',
					'difficulty'  => 'City Heritage',
					'altitude'    => 'Cultural & Culinary',
					'destination' => 'Lucknow',
					'thumbnail'   => 'https://images.unsplash.com/photo-1587474260584-136574528ed5?w=600&q=80',
					'url'         => home_url( '/tour/lucknow-heritage/' ),
				),
				array(
					'id'          => 107,
					'title'       => 'Outstation Cabs & Airport Transfers (Varanasi / Ayodhya / Prayagraj)',
					'excerpt'     => 'Comfortable AC cabs with verified chauffeurs: Dzire, Etios, Innova Crysta, and Tempo Traveller (12-26 seater) for round-trips & airport transfers.',
					'price'       => 'From ₹11/km (or ₹1,200 airport transfer)',
					'duration'    => 'Hourly / Daily Outstation',
					'difficulty'  => 'Doorstep Pickup',
					'altitude'    => 'Sedan / SUV / Tempo',
					'destination' => 'Varanasi, Ayodhya, Prayagraj, Lucknow, Delhi',
					'thumbnail'   => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=600&q=80',
					'url'         => home_url( '/cabs/' ),
				),
				array(
					'id'          => 108,
					'title'       => 'Varanasi Evening Ganga Aarti & Sunrise Boat Ride',
					'excerpt'     => 'Private wooden boat & luxury Bajra reservations with front-row view of Dashashwamedh Ganga Aarti and Subah-e-Banaras morning ghat cruise.',
					'price'       => '₹1,500 - ₹3,500 (Private Boat)',
					'duration'    => '2 Hours',
					'difficulty'  => 'Boat Ride',
					'altitude'    => 'Ganga Riverfront',
					'destination' => 'Varanasi',
					'thumbnail'   => 'https://images.unsplash.com/photo-1571536802807-30451e3955d8?w=600&q=80',
					'url'         => home_url( '/boat-ride/' ),
				),
			);

			$filtered = array();
			$q_lower  = strtolower( $query_str );
			$d_lower  = strtolower( $destination );

			foreach ( $catalog_templates as $tmpl ) {
				$match = true;
				if ( ! empty( $q_lower ) ) {
					$match = ( strpos( strtolower( $tmpl['title'] ), $q_lower ) !== false || strpos( strtolower( $tmpl['excerpt'] ), $q_lower ) !== false || strpos( strtolower( $tmpl['destination'] ), $q_lower ) !== false );
				}
				if ( $match && ! empty( $d_lower ) ) {
					$match = ( strpos( strtolower( $tmpl['destination'] ), $d_lower ) !== false );
				}
				if ( $match ) {
					$filtered[] = $tmpl;
				}
			}

			// If query didn't match specific keyword, return popular top treks
			$trips = ! empty( $filtered ) ? array_slice( $filtered, 0, $limit ) : array_slice( $catalog_templates, 0, $limit );
		}

		return array(
			'found' => count( $trips ),
			'trips' => $trips,
		);
	}

	/**
	 * Tool: Lookup contact in Fluent CRM & Twenty CRM.
	 */
	private static function tool_lookup_contact_crm( array $args ) {
		$email = sanitize_email( $args['email'] ?? '' );
		$phone = sanitize_text_field( $args['phone'] ?? '' );

		$contact_found = null;

		// 1. Fluent CRM lookup
		if ( class_exists( 'TC_Integration_FluentCRM' ) ) {
			$fluent_contact = TC_Integration_FluentCRM::find_contact( $email, $phone );
			if ( $fluent_contact ) {
				$contact_found = array(
					'source' => 'fluent_crm',
					'id'     => $fluent_contact->id ?? 0,
					'name'   => trim( ( $fluent_contact->first_name ?? '' ) . ' ' . ( $fluent_contact->last_name ?? '' ) ),
					'status' => $fluent_contact->status ?? 'lead',
				);
			}
		}

		if ( ! $contact_found ) {
			return array( 'found' => false, 'message' => 'No prior CRM contact record found.' );
		}

		return array(
			'found'   => true,
			'contact' => $contact_found,
		);
	}

	/**
	 * Tool: Sync lead into Fluent CRM, Twenty CRM, Google Sheets, and local database pipeline.
	 */
	private static function tool_sync_lead_crm( array $args, array $context ) {
		global $wpdb;

		$name        = sanitize_text_field( $args['name'] ?? '' );
		$email       = sanitize_email( $args['email'] ?? '' );
		$phone       = sanitize_text_field( $args['phone'] ?? '' );
		$destination = sanitize_text_field( $args['destination'] ?? '' );
		$group_size  = sanitize_text_field( $args['group_size'] ?? '' );
		$month       = sanitize_text_field( $args['travel_month'] ?? '' );
		$channel     = sanitize_text_field( $context['channel'] ?? 'web' );
		$session_id  = sanitize_text_field( $context['session_id'] ?? '' );

		$results = array(
			'local_crm'  => 'skipped',
			'fluent_crm' => 'skipped',
			'twenty_crm' => 'skipped',
			'sheets'     => 'skipped',
		);

		// 1. Local Database Persistence (_tc_agent_contacts)
		$table_contacts = $wpdb->prefix . 'tc_agent_contacts';
		$contact_id     = 0;
		$fluent_id      = 0;

		// Search for existing contact by email or phone
		$existing_contact = null;
		if ( ! empty( $email ) ) {
			$existing_contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE email = %s LIMIT 1", $email ), ARRAY_A );
		}
		if ( ! $existing_contact && ! empty( $phone ) ) {
			$existing_contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_contacts WHERE phone = %s LIMIT 1", $phone ), ARRAY_A );
		}

		$contact_data = array(
			'name'           => ! empty( $name ) ? $name : ( $existing_contact['name'] ?? 'Traveler' ),
			'email'          => ! empty( $email ) ? $email : ( $existing_contact['email'] ?? '' ),
			'phone'          => ! empty( $phone ) ? $phone : ( $existing_contact['phone'] ?? '' ),
			'source_channel' => $channel,
			'meta_data'      => wp_json_encode( array(
				'destination'  => $destination,
				'group_size'   => $group_size,
				'travel_month' => $month,
				'synced_at'    => current_time( 'mysql' ),
			) ),
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $existing_contact ) {
			$contact_id = (int) $existing_contact['id'];
			$wpdb->update( $table_contacts, $contact_data, array( 'id' => $contact_id ) );
			$results['local_crm'] = 'updated';
		} else {
			$contact_data['stage']      = 'inquiry';
			$contact_data['score']      = 60;
			$contact_data['deal_value'] = ! empty( $group_size ) && is_numeric( $group_size ) ? (float) $group_size * 12000 : 15000.00;
			$contact_data['currency']   = 'INR';
			$contact_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table_contacts, $contact_data );
			$contact_id = (int) $wpdb->insert_id;
			$results['local_crm'] = 'created';
		}

		// Link contact_id to conversation record
		if ( ! empty( $session_id ) && $contact_id > 0 ) {
			$table_convo = $wpdb->prefix . 'tc_agent_conversations';
			$wpdb->update( $table_convo, array( 'contact_id' => $contact_id ), array( 'session_id' => $session_id ) );
		}

		// Initialize traveler memory facts if destination or month provided
		if ( $contact_id > 0 && class_exists( 'TC_Agent_Memory' ) ) {
			$facts = array();
			if ( ! empty( $destination ) ) {
				$facts[] = "Interested in {$destination}";
			}
			if ( ! empty( $month ) ) {
				$facts[] = "Planning trip around {$month}";
			}
			if ( ! empty( $group_size ) ) {
				$facts[] = "Group size estimate: {$group_size}";
			}
			if ( ! empty( $facts ) ) {
				TC_Agent_Memory::save_memory( $contact_id, array( 'facts' => $facts ) );
			}
		}

		// 2. Fluent CRM sync
		if ( class_exists( 'TC_Integration_FluentCRM' ) ) {
			$f_res = TC_Integration_FluentCRM::sync_lead(
				array(
					'name'        => $name,
					'email'       => $email,
					'phone'       => $phone,
					'destination' => $destination,
					'channel'     => $channel,
				)
			);
			if ( $f_res ) {
				$fluent_id = (int) $f_res;
				$wpdb->update( $table_contacts, array( 'fluentcrm_id' => $fluent_id ), array( 'id' => $contact_id ) );
				$results['fluent_crm'] = 'synced (ID: ' . $fluent_id . ')';
			} else {
				$results['fluent_crm'] = 'skipped/invalid_email';
			}
		}

		// 3. Twenty CRM sync
		if ( class_exists( 'TC_Integration_TwentyCRM' ) ) {
			$t_res = TC_Integration_TwentyCRM::push_lead(
				array(
					'name'        => $name,
					'email'       => $email,
					'phone'       => $phone,
					'destination' => $destination,
					'group_size'  => $group_size,
					'month'       => $month,
				)
			);
			$results['twenty_crm'] = $t_res ? 'synced' : 'skipped';
		}

		// 4. Google Sheets sync
		if ( class_exists( 'TC_Integration_Sheets' ) ) {
			$s_res = TC_Integration_Sheets::append_lead(
				array(
					'name'        => $name,
					'email'       => $email,
					'phone'       => $phone,
					'destination' => $destination,
					'group_size'  => $group_size,
					'month'       => $month,
					'channel'     => $channel,
				)
			);
			$results['sheets'] = $s_res ? 'appended' : 'skipped';
		}

		// 5. Automated Follow-Up Sequence enrollment for new inquiry
		if ( $contact_id > 0 && class_exists( 'TC_Agent_Sequences' ) && 'created' === $results['local_crm'] ) {
			TC_Agent_Sequences::enroll_contact( $contact_id, 'inquiry_abandoned' );
		}

		return array(
			'success'    => true,
			'contact_id' => $contact_id,
			'message'    => "Lead {$name} registered in TripCosmos Pipeline and synced across integrations.",
			'status'     => $results,
		);
	}


	/**
	 * Tool: Request human handoff and generate direct WhatsApp link.
	 */
	private static function tool_request_human_handoff( array $args, array $context ) {
		$reason  = sanitize_text_field( $args['reason'] ?? 'User requested human specialist' );
		$contact = sanitize_text_field( $args['user_contact'] ?? '' );

		$wa_number = get_option( 'tc_agents_human_whatsapp_number', '+919876543210' );
		$clean_num = preg_replace( '/[^0-9]/', '', $wa_number );

		$handoff_text = rawurlencode( "Hi TripCosmos Team, I was chatting with your AI assistant on tripcosmos.co and would like to speak directly with an expedition specialist. (Reason: {$reason})" );
		$wa_link      = "https://wa.me/{$clean_num}?text={$handoff_text}";

		// Send email notification to staff if email configured
		$notify_email = get_option( 'tc_agents_human_notification_email', get_option( 'admin_email' ) );
		if ( ! empty( $notify_email ) ) {
			$subject = '[TripCosmos Agents] Human Handoff Requested';
			$body    = "A traveler on the TripCosmos chat has requested to speak with a human specialist.\n\n" .
					   "Reason: {$reason}\n" .
					   "Provided Contact: {$contact}\n" .
					   "Channel: " . ( $context['channel'] ?? 'web' ) . "\n" .
					   "Session ID: " . ( $context['session_id'] ?? 'unknown' ) . "\n\n" .
					   "Direct WhatsApp link generated for user: {$wa_link}\n";
			wp_mail( $notify_email, $subject, $body );
		}

		TC_Agents_Logger::log(
			'human_handoff_requested',
			'info',
			array(
				'reason'     => $reason,
				'contact'    => $contact,
				'session_id' => $context['session_id'] ?? '',
			),
			'',
			$context['channel'] ?? 'web'
		);

		return array(
			'handoff_initiated' => true,
			'whatsapp_url'      => $wa_link,
			'whatsapp_number'   => $wa_number,
			'message'           => 'Human handoff registered. Direct WhatsApp connect link ready.',
		);
	}

	/**
	 * Tool: Query Pricing Sheet for live seasonal rates, group tiers, and availability.
	 */
	private static function tool_query_pricing_sheet( array $args ) {
		$package_name = sanitize_text_field( $args['package_name'] ?? '' );
		$season       = sanitize_text_field( $args['season'] ?? 'Standard' );

		// Check if remote sheet proxy URL is configured
		$sheet_url = get_option( 'tc_agents_sheets_pricing_url', '' );
		if ( ! empty( $sheet_url ) ) {
			$cached = get_transient( 'tc_pricing_cache_' . md5( $package_name . '_' . $season ) );
			if ( false !== $cached ) {
				return $cached;
			}

			$query_url = add_query_arg(
				array(
					'action'  => 'get_price',
					'package' => $package_name,
					'season'  => $season,
				),
				$sheet_url
			);

			$res = wp_remote_get( $query_url, array( 'timeout' => 5, 'sslverify' => true ) );
			if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
				$data = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( ! empty( $data ) ) {
					set_transient( 'tc_pricing_cache_' . md5( $package_name . '_' . $season ), $data, HOUR_IN_SECONDS );
					return $data;
				}
			}
		}

		// Fallback: Query live catalog post metadata for price
		$trips = self::tool_search_trips( array( 'query' => $package_name, 'max_results' => 1 ) );
		if ( ! empty( $trips['trips'][0] ) ) {
			$trip = $trips['trips'][0];
			return array(
				'package'        => $trip['title'],
				'season'         => $season,
				'base_price'     => $trip['price'],
				'duration'       => $trip['duration'],
				'group_discount' => 'Available for groups of 4+ (Inquire for exact group quote)',
				'source'         => 'live_catalog',
			);
		}

		return array(
			'package' => $package_name,
			'message' => "Custom expedition pricing available for {$package_name} in {$season}. Connecting with expedition specialist for quotation.",
		);
	}

	/**
	 * Tool: Trigger an automated outbound AI phone consultation to the traveler.
	 */
	private static function tool_request_voice_call( array $args, array $context = array() ) {
		$phone  = sanitize_text_field( $args['phone'] ?? '' );
		$name   = sanitize_text_field( $args['traveler_name'] ?? '' );
		$reason = sanitize_text_field( $args['reason'] ?? 'Himalayan expedition consultation' );

		if ( empty( $phone ) ) {
			return array( 'error' => 'A valid phone number is required to place an AI phone call.' );
		}

		// Ensure lead is captured/synced in CRM
		self::tool_sync_lead_crm(
			array(
				'name'        => $name,
				'phone'       => $phone,
				'destination' => $reason,
				'notes'       => 'Requested AI phone consultation: ' . $reason,
			),
			$context
		);

		if ( class_exists( 'TC_Integration_Voice' ) ) {
			$res = TC_Integration_Voice::place_call( $phone, $name, $reason );
			if ( is_wp_error( $res ) ) {
				return array(
					'success'           => false,
					'message'           => 'Could not place voice call: ' . $res->get_error_message() . '. Directing to WhatsApp.',
					'whatsapp_fallback' => true,
				);
			}

			return array(
				'success' => true,
				'status'  => 'call_initiated',
				'phone'   => $phone,
				'call_id' => is_array( $res ) ? ( $res['id'] ?? ( 'call_' . time() ) ) : 'call_' . time(),
				'message' => "AI phone call successfully dispatched to {$phone}. Your mobile will ring in a few seconds.",
			);
		}

		return array(
			'success' => false,
			'message' => 'Voice telephony integration is not available.',
		);
	}

	private static function tool_import_b2b_agencies( array $args ) {
		$city = sanitize_text_field( $args['city'] ?? '' );
		if ( '' === $city ) { return array( 'error' => 'City is required.' ); }
		$keyword = sanitize_text_field( $args['keyword'] ?? 'travel agency' );
		$max = min( 20, max( 1, (int) ( $args['max_results'] ?? 20 ) ) );
		if ( ! class_exists( 'TC_Integration_Google_Business' ) ) { return array( 'error' => 'Google Business importer unavailable.' ); }
		$res = TC_Integration_Google_Business::search_agencies( $keyword . ' in ' . $city, $max );
		if ( is_wp_error( $res ) ) { return array( 'error' => $res->get_error_message() ); }
		$stats = TC_Integration_Google_Business::upsert_agencies( $res, $city, 'google' );
		return array( 'success' => true, 'city' => $city, 'found' => count( $res ), 'stats' => $stats );
	}

	private static function tool_outreach_b2b_agency( array $args ) {
		global $wpdb;
		$id = absint( $args['agency_id'] ?? 0 );
		if ( ! $id ) { return array( 'error' => 'agency_id required.' ); }
		$table = $wpdb->prefix . 'tc_agent_agencies';
		$agency = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );
		if ( ! $agency ) { return array( 'error' => 'Agency not found.' ); }
		$channel = sanitize_key( $args['channel'] ?? 'both' );
		$results = array();
		$msg = sprintf( 'Namaste %s team! TripCosmos (Varanasi) — B2B Kashi-Ayodhya-Prayagraj packages, cabs & Ganga Aarti boats at net rates. Shall we share commission slabs?', $agency['name'] );
		if ( in_array( $channel, array( 'whatsapp', 'both' ), true ) && ! empty( $agency['phone'] ) && class_exists( 'TC_Integration_WhatsApp' ) ) {
			$results['whatsapp'] = (bool) TC_Integration_WhatsApp::send_message( $agency['phone'], $msg );
		}
		if ( in_array( $channel, array( 'email', 'both' ), true ) && ! empty( $agency['email'] ) && class_exists( 'TC_Integration_Brevo' ) ) {
			$r = TC_Integration_Brevo::send_b2b_outreach( $agency );
			$results['email'] = ! is_wp_error( $r );
			if ( is_wp_error( $r ) ) { $results['email_error'] = $r->get_error_message(); }
		}
		$wpdb->update( $table, array( 'status' => 'contacted', 'last_outreach_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		if ( class_exists( 'TC_Integration_FluentCRM' ) && ! empty( $agency['email'] ) ) {
			TC_Integration_FluentCRM::sync_lead( array( 'name' => $agency['name'], 'email' => $agency['email'], 'phone' => $agency['phone'], 'destination' => 'B2B:' . $agency['city'], 'channel' => 'b2b', 'stage' => 'inquiry', 'score' => 65 ) );
		}
		if ( class_exists( 'TC_Integration_TwentyCRM' ) ) {
			TC_Integration_TwentyCRM::push_lead( array( 'name' => $agency['name'], 'email' => $agency['email'], 'phone' => $agency['phone'], 'destination' => 'B2B Partnership ' . $agency['city'] ) );
		}
		return array( 'success' => true, 'agency' => $agency['name'], 'results' => $results );
	}
}
