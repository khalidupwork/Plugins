/* CLICK MAT KAR. — attention director.
 * When the visitor goes idle, every few seconds ONE visible thing on the page does something
 * (brand rule: one focal movement at a time). Any pointer/scroll/key activity pauses it.
 */
(function () {
	'use strict';

	var doc = document;
	var UI = window.CMKUI || {};
	var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* Tab title tease works even with reduced motion (it is not motion). */
	var baseTitle = doc.title;
	var AWAY = ['👀 Come back… mana kiya tha', '🛒 Your cart misses you', '🙃 We told you not to click', '💸 Fake money is waiting'];
	doc.addEventListener('visibilitychange', function () {
		if (doc.hidden) { baseTitle = doc.title; doc.title = AWAY[Math.floor(Math.random() * AWAY.length)]; }
		else { doc.title = baseTitle; }
	});

	if (REDUCED) { return; }

	var IDLE_MS = 4500;      // visitor must be still this long
	var GAP_MS = 7500;       // minimum time between two attractions
	var MAX_PER_PAGE = 14;   // never become annoying
	var lastActive = Date.now();
	var lastAct = 0;
	var count = 0;
	var busy = false;
	var bigIdleShown = false;

	['pointermove', 'pointerdown', 'scroll', 'keydown', 'touchstart', 'wheel'].forEach(function (ev) {
		window.addEventListener(ev, function () { lastActive = Date.now(); }, { passive: true });
	});

	function pick(list) { return list[Math.floor(Math.random() * list.length)]; }
	function inView(el, ratio) {
		if (!el || !el.getClientRects().length) { return false; }
		var r = el.getBoundingClientRect();
		var vh = window.innerHeight, vw = window.innerWidth;
		var visH = Math.min(r.bottom, vh) - Math.max(r.top, 0);
		var visW = Math.min(r.right, vw) - Math.max(r.left, 0);
		return visH > 0 && visW > 0 && (visH * visW) / (r.width * r.height) >= (ratio || 0.6);
	}
	function pop(el, text, dy) {
		if (!UI.pop || !el) { return; }
		var r = el.getBoundingClientRect();
		UI.pop(r.left + r.width / 2, r.top + (dy || 0), text);
	}
	function flash(el, cls, ms) {
		if (!el) { return; }
		el.classList.remove(cls);
		void el.offsetWidth;
		el.classList.add(cls);
		setTimeout(function () { el.classList.remove(cls); }, ms || 1200);
	}
	/* Programmatic clicks must not pollute analytics. */
	function ghostClick(el) {
		window.__cmkGhost = true;
		try { el.click(); } finally { window.__cmkGhost = false; }
	}

	/* A finger that flies in and taps a real button. */
	function ghostTap(target, done) {
		var r = target.getBoundingClientRect();
		var f = doc.createElement('span');
		f.className = 'cmk-ghost-finger';
		f.textContent = '👆';
		f.setAttribute('aria-hidden', 'true');
		f.style.left = (r.left + r.width / 2 + 70) + 'px';
		f.style.top = (r.top + r.height / 2 + 90) + 'px';
		doc.body.appendChild(f);
		requestAnimationFrame(function () {
			f.style.translate = '-70px -78px';
			setTimeout(function () {
				f.classList.add('is-tap');
				ghostClick(target);
				setTimeout(function () { f.style.opacity = '0'; setTimeout(function () { f.remove(); if (done) { done(); } }, 300); }, 380);
			}, 620);
		});
	}

	/* Corner peeker: 👀 slides in with a line. */
	var peeker = null;
	function peek(text, href, ms) {
		if (!peeker) {
			peeker = doc.createElement('a');
			peeker.className = 'cmk-peeker';
			peeker.innerHTML = '<span class="cmk-peeker__eyes" aria-hidden="true">👀</span><span class="cmk-peeker__text"></span>';
			doc.body.appendChild(peeker);
			peeker.addEventListener('click', function () { if (UI.track) { UI.track('cta_click', { label: 'peeker' }); } });
		}
		peeker.querySelector('.cmk-peeker__text').textContent = text;
		peeker.href = href || (UI.config && (UI.config.shopUrl || UI.config.gamesUrl)) || '/';
		peeker.classList.add('is-on');
		clearTimeout(peeker._t);
		peeker._t = setTimeout(function () { peeker.classList.remove('is-on'); }, ms || 3200);
	}

	/* ------------------------------------------------------------------
	 * Attractions. Each: { el(): element|null, run(el, done), max }
	 * ---------------------------------------------------------------- */
	var ACTS = [];
	function act(name, max, el, run) { ACTS.push({ name: name, max: max, used: 0, el: el, run: run }); }

	// Hero: ghost finger taps "Add" in the mini shop (only before the visitor tries it).
	act('hero-ghost', 2, function () {
		var shop = doc.querySelector('[data-mini-shop]');
		if (!shop || shop.dataset.touched || !inView(shop, 0.7)) { return null; }
		var btns = Array.prototype.filter.call(shop.querySelectorAll('[data-mini-add]'), function (b) { return !b.classList.contains('is-added'); });
		return btns.length ? btns[Math.floor(Math.random() * Math.min(2, btns.length))] : null;
	}, function (btn, done) { ghostTap(btn, done); });

	// Featured phone swipes by itself.
	act('featured-swipe', 3, function () {
		var ph = doc.querySelector('[data-swipe]');
		return ph && inView(ph, 0.7) ? ph : null;
	}, function (ph, done) {
		var b = ph.querySelector(Math.random() < 0.6 ? '[data-swipe-add]' : '[data-swipe-nope]');
		ghostTap(b, done);
	});

	// A visible game card stands up and asks to be played.
	act('card-call', 4, function () {
		var cards = Array.prototype.filter.call(doc.querySelectorAll('a.cmk-game-card'), function (c) { return inView(c, 0.9); });
		return cards.length ? pick(cards) : null;
	}, function (card, done) {
		flash(card, 'is-calling', 1400);
		var em = card.querySelector('.cmk-game-card__emoji');
		if (em) { flash(em, 'is-jump', 600); }
		pop(card, pick(['Mujhe khelo!', 'Pick me 👀', 'Bad idea inside', 'Psst. Over here.', 'Just one game?']), 34);
		setTimeout(done, 1400);
	});

	// Chaos cards do a wave.
	act('chaos-wave', 2, function () {
		var wrap = doc.querySelector('.cmk-chaos__cards');
		return wrap && inView(wrap, 0.7) ? wrap : null;
	}, function (wrap, done) {
		Array.prototype.forEach.call(wrap.children, function (c, i) { setTimeout(function () { flash(c, 'is-wave', 700); }, i * 160); });
		setTimeout(done, 1200);
	});

	// How it works: 1 → 2 → 3 light up.
	act('steps', 2, function () {
		var s = doc.querySelector('.cmk-steps');
		return s && inView(s, 0.7) ? s : null;
	}, function (s, done) {
		s.querySelectorAll('.cmk-step__num').forEach(function (n, i) { setTimeout(function () { flash(n, 'is-ping', 700); }, i * 380); });
		setTimeout(done, 1500);
	});

	// Spin machine lever: "pull me".
	act('lever', 2, function () {
		var m = doc.querySelector('[data-spin]');
		return m && inView(m, 0.7) && !m.classList.contains('is-spinning') ? m : null;
	}, function (m, done) {
		flash(m.querySelector('.cmk-spin__lever'), 'is-wiggle', 900);
		pop(doc.querySelector('[data-spin-go]') || m, pick(['Pull me 🎰', 'Let fate decide', 'Spin. Coward.']), -8);
		setTimeout(done, 900);
	});

	// Result cards: the stamp thumps.
	act('stamp', 2, function () {
		var st = doc.querySelector('.cmk-result-card__stamp');
		return st && inView(st, 0.9) ? st : null;
	}, function (st, done) { flash(st, 'is-thump', 700); setTimeout(done, 700); });

	// Chat bubbles in the challenge section pop one after another.
	act('bubbles', 2, function () {
		var s = doc.querySelector('.cmk-challenge__stage');
		return s && inView(s, 0.7) ? s : null;
	}, function (s, done) {
		s.querySelectorAll('.cmk-bubble').forEach(function (b, i) { setTimeout(function () { flash(b, 'is-boop', 500); }, i * 260); });
		setTimeout(done, 1000);
	});

	// Shop games: an affordable product "calls" the visitor.
	act('product-call', 6, function () {
		var items = Array.prototype.filter.call(doc.querySelectorAll('.cmk-product:not(.is-broke):not(.is-in-cart)'), function (p) { return inView(p, 0.95); });
		return items.length ? pick(items) : null;
	}, function (p, done) {
		flash(p, 'is-calling', 1500);
		var name = (p.querySelector('.cmk-product__brand') || p.querySelector('.cmk-product__name') || {}).textContent || 'This';
		var react = doc.querySelector('[data-react]');
		if (react) { react.textContent = 'Psst… ' + name + ' is calling you. ' + (p.querySelector('.cmk-product__emoji') || {}).textContent; }
		pop(p, pick(['Add me 🥺', 'Take me home', 'You deserve this', 'Ammi won\'t know']), 40);
		setTimeout(done, 1500);
	});

	// Shop games: the checkout bar nudges once the cart has stuff.
	act('checkout-nudge', 2, function () {
		var bar = doc.querySelector('[data-finish-bar]:not([hidden]) [data-finish]');
		return bar && inView(bar, 0.9) ? bar : null;
	}, function (b, done) { flash(b, 'is-shake', 700); pop(b, 'Ready for judgement?', -6); setTimeout(done, 800); });

	// Quiz: the options shiver, one by one.
	act('quiz-options', 3, function () {
		var o = doc.querySelector('[data-quiz-options]');
		return o && o.children.length && !o.querySelector(':disabled') && inView(o, 0.6) ? o : null;
	}, function (o, done) {
		Array.prototype.forEach.call(o.children, function (b, i) { setTimeout(function () { flash(b, 'is-wave', 600); }, i * 150); });
		pop(o, pick(['Choose. Courage.', 'No wrong answers. Only bad ones.', 'Tick tock…']), -10);
		setTimeout(done, 1100);
	});
	act('quiz-start', 2, function () {
		var b = doc.querySelector('[data-quiz-start]:not([hidden]) [data-quiz-go]');
		return b && inView(b, 0.9) ? b : null;
	}, function (b, done) { flash(b, 'is-shake', 700); pop(b, 'It takes 2 minutes. Probably.', -8); setTimeout(done, 800); });

	// Result page: challenge / share buttons beg to be used.
	act('result-share', 3, function () {
		var b = doc.querySelector('[data-share-owner]:not([hidden]) [data-challenge], [data-share-visitor]:not([hidden]) [data-beat]');
		return b && inView(b, 0.9) ? b : null;
	}, function (b, done) {
		flash(b, 'is-shake', 700);
		pop(b, pick(['Send it. Ruin a friendship.', 'Your friends need to see this', 'Do worse. We dare you.']), -8);
		setTimeout(done, 900);
	});

	// The eyes peek from the corner while the visitor reads.
	act('peeker', 2, function () { return doc.body; }, function (b, done) {
		peek(pick(['Still scrolling? Concerning.', 'Psst… one game. Just one.', 'We can see you not clicking.']));
		setTimeout(done, 3400);
	});

	/* Mark the hero mini shop as touched once a real person uses it. */
	doc.addEventListener('pointerdown', function (e) {
		var shop = e.target.closest && e.target.closest('[data-mini-shop]');
		if (shop) { shop.dataset.touched = '1'; }
	}, true);

	/* ------------------------------------------------------------------
	 * Scheduler
	 * ---------------------------------------------------------------- */
	setInterval(function () {
		var now = Date.now();
		if (doc.hidden || busy || doc.body.classList.contains('cmk-menu-open')) { return; }

		// Long idle: one bigger "still here?" moment per page.
		if (!bigIdleShown && now - lastActive > 30000) {
			bigIdleShown = true;
			peek('Still here? That\'s concerning. Play a game →', null, 6000);
			lastAct = now;
			return;
		}
		if (count >= MAX_PER_PAGE || now - lastActive < IDLE_MS || now - lastAct < GAP_MS) { return; }

		var pool = [];
		ACTS.forEach(function (a) {
			if (a.used >= a.max) { return; }
			var el = a.el();
			if (el) { pool.push([a, el]); }
		});
		// The peeker only fills in when nothing on screen can perform.
		var real = pool.filter(function (p) { return p[0].name !== 'peeker'; });
		if (real.length) { pool = real; }
		if (!pool.length) { return; }

		var choice = pool[Math.floor(Math.random() * pool.length)];
		busy = true;
		count += 1;
		choice[0].used += 1;
		lastAct = now;
		choice[0].run(choice[1], function () { busy = false; lastAct = Date.now(); });
		setTimeout(function () { busy = false; }, 5000); // safety net
	}, 1000);
})();
