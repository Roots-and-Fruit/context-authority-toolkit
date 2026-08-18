/* global wp */
( function( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element || ! wp.i18n ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var createElement = wp.element.createElement;
	var __ = wp.i18n.__;

	registerBlockType( 'cat-toolkit/term-panel', {
		edit: function() {
			var blockProps = useBlockProps( { className: 'cat-term-panel' } );

			return createElement(
				'aside',
				blockProps,
				createElement(
					'h2',
					{ className: 'cat-term-panel__heading' },
					__( 'About this term', 'context-authority-toolkit' )
				),
				createElement(
					'p',
					{ className: 'cat-term-panel__editor-note' },
					__( 'Aliases, related terms, authority links, sources, and cite this appear here on glossary term pages.', 'context-authority-toolkit' )
				)
			);
		},
		save: function() {
			return null;
		}
	} );
} )( window.wp );
