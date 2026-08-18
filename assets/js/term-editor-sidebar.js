/* global wp */
( function( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.data || ! wp.components || ! wp.element || ! wp.i18n || ! wp.apiFetch ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var PanelRow = wp.components.PanelRow;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var FormTokenField = wp.components.FormTokenField;
	var Button = wp.components.Button;
	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useDispatch = wp.data.useDispatch;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	var ALTERNATIVES_META_KEY = 'cat_alternatives';
	var TOOLTIP_META_KEY = 'cat_tooltip_content';
	var SAME_AS_META_KEY = window.catToolkitEditor && window.catToolkitEditor.sameAsMeta ? window.catToolkitEditor.sameAsMeta : 'cat_same_as';
	var SOURCES_META_KEY = window.catToolkitEditor && window.catToolkitEditor.sourcesMeta ? window.catToolkitEditor.sourcesMeta : 'cat_sources';
	var RELATED_TERMS_META_KEY = window.catToolkitEditor && window.catToolkitEditor.relatedTermsMeta ? window.catToolkitEditor.relatedTermsMeta : 'cat_related_terms';
	var RELATED_TERMS_MAX = window.catToolkitEditor && window.catToolkitEditor.relatedTermsMax ? window.catToolkitEditor.relatedTermsMax : 8;
	var WIKIDATA_SEARCH_PATH = window.catToolkitEditor && window.catToolkitEditor.wikidataSearchPath ? window.catToolkitEditor.wikidataSearchPath : '/context-authority-toolkit/v1/wikidata-search';
	var PUBLIC_DISABLE_AUTOLINK_META_KEY = window.catToolkitEditor && window.catToolkitEditor.disableAutolinkMeta ? window.catToolkitEditor.disableAutolinkMeta : 'cat_disable_autolinking';
	var PUBLIC_POST_TYPES = window.catToolkitEditor && Array.isArray( window.catToolkitEditor.publicPostTypes ) ? window.catToolkitEditor.publicPostTypes : [ 'post', 'page', 'term' ];

	function parseAlternatives( value ) {
		if ( 'string' !== typeof value ) {
			return [];
		}

		var names = value.split( /\s*,\s*/ ).map( function( name ) {
			return name.trim();
		} );

		names = names.filter( function( name ) {
			return name.length > 0;
		} );

		return names.filter( function( name, index ) {
			return names.indexOf( name ) === index;
		} );
	}

	function parseSameAs( value ) {
		if ( 'string' !== typeof value ) {
			return [];
		}

		var links = value.split( /[\n,]+/ ).map( function( link ) {
			return link.trim();
		} );

		return links.filter( function( link, index ) {
			return link.length > 0 && links.indexOf( link ) === index;
		} );
	}

	function isValidHttpUrl( value ) {
		if ( 'string' !== typeof value || ! value.trim().length ) {
			return false;
		}

		try {
			var parsed = new window.URL( value.trim() );
			return parsed.protocol === 'http:' || parsed.protocol === 'https:';
		} catch ( error ) {
			return false;
		}
	}

	function isValidIsoDate( value ) {
		if ( 'string' !== typeof value || ! value.trim().length ) {
			return true;
		}

		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( value.trim() ) ) {
			return false;
		}

		var parsed = new Date( value + 'T00:00:00Z' );
		if ( Number.isNaN( parsed.getTime() ) ) {
			return false;
		}

		return parsed.toISOString().slice( 0, 10 ) === value.trim();
	}

	function normalizeSource( source ) {
		return {
			url: source && source.url ? String( source.url ) : '',
			title: source && source.title ? String( source.title ) : '',
			publisher: source && source.publisher ? String( source.publisher ) : '',
			datePublished: source && source.datePublished ? String( source.datePublished ) : ''
		};
	}

	function decodeTitle( rendered ) {
		var textarea = document.createElement( 'textarea' );
		textarea.innerHTML = rendered || '';
		return textarea.value;
	}

	function RelatedTermsField( props ) {
		var relatedIds = Array.isArray( props.value ) ? props.value.map( function( id ) {
			return parseInt( id, 10 );
		} ).filter( function( id ) {
			return id > 0;
		} ) : [];
		var currentPostId = props.currentPostId || 0;
		var onChange = props.onChange;
		var titleById = useState( {} );
		var setTitleById = titleById[ 1 ];
		var titleMap = titleById[ 0 ];
		var suggestions = useState( [] );
		var setSuggestions = suggestions[ 1 ];
		var suggestionList = suggestions[ 0 ];
		var searchMap = useState( {} );
		var setSearchMap = searchMap[ 1 ];
		var searchTitleToId = searchMap[ 0 ];

		useEffect( function() {
			if ( ! relatedIds.length ) {
				return;
			}

			var missing = relatedIds.filter( function( id ) {
				return ! titleMap[ id ];
			} );
			if ( ! missing.length ) {
				return;
			}

			apiFetch( {
				path: '/wp/v2/term?include=' + missing.join( ',' ) + '&per_page=' + RELATED_TERMS_MAX + '&status=publish&_fields=id,title'
			} ).then( function( posts ) {
				if ( ! Array.isArray( posts ) ) {
					return;
				}

				setTitleById( function( prev ) {
					var next = Object.assign( {}, prev );
					posts.forEach( function( post ) {
						if ( post && post.id && post.title && post.title.rendered ) {
							next[ post.id ] = decodeTitle( post.title.rendered );
						}
					} );
					return next;
				} );
			} ).catch( function() {
				// Leave unresolved IDs as fallback labels.
			} );
		}, [ relatedIds.join( ',' ) ] );

		var tokens = relatedIds.map( function( id ) {
			return titleMap[ id ] || ( '#' + id );
		} );

		var mergeTitleMaps = function( posts ) {
			var nextTitles = Object.assign( {}, titleMap );
			var nextSearch = Object.assign( {}, searchTitleToId );
			posts.forEach( function( post ) {
				if ( ! post || ! post.id || post.id === currentPostId ) {
					return;
				}
				if ( ! post.title || ! post.title.rendered ) {
					return;
				}
				var title = decodeTitle( post.title.rendered );
				nextTitles[ post.id ] = title;
				nextSearch[ title ] = post.id;
			} );
			setTitleById( nextTitles );
			setSearchMap( nextSearch );
			return nextSearch;
		};

		return createElement( FormTokenField, {
			label: __( 'Related terms', 'context-authority-toolkit' ),
			help: __( 'Search and attach up to eight published glossary terms. Relationships are one-way.', 'context-authority-toolkit' ),
			value: tokens,
			suggestions: suggestionList,
			maxLength: RELATED_TERMS_MAX,
			onInputChange: function( search ) {
				if ( ! search || search.length < 2 ) {
					setSuggestions( [] );
					return;
				}

				apiFetch( {
					path: '/wp/v2/term?search=' + encodeURIComponent( search ) + '&per_page=10&status=publish&_fields=id,title'
				} ).then( function( posts ) {
					if ( ! Array.isArray( posts ) ) {
						setSuggestions( [] );
						return;
					}

					var nextSearch = mergeTitleMaps( posts );
					var titles = [];
					posts.forEach( function( post ) {
						if ( ! post || ! post.id || post.id === currentPostId ) {
							return;
						}
						if ( relatedIds.indexOf( post.id ) !== -1 ) {
							return;
						}
						var title = nextSearch && Object.keys( nextSearch ).length ? decodeTitle( post.title.rendered ) : decodeTitle( post.title.rendered );
						if ( title && titles.indexOf( title ) === -1 ) {
							titles.push( title );
						}
					} );
					setSuggestions( titles );
				} ).catch( function() {
					setSuggestions( [] );
				} );
			},
			onChange: function( nextTokens ) {
				var capped = nextTokens.slice( 0, RELATED_TERMS_MAX );
				var nextIds = [];
				var seen = {};

				capped.forEach( function( token ) {
					var id = 0;
					Object.keys( titleMap ).some( function( mapId ) {
						if ( titleMap[ mapId ] === token ) {
							id = parseInt( mapId, 10 );
							return true;
						}
						return false;
					} );
					if ( ! id && searchTitleToId[ token ] ) {
						id = parseInt( searchTitleToId[ token ], 10 );
					}
					if ( ! id && /^#\d+$/.test( token ) ) {
						id = parseInt( token.slice( 1 ), 10 );
					}
					if ( ! id || id === currentPostId || seen[ id ] ) {
						return;
					}
					seen[ id ] = true;
					nextIds.push( id );
				} );

				onChange( nextIds );
			}
		} );
	}

	function WikidataSameAsLookup( props ) {
		var currentPostId = props.currentPostId || 0;
		var sameAsLinks = Array.isArray( props.sameAsLinks ) ? props.sameAsLinks.slice() : [];
		var setMetaValue = props.setMetaValue;
		var searchState = useState( '' );
		var search = searchState[ 0 ];
		var setSearch = searchState[ 1 ];
		var resultsState = useState( [] );
		var results = resultsState[ 0 ];
		var setResults = resultsState[ 1 ];
		var statusState = useState( '' );
		var status = statusState[ 0 ];
		var setStatus = statusState[ 1 ];
		var loadingState = useState( false );
		var isLoading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];

		var clearResults = function() {
			setResults( [] );
			setStatus( '' );
		};

		var runSearch = function() {
			var query = ( search || '' ).trim();
			if ( ! query || ! currentPostId ) {
				setResults( [] );
				setStatus( __( 'Enter a search term to look up Wikidata.', 'context-authority-toolkit' ) );
				return;
			}

			setLoading( true );
			setStatus( '' );
			apiFetch( {
				path: WIKIDATA_SEARCH_PATH + '?post_id=' + encodeURIComponent( currentPostId ) + '&search=' + encodeURIComponent( query )
			} ).then( function( response ) {
				var nextResults = response && Array.isArray( response.results ) ? response.results : [];
				setResults( nextResults );
				setStatus( nextResults.length ? '' : __( 'No Wikidata matches found.', 'context-authority-toolkit' ) );
			} ).catch( function() {
				setResults( [] );
				setStatus( __( 'Wikidata lookup failed. Try again or paste a URL manually.', 'context-authority-toolkit' ) );
			} ).then( function() {
				setLoading( false );
			} );
		};

		var pickResult = function( result ) {
			if ( ! result || ! result.url ) {
				return;
			}

			var nextLinks = sameAsLinks.slice();
			if ( nextLinks.indexOf( result.url ) === -1 ) {
				nextLinks.push( result.url );
				setMetaValue( SAME_AS_META_KEY, nextLinks );
			}

			clearResults();
			setSearch( '' );
		};

		var resultNodes = results.map( function( result ) {
			var key = result.id || result.url;
			var label = result.label || result.id || result.url;
			var description = result.description ? ' — ' + result.description : '';

			return createElement(
				'li',
				{
					key: key,
					style: { marginBottom: '8px' }
				},
				createElement(
					'div',
					{ style: { marginBottom: '4px' } },
					createElement( 'strong', null, label ),
					description
				),
				createElement(
					'div',
					{ style: { marginBottom: '4px', wordBreak: 'break-all' } },
					result.url
				),
				createElement( Button, {
					isSecondary: true,
					onClick: function() {
						pickResult( result );
					}
				}, __( 'Add to Related Authority Links', 'context-authority-toolkit' ) )
			);
		} );

		return createElement(
			'div',
			{
				style: { width: '100%', marginTop: '12px' }
			},
			createElement( 'strong', null, __( 'Search Wikidata', 'context-authority-toolkit' ) ),
			createElement(
				'p',
				{ style: { marginTop: '8px', marginBottom: '8px' } },
				__( 'Find a Wikidata entity and add its URL to Related Authority Links. Nothing is added until you pick a result.', 'context-authority-toolkit' )
			),
			createElement( TextControl, {
				label: __( 'Wikidata search', 'context-authority-toolkit' ),
				value: search,
				onChange: function( value ) {
					setSearch( value || '' );
				}
			} ),
			createElement(
				'div',
				{ style: { display: 'flex', gap: '8px', marginBottom: '8px' } },
				createElement( Button, {
					isPrimary: true,
					isBusy: isLoading,
					disabled: isLoading,
					onClick: runSearch
				}, __( 'Search Wikidata', 'context-authority-toolkit' ) ),
				createElement( Button, {
					isSecondary: true,
					disabled: isLoading,
					onClick: clearResults
				}, __( 'Clear results', 'context-authority-toolkit' ) )
			),
			status ? createElement( 'p', null, status ) : null,
			resultNodes.length ? createElement( 'ul', { style: { margin: '0', paddingLeft: '18px' } }, resultNodes ) : null
		);
	}

	function TermSidebarFields() {
		var postType = useSelect( function( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var currentPostId = useSelect( function( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var meta = useSelect( function( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var setMetaValue = function( key, value ) {
			editPost(
				{
					meta: Object.assign(
						{},
						meta,
						{
							[ key ]: value
						}
					)
				}
			);
		};

		if ( 'term' !== postType ) {
			if ( PUBLIC_POST_TYPES.indexOf( postType ) === -1 ) {
				return null;
			}

			return createElement(
				PluginDocumentSettingPanel,
				{
					name: 'cat-public-autolink-settings',
					title: __( 'Context & Authority Toolkit', 'context-authority-toolkit' )
				},
				createElement(
					PanelRow,
					null,
					createElement( ToggleControl, {
						label: __( 'Disable glossary auto-linking', 'context-authority-toolkit' ),
						help: __( 'Turn off glossary auto-linking for this content.', 'context-authority-toolkit' ),
						checked: !! meta[ PUBLIC_DISABLE_AUTOLINK_META_KEY ],
						onChange: function( value ) {
							setMetaValue( PUBLIC_DISABLE_AUTOLINK_META_KEY, !! value );
						}
					} )
				)
			);
		}

		var alternatives = Array.isArray( meta[ ALTERNATIVES_META_KEY ] ) ? meta[ ALTERNATIVES_META_KEY ].join( ', ' ) : '';
		var tooltipContent = 'string' === typeof meta[ TOOLTIP_META_KEY ] ? meta[ TOOLTIP_META_KEY ] : '';
		var sameAsLinks = Array.isArray( meta[ SAME_AS_META_KEY ] ) ? meta[ SAME_AS_META_KEY ] : [];
		var sources = Array.isArray( meta[ SOURCES_META_KEY ] ) ? meta[ SOURCES_META_KEY ] : [];
		var relatedTerms = Array.isArray( meta[ RELATED_TERMS_META_KEY ] ) ? meta[ RELATED_TERMS_META_KEY ] : [];
		var invalidSameAsCount = sameAsLinks.filter( function( link ) {
			return ! isValidHttpUrl( link );
		} ).length;
		var updateSourceItem = function( index, key, value ) {
			var nextSources = sources.map( function( source ) {
				return normalizeSource( source );
			} );
			var currentSource = normalizeSource( nextSources[ index ] || {} );
			currentSource[ key ] = value;
			nextSources[ index ] = currentSource;
			setMetaValue( SOURCES_META_KEY, nextSources );
		};
		var addSourceItem = function() {
			var nextSources = sources.map( function( source ) {
				return normalizeSource( source );
			} );
			nextSources.push( {
				url: '',
				title: '',
				publisher: '',
				datePublished: ''
			} );
			setMetaValue( SOURCES_META_KEY, nextSources );
		};
		var removeSourceItem = function( index ) {
			var nextSources = sources.filter( function( source, itemIndex ) {
				return itemIndex !== index;
			} );
			setMetaValue( SOURCES_META_KEY, nextSources );
		};
		var sourceRows = sources.map( function( source, index ) {
			var normalizedSource = normalizeSource( source );

			return createElement(
				'div',
				{
					key: 'cat-source-' + index,
					style: { marginBottom: '16px', border: '1px solid #ddd', padding: '12px' }
				},
				createElement( TextControl, {
					label: __( 'Source URL', 'context-authority-toolkit' ),
					help: ! normalizedSource.url.length ? __( 'Required. Use a full URL starting with https://', 'context-authority-toolkit' ) : ( isValidHttpUrl( normalizedSource.url ) ? __( 'Looks good.', 'context-authority-toolkit' ) : __( 'Please enter a valid URL (for example: https://example.com/article).', 'context-authority-toolkit' ) ),
					type: 'url',
					placeholder: __( 'https://example.com/source-article', 'context-authority-toolkit' ),
					value: normalizedSource.url,
					onChange: function( value ) {
						updateSourceItem( index, 'url', value || '' );
					}
				} ),
				createElement( TextControl, {
					label: __( 'Source Title', 'context-authority-toolkit' ),
					value: normalizedSource.title,
					onChange: function( value ) {
						updateSourceItem( index, 'title', value || '' );
					}
				} ),
				createElement( TextControl, {
					label: __( 'Publisher', 'context-authority-toolkit' ),
					value: normalizedSource.publisher,
					onChange: function( value ) {
						updateSourceItem( index, 'publisher', value || '' );
					}
				} ),
				createElement( TextControl, {
					label: __( 'Date Published', 'context-authority-toolkit' ),
					help: ! normalizedSource.datePublished.length ? __( 'Use YYYY-MM-DD when possible.', 'context-authority-toolkit' ) : ( isValidIsoDate( normalizedSource.datePublished ) ? __( 'Date format looks good.', 'context-authority-toolkit' ) : __( 'Invalid date format. Please use YYYY-MM-DD.', 'context-authority-toolkit' ) ),
					type: 'date',
					placeholder: 'YYYY-MM-DD',
					value: normalizedSource.datePublished,
					onChange: function( value ) {
						updateSourceItem( index, 'datePublished', value || '' );
					}
				} ),
				createElement( Button, {
					isSecondary: true,
					isDestructive: true,
					onClick: function() {
						removeSourceItem( index );
					}
				}, __( 'Remove Source', 'context-authority-toolkit' ) )
			);
		} );

		return createElement(
			wp.element.Fragment,
			null,
			createElement(
				PluginDocumentSettingPanel,
				{
					name: 'cat-public-autolink-settings',
					title: __( 'Context & Authority Toolkit', 'context-authority-toolkit' )
				},
				createElement(
					PanelRow,
					null,
					createElement( ToggleControl, {
						label: __( 'Disable glossary auto-linking', 'context-authority-toolkit' ),
						help: __( 'Turn off glossary auto-linking for this content.', 'context-authority-toolkit' ),
						checked: !! meta[ PUBLIC_DISABLE_AUTOLINK_META_KEY ],
						onChange: function( value ) {
							setMetaValue( PUBLIC_DISABLE_AUTOLINK_META_KEY, !! value );
						}
					} )
				)
			),
			createElement(
				PluginDocumentSettingPanel,
				{
					name: 'cat-term-sidebar-fields',
					title: __( 'Term Settings', 'context-authority-toolkit' )
				},
				createElement(
					PanelRow,
					null,
					createElement( TextControl, {
						label: __( 'Alternate Names', 'context-authority-toolkit' ),
						help: __( 'Comma-separated alternative names or abbreviations for this term.', 'context-authority-toolkit' ),
						value: alternatives,
						onChange: function( value ) {
							setMetaValue( ALTERNATIVES_META_KEY, parseAlternatives( value ) );
						}
					} )
				),
				createElement(
					PanelRow,
					null,
					createElement( TextareaControl, {
						label: __( 'Tooltip content', 'context-authority-toolkit' ),
						help: __( 'Plain text only. Line breaks are supported.', 'context-authority-toolkit' ),
						value: tooltipContent,
						rows: 6,
						onChange: function( value ) {
							setMetaValue( TOOLTIP_META_KEY, value || '' );
						}
					} )
				),
				createElement(
					PanelRow,
					null,
					createElement( RelatedTermsField, {
						value: relatedTerms,
						currentPostId: currentPostId,
						onChange: function( ids ) {
							setMetaValue( RELATED_TERMS_META_KEY, ids );
						}
					} )
				),
				createElement(
					PanelRow,
					null,
					createElement(
						'div',
						{ style: { width: '100%' } },
						createElement( TextareaControl, {
							label: __( 'Related Authority Links', 'context-authority-toolkit' ),
							help: invalidSameAsCount > 0 ? __( 'One or more links look invalid. Use full URLs (http/https), one per line.', 'context-authority-toolkit' ) : __( 'Add links to trusted pages about this term (for example: Wikipedia, industry standards, or official docs). Use one URL per line (or comma-separated).', 'context-authority-toolkit' ),
							value: sameAsLinks.join( '\n' ),
							rows: 4,
							placeholder: __( 'https://en.wikipedia.org/wiki/...\nhttps://www.example.org/glossary/...', 'context-authority-toolkit' ),
							onChange: function( value ) {
								setMetaValue( SAME_AS_META_KEY, parseSameAs( value ) );
							}
						} ),
						createElement( WikidataSameAsLookup, {
							currentPostId: currentPostId,
							sameAsLinks: sameAsLinks,
							setMetaValue: setMetaValue
						} )
					)
				),
				createElement(
					PanelRow,
					null,
					createElement(
						'div',
						{ style: { width: '100%' } },
						createElement( 'strong', null, __( 'Sources and References', 'context-authority-toolkit' ) ),
						createElement(
							'p',
							{ style: { marginTop: '8px', marginBottom: '12px' } },
							__( 'Add the sources that support this definition. Include at least the URL, and optionally the title, publisher, and date.', 'context-authority-toolkit' )
						),
						sourceRows.length ? sourceRows : createElement( 'p', null, __( 'No sources added yet. Add your first reference below.', 'context-authority-toolkit' ) ),
						createElement( Button, {
							isPrimary: false,
							isSecondary: true,
							onClick: addSourceItem
						}, __( 'Add Source / Reference', 'context-authority-toolkit' ) )
					)
				)
			)
		);
	}

	registerPlugin(
		'cat-term-sidebar-fields',
		{
			render: TermSidebarFields
		}
	);
} )( window.wp );
