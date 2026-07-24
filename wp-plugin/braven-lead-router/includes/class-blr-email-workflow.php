<?php
/**
 * BLR_Email_Workflow — trigger the transactional email side of the funnel.
 *
 * Two messages fire on a qualified lead:
 *   1. Internal alert to the Braven team (routing summary, so a rep can act fast).
 *   2. A tailored auto-responder to the prospect matching the routed outcome
 *      (booking confirmation vs. proposal ack vs. nurture overview).
 *
 * In WordPress this uses wp_mail(), which any SMTP / ESP plugin (Brevo, SES,
 * Postmark) transparently upgrades. It fires an action hook (`blr_lead_captured`)
 * too, so a marketer can attach a Zapier/FluentCRM/Mailchimp automation without
 * editing plugin code. It never throws — email failure must not fail capture.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Email_Workflow {

	/**
	 * @return array { internal:bool, prospect:bool }
	 */
	public function run( array $lead, array $decision, $lead_id = 0 ) {
		$internal = $this->send_internal_alert( $lead, $decision, $lead_id );
		$prospect = '' !== ( $lead['email'] ?? '' ) ? $this->send_autoresponder( $lead, $decision ) : false;

		/**
		 * Let any external automation (Zapier, FluentCRM, Mailchimp) hook the lead.
		 * This is the documented seam for marketing to extend the workflow.
		 */
		if ( function_exists( 'do_action' ) ) {
			do_action( 'blr_lead_captured', $lead, $decision, $lead_id );
		}

		return array( 'internal' => $internal, 'prospect' => $prospect );
	}

	protected function send_internal_alert( array $lead, array $decision, $lead_id ) {
		$to = blr_option( 'notify_email', get_option( 'admin_email' ) );
		if ( ! $to ) {
			return false;
		}
		$subject = sprintf(
			'[Braven] %s lead — %s / %s (%s)',
			strtoupper( $decision['tier'] ?? 'C' ),
			$decision['buyer_type_label'] ?? '',
			$decision['track_label'] ?? '',
			$decision['priority'] ?? ''
		);
		$lines = array(
			'A new partner lead was routed by the self-select tool.',
			'',
			'Organization : ' . ( $lead['organization'] ?? '' ),
			'Contact      : ' . ( $lead['name'] ?? '' ) . ' <' . ( $lead['email'] ?? '' ) . '>',
			'Phone        : ' . ( $lead['phone'] ?? '' ),
			'Buyer type   : ' . ( $decision['buyer_type_label'] ?? '' ),
			'Track        : ' . ( $decision['track_label'] ?? '' ),
			'Intent       : tier ' . ( $decision['tier'] ?? '' ) . ' (score ' . ( $decision['score'] ?? '' ) . ')',
			'Routed to    : ' . ( $decision['outcome'] ?? '' ),
			'Goals        : ' . ( $lead['goals'] ?? '' ),
			'',
			'Admin: ' . admin_url( 'edit.php?post_type=blr_lead' ),
		);
		return (bool) wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	protected function send_autoresponder( array $lead, array $decision ) {
		$name    = $lead['name'] ?: 'there';
		$subject = $this->autoresponder_subject( $decision['outcome'] ?? 'nurture' );
		$body    = sprintf(
			"Hi %s,\n\n%s\n\n%s\n\n— The Braven Agency Team\n(562) 826-3995",
			$name,
			$decision['sub'] ?? 'Thanks for reaching out.',
			$this->autoresponder_action_line( $decision )
		);
		$headers = array( 'From: Braven Agency <' . blr_option( 'from_email', get_option( 'admin_email' ) ) . '>' );
		return (bool) wp_mail( $lead['email'], $subject, $body, $headers );
	}

	protected function autoresponder_subject( $outcome ) {
		$map = array(
			'book_call'           => 'Let’s design your Braven program',
			'request_proposal'    => 'Your Braven program proposal is on the way',
			'funding_partnership' => 'Building a fundable program with Braven',
			'nurture'             => 'Your Braven program overview',
		);
		return $map[ $outcome ] ?? 'Thanks for reaching out to Braven';
	}

	protected function autoresponder_action_line( array $decision ) {
		switch ( $decision['action'] ?? '' ) {
			case 'booking':
				return 'Pick a time that works here: ' . blr_option( 'booking_url', '#' );
			case 'lead_magnet':
				return 'Grab the program overview here: ' . blr_option( 'lead_magnet_url', '#' );
			default:
				return 'We’ll follow up within one business day with next steps.';
		}
	}
}
