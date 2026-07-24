<?php
/**
 * Minimal WordPress function shims so the PURE plugin classes (routing engine,
 * validator, webhook payload builder, GA4 payload) can be exercised with plain
 * `php tests/test-routing.php` — no WordPress, no DB, no HTTP server.
 *
 * This is the same technique used to unit-test the routing contract in CI. Only
 * the surface the pure classes actually touch is shimmed.
 *
 * @package Braven_Lead_Router
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'BLR_VERSION', 'test' );
define( 'BLR_DIR', dirname( __DIR__ ) . '/wp-plugin/braven-lead-router/' );
define( 'BLR_URL', 'http://example.test/' );

// --- sanitisers -----------------------------------------------------------
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $s ) ) ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { $s = trim( (string) $s ); return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function esc_url_raw( $s ) { $s = trim( (string) $s ); return preg_match( '#^https?://#i', $s ) ? $s : ''; }
function sanitize_title( $s ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $s ), '-' ) ); }

// --- options / misc -------------------------------------------------------
$GLOBALS['__blr_opts'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['__blr_opts'][ $k ] ?? $d; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function wp_rand( $min, $max ) { return random_int( $min, $max ); }
function add_query_arg( $args, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args ); }

// --- HTTP (unused in these tests; present so classes load) ---------------
function wp_remote_post( $url, $args = array() ) { return array( 'response' => array( 'code' => 0 ) ); }
function is_wp_error( $t ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }

// Load the pure classes under test.
require BLR_DIR . 'includes/helpers.php';
require BLR_DIR . 'includes/class-blr-routing-engine.php';
require BLR_DIR . 'includes/class-blr-lead-validator.php';
require BLR_DIR . 'includes/class-blr-webhook-dispatcher.php';

// helpers.php references BLR_Core::OPTION_KEY via blr_option(); provide a stub.
class BLR_Core { const OPTION_KEY = 'blr_settings'; }
