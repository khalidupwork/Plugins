/* Talkwyn Pro admin. */
jQuery( function ( $ ) {
	'use strict';
	var A = window.TalkwynAdmin || {};
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	$( '#twp-embed' ).on( 'click', function () {
		var btn = $( this );
		var out = $( '#twp-embed-status' );
		btn.prop( 'disabled', true );
		( function step() {
			$.post( A.ajax, { action: 'talkwyn_pro_embed', nonce: A.nonce } ).done( function ( r ) {
				var d = r.data;
				/* translators: 1: vectors, 2: chunks */
				out.removeClass( 'is-bad' ).text( sprintf( __( '%1$s of %2$s chunks ready', 'talkwyn-pro' ), d.vectors, d.chunks ) );
				if ( d.done ) {
					btn.prop( 'disabled', false );
					out.addClass( 'is-good' );
				} else {
					step();
				}
			} ).fail( function ( x ) {
				btn.prop( 'disabled', false );
				out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Could not build smart search. Save your settings and check the key.', 'talkwyn-pro' ) );
			} );
		}() );
	} );

	$( '#twp-test-alert' ).on( 'click', function () {
		var out = $( '#twp-alert-status' );
		out.removeClass( 'is-good is-bad' ).text( __( 'Sending', 'talkwyn-pro' ) );
		$.post( A.ajax, { action: 'talkwyn_pro_test_alert', nonce: A.nonce } ).done( function ( r ) {
			out.addClass( 'is-good' ).text( r.data.message );
		} ).fail( function ( x ) {
			out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Failed.', 'talkwyn-pro' ) );
		} );
	} );
} );
