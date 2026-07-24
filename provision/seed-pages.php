<?php
/**
 * First-boot provisioning, run via `wp eval-file` from the entrypoint (full WP
 * context, plugin already active). Idempotent — safe to re-run.
 *
 *  - plugin settings (from env)     - custom logo + site identity
 *  - seed the video library         - pages (front router + training library)
 *  - primary nav menu
 *
 * @package Braven_Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_url = getenv( 'SITE_URL' ) ?: 'https://braven-demo.levelbrook.com';

/* ---- 1. site identity ------------------------------------------------ */
update_option( 'blogname', 'Braven Agency' );
update_option( 'blogdescription', "America's Program Delivery Partner for Small Business AI Resilience" );

/* ---- 2. plugin settings from env ------------------------------------ */
$settings = get_option( 'blr_settings', array() );
$settings = array_merge( array(
	'crm_webhook_url'    => getenv( 'CRM_WEBHOOK_URL' ) ?: 'http://localhost/wp-json/braven/v1/mock-crm',
	'crm_webhook_secret' => getenv( 'CRM_WEBHOOK_SECRET' ) ?: 'braven-demo-secret',
	'gtm_id'             => getenv( 'GTM_ID' ) ?: '',
	'ga4_measurement_id' => getenv( 'GA4_MEASUREMENT_ID' ) ?: '',
	'ga4_api_secret'     => getenv( 'GA4_API_SECRET' ) ?: '',
	'booking_url'        => getenv( 'BOOKING_URL' ) ?: 'https://bravenagency.com/#contact',
	'lead_magnet_url'    => getenv( 'LEAD_MAGNET_URL' ) ?: 'https://bravenagency.com/',
	'privacy_url'        => getenv( 'PRIVACY_URL' ) ?: ( $site_url . '/privacy' ),
	'notify_email'       => getenv( 'NOTIFY_EMAIL' ) ?: 'team@levelbrook.com',
	'from_email'         => getenv( 'FROM_EMAIL' ) ?: 'hello@outreach.levelbrook.com',
), $settings );
// env wins over any previously-stored blanks
foreach ( $settings as $k => $v ) {
	if ( getenv( strtoupper( $k ) ) ) {
		$settings[ $k ] = getenv( strtoupper( $k ) );
	}
}
update_option( 'blr_settings', $settings );

/* ---- 3. seed the video library -------------------------------------- */
if ( class_exists( 'BLR_Core' ) ) {
	BLR_Core::import_videos( blr_data( 'videos' ) );
	update_option( 'blr_videos_seeded', 1 );
}

/* ---- 4. custom logo + favicon --------------------------------------- */
$logo_src = WP_PLUGIN_DIR . '/braven-lead-router/assets/img/braven-logo.png';
if ( file_exists( $logo_src ) && ! get_theme_mod( 'custom_logo' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'braven-logo.png' );
	copy( $logo_src, $tmp );
	$file  = array( 'name' => 'braven-logo.png', 'tmp_name' => $tmp );
	$aid   = media_handle_sideload( $file, 0 );
	if ( ! is_wp_error( $aid ) ) {
		set_theme_mod( 'custom_logo', $aid );
		update_option( 'site_icon', $aid );
	}
}

/* ---- 5. pages ------------------------------------------------------- */
function braven_ensure_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		return $existing->ID;
	}
	return wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	) );
}

$hero = '<div class="braven-demo-note">Interactive demo of a self-select lead-routing tool, built for Braven Agency. '
	. '<a href="https://braven-demo.levelbrook.com/docs/" target="_blank" rel="noopener">Read the build docs →</a></div>'
	. '<section class="braven-hero">'
	. '<span class="braven-hero__eyebrow">Partner With Braven</span>'
	. '<h1>Turn your funding into measurable small-business growth.</h1>'
	. '<p>Cities, counties, chambers, and foundations use Braven to train small businesses in AI and digital marketing. '
	. 'Tell us who you are and what you want to run — we’ll route you to the right next step in under a minute.</p>'
	. '<p class="braven-hero__certs">MBE · SBE · LSBE Certified</p>'
	. '</section>';

$front_id = braven_ensure_page( 'partner', 'Partner With Braven', $hero . "\n[braven_lead_router]" );

$lib_content = '<section class="braven-hero" style="padding-bottom:14px">'
	. '<span class="braven-hero__eyebrow">Training Library</span>'
	. '<h1>Lessons for every small business.</h1>'
	. '<p>Browse the curriculum by track and level. This is the same CPT-backed, no-plugin-bloat library engine described in the build docs.</p>'
	. '</section>' . "\n[braven_video_library]";
$lib_id = braven_ensure_page( 'training-library', 'Training Library', $lib_content );

/* front page = the router */
if ( $front_id && ! is_wp_error( $front_id ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front_id );
}

/* ---- 6. primary menu ------------------------------------------------ */
$menu_name = 'Primary';
$menu      = wp_get_nav_menu_object( $menu_name );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( $menu_name );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Partner With Us', 'menu-item-object' => 'page', 'menu-item-object-id' => $front_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Training Library', 'menu-item-object' => 'page', 'menu-item-object-id' => $lib_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Build Docs', 'menu-item-url' => $site_url . '/docs/', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'bravenagency.com', 'menu-item-url' => 'https://bravenagency.com', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );

	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations['menu-1'] = $menu_id; // hello-elementor primary location
	set_theme_mod( 'nav_menu_locations', $locations );
}

echo "[seed] provisioning complete\n";
