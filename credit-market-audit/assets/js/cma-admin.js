/* Credit Market Free Audit – admin settings */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.cma-color' ).wpColorPicker();

		$( '.cma-media-button' ).on( 'click', function ( e ) {
			e.preventDefault();
			var $input = $( '#' + $( this ).data( 'target' ) );
			var $preview = $input.siblings( '.cma-media-preview' );
			var frame = wp.media( { title: 'Select logo', multiple: false, library: { type: 'image' } } );

			frame.on( 'select', function () {
				var file = frame.state().get( 'selection' ).first().toJSON();
				$input.val( file.url );
				$preview.html( $( '<img>', { src: file.url, alt: '' } ) );
			} );
			frame.open();
		} );
	} );
}( jQuery ) );
