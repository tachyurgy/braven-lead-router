<?php
/**
 * BLR_Admin — the operator surface.
 *
 *  - Custom columns on the Leads list (buyer type, track, intent tier, CRM status)
 *    so the team triages from the list view without opening each record.
 *  - A read-only lead meta panel (works with OR without ACF; ACF Pro supersedes it).
 *  - A "Routing Console" dashboard page: funnel counts by tier/outcome + the CRM
 *    webhook delivery log — the live telemetry that proves capture end-to-end.
 *  - A Settings page: CRM webhook URL/secret, GA4 MP credentials, GTM ID, booking
 *    URL, lead-magnet URL, notification email.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Admin {

	public static function init() {
		add_filter( 'manage_' . BLR_CPT::LEAD . '_posts_columns', array( __CLASS__, 'lead_columns' ) );
		add_action( 'manage_' . BLR_CPT::LEAD . '_posts_custom_column', array( __CLASS__, 'lead_column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'lead_metabox' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/* ------------------------- Leads list columns ------------------------ */

	public static function lead_columns( $cols ) {
		$new = array( 'cb' => $cols['cb'], 'title' => 'Organization' );
		$new['blr_buyer'] = 'Buyer type';
		$new['blr_track'] = 'Track';
		$new['blr_tier']  = 'Intent';
		$new['blr_crm']   = 'CRM';
		$new['date']      = $cols['date'];
		return $new;
	}

	public static function lead_column( $col, $post_id ) {
		switch ( $col ) {
			case 'blr_buyer':
				echo esc_html( self::pretty( BLR_Lead_Repository::get_meta( $post_id, 'buyer_type' ) ) );
				break;
			case 'blr_track':
				echo esc_html( self::pretty( BLR_Lead_Repository::get_meta( $post_id, 'track' ) ) );
				break;
			case 'blr_tier':
				$tier  = BLR_Lead_Repository::get_meta( $post_id, 'intent_tier', 'C' );
				$score = BLR_Lead_Repository::get_meta( $post_id, 'intent_score', 0 );
				printf( '<strong>%s</strong> <span style="color:#7a7772">(%d)</span>', esc_html( $tier ), (int) $score );
				break;
			case 'blr_crm':
				$status = BLR_Lead_Repository::get_meta( $post_id, 'crm_status', 'pending' );
				$colors = array( 'delivered' => '#2e7d32', 'failed' => '#c62828', 'pending' => '#a87340', 'unconfigured' => '#7a7772' );
				printf(
					'<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;color:#fff;background:%s">%s</span>',
					esc_attr( $colors[ $status ] ?? '#7a7772' ),
					esc_html( $status )
				);
				break;
		}
	}

	/* ------------------------- Lead meta panel --------------------------- */

	public static function lead_metabox() {
		// If ACF Pro is present, its field group renders the editable UI instead.
		if ( BLR_Fields::acf_active() ) {
			return;
		}
		add_meta_box( 'blr_lead_details', 'Lead Details', array( __CLASS__, 'render_lead_metabox' ), BLR_CPT::LEAD, 'normal', 'high' );
	}

	public static function render_lead_metabox( $post ) {
		$rows = array(
			'Name'         => BLR_Lead_Repository::get_meta( $post->ID, 'name' ),
			'Organization' => BLR_Lead_Repository::get_meta( $post->ID, 'organization' ),
			'Title'        => BLR_Lead_Repository::get_meta( $post->ID, 'title' ),
			'Email'        => BLR_Lead_Repository::get_meta( $post->ID, 'email' ),
			'Phone'        => BLR_Lead_Repository::get_meta( $post->ID, 'phone' ),
			'Buyer type'   => self::pretty( BLR_Lead_Repository::get_meta( $post->ID, 'buyer_type' ) ),
			'Track'        => self::pretty( BLR_Lead_Repository::get_meta( $post->ID, 'track' ) ),
			'Intent'       => BLR_Lead_Repository::get_meta( $post->ID, 'intent_tier' ) . ' (' . BLR_Lead_Repository::get_meta( $post->ID, 'intent_score', 0 ) . ')',
			'Outcome'      => self::pretty( BLR_Lead_Repository::get_meta( $post->ID, 'outcome' ) ),
			'CRM status'   => BLR_Lead_Repository::get_meta( $post->ID, 'crm_status' ),
			'Goals'        => BLR_Lead_Repository::get_meta( $post->ID, 'goals' ),
			'Page URL'     => BLR_Lead_Repository::get_meta( $post->ID, 'page_url' ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $val ) {
			printf( '<tr><th style="width:160px">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $val ) );
		}
		echo '</tbody></table>';
	}

	/* --------------------------- Menu pages ------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . BLR_CPT::LEAD,
			'Routing Console',
			'Routing Console',
			'manage_options',
			'blr-console',
			array( __CLASS__, 'render_console' )
		);
		add_submenu_page(
			'edit.php?post_type=' . BLR_CPT::LEAD,
			'Router Settings',
			'Settings',
			'manage_options',
			'blr-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render_console() {
		$counts = self::funnel_counts();
		$log    = BLR_CPT::recent_deliveries( 25 );
		echo '<div class="wrap"><h1>Routing Console</h1>';
		echo '<p>Live funnel from the self-select tool. Leads are stored as the <code>blr_lead</code> CPT; CRM handoffs are logged in <code>' . esc_html( BLR_CPT::delivery_table() ) . '</code>.</p>';

		echo '<h2>Leads by intent tier</h2><table class="widefat" style="max-width:520px"><thead><tr><th>Tier</th><th>Leads</th></tr></thead><tbody>';
		foreach ( array( 'A' => 'High intent', 'B' => 'Qualified', 'C' => 'Nurture' ) as $k => $label ) {
			printf( '<tr><td><strong>%s</strong> — %s</td><td>%d</td></tr>', esc_html( $k ), esc_html( $label ), (int) ( $counts['tier'][ $k ] ?? 0 ) );
		}
		echo '</tbody></table>';

		echo '<h2 style="margin-top:24px">Recent CRM deliveries</h2>';
		echo '<table class="widefat striped"><thead><tr><th>When</th><th>Lead</th><th>Attempt</th><th>Status</th><th>Result</th></tr></thead><tbody>';
		if ( empty( $log ) ) {
			echo '<tr><td colspan="5">No deliveries yet.</td></tr>';
		}
		foreach ( (array) $log as $row ) {
			printf(
				'<tr><td>%s</td><td>#%d</td><td>%d</td><td>%d</td><td>%s</td></tr>',
				esc_html( $row['created_at'] ), (int) $row['lead_id'], (int) $row['attempt'], (int) $row['http_status'], esc_html( $row['result'] )
			);
		}
		echo '</tbody></table></div>';
	}

	protected static function funnel_counts() {
		$out = array( 'tier' => array( 'A' => 0, 'B' => 0, 'C' => 0 ) );
		$q   = new WP_Query( array(
			'post_type'      => BLR_CPT::LEAD,
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( $q->posts as $id ) {
			$tier = BLR_Lead_Repository::get_meta( $id, 'intent_tier', 'C' );
			if ( isset( $out['tier'][ $tier ] ) ) {
				$out['tier'][ $tier ]++;
			}
		}
		return $out;
	}

	/* --------------------------- Settings -------------------------------- */

	public static function register_settings() {
		register_setting( 'blr_settings_group', BLR_Core::OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
		) );
	}

	public static function sanitize_settings( $input ) {
		$clean = array();
		$urls  = array( 'crm_webhook_url', 'booking_url', 'lead_magnet_url', 'privacy_url' );
		$texts = array( 'crm_webhook_secret', 'ga4_measurement_id', 'ga4_api_secret', 'gtm_id' );
		$mails = array( 'notify_email', 'from_email' );
		foreach ( $urls as $k ) {
			$clean[ $k ] = isset( $input[ $k ] ) ? esc_url_raw( trim( $input[ $k ] ) ) : '';
		}
		foreach ( $texts as $k ) {
			$clean[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( $input[ $k ] ) : '';
		}
		foreach ( $mails as $k ) {
			$clean[ $k ] = isset( $input[ $k ] ) ? sanitize_email( $input[ $k ] ) : '';
		}
		return $clean;
	}

	public static function render_settings() {
		$fields = array(
			'crm_webhook_url'    => array( 'CRM webhook URL', 'Smrts CRM inbound webhook / Zapier catch-hook. Leads POST here.' ),
			'crm_webhook_secret' => array( 'CRM webhook secret', 'Optional. Signs the payload as X-Braven-Signature (HMAC-SHA256).' ),
			'ga4_measurement_id' => array( 'GA4 Measurement ID', 'e.g. G-XXXXXXXXXX — for the server-side generate_lead event.' ),
			'ga4_api_secret'     => array( 'GA4 API secret', 'Measurement Protocol API secret (Admin → Data Streams).' ),
			'gtm_id'             => array( 'GTM container ID', 'e.g. GTM-535B5NR — printed into the dataLayer bootstrap.' ),
			'booking_url'        => array( 'Booking URL', 'Calendly/booking link for high-intent (Tier A) leads.' ),
			'lead_magnet_url'    => array( 'Lead-magnet URL', 'Program overview PDF for nurture (Tier C) leads.' ),
			'privacy_url'        => array( 'Privacy policy URL', 'Linked from the consent checkbox.' ),
			'notify_email'       => array( 'Team notification email', 'Where internal lead alerts are sent.' ),
			'from_email'         => array( 'Auto-responder From', 'From address for the prospect auto-responder.' ),
		);
		$opts = get_option( BLR_Core::OPTION_KEY, array() );
		echo '<div class="wrap"><h1>Braven Lead Router — Settings</h1><form method="post" action="options.php">';
		settings_fields( 'blr_settings_group' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $meta ) {
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%3$s[%1$s]" value="%4$s" class="regular-text"><p class="description">%5$s</p></td></tr>',
				esc_attr( $key ),
				esc_html( $meta[0] ),
				esc_attr( BLR_Core::OPTION_KEY ),
				esc_attr( $opts[ $key ] ?? '' ),
				esc_html( $meta[1] )
			);
		}
		echo '</tbody></table>';
		submit_button();
		echo '</form></div>';
	}

	protected static function pretty( $slug ) {
		return ucwords( str_replace( '_', ' ', (string) $slug ) );
	}
}
