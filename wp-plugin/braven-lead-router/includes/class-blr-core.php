<?php
/**
 * BLR_Core — the bootstrapper that wires the plugin together.
 *
 * Singleton, instantiated on plugins_loaded. Registers the CPTs, fields, REST,
 * shortcodes, admin, and the Elementor + GTM integrations. Also owns the option
 * schema and the GTM dataLayer bootstrap printed into <head>.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Core {

	const OPTION_KEY = 'blr_settings';

	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Data layer: register post types on init.
		add_action( 'init', array( 'BLR_CPT', 'register' ) );

		// Feature modules.
		BLR_Fields::init();
		BLR_REST::init();
		BLR_Shortcode::init();
		BLR_Admin::init();
		BLR_Elementor::init();

		// GTM: print the dataLayer + container into <head>/<body> if an ID is set.
		add_action( 'wp_head', array( $this, 'print_gtm_head' ), 1 );
		add_action( 'wp_body_open', array( $this, 'print_gtm_body' ), 1 );

		// Ship default seed videos on first run (idempotent) so the library isn't empty.
		add_action( 'init', array( $this, 'maybe_seed_videos' ), 20 );

		load_plugin_textdomain( 'braven-lead-router', false, dirname( BLR_BASENAME ) . '/languages' );
	}

	/* ------------------------------------------------------------------ */

	public static function seed_default_options() {
		$existing = get_option( self::OPTION_KEY, null );
		if ( null === $existing ) {
			add_option( self::OPTION_KEY, array(
				'gtm_id'      => '',
				'booking_url' => '',
			) );
		}
	}

	/**
	 * The GTM container + a seeded dataLayer. The client wizard pushes funnel
	 * events onto this same dataLayer (see assets/js/router.js). Only printed when
	 * a container ID is configured, so nothing loads otherwise.
	 */
	public function print_gtm_head() {
		$gtm = blr_option( 'gtm_id' );
		if ( ! $gtm ) {
			return;
		}
		$gtm = esc_js( $gtm );
		echo "\n<!-- Braven Lead Router: dataLayer + GTM -->\n";
		echo "<script>window.dataLayer=window.dataLayer||[];</script>\n";
		echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm}');</script>\n";
	}

	public function print_gtm_body() {
		$gtm = blr_option( 'gtm_id' );
		if ( ! $gtm ) {
			return;
		}
		$gtm = esc_attr( $gtm );
		echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$gtm}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
	}

	/**
	 * Seed the video library once from data/videos.php so the demo/library is
	 * populated on activation. Idempotent: guarded by an option flag and by
	 * checking for an existing post with the same source key.
	 */
	public function maybe_seed_videos() {
		if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return; // only seed in admin / WP-CLI context, never on a front-end hit
		}
		if ( get_option( 'blr_videos_seeded' ) ) {
			return;
		}
		$videos = blr_data( 'videos' );
		if ( empty( $videos ) ) {
			return;
		}
		self::import_videos( $videos );
		update_option( 'blr_videos_seeded', 1 );
	}

	/**
	 * Import an array of video definitions as blr_video posts + terms.
	 * Public + static so the WP-CLI provisioner can call it directly.
	 */
	public static function import_videos( array $videos ) {
		foreach ( $videos as $v ) {
			$slug_key = sanitize_title( $v['title'] );
			$existing = get_page_by_path( $slug_key, OBJECT, BLR_CPT::VIDEO );
			if ( $existing ) {
				continue;
			}
			$post_id = wp_insert_post( array(
				'post_type'    => BLR_CPT::VIDEO,
				'post_status'  => 'publish',
				'post_title'   => $v['title'],
				'post_name'    => $slug_key,
				'post_excerpt' => $v['excerpt'] ?? '',
				'post_content' => $v['description'] ?? ( $v['excerpt'] ?? '' ),
				'menu_order'   => $v['order'] ?? 0,
			) );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			update_post_meta( $post_id, '_blr_video_url', esc_url_raw( $v['url'] ?? '' ) );
			update_post_meta( $post_id, '_blr_video_duration', sanitize_text_field( $v['duration'] ?? '' ) );
			if ( ! empty( $v['track'] ) ) {
				wp_set_object_terms( $post_id, $v['track'], 'blr_track' );
			}
			if ( ! empty( $v['proficiency'] ) ) {
				wp_set_object_terms( $post_id, $v['proficiency'], 'blr_proficiency' );
			}
		}
	}
}
