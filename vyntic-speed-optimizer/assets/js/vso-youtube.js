/*! Vyntic Speed Optimizer: YouTube click-to-load */
(function (d) {
	'use strict';
	d.addEventListener('click', function (e) {
		var box = e.target.closest && e.target.closest('.vso-yt');
		if (!box) { return; }
		e.preventDefault();
		var f = d.createElement('iframe');
		var src = box.getAttribute('data-src');
		f.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
		f.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
		f.setAttribute('allowfullscreen', '');
		f.setAttribute('frameborder', '0');
		f.title = box.getAttribute('data-title') || 'YouTube video';
		f.className = box.getAttribute('data-class') || '';
		f.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0';
		box.innerHTML = '';
		box.appendChild(f);
	});
})(document);
