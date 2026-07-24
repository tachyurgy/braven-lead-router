<?php
/**
 * BLR_Lead_Validator — sanitise + validate an inbound submission.
 *
 * Pure: no WordPress calls except the shimmable sanitizers. Returns either a
 * clean normalised lead array or a list of field errors. The REST controller
 * never persists anything this class hasn't blessed.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Lead_Validator {

	/** Fields the router may ever collect (superset of any single outcome). */
	const KNOWN_FIELDS = array( 'name', 'organization', 'title', 'email', 'phone', 'goals' );

	/**
	 * @param array $raw            Raw $request params.
	 * @param array $required_fields Fields the routed outcome asked for.
	 * @return array { 'ok' => bool, 'lead' => array, 'errors' => array }
	 */
	public function validate( array $raw, array $required_fields ) {
		$errors = array();
		$lead   = array(
			'buyer_type' => blr_clean( $raw['buyer_type'] ?? '' ),
			'track'      => blr_clean( $raw['track'] ?? '' ),
			'answers'    => is_array( $raw['answers'] ?? null ) ? blr_clean( $raw['answers'] ) : array(),
			'consent'    => ! empty( $raw['consent'] ) ? 1 : 0,
			'source'     => blr_clean( $raw['source'] ?? 'braven-lead-router' ),
			'page_url'   => esc_url_raw( $raw['page_url'] ?? '' ),
		);

		// Contact + context fields.
		foreach ( self::KNOWN_FIELDS as $field ) {
			$lead[ $field ] = '';
			if ( isset( $raw[ $field ] ) ) {
				$lead[ $field ] = 'email' === $field
					? sanitize_email( $raw[ $field ] )
					: blr_clean( $raw[ $field ] );
			}
		}

		// UTM / attribution passthrough (never trusted for logic, only stored).
		$lead['utm'] = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid' ) as $k ) {
			if ( ! empty( $raw['utm'][ $k ] ) ) {
				$lead['utm'][ $k ] = blr_clean( $raw['utm'][ $k ] );
			}
		}

		// Honeypot: a bot fills this hidden field; humans never see it.
		if ( ! empty( $raw['company_website'] ) ) {
			$errors['_spam'] = 'rejected';
		}

		// Required-field checks scoped to what the outcome actually asked for.
		foreach ( $required_fields as $field ) {
			if ( 'email' === $field ) {
				if ( empty( $lead['email'] ) || ! is_email( $lead['email'] ) ) {
					$errors['email'] = 'A valid email is required.';
				}
			} elseif ( in_array( $field, self::KNOWN_FIELDS, true ) && '' === $lead[ $field ] && 'title' !== $field && 'goals' !== $field ) {
				$errors[ $field ] = 'This field is required.';
			}
		}

		if ( empty( $lead['consent'] ) ) {
			$errors['consent'] = 'Please agree to be contacted.';
		}

		return array(
			'ok'     => empty( $errors ),
			'lead'   => $lead,
			'errors' => $errors,
		);
	}
}
