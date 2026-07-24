<?php
/**
 * Self-select wizard markup. Rendered server-side from the routing matrix so the
 * questions exist in the DOM before JS runs (SEO + resilience). JS (router.js)
 * handles step transitions, calls the /route + /lead REST endpoints, manages
 * focus, and pushes GA4 dataLayer events.
 *
 * Accessibility: each question is a <fieldset> with a <legend>; options are real
 * radio inputs inside a role="radiogroup"; step changes move focus and are
 * announced via aria-live. Fully keyboard operable; works with the mouse, too.
 *
 * @package Braven_Lead_Router
 * @var string $heading
 * @var string $intro
 * @var array  $config  buyer_types, tracks, qualifiers
 * @var string $logo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icons = array(
	'building'   => 'M3 21h18M6 21V7l6-4 6 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01',
	'map'        => 'M9 3 3 6v15l6-3 6 3 6-3V3l-6 3-6-3Zm0 0v15m6-12v15',
	'users'      => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
	'heart'      => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',
	'briefcase'  => 'M20 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2ZM16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2',
	'sparkles'   => 'M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M18 6l-2 2M8 16l-2 2',
	'trending-up'=> 'M23 6l-9.5 9.5-5-5L1 18M17 6h6v6',
	'share'      => 'M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm12 7a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8.6 13.5l6.8 4M15.4 6.5l-6.8 4',
	'layers'     => 'm12 2 10 5-10 5L2 7l10-5Zm10 10-10 5-10-5m20 5-10 5-10-5',
);
$icon_svg = static function ( $name ) use ( $icons ) {
	$d = $icons[ $name ] ?? $icons['sparkles'];
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . esc_attr( $d ) . '"/></svg>';
};
?>
<section class="blr" data-blr-root aria-labelledby="blr-title">
	<div class="blr__frame">

		<header class="blr__head">
			<img class="blr__logo" src="<?php echo esc_url( $logo ); ?>" alt="Braven Agency" width="180" height="54" loading="eager" decoding="async">
			<h2 class="blr__title" id="blr-title"><?php echo esc_html( $heading ); ?></h2>
			<p class="blr__intro"><?php echo esc_html( $intro ); ?></p>
		</header>

		<!-- Progress -->
		<ol class="blr__steps" aria-hidden="true" data-blr-progress>
			<li class="is-active" data-step="1"><span>1</span> You</li>
			<li data-step="2"><span>2</span> Your goal</li>
			<li data-step="3"><span>3</span> A few details</li>
			<li data-step="4"><span>4</span> Your path</li>
		</ol>

		<form class="blr__form" data-blr-form novalidate>

			<!-- STEP 1 — buyer type -->
			<fieldset class="blr__step is-active" data-step="1">
				<legend class="blr__legend">Which best describes your organization?</legend>
				<div class="blr__grid" role="radiogroup" aria-label="Organization type">
					<?php foreach ( $config['buyer_types'] as $slug => $bt ) : ?>
						<label class="blr__card">
							<input type="radio" name="buyer_type" value="<?php echo esc_attr( $slug ); ?>" required>
							<span class="blr__card-ic"><?php echo $icon_svg( $bt['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>
							<span class="blr__card-t"><?php echo esc_html( $bt['label'] ); ?></span>
							<span class="blr__card-b"><?php echo esc_html( $bt['blurb'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<!-- STEP 2 — track -->
			<fieldset class="blr__step" data-step="2" hidden>
				<legend class="blr__legend">What kind of program do you want to run?</legend>
				<div class="blr__grid" role="radiogroup" aria-label="Program track">
					<?php foreach ( $config['tracks'] as $slug => $tk ) : ?>
						<label class="blr__card">
							<input type="radio" name="track" value="<?php echo esc_attr( $slug ); ?>" required>
							<span class="blr__card-ic"><?php echo $icon_svg( $tk['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG. ?></span>
							<span class="blr__card-t"><?php echo esc_html( $tk['label'] ); ?></span>
							<span class="blr__card-b"><?php echo esc_html( $tk['blurb'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<!-- STEP 3 — qualifiers -->
			<fieldset class="blr__step" data-step="3" hidden>
				<legend class="blr__legend">A few details so we route you correctly</legend>
				<div class="blr__quals">
					<?php foreach ( $config['qualifiers'] as $qkey => $q ) : ?>
						<fieldset class="blr__qual">
							<legend class="blr__qual-q"><?php echo esc_html( $q['question'] ); ?></legend>
							<?php if ( ! empty( $q['help'] ) ) : ?>
								<p class="blr__qual-help"><?php echo esc_html( $q['help'] ); ?></p>
							<?php endif; ?>
							<div class="blr__pills" role="radiogroup" aria-label="<?php echo esc_attr( $q['question'] ); ?>">
								<?php foreach ( $q['options'] as $okey => $opt ) : ?>
									<label class="blr__pill">
										<input type="radio" name="answers[<?php echo esc_attr( $qkey ); ?>]" value="<?php echo esc_attr( $okey ); ?>" required>
										<span><?php echo esc_html( $opt['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<!-- STEP 4 — routed result (filled by JS) -->
			<fieldset class="blr__step blr__result" data-step="4" hidden>
				<div data-blr-result aria-live="polite"></div>
			</fieldset>

			<!-- Nav -->
			<div class="blr__nav">
				<button type="button" class="blr__btn blr__btn--ghost" data-blr-back hidden>← Back</button>
				<button type="button" class="blr__btn blr__btn--primary" data-blr-next>Continue →</button>
			</div>

			<p class="blr__live sr-only" role="status" aria-live="polite" data-blr-live></p>
		</form>
	</div>
	<noscript><p class="blr__noscript">This quick router needs JavaScript. Prefer to talk to a person? Call <a href="tel:+15628263995">(562) 826-3995</a>.</p></noscript>
</section>
