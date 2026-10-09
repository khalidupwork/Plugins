/* Talkwyn Hub: start-trial form over AJAX. Without JavaScript the form still posts normally. */
( function () {
	'use strict';
	var cfg = window.twhTrial || {};

	function show( box, text ) {
		var note = box.querySelector( '.twh-notice' );
		if ( ! note ) {
			return;
		}
		note.textContent = text || '';
		note.hidden = ! text;
	}

	function bind( form ) {
		if ( form.getAttribute( 'data-twh-bound' ) ) {
			return;
		}
		form.setAttribute( 'data-twh-bound', '1' );
		var box = form.closest( '.twh-trial' ) || form.parentNode;
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! form.reportValidity() ) {
				return;
			}
			var btn = form.querySelector( 'button[type="submit"]' );
			var label = btn ? btn.textContent : '';
			if ( btn ) {
				btn.disabled = true;
				btn.textContent = cfg.sending || '...';
			}
			show( box, '' );
			var done = function () {
				if ( btn ) {
					btn.disabled = false;
					btn.textContent = label;
				}
			};
			// A fresh nonce first: cached pages can hold an old one.
			fetch( cfg.ajax + '?action=twh_trial_nonce', { credentials: 'same-origin', cache: 'no-store' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( n ) {
					var data = new FormData( form );
					data.set( 'action', 'twh_start_trial' );
					if ( n && n.success && n.data && n.data.nonce ) {
						data.set( '_twh_nonce', n.data.nonce );
					}
					return fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } );
				} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					var d = ( res && res.data ) || {};
					if ( res && res.success && d.html ) {
						box.innerHTML = d.html;
						var title = box.querySelector( '.twh-trial-done__title' );
						if ( title ) {
							title.setAttribute( 'tabindex', '-1' );
							title.focus();
						}
						document.dispatchEvent( new CustomEvent( 'twh:trial_requested' ) );
						return;
					}
					show( box, d.message || cfg.error );
					document.dispatchEvent( new CustomEvent( 'twh:trial_error', { detail: { form: form } } ) );
					done();
				} )
				.catch( function () {
					show( box, cfg.error );
					document.dispatchEvent( new CustomEvent( 'twh:trial_error', { detail: { form: form } } ) );
					done();
				} );
		} );
	}

	function scan( root ) {
		( root || document ).querySelectorAll( 'form[data-twh-trial]' ).forEach( bind );
	}

	scan();
	// The site popup copies the form from a <template> when it opens.
	new MutationObserver( function ( list ) {
		list.forEach( function ( m ) {
			m.addedNodes.forEach( function ( node ) {
				if ( node.nodeType === 1 ) {
					scan( node );
				}
			} );
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true } );
}() );
