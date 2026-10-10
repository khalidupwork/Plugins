/* CLICK MAT KAR. — site UI: nav, analytics, money, homepage toys. Vanilla, no deps. */
(function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var CFG = window.CMK || {};
	root.classList.add('cmk-js');

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------------------------------------------------------------
	 * Analytics: one tiny entry point. Pushes to dataLayer / gtag / plausible
	 * if any of them exist and always fires a DOM event for custom wiring.
	 * Event names follow 04-Technical/analytics-event-map.csv.
	 * ------------------------------------------------------------- */
	function track(name, params) {
		if (window.__cmkGhost) { return; } // auto-demo taps are not real players
		params = params || {};
		try {
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push(Object.assign({ event: name }, params));
			if (typeof window.gtag === 'function') { window.gtag('event', name, params); }
			if (typeof window.plausible === 'function') { window.plausible(name, { props: params }); }
			doc.dispatchEvent(new CustomEvent('cmk:track', { detail: { name: name, params: params } }));
			if (CFG.debug && window.console) { console.log('[cmk]', name, params); }
		} catch (e) { /* analytics must never break play */ }
	}

	/* ---------------------------------------------------------------
	 * Money: prices are stored in "Rs base units". Visitors outside
	 * PK/IN see dollars (1 USD = 100 base units keeps the jokes intact).
	 * ------------------------------------------------------------- */
	var PACKS = {
		pkr: { code: 'pkr', symbol: 'Rs ', factor: 1, rate: 280, intl: 'en-IN', budgetLabel: 'Rs 25 Crore' },
		inr: { code: 'inr', symbol: '\u20B9', factor: 1, rate: 88, intl: 'en-IN', budgetLabel: '\u20B910 Crore' },
		usd: { code: 'usd', symbol: '$', factor: 0.01, rate: 1, intl: 'en-US', budgetLabel: '$1 Million' }
	};

	function detectLocale() {
		var forced = (new URLSearchParams(window.location.search).get('cur') || '').toLowerCase();
		var tz = '';
		try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
		var code = PACKS[forced] ? forced : (/Karachi/.test(tz) ? 'pkr' : /(Kolkata|Calcutta)/.test(tz) ? 'inr' : 'usd');
		return PACKS[code];
	}
	var LOCALE = detectLocale();

	function moneyIn(pack, base) {
		pack = PACKS[pack] || pack || LOCALE;
		var value = Math.round(base * pack.factor);
		return pack.symbol + value.toLocaleString(pack.intl);
	}

	function money(base) { return moneyIn(LOCALE, base); }

	/* Convert a real-world USD price into base units for a currency pack (uses a rough fixed rate). */
	function fromUsd(usd, pack) {
		pack = PACKS[pack] || pack || LOCALE;
		return Math.round((usd * pack.rate) / pack.factor);
	}
	/* Local-amount budgets ({ usd: 1e6, pkr: 25e7 }) -> base units for a pack. */
	function budgetFor(budgets, pack) {
		pack = PACKS[pack] || pack || LOCALE;
		if (budgets[pack.code]) { return Math.round(budgets[pack.code] / pack.factor); }
		return fromUsd(budgets.usd, pack);
	}

	/* "= 4,800 plates of biryani" — the line people screenshot. */
	var COMPARE = {
		pkr: [[50000, 80, 'cups of chai'], [5000000, 600, 'plates of biryani'], [Infinity, 160000, 'Honda 70 bikes']],
		inr: [[20000, 20, 'cutting chais'], [2000000, 250, 'plates of biryani'], [Infinity, 90000, 'Activa scooters']],
		usd: [[500, 5, 'cups of coffee'], [50000, 12, 'pizzas'], [Infinity, 9000, 'used Corollas']]
	};
	function compare(base, pack) {
		pack = PACKS[pack] || pack || LOCALE;
		var local = base * pack.factor;
		var rows = COMPARE[pack.code] || COMPARE.usd;
		for (var i = 0; i < rows.length; i++) {
			if (local < rows[i][0]) {
				var n = local / rows[i][1];
				if (n < 1) { return ''; }
				return '= ' + (n >= 10 ? Math.round(n).toLocaleString(pack.intl) : (Math.round(n * 10) / 10)) + ' ' + rows[i][2];
			}
		}
		return '';
	}

	/* "Rs 10 Crore" / "$1 Million" style labels for budgets. */
	function bigMoney(pack, base) {
		pack = PACKS[pack] || pack || LOCALE;
		var v = base * pack.factor;
		var units = pack.code === 'usd'
			? [[1e9, 'Billion'], [1e6, 'Million'], [1e3, 'Thousand']]
			: [[1e9, 'Arab'], [1e7, 'Crore'], [1e5, 'Lakh']];
		for (var i = 0; i < units.length; i++) {
			if (v >= units[i][0]) {
				var n = Math.round((v / units[i][0]) * 10) / 10;
				return pack.symbol.trim() + (pack.symbol.length > 1 ? ' ' : '') + n + ' ' + units[i][1];
			}
		}
		return moneyIn(pack, base);
	}

	/* Compact URL-safe encoding for result / challenge payloads. */
	function encode(obj) {
		var json = JSON.stringify(obj);
		return btoa(unescape(encodeURIComponent(json))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	}
	function decode(str) {
		try {
			str = String(str || '').replace(/-/g, '+').replace(/_/g, '/');
			while (str.length % 4) { str += '='; }
			return JSON.parse(decodeURIComponent(escape(atob(str))));
		} catch (e) { return null; }
	}

	function localizeMoney(scope) {
		(scope || doc).querySelectorAll('[data-money]').forEach(function (el) {
			el.textContent = money(Number(el.getAttribute('data-money')));
		});
		(scope || doc).querySelectorAll('[data-budget-label]').forEach(function (el) {
			el.textContent = LOCALE.budgetLabel + '.';
		});
	}

	/* ---------------------------------------------------------------
	 * Toast + fly-to-cart helpers (shared with game scripts).
	 * ------------------------------------------------------------- */
	var toastTimer;
	function toast(msg) {
		var el = doc.querySelector('[data-toast]');
		if (!el) { return; }
		el.textContent = msg;
		el.classList.add('is-on');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.classList.remove('is-on'); }, 2200);
	}

	function flyTo(fromEl, toEl, emoji) {
		if (reduceMotion || !fromEl || !toEl) { return; }
		var a = fromEl.getBoundingClientRect();
		var b = toEl.getBoundingClientRect();
		var ghost = doc.createElement('span');
		ghost.className = 'cmk-fly';
		ghost.textContent = emoji;
		ghost.style.left = (a.left + a.width / 2 - 14) + 'px';
		ghost.style.top = (a.top + a.height / 2 - 14) + 'px';
		doc.body.appendChild(ghost);
		requestAnimationFrame(function () {
			ghost.style.transform = 'translate(' + (b.left + b.width / 2 - a.left - a.width / 2) + 'px,' + (b.top + b.height / 2 - a.top - a.height / 2) + 'px) scale(.4) rotate(40deg)';
			ghost.style.opacity = '0.2';
		});
		setTimeout(function () { ghost.remove(); }, 650);
	}

	function bump(el) {
		if (!el) { return; }
		el.classList.remove('is-bump');
		void el.offsetWidth;
		el.classList.add('is-bump');
	}

	function pick(list) { return list[Math.floor(Math.random() * list.length)]; }

	window.CMKUI = { track: track, money: money, moneyIn: moneyIn, bigMoney: bigMoney, fromUsd: fromUsd, budgetFor: budgetFor, compare: compare, packs: PACKS, encode: encode, decode: decode, locale: LOCALE, toast: toast, flyTo: flyTo, bump: bump, pick: pick, reduceMotion: reduceMotion, config: CFG };

	/* ---------------------------------------------------------------
	 * Header + mobile nav
	 * ------------------------------------------------------------- */
	var header = doc.querySelector('[data-header]');
	var burger = doc.querySelector('[data-burger]');
	var nav = doc.querySelector('[data-nav]');

	function setMenu(open) {
		if (!burger || !nav) { return; }
		burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		nav.classList.toggle('is-open', open);
		doc.body.classList.toggle('cmk-menu-open', open);
	}
	if (burger && nav) {
		burger.addEventListener('click', function () { setMenu(burger.getAttribute('aria-expanded') !== 'true'); });
		nav.addEventListener('click', function (e) { if (e.target.closest('a')) { setMenu(false); } });
		doc.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) { setMenu(false); burger.focus(); }
		});
		window.addEventListener('resize', function () { if (window.innerWidth >= 900) { setMenu(false); } });
	}
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* CTA clicks */
	doc.addEventListener('click', function (e) {
		var el = e.target.closest('[data-track]');
		if (el) { track(el.getAttribute('data-track'), { label: el.getAttribute('data-label') || '' }); }
	});

	/* ---------------------------------------------------------------
	 * Hero mini shop: tap Add, budget drops, cart bumps, phone sasses you.
	 * ------------------------------------------------------------- */
	var MINI_REACT = [
		'Excellent bad decision.', 'Mana kiya tha.', 'Your accountant felt that.', 'Wah. Classy.',
		'Ammi ko mat batana.', 'Zero regrets. For now.', 'Bold. Wrong, but bold.'
	];
	doc.querySelectorAll('[data-mini-shop]').forEach(function (shop) {
		var budget = shop.hasAttribute('data-budget-usd') ? fromUsd(Number(shop.getAttribute('data-budget-usd'))) : (Number(shop.getAttribute('data-budget')) || 0);
		shop.querySelectorAll('[data-usd]').forEach(function (el) { el.setAttribute('data-price', fromUsd(Number(el.getAttribute('data-usd')))); });
		var left = budget;
		var count = 0;
		var budgetEl = shop.querySelector('[data-mini-budget]');
		var countEl = shop.querySelector('[data-mini-count]');
		var reactEl = shop.querySelector('[data-mini-react]');
		var started = false;

		shop.querySelectorAll('.cmk-mini-item__price').forEach(function (el) {
			el.textContent = money(Number(el.getAttribute('data-price')));
		});
		budgetEl.textContent = money(left);

		shop.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-mini-add]');
			if (!btn) { return; }
			var price = Number(btn.getAttribute('data-price'));
			if (!started) { started = true; track('game_start', { game_id: 'hero_demo' }); }
			if (price > left) {
				reactEl.textContent = 'Budget khatam. Beta, ghar jao.';
				shop.style.animation = 'cmk-shake .4s';
				setTimeout(function () { shop.style.animation = ''; }, 420);
				return;
			}
			left -= price;
			count += 1;
			rollNumber(budgetEl, left + price, left);
			countEl.textContent = count;
			bump(countEl.parentNode);
			flyTo(btn, countEl, btn.closest('.cmk-mini-item').querySelector('.cmk-mini-item__emoji').textContent);
			btn.classList.add('is-added');
			btn.textContent = '+1 more';
			reactEl.textContent = pick(MINI_REACT);
			reactEl.classList.remove('is-pop');
			void reactEl.offsetWidth;
			reactEl.classList.add('is-pop');
			track('product_add', { game_id: 'hero_demo', product_id: btn.getAttribute('data-name'), price: price });
		});
	});

	function rollNumber(el, from, to, fmt) {
		fmt = fmt || money;
		if (reduceMotion) { el.textContent = fmt(to); return; }
		var start = null;
		var dur = 500;
		function step(ts) {
			if (!start) { start = ts; }
			var p = Math.min(1, (ts - start) / dur);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = fmt(from + (to - from) * eased);
			if (p < 1) { requestAnimationFrame(step); }
		}
		requestAnimationFrame(step);
	}
	window.CMKUI.rollNumber = rollNumber;

	/* ---------------------------------------------------------------
	 * Shared game shell helpers (used by every engine + result page).
	 * ------------------------------------------------------------- */

	/* Describe a result payload using its game's pack: big headline value, tier, labels. */
	function describe(r) {
		var games = window.CMK_GAMES || {};
		var G = games[r.g] || games['shop-like-youre-rich'] || { result: {} };
		var R = G.result || {};
		var pack = PACKS[r.c] || LOCALE;
		var big;
		if (R.big === 'money') { big = moneyIn(pack, r.s); }
		else if (R.big === 'count') { big = r.s + ' ' + (R.countWord || ''); }
		else { big = r.q + '/100'; }
		var tiers = R.tiers || [[100, 'Done.', '']];
		var tier = tiers.filter(function (t) { return r.q < t[0]; })[0] || tiers[tiers.length - 1];
		return { G: G, R: R, big: big.trim(), tier: tier, pack: pack };
	}

	function finishGame(result) {
		track('game_complete', { game_id: result.g, spend: result.s, items: result.n, duration: result.d });
		try { sessionStorage.setItem('cmk_last_result', JSON.stringify(result)); } catch (e) {}
		var url = CFG.resultUrl || '/result/';
		window.location.href = url + (url.indexOf('?') > -1 ? '&' : '?') + 'r=' + encode(result);
	}

	/* game_view + "you have been challenged" banner. */
	function gameView(gameId, currency) {
		track('game_view', { game_id: gameId, locale: navigator.language || '', currency: currency || LOCALE.code });
		var banner = doc.querySelector('[data-challenge-banner]');
		var ch = decode(new URLSearchParams(window.location.search).get('challenge'));
		if (!banner || !ch || typeof ch.q === 'undefined') { return; }
		ch.g = gameId;
		ch.q = Math.max(1, Math.min(99, parseInt(ch.q, 10) || 1));
		ch.s = Number(ch.s) || 0;
		var d = describe(ch);
		var what = d.R.big === 'score' ? '' : d.big + ' · ';
		banner.querySelector('[data-challenge-text]').textContent = 'Your friend got ' + what + (d.R.scoreLabel || 'Score') + ' ' + ch.q + '/100. Do worse. We dare you.';
		banner.hidden = false;
		track('challenge_open', { source_result_id: ch.id || '', game_id: gameId });
	}

	window.CMKUI.describe = describe;
	window.CMKUI.finishGame = finishGame;
	window.CMKUI.gameView = gameView;

	/* ---------------------------------------------------------------
	 * Featured swipe demo: Nope / Add to cart through a small deck.
	 * ------------------------------------------------------------- */
	var DECK = [
		['👜', 'Gucchi Mini Bag (Holds 1 Mint)', 3200],
		['⌚', 'Rolax "Relax" Daytona', 45000],
		['🏎️', 'Lambo-Ghanta, No Parking', 260000],
		['🦒', 'Emotional Support Giraffe', 25000],
		['🚘', 'Rolls Rice With Biryani Boot', 460000],
		['🧥', 'Bala-ji-aga Trash Bag Jacket', 1800]
	].map(function (d) { return [d[0], d[1], fromUsd(d[2])]; });
	doc.querySelectorAll('[data-swipe]').forEach(function (phone) {
		var i = 0;
		var count = 0;
		var card = phone.querySelector('[data-swipe-card]');
		var emoji = phone.querySelector('[data-swipe-emoji]');
		var name = phone.querySelector('[data-swipe-name]');
		var price = phone.querySelector('[data-swipe-price]');
		var countEl = phone.querySelector('[data-swipe-count]');
		price.textContent = money(DECK[0][2]);

		function next(dir) {
			card.classList.add(dir === 'left' ? 'is-out-left' : 'is-out-right');
			setTimeout(function () {
				i = (i + 1) % DECK.length;
				emoji.textContent = DECK[i][0];
				name.textContent = DECK[i][1];
				price.textContent = money(DECK[i][2]);
				card.classList.remove('is-out-left', 'is-out-right');
				card.classList.remove('is-in');
				void card.offsetWidth;
				card.classList.add('is-in');
			}, reduceMotion ? 0 : 260);
		}
		phone.querySelector('[data-swipe-nope]').addEventListener('click', function () {
			toast(pick(['Responsible. Boring, but responsible.', 'Nope? Theek hai.', 'Fine. Next bad idea.']));
			next('left');
		});
		phone.querySelector('[data-swipe-add]').addEventListener('click', function () {
			count += 1;
			countEl.textContent = count;
			bump(countEl.parentNode);
			toast(pick(['Excellent bad decision.', 'Added. Obviously.', 'Cart says thank you.']));
			track('product_add', { game_id: 'featured_demo', product_id: DECK[i][1] });
			next('right');
		});
	});

	/* ---------------------------------------------------------------
	 * Reveal on scroll (one focal movement at a time).
	 * ------------------------------------------------------------- */
	var revealEls = doc.querySelectorAll('[data-reveal]');
	if ('IntersectionObserver' in window && !reduceMotion) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
			});
		}, { rootMargin: '0px 0px -10% 0px' });
		revealEls.forEach(function (el) { io.observe(el); });
	} else {
		revealEls.forEach(function (el) { el.classList.add('is-in'); });
	}

	localizeMoney();
	track('page_view', { page_type: doc.body.classList.contains('home') ? 'home' : (doc.body.className.match(/\b(single-cmk_game|page|archive)\b/) || ['other'])[0] });
})();

/* Games library filter (archive page). */
(function () {
	'use strict';
	var bar = document.querySelector('[data-lib-filter]');
	var grid = document.querySelector('[data-lib-grid]');
	if (!bar || !grid) { return; }
	bar.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-lib]');
		if (!btn) { return; }
		var mode = btn.getAttribute('data-lib');
		bar.querySelectorAll('[data-lib]').forEach(function (b) {
			b.classList.toggle('is-on', b === btn);
			b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
		});
		grid.querySelectorAll('.cmk-game-card').forEach(function (card) {
			var soon = card.classList.contains('is-soon');
			card.hidden = mode === 'ready' ? soon : mode === 'cooking' ? !soon : false;
		});
	});
})();

/* Spin the wheel of bad ideas (homepage). */
(function () {
	'use strict';
	var machine = document.querySelector('[data-spin]');
	var btn = document.querySelector('[data-spin-go]');
	if (!machine || !btn) { return; }
	var slots = Array.prototype.slice.call(machine.querySelectorAll('[data-spin-slot]'));
	var cur = 0;
	var busy = false;
	var UI = window.CMKUI || {};

	function show(i) {
		slots.forEach(function (s, idx) {
			var on = idx === i;
			s.classList.toggle('is-on', on);
			if (on) { s.removeAttribute('tabindex'); s.removeAttribute('aria-hidden'); }
			else { s.setAttribute('tabindex', '-1'); s.setAttribute('aria-hidden', 'true'); }
		});
		cur = i;
	}

	btn.addEventListener('click', function () {
		if (busy) { return; }
		busy = true;
		machine.classList.remove('is-landed');
		machine.classList.add('is-spinning');
		var target = Math.floor(Math.random() * slots.length);
		if (target === cur) { target = (target + 1) % slots.length; }
		var steps = (UI.reduceMotion ? 1 : slots.length * 2) + ((target - cur + slots.length) % slots.length);
		var n = 0;
		(function tick() {
			show((cur + 1) % slots.length);
			n += 1;
			if (n < steps) {
				setTimeout(tick, 60 + Math.pow(n / steps, 3) * 260);
			} else {
				machine.classList.remove('is-spinning');
				machine.classList.add('is-landed');
				busy = false;
				if (UI.toast) { UI.toast('Fate has spoken. No take-backs.'); }
				if (UI.track) { UI.track('cta_click', { label: 'spin_' + slots[cur].getAttribute('data-game') }); }
			}
		})();
	});
})();
