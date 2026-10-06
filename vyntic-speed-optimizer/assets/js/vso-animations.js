/*! Vyntic Speed Optimizer – Elementor entrance animations without waiting for JS */
(function (w, d) {
	'use strict';
	var items = d.querySelectorAll('.elementor-invisible');
	if (!items.length) { return; }
	function show(el) {
		var anim = 'fadeIn';
		try {
			var s = JSON.parse(el.getAttribute('data-settings') || '{}');
			anim = s._animation || s.animation || anim;
		} catch (e) {}
		el.classList.remove('elementor-invisible');
		if (anim && anim !== 'none') {
			el.classList.add('animated', anim);
		}
	}
	if (!('IntersectionObserver' in w)) {
		Array.prototype.forEach.call(items, show);
		return;
	}
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (entry.isIntersecting) {
				io.unobserve(entry.target);
				show(entry.target);
			}
		});
	});
	Array.prototype.forEach.call(items, function (el) { io.observe(el); });
})(window, document);
