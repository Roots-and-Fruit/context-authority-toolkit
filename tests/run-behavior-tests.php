<?php
// phpcs:ignoreFile -- Executable wp eval-file behavior harness; procedural globals are intentional.
/**
 * Behavior and security gate tests for Context & Authority Toolkit.
 *
 * Execute using: wp eval-file tests/run-behavior-tests.php
 *
 * PHP version 7.2+
 *
 * @category ContextAuthorityToolkit
 * @package  ContextAuthorityToolkit
 * @author   Crucible CRM <support@cruciblecrm.com>
 * @license  GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link     https://cruciblecrm.com/
 */

// phpcs:disable Generic.Files.LineLength.TooLong

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/user.php';

if ( ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary_Handler' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary_Admin' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_SEO_Peacekeeper' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Settings' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Category' ) ) {
	echo "Plugin classes are unavailable. Ensure plugin is active before running tests.\n";
	exit( 1 );
}

$failures = array();

/**
 * Record assertion failures.
 *
 * @param bool   $condition Condition result.
 * @param string $message   Failure message.
 *
 * @return void
 */
function cat_assert( $condition, $message ) {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $message;
	}
}

/**
 * Count substring occurrences.
 *
 * @param string $haystack Full string.
 * @param string $needle   Search token.
 *
 * @return int
 */
function cat_count_occurrences( $haystack, $needle ) {
	return substr_count( $haystack, $needle );
}

/**
 * Create a glossary term post.
 *
 * @param string   $name           Term name.
 * @param string   $single_content Single term page content.
 * @param string[] $alternatives   Alternatives list.
 * @param string   $tooltip        Tooltip text.
 * @param string[] $same_as        sameAs links.
 * @param array[]  $sources        Citation source rows.
 *
 * @return int
 */
function cat_create_term( $name, $single_content, array $alternatives = array(), $tooltip = '', array $same_as = array(), array $sources = array() ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => $single_content,
		)
	);

	if ( ! is_wp_error( $post_id ) && ! empty( $alternatives ) ) {
		update_post_meta( $post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::ALTERNATIVES_META_KEY, $alternatives );
	}

	if ( ! is_wp_error( $post_id ) && '' !== $tooltip ) {
		update_post_meta( $post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, $tooltip );
	}

	if ( ! is_wp_error( $post_id ) && ! empty( $same_as ) ) {
		update_post_meta( $post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::SAME_AS_META_KEY, $same_as );
	}

	if ( ! is_wp_error( $post_id ) && ! empty( $sources ) ) {
		update_post_meta( $post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::SOURCES_META_KEY, $sources );
	}

	return (int) $post_id;
}

/**
 * Create a public post for content-linking tests.
 *
 * @param string $title   Post title.
 * @param string $content Post content.
 *
 * @return int
 */
function cat_create_public_post( $title, $content ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		)
	);

	return (int) $post_id;
}

$admin_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);

if ( empty( $admin_users ) ) {
	echo "No administrator user available for tests.\n";
	exit( 1 );
}

$admin_user_id = (int) $admin_users[0];
wp_set_current_user( $admin_user_id );

$admin_handler = new \ContextAuthorityToolkit\Cat_Glossary_Admin();
$admin_handler->register_post_type();
$admin_handler->register_post_meta();
$seo_peacekeeper = new \ContextAuthorityToolkit\Cat_SEO_Peacekeeper();

$test_post_ids = array();

// Test 1: Longer term precedence should still allow shorter standalone matches.
$test_post_ids[] = cat_create_term( 'WordPress', 'Single content A', array(), 'Short description.' );
$test_post_ids[] = cat_create_term( 'WordPress.org', 'Single content B', array(), 'Long description.' );
$content         = 'WordPress.org powers WordPress.';
$filtered        = apply_filters( 'the_content', $content );

cat_assert(
	cat_count_occurrences( $filtered, 'cat-glossary-item-container' ) === 2,
	'Longer term precedence test failed: expected two wrapped terms.'
);
cat_assert(
	strpos( $filtered, '>WordPress.org<' ) !== false,
	'Longer term precedence test failed: WordPress.org was not wrapped.'
);

// Test 2: Short term case sensitivity (<=3 chars) should require exact case.
$test_post_ids[] = cat_create_term( 'API', 'Single content C', array(), 'Application Programming Interface.' );
$filtered_case   = apply_filters( 'the_content', 'api and API are different here.' );
cat_assert(
	cat_count_occurrences( $filtered_case, 'cat-glossary-item-container' ) === 1,
	'Case sensitivity test failed: expected exactly one wrapped short term.'
);
cat_assert(
	strpos( $filtered_case, 'api and' ) !== false,
	'Case sensitivity test failed: lowercase short term should remain plain text.'
);

// Test 3: Terms inside excluded HTML tags should not be wrapped.
$excluded_content  = 'Outside WordPress <a href="#">WordPress</a> <code>API</code> <pre>WordPress.org</pre>';
$filtered_excluded = apply_filters( 'the_content', $excluded_content );
cat_assert(
	cat_count_occurrences( $filtered_excluded, 'cat-glossary-item-container' ) === 1,
	'Excluded tags test failed: only non-excluded text should be wrapped.'
);
cat_assert(
	strpos( $filtered_excluded, '<a href="#">WordPress</a>' ) !== false,
	'Excluded tags test failed: anchor content should be untouched.'
);
cat_assert(
	strpos( $filtered_excluded, '<code>API</code>' ) !== false,
	'Excluded tags test failed: code content should be untouched.'
);

// Test 4: Auto-linking should cap total links per content to first two mentions.
$filtered_repeat = apply_filters( 'the_content', 'WordPress WordPress WordPress' );
cat_assert(
	cat_count_occurrences( $filtered_repeat, 'cat-glossary-item-container' ) === 2,
	'Link cap test failed: expected only first two mentions to be wrapped.'
);

// Test 5: Interactive popover markup contract and Learn more permalink link.
$learn_more_post_id = cat_create_term(
	'Permalink Term',
	'Single term block content should not be used as tooltip.',
	array(),
	"Tooltip line one.\n<strong>Tooltip line two</strong>"
);
$test_post_ids[]    = $learn_more_post_id;
$filtered_link      = apply_filters( 'the_content', 'Permalink Term appears in content.' );
$expected_href      = esc_url( get_permalink( $learn_more_post_id ) );

cat_assert(
	strpos( $filtered_link, 'class="cat-glossary-item-trigger"' ) !== false,
	'Popover contract failed: trigger button class is missing.'
);
cat_assert(
	strpos( $filtered_link, 'aria-expanded="false"' ) !== false && strpos( $filtered_link, 'aria-haspopup="dialog"' ) !== false,
	'Popover contract failed: trigger ARIA state attributes are missing.'
);
cat_assert(
	preg_match( '/id="([^"]*cat-glossary-item-trigger[^"]*)"/', $filtered_link, $trigger_match ) === 1,
	'Popover contract failed: trigger ID was not rendered.'
);
cat_assert(
	preg_match( '/id="([^"]*cat-glossary-item-panel[^"]*)"/', $filtered_link, $panel_match ) === 1,
	'Popover contract failed: panel ID was not rendered.'
);

if ( ! empty( $trigger_match[1] ) && ! empty( $panel_match[1] ) ) {
	cat_assert(
		strpos( $filtered_link, 'aria-controls="' . esc_attr( $panel_match[1] ) . '"' ) !== false,
		'Popover contract failed: aria-controls does not match panel ID.'
	);
	cat_assert(
		strpos( $filtered_link, 'aria-labelledby="' . esc_attr( $trigger_match[1] ) . '"' ) !== false,
		'Popover contract failed: panel aria-labelledby does not match trigger ID.'
	);
}

cat_assert(
	strpos( $filtered_link, 'role="dialog"' ) !== false && strpos( $filtered_link, ' hidden' ) !== false,
	'Popover contract failed: panel dialog role/hidden state is missing.'
);
cat_assert(
	cat_count_occurrences( $filtered_link, 'class="cat-glossary-item-link"' ) === 1,
	'Learn more link test failed: expected exactly one Learn more link.'
);
cat_assert(
	strpos( $filtered_link, '>Learn more<' ) !== false,
	'Learn more link test failed: anchor text must be exactly Learn more.'
);
cat_assert(
	strpos( $filtered_link, 'href="' . $expected_href . '"' ) !== false,
	'Learn more link test failed: href does not match term permalink.'
);
cat_assert(
	strpos( $filtered_link, 'class="cat-glossary-item-link" href="' . $expected_href . '" rel="help"' ) !== false,
	'Learn more link test failed: expected rel="help" semantic relation on definition link.'
);
cat_assert(
	strpos( $expected_href, '?post_type=' ) === false && strpos( $expected_href, '&p=' ) === false,
	'Learn more link test failed: expected permalink URL instead of query-style URL.'
);
cat_assert(
	strpos( $filtered_link, 'Edit Term' ) === false,
	'Learn more link test failed: legacy Edit Term link text must not appear in frontend output.'
);
cat_assert(
	strpos( $filtered_link, 'Tooltip line one.<br />' ) !== false,
	'Tooltip source test failed: expected newline conversion into <br />.'
);
cat_assert(
	strpos( $filtered_link, '&lt;strong&gt;Tooltip line two&lt;/strong&gt;' ) !== false,
	'Tooltip source test failed: expected tooltip HTML to be escaped and rendered as text.'
);
cat_assert(
	strpos( $filtered_link, 'Single term block content should not be used as tooltip.' ) === false,
	'Tooltip source test failed: tooltip output still appears to source from post_content.'
);

// Test 6: Meta authorization callback should reject subscribers and allow admins.
$secured_post_id = cat_create_term( 'Security Term', 'Security test term.', array(), 'Security tooltip.' );
$test_post_ids[] = $secured_post_id;

$subscriber_login = 'cat_subscriber_' . wp_generate_password( 8, false, false );
$subscriber_user  = wp_insert_user(
	array(
		'user_login' => $subscriber_login,
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => $subscriber_login . '@example.com',
		'role'       => 'subscriber',
	)
);

// Subscriber should not be authorized to edit term meta.
if ( ! is_wp_error( $subscriber_user ) ) {
	wp_set_current_user( (int) $subscriber_user );
	$allowed = $admin_handler->can_edit_term_meta( false, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, $secured_post_id, (int) $subscriber_user );
	cat_assert(
		false === $allowed,
		'Security test failed: subscriber should not be authorized to edit term meta.'
	);
}

// Admin should be authorized to edit term meta.
wp_set_current_user( $admin_user_id );
$allowed = $admin_handler->can_edit_term_meta( false, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, $secured_post_id, $admin_user_id );
cat_assert(
	true === $allowed,
	'Security test failed: administrator should be authorized to edit term meta.'
);

// Test 7: Meta sanitizers should enforce field rules.
$sanitized_alternatives = $admin_handler->sanitize_alternatives_meta(
	array(
		' WP ',
		'WP',
		'a',
		'API',
	)
);
cat_assert(
	array( 'WP', 'API' ) === $sanitized_alternatives,
	'Sanitizer test failed: alternatives sanitizer did not normalize values as expected.'
);
$raw_tooltip       = "Tooltip first line\r\n<script>alert('x')</script>";
$sanitized_tooltip = $admin_handler->sanitize_tooltip_meta( $raw_tooltip );
cat_assert(
	strpos( $sanitized_tooltip, "\r" ) === false && strpos( $sanitized_tooltip, "<script>alert('x')</script>" ) !== false,
	'Sanitizer test failed: tooltip sanitizer should normalize line endings without stripping literal text.'
);
$sanitized_same_as = $admin_handler->sanitize_same_as_meta(
	array(
		' https://example.com/a ',
		'not-a-url',
		'https://example.com/a',
		'https://example.com/b',
	)
);
cat_assert(
	array( 'https://example.com/a', 'https://example.com/b' ) === $sanitized_same_as,
	'Sanitizer test failed: sameAs sanitizer should keep only unique valid URLs.'
);
$sanitized_sources = $admin_handler->sanitize_sources_meta(
	array(
		array(
			'url'           => 'https://example.com/source-a',
			'title'         => 'Source A',
			'publisher'     => 'Publisher A',
			'datePublished' => '2025-01-05',
		),
		array(
			'url' => '',
		),
	)
);
cat_assert(
	1 === count( $sanitized_sources ) && 'https://example.com/source-a' === $sanitized_sources[0]['url'],
	'Sanitizer test failed: source sanitizer should remove invalid rows and keep valid source entries.'
);

// Test 8: One-time migration copies legacy post_content only when tooltip meta is empty.
delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY );
$migration_source_id = cat_create_term( 'Migration Term', 'Legacy content for migration.', array(), '' );
$test_post_ids[]     = $migration_source_id;
$migration_target_id = cat_create_term( 'Migration Already Set', 'Should not overwrite.', array(), 'Existing tooltip text.' );
$test_post_ids[]     = $migration_target_id;
$admin_handler->maybe_run_tooltip_migration();

$migrated_tooltip = get_post_meta( $migration_source_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, true );
cat_assert(
	'Legacy content for migration.' === $migrated_tooltip,
	'Migration test failed: empty tooltip meta should be populated from legacy post_content.'
);
$preserved_tooltip = get_post_meta( $migration_target_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, true );
cat_assert(
	'Existing tooltip text.' === $preserved_tooltip,
	'Migration test failed: existing tooltip meta should not be overwritten.'
);

// Migration should not run again once option is set.
wp_update_post(
	array(
		'ID'           => $migration_source_id,
		'post_content' => 'Updated post content after migration.',
	)
);
$admin_handler->maybe_run_tooltip_migration();
$migrated_tooltip_after_second_run = get_post_meta( $migration_source_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, true );
cat_assert(
	'Legacy content for migration.' === $migrated_tooltip_after_second_run,
	'Migration test failed: migration should be idempotent and skip second run.'
);

// Test 9: Public post toggle disables/enables glossary auto-linking.
$toggle_post_id  = cat_create_public_post( 'Toggle Test Post', 'WordPress remains plain text when disabled.' );
$test_post_ids[] = $toggle_post_id;

update_post_meta( $toggle_post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY, true );
$toggle_post = get_post( $toggle_post_id );
setup_postdata( $toggle_post );
$filtered_toggle_disabled = apply_filters( 'the_content', 'WordPress should not be linked while disabled.' );
cat_assert(
	strpos( $filtered_toggle_disabled, 'cat-glossary-item-container' ) === false,
	'Auto-link toggle test failed: expected no glossary links when disabled.'
);

update_post_meta( $toggle_post_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY, false );
$toggle_post = get_post( $toggle_post_id );
setup_postdata( $toggle_post );
$filtered_toggle_enabled = apply_filters( 'the_content', 'WordPress should be linked while enabled.' );
cat_assert(
	strpos( $filtered_toggle_enabled, 'cat-glossary-item-container' ) !== false,
	'Auto-link toggle test failed: expected glossary links when enabled.'
);

// Test 10: Term content does not self-link but still links other glossary terms.
$self_term_id    = cat_create_term( 'Objective', 'Objective mentions WordPress.', array(), 'Objective tooltip.' );
$test_post_ids[] = $self_term_id;
$self_term_post  = get_post( $self_term_id );
setup_postdata( $self_term_post );
$filtered_self_term = apply_filters( 'the_content', 'Objective mentions WordPress.' );

cat_assert(
	strpos( $filtered_self_term, '>Objective<' ) === false,
	'Self-link test failed: term title should not be converted into a self-link trigger.'
);
cat_assert(
	strpos( $filtered_self_term, 'Objective mentions' ) !== false,
	'Self-link test failed: original self-term text should remain plain text.'
);
cat_assert(
	strpos( $filtered_self_term, '>WordPress<' ) !== false && strpos( $filtered_self_term, 'cat-glossary-item-container' ) !== false,
	'Self-link test failed: other glossary terms should still be linked in term content.'
);

// Test 11: Auto-linking should only run in paragraph and list item content.
$scope_test_content = '<h2>WordPress</h2><p>WordPress appears in paragraph.</p><ul><li>API appears in list item.</li></ul>';
$filtered_scope     = apply_filters( 'the_content', $scope_test_content );
cat_assert(
	strpos( $filtered_scope, '<h2>WordPress</h2>' ) !== false,
	'Scope test failed: heading text should remain untouched.'
);
cat_assert(
	cat_count_occurrences( $filtered_scope, 'cat-glossary-item-container' ) === 2,
	'Scope test failed: expected glossary links only inside paragraph/list-item content.'
);

// Test 12: Canonical schema builder should map CAT data and keep citations/entity links.
$schema_term_id = cat_create_term(
	'Entity Resolution',
	'Fallback post content for read aloud.',
	array(),
	'Entity resolution is matching records that refer to the same real-world entity.',
	array(
		'https://en.wikipedia.org/wiki/Record_linkage',
		'https://www.wikidata.org/wiki/Q1266546',
	),
	array(
		array(
			'url'           => 'https://doi.org/10.1145/123456',
			'title'         => 'A Survey of Entity Resolution',
			'publisher'     => 'ACM',
			'datePublished' => '2024-06-01',
		),
	)
);
$test_post_ids[] = $schema_term_id;
$canonical_node  = $seo_peacekeeper->get_canonical_term_schema( $schema_term_id );
cat_assert(
	'DefinedTerm' === $canonical_node['@type'] && ! empty( $canonical_node['inDefinedTermSet'] ),
	'SEO Peacekeeper test failed: canonical node should include DefinedTerm type and inDefinedTermSet.'
);
cat_assert(
	! empty( $canonical_node['sameAs'] ) && 2 === count( $canonical_node['sameAs'] ),
	'SEO Peacekeeper test failed: canonical node should preserve sameAs links.'
);
cat_assert(
	! empty( $canonical_node['citation'] ) && is_array( $canonical_node['citation'][0] ),
	'SEO Peacekeeper test failed: canonical node should preserve citation objects.'
);

// Test 13: Semantic wrapper should inject dfn name markup in first paragraph only.
$schema_term_post = get_post( $schema_term_id );
setup_postdata( $schema_term_post );
$semantic_content = apply_filters(
	'the_content',
	'<p>Entity Resolution links records across systems.</p><p>Entity Resolution appears again in later content.</p>'
);
cat_assert(
	strpos( $semantic_content, 'cat-defined-term-semantic' ) !== false && strpos( $semantic_content, '<dfn id="cat-defined-term-name-' ) !== false && strpos( $semantic_content, 'itemprop="name">Entity Resolution</dfn>' ) !== false,
	'SEO Peacekeeper test failed: semantic term wrapper should inject term-name dfn markup.'
);
cat_assert(
	strpos( $semantic_content, 'role="definition"' ) !== false,
	'SEO Peacekeeper test failed: semantic wrapper should include role definition.'
);
cat_assert(
	1 === preg_match_all( '/<dfn\b[^>]*itemprop="name"[^>]*>Entity Resolution<\/dfn>/', $semantic_content ),
	'SEO Peacekeeper test failed: semantic term wrapper should only annotate first first-paragraph occurrence.'
);

// Test 13b: Existing manual dfn should be annotated, not duplicated.
$semantic_content_manual = apply_filters(
	'the_content',
	'<p><dfn>Entity Resolution</dfn> links records.</p><p>Entity Resolution appears later.</p>'
);
cat_assert(
	1 === preg_match_all( '/<dfn\b/i', $semantic_content_manual ),
	'SEO Peacekeeper test failed: existing manual dfn should not be duplicated.'
);
cat_assert(
	false !== strpos( $semantic_content_manual, '<dfn itemprop="name" id="cat-defined-term-name-' ) || false !== strpos( $semantic_content_manual, '<dfn id="cat-defined-term-name-' ),
	'SEO Peacekeeper test failed: existing manual dfn should be annotated with semantic name attributes.'
);

// Test 14: Read aloud sanitizer should normalize shortcode/symbol-heavy text.
$read_aloud = $seo_peacekeeper->prepare_read_aloud_text( "Term [shortcode] \n\t details &amp; symbols \x01" );
cat_assert(
	strpos( $read_aloud, '[' ) === false && strpos( $read_aloud, '&amp;' ) === false && strpos( $read_aloud, 'symbols' ) !== false,
	'SEO Peacekeeper test failed: read aloud sanitizer should remove shortcode tokens and decode entities.'
);

// Test 15: Rank Math and SEOPress adapters should inject canonical node with sameAs/citation parity.
update_option( \ContextAuthorityToolkit\Cat_SEO_Peacekeeper::OPTION_SCHEMA_OUTPUT_MODE, 'auto' );
if ( ! defined( 'SEOPRESS_VERSION' ) ) {
	define( 'SEOPRESS_VERSION', '8.0.0-test' );
}
if ( defined( 'SEOPRESS_PRO_VERSION' ) || defined( 'SEOPRESS_PRO_PLUGIN_DIR_PATH' ) ) {
	$seopress_data     = array( '@graph' => array() );
	$seopress_filtered = $seo_peacekeeper->inject_seopress_schema( $seopress_data );
	$seopress_node     = end( $seopress_filtered['@graph'] );
	cat_assert(
		! empty( $seopress_node['sameAs'] ) && ! empty( $seopress_node['citation'] ),
		'SEO Peacekeeper test failed: SEOPress adapter should keep sameAs and citation data when compatible transport is available.'
	);
} else {
	$seopress_data     = array( '@graph' => array() );
	$seopress_filtered = $seo_peacekeeper->inject_seopress_schema( $seopress_data );
	cat_assert(
		$seopress_filtered === $seopress_data,
		'SEO Peacekeeper test failed: SEOPress Free should not become schema transport owner; standalone fallback should remain responsible for JSON-LD output.'
	);
}

if ( ! defined( 'RANK_MATH_VERSION' ) ) {
	define( 'RANK_MATH_VERSION', '1.0.0-test' );
}
$rank_math_data     = array( '@graph' => array() );
$rank_math_filtered = $seo_peacekeeper->inject_rank_math_json_ld( $rank_math_data, null );
$rank_math_node     = end( $rank_math_filtered['@graph'] );
cat_assert(
	! empty( $rank_math_node['sameAs'] ) && ! empty( $rank_math_node['citation'] ),
	'SEO Peacekeeper test failed: Rank Math adapter should keep sameAs and citation data.'
);

// Test 16: Term settings defaults and sanitizers.
$saved_term_slug         = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, null );
$saved_categories        = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, null );
$saved_permalink_include = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, null );

delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG );
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );

cat_assert(
	'term' === \ContextAuthorityToolkit\Cat_Term_Settings::get_term_slug(),
	'Term settings test failed: default term slug must be term.'
);
cat_assert(
	false === \ContextAuthorityToolkit\Cat_Term_Settings::are_categories_enabled(),
	'Term settings test failed: categories must default to disabled.'
);
cat_assert(
	false === \ContextAuthorityToolkit\Cat_Term_Settings::should_include_category_in_permalink(),
	'Term settings test failed: permalink category include must default to false.'
);

$sanitized_invalid_slug = \ContextAuthorityToolkit\Cat_Term_Settings::sanitize_term_slug( 'Foo Bar!!' );
cat_assert(
	'foo-bar' === $sanitized_invalid_slug,
	'Term settings test failed: invalid slug should sanitize to foo-bar, got: ' . $sanitized_invalid_slug
);
cat_assert(
	'term' === \ContextAuthorityToolkit\Cat_Term_Settings::sanitize_term_slug( '' ),
	'Term settings test failed: empty slug must fall back to term.'
);
cat_assert(
	'term' === \ContextAuthorityToolkit\Cat_Term_Settings::sanitize_term_slug( array( 'bad' ) ),
	'Term settings test failed: non-string slug must fall back to term.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, 'glossary' );
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, true );
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, true );

cat_assert(
	'glossary' === \ContextAuthorityToolkit\Cat_Term_Settings::get_term_slug(),
	'Term settings test failed: getter must reflect stored slug.'
);
cat_assert(
	true === \ContextAuthorityToolkit\Cat_Term_Settings::are_categories_enabled(),
	'Term settings test failed: getter must reflect categories enabled.'
);
cat_assert(
	true === \ContextAuthorityToolkit\Cat_Term_Settings::should_include_category_in_permalink(),
	'Term settings test failed: getter must reflect permalink include when categories enabled.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, false );
cat_assert(
	false === \ContextAuthorityToolkit\Cat_Term_Settings::should_include_category_in_permalink(),
	'Term settings test failed: permalink include must be false when categories are disabled.'
);

if ( null === $saved_term_slug ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, $saved_term_slug );
}
if ( null === $saved_categories ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, $saved_categories );
}
if ( null === $saved_permalink_include ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, $saved_permalink_include );
}

// Test 17: Term → Settings submenu is registered under the term CPT menu.
global $submenu;
if ( ! is_array( $submenu ) ) {
	$submenu = array();
}
do_action( 'admin_menu' );
$term_parent     = 'edit.php?post_type=' . \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE;
$settings_found  = false;
$settings_cap_ok = false;
if ( isset( $submenu[ $term_parent ] ) && is_array( $submenu[ $term_parent ] ) ) {
	foreach ( $submenu[ $term_parent ] as $item ) {
		if ( isset( $item[2] ) && \ContextAuthorityToolkit\Cat_Term_Settings::PAGE_SLUG === $item[2] ) {
			$settings_found = true;
			$settings_cap_ok = isset( $item[1] ) && 'manage_options' === $item[1];
			break;
		}
	}
}
cat_assert(
	$settings_found,
	'Term settings test failed: Settings submenu must be registered under Term admin menu.'
);
cat_assert(
	$settings_cap_ok,
	'Term settings test failed: Settings submenu must require manage_options.'
);

$options_parent_has_term_settings = false;
if ( isset( $submenu['options-general.php'] ) && is_array( $submenu['options-general.php'] ) ) {
	foreach ( $submenu['options-general.php'] as $item ) {
		if ( isset( $item[2] ) && \ContextAuthorityToolkit\Cat_Term_Settings::PAGE_SLUG === $item[2] ) {
			$options_parent_has_term_settings = true;
			break;
		}
	}
}
cat_assert(
	! $options_parent_has_term_settings,
	'Term settings test failed: Term Settings must not appear under Settings → General.'
);

// Test 18: CPT rewrite slug follows Cat_Term_Settings and restores cleanly.
$rewrite_saved_slug = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, null );
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG );

$admin_for_rewrite = new \ContextAuthorityToolkit\Cat_Glossary_Admin();
$admin_for_rewrite->register_post_type();
flush_rewrite_rules( false );

$default_pto = get_post_type_object( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE );
cat_assert(
	$default_pto && isset( $default_pto->rewrite['slug'] ) && 'term' === $default_pto->rewrite['slug'],
	'Term settings test failed: default CPT rewrite slug must be term.'
);

$rewrite_term_id = cat_create_term( 'Rewrite Probe Default', 'Default rewrite body.' );
$test_post_ids[] = $rewrite_term_id;
$default_permalink = get_permalink( $rewrite_term_id );
cat_assert(
	is_string( $default_permalink ) && false !== strpos( $default_permalink, '/term/' ),
	'Term settings test failed: default term permalink must contain /term/.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, 'glossary' );
$admin_for_rewrite->register_post_type();
flush_rewrite_rules( false );

$custom_pto = get_post_type_object( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE );
cat_assert(
	$custom_pto && isset( $custom_pto->rewrite['slug'] ) && 'glossary' === $custom_pto->rewrite['slug'],
	'Term settings test failed: CPT rewrite slug must follow configured glossary slug.'
);

$custom_term_id = cat_create_term( 'Rewrite Probe Custom', 'Custom rewrite body.' );
$test_post_ids[] = $custom_term_id;
$custom_permalink = get_permalink( $custom_term_id );
cat_assert(
	is_string( $custom_permalink ) && false !== strpos( $custom_permalink, '/glossary/' ),
	'Term settings test failed: custom term permalink must contain /glossary/.'
);
cat_assert(
	\ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE === get_post_type( $custom_term_id ),
	'Term settings test failed: CPT post type key must remain term.'
);

// Saving permalink-include must not break CPT registration.
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, true );
$admin_for_rewrite->register_post_type();
$after_permalink_opt = get_post_type_object( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE );
cat_assert(
	$after_permalink_opt && isset( $after_permalink_opt->rewrite['slug'] ) && 'glossary' === $after_permalink_opt->rewrite['slug'],
	'Term settings test failed: saving permalink-include must not break CPT rewrite registration.'
);

if ( null === $rewrite_saved_slug ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_TERM_SLUG, $rewrite_saved_slug );
}
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );
$admin_for_rewrite->register_post_type();
flush_rewrite_rules( false );

// Test 19: Settings assets enqueue only on Term Settings screen.
$settings_for_assets = new \ContextAuthorityToolkit\Cat_Term_Settings();
$settings_for_assets->enqueue_settings_assets( 'edit.php' );
cat_assert(
	! wp_script_is( 'cat-term-settings', 'enqueued' ),
	'Term settings test failed: settings script must not enqueue on unrelated admin screens.'
);
$settings_for_assets->enqueue_settings_assets( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE . '_page_' . \ContextAuthorityToolkit\Cat_Term_Settings::PAGE_SLUG );
cat_assert(
	wp_script_is( 'cat-term-settings', 'enqueued' ),
	'Term settings test failed: settings script must enqueue on Term Settings screen.'
);

// Test 20: Category taxonomy gated by settings + schema DefinedTermSet mapping.
$category_saved_enabled  = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, null );
$category_saved_permalink = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, null );
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );

$category_module = new \ContextAuthorityToolkit\Cat_Term_Category();
$category_module->register_taxonomy();
cat_assert(
	! taxonomy_exists( \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY ),
	'Category taxonomy test failed: taxonomy must not register when categories are disabled.'
);
cat_assert(
	\ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY !== 'category',
	'Category taxonomy test failed: taxonomy slug must not be core category.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, true );
$category_module->register_taxonomy();
cat_assert(
	taxonomy_exists( \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY ),
	'Category taxonomy test failed: taxonomy must register when categories are enabled.'
);

$tax_obj = get_taxonomy( \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	$tax_obj && 'Category' === $tax_obj->labels->singular_name && 'Categories' === $tax_obj->labels->name,
	'Category taxonomy test failed: labels must be Category / Categories.'
);
cat_assert(
	! empty( $tax_obj->show_in_rest ),
	'Category taxonomy test failed: taxonomy must be exposed in REST.'
);
cat_assert(
	in_array( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE, (array) $tax_obj->object_type, true ),
	'Category taxonomy test failed: taxonomy must be attached to term CPT only context.'
);

$cat_term = wp_insert_term( 'SEO Authority', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'seo-authority' ) );
cat_assert(
	! is_wp_error( $cat_term ),
	'Category taxonomy test failed: could not create Category term.'
);
$cat_term_id = (int) $cat_term['term_id'];

$categorized_post_id = cat_create_term(
	'Entity Graph',
	'<p>Entity Graph connects records.</p>',
	array(),
	'Entity Graph tooltip',
	array( 'https://schema.org/DefinedTerm' ),
	array(
		array(
			'url'           => 'https://example.com/entity-graph',
			'title'         => 'Entity Graph Source',
			'publisher'     => 'Example',
			'datePublished' => '2026-01-15',
		),
	)
);
$test_post_ids[] = $categorized_post_id;
wp_set_object_terms( $categorized_post_id, array( $cat_term_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );

$fallback_schema = $seo_peacekeeper->get_canonical_term_schema( $schema_term_id );
$category_link   = get_term_link( $cat_term_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
$with_cat_schema = $seo_peacekeeper->get_canonical_term_schema( $categorized_post_id );

cat_assert(
	! empty( $fallback_schema['inDefinedTermSet'] ),
	'Category taxonomy test failed: uncategorized terms still need inDefinedTermSet fallback.'
);
cat_assert(
	! is_wp_error( $category_link ) && ! empty( $with_cat_schema['inDefinedTermSet'] ) && (string) $category_link === (string) $with_cat_schema['inDefinedTermSet'],
	'Category taxonomy test failed: categorized term inDefinedTermSet must use Category archive URL.'
);

$defined_set_schema = \ContextAuthorityToolkit\Cat_Term_Category::get_canonical_defined_term_set_schema( get_term( $cat_term_id ) );
cat_assert(
	! empty( $defined_set_schema['@type'] ) && 'DefinedTermSet' === $defined_set_schema['@type'],
	'Category taxonomy test failed: Category archive schema must be DefinedTermSet.'
);
cat_assert(
	! empty( $defined_set_schema['name'] ) && 'SEO Authority' === $defined_set_schema['name'],
	'Category taxonomy test failed: DefinedTermSet name must match Category.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, true );
$admin_for_category_rewrite = new \ContextAuthorityToolkit\Cat_Glossary_Admin();
$admin_for_category_rewrite->register_post_type();
flush_rewrite_rules( false );
$categorized_permalink = get_permalink( $categorized_post_id );
cat_assert(
	is_string( $categorized_permalink ) && false !== strpos( $categorized_permalink, '/seo-authority/' ),
	'Category taxonomy test failed: permalink include mode must embed Category slug.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, false );
$disabled_set_url = \ContextAuthorityToolkit\Cat_Term_Category::get_defined_term_set_url_for_post( $categorized_post_id );
cat_assert(
	null === \ContextAuthorityToolkit\Cat_Term_Category::get_primary_category( $categorized_post_id ),
	'Category taxonomy test failed: primary category helper must return null when categories disabled.'
);
cat_assert(
	is_string( $disabled_set_url ) && ( is_wp_error( $category_link ) || (string) $category_link !== (string) $disabled_set_url ),
	'Category taxonomy test failed: disabled categories must fall back away from Category archive URL.'
);

wp_delete_term( $cat_term_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );

if ( null === $category_saved_enabled ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, $category_saved_enabled );
}
if ( null === $category_saved_permalink ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, $category_saved_permalink );
}
$admin_for_category_rewrite->register_post_type();
flush_rewrite_rules( false );

// Test 21: Rewrite flush flag is autoloaded while set and gone after flush.
delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_REWRITE_FLUSH_NEEDED );
wp_cache_delete( 'alloptions', 'options' );
\ContextAuthorityToolkit\Cat_Term_Settings::request_rewrite_flush();
wp_cache_delete( 'alloptions', 'options' );
$alloptions_with_flag = wp_load_alloptions();
cat_assert(
	isset( $alloptions_with_flag[ \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_REWRITE_FLUSH_NEEDED ] ),
	'Flush flag test failed: flag must be autoloaded (present in alloptions) while set.'
);

$settings_for_flush = new \ContextAuthorityToolkit\Cat_Term_Settings();
$settings_for_flush->maybe_flush_rewrites();
wp_cache_delete( 'alloptions', 'options' );
$alloptions_after_flush = wp_load_alloptions();
cat_assert(
	! isset( $alloptions_after_flush[ \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_REWRITE_FLUSH_NEEDED ] ),
	'Flush flag test failed: flag must be deleted (absent from alloptions) after flush runs.'
);
cat_assert(
	false === get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_REWRITE_FLUSH_NEEDED ),
	'Flush flag test failed: option must not exist after flush.'
);

// Test 22: Category cache busts go through the canonical glossary clearer.
$glossary_cache_key = 'items-v' . \ContextAuthorityToolkit\Cat_Glossary::CACHE_VERSION;
wp_cache_set( $glossary_cache_key, array( 'sentinel' ), \ContextAuthorityToolkit\Cat_Glossary::CACHE_GROUP, HOUR_IN_SECONDS );
cat_assert(
	false !== wp_cache_get( $glossary_cache_key, \ContextAuthorityToolkit\Cat_Glossary::CACHE_GROUP ),
	'Cache clear test failed: sentinel cache seed did not persist.'
);
$category_module_for_cache = new \ContextAuthorityToolkit\Cat_Term_Category();
$category_module_for_cache->clear_glossary_cache();
cat_assert(
	false === wp_cache_get( $glossary_cache_key, \ContextAuthorityToolkit\Cat_Glossary::CACHE_GROUP ),
	'Cache clear test failed: Category clear path must invalidate the glossary items cache.'
);

wp_cache_set( $glossary_cache_key, array( 'sentinel-2' ), \ContextAuthorityToolkit\Cat_Glossary::CACHE_GROUP, HOUR_IN_SECONDS );
$category_module_for_cache->maybe_clear_cache_on_object_terms( 0, array(), array(), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	false === wp_cache_get( $glossary_cache_key, \ContextAuthorityToolkit\Cat_Glossary::CACHE_GROUP ),
	'Cache clear test failed: set_object_terms path must invalidate the glossary items cache.'
);

wp_reset_postdata();
foreach ( $test_post_ids as $test_post_id ) {
	wp_delete_post( (int) $test_post_id, true );
}

if ( ! is_wp_error( $subscriber_user ) ) {
	wp_delete_user( (int) $subscriber_user );
}
delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY );

if ( ! empty( $failures ) ) {
	echo "Behavior/Security tests FAILED:\n";
	foreach ( $failures as $failure ) {
		echo ' - ' . esc_html( $failure ) . "\n";
	}
	exit( 1 );
}

echo "Behavior/Security tests PASSED.\n";
exit( 0 );
