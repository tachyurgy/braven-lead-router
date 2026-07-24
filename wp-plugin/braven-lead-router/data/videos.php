<?php
/**
 * Seed catalog for the training-video repository (blr_video CPT).
 *
 * On activation these become real posts with `blr_track` + `blr_proficiency`
 * terms, so the library is populated and filterable out of the box. `url` is
 * left empty for the demo, so each card links to its real WordPress single-post
 * page (no broken external links); in production a marketer pastes the hosted
 * clip URL into the ACF/meta field and the card links out to it instead.
 *
 * @package Braven_Lead_Router
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$T_DM = 'Digital Marketing';
$T_SM = 'Social Media';
$T_AI = 'AI';
$P_B  = 'Beginner';
$P_I  = 'Intermediate';
$P_A  = 'Advanced';

return array(
	array( 'title' => 'What a Google Business Profile Actually Does', 'track' => $T_DM, 'proficiency' => $P_B, 'duration' => '6:12', 'order' => 1, 'excerpt' => 'The single highest-ROI first step for a local small business online.' ),
	array( 'title' => 'Your Homepage in One Sentence', 'track' => $T_DM, 'proficiency' => $P_B, 'duration' => '5:40', 'order' => 2, 'excerpt' => 'Write a headline a stranger understands in five seconds.' ),
	array( 'title' => 'Local SEO: Getting Found in Your City', 'track' => $T_DM, 'proficiency' => $P_I, 'duration' => '11:03', 'order' => 3, 'excerpt' => 'Citations, reviews, and the map pack — what moves the needle.' ),
	array( 'title' => 'Email That People Open', 'track' => $T_DM, 'proficiency' => $P_I, 'duration' => '9:22', 'order' => 4, 'excerpt' => 'Subject lines, segments, and a simple welcome sequence.' ),
	array( 'title' => 'Attribution Without a Data Team', 'track' => $T_DM, 'proficiency' => $P_A, 'duration' => '14:50', 'order' => 5, 'excerpt' => 'UTMs, GA4 events, and knowing which dollar worked.' ),
	array( 'title' => 'Paid Ads on a $10/Day Budget', 'track' => $T_DM, 'proficiency' => $P_A, 'duration' => '13:18', 'order' => 6, 'excerpt' => 'A disciplined test framework for tiny budgets.' ),

	array( 'title' => 'Pick One Platform and Win It', 'track' => $T_SM, 'proficiency' => $P_B, 'duration' => '5:05', 'order' => 7, 'excerpt' => 'Why spreading thin is the most common small-business mistake.' ),
	array( 'title' => 'Filming on Just Your Phone', 'track' => $T_SM, 'proficiency' => $P_B, 'duration' => '7:44', 'order' => 8, 'excerpt' => 'Light, sound, and framing with zero extra gear.' ),
	array( 'title' => 'A Week of Content in One Afternoon', 'track' => $T_SM, 'proficiency' => $P_I, 'duration' => '10:31', 'order' => 9, 'excerpt' => 'Batching, repurposing, and a simple content calendar.' ),
	array( 'title' => 'Turning Comments into Customers', 'track' => $T_SM, 'proficiency' => $P_I, 'duration' => '8:15', 'order' => 10, 'excerpt' => 'Community management that actually drives sales.' ),
	array( 'title' => 'Reading Your Analytics Like a Pro', 'track' => $T_SM, 'proficiency' => $P_A, 'duration' => '12:09', 'order' => 11, 'excerpt' => 'Reach vs. engagement vs. saves — what each really means.' ),

	array( 'title' => 'AI in Plain English for Small Business', 'track' => $T_AI, 'proficiency' => $P_B, 'duration' => '6:58', 'order' => 12, 'excerpt' => 'What these tools are, and three safe places to start today.' ),
	array( 'title' => 'Writing Better Prompts', 'track' => $T_AI, 'proficiency' => $P_B, 'duration' => '8:03', 'order' => 13, 'excerpt' => 'Give the model context, a role, and an example.' ),
	array( 'title' => 'An AI Assistant for Customer Replies', 'track' => $T_AI, 'proficiency' => $P_I, 'duration' => '11:47', 'order' => 14, 'excerpt' => 'Draft on-brand responses without losing your voice.' ),
	array( 'title' => 'Automating the Boring 20%', 'track' => $T_AI, 'proficiency' => $P_I, 'duration' => '10:12', 'order' => 15, 'excerpt' => 'Connect your forms, inbox, and CRM with simple automations.' ),
	array( 'title' => 'Keeping Your Data Safe with AI Tools', 'track' => $T_AI, 'proficiency' => $P_A, 'duration' => '13:33', 'order' => 16, 'excerpt' => 'Privacy, prompts, and what never to paste into a chatbot.' ),
	array( 'title' => 'Building a Custom GPT for Your Storefront', 'track' => $T_AI, 'proficiency' => $P_A, 'duration' => '15:20', 'order' => 17, 'excerpt' => 'A guided walkthrough from idea to a working assistant.' ),
);
