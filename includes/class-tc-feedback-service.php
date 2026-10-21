<?php
/**
 * Google Business Profile Review Generator & Sentiment Gate.
 *
 * Automatically collects post-trip traveler sentiment via WhatsApp.
 * Directs 4-5 star happy pilgrims to leave public 5-star Google Reviews.
 * Intercepts 1-3 star grievances internally to alert management before public posting.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Feedback_Service {

	const GOOGLE_REVIEW_URL = 'https://g.page/r/CbG99dO6rSndEBM/review';

	/**
	 * Send post-trip WhatsApp feedback request to traveler.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool|WP_Error
	 */
	public static function trigger_request( $contact_id ) {
		$contact = class_exists( 'TC_Contact_Service' ) ? TC_Contact_Service::get( (int) $contact_id ) : null;
		if ( ! $contact || empty( $contact['phone'] ) ) {
			return new WP_Error( 'invalid_contact', 'Contact or phone not found' );
		}

		$phone = $contact['phone_e164'] ?: $contact['phone'];
		$name  = ! empty( $contact['name'] ) && 'Traveler' !== $contact['name'] ? $contact['name'] : 'Pilgrim';

		$text = sprintf(
			"Namaste %s ji! 🙏\n\nGreetings from the TripCosmos Varanasi team. We hope your Ganga Aarti boat ride, temple darshan, and journey were peaceful and blessed.\n\nCould you please take 10 seconds to rate your overall experience with our chauffeurs, tour coordinators, and boat deck?\n\nPlease reply with a rating from *1 to 5*:\n⭐⭐⭐⭐⭐ (5 - Outstanding)\n⭐⭐⭐⭐ (4 - Very Good)\n⭐⭐⭐ (3 - Average)\n⭐⭐ (2 - Needs Improvement)\n⭐ (1 - Unsatisfactory)",
			$name
		);

		if ( class_exists( 'TC_Integration_Evolution' ) && TC_Integration_Evolution::is_configured() ) {
			$res = TC_Integration_Evolution::send_message( $phone, $text, 'broadcast' );
			if ( ! is_wp_error( $res ) ) {
				$meta = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
				$meta['feedback_requested_at'] = current_time( 'mysql' );
				TC_Contact_Service::update( $contact_id, array(), false );
				TC_Contact_Service::add_activity( $contact_id, 'system', 'Post-trip feedback review prompt dispatched via WhatsApp.' );
				return true;
			}
			return $res;
		}

		return false;
	}

	/**
	 * Process incoming customer rating response.
	 *
	 * @param string $phone Customer phone number.
	 * @param string $message Incoming WhatsApp message content.
	 * @return bool Whether the message was handled as feedback.
	 */
	public static function maybe_handle_feedback_response( $phone, $message ) {
		$contact = class_exists( 'TC_Contact_Service' ) ? TC_Contact_Service::find( '', $phone ) : null;
		if ( ! $contact ) {
			return false;
		}

		$meta = json_decode( (string) ( $contact['meta_data'] ?? '' ), true ) ?: array();
		if ( empty( $meta['feedback_requested_at'] ) || ! empty( $meta['feedback_rating_received'] ) ) {
			return false;
		}

		$clean = trim( $message );
		$rating = null;

		// Extract rating 1-5 from text or star emojis
		if ( preg_match( '/\b([1-5])\b/', $clean, $m ) ) {
			$rating = (int) $m[1];
		} elseif ( mb_strpos( $clean, '⭐⭐⭐⭐⭐' ) !== false || mb_strpos( $clean, '5' ) !== false ) {
			$rating = 5;
		} elseif ( mb_strpos( $clean, '⭐⭐⭐⭐' ) !== false ) {
			$rating = 4;
		} elseif ( mb_strpos( $clean, '⭐⭐⭐' ) !== false ) {
			$rating = 3;
		} elseif ( mb_strpos( $clean, '⭐⭐' ) !== false ) {
			$rating = 2;
		} elseif ( mb_strpos( $clean, '⭐' ) !== false ) {
			$rating = 1;
		}

		if ( null === $rating ) {
			return false;
		}

		$meta['feedback_rating_received'] = $rating;
		$meta['feedback_received_at']     = current_time( 'mysql' );
		global $wpdb;
		$wpdb->update(
			TC_Contact_Service::table(),
			array( 'meta_data' => wp_json_encode( $meta ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $contact['id'] )
		);

		$name = ! empty( $contact['name'] ) && 'Traveler' !== $contact['name'] ? $contact['name'] : 'Pilgrim';

		if ( $rating >= 4 ) {
			// Sentiment Positive: Route to Google Review
			$reply = sprintf(
				"Har Har Mahadev %s ji! 🛕🙏\n\nThank you so much for your gracious %d-star rating! Your kind words mean the world to our ground guides, chauffeurs, and boat crew in Kashi.\n\nCould you please take 30 seconds to tap this link and leave a Google Review? It helps other pilgrim families find trusted local care:\n\n👉 %s\n\nMay Lord Vishwanath and Maa Ganga bless you and your family!",
				$name,
				$rating,
				self::GOOGLE_REVIEW_URL
			);
			TC_Contact_Service::add_activity( (int) $contact['id'], 'system', sprintf( 'Positive rating (%d/5) received. Google Review link dispatched.', $rating ) );
		} else {
			// Sentiment Gate: Intercept grievance internally
			$reply = sprintf(
				"Namaste %s ji. 🙏\n\nWe sincerely apologize that your experience did not meet expectations (%d/5 stars). At TripCosmos, guest comfort and sacred dignity are our highest priority.\n\nOur Operations Director has been alerted immediately and will personally follow up with you to address any issues. Thank you for your honest feedback.",
				$name,
				$rating
			);

			// Alert Manager via WhatsApp & Internal Ticket
			if ( class_exists( 'TC_CRM_Notify' ) ) {
				TC_CRM_Notify::send_managers(
					sprintf( '⚠️ URGENT: Low Feedback Alert (%d/5 Stars)', $rating ),
					sprintf( "Customer: %s\nPhone: %s\nRating: %d/5\nFeedback: %s", $name, $phone, $rating, $clean ),
					(int) $contact['id']
				);
			}
			TC_Contact_Service::add_activity( (int) $contact['id'], 'system', sprintf( '⚠️ Low rating (%d/5) intercepted. Manager alerted immediately.', $rating ) );
		}

		if ( class_exists( 'TC_Integration_Evolution' ) ) {
			TC_Integration_Evolution::send_message( $phone, $reply, 'inbound_reply' );
		}

		return true;
	}
}
