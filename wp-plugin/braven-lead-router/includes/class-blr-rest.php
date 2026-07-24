<?php
/**
 * BLR_REST — the JSON API the wizard talks to.
 *
 *   GET  /wp-json/braven/v1/config    public   — buyer types, tracks, qualifiers
 *   POST /wp-json/braven/v1/route     public   — selection -> routing decision
 *   POST /wp-json/braven/v1/lead      public   — capture: persist + CRM + email + GA4
 *
 * The route endpoint is intentionally separate from lead capture so the UI can
 * show the tailored routed view (and fire the `generate_lead`-intent dataLayer
 * event) BEFORE asking for contact details — a lower-friction flow that also
 * lets us A/B the routed copy without re-submitting PII.
 *
 * Security: a WP REST nonce guards capture against CSRF; a honeypot + the
 * idempotency key guard against bots and double-submits. Public reads carry no
 * PII.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_REST {

	const NS = 'braven/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( self::NS, '/config', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/route', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_route' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/lead', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'post_lead' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function get_config( WP_REST_Request $req ) {
		$engine = new BLR_Routing_Engine();
		return new WP_REST_Response( $engine->config(), 200 );
	}

	/**
	 * Compute a routing decision from a selection. No persistence, no PII.
	 */
	public static function post_route( WP_REST_Request $req ) {
		$params = $req->get_json_params() ?: $req->get_params();
		$engine = new BLR_Routing_Engine();
		$decision = $engine->decide( array(
			'buyer_type' => blr_clean( $params['buyer_type'] ?? '' ),
			'track'      => blr_clean( $params['track'] ?? '' ),
			'answers'    => is_array( $params['answers'] ?? null ) ? blr_clean( $params['answers'] ) : array(),
		) );

		// Resolve the abstract action to a concrete destination from settings.
		$decision['destination'] = self::resolve_destination( $decision['action'], $decision );

		return new WP_REST_Response( array( 'ok' => true, 'decision' => $decision ), 200 );
	}

	/**
	 * Capture a lead: validate -> persist (CPT) -> CRM webhook -> email -> GA4.
	 * Orchestrated here so the full funnel fires atomically on one request.
	 */
	public static function post_lead( WP_REST_Request $req ) {
		// CSRF: require a valid REST nonce for the write endpoint.
		if ( ! self::verify_nonce( $req ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_nonce' ), 403 );
		}

		$params = $req->get_json_params() ?: $req->get_params();

		// Recompute the decision server-side — never trust a client-sent outcome.
		$engine   = new BLR_Routing_Engine();
		$decision = $engine->decide( array(
			'buyer_type' => blr_clean( $params['buyer_type'] ?? '' ),
			'track'      => blr_clean( $params['track'] ?? '' ),
			'answers'    => is_array( $params['answers'] ?? null ) ? blr_clean( $params['answers'] ) : array(),
		) );

		$validator = new BLR_Lead_Validator();
		$result    = $validator->validate( (array) $params, $decision['form_fields'] );

		if ( isset( $result['errors']['_spam'] ) ) {
			// Silently 200 a bot so it doesn't learn; capture nothing.
			return new WP_REST_Response( array( 'ok' => true, 'lead_id' => 0 ), 200 );
		}
		if ( ! $result['ok'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'errors' => $result['errors'] ), 422 );
		}

		$lead = $result['lead'];

		// 1) Persist as a CPT.
		$repo    = new BLR_Lead_Repository();
		$lead_id = $repo->save( $lead, $decision );

		// 2) CRM webhook (Smrts) with retries + audit log.
		$dispatcher = new BLR_Webhook_Dispatcher();
		$crm        = $dispatcher->dispatch( $lead, $decision, $lead_id );
		$repo->set_crm_status( $lead_id, $crm['delivered'] ? 'delivered' : ( $dispatcher->configured() ? 'failed' : 'unconfigured' ) );

		// 3) Email workflow (internal alert + prospect auto-responder + hook).
		( new BLR_Email_Workflow() )->run( $lead, $decision, $lead_id );

		// 4) Server-side GA4 conversion (redundant with the client dataLayer).
		$ga4 = new BLR_GA4();
		$ga4->generate_lead( $decision, blr_clean( $params['ga_client_id'] ?? '' ) );

		return new WP_REST_Response( array(
			'ok'          => true,
			'lead_id'     => $lead_id,
			'crm'         => array( 'delivered' => $crm['delivered'], 'status' => $crm['status'], 'attempts' => $crm['attempts'] ),
			'destination' => self::resolve_destination( $decision['action'], $decision ),
			'decision'    => array(
				'outcome' => $decision['outcome'],
				'tier'    => $decision['tier'],
			),
		), 201 );
	}

	/* --------------------------------------------------------------------- */

	protected static function verify_nonce( WP_REST_Request $req ) {
		$nonce = $req->get_header( 'X-WP-Nonce' );
		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Map an abstract outcome action to a concrete URL/next-step from settings.
	 */
	protected static function resolve_destination( $action, array $decision ) {
		switch ( $action ) {
			case 'booking':
				return array( 'type' => 'booking', 'url' => blr_option( 'booking_url', '' ) );
			case 'lead_magnet':
				return array( 'type' => 'download', 'url' => blr_option( 'lead_magnet_url', '' ) );
			case 'intake':
			default:
				return array( 'type' => 'intake', 'url' => '' ); // handled inline by the form
		}
	}
}
