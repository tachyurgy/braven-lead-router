<?php
/**
 * BLR_CPT — custom data structures.
 *
 *  Custom Post Types
 *    blr_lead   captured partner leads (admin-only; ACF-mappable meta)
 *    blr_video  the categorized training-video repository
 *
 *  Custom Taxonomies (attached to blr_video)
 *    blr_track        Digital Marketing / Social Media / AI
 *    blr_proficiency  Beginner / Intermediate / Advanced
 *
 *  Custom Table
 *    {$prefix}blr_deliveries   append-only CRM webhook delivery audit log
 *
 * The video library is deliberately CPT + taxonomy (not a heavy directory
 * plugin): terms are indexed, WP_Query is cached, and the whole thing is served
 * as static-friendly markup. That is the concrete answer to the "build a
 * searchable video library without tanking page load" screening question.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_CPT {

	const VIDEO = 'blr_video';
	const LEAD  = 'blr_lead';

	public static function register() {
		self::register_leads();
		self::register_videos();
		self::register_taxonomies();
	}

	protected static function register_leads() {
		register_post_type( self::LEAD, array(
			'labels'          => array(
				'name'          => 'Leads',
				'singular_name' => 'Lead',
				'menu_name'     => 'Partner Leads',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-groups',
			'menu_position'   => 26,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
			'show_in_rest'    => false, // leads are private; never expose over public REST
		) );
	}

	protected static function register_videos() {
		register_post_type( self::VIDEO, array(
			'labels'       => array(
				'name'          => 'Training Videos',
				'singular_name' => 'Training Video',
				'menu_name'     => 'Video Library',
				'add_new_item'  => 'Add Training Video',
			),
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'training-videos' ),
			'menu_icon'    => 'dashicons-video-alt3',
			'menu_position'=> 27,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'show_in_rest' => true,
		) );
	}

	protected static function register_taxonomies() {
		register_taxonomy( 'blr_track', self::VIDEO, array(
			'labels'            => array( 'name' => 'Tracks', 'singular_name' => 'Track' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'track' ),
		) );
		register_taxonomy( 'blr_proficiency', self::VIDEO, array(
			'labels'            => array( 'name' => 'Proficiency', 'singular_name' => 'Proficiency' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'level' ),
		) );
	}

	/* --------------------------------------------------------------------- *
	 *  Custom delivery-log table
	 * --------------------------------------------------------------------- */

	public static function delivery_table() {
		global $wpdb;
		return $wpdb->prefix . 'blr_deliveries';
	}

	public static function install_delivery_table() {
		global $wpdb;
		$table   = self::delivery_table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			idem_key VARCHAR(64) NOT NULL DEFAULT '',
			attempt SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			http_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			result VARCHAR(20) NOT NULL DEFAULT '',
			message VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY lead_id (lead_id),
			KEY idem_key (idem_key)
		) $collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Append one delivery attempt. Signature matches the dispatcher's logger.
	 *
	 * @param array $row { lead_id, key, attempt, status, result, message }
	 */
	public static function log_delivery( array $row ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- append-only audit log, no cache.
		$wpdb->insert(
			self::delivery_table(),
			array(
				'lead_id'     => (int) ( $row['lead_id'] ?? 0 ),
				'idem_key'    => substr( (string) ( $row['key'] ?? '' ), 0, 64 ),
				'attempt'     => (int) ( $row['attempt'] ?? 0 ),
				'http_status' => (int) ( $row['status'] ?? 0 ),
				'result'      => substr( (string) ( $row['result'] ?? '' ), 0, 20 ),
				'message'     => substr( (string) ( $row['message'] ?? '' ), 0, 255 ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Recent delivery rows for the admin dashboard.
	 *
	 * @return array
	 */
	public static function recent_deliveries( $limit = 25 ) {
		global $wpdb;
		$table = self::delivery_table();
		$limit = max( 1, (int) $limit );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be bound; limit is int-cast.
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT $limit", ARRAY_A );
	}
}
