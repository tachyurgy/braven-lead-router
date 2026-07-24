<?php
/**
 * BLR_GA4 — server-side GA4 Measurement Protocol client.
 *
 * The client-side dataLayer (see assets/js/router.js) fires funnel events into
 * GTM/GA4 for the browser session. This class ALSO fires the money event
 * (`generate_lead`) server-side, so a conversion is recorded even if an ad
 * blocker or a bounce kills the client beacon. That server-side redundancy is
 * the "server-side tagging a plus" line in the job spec.
 *
 * Credentials (GA4 Measurement ID + Measurement Protocol API secret) come from
 * plugin settings; with none configured it degrades to a no-op that still logs,
 * so the demo never hard-fails.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_GA4 {

	protected $measurement_id;
	protected $api_secret;

	public function __construct( $measurement_id = null, $api_secret = null ) {
		$this->measurement_id = $measurement_id ?? blr_option( 'ga4_measurement_id' );
		$this->api_secret     = $api_secret ?? blr_option( 'ga4_api_secret' );
	}

	public function configured() {
		return $this->measurement_id && $this->api_secret;
	}

	/**
	 * Send the GA4 `generate_lead` conversion event.
	 *
	 * @param array  $decision  Routing decision (buyer_type, track, tier, outcome…).
	 * @param string $client_id GA client_id captured from the _ga cookie (keeps the
	 *                          server event stitched to the same browser session).
	 * @return array { ok, status, skipped }
	 */
	public function generate_lead( array $decision, $client_id ) {
		$payload = array(
			'client_id' => $client_id ?: $this->fallback_client_id(),
			'events'    => array(
				array(
					'name'   => 'generate_lead',
					'params' => array(
						'buyer_type'  => $decision['buyer_type'] ?? '',
						'track'       => $decision['track'] ?? '',
						'intent_tier' => $decision['tier'] ?? '',
						'outcome'     => $decision['outcome'] ?? '',
						'value'       => $this->tier_value( $decision['tier'] ?? 'C' ),
						'currency'    => 'USD',
						'engagement_time_msec' => 1,
					),
				),
			),
		);

		if ( ! $this->configured() ) {
			return array( 'ok' => false, 'skipped' => true, 'status' => 0 );
		}

		$endpoint = add_query_arg(
			array(
				'measurement_id' => $this->measurement_id,
				'api_secret'     => $this->api_secret,
			),
			'https://www.google-analytics.com/mp/collect'
		);

		$res = wp_remote_post(
			$endpoint,
			array(
				'timeout'  => 4,
				'blocking' => true,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'skipped' => false, 'status' => 0, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return array( 'ok' => ( $code >= 200 && $code < 300 ), 'skipped' => false, 'status' => $code );
	}

	/** A rough monetary weight per tier so GA4 conversion value is meaningful. */
	protected function tier_value( $tier ) {
		return array( 'A' => 40, 'B' => 15, 'C' => 3 )[ $tier ] ?? 1;
	}

	protected function fallback_client_id() {
		return sprintf( '%d.%d', wp_rand( 100000000, 999999999 ), time() );
	}
}
