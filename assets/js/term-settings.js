( function() {
	'use strict';

	var slugInput = document.getElementById( 'cat_term_slug' );
	var categoriesInput = document.getElementById( 'cat_categories_enabled' );
	var permalinkInput = document.getElementById( 'cat_term_permalink_include_category' );
	var preview = document.getElementById( 'cat-term-slug-preview' );

	if ( ! slugInput || ! categoriesInput || ! permalinkInput || ! preview ) {
		return;
	}

	var homeUrl = preview.getAttribute( 'data-home-url' ) || '';
	if ( ! homeUrl ) {
		var current = preview.textContent || '';
		var match = current.match( /^(https?:\/\/[^/]+)/i );
		homeUrl = match ? match[1] : '';
	}

	function sanitizeSlug( value ) {
		return String( value || '' )
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9\-]+/g, '-' )
			.replace( /^-+|-+$/g, '' ) || 'term';
	}

	function syncPermalinkControl() {
		var enabled = categoriesInput.checked;
		permalinkInput.disabled = ! enabled;
		if ( ! enabled ) {
			permalinkInput.checked = false;
		}
	}

	function syncPreview() {
		var slug = sanitizeSlug( slugInput.value );
		var path = '/' + slug + '/example-term/';
		if ( categoriesInput.checked && permalinkInput.checked ) {
			path = '/' + slug + '/example-category/example-term/';
		}
		preview.textContent = homeUrl ? ( homeUrl.replace( /\/$/, '' ) + path ) : path;
	}

	slugInput.addEventListener( 'input', syncPreview );
	categoriesInput.addEventListener( 'change', function() {
		syncPermalinkControl();
		syncPreview();
	} );
	permalinkInput.addEventListener( 'change', syncPreview );

	syncPermalinkControl();
	syncPreview();
}() );
