<?php
/**
 * BLR_Elementor — register the router + video library as native Elementor widgets.
 *
 * This is the "keep Elementor clean while the deep logic lives in custom PHP"
 * pattern from the job spec: the widgets carry NO business logic — they call
 * BLR_Shortcode::render_*() — so marketers drag "Braven Lead Router" onto any
 * Elementor page and the exact same engine renders.
 *
 * IMPORTANT load-order note: Elementor's base class (\Elementor\Widget_Base) is
 * NOT available at `plugins_loaded` (when this file is required). So the widget
 * subclasses are defined LAZILY — required from includes/elementor-widgets.php
 * inside register(), which only fires on `elementor/widgets/register` when
 * Elementor is fully loaded. The plugin remains fully functional (shortcodes)
 * even if Elementor is never active.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Elementor {

	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register' ) );
	}

	public static function register( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		// Elementor is loaded now — safe to declare subclasses of Widget_Base.
		require_once BLR_DIR . 'includes/elementor-widgets.php';
		$widgets_manager->register( new BLR_Elementor_Router_Widget() );
		$widgets_manager->register( new BLR_Elementor_Video_Widget() );
	}
}
