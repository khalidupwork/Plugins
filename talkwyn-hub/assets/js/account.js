/* Talkwyn Hub, My Account: reveal and copy license keys. */
( function () {
	'use strict';

	var cfg = window.twhAccount || {};
	var cache = {};

	function fetchKey( id ) {
		if ( cache[ id ] ) {
			return Promise.resolve( cache[ id ] );
		}
		var body = new URLSearchParams();
		body.append( 'action', 'twh_reveal_key' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'license_id', id );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( 'reveal failed' );
				}
				cache[ id ] = json.data.key;
				return json.data.key;
			} );
	}

	function copy( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		document.body.removeChild( ta );
		return Promise.resolve();
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.twh-reveal, .twh-copy' );
		if ( ! btn ) {
			return;
		}
		var wrap = btn.closest( '.twh-key' );
		if ( ! wrap ) {
			return;
		}
		var id = wrap.getAttribute( 'data-license' );
		var value = wrap.querySelector( '.twh-key-value' );
		var full = wrap.classList.contains( 'twh-key--full' );

		var keyPromise = full ? Promise.resolve( value.textContent.trim() ) : fetchKey( id );

		if ( btn.classList.contains( 'twh-reveal' ) ) {
			if ( wrap.classList.contains( 'is-revealed' ) ) {
				value.textContent = wrap.getAttribute( 'data-masked' );
				wrap.classList.remove( 'is-revealed' );
				btn.textContent = cfg.i18n.reveal;
				return;
			}
			keyPromise.then( function ( key ) {
				value.textContent = key;
				wrap.classList.add( 'is-revealed' );
				btn.textContent = cfg.i18n.hide;
			} ).catch( function () { window.alert( cfg.i18n.error ); } );
			return;
		}

		keyPromise.then( copy ).then( function () {
			var old = btn.textContent;
			btn.textContent = cfg.i18n.copied;
			setTimeout( function () { btn.textContent = old; }, 1500 );
		} ).catch( function () { window.alert( cfg.i18n.error ); } );
	} );
}() );
