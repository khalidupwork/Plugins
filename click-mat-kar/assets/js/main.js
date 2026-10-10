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
	function detectLocale() {
		var forced = (new URLSearchParams(window.location.search).get('cur') || '').toLowerCase();
		var tz = '';
		try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
		var code = forced || (/Karachi/.test(tz) ? 'pkr' : /(Kolkata|Calcutta)/.test(tz) ? 'inr' : 'usd');
		var packs = {
			pkr: { code: 'pkr', symbol: 'Rs ', factor: 1, intl: 'en-IN', budgetLabel: 'Rs 10 Crore' },
			inr: { code: 'inr', symbol: '₹', factor: 1, intl: 'en-IN', budgetLabel: '₹10 Crore' },
			usd: { code: 'usd', symbol: '$', factor: 0.01, intl: 'en-US', budgetLabel: '$1 Million' }
		};
		return packs[code] || packs.usd;
	}
	var LOCALE = detectLocale();

	function money(base) {
		var value = Math.round(base * LOCALE.factor);
		return LOCALE.symbol + value.toLocaleString(LOCALE.intl);
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

	window.CMKUI = { track: track, money: money, locale: LOCALE, toast: toast, flyTo: flyTo, bump: bump, pick: pick, reduceMotion: reduceMotion, config: CFG };

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
		var budget = Number(shop.getAttribute('data-budget')) || 0;
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

	function rollNumber(el, from, to) {
		if (reduceMotion) { el.textContent = money(to); return; }
		var start = null;
		var dur = 500;
		function step(ts) {
			if (!start) { start = ts; }
			var p = Math.min(1, (ts - start) / dur);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = money(from + (to - from) * eased);
			if (p < 1) { requestAnimationFrame(step); }
		}
		requestAnimationFrame(step);
	}
	window.CMKUI.rollNumber = rollNumber;

	/* ---------------------------------------------------------------
	 * Featured swipe demo: Nope / Add to cart through a small deck.
	 * ------------------------------------------------------------- */
	var DECK = [
		['👜', 'Tiny Designer Bag', 620000],
		['🦒', 'Emotional Support Giraffe', 4500000],
		['🛥️', 'Yacht (for the bathtub)', 42000000],
		['🧈', 'Gold-Plated Butter Knife', 95000],
		['🚁', 'Helicopter. To avoid traffic.', 120000000],
		['🪑', 'Chair That Judges You', 310000]
	];
	doc.querySelectorAll('[data-swipe]').forEach(function (phone) {
		var i = 0;
		var count = 0;
		var card = phone.querySelector('[data-swipe-card]');
		var emoji = phone.querySelector('[data-swipe-emoji]');
		var name = phone.querySelector('[data-swipe-name]');
		var price = phone.querySelector('[data-swipe-price]');
		var countEl = phone.querySelector('[data-swipe-count]');
		price.textContent = money(Number(price.getAttribute('data-price')));

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
