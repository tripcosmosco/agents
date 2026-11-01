<?php
/**
 * TripCosmos Master AI Assistant (Codename: Garuda Master Co-Pilot).
 *
 * Central autonomous intelligence co-pilot embedded inside X-Agents mobile app.
 * Provides real-time business intelligence, lead triaging, instant tour quotes,
 * B2B partner analytics, finance/payroll insights, and WhatsApp communication drafting.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Master_AI_Assistant {

	/**
	 * Assemble live operational context from across TripCosmos systems.
	 *
	 * @param bool $is_admin Whether the requesting user is Administrator or Manager.
	 * @return array
	 */
	public static function get_live_business_context( $is_admin = false ) {
		global $wpdb;

		// 1. Leads & CRM stats
		$leads_table = $wpdb->prefix . 'tc_agents_contacts';
		$leads_summary = array(
			'total_leads'     => 0,
			'hot_leads'       => 0,
			'recent_inquiries'=> array(),
		);

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $leads_table ) ) === $leads_table ) {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table" );
			$hot   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $leads_table WHERE stage IN ('qualified', 'quoted', 'hot_negotiation', 'negotiation')" );
			$recent = $wpdb->get_results( "SELECT id, name, phone, stage, deal_value, destination, next_follow_up_at, created_at FROM $leads_table ORDER BY updated_at DESC LIMIT 5", ARRAY_A );

			$leads_summary['total_leads'] = $total;
			$leads_summary['hot_leads']   = $hot;
			$leads_summary['recent_inquiries'] = $recent ?: array();
		}

		// 1b. WhatsApp SLA & Unread stats
		$whatsapp_table = $wpdb->prefix . 'tc_agent_whatsapp_threads';
		$wp_summary = array(
			'unread_threads' => 0,
			'sla_breached'   => 0,
		);
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $whatsapp_table ) ) === $whatsapp_table ) {
			$wp_summary['unread_threads'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $whatsapp_table WHERE unread_count > 0" );
			$wp_summary['sla_breached']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $whatsapp_table WHERE sla_breached = 1 OR (unread_count > 0 AND TIMESTAMPDIFF(MINUTE, last_message_at, NOW()) > 15)" );
		}

		// 1c. Jio SIM Telephony queue
		$telephony_table = $wpdb->prefix . 'tc_agent_call_dispatch';
		$telephony_summary = array(
			'pending_calls' => 0,
		);
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $telephony_table ) ) === $telephony_table ) {
			$telephony_summary['pending_calls'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $telephony_table WHERE status = 'pending'" );
		}

		// 2. B2B Network stats
		$b2b_table = $wpdb->prefix . 'tc_agents_b2b_agencies';
		$b2b_summary = array(
			'total_partners' => 0,
			'top_cities'     => array(),
		);

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $b2b_table ) ) === $b2b_table ) {
			$b2b_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $b2b_table" );
			$cities = $wpdb->get_col( "SELECT DISTINCT city FROM $b2b_table WHERE city != '' LIMIT 10" );
			$b2b_summary['total_partners'] = $b2b_count;
			$b2b_summary['top_cities']     = $cities ?: array( 'Ahmedabad', 'Surat', 'Mumbai', 'Pune', 'Delhi', 'Rajkot' );
		}

		// 3. Staff Payroll stats (Admin & Manager access only)
		$current_month = date( 'Y-m' );
		$payroll_data  = array();
		if ( $is_admin && class_exists( 'TC_Agents_Payroll_Service' ) ) {
			$run = get_option( 'tc_agents_payroll_run_' . $current_month, null );
			if ( empty( $run ) ) {
				$run = TC_Agents_Payroll_Service::calculate_payroll( $current_month );
			}
			$payroll_data = array(
				'month'       => $run['month_name'] ?? date( 'F Y' ),
				'staff_count' => $run['employee_count'] ?? 0,
				'total_gross' => $run['total_gross'] ?? 0,
				'total_net'   => $run['total_net'] ?? 0,
			);
		}

		// 4. Finance & Margins (Admin & Manager access only)
		$margins_summary = array();
		if ( $is_admin && class_exists( 'TC_Payments' ) && method_exists( 'TC_Payments', 'get_margins_summary' ) ) {
			$margins_summary = TC_Payments::get_margins_summary();
		}
		if ( class_exists( 'TC_Payments' ) && method_exists( 'TC_Payments', 'get_margins_summary' ) ) {
			$margins_summary = TC_Payments::get_margins_summary();
		}

		// 5. Fleet & Tariffs
		$fleet_tariffs = array(
			'Swift Dzire / Etios' => '₹12/km • Airport Transfer: ₹1,100 • 8hr/80km: ₹1,800',
			'Maruti Ertiga / XL6' => '₹15/km • Airport Transfer: ₹1,500 • 8hr/80km: ₹2,400',
			'Toyota Innova Crysta'=> '₹19/km • Airport Transfer: ₹2,000 • 8hr/80km: ₹3,000',
			'Tempo Traveller'     => '₹25-28/km • Airport Transfer: ₹3,000 • 8hr/80km: ₹4,500',
		);

		// 6. Tour Packages
		$tour_packages = array(
			'Varanasi (Kashi) 3D/2N Complete Pilgrimage' => '₹14,500 Retail • ₹12,200 B2B DMC Net (Sugam Darshan VIP Pass, Boat Aarti, Sarnath, AC Cab & Deluxe Ghat Hotel)',
			'Ayodhya Ram Janmabhoomi 2D/1N Sacred Tour'  => '₹9,800 Retail • ₹8,100 B2B DMC Net (Ram Mandir VIP Pass, Hanumangarhi, Saryu Aarti, AC Cab & Stay)',
			'Kashi - Ayodhya - Prayagraj 4D/3N Circuit'  => '₹22,500 Retail • ₹18,900 B2B DMC Net (Innova Crysta, Sugam Darshan, Sangam Boat, Deluxe Hotels)',
			'Prayagraj Triveni Sangam 1-Day Excursion'   => '₹4,800 Retail • ₹3,900 B2B DMC Net (Roundtrip AC Cab, Sangam Holy Dip Boat, Bade Hanuman Ji)',
			'Bodhgaya & Gaya 2D/1N Enlightenment Tour'   => '₹13,200 Retail • ₹11,000 B2B DMC Net (Mahabodhi UNESCO, Vishnupad Temple, AC Cab & Stay)',
		);

		return array(
			'timestamp'       => current_time( 'mysql' ),
			'date'            => date( 'l, F j, Y' ),
			'leads'           => $leads_summary,
			'whatsapp'        => $wp_summary,
			'telephony'       => $telephony_summary,
			'b2b'             => $b2b_summary,
			'payroll'         => $payroll_data,
			'margins'         => $margins_summary,
			'fleet_tariffs'   => $fleet_tariffs,
			'tour_packages'   => $tour_packages,
			'zoho_connected'  => class_exists( 'TC_Integration_Zoho' ) ? TC_Integration_Zoho::is_connected() : false,
		);
	}

	/**
	 * Build system prompt with live enterprise context.
	 *
	 * @param bool $is_admin
	 * @return string
	 */
	public static function build_system_prompt( $is_admin = false ) {
		$ctx = self::get_live_business_context( $is_admin );

		$prompt = "You are 'TripCosmos Master AI' (Codename: Garuda-1), the Chief Operations, Sales & Intelligence Co-Pilot of TripCosmos.co, embedded directly into the X-Agents mobile command app.\n\n";
		$prompt .= "Your role is to empower sales agents, operations coordinators, and company leadership with instant business intelligence, actionable advice, quote drafting, B2B partner support, and operations automation.\n\n";

		$prompt .= "=== CURRENT LIVE BUSINESS METRICS (" . $ctx['date'] . ") ===\n";
		$prompt .= "• Total CRM Leads: " . $ctx['leads']['total_leads'] . " (" . $ctx['leads']['hot_leads'] . " active in qualified/quoted/negotiation stage)\n";
		if ( ! empty( $ctx['whatsapp'] ) ) {
			$prompt .= "• WhatsApp Superfone Desk: " . $ctx['whatsapp']['unread_threads'] . " unread customer chats (" . $ctx['whatsapp']['sla_breached'] . " active SLA response breaches requiring urgent reply!)\n";
		}
		if ( ! empty( $ctx['telephony']['pending_calls'] ) ) {
			$prompt .= "• Jio SIM Offline Call Queue: " . $ctx['telephony']['pending_calls'] . " outbound calls awaiting Android companion dispatch.\n";
		}
		$prompt .= "• B2B Travel Agencies Network: " . $ctx['b2b']['total_partners'] . " verified wholesale partners across " . implode( ', ', $ctx['b2b']['top_cities'] ) . "\n";
		if ( $is_admin && ! empty( $ctx['payroll'] ) ) {
			$prompt .= "• Staff Payroll (" . $ctx['payroll']['month'] . "): " . $ctx['payroll']['staff_count'] . " staff members • Net Payout: ₹" . number_format( $ctx['payroll']['total_net'] ) . " (Gross: ₹" . number_format( $ctx['payroll']['total_gross'] ) . ")\n";
		} else {
			$prompt .= "• Staff Payroll: [Confidential — Protected Executive Data]\n";
		}
		$prompt .= "• Zoho Books Accounting: " . ( $ctx['zoho_connected'] ? 'Connected (Synced with Manual Journals)' : 'Offline/Demo' ) . "\n\n";

		$prompt .= "=== OFFICIAL TOUR PACKAGES & PRICING ===\n";
		foreach ( $ctx['tour_packages'] as $pkg => $details ) {
			$prompt .= "• " . $pkg . ": " . $details . "\n";
		}
		$prompt .= "\n";

		$prompt .= "=== OFFICIAL FLEET & CAB TARIFFS ===\n";
		foreach ( $ctx['fleet_tariffs'] as $cab => $rate ) {
			$prompt .= "• " . $cab . ": " . $rate . "\n";
		}
		$prompt .= "\n";

		$prompt .= "=== BEHAVIOR & RESPONSE GUIDELINES ===\n";
		$prompt .= "1. TONE: Confident, executive, crisp, hospitable, and operationally precise. Use bullet points and clean structure.\n";
		$prompt .= "2. CURRENCY: Always format prices in Indian Rupees (₹) with commas (e.g. ₹14,500).\n";
		$prompt .= "3. QUOTE DRAFTS: When asked to draft a quote or WhatsApp message, write ready-to-send messages with clear breakdown, inclusions, and a warm sign-off from TripCosmos.co.\n";
		$prompt .= "4. MARGIN PROTECTION: Built-in retail margin is 18-22%. B2B wholesale agency discount is capped at 10-12%. Maximum autonomous discount is 10%; higher requires manager approval.\n";
		if ( ! $is_admin ) {
			$prompt .= "5. CONFIDENTIALITY: If the user queries staff salaries, net payroll totals, or company banking/margins, state clearly: 'Staff payroll and internal compensation records are strictly confidential and accessible only to Company Leadership.'\n";
		}
		$prompt .= "6. ACTIONABILITY: At the end of helpful explanations, provide 2-3 logical next-step commands or suggestions.\n";

		return $prompt;
	}

	/**
	 * Process a conversational turn with the Master AI Assistant.
	 *
	 * @param string $user_message
	 * @param array $history Previous messages [ ['role' => 'user'|'assistant', 'content' => '...'] ]
	 * @param array $context Extra client context (e.g. current screen, active lead_id)
	 * @param bool $is_admin Whether the user is Administrator/Manager
	 * @return array
	 */
	public static function chat( $user_message, array $history = array(), array $context = array(), $is_admin = false ) {
		$system_prompt = self::build_system_prompt( $is_admin );

		if ( ! empty( $context['active_lead_name'] ) ) {
			$system_prompt .= "\n[Active Client Context: Working on lead " . sanitize_text_field( $context['active_lead_name'] ) . " (" . ( $context['destination'] ?? 'Kashi' ) . ")]\n";
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

		// Append recent history (up to last 6 turns to keep context window tight)
		if ( ! empty( $history ) && is_array( $history ) ) {
			$sliced = array_slice( $history, -6 );
			foreach ( $sliced as $h ) {
				if ( ! empty( $h['role'] ) && ! empty( $h['content'] ) ) {
					$messages[] = array(
						'role'    => 'user' === $h['role'] ? 'user' : 'assistant',
						'content' => (string) $h['content'],
					);
				}
			}
		}

		// Append current user message
		$messages[] = array(
			'role'    => 'user',
			'content' => (string) $user_message,
		);

		$start_time = microtime( true );

		$res = TC_AI_Router::chat_static(
			$messages,
			array(),
			array(
				'temperature' => 0.4,
				'max_tokens'  => 1024,
			)
		);

		$latency = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $res ) ) {
			return array(
				'ok'         => false,
				'error'      => $res->get_error_message(),
				'reply'      => 'I apologize, but I encountered an error accessing the intelligence router: ' . $res->get_error_message(),
				'latency_ms' => $latency,
			);
		}

		$reply = $res['content'] ?? '';
		$provider = $res['provider_used'] ?? 'gateway';

		// Formulate smart dynamic follow-up suggestions
		$suggestions = array();
		$lower_msg = strtolower( $user_message );
		if ( str_contains( $lower_msg, 'brief' ) || str_contains( $lower_msg, 'morning' ) ) {
			$suggestions = $is_admin
				? array( 'Show hot leads needing callback', 'What is our current payroll status?', 'Latest B2B partners in Gujarat' )
				: array( 'Show hot leads needing callback', 'Check tour packages', 'Calculate cab rate for Varanasi' );
		} elseif ( str_contains( $lower_msg, 'lead' ) || str_contains( $lower_msg, 'crm' ) ) {
			$suggestions = array( 'Draft WhatsApp follow-up for hot lead', 'Calculate 3D/2N Kashi quote for 4 pax', 'Check today\'s call logs' );
		} elseif ( str_contains( $lower_msg, 'cab' ) || str_contains( $lower_msg, 'fare' ) || str_contains( $lower_msg, 'tariff' ) ) {
			$suggestions = array( 'Calculate Varanasi to Ayodhya roundtrip Innova', 'Check airport transfer rate for Dzire', 'Draft cab confirmation message' );
		} elseif ( str_contains( $lower_msg, 'payroll' ) || str_contains( $lower_msg, 'salary' ) ) {
			$suggestions = $is_admin
				? array( 'Sync payroll journal to Zoho Books', 'Generate payslip for Rahul Sharma', 'Check staff directory' )
				: array( 'Check tour packages', 'Calculate cab fare', 'Draft WhatsApp brochure' );
		} else {
			$suggestions = $is_admin
				? array( '🌅 Daily Operations Briefing', '🚗 Instant Cab Tariff Calculator', '💰 Financial & Payroll Summary' )
				: array( '🌅 Daily Operations Briefing', '🚗 Instant Cab Tariff Calculator', '🔥 Hot Leads Priority' );
		}

		return array(
			'ok'          => true,
			'reply'       => $reply,
			'provider'    => $provider,
			'latency_ms'  => $latency,
			'suggestions' => $suggestions,
		);
	}

	/**
	 * Generate an instant Daily Operations Morning Briefing.
	 *
	 * @param bool $is_admin
	 * @return array
	 */
	public static function generate_daily_briefing( $is_admin = false ) {
		$ctx = self::get_live_business_context( $is_admin );

		$prompt = "Generate a comprehensive, motivational, and highly actionable 'Morning Operations & Sales Briefing' for the TripCosmos team. Cover:\n";
		$prompt .= "1. 🌅 Executive Overview: Highlights for " . $ctx['date'] . ".\n";
		$prompt .= "2. 🔥 CRM & Pipeline Priority: " . $ctx['leads']['hot_leads'] . " hot leads to close today.\n";
		$prompt .= "3. 🤝 B2B Wholesale Focus: " . $ctx['b2b']['total_partners'] . " agency partners in Gujarat/Maharashtra to follow up for winter pilgrimage bookings.\n";
		$prompt .= "4. 🚗 Fleet Readiness: Cabs available for Kashi-Ayodhya-Prayagraj circuits.\n";
		$prompt .= "5. 🎯 Team Target: Daily action items for sales & operations reps.\n";
		$prompt .= "Keep formatting punchy with bullet points and emojis.";

		return self::chat( $prompt, array(), array(), $is_admin );
	}
}
