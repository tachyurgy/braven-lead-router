<?php
/**
 * BRAVEN LEAD-ROUTER — declarative routing matrix.
 *
 * This file is the single source of truth for the self-select flow. A marketer
 * can extend a buyer type, add a track, or re-point a destination WITHOUT
 * touching engine code — the engine (class-blr-routing-engine.php) just reads
 * this structure. It is plain PHP so OPcache serves it with zero DB hits.
 *
 * Shape:
 *   buyer_types[]   the first question — who the partner is
 *   tracks[]        the second question — what they want to run
 *   qualifiers[]    scored questions that establish intent
 *   outcomes[]      the destinations a lead can be routed to
 *   rules           how (buyer_type, track, intent tier) picks an outcome
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	/* ---------------------------------------------------------------------
	 * STEP 1 — Buyer type. Mirrors Braven's four institutional audiences.
	 * `intent_weight` nudges inherently higher-value segments upward.
	 * ------------------------------------------------------------------- */
	'buyer_types' => array(
		'city' => array(
			'label'         => 'City',
			'blurb'         => 'Economic & workforce development for your local small businesses.',
			'icon'          => 'building',
			'intent_weight' => 2,
			'value_prop'    => 'Turn ARPA / CDBG / general-fund dollars into measurable small-business digital growth and job creation your council can point to.',
		),
		'county' => array(
			'label'         => 'County',
			'blurb'         => 'Regional programs across multiple cities and districts.',
			'icon'          => 'map',
			'intent_weight' => 2,
			'value_prop'    => 'Run one accountable program across every municipality in the county, with district-level reporting for your board of supervisors.',
		),
		'chamber' => array(
			'label'         => 'Chamber / Business Org',
			'blurb'         => 'Member value, retention, and non-dues revenue.',
			'icon'          => 'users',
			'intent_weight' => 1,
			'value_prop'    => 'Give members a tangible AI & digital-marketing benefit that drives renewals and opens sponsorship revenue.',
		),
		'foundation' => array(
			'label'         => 'Foundation / Funder',
			'blurb'         => 'Grantee outcomes and impact you can report to your board.',
			'icon'          => 'heart',
			'intent_weight' => 2,
			'value_prop'    => 'Fund a turnkey program with clean impact reporting — graduates, revenue lift, and jobs — mapped to your theory of change.',
		),
		'corporate' => array(
			'label'         => 'Corporate / Employer',
			'blurb'         => 'Community investment & supplier-diversity programs.',
			'icon'          => 'briefcase',
			'intent_weight' => 1,
			'value_prop'    => 'Deliver a branded community-investment or supplier-diversity program with the ESG metrics your stakeholders expect.',
		),
	),

	/* ---------------------------------------------------------------------
	 * STEP 2 — Training track. Braven's three program pillars, plus a
	 * "full program" option for partners who want the whole curriculum.
	 * ------------------------------------------------------------------- */
	'tracks' => array(
		'ai' => array(
			'label' => 'AI Enablement & Resilience',
			'blurb' => 'Practical AI adoption for small businesses.',
			'icon'  => 'sparkles',
		),
		'digital_marketing' => array(
			'label' => 'Digital Marketing',
			'blurb' => 'SEO, web, paid, and email that converts.',
			'icon'  => 'trending-up',
		),
		'social_media' => array(
			'label' => 'Social Media',
			'blurb' => 'Content, organic growth, and community.',
			'icon'  => 'share',
		),
		'full_program' => array(
			'label' => 'Full Program (all three)',
			'blurb' => 'A complete cohort across every track.',
			'icon'  => 'layers',
		),
	),

	/* ---------------------------------------------------------------------
	 * STEP 3 — Qualifiers. Each answer carries a `score`; the sum places the
	 * lead in an intent TIER (A/B/C). This is the transparent, auditable core
	 * of the "how do you architect the conditional logic" screening question.
	 * ------------------------------------------------------------------- */
	'qualifiers' => array(
		'funding' => array(
			'question' => 'Where are you with funding?',
			'help'     => 'This helps us route you to the right next step, not to disqualify you.',
			'options'  => array(
				'funded'    => array( 'label' => 'Funded & ready to launch', 'score' => 40 ),
				'budgeted'  => array( 'label' => 'Budgeted for this cycle', 'score' => 25 ),
				'seeking'   => array( 'label' => 'Seeking / applying for funding', 'score' => 10 ),
				'exploring' => array( 'label' => 'Just exploring options', 'score' => 0 ),
			),
		),
		'timeline' => array(
			'question' => 'When would the program start?',
			'options'  => array(
				'quarter'   => array( 'label' => 'This quarter', 'score' => 30 ),
				'half'      => array( 'label' => 'Within 6 months', 'score' => 20 ),
				'year'      => array( 'label' => 'This year', 'score' => 10 ),
				'unsure'    => array( 'label' => 'Not sure yet', 'score' => 0 ),
			),
		),
		'role' => array(
			'question' => 'What is your role in the decision?',
			'options'  => array(
				'decision'   => array( 'label' => 'I approve the budget', 'score' => 20 ),
				'recommend'  => array( 'label' => 'I recommend / influence it', 'score' => 12 ),
				'research'   => array( 'label' => 'I am researching for my team', 'score' => 4 ),
			),
		),
		'scale' => array(
			'question' => 'How many businesses would you train?',
			'options'  => array(
				'xl'  => array( 'label' => '500+', 'score' => 20 ),
				'l'   => array( 'label' => '100–500', 'score' => 14 ),
				'm'   => array( 'label' => '25–100', 'score' => 8 ),
				's'   => array( 'label' => 'Under 25', 'score' => 3 ),
			),
		),
	),

	/* ---------------------------------------------------------------------
	 * Intent tiers. `min` is the inclusive lower score bound (highest first).
	 * ------------------------------------------------------------------- */
	'tiers' => array(
		'A' => array( 'min' => 70, 'label' => 'High intent' ),
		'B' => array( 'min' => 35, 'label' => 'Qualified' ),
		'C' => array( 'min' => 0,  'label' => 'Nurture' ),
	),

	/* ---------------------------------------------------------------------
	 * Outcomes — where a routed lead lands. `destination` is resolved at
	 * render time against plugin settings (booking URL, form, lead magnet).
	 * ------------------------------------------------------------------- */
	'outcomes' => array(
		'book_call' => array(
			'headline'    => 'Let’s design your program',
			'sub'         => 'You’re ready to move — grab a 30-minute program-design call with our team.',
			'cta_label'   => 'Book your program-design call',
			'action'      => 'booking',        // resolves to settings booking_url
			'form_fields' => array( 'name', 'organization', 'title', 'email', 'phone' ),
			'priority'    => 'hot',
		),
		'request_proposal' => array(
			'headline'    => 'Get a tailored program proposal',
			'sub'         => 'Tell us a little more and we’ll send a scoped proposal with pricing and outcomes.',
			'cta_label'   => 'Request my proposal',
			'action'      => 'intake',         // full intake form
			'form_fields' => array( 'name', 'organization', 'title', 'email', 'phone', 'goals' ),
			'priority'    => 'warm',
		),
		'funding_partnership' => array(
			'headline'    => 'Let’s build a fundable program',
			'sub'         => 'We partner with funders and applicants to structure programs that win grants and report clean impact.',
			'cta_label'   => 'Start a funding conversation',
			'action'      => 'intake',
			'form_fields' => array( 'name', 'organization', 'title', 'email', 'phone', 'goals' ),
			'priority'    => 'warm',
			'resource'    => 'funding_playbook', // also offers the funding playbook download
		),
		'nurture' => array(
			'headline'    => 'Here’s where to start',
			'sub'         => 'Get the program overview and see outcomes from past cohorts. We’ll follow up when you’re ready.',
			'cta_label'   => 'Send me the program overview',
			'action'      => 'lead_magnet',    // resolves to settings lead_magnet_url
			'form_fields' => array( 'name', 'organization', 'email' ),
			'priority'    => 'cool',
		),
	),

	/*
	 * Rule resolution order (first match wins), evaluated by the engine:
	 *   1. Foundations/funders in a non-committed funding posture   -> funding_partnership
	 *   2. Tier A                                                    -> book_call
	 *   3. Tier B                                                    -> request_proposal
	 *   4. Tier C                                                    -> nurture
	 * The engine also always attaches the buyer-type value_prop + track label so
	 * the routed view is tailored copy, never a generic form.
	 */
	'rules' => array(
		array( 'when' => array( 'buyer_type_in' => array( 'foundation' ), 'funding_in' => array( 'seeking', 'exploring' ) ), 'outcome' => 'funding_partnership' ),
		array( 'when' => array( 'tier' => 'A' ), 'outcome' => 'book_call' ),
		array( 'when' => array( 'tier' => 'B' ), 'outcome' => 'request_proposal' ),
		array( 'when' => array( 'tier' => 'C' ), 'outcome' => 'nurture' ),
	),
);
