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

	/* Settings tabs: one form, panels shown by tab. The open tab survives a save. */
	var tabs = document.querySelectorAll( '.twh-tabs__item' );
	if ( tabs.length ) {
		var panels = document.querySelectorAll( '.twh-tabpanel' );
		var field = document.querySelector( 'input[name="twh_tab"]' );
		var savebar = document.querySelector( '.twh-savebar' );
		var show = function ( name ) {
			var found = false;
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-tab' ) === name;
				found = found || on;
				t.classList.toggle( 'is-active', on );
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			if ( ! found ) {
				return show( 'general' );
			}
			panels.forEach( function ( p ) {
				p.hidden = p.getAttribute( 'data-tab' ) !== name;
			} );
			if ( field ) {
				field.value = name;
			}
			if ( savebar ) {
				savebar.hidden = 'keys' === name || 'status' === name;
			}
		};
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				var name = t.getAttribute( 'data-tab' );
				show( name );
				if ( window.history && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.set( 'tab', name );
					url.searchParams.delete( 'twh_notice' );
					window.history.replaceState( null, '', url.toString() );
				}
			} );
		} );
		var start = new URL( window.location.href ).searchParams.get( 'tab' ) || ( window.location.hash || '' ).replace( '#', '' ) || 'general';
		show( start );
	}
}() );
