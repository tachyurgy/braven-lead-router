<?php
/**
 * Pure-PHP tests for the Braven Lead Router decision core.
 * Run: php tests/test-routing.php   (exit 0 = all pass)
 *
 * @package Braven_Lead_Router
 */

require __DIR__ . '/wp-stubs.php';

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  \033[32m✓\033[0m $label\n"; }
	else { $fail++; echo "  \033[31m✗ $label\033[0m\n"; }
}

echo "\nBLR_Routing_Engine\n";
$engine = new BLR_Routing_Engine();

// config shape
$cfg = $engine->config();
ok( isset( $cfg['buyer_types']['city'], $cfg['tracks']['ai'], $cfg['qualifiers']['funding'] ), 'config() exposes buyer_types, tracks, qualifiers' );
ok( count( $cfg['buyer_types'] ) === 5, 'five buyer types (city/county/chamber/foundation/corporate)' );

// scoring
$hot = array( 'funding' => 'funded', 'timeline' => 'quarter', 'role' => 'decision', 'scale' => 'xl' );
ok( $engine->score( $hot, 'city' ) === 40 + 30 + 20 + 20 + 10, 'hot answers + city weight score to 120' );
$cold = array( 'funding' => 'exploring', 'timeline' => 'unsure', 'role' => 'research', 'scale' => 's' );
ok( $engine->score( $cold, 'chamber' ) === 0 + 0 + 4 + 3 + 5, 'cold answers + chamber weight score to 12' );

// tiers
ok( $engine->tier( 120 ) === 'A', 'score 120 -> tier A' );
ok( $engine->tier( 40 ) === 'B', 'score 40 -> tier B' );
ok( $engine->tier( 12 ) === 'C', 'score 12 -> tier C' );
ok( $engine->tier( 70 ) === 'A', 'boundary 70 -> tier A' );
ok( $engine->tier( 35 ) === 'B', 'boundary 35 -> tier B' );

// decisions
$d1 = $engine->decide( array( 'buyer_type' => 'city', 'track' => 'ai', 'answers' => $hot ) );
ok( $d1['outcome'] === 'book_call' && $d1['tier'] === 'A', 'high-intent city/AI -> book_call' );
ok( $d1['priority'] === 'hot', 'book_call outcome carries hot priority' );
ok( $d1['value_prop'] !== '' && $d1['track_label'] === 'AI Enablement & Resilience', 'decision attaches tailored copy + track label' );
ok( in_array( 'phone', $d1['form_fields'], true ), 'book_call collects phone' );

// budgeted(25)+year(10)+recommend(12)+small(3)=50, +county weight(10)=60 -> tier B
$mid = array( 'funding' => 'budgeted', 'timeline' => 'year', 'role' => 'recommend', 'scale' => 's' );
$d2  = $engine->decide( array( 'buyer_type' => 'county', 'track' => 'digital_marketing', 'answers' => $mid ) );
ok( $d2['score'] === 60 && $d2['tier'] === 'B' && $d2['outcome'] === 'request_proposal', 'mid-intent county (score 60) -> request_proposal (tier B)' );

$d3 = $engine->decide( array( 'buyer_type' => 'chamber', 'track' => 'social_media', 'answers' => $cold ) );
ok( $d3['outcome'] === 'nurture' && $d3['tier'] === 'C', 'cold chamber -> nurture (tier C)' );

// foundation special-case rule beats tier
$fnd = $engine->decide( array( 'buyer_type' => 'foundation', 'track' => 'ai', 'answers' => array( 'funding' => 'seeking', 'timeline' => 'quarter', 'role' => 'decision', 'scale' => 'xl' ) ) );
ok( $fnd['outcome'] === 'funding_partnership', 'foundation + seeking funding -> funding_partnership (rule beats tier A)' );
ok( $fnd['resource'] === 'funding_playbook', 'funding_partnership offers the funding playbook resource' );

// robustness: garbage never throws, always routes somewhere
$junk = $engine->decide( array( 'buyer_type' => 'martians', 'track' => '', 'answers' => array() ) );
ok( isset( $junk['outcome'] ) && $junk['outcome'] === 'nurture', 'invalid input degrades to nurture, never throws' );

echo "\nBLR_Lead_Validator\n";
$v = new BLR_Lead_Validator();

$good = $v->validate( array( 'name' => 'Jane Rivera', 'organization' => 'City of Long Beach', 'email' => 'jane@longbeach.gov', 'phone' => '5620001111', 'consent' => 1 ), array( 'name', 'organization', 'email', 'phone' ) );
ok( $good['ok'] === true && $good['lead']['email'] === 'jane@longbeach.gov', 'valid submission passes + normalises' );

$bad = $v->validate( array( 'name' => 'X', 'organization' => 'Y', 'email' => 'not-an-email', 'consent' => 1 ), array( 'name', 'organization', 'email' ) );
ok( $bad['ok'] === false && isset( $bad['errors']['email'] ), 'invalid email is rejected' );

$noconsent = $v->validate( array( 'name' => 'X', 'organization' => 'Y', 'email' => 'a@b.com' ), array( 'name', 'email' ) );
ok( isset( $noconsent['errors']['consent'] ), 'missing consent is rejected' );

$spam = $v->validate( array( 'name' => 'Bot', 'email' => 'bot@spam.com', 'consent' => 1, 'company_website' => 'http://spam' ), array( 'name', 'email' ) );
ok( isset( $spam['errors']['_spam'] ), 'honeypot catches bots' );

$xss = $v->validate( array( 'name' => "<script>alert(1)</script>Jane", 'organization' => 'Org', 'email' => 'j@x.com', 'consent' => 1 ), array( 'name', 'email' ) );
ok( strpos( $xss['lead']['name'], '<script>' ) === false, 'script tags are stripped from input' );

echo "\nBLR_Webhook_Dispatcher (payload)\n";
$disp = new BLR_Webhook_Dispatcher( 'https://crm.example/hook', 'shhh', 3, function () {} );
$lead = array( 'name' => 'Jane', 'organization' => 'City of LB', 'email' => 'jane@lb.gov', 'buyer_type' => 'city', 'track' => 'ai', 'consent' => 1 );
$payload = $disp->build_payload( $lead, $d1, 42 );
ok( isset( $payload['idempotency_key'] ) && strlen( $payload['idempotency_key'] ) === 32, 'payload carries a 32-char idempotency key' );
ok( $payload['segmentation']['intent_tier'] === 'A' && $payload['contact']['email'] === 'jane@lb.gov', 'payload maps segmentation + contact for the CRM' );
$k1 = blr_idempotency_key( $lead );
$k2 = blr_idempotency_key( $lead );
ok( $k1 === $k2, 'idempotency key is stable for identical input within the hour' );

echo "\n" . ( $fail === 0 ? "\033[32mALL $pass PASSED\033[0m" : "\033[31m$fail FAILED\033[0m ($pass passed)" ) . "\n\n";
exit( $fail === 0 ? 0 : 1 );
