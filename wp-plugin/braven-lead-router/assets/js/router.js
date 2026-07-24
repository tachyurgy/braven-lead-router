/**
 * Braven Lead Router — front-end controller.
 *
 * Progressive enhancement over the server-rendered wizard (templates/router.php):
 *  - step transitions with focus management + aria-live announcements
 *  - POST /braven/v1/route  -> tailored routed view (no PII yet)
 *  - POST /braven/v1/lead   -> capture (CPT + CRM + email + GA4)
 *  - GA4 dataLayer events at every funnel step (client side; the server fires a
 *    redundant server-side generate_lead so ad-blockers can't hide conversions)
 *
 * Dependency-free (no jQuery), ~7KB, deferred. All markup escaped.
 */
(function () {
	'use strict';

	var boot = window.BLR_BOOT || {};
	var root = document.querySelector('[data-blr-root]');
	if (!root || !boot.restUrl) { return; }

	var form = root.querySelector('[data-blr-form]');
	var live = root.querySelector('[data-blr-live]');
	var progress = root.querySelector('[data-blr-progress]');
	var backBtn = root.querySelector('[data-blr-back]');
	var nextBtn = root.querySelector('[data-blr-next]');
	var resultBox = root.querySelector('[data-blr-result]');
	var steps = Array.prototype.slice.call(form.querySelectorAll('.blr__step'));

	var state = { step: 1, buyerType: '', track: '', answers: {}, decision: null };
	var TOTAL_SELECT_STEPS = 3; // steps 1-3 collect; step 4 is the routed result

	/* -------- utilities -------- */
	function dl(event, data) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push(Object.assign({ event: event }, data || {}));
	}
	function cookie(name) {
		var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
		return m ? m.pop() : '';
	}
	function gaClientId() {
		// _ga cookie looks like GA1.1.1234567890.1680000000 -> "1234567890.1680000000"
		var raw = cookie('_ga');
		if (!raw) { return ''; }
		var parts = raw.split('.');
		return parts.length >= 4 ? parts[2] + '.' + parts[3] : '';
	}
	function utms() {
		var p = new URLSearchParams(window.location.search), out = {};
		['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'].forEach(function (k) {
			if (p.get(k)) { out[k] = p.get(k); }
		});
		return out;
	}
	function announce(msg) { if (live) { live.textContent = msg; } }
	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

	/* -------- step machine -------- */
	function showStep(n) {
		state.step = n;
		steps.forEach(function (s) {
			var is = parseInt(s.getAttribute('data-step'), 10) === n;
			s.hidden = !is;
			s.classList.toggle('is-active', is);
		});
		if (progress) {
			Array.prototype.forEach.call(progress.children, function (li) {
				var sn = parseInt(li.getAttribute('data-step'), 10);
				li.classList.toggle('is-active', sn === n);
				li.classList.toggle('is-done', sn < n);
			});
		}
		backBtn.hidden = n === 1;
		nextBtn.hidden = n === 4; // step 4 has its own submit button
		if (n === 3) { nextBtn.textContent = 'See my path →'; }
		else if (n < 3) { nextBtn.textContent = 'Continue →'; }

		var legend = steps[n - 1] && steps[n - 1].querySelector('.blr__legend, [data-blr-result]');
		var focusTarget = steps[n - 1] && (steps[n - 1].querySelector('input, a, button') || legend);
		if (focusTarget && focusTarget.focus) { setTimeout(function () { focusTarget.focus({ preventScroll: false }); }, 30); }
		root.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function readStep(n) {
		if (n === 1) {
			var b = form.querySelector('input[name="buyer_type"]:checked');
			return b ? (state.buyerType = b.value) : '';
		}
		if (n === 2) {
			var t = form.querySelector('input[name="track"]:checked');
			return t ? (state.track = t.value) : '';
		}
		if (n === 3) {
			state.answers = {};
			var groups = form.querySelectorAll('.blr__qual');
			var complete = true;
			Array.prototype.forEach.call(groups, function (g) {
				var checked = g.querySelector('input:checked');
				if (checked) {
					var m = checked.name.match(/answers\[(.+?)\]/);
					if (m) { state.answers[m[1]] = checked.value; }
				} else { complete = false; }
			});
			return complete ? 'ok' : '';
		}
		return 'ok';
	}

	function invalidPulse(n) {
		var step = steps[n - 1];
		step.classList.remove('blr__step--shake');
		void step.offsetWidth; // reflow to restart animation
		step.classList.add('blr__step--shake');
		announce('Please make a selection to continue.');
	}

	/* -------- navigation -------- */
	nextBtn.addEventListener('click', function () {
		var val = readStep(state.step);
		if (!val) { invalidPulse(state.step); return; }

		if (state.step === 1) { dl('blr_buyer_type_selected', { buyer_type: state.buyerType }); }
		if (state.step === 2) { dl('blr_track_selected', { track: state.track }); }

		if (state.step < TOTAL_SELECT_STEPS) {
			showStep(state.step + 1);
		} else {
			dl('blr_qualifiers_answered', { answers: state.answers });
			fetchRoute();
		}
	});

	backBtn.addEventListener('click', function () {
		if (state.step > 1) { showStep(state.step - 1); }
	});

	// Auto-advance select steps on choice (kept snappy but not on qualifiers).
	form.addEventListener('change', function (e) {
		if (e.target.name === 'buyer_type' && state.step === 1) { setTimeout(function () { nextBtn.click(); }, 180); }
		if (e.target.name === 'track' && state.step === 2) { setTimeout(function () { nextBtn.click(); }, 180); }
	});

	/* -------- routing -------- */
	function fetchRoute() {
		nextBtn.disabled = true;
		announce('Finding your path…');
		fetch(boot.restUrl + '/route', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ buyer_type: state.buyerType, track: state.track, answers: state.answers })
		}).then(function (r) { return r.json(); }).then(function (res) {
			nextBtn.disabled = false;
			if (!res || !res.decision) { throw new Error('no decision'); }
			state.decision = res.decision;
			dl('blr_lead_routed', {
				buyer_type: res.decision.buyer_type, track: res.decision.track,
				intent_tier: res.decision.tier, outcome: res.decision.outcome
			});
			renderResult(res.decision);
			showStep(4);
		}).catch(function () {
			nextBtn.disabled = false;
			announce('Something went wrong. Please call (562) 826-3995.');
		});
	}

	/* -------- routed view + contact form -------- */
	var FIELD_LABELS = {
		name: 'Your name', organization: 'Organization', title: 'Your title',
		email: 'Work email', phone: 'Phone', goals: 'What are you hoping to achieve?'
	};
	var FIELD_TYPES = { email: 'email', phone: 'tel', goals: 'textarea' };

	function renderResult(d) {
		var tierClass = 'is-' + (d.priority || 'warm');
		var fields = (d.form_fields || ['name', 'organization', 'email']).map(function (f) {
			var label = FIELD_LABELS[f] || f;
			var req = (f === 'title' || f === 'goals') ? '' : ' required';
			if (FIELD_TYPES[f] === 'textarea') {
				return '<label class="blr__field"><span>' + esc(label) + '</span>' +
					'<textarea name="' + esc(f) + '" rows="3"' + req + '></textarea></label>';
			}
			return '<label class="blr__field"><span>' + esc(label) + (req ? ' *' : '') + '</span>' +
				'<input type="' + (FIELD_TYPES[f] || 'text') + '" name="' + esc(f) + '"' + req + ' autocomplete="' + autoc(f) + '"></label>';
		}).join('');

		resultBox.innerHTML =
			'<div class="blr__routed ' + tierClass + '">' +
				'<span class="blr__routed-tag">' + esc(d.buyer_type_label) + ' · ' + esc(d.track_label) + '</span>' +
				'<h3 class="blr__routed-h">' + esc(d.headline) + '</h3>' +
				(d.value_prop ? '<p class="blr__routed-vp">' + esc(d.value_prop) + '</p>' : '') +
				'<p class="blr__routed-sub">' + esc(d.sub) + '</p>' +
				'<div class="blr__routed-form">' + fields +
					consentRow() +
					'<div class="blr__hp"><label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>' +
					'<p class="blr__err" data-blr-err hidden></p>' +
					'<button type="button" class="blr__btn blr__btn--primary blr__btn--wide" data-blr-submit>' + esc(d.cta_label) + '</button>' +
				'</div>' +
			'</div>';

		resultBox.querySelector('[data-blr-submit]').addEventListener('click', submitLead);
	}
	function autoc(f) {
		return { name: 'name', organization: 'organization', title: 'organization-title', email: 'email', phone: 'tel' }[f] || 'on';
	}
	function consentRow() {
		var privacy = boot.settings && boot.settings.privacyUrl;
		var link = privacy ? ' <a href="' + esc(privacy) + '" target="_blank" rel="noopener">Privacy Policy</a>' : '';
		return '<label class="blr__consent"><input type="checkbox" name="consent" value="1" required>' +
			'<span>I agree to be contacted by Braven Agency about my program.' + link + '</span></label>';
	}

	/* -------- capture -------- */
	function submitLead() {
		var errEl = resultBox.querySelector('[data-blr-err]');
		var btn = resultBox.querySelector('[data-blr-submit]');
		errEl.hidden = true;

		var payload = {
			buyer_type: state.buyerType, track: state.track, answers: state.answers,
			ga_client_id: gaClientId(), utm: utms(),
			page_url: window.location.href, source: 'braven-lead-router'
		};
		resultBox.querySelectorAll('input, textarea').forEach(function (el) {
			if (el.type === 'checkbox') { payload[el.name] = el.checked ? 1 : 0; }
			else { payload[el.name] = el.value; }
		});

		// Light client validation for a fast error; server re-validates authoritatively.
		if (!payload.consent) { return showError(errEl, 'Please agree to be contacted.'); }
		if ('email' in payload && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email || '')) {
			return showError(errEl, 'Please enter a valid email.');
		}

		btn.disabled = true; btn.textContent = 'Sending…';
		fetch(boot.restUrl + '/lead', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': boot.nonce },
			body: JSON.stringify(payload)
		}).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
		.then(function (res) {
			btn.disabled = false;
			if (res.status === 201 && res.body.ok) {
				dl('blr_lead_submitted', {
					buyer_type: state.buyerType, track: state.track,
					intent_tier: state.decision.tier, outcome: state.decision.outcome, lead_id: res.body.lead_id
				});
				renderSuccess(res.body);
			} else if (res.body && res.body.errors) {
				var first = Object.keys(res.body.errors)[0];
				showError(errEl, res.body.errors[first] || 'Please check your details.');
				btn.textContent = state.decision.cta_label;
			} else {
				showError(errEl, 'Something went wrong. Please call (562) 826-3995.');
				btn.textContent = state.decision.cta_label;
			}
		}).catch(function () {
			btn.disabled = false; btn.textContent = state.decision.cta_label;
			showError(errEl, 'Network error. Please call (562) 826-3995.');
		});
	}
	function showError(el, msg) { el.textContent = msg; el.hidden = false; announce(msg); }

	function renderSuccess(body) {
		var d = state.decision, dest = body.destination || {};
		var cta = '';
		if (dest.type === 'booking' && dest.url) {
			cta = '<a class="blr__btn blr__btn--primary blr__btn--wide" href="' + esc(dest.url) + '" target="_blank" rel="noopener">Choose a time →</a>';
		} else if (dest.type === 'download' && dest.url) {
			cta = '<a class="blr__btn blr__btn--primary blr__btn--wide" href="' + esc(dest.url) + '" target="_blank" rel="noopener">Download the program overview →</a>';
		}
		resultBox.innerHTML =
			'<div class="blr__done">' +
				'<div class="blr__check" aria-hidden="true">✓</div>' +
				'<h3>You’re all set.</h3>' +
				'<p>' + esc(successLine(d)) + '</p>' + cta +
				'<p class="blr__done-small">A member of the Braven team will follow up within one business day.</p>' +
			'</div>';
		announce('Submitted. ' + successLine(d));
		resultBox.querySelector('.blr__done').focus && resultBox.querySelector('.blr__done').setAttribute('tabindex', '-1');
	}
	function successLine(d) {
		switch (d.outcome) {
			case 'book_call': return 'Grab a time below and we’ll design your program together.';
			case 'request_proposal': return 'We’ll send a tailored proposal with pricing and outcomes shortly.';
			case 'funding_partnership': return 'We’ll reach out to start structuring a fundable program.';
			default: return 'We’ll send your program overview and stay in touch.';
		}
	}

	/* -------- init -------- */
	dl('blr_router_start', {});
	showStep(1);
})();
