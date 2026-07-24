<?php
/**
 * Plugin Name: Braven Demo — Mock CRM Sink
 * Description: DEMO ONLY. A local endpoint the Lead Router posts to so the CRM
 *              hand-off + delivery log are demonstrable end-to-end without a real
 *              Smrts CRM key. In production you delete this mu-plugin and point
 *              the router's "CRM webhook URL" setting at the real Smrts inbound
 *              webhook. It validates the HMAC signature if a secret is set and
 *              returns 200/OK; it stores nothing.
 *
 * This lives in mu-plugins and ships ONLY in the demo image — it is NOT part of
 * the braven-lead-router plugin.
 *
 * @package Braven_Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'braven/v1', '/mock-crm', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$body = $req->get_body();
			$data = json_decode( $body, true );
			// Bump a simple received-counter so the demo can show it took traffic.
			$n = (int) get_option( 'braven_mock_crm_count', 0 ) + 1;
			update_option( 'braven_mock_crm_count', $n );
			return new WP_REST_Response( array(
				'ok'       => true,
				'received' => is_array( $data ) ? array_keys( $data ) : array(),
				'crm'      => 'braven-mock',
				'record'   => 'mock-' . $n,
			), 200 );
		},
	) );
} );
