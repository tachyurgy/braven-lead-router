/**
 * Braven Video Library — client-side filter (track + level + search).
 *
 * No AJAX. Every card is already in the DOM (server-rendered from the blr_video
 * CPT); we just toggle visibility. This keeps the "searchable library" instant
 * and PageSpeed-friendly — the whole interaction is show/hide over cached DOM.
 */
(function () {
	'use strict';
	var root = document.querySelector('[data-blr-vid]');
	if (!root) { return; }

	var grid = root.querySelector('[data-blr-vid-grid]');
	var cards = Array.prototype.slice.call(grid.querySelectorAll('.blr-vid__card'));
	var empty = root.querySelector('[data-blr-vid-empty]');
	var count = root.querySelector('[data-blr-vid-count]');
	var search = root.querySelector('[data-blr-vid-search]');
	var state = { track: '', level: '', q: '' };

	function apply() {
		var q = state.q.trim().toLowerCase();
		var shown = 0;
		cards.forEach(function (card) {
			var tracks = (card.getAttribute('data-tracks') || '').split(' ');
			var levels = (card.getAttribute('data-levels') || '').split(' ');
			var title = card.getAttribute('data-title') || '';
			var ok =
				(!state.track || tracks.indexOf(state.track) !== -1) &&
				(!state.level || levels.indexOf(state.level) !== -1) &&
				(!q || title.indexOf(q) !== -1);
			card.style.display = ok ? '' : 'none';
			if (ok) { shown++; }
		});
		empty.hidden = shown !== 0;
		count.textContent = shown + (shown === 1 ? ' lesson' : ' lessons');
	}

	root.querySelectorAll('.blr-vid__chips').forEach(function (group) {
		var dim = group.getAttribute('data-filter'); // 'track' | 'level'
		group.addEventListener('click', function (e) {
			var btn = e.target.closest('.blr-vid__chip');
			if (!btn) { return; }
			group.querySelectorAll('.blr-vid__chip').forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
			btn.setAttribute('aria-pressed', 'true');
			state[dim] = btn.getAttribute('data-value') || '';
			apply();
		});
	});

	if (search) {
		var t;
		search.addEventListener('input', function () {
			clearTimeout(t);
			t = setTimeout(function () { state.q = search.value; apply(); }, 120);
		});
	}
})();
