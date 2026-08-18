/* global wp */
( function( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.components || ! wp.element || ! wp.i18n ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var SECTION_SLOTS = [ 'what', 'how', 'examples', 'mistakes', 'takeaways' ];
	var INNER_TEMPLATE = [ [ 'core/paragraph', {} ] ];

	function getDefaultHeading( section ) {
		switch ( section ) {
			case 'how':
				return __( 'How it works', 'context-authority-toolkit' );
			case 'examples':
				return __( 'Examples', 'context-authority-toolkit' );
			case 'mistakes':
				return __( 'Common mistakes', 'context-authority-toolkit' );
			case 'takeaways':
				return __( 'Key takeaways', 'context-authority-toolkit' );
			case 'what':
			default:
				return __( 'What it is', 'context-authority-toolkit' );
		}
	}

	function sanitizeSection( section ) {
		return -1 !== SECTION_SLOTS.indexOf( section ) ? section : 'what';
	}

	function getHeading( attributes ) {
		var custom = 'string' === typeof attributes.customHeading ? attributes.customHeading.trim() : '';
		if ( custom ) {
			return custom;
		}

		return getDefaultHeading( sanitizeSection( attributes.section ) );
	}

	function edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var section = sanitizeSection( attributes.section );
		var heading = getHeading( attributes );
		var blockProps = useBlockProps( {
			className: 'cat-term-section',
			'data-cat-section': section
		} );
		var innerBlocksProps = useInnerBlocksProps
			? useInnerBlocksProps(
				{ className: 'cat-term-section__content' },
				{ template: INNER_TEMPLATE, templateLock: false }
			)
			: null;

		return createElement(
			Fragment,
			null,
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __( 'Term section', 'context-authority-toolkit' ), initialOpen: true },
					createElement( SelectControl, {
						label: __( 'Section', 'context-authority-toolkit' ),
						value: section,
						options: [
							{ label: __( 'What it is', 'context-authority-toolkit' ), value: 'what' },
							{ label: __( 'How it works', 'context-authority-toolkit' ), value: 'how' },
							{ label: __( 'Examples', 'context-authority-toolkit' ), value: 'examples' },
							{ label: __( 'Common mistakes', 'context-authority-toolkit' ), value: 'mistakes' },
							{ label: __( 'Key takeaways', 'context-authority-toolkit' ), value: 'takeaways' }
						],
						onChange: function( value ) {
							setAttributes( { section: sanitizeSection( value ) } );
						}
					} ),
					createElement( TextControl, {
						label: __( 'Custom heading', 'context-authority-toolkit' ),
						help: __( 'Leave empty to use the translated default heading.', 'context-authority-toolkit' ),
						value: attributes.customHeading || '',
						onChange: function( value ) {
							setAttributes( { customHeading: value || '' } );
						}
					} )
				)
			),
			createElement(
				'section',
				blockProps,
				createElement(
					'h2',
					{ className: 'cat-term-section__heading' },
					heading
				),
				innerBlocksProps
					? createElement( 'div', innerBlocksProps )
					: createElement( InnerBlocks, { template: INNER_TEMPLATE, templateLock: false } )
			)
		);
	}

	registerBlockType( 'cat-toolkit/term-section', {
		edit: edit,
		save: function() {
			return createElement( InnerBlocks.Content );
		}
	} );
}( window.wp ) );
