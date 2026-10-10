/* CLICK MAT KAR. — shop engine. Runs every shopping game from its pack in games-data.js. */
(function () {
	'use strict';

	var UI = window.CMKUI;
	var root = document.querySelector('[data-shop-game]');
	if (!UI || !root || !window.CMK_GAMES) { return; }

	var GAME_ID = root.getAttribute('data-game-id');
	var G = window.CMK_GAMES[GAME_ID];
	if (!G || G.engine !== 'shop') { return; }

	var PACK = G.currency ? UI.packs[G.currency] : UI.locale;
	var BUDGET = G.budget;
	var PRODUCTS = G.items;
	var money = function (base) { return UI.moneyIn(PACK, base); };

	var REACT = {
		cheap: ['Itna sasta? Dream bigger.', 'Cute. Now buy something stupid.', 'That is pocket change for you now.'],
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

	/* Copy from the pack. */
	root.querySelectorAll('[data-budget-label]').forEach(function (el) { el.textContent = UI.bigMoney(PACK, BUDGET) + '.'; });
	if (G.intro) { $('[data-intro]').textContent = G.intro; }
	if (G.hint) { reactEl.textContent = G.hint; }
	if (G.checkout) { root.querySelectorAll('[data-finish-label]').forEach(function (el) { el.textContent = G.checkout; }); }

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
	function esc(str) { return String(str).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	/* ---------- render ---------- */
	function renderFilters() {
		filters.innerHTML = G.categories.map(function (c) {
			return '<button type="button" class="cmk-filter' + (state.filter === c[0] ? ' is-on' : '') + '" data-filter="' + c[0] + '" aria-pressed="' + (state.filter === c[0]) + '"><span aria-hidden="true">' + c[2] + '</span> ' + esc(c[1]) + '</button>';
		}).join('');
	}

	function renderGrid() {
		grid.innerHTML = PRODUCTS.filter(function (p) {
			return state.filter === 'all' || p[3] === state.filter;
		}).map(function (p) {
			var qty = state.cart[p[0]] || 0;
			var canAfford = p[4] <= state.left;
			var label = canAfford ? (qty ? (G.qty ? 'Buy another' : 'Add another') : 'Add to cart') : 'Too broke 😭';
			return '<li class="cmk-product' + (qty ? ' is-in-cart' : '') + '" data-id="' + p[0] + '">' +
				(qty ? '<span class="cmk-product__qty">×' + qty + '</span>' : '') +
				'<span class="cmk-product__emoji" aria-hidden="true">' + p[1] + '</span>' +
				'<span class="cmk-product__name">' + esc(p[2]) + '</span>' +
				'<span class="cmk-product__quip">' + esc(p[5]) + '</span>' +
				'<span class="cmk-product__price">' + money(p[4]) + '</span>' +
				'<button type="button" class="cmk-btn cmk-btn--sm ' + (canAfford ? 'cmk-btn--ink' : 'cmk-btn--white') + ' cmk-product__add" data-add="' + p[0] + '"' + (canAfford ? '' : ' disabled') + '>' + label + '</button>' +
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
			finishText.innerHTML = '<b>' + n + '</b> ' + (n === 1 ? 'item' : 'items') + ' · <b>' + money(spent()) + '</b> wasted';
		}
	}

	function renderCart() {
		var ids = Object.keys(state.cart);
		cartEmpty.hidden = ids.length > 0;
		cartList.innerHTML = ids.map(function (id) {
			var p = byId(id);
			return '<li class="cmk-cart__item"><span class="cmk-cart__emoji" aria-hidden="true">' + p[1] + '</span>' +
				'<span class="cmk-cart__name">' + esc(p[2]) + '<small>×' + state.cart[id] + ' · ' + money(p[4] * state.cart[id]) + '</small></span>' +
				'<button type="button" class="cmk-cart__remove" data-remove="' + id + '" aria-label="Remove one ' + esc(p[2]) + '">−</button></li>';
		}).join('');
		cartTotal.textContent = money(spent());
		root.querySelectorAll('[data-finish]').forEach(function (b) { b.disabled = ids.length === 0; });
	}

	function renderAll() { renderGrid(); renderHud(); renderCart(); }

	function react(p) {
		var share = p[4] / BUDGET;
		var pool = p[6] ? REACT.silly : share < 0.004 ? REACT.cheap : share >= 0.1 ? REACT.big : REACT.mid;
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
		var prevLeft = state.left;
		state.left -= p[4];
		state.cart[id] = (state.cart[id] || 0) + 1;
		UI.flyTo(btn, cartOpenBtn, p[1]);
		UI.bump(countEl);
		react(p);
		renderGrid();
		renderCart();
		renderHud();
		UI.rollNumber(leftEl, prevLeft, state.left, money);
		UI.track('product_add', { game_id: GAME_ID, product_id: id, category: p[3], price: p[4] });

		var cheapest = Math.min.apply(null, PRODUCTS.map(function (x) { return x[4]; }));
		if (state.left < cheapest) {
			UI.toast(G.invert ? 'Budget khatam! Legendary spending.' : 'Budget khatam! Time to face the consequences.');
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

	/* Score: spending a lot lowers "IQ/sanity"; for inverted games (efficiency/delusion) it raises the score. */
	function score() {
		var pct = spent() / BUDGET;
		if (G.invert) {
			var silly = 0;
			Object.keys(state.cart).forEach(function (id) { if (byId(id)[6]) { silly += state.cart[id]; } });
			return Math.max(1, Math.min(99, Math.round(pct * 92 + silly * 2)));
		}
		var s = 0;
		Object.keys(state.cart).forEach(function (id) { if (byId(id)[6]) { s += state.cart[id]; } });
		return Math.max(1, Math.min(99, Math.round(100 - pct * 80 - s * 3 - (itemCount() > 15 ? 8 : 0))));
	}

	function finish() {
		if (!itemCount() || state.finished) { return; }
		state.finished = true;
		var ids = Object.keys(state.cart).sort(function (a, b) {
			return byId(b)[4] * state.cart[b] - byId(a)[4] * state.cart[a];
		});
		var result = {
			v: 2,
			g: GAME_ID,
			s: spent(),
			q: score(),
			n: itemCount(),
			e: ids.slice(0, 6).map(function (id) { return byId(id)[1]; }).join(''),
			t: byId(ids[0])[2],
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
