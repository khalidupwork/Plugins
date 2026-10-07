/* Talkwyn Hub partner dashboard: copy buttons, link builder, QR code. */
( function () {
	'use strict';

	function copy( text, btn ) {
		var done = function () {
			var old = btn.textContent;
			btn.textContent = btn.getAttribute( 'data-copied' ) || 'Copied';
			btn.classList.add( 'is-copied' );
			setTimeout( function () {
				btn.textContent = old;
				btn.classList.remove( 'is-copied' );
			}, 1600 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		document.body.removeChild( area );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.twh-copy-text' );
		if ( ! btn ) {
			return;
		}
		var target = document.querySelector( btn.getAttribute( 'data-copy' ) );
		if ( target ) {
			copy( ( target.value || target.textContent ).trim(), btn );
		}
	} );

	var builder = document.querySelector( '.twh-link-builder' );
	if ( builder ) {
		var input = builder.querySelector( '#twh-link-path' );
		var out = builder.querySelector( '#twh-built-link' );
		var base = builder.getAttribute( 'data-base' ) || '/';
		var code = builder.getAttribute( 'data-code' ) || '';
		var build = function () {
			var path = ( input.value || '/' ).trim();
			try {
				var url = new URL( path, base );
				if ( url.origin !== new URL( base ).origin ) {
					url = new URL( url.pathname + url.search + url.hash, base );
				}
				url.searchParams.set( 'ref', code );
				out.textContent = url.toString();
			} catch ( err ) {
				out.textContent = base;
			}
		};
		input.addEventListener( 'input', build );
		build();
	}

	if ( typeof window.qrcode === 'function' ) {
		document.querySelectorAll( '.twh-qr[data-qr]' ).forEach( function ( fig ) {
			var qr = window.qrcode( 0, 'M' );
			qr.addData( fig.getAttribute( 'data-qr' ) );
			qr.make();
			fig.innerHTML = qr.createSvgTag( { cellSize: 4, margin: 2, scalable: true } );
			var svg = fig.querySelector( 'svg' );
			if ( svg ) {
				svg.setAttribute( 'aria-hidden', 'true' );
				svg.setAttribute( 'focusable', 'false' );
			}
		} );
	}
}() );
