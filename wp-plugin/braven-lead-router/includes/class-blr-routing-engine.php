<?php
/**
 * BLR_Routing_Engine — the framework-agnostic decision brain.
 *
 * Pure logic: give it a selection (buyer type, track, qualifier answers) and it
 * returns a fully-resolved routing decision — intent score, tier, chosen
 * outcome, tailored copy, the form fields to collect, and the destination.
 *
 * It has NO WordPress dependency beyond the blr_data() loader (which is trivially
 * shimmed in the test harness), so the entire routing contract is unit-tested in
 * /tests/test-routing.php with zero WordPress bootstrapping.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BLR_Routing_Engine {

	/** @var array The routes.php matrix. */
	protected $matrix;

	public function __construct( ?array $matrix = null ) {
		$this->matrix = $matrix ?: blr_data( 'routes' );
	}

	/**
	 * The public config the front-end needs to render the wizard.
	 *
	 * @return array
	 */
	public function config() {
		return array(
			'buyer_types' => $this->matrix['buyer_types'],
			'tracks'      => $this->matrix['tracks'],
			'qualifiers'  => $this->matrix['qualifiers'],
		);
	}

	/**
	 * Score a set of qualifier answers.
	 *
	 * @param array  $answers    Map of qualifier_key => option_key.
	 * @param string $buyer_type Selected buyer type (adds its intent_weight * 5).
	 * @return int
	 */
	public function score( array $answers, $buyer_type = '' ) {
		$total = 0;
		foreach ( $this->matrix['qualifiers'] as $key => $q ) {
			$choice = $answers[ $key ] ?? '';
			if ( isset( $q['options'][ $choice ]['score'] ) ) {
				$total += (int) $q['options'][ $choice ]['score'];
			}
		}
		if ( isset( $this->matrix['buyer_types'][ $buyer_type ]['intent_weight'] ) ) {
			$total += (int) $this->matrix['buyer_types'][ $buyer_type ]['intent_weight'] * 5;
		}
		return $total;
	}

	/**
	 * Map a numeric score to a tier key (A/B/C).
	 *
	 * @param int $score
	 * @return string
	 */
	public function tier( $score ) {
		foreach ( $this->matrix['tiers'] as $key => $tier ) { // ordered high -> low
			if ( $score >= (int) $tier['min'] ) {
				return $key;
			}
		}
		return 'C';
	}

	/**
	 * The full decision. This is what /wp-json/braven/v1/route returns and what
	 * the routed step renders.
	 *
	 * @param array $selection {
	 *   @type string $buyer_type
	 *   @type string $track
	 *   @type array  $answers   qualifier_key => option_key
	 * }
	 * @return array Decision payload (never throws; invalid input degrades safely).
	 */
	public function decide( array $selection ) {
		$buyer_type = isset( $selection['buyer_type'] ) ? (string) $selection['buyer_type'] : '';
		$track      = isset( $selection['track'] ) ? (string) $selection['track'] : '';
		$answers    = isset( $selection['answers'] ) && is_array( $selection['answers'] ) ? $selection['answers'] : array();

		$bt = $this->matrix['buyer_types'][ $buyer_type ] ?? null;
		$tk = $this->matrix['tracks'][ $track ] ?? null;

		$score    = $this->score( $answers, $buyer_type );
		$tier_key = $this->tier( $score );

		$outcome_key = $this->resolve_outcome( $buyer_type, $track, $tier_key, $answers );
		$outcome     = $this->matrix['outcomes'][ $outcome_key ];

		return array(
			'buyer_type'       => $buyer_type,
			'buyer_type_label' => $bt['label'] ?? '',
			'track'            => $track,
			'track_label'      => $tk['label'] ?? '',
			'score'            => $score,
			'tier'             => $tier_key,
			'tier_label'       => $this->matrix['tiers'][ $tier_key ]['label'] ?? '',
			'outcome'          => $outcome_key,
			'priority'         => $outcome['priority'],
			'headline'         => $outcome['headline'],
			'sub'              => $outcome['sub'],
			'cta_label'        => $outcome['cta_label'],
			'action'           => $outcome['action'],
			'form_fields'      => $outcome['form_fields'],
			'value_prop'       => $bt['value_prop'] ?? '',
			'resource'         => $outcome['resource'] ?? '',
		);
	}

	/**
	 * First-match rule evaluation. Kept separate so the precedence is obvious and
	 * testable in isolation.
	 *
	 * @return string outcome key
	 */
	protected function resolve_outcome( $buyer_type, $track, $tier_key, array $answers ) {
		foreach ( $this->matrix['rules'] as $rule ) {
			if ( $this->rule_matches( $rule['when'], $buyer_type, $track, $tier_key, $answers ) ) {
				return $rule['outcome'];
			}
		}
		return 'nurture'; // safe default: never drop a lead on the floor.
	}

	/**
	 * @return bool
	 */
	protected function rule_matches( array $when, $buyer_type, $track, $tier_key, array $answers ) {
		if ( isset( $when['buyer_type_in'] ) && ! in_array( $buyer_type, $when['buyer_type_in'], true ) ) {
			return false;
		}
		if ( isset( $when['track_in'] ) && ! in_array( $track, $when['track_in'], true ) ) {
			return false;
		}
		if ( isset( $when['tier'] ) && $when['tier'] !== $tier_key ) {
			return false;
		}
		if ( isset( $when['funding_in'] ) ) {
			$funding = $answers['funding'] ?? '';
			if ( ! in_array( $funding, $when['funding_in'], true ) ) {
				return false;
			}
		}
		return true;
	}
}
