/*! Vyntic Speed Optimizer – delayed script loader */
(function (w, d) {
	'use strict';
	var cfg = w.vsoConfig || {};
	var events = ['mouseover', 'mousemove', 'keydown', 'touchstart', 'touchmove', 'wheel', 'scroll', 'click'];
	var started = false;
	var finished = false;
	var clicks = [];

	function loadCss() {
		var links = d.querySelectorAll('link[data-vso-css]');
		for (var i = 0; i < links.length; i++) {
			links[i].rel = 'stylesheet';
			links[i].removeAttribute('data-vso-css');
		}
	}

	function listen(add) {
		for (var i = 0; i < events.length; i++) {
			(add ? w.addEventListener : w.removeEventListener).call(w, events[i], trigger, { passive: true });
		}
	}

	// Keep clicks made while scripts are still loading and replay them afterwards
	// (plain links are left alone so navigation is never blocked).
	function onClick(e) {
		if (finished || !e.isTrusted) {
			return;
		}
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (a) {
			var href = a.getAttribute('href') || '';
			if (href.charAt(0) !== '#' && href.indexOf('javascript:') !== 0) {
				return;
			}
		}
		e.preventDefault();
		e.stopPropagation();
		if (clicks.indexOf(e.target) === -1) {
			clicks.push(e.target);
		}
	}

	function call(fn, ctx, ev) {
		try {
			if (typeof fn === 'function') {
				fn.call(ctx, ev);
			} else if (fn && typeof fn.handleEvent === 'function') {
				fn.handleEvent(ev);
			}
		} catch (err) {
			if (w.console) {
				w.console.error(err);
			}
		}
	}

	function trigger() {
		if (started) {
			return;
		}
		started = true;
		listen(false);
		loadCss();
		if (cfg.delay) {
			d.addEventListener('click', onClick, true);
			run();
		}
	}

	function run() {
		var scripts = Array.prototype.slice.call(d.querySelectorAll('script[type="vso/javascript"]'));
		var origDocAdd = d.addEventListener;
		var origWinAdd = w.addEventListener;
		var origWrite = d.write;
		var origWriteln = d.writeln;
		var origOnload = w.onload;
		var queue = { ready: [], load: [] };
		var current = null;

		// Download everything in parallel, execute in the original order.
		var frag = d.createDocumentFragment();
		scripts.forEach(function (s) {
			var src = s.getAttribute('data-vso-src');
			if (src) {
				var l = d.createElement('link');
				l.rel = s.getAttribute('data-vso-type') === 'module' ? 'modulepreload' : 'preload';
				l.as = 'script';
				l.href = src;
				if (s.hasAttribute('crossorigin')) {
					l.crossOrigin = s.getAttribute('crossorigin');
				}
				frag.appendChild(l);
			}
		});
		d.head.appendChild(frag);

		// Scripts that wait for DOMContentLoaded / load would wait forever, so collect
		// their listeners and fire them once every delayed script has run.
		d.addEventListener = function (type, fn, opts) {
			if (type === 'DOMContentLoaded') {
				queue.ready.push([fn, d]);
				return;
			}
			return origDocAdd.apply(this, arguments);
		};
		w.addEventListener = function (type, fn, opts) {
			if (type === 'DOMContentLoaded') {
				queue.ready.push([fn, w]);
				return;
			}
			if (type === 'load') {
				queue.load.push([fn, w]);
				return;
			}
			return origWinAdd.apply(this, arguments);
		};
		d.write = d.writeln = function () {
			var html = Array.prototype.join.call(arguments, '');
			if (current && current.parentNode) {
				current.insertAdjacentHTML('afterend', html);
			}
		};

		function next(i) {
			while (i < scripts.length) {
				var old = scripts[i];
				var s = d.createElement('script');
				var src = old.getAttribute('data-vso-src');
				var type = old.getAttribute('data-vso-type');
				for (var a = 0; a < old.attributes.length; a++) {
					var name = old.attributes[a].name;
					if (name !== 'type' && name !== 'data-vso-src' && name !== 'data-vso-type') {
						s.setAttribute(name, old.attributes[a].value);
					}
				}
				if (type) {
					s.type = type;
				}
				current = s;
				if (src) {
					var isAsync = old.hasAttribute('async');
					if (!isAsync) {
						s.async = false;
						s.onload = s.onerror = (function (n) {
							return function () { next(n); };
						})(i + 1);
					}
					s.src = src;
					old.parentNode.replaceChild(s, old);
					if (!isAsync) {
						return;
					}
				} else {
					s.text = old.text;
					old.parentNode.replaceChild(s, old);
				}
				i++;
			}
			done();
		}

		function done() {
			d.addEventListener = origDocAdd;
			w.addEventListener = origWinAdd;
			d.write = origWrite;
			d.writeln = origWriteln;

			var ready = new Event('DOMContentLoaded', { bubbles: true });
			queue.ready.forEach(function (item) { call(item[0], item[1], ready); });

			// jQuery keeps one native listener per element and dispatches its own
			// handlers, so window "load" handlers bound through jQuery are fired via
			// jQuery itself (exactly once), and its native handle is skipped below.
			var jq = w.jQuery;
			var jqHandle = null;
			if (jq && jq._data) {
				try { jqHandle = (jq._data(w) || {}).handle || null; } catch (e) {}
			}

			var load = new Event('load');
			queue.load.forEach(function (item) {
				if (!jqHandle || item[0] !== jqHandle) {
					call(item[0], item[1], load);
				}
			});
			if (typeof w.onload === 'function' && w.onload !== origOnload) {
				call(w.onload, w, load);
			}
			if (jqHandle) {
				try { jq(w).trigger('load'); } catch (e) {}
			}

			finished = true;
			d.removeEventListener('click', onClick, true);
			d.dispatchEvent(new Event('vso:loaded'));
			w.vsoLoaded = true;

			setTimeout(function () {
				clicks.forEach(function (el) {
					if (el && typeof el.click === 'function') {
						el.click();
					}
				});
				clicks = [];
			}, 50);
		}

		next(0);
	}

	listen(true);

	function onLoad() {
		if (cfg.css === 'onload') {
			setTimeout(loadCss, 100);
		}
		if (cfg.timeout > 0) {
			setTimeout(trigger, cfg.timeout * 1000);
		}
	}
	if (d.readyState === 'complete') {
		onLoad();
	} else {
		w.addEventListener('load', onLoad);
	}
	// Restored scroll position / back-forward cache: start right away.
	w.addEventListener('pageshow', function (e) {
		if (e.persisted) {
			trigger();
		}
	});
})(window, document);
