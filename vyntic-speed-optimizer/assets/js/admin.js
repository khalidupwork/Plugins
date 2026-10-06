/* Vyntic Speed Optimizer: admin */
(function () {
	'use strict';
	var cfg = window.vsoAdmin || {};
	var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || 'Request failed');
				}
				return json.data;
			});
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = String(s);
		return d.innerHTML;
	}

	/* ---------- PageSpeed ---------- */
	function grade(score) {
		return score >= 0.9 ? 'vso-good' : (score >= 0.5 ? 'vso-avg' : 'vso-poor');
	}
	function color(score) {
		return score >= 90 ? '#0c9b4f' : (score >= 50 ? '#e28a00' : '#d63638');
	}
	function scoreCard(label, result) {
		var r = 42, c = 2 * Math.PI * r, off = c * (1 - result.score / 100);
		var metrics = Object.keys(result.metrics || {}).map(function (k) {
			var m = result.metrics[k];
			return '<div class="vso-metric"><span>' + esc(k) + '</span><strong class="' + grade(m.score) + '">' + esc(m.value) + '</strong></div>';
		}).join('');
		return '<div class="vso-score"><div class="vso-gauge"><svg width="96" height="96"><circle cx="48" cy="48" r="' + r + '" fill="none" stroke="#f0f0f1" stroke-width="8"/>' +
			'<circle cx="48" cy="48" r="' + r + '" fill="none" stroke="' + color(result.score) + '" stroke-width="8" stroke-linecap="round" stroke-dasharray="' + c + '" stroke-dashoffset="' + off + '"/></svg>' +
			'<b style="color:' + color(result.score) + '">' + result.score + '</b></div><div><h4>' + esc(label) + '</h4><div class="vso-metrics">' + metrics + '</div></div></div>';
	}

	var results = $('#vso-psi-results');
	var status = $('#vso-psi-status');
	function renderScores(data) {
		var html = '';
		if (data.mobile) { html += scoreCard('Mobile', data.mobile); }
		if (data.desktop) { html += scoreCard('Desktop', data.desktop); }
		results.innerHTML = html;
	}
	if (results) {
		try {
			var last = JSON.parse(results.getAttribute('data-last') || '{}');
			if (last && (last.mobile || last.desktop)) { renderScores(last); }
		} catch (e) {}
	}
	var runBtn = $('#vso-psi-run');
	if (runBtn) {
		runBtn.addEventListener('click', function () {
			var url = $('#vso-psi-url').value || cfg.home;
			var data = {};
			runBtn.disabled = true;
			status.textContent = cfg.i18n.testing;
			results.innerHTML = '';
			post('vso_psi', { url: url, strategy: 'mobile' })
				.then(function (m) { data.mobile = m; renderScores(data); return post('vso_psi', { url: url, strategy: 'desktop' }); })
				.then(function (d) { data.desktop = d; renderScores(data); status.textContent = ''; })
				.catch(function (err) { status.textContent = cfg.i18n.error + ' ' + err.message; })
				.then(function () { runBtn.disabled = false; });
		});
	}

	/* ---------- WebP bulk ---------- */
	var webpBtn = $('#vso-webp-run');
	if (webpBtn) {
		webpBtn.addEventListener('click', function () {
			var bar = $('#vso-webp-bulk .vso-progress span');
			var text = $('#vso-webp-text');
			webpBtn.disabled = true;
			(function loop() {
				post('vso_webp_batch', {}).then(function (s) {
					var pct = s.total ? Math.round(s.converted / s.total * 100) : 100;
					bar.style.width = pct + '%';
					text.textContent = cfg.i18n.converted + ': ' + s.converted + ' / ' + s.total;
					if (s.done) {
						text.textContent += '. ' + cfg.i18n.done;
						webpBtn.disabled = false;
					} else {
						loop();
					}
				}).catch(function (err) {
					text.textContent = cfg.i18n.error + ' ' + err.message;
					webpBtn.disabled = false;
				});
			})();
		});
	}

	/* ---------- Database ---------- */
	document.querySelectorAll('.vso-db-clean').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.getAttribute('data-item');
			btn.disabled = true;
			post('vso_db_clean', { item: item }).then(function (r) {
				var cell = document.querySelector('.vso-db-count[data-item="' + item + '"]');
				if (cell && item !== 'optimize') { cell.textContent = r.count; }
				btn.textContent = cfg.i18n.cleaned + ' ✓';
			}).catch(function (err) {
				alert(cfg.i18n.error + ' ' + err.message);
				btn.disabled = false;
			});
		});
	});
})();
