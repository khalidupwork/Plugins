/* CLICK MAT KAR. — interaction layer: every visual answers the pointer.
 * Rule from the brand guide: idle motion stays subtle, user action gets the big response,
 * and everything switches off for prefers-reduced-motion.
 */
(function () {
	'use strict';

	var doc = document;
	var mm = function (q) { return window.matchMedia && window.matchMedia(q).matches; };
	var REDUCED = mm('(prefers-reduced-motion: reduce)');
	var FINE = mm('(hover: hover) and (pointer: fine)');
	var UI = window.CMKUI || {};
	var raf = window.requestAnimationFrame;

	if (REDUCED) { return; }

	function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
	function pick(list) { return list[Math.floor(Math.random() * list.length)]; }

	/* ------------------------------------------------------------------
	 * 1. 3D tilt + shine on cards, phones, products (delegated, so it also
	 *    works for cards the games re-render).
	 * ---------------------------------------------------------------- */
	var TILT = [
		'.cmk-game-card', '.cmk-chaos-card', '.cmk-step', '.cmk-product', '.cmk-result-card', '.cmk-phone',
		'.cmk-spin__machine', '.cmk-about-rule', '.cmk-footer__still', '.cmk-featured', '.cmk-challenge__stage', '.cmk-mini-item'
	].join(',');
	var tiltEl = null;

	function shine(el) {
		var s = el.querySelector(':scope > .cmk-shine');
		if (!s) {
			s = doc.createElement('span');
			s.className = 'cmk-shine';
			s.setAttribute('aria-hidden', 'true');
			el.appendChild(s);
		}
		return s;
	}
	function resetTilt(el) {
		if (!el) { return; }
		el.classList.remove('is-tilting');
		el.style.rotate = '';
	}
	function applyTilt(el, x, y) {
		var r = el.getBoundingClientRect();
		var px = (x - r.left) / r.width - 0.5;
		var py = (y - r.top) / r.height - 0.5;
		var big = r.width > 600;
		var max = big ? 3 : (el.classList.contains('cmk-mini-item') ? 14 : 9);
		var ang = Math.sqrt(px * px + py * py) * 2 * max;
		el.style.rotate = (-py).toFixed(3) + ' ' + px.toFixed(3) + ' 0 ' + ang.toFixed(2) + 'deg';
		el.style.setProperty('--mx', ((px + 0.5) * 100).toFixed(1) + '%');
		el.style.setProperty('--my', ((py + 0.5) * 100).toFixed(1) + '%');
		if (el.parentElement && !el.parentElement.style.perspective) { el.parentElement.style.perspective = '900px'; }
	}

	if (FINE) {
		doc.addEventListener('pointermove', function (e) {
			var el = e.target.closest && e.target.closest(TILT);
			// Phones hold tiltable mini items: the innermost one wins.
			if (el !== tiltEl) { resetTilt(tiltEl); tiltEl = el; if (el) { shine(el); el.classList.add('is-tilting'); } }
			if (el) {
				var x = e.clientX, y = e.clientY;
				raf(function () { if (tiltEl === el) { applyTilt(el, x, y); } });
			}
		}, { passive: true });
		doc.addEventListener('pointerleave', function () { resetTilt(tiltEl); tiltEl = null; });
	}

	/* Touch: a satisfying squish on press for everything tiltable. */
	doc.addEventListener('pointerdown', function (e) {
		if (e.pointerType === 'mouse') { return; }
		var el = e.target.closest && e.target.closest(TILT);
		if (!el) { return; }
		el.classList.add('is-pressed');
		var up = function () { el.classList.remove('is-pressed'); doc.removeEventListener('pointerup', up); doc.removeEventListener('pointercancel', up); };
		doc.addEventListener('pointerup', up);
		doc.addEventListener('pointercancel', up);
	}, { passive: true });

	/* ------------------------------------------------------------------
	 * 2. Draggable stickers, tags and chat bubbles. Throw them, they spring back.
	 * ---------------------------------------------------------------- */
	var DRAG = '.cmk-sticker, .cmk-tag, .cmk-bubble, .cmk-result-card__stamp, .cmk-product__tag, .cmk-pill';
	var LINES = ['Mana kiya tha.', 'Put it back.', 'Bad idea. Love it.', 'Wheee!', 'Oye! Careful.', 'That tickles.', 'Click mat kar!', 'Phir se? Fine.'];
	var drag = null;

	doc.addEventListener('pointerdown', function (e) {
		var el = e.target.closest && e.target.closest(DRAG);
		if (!el || e.button > 0) { return; }
		drag = { el: el, x: e.clientX, y: e.clientY, dx: 0, dy: 0, moved: false, id: e.pointerId };
		el.classList.add('is-grabbed');
		el.style.animationPlayState = 'paused';
		try { el.setPointerCapture(e.pointerId); } catch (err) {}
	});
	doc.addEventListener('pointermove', function (e) {
		if (!drag || e.pointerId !== drag.id) { return; }
		drag.dx = e.clientX - drag.x;
		drag.dy = e.clientY - drag.y;
		if (Math.abs(drag.dx) + Math.abs(drag.dy) > 4) { drag.moved = true; }
		var el = drag.el, dx = drag.dx, dy = drag.dy;
		raf(function () {
			el.style.translate = dx + 'px ' + dy + 'px';
			el.style.scale = '1.12';
		});
	}, { passive: true });
	function release() {
		if (!drag) { return; }
		var d = drag;
		drag = null;
		d.el.classList.remove('is-grabbed');
		if (d.moved) { d.el.dataset.justDragged = '1'; setTimeout(function () { delete d.el.dataset.justDragged; }, 60); }
		d.el.classList.add('is-springing');
		d.el.style.translate = '';
		d.el.style.scale = '';
		setTimeout(function () { d.el.classList.remove('is-springing'); d.el.style.animationPlayState = ''; }, 650);
		if (!d.moved) {
			pop(d.x, d.y, pick(LINES));
			d.el.classList.remove('is-boop');
			void d.el.offsetWidth;
			d.el.classList.add('is-boop');
		} else if (Math.abs(d.dx) + Math.abs(d.dy) > 120) {
			pop(d.x + d.dx, d.y + d.dy, pick(['Yeet!', 'Wapas aao!', 'Gravity says no.']));
		}
	}
	doc.addEventListener('pointerup', release);
	doc.addEventListener('pointercancel', release);
	// A drag that started on a link must not navigate.
	doc.addEventListener('click', function (e) {
		var el = e.target.closest && e.target.closest(DRAG);
		if (el && el.dataset.justDragged) { e.preventDefault(); e.stopPropagation(); }
	}, true);

	/* ------------------------------------------------------------------
	 * 3. Floating pop text + the "you were told not to click" counter.
	 * ---------------------------------------------------------------- */
	function pop(x, y, text) {
		var p = doc.createElement('span');
		p.className = 'cmk-pop';
		p.textContent = text;
		p.style.left = x + 'px';
		p.style.top = y + 'px';
		p.style.setProperty('--r', (Math.random() * 16 - 8).toFixed(1) + 'deg');
		doc.body.appendChild(p);
		setTimeout(function () { p.remove(); }, 1100);
	}
	window.CMKUI && (window.CMKUI.pop = pop);

	var clicks = 0;
	var counter = null;
	var COUNT_LINES = ['Concerning.', 'We did say no.', 'Ammi ko bataun?', 'Okay, now play a game.', 'Your finger needs a break.', 'Legend behaviour.'];
	var IGNORE = 'a, button, input, select, textarea, label, [data-add], [data-opt], .cmk-cart, ' + DRAG;
	doc.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest(IGNORE)) { return; }
		if (doc.body.classList.contains('cmk-menu-open')) { return; }
		clicks += 1;
		pop(e.clientX, e.clientY, clicks === 1 ? 'Mana kiya tha!' : pick(['Click mat kar!', 'Again?!', 'Bas karo.', 'Oye!', 'Mana kiya tha.', '🤡']));
		if (clicks >= 5) {
			if (!counter) {
				counter = doc.createElement('button');
				counter.type = 'button';
				counter.className = 'cmk-click-counter';
				counter.addEventListener('click', function () {
					var url = (UI.config && (UI.config.shopUrl || UI.config.gamesUrl)) || '/';
					window.location.href = url;
				});
				doc.body.appendChild(counter);
			}
			counter.innerHTML = '<b>' + clicks + '</b> clicks. ' + COUNT_LINES[Math.min(COUNT_LINES.length - 1, Math.floor((clicks - 5) / 3))] + ' <span>Play →</span>';
			counter.classList.remove('is-bump');
			void counter.offsetWidth;
			counter.classList.add('is-bump');
			if (UI.track && (clicks === 5 || clicks % 25 === 0)) { UI.track('cta_click', { label: 'empty_clicks_' + clicks }); }
		}
	});

	/* ------------------------------------------------------------------
	 * 4. Magnetic CTAs (big buttons lean towards the cursor).
	 * ---------------------------------------------------------------- */
	if (FINE) {
		var magnet = null;
		doc.addEventListener('pointermove', function (e) {
			var el = e.target.closest && e.target.closest('.cmk-btn--lg, .cmk-nav__cta, .cmk-hud__cart');
			if (magnet && magnet !== el) { magnet.style.translate = ''; magnet = null; }
			if (!el || drag) { return; }
			magnet = el;
			var r = el.getBoundingClientRect();
			var dx = (e.clientX - (r.left + r.width / 2)) * 0.18;
			var dy = (e.clientY - (r.top + r.height / 2)) * 0.3;
			raf(function () { if (magnet === el) { el.style.translate = clamp(dx, -10, 10).toFixed(1) + 'px ' + clamp(dy, -8, 8).toFixed(1) + 'px'; } });
		}, { passive: true });
	}

	/* ------------------------------------------------------------------
	 * 5. Hero: depth parallax + bouncy wordmark letters + logo "press" sequence.
	 * ---------------------------------------------------------------- */
	var hero = doc.querySelector('.cmk-hero');
	if (hero && FINE) {
		var layers = [
			[hero.querySelector('.cmk-hero__stickers'), 26],
			[hero.querySelector('.cmk-hero__stage'), -10],
			[hero.querySelector('.cmk-wordmark__cursor'), 14]
		].filter(function (l) { return l[0]; });
		hero.addEventListener('pointermove', function (e) {
			var r = hero.getBoundingClientRect();
			var px = (e.clientX - r.left) / r.width - 0.5;
			var py = (e.clientY - r.top) / r.height - 0.5;
			raf(function () {
				layers.forEach(function (l) { if (!(drag && l[0].contains(drag.el))) { l[0].style.translate = (px * l[1]).toFixed(1) + 'px ' + (py * l[1]).toFixed(1) + 'px'; } });
			});
		}, { passive: true });
		hero.addEventListener('pointerleave', function () { layers.forEach(function (l) { l[0].style.translate = ''; }); });
	}

	doc.querySelectorAll('.cmk-wordmark--xl .cmk-wordmark__click, .cmk-wordmark--xl .cmk-wordmark__mat').forEach(function (part) {
		var dot = part.querySelector('.cmk-wordmark__dot');
		var text = part.firstChild && part.firstChild.nodeType === 3 ? part.firstChild.textContent : part.textContent;
		var frag = doc.createDocumentFragment();
		text.split('').forEach(function (ch, i) {
			var s = doc.createElement('span');
			s.className = 'cmk-letter';
			s.style.setProperty('--i', i);
			s.textContent = ch === ' ' ? ' ' : ch;
			frag.appendChild(s);
		});
		if (part.firstChild && part.firstChild.nodeType === 3) { part.replaceChild(frag, part.firstChild); }
		else if (!dot) { part.textContent = ''; part.appendChild(frag); }
	});
	doc.querySelectorAll('.cmk-wordmark--xl').forEach(function (wm) {
		wm.style.cursor = 'pointer';
		wm.addEventListener('click', function (e) {
			wm.classList.remove('is-pressed');
			void wm.offsetWidth;
			wm.classList.add('is-pressed');
			pop(e.clientX, e.clientY - 20, pick(['We told you not to click.', 'Mana kiya tha.', 'Click mat kar!']));
			e.stopPropagation();
		});
	});

	/* ------------------------------------------------------------------
	 * 6. Ticker speeds up with scroll velocity.
	 * ---------------------------------------------------------------- */
	var track = doc.querySelector('.cmk-ticker__track');
	if (track && track.getAnimations) {
		var lastY = window.scrollY, speed = 1, idleTimer;
		window.addEventListener('scroll', function () {
			var v = Math.abs(window.scrollY - lastY);
			lastY = window.scrollY;
			speed = clamp(1 + v / 12, 1, 6);
			track.getAnimations().forEach(function (a) { a.playbackRate = speed; });
			clearTimeout(idleTimer);
			idleTimer = setTimeout(function () { track.getAnimations().forEach(function (a) { a.playbackRate = 1; }); }, 180);
		}, { passive: true });
	}

	/* ------------------------------------------------------------------
	 * 7. Section titles slide in with a little skew when they enter.
	 * ---------------------------------------------------------------- */
	if ('IntersectionObserver' in window) {
		var heads = doc.querySelectorAll('.cmk-section .cmk-h2, .cmk-section .cmk-h1, .cmk-display');
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -12% 0px' });
		heads.forEach(function (h) {
			if (h.getBoundingClientRect().top > window.innerHeight) { h.classList.add('cmk-head-reveal'); io.observe(h); }
		});
	}

	/* Emoji in cards: hover/tap makes them do a little jump. */
	doc.addEventListener('pointerover', function (e) {
		var em = e.target.closest && e.target.closest('.cmk-game-card__emoji, .cmk-chaos-card__emoji, .cmk-step__icon, .cmk-quiz__q-emoji, .cmk-spin__emoji, .cmk-about-rule__emoji, .cmk-result-card__items');
		if (!em || em.classList.contains('is-jump')) { return; }
		em.classList.add('is-jump');
		setTimeout(function () { em.classList.remove('is-jump'); }, 600);
	});
})();
