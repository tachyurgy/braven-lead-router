<?php
/**
 * BLR_Webhook_Dispatcher — hand a qualified lead to the CRM (Smrts) and/or any
 * automation endpoint (Zapier / Make / n8n), with idempotency, bounded retries,
 * and a full delivery audit trail.
 *
 * Design notes
 *  - Idempotency: every payload carries an `idempotency_key` (see helpers) and it
 *    is also sent as a header, so a retried or double-fired submission collapses
 *    to one CRM record.
 *  - Retries: transient failures (timeouts, 5xx, 429) are retried with a short
 *    linear backoff. 4xx (except 429) are terminal — retrying a bad request is
 *    pointless. In production the retry loop is offloaded to wp-cron / Action
 *    Scheduler; here it runs inline with a tight cap so the user is never blocked.
 *  - Every attempt is logged via an injected callback so the class stays testable
 *    outside WordPress (the harness passes a closure; WP passes BLR_CPT::log_delivery).
 *  - Transport: prefers wp_remote_post(); falls back to cURL when WP HTTP is absent
 *    (test harness), so the SAME class exercises the real network path in tests.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Webhook_Dispatcher {

	/** @var string CRM endpoint (Smrts CRM inbound webhook, or Zapier catch-hook). */
	protected $endpoint;

	/** @var string Optional shared secret sent as X-Braven-Signature (HMAC of body). */
	protected $secret;

	/** @var int Max total send attempts. */
	protected $max_attempts;

	/** @var callable|null Logger: fn(array $attempt): void */
	protected $logger;

	public function __construct( $endpoint = null, $secret = null, $max_attempts = 3, ?callable $logger = null ) {
		$this->endpoint     = $endpoint ?? blr_option( 'crm_webhook_url' );
		$this->secret       = $secret ?? blr_option( 'crm_webhook_secret' );
		$this->max_attempts = max( 1, (int) $max_attempts );
		$this->logger       = $logger;
	}

	public function configured() {
		return ! empty( $this->endpoint );
	}

	/**
	 * Build the canonical CRM payload from a lead + its routing decision.
	 * Field names use a CRM-neutral shape that maps cleanly onto Smrts CRM (or
	 * any CRM) via a documented field-mapping table (see /docs).
	 *
	 * @return array
	 */
	public function build_payload( array $lead, array $decision, $lead_id = 0 ) {
		return array(
			'idempotency_key' => blr_idempotency_key( $lead ),
			'lead_id'         => (int) $lead_id,
			'contact'         => array(
				'name'         => $lead['name'] ?? '',
				'title'        => $lead['title'] ?? '',
				'email'        => $lead['email'] ?? '',
				'phone'        => $lead['phone'] ?? '',
				'organization' => $lead['organization'] ?? '',
			),
			'segmentation'    => array(
				'buyer_type'  => $decision['buyer_type'] ?? '',
				'track'       => $decision['track'] ?? '',
				'intent_tier' => $decision['tier'] ?? '',
				'intent_score' => $decision['score'] ?? 0,
				'outcome'     => $decision['outcome'] ?? '',
				'priority'    => $decision['priority'] ?? '',
			),
			'notes'           => $lead['goals'] ?? '',
			'consent'         => ! empty( $lead['consent'] ),
			'attribution'     => $lead['utm'] ?? array(),
			'source'          => $lead['source'] ?? 'braven-lead-router',
			'page_url'        => $lead['page_url'] ?? '',
			'submitted_at'    => gmdate( 'c' ),
		);
	}

	/**
	 * Send with retries. Returns the final result and logs every attempt.
	 *
	 * @return array { delivered:bool, status:int, attempts:int, key:string }
	 */
	public function dispatch( array $lead, array $decision, $lead_id = 0 ) {
		$payload = $this->build_payload( $lead, $decision, $lead_id );
		$body    = wp_json_encode( $payload );
		$key     = $payload['idempotency_key'];

		if ( ! $this->configured() ) {
			$this->log( $lead_id, $key, 0, 0, 'skipped', 'No CRM endpoint configured' );
			return array( 'delivered' => false, 'status' => 0, 'attempts' => 0, 'key' => $key );
		}

		$headers = array(
			'Content-Type'         => 'application/json',
			'X-Braven-Idempotency' => $key,
		);
		if ( $this->secret ) {
			$headers['X-Braven-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $this->secret );
		}

		$status  = 0;
		$attempt = 0;
		$delivered = false;

		while ( $attempt < $this->max_attempts ) {
			$attempt++;
			$status = $this->post( $body, $headers );

			if ( $status >= 200 && $status < 300 ) {
				$delivered = true;
				$this->log( $lead_id, $key, $attempt, $status, 'delivered', '' );
				break;
			}

			$terminal = ( $status >= 400 && $status < 500 && 429 !== $status );
			$this->log( $lead_id, $key, $attempt, $status, $terminal ? 'failed' : 'retry', '' );

			if ( $terminal || $attempt >= $this->max_attempts ) {
				break;
			}
			// Short linear backoff; capped so we never block the request long.
			$this->sleep_ms( 150 * $attempt );
		}

		return array( 'delivered' => $delivered, 'status' => $status, 'attempts' => $attempt, 'key' => $key );
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @return int HTTP status (0 on transport failure).
	 */
	protected function post( $body, array $headers ) {
		if ( function_exists( 'wp_remote_post' ) ) {
			$res = wp_remote_post(
				$this->endpoint,
				array( 'timeout' => 5, 'blocking' => true, 'headers' => $headers, 'body' => $body )
			);
			if ( is_wp_error( $res ) ) {
				return 0;
			}
			return (int) wp_remote_retrieve_response_code( $res );
		}
		return $this->curl_post( $body, $headers ); // test-harness path
	}

	protected function curl_post( $body, array $headers ) {
		$h = array();
		foreach ( $headers as $k => $v ) {
			$h[] = "$k: $v";
		}
		$ch = curl_init( $this->endpoint );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $h,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 5,
		) );
		curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		return $code;
	}

	protected function sleep_ms( $ms ) {
		usleep( (int) $ms * 1000 );
	}

	protected function log( $lead_id, $key, $attempt, $status, $result, $message ) {
		$row = compact( 'lead_id', 'key', 'attempt', 'status', 'result', 'message' );
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $row );
		} elseif ( class_exists( 'BLR_CPT' ) ) {
			BLR_CPT::log_delivery( $row );
		}
	}
}
