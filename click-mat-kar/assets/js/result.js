/* CLICK MAT KAR. — result page: render card from ?r=, share, story PNG, challenge link. */
(function () {
	'use strict';

	var UI = window.CMKUI;
	var page = document.querySelector('[data-result-page]');
	if (!UI || !page) { return; }

	var CFG = UI.config;
	var params = new URLSearchParams(window.location.search);
	var r = UI.decode(params.get('r'));
	var $ = function (sel) { return page.querySelector(sel); };

	if (!r || typeof r.s !== 'number' || r.s < 0) {
		$('[data-result-empty]').hidden = false;
		return;
	}

	/* ---------- clamp untrusted payload ---------- */
	r.q = Math.max(1, Math.min(99, parseInt(r.q, 10) || 1));
	r.n = Math.max(0, parseInt(r.n, 10) || 0);
	r.e = String(r.e || '').slice(0, 24);
	r.t = String(r.t || '').slice(0, 60);
	r.c = UI.packs[r.c] ? r.c : UI.locale.code;
	r.s = Math.min(r.s, 1e12);

	var mine = false;
	try {
		var last = JSON.parse(sessionStorage.getItem('cmk_last_result') || 'null');
		mine = !!(last && last.id && last.id === r.id);
	} catch (e) {}

	var D = UI.describe(r);
	var R = D.R;
	var tier = D.tier;
	var spentText = D.big;
	var isShop = D.G.engine === 'shop';
	var scoreLabel = R.scoreLabel || 'Score';
	var topLabel = isShop ? 'Worst buy: ' : 'Worst answer: ';
	var stampTop = isShop && !D.G.invert ? 'Regret' : 'Mana kiya';
	var stampBottom = isShop && !D.G.invert ? Math.max(1, Math.min(99, 100 - r.q)) + '%' : 'tha.';
	var subText = R.sub || '';
	var labelText = R.label || 'My result';

	/* ---------- render ---------- */
	$('[data-r-label]').textContent = labelText;
	$('[data-r-spent]').textContent = spentText;
	$('[data-r-sub]').textContent = subText;
	$('[data-r-items]').textContent = r.e;
	$('[data-r-top]').textContent = r.t ? topLabel + r.t : '';
	$('[data-r-score-label]').textContent = scoreLabel;
	$('[data-r-iq]').textContent = r.q + '/100';
	$('[data-r-stamp]').innerHTML = stampTop + '<br>' + stampBottom;
	$('[data-r-title]').textContent = tier[1];
	$('[data-r-verdict]').textContent = (mine ? '' : 'Your friend\'s result. ') + tier[2];
	$('[data-r-game]').textContent = (CFG.gameTitles && CFG.gameTitles[r.g]) || '';
	$('[data-result-card]').hidden = false;
	$('[data-result-copy]').hidden = false;

	var gameUrl = (CFG.gameUrls && CFG.gameUrls[r.g]) || CFG.shopUrl || CFG.home;
	var challengeUrl = gameUrl + (gameUrl.indexOf('?') > -1 ? '&' : '?') + 'challenge=' + UI.encode({ s: r.s, q: r.q, c: r.c, id: r.id });
	var resultUrl = window.location.href.split('#')[0];
	var shareText = (R.share || 'My result: {big}. {q}/100. Beat me:').replace('{big}', spentText).replace('{q}', r.q);

	$('[data-again]').href = gameUrl;
	$('[data-beat]').href = challengeUrl;

	if (!mine) {
		$('[data-share-owner]').hidden = true;
		$('[data-share-visitor]').hidden = false;
		$('[data-again]').textContent = 'Play it yourself';
	}

	/* ---------- share ---------- */
	var wa = $('[data-share="whatsapp"]');
	var x = $('[data-share="x"]');
	wa.href = 'https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + resultUrl);
	x.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(resultUrl);

	var nativeBtn = $('[data-share="native"]');
	if (navigator.share) { nativeBtn.hidden = false; }

	function copy(text, msg) {
		var done = function () { UI.toast(msg); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
		} else {
			fallbackCopy(text);
			done();
		}
	}
	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		ta.remove();
	}

	page.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-share]');
		if (btn) {
			var channel = btn.getAttribute('data-share');
			UI.track('result_share', { game_id: r.g || '', channel: channel, result_id: r.id || '' });
			if (channel === 'native') {
				e.preventDefault();
				drawCard().then(function (blob) {
					var data = { title: 'Click Mat Kar', text: shareText, url: resultUrl };
					var file = blob && window.File ? new File([blob], 'click-mat-kar-result.png', { type: 'image/png' }) : null;
					if (file && navigator.canShare && navigator.canShare({ files: [file] })) { data.files = [file]; }
					return navigator.share(data);
				}).catch(function () {});
			} else if (channel === 'copy') {
				copy(resultUrl, 'Link copied. Go ruin someone\'s day.');
			} else if (channel === 'download') {
				drawCard().then(function (blob) {
					if (!blob) { return; }
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = 'click-mat-kar-result.png';
					document.body.appendChild(a);
					a.click();
					setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
					UI.toast('Story card saved. Post it. Embarrass yourself.');
				});
			}
			return;
		}
		if (e.target.closest('[data-challenge]')) {
			UI.track('challenge_create', { game_id: r.g || '', result_id: r.id || '' });
			if (navigator.share) {
				navigator.share({ title: 'Click Mat Kar challenge', text: labelText + ' ' + spentText + '. Bet you can\'t do worse 😈', url: challengeUrl }).catch(function () {});
			} else {
				copy(challengeUrl, 'Challenge link copied. Send it to your worst friend.');
			}
			return;
		}
		if (e.target.closest('[data-play-another]')) {
			UI.track('play_another', { from_game: r.g || '', to_game: 'library' });
		}
	});

	/* ---------- 1080x1920 story card on canvas ---------- */
	function drawCard() {
		var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
		return ready.then(function () {
			return new Promise(function (resolve) {
				var W = 1080, H = 1920;
				var c = document.createElement('canvas');
				c.width = W; c.height = H;
				var g = c.getContext('2d');
				var INK = '#111111', LIME = '#D7FF3F', PINK = '#FF72B6', WHITE = '#FFFFFF';
				var DISPLAY = '"Bowlby One SC", "Arial Black", Impact, sans-serif';
				var BODY = '"DM Sans", Arial, sans-serif';

				function rr(x, y, w, h, rad) {
					g.beginPath();
					g.moveTo(x + rad, y);
					g.arcTo(x + w, y, x + w, y + h, rad);
					g.arcTo(x + w, y + h, x, y + h, rad);
					g.arcTo(x, y + h, x, y, rad);
					g.arcTo(x, y, x + w, y, rad);
					g.closePath();
				}
				function fit(text, font, size, max, weight) {
					do { g.font = (weight ? weight + ' ' : '') + size + 'px ' + font; size -= 4; } while (g.measureText(text).width > max && size > 20);
				}

				g.fillStyle = LIME; g.fillRect(0, 0, W, H);
				// dotted texture
				g.fillStyle = 'rgba(17,17,17,.08)';
				for (var yy = 20; yy < H; yy += 44) { for (var xx = 20; xx < W; xx += 44) { g.beginPath(); g.arc(xx, yy, 3, 0, 7); g.fill(); } }

				g.save();
				g.translate(W / 2, H / 2);
				g.rotate(-0.035);
				g.translate(-W / 2, -H / 2);
				// card shadow + body
				g.fillStyle = INK; rr(110, 250, 880, 1360, 56); g.fill();
				g.fillStyle = WHITE; rr(90, 230, 880, 1360, 56); g.fill();
				g.lineWidth = 8; g.strokeStyle = INK; g.stroke();

				// logo chip
				g.fillStyle = LIME; rr(150, 290, 420, 84, 18); g.fill(); g.lineWidth = 5; g.stroke();
				g.fillStyle = INK; g.font = '44px ' + DISPLAY; g.textBaseline = 'middle'; g.fillText('CLICK MAT KAR.', 172, 334);

				g.textBaseline = 'alphabetic';
				g.font = '700 46px ' + BODY; g.fillText(labelText.toUpperCase(), 150, 500);
				fit(spentText, DISPLAY, 140, 760); g.fillText(spentText, 150, 640);
				fit(subText, BODY, 44, 760, '500'); g.fillText(subText, 150, 730);

				g.font = '120px ' + BODY; g.fillText(r.e, 150, 1010);
				if (r.t) { fit(topLabel + r.t, BODY, 40, 760, '700'); g.fillText(topLabel + r.t, 150, 1100); }

				g.setLineDash([18, 14]); g.lineWidth = 5; g.beginPath(); g.moveTo(150, 1190); g.lineTo(910, 1190); g.stroke(); g.setLineDash([]);
				fit(scoreLabel.toUpperCase(), BODY, 40, 360, '800'); g.fillText(scoreLabel.toUpperCase(), 150, 1290);
				fit(r.q + '/100', DISPLAY, 112, 380); g.textAlign = 'right'; g.fillText(r.q + '/100', 910, 1320); g.textAlign = 'left';
				g.font = '600 38px ' + BODY; g.fillText(tier[1], 150, 1420);
				g.font = '700 40px ' + BODY; g.fillText('Beat me. clickmatkar.com', 150, 1530);
				g.restore();

				// stamp
				g.save(); g.translate(860, 1640); g.rotate(0.17);
				g.fillStyle = PINK; rr(-150, -80, 300, 160, 28); g.fill(); g.lineWidth = 7; g.strokeStyle = INK; g.stroke();
				g.fillStyle = INK; g.textAlign = 'center'; g.font = '46px ' + DISPLAY; fit(stampTop.toUpperCase(), DISPLAY, 46, 250); g.fillText(stampTop.toUpperCase(), 0, -10); g.fillText(String(stampBottom).toUpperCase(), 0, 48);
				g.restore();

				g.fillStyle = INK; g.textAlign = 'center'; fit('WE TOLD YOU NOT TO CLICK.', DISPLAY, 64, 960); g.fillText('WE TOLD YOU NOT TO CLICK.', W / 2, 150);
				var footer = (CFG.gameTitles && CFG.gameTitles[r.g]) ? 'Play ' + CFG.gameTitles[r.g] + ' on clickmatkar.com' : 'clickmatkar.com'; fit(footer, BODY, 40, 940, '700'); g.fillText(footer, W / 2, 1830);

				if (c.toBlob) { c.toBlob(resolve, 'image/png'); } else { resolve(null); }
			});
		});
	}
})();
