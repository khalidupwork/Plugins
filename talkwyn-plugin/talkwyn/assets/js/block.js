( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks ) {
		return;
	}
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;

	wp.blocks.registerBlockType( 'talkwyn/chat', {
		edit: function ( props ) {
			var height = props.attributes.height || 600;
			var blockProps = useBlockProps( {
				className: 'talkwyn-block-preview',
				style: { minHeight: Math.min( height, 360 ) + 'px', border: '1px solid #EEE7E8', borderRadius: '24px', background: '#F7F3F3', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: '8px', padding: '24px', textAlign: 'center' }
			} );
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Chat size', 'talkwyn' ) },
						el( RangeControl, {
							label: __( 'Height (px)', 'talkwyn' ),
							value: height,
							min: 420,
							max: 900,
							step: 10,
							onChange: function ( v ) {
								props.setAttributes( { height: v } );
							}
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'strong', { style: { fontSize: '18px', color: '#1A0F12' } }, __( 'Talkwyn Chat', 'talkwyn' ) ),
					el( 'span', { style: { color: '#6B5E61' } }, __( 'Visitors chat with your assistant here. Preview it on the live page.', 'talkwyn' ) )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
