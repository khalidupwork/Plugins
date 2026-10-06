/* Vyntic Hub: release uploads on the plugin edit screen */
(function ($) {
	'use strict';
	var app = $('#vh-releases-app');
	if (!app.length) { return; }
	var cfg = window.vhAdmin || {};
	var post = app.data('post');
	var nonce = app.data('nonce');
	var msg = $('#vh-release-msg');
	var frame;

	function notice(text, type) {
		msg.html($('<div class="notice inline"><p></p></div>').addClass('notice-' + type).find('p').text(text).end());
	}

	function bindTable() {
		app.find('.vh-act').off('click').on('click', function () {
			var btn = $(this);
			var act = btn.data('act');
			if (act === 'delete' && !window.confirm(cfg.i18n.confirm)) { return; }
			btn.prop('disabled', true);
			$.post(cfg.ajax, { action: 'vh_release_action', post: post, nonce: nonce, act: act, version: btn.data('version') })
				.done(function (r) {
					if (r && r.success) { $('#vh-release-table').html(r.data.table); bindTable(); }
					else { notice((r && r.data && r.data.message) || 'Error', 'error'); btn.prop('disabled', false); }
				})
				.fail(function () { notice('Error', 'error'); btn.prop('disabled', false); });
		});
	}
	bindTable();

	$('#vh-upload-btn').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: cfg.i18n.choose,
				button: { text: cfg.i18n.use },
				library: { type: 'application/zip' },
				multiple: false
			});
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				notice(cfg.i18n.working, 'info');
				$.post(cfg.ajax, {
					action: 'vh_add_release',
					post: post,
					nonce: nonce,
					attachment: file.id,
					changelog: $('#vh-changelog').val()
				}).done(function (r) {
					if (r && r.success) {
						notice(r.data.message, 'success');
						$('#vh-release-table').html(r.data.table);
						$('#vh-changelog').val('');
						if (r.data.slug) { $('#vh-slug').val(r.data.slug).prop('readonly', true); }
						if (r.data.title) {
							$('#title').val(r.data.title).trigger('input');
							if (window.wp && wp.data && wp.data.dispatch) {
								try { wp.data.dispatch('core/editor').editPost({ title: r.data.title }); } catch (err) {}
							}
						}
						bindTable();
					} else {
						notice((r && r.data && r.data.message) || 'Error', 'error');
					}
				}).fail(function () { notice('Error', 'error'); });
			});
		}
		frame.open();
	});
})(jQuery);
