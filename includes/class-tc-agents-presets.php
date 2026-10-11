<?php
/**
 * Universal Industry Presets Engine.
 * Allows 1-click transformation of TripCosmos Agents into any industry.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Presets {

	/**
	 * Get all registered industry presets.
	 *
	 * @return array
	 */
	public static function get_presets() {
		return array(
			'travel' => array(
				'id'          => 'travel',
				'title'       => __( 'Travel & Tourism Desk', 'tripcosmos-agents' ),
				'icon'        => 'dashicons-airplane',
				'tagline'     => __( 'Pilgrimage packages, tour guides, cab tariffs, hotel & boat concierges.', 'tripcosmos-agents' ),
				'primary_color' => '#ea580c',
				'widget_title'  => 'Trip Specialist',
				'greeting'      => "Namaste! 🙏 Welcome! Looking for customized tour packages, VIP darshan passes, outstation cabs, or hotel bookings? How can I assist you today?",
				'personas'      => array(
					array(
						'slug'             => 'tripcosmos-guide',
						'name'             => 'Tour & Pilgrimage Specialist',
						'system_prompt'    => "You are the AI Tour Specialist for this travel agency.\nProvide customized tour packages, itineraries, hotel recommendations, and sightseeing guidance.\nBe warm, professional, respectful, and guide the customer toward booking a customized package.",
						'greeting_message' => "Namaste! 🙏 Welcome! Looking for tour packages, cabs, or hotel bookings? How can I assist you today?",
						'allowed_tools'    => array( 'calculate_cab_tariff', 'generate_itinerary_pdf', 'lookup_temple_protocol', 'search_trips' ),
					),
					array(
						'slug'             => 'tripcosmos-concierge',
						'name'             => 'Cabs, Hotels & Transport Concierge',
						'system_prompt'    => "You are the Cabs & Transport Concierge. Assist travelers needing outstation cabs (Innova, Dzire, Tempo Traveller), airport pickup/drop, and hotel bookings.",
						'greeting_message' => "Hello! Need a reliable outstation cab, airport transfer, or hotel booking? Let me know your pickup city and travel date!",
						'allowed_tools'    => array( 'calculate_cab_tariff', 'generate_itinerary_pdf' ),
					),
					array(
						'slug'             => 'tripcosmos-b2b',
						'name'             => 'B2B Agency Partner Hunter',
						'system_prompt'    => "You recruit travel agencies as B2B partners for white-label destination packages and cab fleet access.",
						'greeting_message' => "B2B Desk active. Give me a city and I will assist with agency partnership outreach!",
						'allowed_tools'    => array(),
					),
				),
			),

			'real_estate' => array(
				'id'          => 'real_estate',
				'title'       => __( 'Real Estate & Properties', 'tripcosmos-agents' ),
				'icon'        => 'dashicons-building',
				'tagline'     => __( 'Property listings, luxury villas, commercial spaces, and site visit booking.', 'tripcosmos-agents' ),
				'primary_color' => '#0284c7',
				'widget_title'  => 'Property Consultant',
				'greeting'      => "Hello! 🏢 Looking to buy, rent, or invest in premium properties? Tell me your preferred location and budget!",
				'personas'      => array(
					array(
						'slug'             => 'estate-advisor',
						'name'             => 'Senior Property Consultant',
						'system_prompt'    => "You are a Senior Real Estate Consultant.\nHelp clients discover luxury apartments, villas, and commercial investment properties.\nQualify the client: Ask budget range, preferred location, timeline (ready to move vs under construction), and schedule a private site visit.",
						'greeting_message' => "Hello! 🏢 Looking to buy, rent, or invest in premium properties? Tell me your preferred location and budget!",
						'allowed_tools'    => array(),
					),
					array(
						'slug'             => 'site-visit-agent',
						'name'             => 'Site Visit & Booking Desk',
						'system_prompt'    => "You coordinate private site visits and home walkthroughs. Confirm property name, client phone number, preferred visit date, and cab pickup requirement.",
						'greeting_message' => "Need to schedule a private walkthrough or site visit? Let me know which property you are interested in!",
						'allowed_tools'    => array(),
					),
				),
			),

			'clinic' => array(
				'id'          => 'clinic',
				'title'       => __( 'Healthcare & Clinic Desk', 'tripcosmos-agents' ),
				'icon'        => 'dashicons-heart',
				'tagline'     => __( 'Doctor appointments, medical consultation triage, clinic hours, and patient care.', 'tripcosmos-agents' ),
				'primary_color' => '#059669',
				'widget_title'  => 'Care Assistant',
				'greeting'      => "Welcome to our clinic care desk. 🩺 How can we assist you today? You can book an appointment or ask about our specialists.",
				'personas'      => array(
					array(
						'slug'             => 'clinic-care',
						'name'             => 'Patient Care Coordinator',
						'system_prompt'    => "You are the Clinic Patient Care Assistant.\nProvide information on clinic timings, specialist doctors, consultation fees, and appointment scheduling.\nAlways include a reminder: 'For life-threatening medical emergencies, please visit the nearest hospital emergency room immediately.'",
						'greeting_message' => "Welcome to our clinic care desk. 🩺 How can we assist you today? You can book an appointment or ask about our specialists.",
						'allowed_tools'    => array(),
					),
				),
			),

			'ecommerce' => array(
				'id'          => 'ecommerce',
				'title'       => __( 'E-Commerce & Retail Concierge', 'tripcosmos-agents' ),
				'icon'        => 'dashicons-cart',
				'tagline'     => __( 'Product recommendations, order status, return policies, and checkout discounts.', 'tripcosmos-agents' ),
				'primary_color' => '#8b5cf6',
				'widget_title'  => 'Shopping Assistant',
				'greeting'      => "Hi there! 👋 Welcome to our store. Need help finding the perfect item, tracking an order, or getting a discount code?",
				'personas'      => array(
					array(
						'slug'             => 'shop-concierge',
						'name'             => 'Shopping Concierge',
						'system_prompt'    => "You are the Store Shopping Concierge.\nHelp shoppers find products, compare specifications, answer sizing/delivery questions, and offer available promo codes.\nEncourage checkout with friendly, helpful advice.",
						'greeting_message' => "Hi there! 👋 Welcome to our store. Need help finding the perfect item, tracking an order, or getting a discount code?",
						'allowed_tools'    => array(),
					),
				),
			),

			'agency' => array(
				'id'          => 'agency',
				'title'       => __( 'B2B Sales & Digital Agency', 'tripcosmos-agents' ),
				'icon'        => 'dashicons-chart-line',
				'tagline'     => __( 'Lead qualification, portfolio discovery, proposal requests, and consultation booking.', 'tripcosmos-agents' ),
				'primary_color' => '#4f46e5',
				'widget_title'  => 'Growth Advisor',
				'greeting'      => "Hi! 🚀 Looking to grow your business? Let us know what project or service you are interested in, and let's get you a quote.",
				'personas'      => array(
					array(
						'slug'             => 'agency-growth',
						'name'             => 'Client Growth Strategist',
						'system_prompt'    => "You are the Agency Growth Strategist.\nQualify incoming business leads: Ask about project scope, timeline, estimated monthly/project budget, and contact info to prepare a customized proposal.",
						'greeting_message' => "Hi! 🚀 Looking to grow your business? Let us know what project or service you are interested in, and let's get you a quote.",
						'allowed_tools'    => array(),
					),
				),
			),
		);
	}

	/**
	 * Apply an industry preset to the WordPress site.
	 *
	 * @param string $preset_id
	 * @return bool|WP_Error
	 */
	public static function apply_preset( $preset_id ) {
		$presets = self::get_presets();
		if ( ! isset( $presets[ $preset_id ] ) ) {
			return new WP_Error( 'invalid_preset', __( 'Specified preset does not exist.', 'tripcosmos-agents' ) );
		}

		$p = $presets[ $preset_id ];
		global $wpdb;
		$table_personas = $wpdb->prefix . 'tc_agent_personas';

		// Update Site Options
		update_option( 'tc_agents_active_preset', $preset_id );
		update_option( 'tc_agents_widget_title', $p['widget_title'] );
		update_option( 'tc_agents_widget_greeting', $p['greeting'] );
		update_option( 'tc_agents_widget_primary_color', $p['primary_color'] );

		// Seed/Update Personas
		foreach ( $p['personas'] as $persona ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_personas WHERE slug = %s", $persona['slug'] ) );
			$data = array(
				'slug'             => $persona['slug'],
				'name'             => $persona['name'],
				'system_prompt'    => $persona['system_prompt'],
				'greeting_message' => $persona['greeting_message'],
				'allowed_tools'    => wp_json_encode( $persona['allowed_tools'] ),
				'is_active'        => 1,
			);

			if ( $exists ) {
				$wpdb->update( $table_personas, $data, array( 'id' => (int) $exists ) );
			} else {
				$wpdb->insert( $table_personas, $data );
			}
		}

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'preset_applied',
				'info',
				array(
					'preset' => $preset_id,
					'title'  => $p['title'],
				),
				'',
				'admin'
			);
		}

		return true;
	}
}
