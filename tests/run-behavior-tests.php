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

if ( ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary_Handler' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Glossary_Admin' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_SEO_Peacekeeper' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Single_Chrome' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Panel' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Section_Block' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Settings' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Term_Category' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Abilities' ) || ! class_exists( '\\ContextAuthorityToolkit\\Cat_Wikidata_Lookup' ) ) {
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
 * Execute a registered CAT ability.
 *
 * @param string $name  Ability name.
 * @param array  $input Ability input.
 * @return mixed|\WP_Error
 */
function cat_execute_ability( $name, array $input = array() ) {
	if ( ! function_exists( 'wp_get_ability' ) ) {
		return new WP_Error( 'cat_abilities_missing', 'Abilities API is not available.' );
	}

	$ability = wp_get_ability( $name );
	if ( ! $ability ) {
		return new WP_Error( 'cat_ability_unregistered', 'Ability is not registered: ' . $name );
	}

	return $ability->execute( $input );
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
$restore_tooltip_migration = get_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY );
$restore_tooltip_scrub     = get_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY );

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

// Test 5: Interactive popover markup contract and crawlable mention permalink.
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
	preg_match( '/<a\b([^>]*)\bclass="cat-glossary-item-trigger"([^>]*)>/', $filtered_link, $trigger_open_match ) === 1,
	'Popover contract failed: mention trigger must be an <a class="cat-glossary-item-trigger">.'
);
$trigger_attrs = isset( $trigger_open_match[1], $trigger_open_match[2] )
	? $trigger_open_match[1] . $trigger_open_match[2]
	: '';
cat_assert(
	strpos( $trigger_attrs, 'href="' . $expected_href . '"' ) !== false,
	'Popover contract failed: mention trigger href must be the term permalink.'
);
cat_assert(
	strpos( $trigger_attrs, 'rel="help"' ) !== false,
	'Popover contract failed: mention trigger must include rel="help".'
);
cat_assert(
	strpos( $filtered_link, '<button' ) === false,
	'Popover contract failed: mention trigger must not render as a button.'
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
	cat_count_occurrences( $filtered_link, 'class="cat-glossary-item-container"' ) === 1,
	'Popover contract failed: expected exactly one glossary mention wrapper.'
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
$sanitized_same_as_wikidata = $admin_handler->sanitize_same_as_meta(
	array(
		'https://www.wikidata.org/wiki/Q42',
		'ftp://example.com/bad',
		'https://www.wikidata.org/wiki/Q42',
		'javascript:alert(1)',
		'https://en.wikipedia.org/wiki/WordPress',
	)
);
cat_assert(
	array( 'https://www.wikidata.org/wiki/Q42', 'https://en.wikipedia.org/wiki/WordPress' ) === $sanitized_same_as_wikidata,
	'Sanitizer test failed: sameAs sanitizer should keep unique public http(s) URLs including Wikidata and reject non-public schemes.'
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

// Test 7b: Related terms sanitizer — published term only, unique, never self, cap 8.
$related_keep_a = cat_create_term( 'Related Keep A', '<p>Keep A</p>', array(), 'Keep A tip' );
$related_keep_b = cat_create_term( 'Related Keep B', '<p>Keep B</p>', array(), 'Keep B tip' );
$related_self   = cat_create_term( 'Related Self Host', '<p>Self host</p>', array(), 'Self tip' );
$related_draft  = wp_insert_post(
	array(
		'post_type'    => \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE,
		'post_status'  => 'draft',
		'post_title'   => 'Related Draft Term',
		'post_content' => '<p>Draft</p>',
	)
);
$related_trash = cat_create_term( 'Related Trash Term', '<p>Trash</p>', array(), 'Trash tip' );
wp_trash_post( $related_trash );
$related_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Related Non Term Page',
		'post_content' => '<p>Not a term</p>',
	)
);
$test_post_ids[] = $related_keep_a;
$test_post_ids[] = $related_keep_b;
$test_post_ids[] = $related_self;
$test_post_ids[] = $related_draft;
$test_post_ids[] = $related_trash;
$test_post_ids[] = $related_page;

$extra_related_ids = array();
for ( $i = 1; $i <= 8; $i++ ) {
	$extra_id          = cat_create_term( 'Related Cap ' . $i, '<p>Cap ' . $i . '</p>', array(), 'Cap tip ' . $i );
	$extra_related_ids[] = $extra_id;
	$test_post_ids[]     = $extra_id;
}

$sanitized_related = $admin_handler->sanitize_related_terms_meta(
	array(
		$related_keep_a,
		$related_keep_a,
		$related_self,
		$related_draft,
		$related_trash,
		$related_page,
		0,
		'not-an-id',
		$related_keep_b,
	),
	$related_self
);
cat_assert(
	array( (int) $related_keep_a, (int) $related_keep_b ) === $sanitized_related,
	'Sanitizer test failed: related terms must keep unique published terms and reject self/draft/trash/non-term.'
);

$ninth_id            = cat_create_term( 'Related Cap Ninth', '<p>Ninth</p>', array(), 'Ninth tip' );
$test_post_ids[]     = $ninth_id;
$capped_related_input = array_merge( $extra_related_ids, array( $ninth_id ) );
$capped_related       = $admin_handler->sanitize_related_terms_meta( $capped_related_input, 0 );
cat_assert(
	8 === count( $capped_related )
		&& $extra_related_ids === $capped_related
		&& ! in_array( (int) $ninth_id, $capped_related, true ),
	'Sanitizer test failed: related terms must cap at 8 and drop the ninth ID.'
);

// Test 8: One-time migration copies legacy post_content only when tooltip meta is empty.
delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY );
delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY );
$migration_source_id = cat_create_term( 'Migration Term', 'Legacy content for migration.', array(), '' );
$test_post_ids[]     = $migration_source_id;
$migration_target_id = cat_create_term( 'Migration Already Set', 'Should not overwrite.', array(), 'Existing tooltip text.' );
$test_post_ids[]     = $migration_target_id;
$block_scaffold_id   = cat_create_term(
	'Block Scaffold Migration',
	"<!-- wp:cat-toolkit/term-section {\"section\":\"example\",\"customHeading\":\"What is this Term?\"} -->\n<!-- wp:paragraph -->\n<p> </p>\n<!-- /wp:paragraph -->\n<!-- /wp:cat-toolkit/term-section -->",
	array(),
	''
);
$test_post_ids[] = $block_scaffold_id;
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
cat_assert(
	'' === (string) get_post_meta( $block_scaffold_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, true ),
	'Migration test failed: Gutenberg term-section post_content must not copy into tooltip meta.'
);

$block_only_tooltip = $admin_handler->sanitize_tooltip_meta(
	"<!-- wp:cat-toolkit/term-section\n{\"customHeading\":\"What is this Term?\"} -->\n<!-- wp:paragraph -->\n<p> </p>\n<!-- /wp:paragraph -->\n<!-- /wp:cat-toolkit/term-section -->"
);
cat_assert(
	'' === $block_only_tooltip,
	'Tooltip sanitizer test failed: block markup-only tooltip must sanitize to empty.'
);
cat_assert(
	"Tooltip line one.\n<strong>Tooltip line two</strong>" === $admin_handler->sanitize_tooltip_meta( "Tooltip line one.\n<strong>Tooltip line two</strong>" ),
	'Tooltip sanitizer test failed: intentional plain-text angle brackets must be preserved.'
);

delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY );
$polluted_tooltip_id = cat_create_term( 'Polluted Tooltip Term', '<p>Body stays in post_content.</p>', array(), '' );
$test_post_ids[]     = $polluted_tooltip_id;
$polluted_markup     = "<!-- wp:cat-toolkit/term-section {\"customHeading\":\"What is this Term?\"} -->\n<!-- wp:paragraph -->\n<p> </p>\n<!-- /wp:paragraph -->\n<!-- /wp:cat-toolkit/term-section -->";
delete_post_meta( $polluted_tooltip_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY );
global $wpdb;
$wpdb->insert(
	$wpdb->postmeta,
	array(
		'post_id'    => $polluted_tooltip_id,
		'meta_key'   => \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY,
		'meta_value' => $polluted_markup,
	),
	array( '%d', '%s', '%s' )
);
clean_post_cache( $polluted_tooltip_id );
$admin_handler->maybe_scrub_block_markup_tooltips();
cat_assert(
	'' === (string) get_post_meta( $polluted_tooltip_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, true ),
	'Tooltip scrub test failed: stored Gutenberg markup in cat_tooltip_content must be cleared.'
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

// Test 12b: Standalone term graph wraps DefinedTerm as WebPage.mainEntity.
$standalone_graph = $seo_peacekeeper->build_standalone_term_graph( $schema_term_id );
cat_assert(
	2 === count( $standalone_graph )
		&& ! empty( $standalone_graph[0]['@type'] ) && 'WebPage' === $standalone_graph[0]['@type']
		&& ! empty( $standalone_graph[1]['@type'] ) && 'DefinedTerm' === $standalone_graph[1]['@type']
		&& ! empty( $standalone_graph[0]['mainEntity'] )
		&& $standalone_graph[0]['mainEntity'] === $standalone_graph[1]['@id']
		&& ! empty( $standalone_graph[0]['url'] )
		&& $standalone_graph[0]['url'] === $standalone_graph[1]['url'],
	'SEO Peacekeeper test failed: standalone term graph should include WebPage.mainEntity pointing at DefinedTerm @id.'
);
cat_assert(
	'DefinedTerm' === $seo_peacekeeper->get_canonical_term_schema( $schema_term_id )['@type'],
	'SEO Peacekeeper test failed: get_canonical_term_schema must remain DefinedTerm-only.'
);

// Test 12c: alternateName / termCode mapping from cat_alternatives.
$alias_term_id = cat_create_term(
	'WordPress',
	'<p>WordPress is open source software.</p>',
	array( 'WP', 'WordPress', 'SaaS', 'wordpress.com' ),
	'WordPress is free and open-source CMS software.'
);
$test_post_ids[] = $alias_term_id;
$alias_schema    = $seo_peacekeeper->get_canonical_term_schema( $alias_term_id );
cat_assert(
	! empty( $alias_schema['alternateName'] )
		&& in_array( 'WP', $alias_schema['alternateName'], true )
		&& in_array( 'SaaS', $alias_schema['alternateName'], true )
		&& in_array( 'wordpress.com', $alias_schema['alternateName'], true )
		&& ! in_array( 'WordPress', $alias_schema['alternateName'], true ),
	'SEO Peacekeeper test failed: alternateName should include aliases but not duplicate the title.'
);
cat_assert(
	! empty( $alias_schema['termCode'] ) && 'WP' === $alias_schema['termCode'],
	'SEO Peacekeeper test failed: uppercase acronym aliases should map to termCode.'
);
cat_assert(
	! in_array( 'SaaS', (array) $alias_schema['termCode'], true ),
	'SEO Peacekeeper test failed: mixed-case aliases like SaaS must stay alias-only (not termCode).'
);

$empty_alias_term_id = cat_create_term(
	'No Aliases Term',
	'<p>No Aliases Term body.</p>',
	array(),
	'A term without alternatives.'
);
$test_post_ids[]     = $empty_alias_term_id;
$empty_alias_schema  = $seo_peacekeeper->get_canonical_term_schema( $empty_alias_term_id );
cat_assert(
	! isset( $empty_alias_schema['alternateName'] ) && ! isset( $empty_alias_schema['termCode'] ),
	'SEO Peacekeeper test failed: empty alternatives must omit alternateName and termCode.'
);

// Test 12d: Visible lead in the article; aliases only in the term panel.
$alias_term_post = get_post( $alias_term_id );
setup_postdata( $alias_term_post );
$alias_semantic = apply_filters(
	'the_content',
	'<p>WordPress is open source software used for publishing.</p>'
);
cat_assert(
	false !== strpos( $alias_semantic, 'cat-term-single-lead' )
		&& false !== strpos( $alias_semantic, 'WordPress is free and open-source CMS software.' )
		&& false !== strpos( $alias_semantic, 'itemprop="description"' ),
	'SEO Peacekeeper test failed: non-empty tooltip should render as visible lead inside description.'
);
$alias_article = '';
if ( preg_match( '/<article[^>]*class="cat-defined-term-semantic"[^>]*>(.*)<\/article>/s', $alias_semantic, $alias_article_match ) ) {
	$alias_article = $alias_article_match[1];
}
cat_assert(
	'' !== $alias_article
		&& false === strpos( $alias_article, 'cat-term-single-aliases' )
		&& false === strpos( $alias_article, 'Also known as' ),
	'SEO Peacekeeper test failed: DefinedTerm article must not print Also known as aliases.'
);
$alias_panel_html = ( new \ContextAuthorityToolkit\Cat_Term_Single_Chrome() )->render_aliases_html( $alias_term_id );
cat_assert(
	false !== strpos( $alias_panel_html, 'Also known as' )
		&& false !== strpos( $alias_panel_html, 'itemprop="alternateName">WP</span>' )
		&& false !== strpos( $alias_panel_html, 'itemprop="alternateName">SaaS</span>' ),
	'Term chrome test failed: panel aliases must still render with alternateName microdata.'
);
cat_assert(
	$alias_schema['description'] === 'WordPress is free and open-source CMS software.',
	'SEO Peacekeeper test failed: JSON-LD description must remain tooltip-owned.'
);

$dup_lead_term_id = cat_create_term(
	'Duplicate Lead Term',
	'<p>Same lead sentence already in the body.</p><p>More detail follows.</p>',
	array(),
	'Same lead sentence already in the body.'
);
$test_post_ids[] = $dup_lead_term_id;
$dup_lead_post   = get_post( $dup_lead_term_id );
setup_postdata( $dup_lead_post );
$dup_lead_html = apply_filters(
	'the_content',
	'<p>Same lead sentence already in the body.</p><p>More detail follows.</p>'
);
cat_assert(
	false === strpos( $dup_lead_html, 'cat-term-single-lead' )
		&& 1 === substr_count( $dup_lead_html, 'Same lead sentence already in the body.' ),
	'SEO Peacekeeper test failed: identical body start must not duplicate the tooltip lead.'
);

$empty_tooltip_term_id = cat_create_term(
	'Empty Tooltip Term',
	'<p>Empty Tooltip Term has body only.</p>',
	array(),
	''
);
$test_post_ids[] = $empty_tooltip_term_id;
$empty_tip_post  = get_post( $empty_tooltip_term_id );
setup_postdata( $empty_tip_post );
$empty_tip_html = apply_filters(
	'the_content',
	'<p>Empty Tooltip Term has body only.</p>'
);
cat_assert(
	false === strpos( $empty_tip_html, 'cat-term-single-lead' )
		&& false === strpos( $empty_tip_html, 'cat-term-single-aliases' ),
	'SEO Peacekeeper test failed: empty tooltip/alternatives must omit lead and alias chrome.'
);

// Test 12f: Related terms seeAlso + visible chrome.
$related_target_a = cat_create_term( 'SeeAlso Target Alpha', '<p>Alpha related body.</p>', array(), 'Alpha tip' );
$related_target_b = cat_create_term( 'SeeAlso Target Beta', '<p>Beta related body.</p>', array(), 'Beta tip' );
$related_draft_vis = wp_insert_post(
	array(
		'post_type'    => \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE,
		'post_status'  => 'draft',
		'post_title'   => 'SeeAlso Draft Hidden',
		'post_content' => '<p>Draft related body.</p>',
	)
);
$related_host = cat_create_term( 'SeeAlso Host Term', '<p>Host term body for related chrome.</p>', array(), 'Host tip' );
$test_post_ids[] = $related_target_a;
$test_post_ids[] = $related_target_b;
$test_post_ids[] = $related_draft_vis;
$test_post_ids[] = $related_host;

update_post_meta(
	$related_host,
	\ContextAuthorityToolkit\Cat_Glossary_Admin::RELATED_TERMS_META_KEY,
	array( $related_target_a, $related_draft_vis, $related_host, $related_target_b )
);

$related_schema = $seo_peacekeeper->get_canonical_term_schema( $related_host );
$alpha_url      = get_permalink( $related_target_a );
$beta_url       = get_permalink( $related_target_b );
cat_assert(
	! empty( $related_schema['seeAlso'] )
		&& is_array( $related_schema['seeAlso'] )
		&& array( $alpha_url, $beta_url ) === $related_schema['seeAlso'],
	'SEO Peacekeeper test failed: seeAlso must list related permalinks in stored order after read-filter.'
);
cat_assert(
	'DefinedTerm' === $related_schema['@type'],
	'SEO Peacekeeper test failed: canonical @type must stay DefinedTerm-only when seeAlso is present.'
);
$related_standalone = $seo_peacekeeper->build_standalone_term_graph( $related_host );
cat_assert(
	! empty( $related_standalone[0]['@type'] ) && 'WebPage' === $related_standalone[0]['@type']
		&& ! isset( $related_standalone[0]['seeAlso'] )
		&& ! empty( $related_standalone[1]['seeAlso'] ),
	'SEO Peacekeeper test failed: seeAlso must live on DefinedTerm, not WebPage.'
);

$empty_related_term = cat_create_term( 'No Related Host', '<p>No related body.</p>', array(), 'No related tip' );
$test_post_ids[]    = $empty_related_term;
$empty_related_schema = $seo_peacekeeper->get_canonical_term_schema( $empty_related_term );
cat_assert(
	! isset( $empty_related_schema['seeAlso'] ),
	'SEO Peacekeeper test failed: empty related list must omit seeAlso.'
);

$term_chrome     = new \ContextAuthorityToolkit\Cat_Term_Single_Chrome();
$related_html    = $term_chrome->render_related_html( $related_host );
$empty_related_html = $term_chrome->render_related_html( $empty_related_term );
cat_assert(
	false !== strpos( $related_html, 'cat-term-single-related' )
		&& false !== strpos( $related_html, 'SeeAlso Target Alpha' )
		&& false !== strpos( $related_html, 'SeeAlso Target Beta' )
		&& false !== strpos( $related_html, esc_url( $alpha_url ) )
		&& false !== strpos( $related_html, esc_url( $beta_url ) )
		&& false === strpos( $related_html, 'SeeAlso Draft Hidden' ),
	'SEO Peacekeeper test failed: related chrome must list published titles/permalinks and skip drafts.'
);
cat_assert(
	'' === $empty_related_html,
	'SEO Peacekeeper test failed: empty related list must omit the related block.'
);

$related_host_post = get_post( $related_host );
setup_postdata( $related_host_post );
$related_semantic = apply_filters(
	'the_content',
	'<p>Host term body for related chrome.</p>'
);
cat_assert(
	false !== strpos( $related_semantic, 'cat-term-single-related' )
		&& false !== strpos( $related_semantic, 'SeeAlso Target Alpha' )
		&& preg_match(
			'/<article[^>]*class="cat-defined-term-semantic"[^>]*>.*?<div itemprop="description"[^>]*>.*?<\/div>.*?cat-term-single-related/s',
			$related_semantic
		),
	'SEO Peacekeeper test failed: related HTML must render inside DefinedTerm article outside description.'
);

// Test 12e: Adapter-style graph must not emit a second WebPage when one exists.
$adapter_stub_graph = array(
	array(
		'@type' => 'WebPage',
		'url'   => get_permalink( $alias_term_id ),
	),
);
$adapter_stub_graph = $seo_peacekeeper->attach_main_entity_to_graph( $adapter_stub_graph, $alias_schema['@id'] );
$adapter_stub_graph[] = $alias_schema;
$webpage_count = 0;
$defined_count = 0;
$adapter_main  = '';
foreach ( $adapter_stub_graph as $adapter_node ) {
	if ( ! is_array( $adapter_node ) ) {
		continue;
	}
	$type = isset( $adapter_node['@type'] ) ? $adapter_node['@type'] : '';
	if ( 'WebPage' === $type ) {
		++$webpage_count;
		$adapter_main = isset( $adapter_node['mainEntity'] ) ? (string) $adapter_node['mainEntity'] : '';
	}
	if ( 'DefinedTerm' === $type ) {
		++$defined_count;
	}
}
cat_assert(
	1 === $webpage_count && 1 === $defined_count && $adapter_main === $alias_schema['@id'],
	'SEO Peacekeeper test failed: injecting CAT into a graph that already has WebPage must not yield two WebPages.'
);

// Restore schema term context for later semantic tests that reuse it.
$schema_term_post = get_post( $schema_term_id );
setup_postdata( $schema_term_post );

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

// Test 15b: Rank Math with an existing WebPage sets mainEntity and does not add a second WebPage.
$schema_term_for_rm = get_post( $schema_term_id );
setup_postdata( $schema_term_for_rm );
$rm_existing = array(
	'@graph' => array(
		array(
			'@type' => 'WebPage',
			'url'   => get_permalink( $schema_term_id ),
		),
	),
);
$rm_with_page = $seo_peacekeeper->inject_rank_math_json_ld( $rm_existing, null );
$rm_pages     = 0;
$rm_terms     = 0;
$rm_main      = '';
foreach ( $rm_with_page['@graph'] as $rm_node ) {
	if ( ! is_array( $rm_node ) ) {
		continue;
	}
	$rm_type = isset( $rm_node['@type'] ) ? $rm_node['@type'] : '';
	if ( 'WebPage' === $rm_type ) {
		++$rm_pages;
		$rm_main = isset( $rm_node['mainEntity'] ) ? (string) $rm_node['mainEntity'] : '';
	}
	if ( 'DefinedTerm' === $rm_type ) {
		++$rm_terms;
	}
}
$expected_rm_id = $seo_peacekeeper->get_canonical_term_schema( $schema_term_id )['@id'];
cat_assert(
	1 === $rm_pages && 1 === $rm_terms && $rm_main === $expected_rm_id,
	'SEO Peacekeeper test failed: Rank Math must attach mainEntity to the existing WebPage without duplicating WebPage.'
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

$secondary_cat = wp_insert_term( 'Other Primary', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'other-primary' ) );
cat_assert(
	! is_wp_error( $secondary_cat ),
	'Category taxonomy test failed: could not create secondary Category fixture.'
);
$secondary_cat_id = (int) $secondary_cat['term_id'];

$alpha_member_id = cat_create_term( 'Alpha Member', '<p>Alpha sorts first.</p>' );
$test_post_ids[] = $alpha_member_id;
wp_set_object_terms( $alpha_member_id, array( $cat_term_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );

$secondary_only_id = cat_create_term( 'Secondary Only', '<p>Assigned but not primary.</p>' );
$test_post_ids[] = $secondary_only_id;
wp_set_object_terms( $secondary_only_id, array( $cat_term_id, $secondary_cat_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
update_post_meta( $secondary_only_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, $secondary_cat_id );

$draft_member_id = wp_insert_post(
	array(
		'post_type'   => \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => 'Draft Member',
		'post_content'=> '<p>Draft must not appear.</p>',
	)
);
$test_post_ids[] = $draft_member_id;
wp_set_object_terms( $draft_member_id, array( $cat_term_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
update_post_meta( $draft_member_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, $cat_term_id );

$empty_cat = wp_insert_term( 'Empty Set', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'empty-set' ) );
cat_assert(
	! is_wp_error( $empty_cat ),
	'Category taxonomy test failed: could not create empty Category fixture.'
);
$empty_cat_id = (int) $empty_cat['term_id'];

$defined_set_schema = \ContextAuthorityToolkit\Cat_Term_Category::get_canonical_defined_term_set_schema( get_term( $cat_term_id ) );
cat_assert(
	! empty( $defined_set_schema['@type'] ) && 'DefinedTermSet' === $defined_set_schema['@type'],
	'Category taxonomy test failed: Category archive schema must be DefinedTermSet.'
);
cat_assert(
	! empty( $defined_set_schema['name'] ) && 'SEO Authority' === $defined_set_schema['name'],
	'Category taxonomy test failed: DefinedTermSet name must match Category.'
);
cat_assert(
	! empty( $defined_set_schema['hasDefinedTerm'] ) && is_array( $defined_set_schema['hasDefinedTerm'] ),
	'Category taxonomy test failed: Category with primary members must include hasDefinedTerm.'
);
$member_urls = array();
$member_ids  = array();
foreach ( $defined_set_schema['hasDefinedTerm'] as $member_node ) {
	cat_assert(
		! empty( $member_node['@type'] ) && 'DefinedTerm' === $member_node['@type'],
		'Category taxonomy test failed: hasDefinedTerm members must be compact DefinedTerm nodes.'
	);
	cat_assert(
		array( '@type', '@id', 'name', 'url' ) === array_keys( $member_node ),
		'Category taxonomy test failed: hasDefinedTerm members must not include tooltip, sameAs, or citation fields.'
	);
	$member_urls[] = (string) $member_node['url'];
	$member_ids[]  = (string) $member_node['@id'];
}
$entity_graph_url = get_permalink( $categorized_post_id );
$alpha_member_url = get_permalink( $alpha_member_id );
cat_assert(
	is_string( $entity_graph_url ) && in_array( $entity_graph_url, $member_urls, true ),
	'Category taxonomy test failed: primary member Entity Graph must appear in hasDefinedTerm.'
);
cat_assert(
	is_string( $alpha_member_url ) && in_array( $alpha_member_url, $member_urls, true ),
	'Category taxonomy test failed: primary member Alpha Member must appear in hasDefinedTerm.'
);
cat_assert(
	in_array( trailingslashit( (string) $entity_graph_url ) . '#definedterm', $member_ids, true ),
	'Category taxonomy test failed: hasDefinedTerm member @id must use permalink/#definedterm pattern.'
);
cat_assert(
	! in_array( get_permalink( $secondary_only_id ), $member_urls, true ),
	'Category taxonomy test failed: secondary-only assignment must not appear in hasDefinedTerm.'
);
cat_assert(
	! in_array( get_permalink( $draft_member_id ), $member_urls, true ),
	'Category taxonomy test failed: draft terms must not appear in hasDefinedTerm.'
);
cat_assert(
	$member_urls === array_values( array_unique( $member_urls ) ),
	'Category taxonomy test failed: hasDefinedTerm must not duplicate member URLs.'
);
$sorted_member_names = array_map(
	static function ( $node ) {
		return (string) $node['name'];
	},
	$defined_set_schema['hasDefinedTerm']
);
$sorted_expected = $sorted_member_names;
sort( $sorted_expected, SORT_STRING );
cat_assert(
	$sorted_expected === $sorted_member_names,
	'Category taxonomy test failed: hasDefinedTerm members must be sorted by title ascending.'
);
$empty_set_schema = \ContextAuthorityToolkit\Cat_Term_Category::get_canonical_defined_term_set_schema( get_term( $empty_cat_id ) );
cat_assert(
	empty( $empty_set_schema['hasDefinedTerm'] ),
	'Category taxonomy test failed: empty membership must omit hasDefinedTerm.'
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
wp_delete_term( $secondary_cat_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
wp_delete_term( $empty_cat_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );

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

// Test 23: Category caps decoupled from core manage_categories.
$phase2_saved_enabled = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, null );
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, true );
$category_module_phase2 = new \ContextAuthorityToolkit\Cat_Term_Category();
$category_module_phase2->register_taxonomy();
$category_module_phase2->register_primary_meta();

$phase2_tax = get_taxonomy( \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	$phase2_tax && 'manage_options' === $phase2_tax->cap->manage_terms && 'manage_options' === $phase2_tax->cap->edit_terms && 'manage_options' === $phase2_tax->cap->delete_terms,
	'Category caps test failed: manage/edit/delete terms must map to manage_options.'
);
cat_assert(
	$phase2_tax && 'edit_posts' === $phase2_tax->cap->assign_terms,
	'Category caps test failed: assign_terms must map to edit_posts.'
);
cat_assert(
	$phase2_tax && ! in_array( 'manage_categories', (array) $phase2_tax->cap, true ),
	'Category caps test failed: taxonomy must not use core manage_categories.'
);

// Test 24: Primary Category meta is explicit, stable, and self-healing.
cat_assert(
	registered_meta_key_exists( 'post', \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE ),
	'Primary category test failed: cat_primary_category meta must be registered for the term CPT.'
);

$primary_cat_a = wp_insert_term( 'Primary Alpha', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'primary-alpha' ) );
$primary_cat_b = wp_insert_term( 'Primary Beta', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'primary-beta' ) );
cat_assert(
	! is_wp_error( $primary_cat_a ) && ! is_wp_error( $primary_cat_b ),
	'Primary category test failed: could not create fixture Categories.'
);
$primary_cat_a_id = (int) $primary_cat_a['term_id'];
$primary_cat_b_id = (int) $primary_cat_b['term_id'];
$primary_low_id   = min( $primary_cat_a_id, $primary_cat_b_id );
$primary_high_id  = max( $primary_cat_a_id, $primary_cat_b_id );

$primary_post_id = cat_create_term( 'Primary Resolution', '<p>Primary resolution fixture.</p>' );
$test_post_ids[] = $primary_post_id;
wp_set_object_terms( $primary_post_id, array( $primary_cat_a_id, $primary_cat_b_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );

// Explicit primary meta wins regardless of assignment order.
update_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, $primary_high_id );
$resolved_primary = \ContextAuthorityToolkit\Cat_Term_Category::get_primary_category( $primary_post_id );
cat_assert(
	$resolved_primary && $primary_high_id === (int) $resolved_primary->term_id,
	'Primary category test failed: explicit primary meta must win over assignment order.'
);

// Invalid primary meta falls back to lowest assigned term ID and backfills.
update_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, 999999 );
$resolved_fallback = \ContextAuthorityToolkit\Cat_Term_Category::get_primary_category( $primary_post_id );
cat_assert(
	$resolved_fallback && $primary_low_id === (int) $resolved_fallback->term_id,
	'Primary category test failed: invalid primary must fall back to lowest assigned term ID.'
);
cat_assert(
	$primary_low_id === absint( get_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, true ) ),
	'Primary category test failed: fallback resolution must backfill the primary meta.'
);

// Removing the stored primary re-syncs meta to a remaining assignment.
wp_set_object_terms( $primary_post_id, array( $primary_high_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	$primary_high_id === absint( get_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, true ) ),
	'Primary category test failed: removing the primary assignment must re-point meta at a remaining Category.'
);

// Removing all Categories clears the primary meta.
wp_set_object_terms( $primary_post_id, array(), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	'' === (string) get_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, true ),
	'Primary category test failed: clearing all Categories must delete the primary meta.'
);

// Test 25: Permalink integrity — no synthetic segments, reserved slugs, redirects.
$phase3_saved_include = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, null );
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, true );
delete_option( \ContextAuthorityToolkit\Cat_Term_Category::REDIRECTS_OPTION );

$admin_for_phase3 = new \ContextAuthorityToolkit\Cat_Glossary_Admin();
$admin_for_phase3->register_post_type();
$category_module_phase2->register_taxonomy();
flush_rewrite_rules( false );

// No primary Category assigned: permalink must be single-segment, no synthetic slug.
$uncat_permalink = get_permalink( $primary_post_id );
cat_assert(
	is_string( $uncat_permalink ) && false === strpos( $uncat_permalink, 'uncategorized' ),
	'Permalink integrity test failed: no synthetic uncategorized segment allowed.'
);
cat_assert(
	is_string( $uncat_permalink ) && false === strpos( $uncat_permalink, '%' ) && false === strpos( (string) wp_parse_url( $uncat_permalink, PHP_URL_PATH ), '//' ),
	'Permalink integrity test failed: category-less permalink must collapse cleanly to /base/name/.'
);

// Assign Categories; deterministic primary appears in the permalink.
wp_set_object_terms( $primary_post_id, array( $primary_cat_a_id, $primary_cat_b_id ), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
$low_slug  = get_term( $primary_low_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY )->slug;
$high_slug = get_term( $primary_high_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY )->slug;
$with_primary_permalink = get_permalink( $primary_post_id );
cat_assert(
	is_string( $with_primary_permalink ) && false !== strpos( $with_primary_permalink, '/' . $low_slug . '/' ),
	'Permalink integrity test failed: permalink must contain the primary Category slug.'
);

// Changing the primary updates the permalink and records a redirect entry.
update_post_meta( $primary_post_id, \ContextAuthorityToolkit\Cat_Term_Category::PRIMARY_META_KEY, $primary_high_id );
$after_primary_change = get_permalink( $primary_post_id );
cat_assert(
	is_string( $after_primary_change ) && false !== strpos( $after_primary_change, '/' . $high_slug . '/' ),
	'Permalink integrity test failed: changing primary must update the permalink.'
);

$phase3_post_name  = (string) get_post_field( 'post_name', $primary_post_id );
$phase3_base       = \ContextAuthorityToolkit\Cat_Term_Settings::get_term_slug();
$expected_old_path = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . $low_slug . '/' . $phase3_post_name . '/' ), PHP_URL_PATH );
$expected_new_path = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . $high_slug . '/' . $phase3_post_name . '/' ), PHP_URL_PATH );
$redirect_map      = get_option( \ContextAuthorityToolkit\Cat_Term_Category::REDIRECTS_OPTION, array() );
cat_assert(
	is_array( $redirect_map ) && isset( $redirect_map[ $expected_old_path ] ) && $expected_new_path === $redirect_map[ $expected_old_path ],
	'Permalink integrity test failed: primary change must record old-path redirect to new path.'
);

// Reserved Category slugs are rejected on create and reverted on update.
$reserved_insert = wp_insert_term( 'Category', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	is_wp_error( $reserved_insert ) && 'cat_reserved_category_slug' === $reserved_insert->get_error_code(),
	'Permalink integrity test failed: reserved slug category must be rejected on insert.'
);
$reserved_explicit = wp_insert_term( 'Perfectly Fine Name', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'term-category' ) );
cat_assert(
	is_wp_error( $reserved_explicit ),
	'Permalink integrity test failed: reserved slug term-category must be rejected on insert.'
);
wp_update_term( $primary_cat_a_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'uncategorized' ) );
cat_assert(
	'uncategorized' !== get_term( $primary_cat_a_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY )->slug,
	'Permalink integrity test failed: reserved slug must be reverted on update.'
);

// Taxonomy archive base is /term-category/, not /category/.
$phase3_archive_link = get_term_link( $primary_cat_a_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
cat_assert(
	! is_wp_error( $phase3_archive_link ) && false !== strpos( (string) $phase3_archive_link, '/term-category/' ),
	'Permalink integrity test failed: taxonomy archive base must be term-category.'
);
cat_assert(
	! is_wp_error( $phase3_archive_link ) && false === strpos( (string) $phase3_archive_link, '/' . $phase3_base . '/category/' ),
	'Permalink integrity test failed: taxonomy archive base must not be bare category.'
);

// No-category single-segment rewrite rule exists while include mode is on.
$phase3_rules = get_option( 'rewrite_rules' );
cat_assert(
	is_array( $phase3_rules ) && isset( $phase3_rules[ '^' . $phase3_base . '/([^/]+)/?$' ] ),
	'Permalink integrity test failed: single-segment rewrite rule must exist in include mode.'
);

// Renaming a Category slug must record redirects for term URLs embedding it.
$renamed_slug = $high_slug . '-renamed';
wp_update_term( $primary_high_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => $renamed_slug ) );
cat_assert(
	$renamed_slug === get_term( $primary_high_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY )->slug,
	'Category rename test failed: non-reserved slug rename must be applied.'
);

$rename_map            = get_option( \ContextAuthorityToolkit\Cat_Term_Category::REDIRECTS_OPTION, array() );
$expected_term_old     = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . $high_slug . '/' . $phase3_post_name . '/' ), PHP_URL_PATH );
$expected_term_new     = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . $renamed_slug . '/' . $phase3_post_name . '/' ), PHP_URL_PATH );
$expected_archive_old  = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . \ContextAuthorityToolkit\Cat_Term_Category::REWRITE_SEGMENT . '/' . $high_slug . '/' ), PHP_URL_PATH );
$expected_archive_new  = (string) wp_parse_url( home_url( '/' . $phase3_base . '/' . \ContextAuthorityToolkit\Cat_Term_Category::REWRITE_SEGMENT . '/' . $renamed_slug . '/' ), PHP_URL_PATH );
cat_assert(
	is_array( $rename_map ) && isset( $rename_map[ $expected_term_old ] ) && $expected_term_new === $rename_map[ $expected_term_old ],
	'Category rename test failed: term permalink embedding the old slug must gain a redirect to the new slug path.'
);
cat_assert(
	is_array( $rename_map ) && isset( $rename_map[ $expected_archive_old ] ) && $expected_archive_new === $rename_map[ $expected_archive_old ],
	'Category rename test failed: taxonomy archive path must gain a redirect to the renamed slug.'
);
$renamed_permalink = get_permalink( $primary_post_id );
cat_assert(
	is_string( $renamed_permalink ) && false !== strpos( $renamed_permalink, '/' . $renamed_slug . '/' ),
	'Category rename test failed: term permalink must use the renamed Category slug.'
);

// Cleanup Phase 3 state.
wp_set_object_terms( $primary_post_id, array(), \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
delete_option( \ContextAuthorityToolkit\Cat_Term_Category::REDIRECTS_OPTION );
if ( null === $phase3_saved_include ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, $phase3_saved_include );
}

wp_delete_term( $primary_cat_a_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
wp_delete_term( $primary_cat_b_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
if ( null === $phase2_saved_enabled ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, $phase2_saved_enabled );
}
$admin_for_phase3->register_post_type();
flush_rewrite_rules( false );

// Test 26: Abilities API CRUD, meta, categories, and permission gates.
$ability_ids = array(
	'context-authority-toolkit/list-terms',
	'context-authority-toolkit/get-term',
	'context-authority-toolkit/create-term',
	'context-authority-toolkit/update-term',
	'context-authority-toolkit/delete-term',
	'context-authority-toolkit/list-term-meta',
	'context-authority-toolkit/update-term-meta',
);
cat_assert(
	function_exists( 'wp_get_ability' ) && function_exists( 'wp_get_ability_category' ),
	'Abilities test failed: WordPress Abilities API functions are unavailable.'
);
$ability_category = wp_get_ability_category( 'context-authority-toolkit' );
cat_assert(
	$ability_category instanceof WP_Ability_Category,
	'Abilities test failed: context-authority-toolkit category must be registered.'
);
foreach ( $ability_ids as $ability_id ) {
	$ability = wp_get_ability( $ability_id );
	cat_assert(
		$ability instanceof WP_Ability,
		'Abilities test failed: ' . $ability_id . ' must be registered.'
	);
	if ( $ability instanceof WP_Ability ) {
		$meta = $ability->get_meta();
		cat_assert(
			! empty( $meta['show_in_rest'] ) && ! empty( $meta['mcp']['public'] ) && isset( $meta['mcp']['type'] ) && 'tool' === $meta['mcp']['type'],
			'Abilities test failed: ' . $ability_id . ' must be REST/MCP discoverable.'
		);
	}
}

$created_via_ability = cat_execute_ability(
	'context-authority-toolkit/create-term',
	array(
		'title'               => 'Ability CRUD Term',
		'status'              => 'publish',
		'content'             => '<p>Ability body.</p>',
		'tooltip'             => "Line one\nLine two",
		'alternatives'        => array( 'ACT' ),
		'same_as'             => array( 'https://schema.org/DefinedTerm', 'ftp://example.com/bad' ),
		'sources'             => array(
			array(
				'url'           => 'https://example.com/ability-source',
				'title'         => 'Ability Source',
				'publisher'     => 'Example',
				'datePublished' => '2026-03-01',
			),
		),
		'disable_autolinking' => false,
		'meta'                => array(
			'ability_custom_note' => 'from-create',
		),
	)
);
cat_assert(
	is_array( $created_via_ability ) && ! empty( $created_via_ability['id'] ),
	'Abilities test failed: create-term must return a term payload.'
);
$ability_term_id = is_array( $created_via_ability ) ? (int) $created_via_ability['id'] : 0;
if ( $ability_term_id > 0 ) {
	$test_post_ids[] = $ability_term_id;
}
cat_assert(
	is_array( $created_via_ability ) && 'Ability CRUD Term' === $created_via_ability['title'] && 'publish' === $created_via_ability['status'],
	'Abilities test failed: create-term must persist title and status.'
);
cat_assert(
	is_array( $created_via_ability ) && "Line one\nLine two" === $created_via_ability['tooltip'],
	'Abilities test failed: create-term must persist tooltip with line breaks.'
);
cat_assert(
	is_array( $created_via_ability ) && in_array( 'https://schema.org/DefinedTerm', $created_via_ability['same_as'], true ),
	'Abilities test failed: create-term must keep public http(s) sameAs URLs.'
);
cat_assert(
	is_array( $created_via_ability ) && ! in_array( 'ftp://example.com/bad', $created_via_ability['same_as'], true ),
	'Abilities test failed: create-term must reject non-public sameAs URLs.'
);
cat_assert(
	is_array( $created_via_ability ) && isset( $created_via_ability['meta']['ability_custom_note'] ) && 'from-create' === $created_via_ability['meta']['ability_custom_note'],
	'Abilities test failed: create-term must persist arbitrary post meta.'
);

$missing_title = cat_execute_ability( 'context-authority-toolkit/create-term', array( 'content' => 'Nope' ) );
cat_assert(
	is_wp_error( $missing_title ),
	'Abilities test failed: create-term without title must error.'
);

$got_by_slug = cat_execute_ability(
	'context-authority-toolkit/get-term',
	array( 'slug' => is_array( $created_via_ability ) ? $created_via_ability['slug'] : '' )
);
cat_assert(
	is_array( $got_by_slug ) && $ability_term_id === (int) $got_by_slug['id'] && ! empty( $got_by_slug['permalink'] ),
	'Abilities test failed: get-term by slug must return the created term with permalink.'
);

$listed = cat_execute_ability(
	'context-authority-toolkit/list-terms',
	array(
		'search'   => 'Ability CRUD Term',
		'status'   => 'publish',
		'per_page' => 20,
	)
);
$listed_ids = array();
if ( is_array( $listed ) && isset( $listed['terms'] ) && is_array( $listed['terms'] ) ) {
	foreach ( $listed['terms'] as $listed_term ) {
		if ( isset( $listed_term['id'] ) ) {
			$listed_ids[] = (int) $listed_term['id'];
		}
	}
}
cat_assert(
	is_array( $listed ) && in_array( $ability_term_id, $listed_ids, true ),
	'Abilities test failed: list-terms search must include the created term.'
);

$updated_via_ability = cat_execute_ability(
	'context-authority-toolkit/update-term',
	array(
		'id'      => $ability_term_id,
		'tooltip' => 'Updated tooltip',
		'meta'    => array(
			'ability_custom_note' => 'from-update',
			'ability_extra_flag'  => '1',
		),
	)
);
cat_assert(
	is_array( $updated_via_ability ) && 'Updated tooltip' === $updated_via_ability['tooltip'],
	'Abilities test failed: update-term must change tooltip.'
);
cat_assert(
	is_array( $updated_via_ability ) && isset( $updated_via_ability['meta']['ability_custom_note'], $updated_via_ability['meta']['ability_extra_flag'] ) && 'from-update' === $updated_via_ability['meta']['ability_custom_note'] && '1' === $updated_via_ability['meta']['ability_extra_flag'],
	'Abilities test failed: update-term must write arbitrary post meta.'
);

$listed_meta = cat_execute_ability(
	'context-authority-toolkit/list-term-meta',
	array( 'id' => $ability_term_id )
);
cat_assert(
	is_array( $listed_meta ) && isset( $listed_meta['meta']['ability_custom_note'] ) && 'from-update' === $listed_meta['meta']['ability_custom_note'],
	'Abilities test failed: list-term-meta must return all post meta including custom keys.'
);
cat_assert(
	is_array( $listed_meta ) && array_key_exists( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_META_KEY, $listed_meta['meta'] ),
	'Abilities test failed: list-term-meta must include CAT tooltip meta.'
);

$meta_write = cat_execute_ability(
	'context-authority-toolkit/update-term-meta',
	array(
		'id'     => $ability_term_id,
		'key'    => 'ability_single_key',
		'value'  => 'single-value',
		'delete' => array( 'ability_extra_flag' ),
	)
);
cat_assert(
	is_array( $meta_write ) && isset( $meta_write['meta']['ability_single_key'] ) && 'single-value' === $meta_write['meta']['ability_single_key'],
	'Abilities test failed: update-term-meta must set a single key/value.'
);
cat_assert(
	is_array( $meta_write ) && ! array_key_exists( 'ability_extra_flag', $meta_write['meta'] ),
	'Abilities test failed: update-term-meta must delete requested keys.'
);

$lock_write = cat_execute_ability(
	'context-authority-toolkit/update-term-meta',
	array(
		'id'   => $ability_term_id,
		'meta' => array(
			'_edit_lock' => 'not-allowed',
		),
	)
);
cat_assert(
	is_wp_error( $lock_write ) && 'cat_protected_meta_key' === $lock_write->get_error_code(),
	'Abilities test failed: update-term-meta must reject editor lock internals.'
);

$ability_saved_categories = get_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, null );
update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, true );
$ability_category_module = new \ContextAuthorityToolkit\Cat_Term_Category();
$ability_category_module->register_taxonomy();
$ability_cat = wp_insert_term( 'Ability Category', \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY, array( 'slug' => 'ability-category' ) );
cat_assert(
	! is_wp_error( $ability_cat ),
	'Abilities test failed: could not create Category fixture for assignment.'
);
$ability_cat_id = is_array( $ability_cat ) ? (int) $ability_cat['term_id'] : 0;

$with_category = cat_execute_ability(
	'context-authority-toolkit/update-term',
	array(
		'id'               => $ability_term_id,
		'categories'       => array( 'ability-category' ),
		'primary_category' => $ability_cat_id,
	)
);
cat_assert(
	is_array( $with_category ) && ! empty( $with_category['categories'] ) && (int) $with_category['categories'][0]['id'] === $ability_cat_id,
	'Abilities test failed: update-term must assign CAT Categories.'
);
cat_assert(
	is_array( $with_category ) && ! empty( $with_category['primary_category']['id'] ) && $ability_cat_id === (int) $with_category['primary_category']['id'],
	'Abilities test failed: update-term must persist primary Category.'
);

$listed_by_category = cat_execute_ability(
	'context-authority-toolkit/list-terms',
	array(
		'category' => 'ability-category',
		'status'   => 'publish',
	)
);
$listed_by_category_ids = array();
if ( is_array( $listed_by_category ) && isset( $listed_by_category['terms'] ) && is_array( $listed_by_category['terms'] ) ) {
	foreach ( $listed_by_category['terms'] as $listed_term ) {
		if ( isset( $listed_term['id'] ) ) {
			$listed_by_category_ids[] = (int) $listed_term['id'];
		}
	}
}
cat_assert(
	in_array( $ability_term_id, $listed_by_category_ids, true ),
	'Abilities test failed: list-terms must filter by Category.'
);

if ( ! is_wp_error( $subscriber_user ) ) {
	wp_set_current_user( (int) $subscriber_user );
	$denied_create = cat_execute_ability(
		'context-authority-toolkit/create-term',
		array(
			'title'  => 'Subscriber should not create',
			'status' => 'publish',
		)
	);
	cat_assert(
		is_wp_error( $denied_create ) && 'ability_invalid_permissions' === $denied_create->get_error_code(),
		'Abilities test failed: subscriber must not create terms.'
	);
	$denied_update = cat_execute_ability(
		'context-authority-toolkit/update-term',
		array(
			'id'      => $ability_term_id,
			'tooltip' => 'nope',
		)
	);
	cat_assert(
		is_wp_error( $denied_update ) && 'ability_invalid_permissions' === $denied_update->get_error_code(),
		'Abilities test failed: subscriber must not update terms.'
	);
	$denied_meta = cat_execute_ability(
		'context-authority-toolkit/list-term-meta',
		array( 'id' => $ability_term_id )
	);
	cat_assert(
		is_wp_error( $denied_meta ) && 'ability_invalid_permissions' === $denied_meta->get_error_code(),
		'Abilities test failed: subscriber must not list term meta.'
	);
	wp_set_current_user( $admin_user_id );
}

$trashed = cat_execute_ability(
	'context-authority-toolkit/delete-term',
	array( 'id' => $ability_term_id )
);
cat_assert(
	is_array( $trashed ) && ! empty( $trashed['success'] ) && ! empty( $trashed['trashed'] ) && 'trash' === get_post_status( $ability_term_id ),
	'Abilities test failed: delete-term must trash by default.'
);
$forced = cat_execute_ability(
	'context-authority-toolkit/delete-term',
	array(
		'id'    => $ability_term_id,
		'force' => true,
	)
);
cat_assert(
	is_array( $forced ) && ! empty( $forced['success'] ) && false === get_post_status( $ability_term_id ),
	'Abilities test failed: delete-term with force must permanently delete.'
);
$test_post_ids = array_values(
	array_filter(
		$test_post_ids,
		static function ( $maybe_id ) use ( $ability_term_id ) {
			return (int) $maybe_id !== (int) $ability_term_id;
		}
	)
);

if ( $ability_cat_id > 0 ) {
	wp_delete_term( $ability_cat_id, \ContextAuthorityToolkit\Cat_Term_Category::TAXONOMY );
}
if ( null === $ability_saved_categories ) {
	delete_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED );
} else {
	update_option( \ContextAuthorityToolkit\Cat_Term_Settings::OPTION_CATEGORIES_ENABLED, $ability_saved_categories );
}

// Test 27: Editor-only Wikidata sameAs lookup (mocked HTTP; never hits live Wikidata).
if ( ! class_exists( '\\ContextAuthorityToolkit\\Cat_Wikidata_Lookup' ) ) {
	cat_assert( false, 'Wikidata lookup test failed: Cat_Wikidata_Lookup class is unavailable.' );
} else {
	$wikidata = new \ContextAuthorityToolkit\Cat_Wikidata_Lookup();
	$wikidata_term_id = cat_create_term( 'Wikidata Lookup Term', '<p>Wikidata lookup host.</p>', array(), 'Wikidata tip' );
	$test_post_ids[]  = $wikidata_term_id;
	$wikidata_marker  = 'before-wikidata-lookup';
	update_post_meta( $wikidata_term_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::SAME_AS_META_KEY, array( 'https://example.com/keep-me' ) );
	update_post_meta( $wikidata_term_id, '_cat_wikidata_test_marker', $wikidata_marker );

	$blocked_host_url = $wikidata->build_api_url( 'WordPress', 'en' );
	cat_assert(
		! is_wp_error( $blocked_host_url ) && false !== strpos( (string) $blocked_host_url, 'https://www.wikidata.org/w/api.php' ),
		'Wikidata lookup test failed: API URL builder must target www.wikidata.org.'
	);
	$parsed_api = wp_parse_url( (string) $blocked_host_url );
	cat_assert(
		is_array( $parsed_api ) && ! empty( $parsed_api['host'] ) && $wikidata->is_allowed_host( $parsed_api['host'] ),
		'Wikidata lookup test failed: built API URL host must be allowlisted.'
	);
	cat_assert(
		! $wikidata->is_allowed_host( 'evil.example' ) && ! $wikidata->is_allowed_host( 'wikipedia.org' ),
		'Wikidata lookup test failed: off-allowlist hosts must be rejected.'
	);
	cat_assert(
		'https://www.wikidata.org/wiki/Q42' === $wikidata->canonical_entity_url( 'Q42' ),
		'Wikidata lookup test failed: canonical entity URL must use validated Q-id.'
	);
	cat_assert(
		'' === $wikidata->canonical_entity_url( 'https://evil.example/Q1' ) && '' === $wikidata->canonical_entity_url( 'Q-abc' ),
		'Wikidata lookup test failed: invalid entity ids must not become URLs.'
	);

	$permission_request = new \WP_REST_Request( 'GET', '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_NAMESPACE . '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_ROUTE );
	$permission_request->set_param( 'post_id', $wikidata_term_id );
	$permission_request->set_param( 'search', 'WordPress' );

	if ( ! is_wp_error( $subscriber_user ) ) {
		wp_set_current_user( (int) $subscriber_user );
		$denied = $wikidata->permission_callback( $permission_request );
		cat_assert(
			is_wp_error( $denied ) && 'cat_wikidata_forbidden' === $denied->get_error_code(),
			'Wikidata lookup test failed: subscriber without edit_post must be denied.'
		);
	}

	wp_set_current_user( $admin_user_id );
	$allowed_lookup = $wikidata->permission_callback( $permission_request );
	cat_assert(
		true === $allowed_lookup,
		'Wikidata lookup test failed: editor with edit_post must be allowed.'
	);

	$empty_search_request = new \WP_REST_Request( 'GET', '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_NAMESPACE . '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_ROUTE );
	$empty_search_request->set_param( 'post_id', $wikidata_term_id );
	$empty_search_request->set_param( 'search', '   ' );
	$empty_response = $wikidata->handle_search( $empty_search_request );
	cat_assert(
		is_wp_error( $empty_response ) && 'cat_wikidata_empty_search' === $empty_response->get_error_code(),
		'Wikidata lookup test failed: empty search must be rejected.'
	);
	cat_assert(
		array( 'https://example.com/keep-me' ) === get_post_meta( $wikidata_term_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::SAME_AS_META_KEY, true ),
		'Wikidata lookup test failed: empty search must not update cat_same_as meta.'
	);
	cat_assert(
		$wikidata_marker === get_post_meta( $wikidata_term_id, '_cat_wikidata_test_marker', true ),
		'Wikidata lookup test failed: lookup must remain read-only for post meta.'
	);

	$http_calls = array();
	$http_filter = static function ( $preempt, $args, $url ) use ( &$http_calls ) {
		unset( $preempt, $args );
		$http_calls[] = $url;
		$parts = wp_parse_url( $url );
		$host  = is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! in_array( $host, \ContextAuthorityToolkit\Cat_Wikidata_Lookup::ALLOWED_HOSTS, true ) ) {
			return new \WP_Error( 'cat_test_off_allowlist', 'Off-allowlist host requested.' );
		}

		$payload = wp_json_encode(
			array(
				'search' => array(
					array(
						'id'          => 'Q42',
						'label'       => 'Douglas Adams',
						'description' => 'English writer',
						'concepturi'  => 'http://www.wikidata.org/entity/Q42',
						'url'         => 'https://evil.example/should-not-leak',
					),
					array(
						'id'          => 'not-a-qid',
						'label'       => 'Bad',
						'description' => 'Should be dropped',
					),
					array(
						'id'          => 'Q83',
						'label'       => 'London',
						'description' => 'capital of England',
					),
				),
			)
		);

		return array(
			'headers'  => array(),
			'body'     => $payload,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	};
	add_filter( 'pre_http_request', $http_filter, 10, 3 );

	$search_request = new \WP_REST_Request( 'GET', '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_NAMESPACE . '/' . \ContextAuthorityToolkit\Cat_Wikidata_Lookup::REST_ROUTE );
	$search_request->set_param( 'post_id', $wikidata_term_id );
	$search_request->set_param( 'search', 'WordPress' );
	$search_response = $wikidata->handle_search( $search_request );
	remove_filter( 'pre_http_request', $http_filter, 10 );

	cat_assert(
		! is_wp_error( $search_response ),
		'Wikidata lookup test failed: mocked search should succeed.'
	);
	$search_data = rest_ensure_response( $search_response )->get_data();
	cat_assert(
		is_array( $search_data ) && ! empty( $search_data['results'] ) && 2 === count( $search_data['results'] ),
		'Wikidata lookup test failed: mocked search should return only valid Q entities.'
	);
	cat_assert(
		'https://www.wikidata.org/wiki/Q42' === $search_data['results'][0]['url']
			&& 'https://www.wikidata.org/wiki/Q83' === $search_data['results'][1]['url'],
		'Wikidata lookup test failed: response URLs must be allowlisted canonical wiki URLs, not remote-supplied URLs.'
	);
	cat_assert(
		1 === count( $http_calls ) && false !== strpos( $http_calls[0], 'https://www.wikidata.org/w/api.php' ),
		'Wikidata lookup test failed: only the allowlisted Wikidata API URL may be requested.'
	);
	cat_assert(
		array( 'https://example.com/keep-me' ) === get_post_meta( $wikidata_term_id, \ContextAuthorityToolkit\Cat_Glossary_Admin::SAME_AS_META_KEY, true ),
		'Wikidata lookup test failed: successful search must not write cat_same_as meta.'
	);
	delete_post_meta( $wikidata_term_id, '_cat_wikidata_test_marker' );
}

// Test 28: Classic term panel chrome + placement options + pattern registration.
$panel_chrome = new \ContextAuthorityToolkit\Cat_Term_Single_Chrome();
$panel_term   = cat_create_term(
	'Panel Term',
	'<p>Panel term body about Panel Term.</p>',
	array( 'PT' ),
	'Panel tip',
	array( 'https://www.wikidata.org/wiki/Q123', 'https://example.com/authority' ),
	array(
		array(
			'url'           => 'https://example.com/source-one',
			'title'         => 'Source One Title',
			'publisher'     => 'Example Press',
			'datePublished' => '2024-06-01',
		),
		array(
			'url' => 'https://example.com/source-untitled',
		),
	)
);
$test_post_ids[] = $panel_term;

$same_as_html = $panel_chrome->render_same_as_html( $panel_term );
cat_assert(
	false !== strpos( $same_as_html, 'cat-term-panel__same-as' )
		&& false !== strpos( $same_as_html, 'www.wikidata.org' )
		&& false !== strpos( $same_as_html, esc_url( 'https://www.wikidata.org/wiki/Q123' ) )
		&& false !== strpos( $same_as_html, esc_url( 'https://example.com/authority' ) ),
	'Term panel test failed: sameAs HTML must include escaped authority URLs and host labels.'
);

$sources_html = $panel_chrome->render_sources_html( $panel_term );
cat_assert(
	false !== strpos( $sources_html, 'cat-term-panel__sources' )
		&& false !== strpos( $sources_html, 'Source One Title' )
		&& false !== strpos( $sources_html, 'Example Press' )
		&& false !== strpos( $sources_html, '2024-06-01' )
		&& false !== strpos( $sources_html, esc_url( 'https://example.com/source-untitled' ) ),
	'Term panel test failed: sources HTML must escape title/publisher/date and fall back to URL label.'
);

$empty_panel_term = cat_create_term( 'Empty Panel Term', '<p>Empty panel body.</p>', array(), '' );
$test_post_ids[]  = $empty_panel_term;
cat_assert(
	'' === $panel_chrome->render_same_as_html( $empty_panel_term )
		&& '' === $panel_chrome->render_sources_html( $empty_panel_term ),
	'Term panel test failed: empty sameAs/sources meta must omit those sections.'
);
cat_assert(
	'' === $panel_chrome->render_panel_html( $empty_panel_term ),
	'Term panel test failed: fully empty panel must omit the aside.'
);

$panel_post = get_post( $panel_term );
setup_postdata( $panel_post );
$GLOBALS['post'] = $panel_post;

global $wp_query;
$wp_query->is_singular       = true;
$wp_query->is_single         = true;
$wp_query->queried_object    = $panel_post;
$wp_query->queried_object_id = $panel_term;

$cite_wrapper_warned = false;
set_error_handler(
	function ( $errno, $errstr ) use ( &$cite_wrapper_warned ) {
		unset( $errno );
		if ( false !== strpos( (string) $errstr, 'array offset on null' ) ) {
			$cite_wrapper_warned = true;
		}
		return false;
	}
);
$full_panel = $panel_chrome->render_panel_html( $panel_term );
restore_error_handler();
cat_assert(
	false !== strpos( $full_panel, 'cat-term-panel' )
		&& false !== strpos( $full_panel, 'About this term' )
		&& false !== strpos( $full_panel, 'Also known as' )
		&& false !== strpos( $full_panel, 'itemprop="alternateName">PT</span>' )
		&& false !== strpos( $full_panel, 'cat-cite-this' )
		&& false !== strpos( $full_panel, 'cat-cite-this__button' ),
	'Term panel test failed: panel composer must include aside, aliases, and cite-this markup from the block renderer.'
);
cat_assert(
	! $cite_wrapper_warned,
	'Term panel test failed: cite-this panel render must not trigger WP_Block_Supports null offset warnings.'
);

update_option( \ContextAuthorityToolkit\Cat_Term_Panel::OPTION_SHOW_SAME_AS, false );
$toggled_panel = \ContextAuthorityToolkit\Cat_Term_Panel::render_panel_for_term( $panel_term );
cat_assert(
	false === strpos( $toggled_panel, 'cat-term-panel__same-as' )
		&& false !== strpos( $toggled_panel, 'cat-term-panel__sources' ),
	'Term panel test failed: section toggle off must omit only that section.'
);
update_option( \ContextAuthorityToolkit\Cat_Term_Panel::OPTION_SHOW_SAME_AS, true );

update_option( \ContextAuthorityToolkit\Cat_Term_Panel::OPTION_ENABLED, false );
cat_assert(
	false === \ContextAuthorityToolkit\Cat_Term_Panel::is_enabled(),
	'Term panel test failed: disabled option must flip is_enabled() to false.'
);

// Force inactive sidebars so content fallback path runs (bootstrapped Cat_Term_Panel).
$cat_panel_sidebars_filter = static function () {
	return array( 'wp_inactive_widgets' => array() );
};
add_filter( 'sidebars_widgets', $cat_panel_sidebars_filter, 999 );
\ContextAuthorityToolkit\Cat_Term_Panel::reset_panel_printed_flag();
$disabled_content = apply_filters( 'the_content', '<p>Panel term body about Panel Term.</p>' );
cat_assert(
	false === strpos( $disabled_content, 'class="cat-term-panel"' ),
	'Term panel test failed: disabled option must omit panel from content injection.'
);
update_option( \ContextAuthorityToolkit\Cat_Term_Panel::OPTION_ENABLED, true );

\ContextAuthorityToolkit\Cat_Term_Panel::reset_panel_printed_flag();
$injected = apply_filters( 'the_content', '<p>Panel term body about Panel Term.</p>' );
cat_assert(
	false !== strpos( $injected, 'class="cat-term-panel"' ),
	'Term panel test failed: content fallback must inject the panel when no active sidebar.'
);

\ContextAuthorityToolkit\Cat_Term_Panel::reset_panel_printed_flag();
$reinjected = apply_filters( 'the_content', $injected );
cat_assert(
	$reinjected === $injected,
	'Term panel test failed: existing cat-term-panel class marker must block double-inject.'
);
remove_filter( 'sidebars_widgets', $cat_panel_sidebars_filter, 999 );

do_action( 'init' );
$patterns = \WP_Block_Patterns_Registry::get_instance();
cat_assert(
	$patterns->is_registered( \ContextAuthorityToolkit\Cat_Term_Panel::PATTERN_NAME ),
	'Term panel test failed: block pattern cat-toolkit/term-panel must be registered.'
);

$panel_block_type = WP_Block_Type_Registry::get_instance()->get_registered(
	\ContextAuthorityToolkit\Cat_Term_Panel::BLOCK_NAME
);
$has_editor_script = ! empty( $panel_block_type->editor_script )
	|| ( isset( $panel_block_type->editor_script_handles ) && ! empty( $panel_block_type->editor_script_handles ) );
cat_assert(
	$panel_block_type instanceof WP_Block_Type && $has_editor_script,
	'Term panel test failed: cat-toolkit/term-panel must register an editor script for Site Editor.'
);

$default_types = get_default_block_template_types();
cat_assert(
	isset( $default_types[ \ContextAuthorityToolkit\Cat_Term_Panel::BLOCK_TEMPLATE_SLUG ] ),
	'Term panel test failed: single-term must be registered as a default template type.'
);

$single_term_templates = get_block_templates(
	array(
		'slug__in' => array( \ContextAuthorityToolkit\Cat_Term_Panel::BLOCK_TEMPLATE_SLUG ),
	)
);
$single_term_slugs = array();
foreach ( $single_term_templates as $single_term_template ) {
	if ( $single_term_template instanceof WP_Block_Template ) {
		$single_term_slugs[] = $single_term_template->slug;
	}
}
cat_assert(
	in_array( \ContextAuthorityToolkit\Cat_Term_Panel::BLOCK_TEMPLATE_SLUG, $single_term_slugs, true ),
	'Term panel test failed: get_block_templates must return the plugin single-term template.'
);

if ( function_exists( 'register_block_template' ) ) {
	$registered_term_template = WP_Block_Templates_Registry::get_instance()->get_by_slug(
		\ContextAuthorityToolkit\Cat_Term_Panel::BLOCK_TEMPLATE_SLUG
	);
	$registered_term_template_content = (string) $registered_term_template->content;
	cat_assert(
		$registered_term_template instanceof WP_Block_Template
			&& false !== strpos( $registered_term_template_content, 'cat-toolkit/term-panel' )
			&& false !== strpos( $registered_term_template_content, 'wp:columns' )
			&& false !== strpos( $registered_term_template_content, 'wp:post-content {"layout":{"type":"constrained"}}' )
			&& false === strpos( $registered_term_template_content, '"justifyContent":"left"' ),
		'Term panel test failed: registered single-term template must include columns and the term-panel block, with centered constrained post-content (no left justify).'
	);
}

$columns_pattern = $patterns->get_registered( \ContextAuthorityToolkit\Cat_Term_Panel::PATTERN_SINGLE_TERM_COLUMNS );
$stacked_pattern = $patterns->get_registered( \ContextAuthorityToolkit\Cat_Term_Panel::PATTERN_SINGLE_TERM_STACKED );
$columns_types   = ( isset( $columns_pattern['templateTypes'] ) && is_array( $columns_pattern['templateTypes'] ) )
	? $columns_pattern['templateTypes']
	: array();
$stacked_types = ( isset( $stacked_pattern['templateTypes'] ) && is_array( $stacked_pattern['templateTypes'] ) )
	? $stacked_pattern['templateTypes']
	: array();
cat_assert(
	in_array( 'single-term', $columns_types, true )
		&& in_array( 'single-term', $stacked_types, true )
		&& ! in_array( 'single', $columns_types, true )
		&& ! in_array( 'posts', $columns_types, true )
		&& ! in_array( 'single', $stacked_types, true )
		&& ! in_array( 'posts', $stacked_types, true ),
	'Term panel test failed: Design starters must use templateTypes single-term only (not single/posts).'
);

$term_section_block = WP_Block_Type_Registry::get_instance()->get_registered(
	\ContextAuthorityToolkit\Cat_Term_Section_Block::BLOCK_NAME
);
$term_section_has_editor_script = $term_section_block instanceof WP_Block_Type
	&& (
		! empty( $term_section_block->editor_script )
		|| ( isset( $term_section_block->editor_script_handles ) && ! empty( $term_section_block->editor_script_handles ) )
	);
cat_assert(
	$term_section_has_editor_script,
	'Term section test failed: cat-toolkit/term-section must register an editor script.'
);

$term_page_pattern = $patterns->get_registered( \ContextAuthorityToolkit\Cat_Term_Section_Block::PATTERN_NAME );
$term_page_types   = ( isset( $term_page_pattern['templateTypes'] ) && is_array( $term_page_pattern['templateTypes'] ) )
	? $term_page_pattern['templateTypes']
	: array();
$term_page_content = isset( $term_page_pattern['content'] ) ? (string) $term_page_pattern['content'] : '';
cat_assert(
	$patterns->is_registered( \ContextAuthorityToolkit\Cat_Term_Section_Block::PATTERN_NAME )
		&& false !== strpos( $term_page_content, '"section":"what"' )
		&& false !== strpos( $term_page_content, '"section":"how"' )
		&& false !== strpos( $term_page_content, '"section":"examples"' )
		&& false !== strpos( $term_page_content, '"section":"mistakes"' )
		&& false !== strpos( $term_page_content, '"section":"takeaways"' )
		&& false === strpos( $term_page_content, 'How it works' )
		&& ! in_array( 'single', $term_page_types, true )
		&& ! in_array( 'posts', $term_page_types, true ),
	'Term section test failed: cat-toolkit/term-page must insert five slot keys without default English headings or single/posts templateTypes.'
);

$term_post_type = get_post_type_object( \ContextAuthorityToolkit\Cat_Glossary_Admin::POST_TYPE );
$term_template  = ( $term_post_type && is_array( $term_post_type->template ) ) ? $term_post_type->template : array();
$term_template_slots = array();
foreach ( $term_template as $template_block ) {
	if ( isset( $template_block[0], $template_block[1]['section'] ) && \ContextAuthorityToolkit\Cat_Term_Section_Block::BLOCK_NAME === $template_block[0] ) {
		$term_template_slots[] = $template_block[1]['section'];
	}
}
cat_assert(
	array( 'what', 'how', 'examples', 'mistakes', 'takeaways' ) === $term_template_slots
		&& empty( $term_post_type->template_lock ),
	'Term section test failed: CPT template must seed five unlocked term-section slots on new terms.'
);

$section_renderer = new \ContextAuthorityToolkit\Cat_Term_Section_Block( false );
$default_section  = $section_renderer->render_block(
	array(
		'section' => 'how',
	),
	'<p>Because extractable chunks help answer engines.</p>'
);
cat_assert(
	false !== strpos( $default_section, 'data-cat-section="how"' )
		&& false !== strpos( $default_section, 'class="cat-term-section' )
		&& false !== strpos( $default_section, '>' . __( 'How it works', 'context-authority-toolkit' ) . '</h2>' )
		&& false !== strpos( $default_section, 'Because extractable chunks help answer engines.' ),
	'Term section test failed: empty custom heading must render the translated default H2 and slot attribute.'
);

$custom_section = $section_renderer->render_block(
	array(
		'section'       => 'mistakes',
		'customHeading' => 'What people get wrong',
	),
	'<ul><li>What is it?</li></ul>'
);
cat_assert(
	false !== strpos( $custom_section, 'data-cat-section="mistakes"' )
		&& false !== strpos( $custom_section, '>What people get wrong</h2>' )
		&& false === strpos( $custom_section, '>' . __( 'Common mistakes', 'context-authority-toolkit' ) . '</h2>' )
		&& false !== strpos( $custom_section, '<ul><li>What is it?</li></ul>' ),
	'Term section test failed: customHeading must replace the default H2 without changing the slot.'
);

$fallback_section = $section_renderer->render_block(
	array(
		'section' => 'not-a-slot',
	),
	''
);
cat_assert(
	false !== strpos( $fallback_section, 'data-cat-section="what"' )
		&& false !== strpos( $fallback_section, '>' . __( 'What it is', 'context-authority-toolkit' ) . '</h2>' ),
	'Term section test failed: unknown slots must fall back to what.'
);

$section_schema_term = cat_create_term(
	'Section Schema Term',
	'<!-- wp:cat-toolkit/term-section {"section":"how"} --><!-- wp:paragraph --><p>What is a section?</p><!-- /wp:paragraph --><!-- /wp:cat-toolkit/term-section -->',
	array(),
	'A section is an extractable chunk on a glossary term page.'
);
$test_post_ids[]     = $section_schema_term;
$section_schema      = $seo_peacekeeper->get_canonical_term_schema( $section_schema_term );
$section_graph       = $seo_peacekeeper->build_standalone_term_graph( $section_schema_term );
$section_graph_json  = wp_json_encode( $section_graph );
cat_assert(
	'DefinedTerm' === $section_schema['@type']
		&& false === strpos( wp_json_encode( $section_schema ), 'FAQPage' )
		&& is_string( $section_graph_json )
		&& false === strpos( $section_graph_json, 'FAQPage' )
		&& false === strpos( $section_graph_json, 'HowTo' )
		&& false === strpos( $section_graph_json, 'Speakable' )
		&& false === strpos( $section_graph_json, 'LearningResource' ),
	'Term section test failed: term-section content must not add FAQPage or other parked schema types.'
);

wp_reset_postdata();
foreach ( $test_post_ids as $test_post_id ) {
	wp_delete_post( (int) $test_post_id, true );
}

if ( ! is_wp_error( $subscriber_user ) ) {
	wp_delete_user( (int) $subscriber_user );
}
if ( $restore_tooltip_migration ) {
	update_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY, $restore_tooltip_migration, false );
} else {
	delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_MIGRATION_OPTION_KEY );
}
if ( $restore_tooltip_scrub ) {
	update_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY, $restore_tooltip_scrub, false );
} else {
	delete_option( \ContextAuthorityToolkit\Cat_Glossary_Admin::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY );
}

if ( ! empty( $failures ) ) {
	echo "Behavior/Security tests FAILED:\n";
	foreach ( $failures as $failure ) {
		echo ' - ' . esc_html( $failure ) . "\n";
	}
	exit( 1 );
}

echo "Behavior/Security tests PASSED.\n";
exit( 0 );
