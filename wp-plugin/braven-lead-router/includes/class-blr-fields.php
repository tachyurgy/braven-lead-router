<?php
/**
 * BLR_Fields — typed meta registration + ACF Pro integration.
 *
 * The plugin works with OR without ACF Pro:
 *  - Without ACF: meta is still registered/typed via register_post_meta(), and
 *    the admin renders a native read-only meta panel (see class-blr-admin.php).
 *  - With ACF Pro (this shop's stack): we point ACF's JSON loader at
 *    /acf-json so the shipped field groups (group_blr_lead / group_blr_video)
 *    appear as fully-editable ACF fields, bound to the SAME `_blr_*` meta keys
 *    the engine reads. Zero data migration — ACF simply takes over the UI.
 *
 * This dual path is the honest answer to "custom PHP + ACF Pro": the logic never
 * depends on ACF, but ACF is a first-class citizen when present.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Fields {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		// Tell ACF where our shipped field groups live (load + save in-plugin).
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'acf_load_json' ) );
	}

	public static function register_meta() {
		$string_keys = array( 'name', 'organization', 'title', 'email', 'phone', 'goals',
			'buyer_type', 'track', 'outcome', 'priority', 'source', 'page_url', 'crm_status', 'crm_key', 'intent_tier' );

		foreach ( $string_keys as $key ) {
			register_post_meta( BLR_CPT::LEAD, '_blr_' . $key, array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
				'auth_callback' => array( __CLASS__, 'can_edit_leads' ),
			) );
		}
		register_post_meta( BLR_CPT::LEAD, '_blr_intent_score', array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => false,
			'auth_callback' => array( __CLASS__, 'can_edit_leads' ),
		) );
		register_post_meta( BLR_CPT::LEAD, '_blr_consent', array(
			'type' => 'boolean', 'single' => true, 'show_in_rest' => false,
			'auth_callback' => array( __CLASS__, 'can_edit_leads' ),
		) );

		// Video meta (external video URL + duration).
		register_post_meta( BLR_CPT::VIDEO, '_blr_video_url', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
		) );
		register_post_meta( BLR_CPT::VIDEO, '_blr_video_duration', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
		) );
	}

	public static function can_edit_leads() {
		return current_user_can( 'edit_posts' );
	}

	public static function acf_load_json( $paths ) {
		$paths[] = BLR_DIR . 'acf-json';
		return $paths;
	}

	public static function acf_active() {
		return class_exists( 'ACF' ) || function_exists( 'get_field' );
	}
}
