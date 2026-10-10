/* CLICK MAT KAR. — quiz engine. Runs choice-based games from their pack in games-data.js. */
(function () {
	'use strict';

	var UI = window.CMKUI;
	var root = document.querySelector('[data-quiz-game]');
	if (!UI || !root || !window.CMK_GAMES) { return; }

	var GAME_ID = root.getAttribute('data-game-id');
	var G = window.CMK_GAMES[GAME_ID];
	if (!G || G.engine !== 'quiz') { return; }

	var $ = function (sel) { return root.querySelector(sel); };
	var Q = G.questions;
	var state = { i: 0, points: 0, money: 0, picks: [], startAt: 0, locked: false };

	var startEl = $('[data-quiz-start]');
	var playEl = $('[data-quiz-play]');
	var stepEl = $('[data-quiz-step]');
	var barEl = $('[data-quiz-bar]');
	var emojiEl = $('[data-quiz-emoji]');
	var qEl = $('[data-quiz-q]');
	var optsEl = $('[data-quiz-options]');
	var reactEl = $('[data-quiz-react]');

	if (G.intro) { $('[data-intro]').textContent = G.intro; }
	if (G.cta) { $('[data-quiz-cta]').textContent = G.cta; }

	function esc(str) { return String(str).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function optLabel(o) { return o[0].replace('{m}', UI.money(o[3] || 0)); }

	function maxPoints() {
		return Q.reduce(function (sum, q) {
			return sum + Math.max.apply(null, q.o.map(function (o) { return o[1]; }));
		}, 0);
	}

	function render() {
		var q = Q[state.i];
		state.locked = false;
		stepEl.textContent = (state.i + 1) + ' / ' + Q.length;
		barEl.style.width = ((state.i) / Q.length) * 100 + '%';
		emojiEl.textContent = q.e;
		qEl.textContent = q.q;
		reactEl.textContent = '';
		optsEl.innerHTML = q.o.map(function (o, idx) {
			return '<button type="button" class="cmk-quiz__opt" data-opt="' + idx + '"><span class="cmk-quiz__opt-key" aria-hidden="true">' + String.fromCharCode(65 + idx) + '</span><span>' + esc(optLabel(o)) + '</span></button>';
		}).join('');
		playEl.classList.remove('is-in');
		void playEl.offsetWidth;
		playEl.classList.add('is-in');
		qEl.focus({ preventScroll: true });
	}

	function start() {
		state.startAt = Date.now();
		startEl.hidden = true;
		playEl.hidden = false;
		UI.track('game_start', { game_id: GAME_ID });
		render();
		var card = $('[data-quiz-card]');
		if (card.getBoundingClientRect().top < 0) { card.scrollIntoView({ behavior: UI.reduceMotion ? 'auto' : 'smooth', block: 'start' }); }
	}

	function choose(idx, btn) {
		if (state.locked) { return; }
		state.locked = true;
		var q = Q[state.i];
		var o = q.o[idx];
		state.points += o[1];
		state.money += o[3] || 0;
		state.picks.push({ q: state.i, o: idx, p: o[1] });

		optsEl.querySelectorAll('.cmk-quiz__opt').forEach(function (b) {
			b.disabled = true;
			if (b !== btn) { b.classList.add('is-dim'); }
		});
		btn.classList.add('is-picked');
		reactEl.textContent = o[2];
		reactEl.classList.remove('is-pop');
		void reactEl.offsetWidth;
		reactEl.classList.add('is-pop');
		UI.track('product_add', { game_id: GAME_ID, product_id: 'q' + (state.i + 1), category: 'answer', price: o[1] });

		setTimeout(function () {
			state.i += 1;
			if (state.i < Q.length) { render(); } else { finish(); }
		}, UI.reduceMotion ? 500 : 1100);
	}

	function finish() {
		barEl.style.width = '100%';
		var q = Math.max(1, Math.min(99, Math.round(1 + (state.points / (maxPoints() || 1)) * 98)));
		var worst = state.picks.slice().sort(function (a, b) { return b.p - a.p; })[0];
		var big = G.result.big;
		var result = {
			v: 2,
			g: GAME_ID,
			s: big === 'money' ? state.money : big === 'count' ? state.points : q,
			q: q,
			n: Q.length,
			e: state.picks.filter(function (p) { return p.p > 0; }).map(function (p) { return Q[p.q].e; }).slice(0, 6).join('') || '😇',
			t: worst && worst.p > 0 ? optLabel(Q[worst.q].o[worst.o]) : '',
			c: UI.locale.code,
			d: Math.round((Date.now() - state.startAt) / 1000),
			id: Math.random().toString(36).slice(2, 9)
		};
		UI.finishGame(result);
	}

	root.addEventListener('click', function (e) {
		if (e.target.closest('[data-quiz-go]')) { start(); return; }
		var opt = e.target.closest('[data-opt]');
		if (opt) { choose(parseInt(opt.getAttribute('data-opt'), 10), opt); }
	});
	root.addEventListener('keydown', function (e) {
		if (playEl.hidden || state.locked) { return; }
		var idx = e.key.toUpperCase().charCodeAt(0) - 65;
		var btn = optsEl.querySelector('[data-opt="' + idx + '"]');
		if (e.key.length === 1 && btn) { choose(idx, btn); }
	});

	UI.gameView(GAME_ID);
})();
