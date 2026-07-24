<?php
/**
 * BLR_Shortcode — front-end entry points.
 *
 *   [braven_lead_router]     the self-select wizard
 *   [braven_video_library]   the categorized, filterable training library
 *
 * Both are also exposed as an Elementor widget (class-blr-elementor-widget.php)
 * that simply calls render() here — one renderer, two placements — so a marketer
 * can drop the tool on any Elementor page while the deep logic stays in PHP.
 *
 * Assets are enqueued ONLY on pages that actually contain the shortcode (or the
 * Elementor widget), so nothing loads on the rest of the site — part of the
 * PageSpeed >90 discipline. CSS/JS are tiny, dependency-free, and deferred.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Shortcode {

	public static function init() {
		add_shortcode( 'braven_lead_router', array( __CLASS__, 'render_router' ) );
		add_shortcode( 'braven_video_library', array( __CLASS__, 'render_video_library' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'blr-router', BLR_URL . 'assets/css/router.css', array(), BLR_VERSION );
		wp_register_script( 'blr-router', BLR_URL . 'assets/js/router.js', array(), BLR_VERSION, true );
		wp_register_script( 'blr-video', BLR_URL . 'assets/js/video-library.js', array(), BLR_VERSION, true );
	}

	protected static function boot_data() {
		$engine = new BLR_Routing_Engine();
		return array(
			'restUrl' => esc_url_raw( rest_url( BLR_REST::NS ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'config'  => $engine->config(),
			'settings' => array(
				'bookingUrl'    => blr_option( 'booking_url', '' ),
				'leadMagnetUrl' => blr_option( 'lead_magnet_url', '' ),
				'privacyUrl'    => blr_option( 'privacy_url', '' ),
				'brand'         => 'Braven Agency',
			),
		);
	}

	/**
	 * @param array $atts Shortcode attributes (heading override, etc.).
	 * @return string
	 */
	public static function render_router( $atts = array() ) {
		$atts = shortcode_atts( array(
			'heading' => 'Find your program path',
			'intro'   => 'Answer three quick questions and we’ll route you to the right next step.',
		), $atts, 'braven_lead_router' );

		wp_enqueue_style( 'blr-router' );
		wp_enqueue_script( 'blr-router' );
		wp_add_inline_script( 'blr-router', 'window.BLR_BOOT=' . wp_json_encode( self::boot_data() ) . ';', 'before' );

		ob_start();
		$heading = $atts['heading'];
		$intro   = $atts['intro'];
		$config  = ( new BLR_Routing_Engine() )->config(); // render steps server-side
		$logo    = BLR_URL . 'assets/img/braven-logo.png';
		include BLR_DIR . 'templates/router.php';
		return ob_get_clean();
	}

	/**
	 * @return string
	 */
	public static function render_video_library( $atts = array() ) {
		$atts = shortcode_atts( array(
			'per_page' => 24,
			'heading'  => 'Training Library',
		), $atts, 'braven_video_library' );

		wp_enqueue_style( 'blr-router' );
		wp_enqueue_script( 'blr-video' );

		$videos = self::query_videos( (int) $atts['per_page'] );
		$tracks = self::terms( 'blr_track' );
		$levels = self::terms( 'blr_proficiency' );

		ob_start();
		$heading = $atts['heading'];
		include BLR_DIR . 'templates/video-library.php';
		return ob_get_clean();
	}

	/**
	 * Fetch published videos as a lean array for the template. Cached by WP_Query.
	 *
	 * @return array
	 */
	protected static function query_videos( $per_page ) {
		$q = new WP_Query( array(
			'post_type'      => BLR_CPT::VIDEO,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		) );
		$out = array();
		foreach ( $q->posts as $p ) {
			$tracks = wp_get_post_terms( $p->ID, 'blr_track', array( 'fields' => 'slugs' ) );
			$levels = wp_get_post_terms( $p->ID, 'blr_proficiency', array( 'fields' => 'slugs' ) );
			$ext    = get_post_meta( $p->ID, '_blr_video_url', true );
			$out[]  = array(
				'id'        => $p->ID,
				'title'     => get_the_title( $p ),
				'excerpt'   => wp_strip_all_tags( get_the_excerpt( $p ) ),
				'url'       => $ext ?: get_permalink( $p ), // external clip if set, else the CPT single page
				'external'  => (bool) $ext,
				'duration'  => get_post_meta( $p->ID, '_blr_video_duration', true ),
				'tracks'    => $tracks,
				'levels'    => $levels,
			);
		}
		wp_reset_postdata();
		return $out;
	}

	protected static function terms( $tax ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		$out   = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count );
			}
		}
		return $out;
	}
}
