<?php
/**
 * Uninstall — full teardown, only ever runs on delete (never on deactivate).
 *
 * Removes leads, videos, terms, the delivery table, options, and the seed flag.
 * Deactivation preserves everything (see braven-lead-router.php) so an operator
 * can safely toggle the plugin without losing captured leads.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete both CPTs' posts + their meta.
foreach ( array( 'blr_lead', 'blr_video' ) as $pt ) {
	$posts = get_posts( array( 'post_type' => $pt, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}
}

// Drop the delivery table.
$table = $wpdb->prefix . 'blr_deliveries';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be bound; teardown only.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'blr_settings' );
delete_option( 'blr_videos_seeded' );
