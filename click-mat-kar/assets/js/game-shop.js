/* CLICK MAT KAR. — Shop Like You're Rich. Prices are in Rs base units (see main.js money()). */
(function () {
	'use strict';

	var UI = window.CMKUI;
	var root = document.querySelector('[data-shop-game]');
	if (!UI || !root) { return; }

	var GAME_ID = root.getAttribute('data-game-id') || 'shop-like-youre-rich';
	var BUDGET = 100000000; // Rs 10 crore / $1M

	var CATEGORIES = [
		['all', 'Everything', '🌀'],
		['fashion', 'Drip', '👟'],
		['tech', 'Gadgets', '📱'],
		['rides', 'Rides', '🏎️'],
		['home', 'Home', '🏰'],
		['weird', 'Why though', '🦒']
	];

	// [id, emoji, name, category, price, quip, silly]
	var PRODUCTS = [
		['sneakers', '👟', 'Limited Sneakers', 'fashion', 280000, 'Too clean to actually walk in.', 0],
		['tiny-bag', '👜', 'Tiny Designer Bag', 'fashion', 620000, 'Holds one (1) mint.', 1],
		['indoor-shades', '🕶️', 'Indoor Sunglasses', 'fashion', 95000, 'For dramatic entries only.', 1],
		['jacket', '🧥', 'Jacket Worn Once', 'fashion', 450000, 'For the photo. Then never.', 0],
		['watch', '⌚', 'Watch That Tells Time Worse', 'fashion', 1800000, 'Your phone is more accurate.', 1],
		['phone', '📱', 'Phone With 7 Cameras', 'tech', 450000, 'All seven for selfies.', 0],
		['laptop', '💻', 'Laptop For Watching Reels', 'tech', 650000, 'Strictly productivity. Strictly.', 0],
		['headphones', '🎧', 'Headphones To Ignore Family', 'tech', 120000, 'Noise cancelling. Rishta cancelling.', 0],
		['robot', '🤖', 'Robot Butler (Judgemental)', 'tech', 8500000, 'It sighs when you order biryani at 3am.', 1],
		['supercar', '🏎️', 'Supercar, No Parking', 'rides', 32000000, 'Will live in the driveway. Forever.', 0],
		['scooter', '🛵', 'Gold-Plated Scooter', 'rides', 900000, 'Same traffic. More shine.', 1],
		['yacht', '🛥️', 'Yacht', 'rides', 42000000, 'You get seasick. Who cares.', 0],
		['helicopter', '🚁', 'Helicopter To Skip Traffic', 'rides', 65000000, 'Landing permission not included.', 0],
		['castle', '🏰', 'Small Castle', 'home', 48000000, 'Comes with one (1) ghost.', 0],
		['sofa', '🛋️', 'Sofa Nobody Sits On', 'home', 750000, 'Plastic cover included, obviously.', 1],
		['gold-tap', '🚿', 'Solid Gold Tap', 'home', 380000, 'Water tastes exactly the same.', 1],
		['portrait', '🖼️', 'Giant Painting Of Yourself', 'home', 2200000, 'Hang it facing the door.', 1],
		['giraffe', '🦒', 'Emotional Support Giraffe', 'weird', 4500000, 'Needs a taller house. Buy one.', 1],
		['camel', '🐪', 'Camel For Wedding Entry', 'weird', 1200000, 'Baraat will never be the same.', 1],
		['butter-knife', '🧈', 'Gold Butter Knife', 'weird', 95000, 'Butters exactly like a normal knife.', 1],
		['judging-chair', '🪑', 'Chair That Judges You', 'weird', 310000, 'Ergonomic. Emotionally not.', 1],
		['star', '🌟', 'A Star Named After You', 'weird', 50000, 'You will never see it. Ever.', 1],
		['banana', '🍌', 'Banana Taped To A Wall', 'weird', 6500000, 'Art. Apparently.', 1],
		['island', '🏝️', 'Private Island (Small)', 'weird', 85000000, 'Mostly sand. Some regret.', 0]
	];

	var REACT = {
		cheap: ['Itna sasta? Bhai, dream bigger.', 'Cute. Now buy something stupid.', 'That is pocket change for you now.'],
		mid: ['Excellent bad decision.', 'Mana kiya tha.', 'Your accountant just felt that.', 'Wah. Classy.', 'Ammi ko mat batana.'],
		big: ['WHAT. Okay. Respect.', 'Bold. Wrong, but bold.', 'Your bank would faint. Good thing it is fake.', 'Chaos level: rising.'],
		silly: ['Why. Just... why.', 'This is the purchase of a legend.', 'Zero regrets. For now.', 'Logic has left the chat.']
	};

	var state = { left: BUDGET, cart: {}, filter: 'all', started: false, startAt: 0, finished: false };

	var $ = function (sel) { return root.querySelector(sel); };
	var grid = $('[data-grid]');
	var filters = $('[data-filters]');
	var leftEl = $('[data-left]');
	var barEl = $('[data-bar]');
	var countEl = $('[data-count]');
	var reactEl = $('[data-react]');
	var cart = $('[data-cart]');
	var cartList = $('[data-cart-list]');
	var cartEmpty = $('[data-cart-empty]');
	var cartTotal = $('[data-cart-total]');
	var cartOpenBtn = $('[data-cart-open]');
	var finishBar = $('[data-finish-bar]');
	var finishText = $('[data-finish-text]');

	function byId(id) {
		for (var i = 0; i < PRODUCTS.length; i++) { if (PRODUCTS[i][0] === id) { return PRODUCTS[i]; } }
		return null;
	}
	function spent() { return BUDGET - state.left; }
	function itemCount() {
		var n = 0;
		Object.keys(state.cart).forEach(function (k) { n += state.cart[k]; });
		return n;
	}

	/* ---------- render ---------- */
	function renderFilters() {
		filters.innerHTML = CATEGORIES.map(function (c) {
			return '<button type="button" class="cmk-filter' + (state.filter === c[0] ? ' is-on' : '') + '" data-filter="' + c[0] + '" aria-pressed="' + (state.filter === c[0]) + '"><span aria-hidden="true">' + c[2] + '</span> ' + c[1] + '</button>';
		}).join('');
	}

	function renderGrid() {
		grid.innerHTML = PRODUCTS.filter(function (p) {
			return state.filter === 'all' || p[3] === state.filter;
		}).map(function (p) {
			var qty = state.cart[p[0]] || 0;
			var canAfford = p[4] <= state.left;
			return '<li class="cmk-product' + (qty ? ' is-in-cart' : '') + '" data-id="' + p[0] + '">' +
				(qty ? '<span class="cmk-product__qty">×' + qty + '</span>' : '') +
				'<span class="cmk-product__emoji" aria-hidden="true">' + p[1] + '</span>' +
				'<span class="cmk-product__name">' + p[2] + '</span>' +
				'<span class="cmk-product__quip">' + p[5] + '</span>' +
				'<span class="cmk-product__price">' + UI.money(p[4]) + '</span>' +
				'<button type="button" class="cmk-btn cmk-btn--sm ' + (canAfford ? 'cmk-btn--ink' : 'cmk-btn--white') + ' cmk-product__add" data-add="' + p[0] + '"' + (canAfford ? '' : ' disabled') + '>' +
				(canAfford ? (qty ? 'Add another' : 'Add to cart') : 'Too broke 😭') + '</button>' +
				'</li>';
		}).join('');
	}

	function renderHud() {
		leftEl.textContent = UI.money(state.left);
		barEl.style.width = Math.max(0, (state.left / BUDGET) * 100) + '%';
		countEl.textContent = itemCount();
		var n = itemCount();
		finishBar.hidden = n === 0;
		if (n) {
			finishText.innerHTML = '<b>' + n + '</b> ' + (n === 1 ? 'item' : 'items') + ' · <b>' + UI.money(spent()) + '</b> wasted';
		}
	}

	function renderCart() {
		var ids = Object.keys(state.cart);
		cartEmpty.hidden = ids.length > 0;
		cartList.innerHTML = ids.map(function (id) {
			var p = byId(id);
			return '<li class="cmk-cart__item"><span class="cmk-cart__emoji" aria-hidden="true">' + p[1] + '</span>' +
				'<span class="cmk-cart__name">' + p[2] + '<small>×' + state.cart[id] + ' · ' + UI.money(p[4] * state.cart[id]) + '</small></span>' +
				'<button type="button" class="cmk-cart__remove" data-remove="' + id + '" aria-label="Remove one ' + p[2] + '">−</button></li>';
		}).join('');
		cartTotal.textContent = UI.money(spent());
		cart.querySelectorAll('[data-finish]').forEach(function (b) { b.disabled = ids.length === 0; });
	}

	function renderAll() { renderGrid(); renderHud(); renderCart(); }

	function react(p) {
		var pool = p[6] ? REACT.silly : p[4] < 300000 ? REACT.cheap : p[4] >= 10000000 ? REACT.big : REACT.mid;
		reactEl.textContent = UI.pick(pool);
		reactEl.classList.remove('is-pop');
		void reactEl.offsetWidth;
		reactEl.classList.add('is-pop');
	}

	/* ---------- actions ---------- */
	function add(id, btn) {
		var p = byId(id);
		if (!p || p[4] > state.left || state.finished) { return; }
		if (!state.started) {
			state.started = true;
			state.startAt = Date.now();
			UI.track('game_start', { game_id: GAME_ID });
		}
		state.left -= p[4];
		state.cart[id] = (state.cart[id] || 0) + 1;
		var prevLeft = state.left + p[4];
		UI.flyTo(btn, cartOpenBtn, p[1]);
		UI.bump(countEl);
		react(p);
		renderGrid();
		renderCart();
		renderHud();
		UI.rollNumber(leftEl, prevLeft, state.left);
		UI.track('product_add', { game_id: GAME_ID, product_id: id, category: p[3], price: p[4] });

		var focusBtn = grid.querySelector('[data-add="' + id + '"]');
		if (focusBtn && document.activeElement === document.body) { focusBtn.focus(); }

		if (state.left < 50000) {
			UI.toast('Budget khatam! Time to face the consequences.');
		}
	}

	function remove(id) {
		var p = byId(id);
		if (!p || !state.cart[id]) { return; }
		state.cart[id] -= 1;
		if (!state.cart[id]) { delete state.cart[id]; }
		state.left += p[4];
		UI.toast(UI.pick(['Refund? Coward.', 'Phir se? Fine.', 'Second thoughts are not allowed here.']));
		renderAll();
	}

	var lastFocus = null;
	function openCart() {
		lastFocus = document.activeElement;
		cart.hidden = false;
		cartOpenBtn.setAttribute('aria-expanded', 'true');
		document.body.classList.add('cmk-menu-open');
		requestAnimationFrame(function () { cart.classList.add('is-open'); cart.querySelector('.cmk-cart__panel').focus(); });
		UI.track('cart_open', { game_id: GAME_ID, items: itemCount(), spend: spent() });
	}
	function closeCart() {
		cart.classList.remove('is-open');
		cartOpenBtn.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('cmk-menu-open');
		setTimeout(function () { cart.hidden = true; }, UI.reduceMotion ? 0 : 250);
		if (lastFocus) { lastFocus.focus(); }
	}

	function financialIQ() {
		var pct = spent() / BUDGET;
		var silly = 0;
		Object.keys(state.cart).forEach(function (id) { if (byId(id)[6]) { silly += state.cart[id]; } });
		var iq = Math.round(100 - pct * 80 - silly * 3 - (itemCount() > 15 ? 8 : 0));
		return Math.max(1, Math.min(99, iq));
	}

	function finish() {
		if (!itemCount() || state.finished) { return; }
		state.finished = true;
		var ids = Object.keys(state.cart).sort(function (a, b) {
			return byId(b)[4] * state.cart[b] - byId(a)[4] * state.cart[a];
		});
		var top = byId(ids[0]);
		var result = {
			v: 1,
			g: GAME_ID,
			s: spent(),
			b: BUDGET,
			n: itemCount(),
			e: ids.slice(0, 6).map(function (id) { return byId(id)[1]; }).join(''),
			t: top[2],
			q: financialIQ(),
			c: UI.locale.code,
			d: Math.round((Date.now() - (state.startAt || Date.now())) / 1000),
			id: Math.random().toString(36).slice(2, 9)
		};
		UI.track('game_complete', { game_id: GAME_ID, spend: result.s, items: result.n, duration: result.d });
		try { sessionStorage.setItem('cmk_last_result', JSON.stringify(result)); } catch (e) {}
		var url = (UI.config.resultUrl || '/result/');
		window.location.href = url + (url.indexOf('?') > -1 ? '&' : '?') + 'r=' + UI.encode(result);
	}

	/* ---------- events ---------- */
	root.addEventListener('click', function (e) {
		var t = e.target;
		var addBtn = t.closest('[data-add]');
		if (addBtn) { add(addBtn.getAttribute('data-add'), addBtn); return; }
		var f = t.closest('[data-filter]');
		if (f) { state.filter = f.getAttribute('data-filter'); renderFilters(); renderGrid(); return; }
		var r = t.closest('[data-remove]');
		if (r) { remove(r.getAttribute('data-remove')); return; }
		if (t.closest('[data-cart-open]')) { openCart(); return; }
		if (t.closest('[data-cart-close]')) { closeCart(); return; }
		if (t.closest('[data-finish]')) { finish(); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !cart.hidden) { closeCart(); }
	});

	/* ---------- challenge banner ---------- */
	var challenge = UI.decode(new URLSearchParams(window.location.search).get('challenge'));
	if (challenge && challenge.s) {
		var banner = $('[data-challenge-banner]');
		banner.querySelector('[data-challenge-text]').textContent =
			'Your friend wasted ' + UI.moneyIn(challenge.c, challenge.s) + ' and scored ' + challenge.q + '/100 Financial IQ. Do worse. We dare you.';
		banner.hidden = false;
		UI.track('challenge_open', { source_result_id: challenge.id || '', game_id: GAME_ID });
	}

	UI.track('game_view', { game_id: GAME_ID, locale: navigator.language || '', currency: UI.locale.code });
	renderFilters();
	renderAll();
})();
