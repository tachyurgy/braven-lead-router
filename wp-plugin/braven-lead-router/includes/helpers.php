<?php
/**
 * Small shared helpers. Kept dependency-light so the pure classes can be loaded
 * inside the standalone test harness (which shims a handful of wp_* functions).
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load one of the declarative data files (routes / videos) as a PHP array.
 *
 * These are plain PHP so they are OPcache-cached and need no DB round-trip on
 * every request — the routing matrix is hot-path config, not content.
 *
 * @param string $name File base name inside /data (without extension).
 * @return array
 */
function blr_data( $name ) {
	static $cache = array();
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}
	$path = BLR_DIR . 'data/' . preg_replace( '/[^a-z0-9_-]/', '', $name ) . '.php';
	$data = is_readable( $path ) ? require $path : array();
	$cache[ $name ] = is_array( $data ) ? $data : array();
	return $cache[ $name ];
}

/**
 * Recursively sanitise a value coming off the wire. Strings are text-sanitised,
 * arrays are walked. Never trust the client; this is the single choke point.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function blr_clean( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'blr_clean', $value );
	}
	return sanitize_text_field( (string) $value );
}

/**
 * Fetch a plugin option with a sane default.
 *
 * @param string $key     Option sub-key.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function blr_option( $key, $default = '' ) {
	$opts = get_option( BLR_Core::OPTION_KEY, array() );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
}

/**
 * Resolve a term slug to its display name from a [ ['slug','name'], ... ] list.
 * Used by the video-library template badges. Guarded so it is declared once.
 *
 * @param string $slug
 * @param array  $terms
 * @return string
 */
if ( ! function_exists( 'blr_term_name' ) ) {
	function blr_term_name( $slug, $terms ) {
		foreach ( $terms as $t ) {
			if ( isset( $t['slug'] ) && $t['slug'] === $slug ) {
				return $t['name'];
			}
		}
		return ucwords( str_replace( '-', ' ', (string) $slug ) );
	}
}

/**
 * Generate a stable idempotency key for a submission so a double-fired form or a
 * webhook retry never creates duplicate CRM records.
 *
 * @param array $lead Normalised lead.
 * @return string
 */
function blr_idempotency_key( array $lead ) {
	$basis = strtolower( trim( $lead['email'] ?? '' ) )
		. '|' . ( $lead['organization'] ?? '' )
		. '|' . ( $lead['buyer_type'] ?? '' )
		. '|' . ( $lead['track'] ?? '' )
		. '|' . gmdate( 'Y-m-d-H' ); // collapse rapid repeats within the hour
	return substr( hash( 'sha256', $basis ), 0, 32 );
}
