<?php
/**
 * BLR_Lead_Repository — persist a routed lead as a `blr_lead` Custom Post Type.
 *
 * Why a CPT (not a bespoke table) for leads? Because it lights up the entire
 * WordPress ecosystem for free: the admin list table, search, capabilities,
 * export tools, REST, and — crucially for this shop — ACF Pro field groups map
 * straight onto the same meta keys (see /acf-json). The webhook-delivery LOG, by
 * contrast, is high-volume append-only audit data, so that lives in a custom
 * table (see class-blr-cpt.php). Right tool per job.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Lead_Repository {

	const POST_TYPE = 'blr_lead';

	/** Meta keys — identical to the ACF field-group export so ACF "just works". */
	const META = array(
		'name', 'organization', 'title', 'email', 'phone', 'goals',
		'buyer_type', 'track', 'intent_score', 'intent_tier', 'outcome',
		'priority', 'consent', 'source', 'page_url', 'utm', 'crm_status', 'crm_key',
	);

	/**
	 * Insert a lead. Deduplicates on the idempotency key so a resubmit updates the
	 * existing record instead of creating a twin.
	 *
	 * @return int post ID (0 on failure).
	 */
	public function save( array $lead, array $decision ) {
		$key      = blr_idempotency_key( $lead );
		$existing = $this->find_by_key( $key );

		$org   = $lead['organization'] ?: ( $lead['name'] ?: 'Unknown' );
		$title = sprintf( '%s — %s / %s', $org, $decision['buyer_type_label'] ?? '', $decision['track_label'] ?? '' );

		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => wp_strip_all_tags( $title ),
		);
		if ( $existing ) {
			$postarr['ID'] = $existing;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$meta = array(
			'name'         => $lead['name'] ?? '',
			'organization' => $lead['organization'] ?? '',
			'title'        => $lead['title'] ?? '',
			'email'        => $lead['email'] ?? '',
			'phone'        => $lead['phone'] ?? '',
			'goals'        => $lead['goals'] ?? '',
			'buyer_type'   => $decision['buyer_type'] ?? '',
			'track'        => $decision['track'] ?? '',
			'intent_score' => (int) ( $decision['score'] ?? 0 ),
			'intent_tier'  => $decision['tier'] ?? '',
			'outcome'      => $decision['outcome'] ?? '',
			'priority'     => $decision['priority'] ?? '',
			'consent'      => ! empty( $lead['consent'] ) ? 1 : 0,
			'source'       => $lead['source'] ?? '',
			'page_url'     => $lead['page_url'] ?? '',
			'utm'          => $lead['utm'] ?? array(),
			'crm_status'   => 'pending',
			'crm_key'      => $key,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $post_id, '_blr_' . $k, $v );
		}

		return (int) $post_id;
	}

	/**
	 * Record the CRM delivery outcome back onto the lead so the admin shows a live
	 * CRM status badge.
	 */
	public function set_crm_status( $post_id, $status ) {
		if ( $post_id ) {
			update_post_meta( $post_id, '_blr_crm_status', sanitize_text_field( $status ) );
		}
	}

	/**
	 * @return int|null existing post ID for this idempotency key.
	 */
	public function find_by_key( $key ) {
		$q = new WP_Query( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_blr_crm_key',
			'meta_value'     => $key,
		) );
		return $q->have_posts() ? (int) $q->posts[0] : null;
	}

	/** Convenience accessor used by the admin dashboard. */
	public static function get_meta( $post_id, $key, $default = '' ) {
		$v = get_post_meta( $post_id, '_blr_' . $key, true );
		return '' === $v ? $default : $v;
	}
}
