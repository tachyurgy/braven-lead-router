<?php
/**
 * Plugin Name:       Braven Lead Router
 * Plugin URI:        https://braven-demo.levelbrook.com
 * Description:        Self-select lead-routing engine for institutional buyers (cities, counties, chambers, foundations). Routes each visitor by buyer type + training track to the right intake form, booking link, or tailored next step — capturing the qualified lead into Custom Post Types and pushing it to the CRM (Smrts), an email workflow, and GA4 (client dataLayer + server-side Measurement Protocol). Ships a categorized, CPT-backed video repository. Zero page-builder bloat.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Levelbrook (Patrick Donahue)
 * Author URI:        https://about.levelbrook.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       braven-lead-router
 *
 * ---------------------------------------------------------------------------
 *  ARCHITECTURE (see /docs/index.html for the full walk-through)
 *  ---------------------------------------------------------------------------
 *  The plugin is deliberately layered so the *decision logic* is framework-
 *  agnostic and unit-testable without WordPress, while a thin adapter layer
 *  binds it to WP primitives (CPTs, ACF-mappable meta, REST, shortcodes, an
 *  Elementor widget, the admin).
 *
 *    includes/
 *      class-blr-routing-engine.php   PURE  — the conditional routing brain
 *      class-blr-lead-validator.php   PURE  — sanitise + validate a submission
 *      class-blr-ga4.php              PURE  — GA4 Measurement Protocol client
 *      class-blr-webhook-dispatcher   PURE* — CRM webhook (Smrts) w/ retries
 *      class-blr-email-workflow.php   thin  — trigger transactional email
 *      class-blr-lead-repository.php  WP    — persist a lead as a CPT + meta
 *      class-blr-cpt.php              WP    — register CPTs + taxonomies + table
 *      class-blr-fields.php           WP    — meta registration + ACF detection
 *      class-blr-rest.php             WP    — /wp-json/braven/v1/* endpoints
 *      class-blr-shortcode.php        WP    — [braven_lead_router] / video lib
 *      class-blr-elementor-widget.php WP    — the same tool as an Elementor block
 *      class-blr-admin.php            WP    — leads dashboard, columns, settings
 *      class-blr-core.php             WP    — bootstraps + wires everything
 *
 *  * PURE* = has one optional wp_remote_post() seam; falls back to cURL so the
 *    identical class runs inside the standalone test harness (see /tests).
 * ---------------------------------------------------------------------------
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BLR_VERSION', '1.0.0' );
define( 'BLR_FILE', __FILE__ );
define( 'BLR_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLR_URL', plugin_dir_url( __FILE__ ) );
define( 'BLR_BASENAME', plugin_basename( __FILE__ ) );

require_once BLR_DIR . 'includes/helpers.php';
require_once BLR_DIR . 'includes/class-blr-routing-engine.php';
require_once BLR_DIR . 'includes/class-blr-lead-validator.php';
require_once BLR_DIR . 'includes/class-blr-ga4.php';
require_once BLR_DIR . 'includes/class-blr-webhook-dispatcher.php';
require_once BLR_DIR . 'includes/class-blr-email-workflow.php';
require_once BLR_DIR . 'includes/class-blr-lead-repository.php';
require_once BLR_DIR . 'includes/class-blr-cpt.php';
require_once BLR_DIR . 'includes/class-blr-fields.php';
require_once BLR_DIR . 'includes/class-blr-rest.php';
require_once BLR_DIR . 'includes/class-blr-shortcode.php';
require_once BLR_DIR . 'includes/class-blr-admin.php';
require_once BLR_DIR . 'includes/class-blr-elementor-widget.php';
require_once BLR_DIR . 'includes/class-blr-core.php';

/**
 * Activation: register post types/taxonomies then flush rewrite rules once, and
 * create the custom webhook-delivery table via dbDelta.
 */
function blr_activate() {
	BLR_CPT::register(); // so rewrite rules include our slugs before the flush
	BLR_CPT::install_delivery_table();
	BLR_Core::seed_default_options();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'blr_activate' );

/**
 * Deactivation: only flush rewrites. Data (leads/videos/settings) is preserved;
 * true teardown lives in uninstall.php so a deactivate never destroys leads.
 */
function blr_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'blr_deactivate' );

// Boot the plugin on plugins_loaded so add-ons (ACF, Elementor) are detectable.
add_action( 'plugins_loaded', array( 'BLR_Core', 'instance' ) );
