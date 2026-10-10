/* CLICK MAT KAR. — shop engine. Runs every shopping game from its pack in games-data.js.
 * Item: { id, e (emoji), brand, name, cat, usd, quip, silly, tag? }
 * Prices are rough real-world USD, converted per currency pack (see main.js fromUsd()).
 */
(function () {
	'use strict';

	var UI = window.CMKUI;
	var root = document.querySelector('[data-shop-game]');
	if (!UI || !root || !window.CMK_GAMES) { return; }

	var GAME_ID = root.getAttribute('data-game-id');
	var G = window.CMK_GAMES[GAME_ID];
	if (!G || G.engine !== 'shop') { return; }

	var PACK = G.currency ? UI.packs[G.currency] : UI.locale;
	var BUDGET = UI.budgetFor(G.budgets, PACK);
	var IMAGES = (UI.config.productImages || {});
	var IMG_BASE = UI.config.productImageBase || '';
	var PRODUCTS = G.items.map(function (it) {
		return {
			id: it.id, e: it.e, brand: it.brand || '', name: it.name, cat: it.cat, quip: it.quip, silly: !!it.silly, tag: it.tag || '',
			price: UI.fromUsd(it.usd, PACK),
			img: IMAGES[it.id] ? IMG_BASE + IMAGES[it.id] : ''
		};
	});
	var CHEAPEST = Math.min.apply(null, PRODUCTS.map(function (p) { return p.price; }));
	var money = function (base) { return UI.moneyIn(PACK, base); };

	var REACT = {
		cheap: ['Itna sasta? Dream bigger.', 'Cute. Now buy something stupid.', 'That is pocket change for you now.'],
		mid: ['Excellent bad decision.', 'Mana kiya tha.', 'Your accountant just felt that.', 'Wah. Classy.', 'Ammi ko mat batana.'],
		big: ['WHAT. Okay. Respect.', 'Bold. Wrong, but bold.', 'Your bank would faint. Good thing it is fake.', 'Chaos level: rising.'],
		silly: ['Why. Just... why.', 'This is the purchase of a legend.', 'Zero regrets. For now.', 'Logic has left the chat.']
	};
	var MILESTONES = [
		[0.25, 'A quarter gone. Ammi has been notified.'],
		[0.5, 'Half the money, gone. Your accountant resigned.'],
		[0.75, '75% gone. The bank sent a "you okay?" text.'],
		[0.95, 'Almost broke. Truly inspiring.']
	];

	var state = { left: BUDGET, cart: {}, filter: 'all', started: false, startAt: 0, finished: false, milestone: 0 };

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
	var cartCompare = $('[data-cart-compare]');
	var cartOpenBtn = $('[data-cart-open]');
	var finishBar = $('[data-finish-bar]');
	var finishText = $('[data-finish-text]');

	/* Copy from the pack. */
	root.querySelectorAll('[data-budget-label]').forEach(function (el) { el.textContent = UI.bigMoney(PACK, BUDGET) + '.'; });
	if (G.intro) { $('[data-intro]').textContent = G.intro; }
	if (G.hint) { reactEl.textContent = G.hint; }
	if (G.checkout) { root.querySelectorAll('[data-finish-label]').forEach(function (el) { el.textContent = G.checkout; }); }

	function byId(id) {
		for (var i = 0; i < PRODUCTS.length; i++) { if (PRODUCTS[i].id === id) { return PRODUCTS[i]; } }
		return null;
	}
	function spent() { return BUDGET - state.left; }
	function itemCount() {
		var n = 0;
		Object.keys(state.cart).forEach(function (k) { n += state.cart[k]; });
		return n;
	}
	function sillyCount() {
		var n = 0;
		Object.keys(state.cart).forEach(function (id) { if (byId(id).silly) { n += state.cart[id]; } });
		return n;
	}
	function esc(str) { return String(str).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function fullName(p) { return (p.brand ? p.brand + ' ' : '') + p.name; }

	/* ---------- render ---------- */
	function renderFilters() {
		filters.innerHTML = G.categories.map(function (c) {
			return '<button type="button" class="cmk-filter' + (state.filter === c[0] ? ' is-on' : '') + '" data-filter="' + c[0] + '" aria-pressed="' + (state.filter === c[0]) + '"><span aria-hidden="true">' + c[2] + '</span> ' + esc(c[1]) + '</button>';
		}).join('');
	}

	function visual(p) {
		return p.img
			? '<img class="cmk-product__img" src="' + esc(p.img) + '" alt="" width="160" height="160" loading="lazy" decoding="async">'
			: '<span class="cmk-product__emoji" aria-hidden="true">' + p.e + '</span>';
	}

	function renderGrid() {
		grid.innerHTML = PRODUCTS.filter(function (p) {
			return state.filter === 'all' || p.cat === state.filter;
		}).map(function (p) {
			var qty = state.cart[p.id] || 0;
			var canAfford = p.price <= state.left;
			var label = canAfford ? (qty ? (G.qty ? 'Buy another' : 'Add another') : 'Add to cart') : 'Too broke 😭';
			var cmp = UI.compare(p.price, PACK);
			return '<li class="cmk-product' + (qty ? ' is-in-cart' : '') + (canAfford ? '' : ' is-broke') + '" data-id="' + p.id + '">' +
				(p.tag ? '<span class="cmk-product__tag">' + esc(p.tag) + '</span>' : '') +
				(qty ? '<span class="cmk-product__qty">×' + qty + '</span>' : '') +
				'<span class="cmk-product__visual">' + visual(p) + '</span>' +
				(p.brand ? '<span class="cmk-product__brand">' + esc(p.brand) + '</span>' : '') +
				'<span class="cmk-product__name">' + esc(p.name) + '</span>' +
				'<span class="cmk-product__quip">' + esc(p.quip) + '</span>' +
				'<span class="cmk-product__price">' + money(p.price) + '</span>' +
				(cmp ? '<span class="cmk-product__cmp">' + esc(cmp) + '</span>' : '') +
				'<button type="button" class="cmk-btn cmk-btn--sm ' + (canAfford ? 'cmk-btn--ink' : 'cmk-btn--white') + ' cmk-product__add" data-add="' + p.id + '"' + (canAfford ? '' : ' disabled') + ' aria-label="' + esc(label + ': ' + fullName(p)) + '">' + label + '</button>' +
				'</li>';
		}).join('');
	}

	function renderHud() {
		leftEl.textContent = money(state.left);
		barEl.style.width = Math.max(0, (state.left / BUDGET) * 100) + '%';
		countEl.textContent = itemCount();
		var n = itemCount();
		finishBar.hidden = n === 0;
		if (n) {
			finishText.innerHTML = '<b>' + n + '</b> ' + (n === 1 ? 'item' : 'items') + ' · <b>' + money(spent()) + '</b> gone';
		}
	}

	function renderCart() {
		var ids = Object.keys(state.cart);
		cartEmpty.hidden = ids.length > 0;
		cartList.innerHTML = ids.map(function (id) {
			var p = byId(id);
			return '<li class="cmk-cart__item"><span class="cmk-cart__emoji" aria-hidden="true">' + p.e + '</span>' +
				'<span class="cmk-cart__name">' + esc(fullName(p)) + '<small>×' + state.cart[id] + ' · ' + money(p.price * state.cart[id]) + '</small></span>' +
				'<button type="button" class="cmk-cart__remove" data-remove="' + id + '" aria-label="Remove one ' + esc(fullName(p)) + '">−</button></li>';
		}).join('');
		cartTotal.textContent = money(spent());
		if (cartCompare) { cartCompare.textContent = spent() ? UI.compare(spent(), PACK) : ''; }
		root.querySelectorAll('[data-finish]').forEach(function (b) { b.disabled = ids.length === 0; });
	}

	function renderAll() { renderGrid(); renderHud(); renderCart(); }

	function react(p) {
		var share = p.price / BUDGET;
		var pool = p.silly ? REACT.silly : share < 0.004 ? REACT.cheap : share >= 0.1 ? REACT.big : REACT.mid;
		var cmp = UI.compare(p.price, PACK);
		reactEl.textContent = UI.pick(pool) + (cmp ? ' (' + cmp + ')' : '');
		reactEl.classList.remove('is-pop');
		void reactEl.offsetWidth;
		reactEl.classList.add('is-pop');
	}

	function buzz() {
		if (!UI.reduceMotion && navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
	}

	function checkMilestone() {
		var pct = spent() / BUDGET;
		while (state.milestone < MILESTONES.length && pct >= MILESTONES[state.milestone][0]) {
			UI.toast(MILESTONES[state.milestone][1]);
			state.milestone += 1;
		}
	}

	/* ---------- actions ---------- */
	function add(id, btn) {
		var p = byId(id);
		if (!p || p.price > state.left || state.finished) { return; }
		if (!state.started) {
			state.started = true;
			state.startAt = Date.now();
			UI.track('game_start', { game_id: GAME_ID });
		}
		var prevLeft = state.left;
		state.left -= p.price;
		state.cart[id] = (state.cart[id] || 0) + 1;
		UI.flyTo(btn, cartOpenBtn, p.e);
		UI.bump(countEl);
		buzz();
		react(p);
		renderGrid();
		renderCart();
		renderHud();
		UI.rollNumber(leftEl, prevLeft, state.left, money);
		UI.track('product_add', { game_id: GAME_ID, product_id: id, category: p.cat, price: p.price });

		if (state.left < CHEAPEST) {
			UI.toast(G.invert ? 'Budget khatam! Legendary spending.' : 'Budget khatam! Time to face the consequences.');
			state.milestone = MILESTONES.length;
		} else {
			checkMilestone();
		}
	}

	function remove(id) {
		var p = byId(id);
		if (!p || !state.cart[id]) { return; }
		state.cart[id] -= 1;
		if (!state.cart[id]) { delete state.cart[id]; }
		state.left += p.price;
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

	/* Score: spending a lot lowers "IQ/sanity"; for inverted games (efficiency/delusion) it raises the score. */
	function score() {
		var pct = spent() / BUDGET;
		if (G.invert) {
			return Math.max(1, Math.min(99, Math.round(pct * 92 + sillyCount() * 2)));
		}
		return Math.max(1, Math.min(99, Math.round(100 - pct * 80 - sillyCount() * 3 - (itemCount() > 15 ? 8 : 0))));
	}

	function finish() {
		if (!itemCount() || state.finished) { return; }
		state.finished = true;
		var ids = Object.keys(state.cart).sort(function (a, b) {
			return byId(b).price * state.cart[b] - byId(a).price * state.cart[a];
		});
		var result = {
			v: 3,
			g: GAME_ID,
			s: spent(),
			q: score(),
			n: itemCount(),
			e: ids.slice(0, 6).map(function (id) { return byId(id).e; }).join(''),
			t: fullName(byId(ids[0])),
			c: PACK.code,
			d: Math.round((Date.now() - (state.startAt || Date.now())) / 1000),
			id: Math.random().toString(36).slice(2, 9)
		};
		UI.finishGame(result);
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

	UI.gameView(GAME_ID, PACK.code);
	renderFilters();
	renderAll();
})();
