/*! Vyntic Speed Optimizer: prefetch links on hover */
(function (w, d) {
	'use strict';
	if (!('IntersectionObserver' in w) || (navigator.connection && (navigator.connection.saveData || /2g/.test(navigator.connection.effectiveType || '')))) {
		return;
	}
	var done = {};
	var timer = null;
	function prefetch(a) {
		var url = a.href;
		if (!url || done[url] || a.origin !== location.origin || a.hasAttribute('download') || a.target === '_blank') {
			return;
		}
		if (url.split('#')[0] === location.href.split('#')[0] || /wp-admin|wp-login|logout|\/cart|\/checkout|add-to-cart|\.(zip|pdf|jpe?g|png|gif|webp|mp4)(\?|$)/i.test(url)) {
			return;
		}
		done[url] = true;
		var l = d.createElement('link');
		l.rel = 'prefetch';
		l.href = url;
		d.head.appendChild(l);
	}
	d.addEventListener('mouseover', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) { return; }
		clearTimeout(timer);
		timer = setTimeout(function () { prefetch(a); }, 65);
	}, { passive: true });
	d.addEventListener('mouseout', function () { clearTimeout(timer); }, { passive: true });
	d.addEventListener('touchstart', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (a) { prefetch(a); }
	}, { passive: true });
})(window, document);
