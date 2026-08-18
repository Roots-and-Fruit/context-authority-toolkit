<?php
/**
 * Plugin Name:       Context & Authority Toolkit
 * Plugin URI:        https://github.com/Roots-and-Fruit/context-authority-toolkit
 * Description:       Adds glossary-powered context tooltips for terms in post and comment content.
 * Version:           0.11.0
 * Author:            Crucible CRM
 * Author URI:        https://cruciblecrm.com/
 * Forked from:       WordPress.org Glossary (Automattic and contributors)
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       context-authority-toolkit
 * GitHub Plugin URI: https://github.com/Roots-and-Fruit/context-authority-toolkit
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This plugin is a clean fork of the "WordPress.org Glossary" plugin
 * and remains licensed under GPL-2.0-or-later. See ATTRIBUTION.txt.
 */

define( 'CAT_TOOLKIT_VERSION', '0.11.0' );
define( 'CAT_TOOLKIT_FILE', __FILE__ );
define( 'CAT_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAT_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

require_once CAT_TOOLKIT_DIR . 'includes/class-cat-glossary.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-glossary-handler.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-glossary-admin.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-glossary-hovercards.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-seo-peacekeeper.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-term-single-chrome.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-cite-this-block.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-term-panel.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-term-panel-widget.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-term-settings.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-term-category.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-abilities.php';
require_once CAT_TOOLKIT_DIR . 'includes/class-cat-wikidata-lookup.php';

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function cat_toolkit_bootstrap() {
	$glossary = new Cat_Glossary();

	$admin = new Cat_Glossary_Admin();
	new Cat_Glossary_Hovercards();
	new Cat_Glossary_Handler( $glossary );
	new Cat_SEO_Peacekeeper();
	new Cat_Cite_This_Block();
	new Cat_Term_Panel();
	new Cat_Term_Settings();
	new Cat_Term_Category();
	new Cat_Abilities( $admin );
	new Cat_Wikidata_Lookup();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cat_toolkit_bootstrap' );

/**
 * Handle plugin activation tasks.
 *
 * @return void
 */
function cat_toolkit_activate() {
	$admin = new Cat_Glossary_Admin();
	$admin->register_post_type();
	$admin->register_post_meta();
	$categories = new Cat_Term_Category();
	$categories->register_taxonomy();
	flush_rewrite_rules();
}
register_activation_hook( CAT_TOOLKIT_FILE, __NAMESPACE__ . '\\cat_toolkit_activate' );

/**
 * Handle plugin deactivation tasks.
 *
 * @return void
 */
function cat_toolkit_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( CAT_TOOLKIT_FILE, __NAMESPACE__ . '\\cat_toolkit_deactivate' );
