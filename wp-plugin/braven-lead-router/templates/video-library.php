<?php
/**
 * Categorized training-video repository. All videos are printed into the DOM
 * once (server-rendered from the blr_video CPT); filtering is pure client-side
 * show/hide — no AJAX, no reload, no per-keystroke query. That is the concrete
 * answer to "build a multi-tiered searchable library without tanking page load":
 * indexed taxonomy terms, one cached WP_Query, static-friendly markup.
 *
 * @package Braven_Lead_Router
 * @var array  $videos
 * @var array  $tracks  [ ['slug','name','count'], ... ]
 * @var array  $levels
 * @var string $heading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="blr-vid" data-blr-vid>
	<div class="blr-vid__head">
		<h2><?php echo esc_html( $heading ); ?></h2>
		<p>Filter by track and level to find the right lesson for your businesses.</p>
	</div>

	<div class="blr-vid__filters">
		<div class="blr-vid__fgroup">
			<span>Track</span>
			<div class="blr-vid__chips" data-filter="track">
				<button type="button" class="blr-vid__chip" data-value="" aria-pressed="true">All</button>
				<?php foreach ( $tracks as $t ) : ?>
					<button type="button" class="blr-vid__chip" data-value="<?php echo esc_attr( $t['slug'] ); ?>" aria-pressed="false"><?php echo esc_html( $t['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="blr-vid__fgroup">
			<span>Level</span>
			<div class="blr-vid__chips" data-filter="level">
				<button type="button" class="blr-vid__chip" data-value="" aria-pressed="true">All</button>
				<?php foreach ( $levels as $l ) : ?>
					<button type="button" class="blr-vid__chip" data-value="<?php echo esc_attr( $l['slug'] ); ?>" aria-pressed="false"><?php echo esc_html( $l['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="blr-vid__search">
			<label for="blr-vid-q">Search</label>
			<input type="search" id="blr-vid-q" data-blr-vid-search placeholder="Search lessons…" autocomplete="off">
		</div>
	</div>

	<p class="blr-vid__count" data-blr-vid-count aria-live="polite"><?php echo count( $videos ); ?> lessons</p>

	<div class="blr-vid__grid" data-blr-vid-grid>
		<?php foreach ( $videos as $v ) : ?>
			<a class="blr-vid__card"
				href="<?php echo esc_url( $v['url'] ); ?>"
				<?php echo $v['external'] ? 'target="_blank" rel="noopener"' : ''; ?>
				data-tracks="<?php echo esc_attr( implode( ' ', $v['tracks'] ) ); ?>"
				data-levels="<?php echo esc_attr( implode( ' ', $v['levels'] ) ); ?>"
				data-title="<?php echo esc_attr( strtolower( $v['title'] . ' ' . $v['excerpt'] ) ); ?>">
				<div class="blr-vid__thumb">
					<span class="blr-vid__play" aria-hidden="true">▶</span>
					<?php if ( $v['duration'] ) : ?><span class="blr-vid__dur"><?php echo esc_html( $v['duration'] ); ?></span><?php endif; ?>
				</div>
				<div class="blr-vid__body">
					<div class="blr-vid__terms">
						<?php foreach ( $v['tracks'] as $slug ) : ?>
							<span class="blr-vid__term"><?php echo esc_html( blr_term_name( $slug, $tracks ) ); ?></span>
						<?php endforeach; ?>
						<?php foreach ( $v['levels'] as $slug ) : ?>
							<span class="blr-vid__term" style="color:#6f6c67;background:#f2efe9"><?php echo esc_html( blr_term_name( $slug, $levels ) ); ?></span>
						<?php endforeach; ?>
					</div>
					<h3 class="blr-vid__t"><?php echo esc_html( $v['title'] ); ?></h3>
					<?php if ( $v['excerpt'] ) : ?><p class="blr-vid__ex"><?php echo esc_html( $v['excerpt'] ); ?></p><?php endif; ?>
				</div>
			</a>
		<?php endforeach; ?>
		<p class="blr-vid__empty" data-blr-vid-empty hidden>No lessons match those filters yet.</p>
	</div>
</section>
