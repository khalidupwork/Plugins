/* Talkwyn admin. */
jQuery( function ( $ ) {
	'use strict';
	var A = window.TalkwynAdmin || {};
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/* Site scan */
	var running = false;
	var total = 0;
	function status( text ) {
		$( '#twa-scan-status' ).text( text );
	}
	function bar( pct ) {
		$( '.twa-progress span' ).css( 'width', pct + '%' );
	}
	function batch( offset ) {
		$.post( A.ajax, { action: 'talkwyn_index_batch', nonce: A.nonce, offset: offset } ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				running = false;
				status( __( 'Scan failed. Please try again.', 'talkwyn' ) );
				return;
			}
			var d = r.data;
			var pct = total ? Math.min( 100, Math.round( ( d.next_offset / total ) * 100 ) ) : 100;
			bar( d.done ? 100 : pct );
			if ( d.done ) {
				running = false;
				$( '#twa-scan' ).prop( 'disabled', false );
				/* translators: %s: number of chunks */
				status( sprintf( __( 'Scan complete. %s chunks indexed.', 'talkwyn' ), d.chunks ) );
			} else {
				/* translators: 1: chunks, 2: percent */
				status( sprintf( __( '%1$s chunks indexed, %2$s%%', 'talkwyn' ), d.chunks, pct ) );
				batch( d.next_offset );
			}
		} ).fail( function () {
			running = false;
			$( '#twa-scan' ).prop( 'disabled', false );
			status( __( 'Scan failed. Please try again.', 'talkwyn' ) );
		} );
	}
	$( '#twa-scan' ).on( 'click', function () {
		if ( running ) {
			return;
		}
		running = true;
		$( this ).prop( 'disabled', true );
		bar( 2 );
		status( __( 'Preparing the scan', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_index_start', nonce: A.nonce } ).done( function ( r ) {
			total = r && r.success ? r.data.total : 0;
			batch( 0 );
		} ).fail( function () {
			running = false;
			$( '#twa-scan' ).prop( 'disabled', false );
			status( __( 'Could not start the scan.', 'talkwyn' ) );
		} );
	} );
	$( '#twa-clear' ).on( 'click', function () {
		if ( ! window.confirm( __( 'Remove all scanned site knowledge?', 'talkwyn' ) ) ) {
			return;
		}
		$.post( A.ajax, { action: 'talkwyn_clear_index', nonce: A.nonce } ).done( function ( r ) {
			bar( 0 );
			/* translators: %s: number of chunks */
			status( sprintf( __( '%s chunks indexed', 'talkwyn' ), r && r.data ? r.data.chunks : 0 ) );
		} );
	} );

	/* Providers */
	function fields( box ) {
		var out = {};
		box.find( '[name^="talkwyn["]' ).each( function () {
			var m = this.name.match( /^talkwyn\[([^\]]+)\]$/ );
			if ( m ) {
				out[ m[ 1 ] ] = $( this ).val();
			}
		} );
		return out;
	}
	$( document ).on( 'click', '.twa-test', function () {
		var btn = $( this );
		var box = btn.closest( '[data-provider]' );
		var out = box.find( '.twa-test-status' );
		btn.prop( 'disabled', true );
		out.removeClass( 'is-good is-bad' ).text( __( 'Testing', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_test_provider', nonce: A.nonce, provider: box.data( 'provider' ), fields: fields( box ) } ).done( function ( r ) {
			out.addClass( 'is-good' ).text( r.data.message );
		} ).fail( function ( x ) {
			out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Connection failed.', 'talkwyn' ) );
		} ).always( function () {
			btn.prop( 'disabled', false );
		} );
	} );
	$( document ).on( 'click', '.twa-load-models', function () {
		var box = $( this ).closest( '[data-provider]' );
		var id = box.data( 'provider' );
		var out = box.find( '.twa-models-status' );
		out.removeClass( 'is-good is-bad' ).text( __( 'Loading', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_models', nonce: A.nonce, provider: id, fields: fields( box ) } ).done( function ( r ) {
			var list = $( '#twa-models-' + id ).empty();
			r.data.models.forEach( function ( m ) {
				list.append( $( '<option>' ).attr( 'value', m ) );
			} );
			/* translators: %s: number of models */
			out.addClass( 'is-good' ).text( sprintf( __( '%s models. Click the model field to pick one, or type your own.', 'talkwyn' ), r.data.models.length ) );
		} ).fail( function ( x ) {
			out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Could not load models.', 'talkwyn' ) );
		} );
	} );

	/* Smart Contrast preview (same math as the server) */
	function lum( hex ) {
		hex = hex.replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex.replace( /(.)/g, '$1$1' );
		}
		var c = [ 0, 2, 4 ].map( function ( i ) {
			var v = parseInt( hex.substr( i, 2 ), 16 ) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
	}
	function ratio( a, b ) {
		var x = lum( a );
		var y = lum( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	}
	$( '.twa-color' ).on( 'input change', function () {
		var color = $( this ).val();
		var box = $( '.twa-contrast' );
		var ink = box.data( 'ink' ) || '#1A0F12';
		var white = ratio( color, '#FFFFFF' );
		var dark = ratio( color, ink );
		var on = white >= dark ? '#FFFFFF' : ink;
		var best = Math.max( white, dark );
		box.find( '.twa-contrast__sample' ).css( { background: color, color: on } );
		box.find( '.twa-contrast__note' ).text(
			/* translators: 1: text colour, 2: contrast ratio */
			sprintf( __( 'Smart Contrast uses %1$s text on this colour (contrast %2$s:1).', 'talkwyn' ), on === '#FFFFFF' ? __( 'white', 'talkwyn' ) : __( 'dark', 'talkwyn' ), best.toFixed( 1 ) )
		);
		box.find( '.twa-contrast__warn' ).prop( 'hidden', best >= 4.5 );
	} );

	/* Avatar picker */
	var frame;
	$( '#twa-avatar-media' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( { title: __( 'Choose an avatar', 'talkwyn' ), button: { text: __( 'Use this image', 'talkwyn' ) }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$( '#twa-avatar_url' ).val( a.url );
				$( '#twa-avatar_type' ).val( 'custom' );
			} );
		}
		frame.open();
	} );
} );
