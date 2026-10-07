/* Talkwyn Hub admin helpers: click to copy, confirmations. */
( function () {
	'use strict';
	var cfg = window.twhAdmin || { i18n: {} };

	document.addEventListener( 'click', function ( e ) {
		var copyable = e.target.closest( '.twh-copyable' );
		if ( copyable && navigator.clipboard ) {
			navigator.clipboard.writeText( copyable.textContent.trim() ).then( function () {
				var old = copyable.getAttribute( 'data-title' ) || '';
				copyable.setAttribute( 'title', cfg.i18n.copied );
				setTimeout( function () { copyable.setAttribute( 'title', old ); }, 1500 );
			} );
		}
		var confirmLink = e.target.closest( 'a.twh-confirm, button.twh-confirm' );
		if ( confirmLink && ! window.confirm( cfg.i18n.confirm ) ) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'submit', function ( e ) {
		if ( e.target.classList && e.target.classList.contains( 'twh-confirm-form' ) && ! window.confirm( cfg.i18n.confirm ) ) {
			e.preventDefault();
		}
	} );
}() );
