<?php
/**
 * Braven Child theme — presentation only.
 *
 * Enqueues the Hello Elementor parent stylesheet, Braven's Google Fonts, and a
 * small brand.css. Deliberately thin: the demo's behaviour lives entirely in the
 * braven-lead-router plugin, matching the job's "deep logic in custom PHP,
 * Elementor/theme stays clean" principle.
 *
 * @package Braven_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'hello-elementor', get_template_directory_uri() . '/style.css', array(), null );

	// Braven's fonts (same families the live site uses).
	wp_enqueue_style(
		'braven-fonts',
		'https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'braven-brand', get_stylesheet_directory_uri() . '/assets/brand.css', array( 'braven-fonts' ), '1.0.0' );
}, 20 );

// Preconnect to the font host for a faster first paint (PageSpeed hygiene).
add_filter( 'wp_resource_hints', function ( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}, 10, 2 );
