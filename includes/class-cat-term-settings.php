<?php
/**
 * Term structure settings (slug, categories toggle, permalink mode).
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns Term → Settings options and the single read API for consumers.
 */
class Cat_Term_Settings {
	/**
	 * Settings group.
	 */
	const SETTINGS_GROUP = 'cat_term_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'cat-term-settings';

	/**
	 * Term rewrite base slug option.
	 */
	const OPTION_TERM_SLUG = 'cat_term_slug';

	/**
	 * Categories feature toggle option.
	 */
	const OPTION_CATEGORIES_ENABLED = 'cat_categories_enabled';

	/**
	 * Include category segment in term permalinks option.
	 */
	const OPTION_PERMALINK_INCLUDE_CATEGORY = 'cat_term_permalink_include_category';

	/**
	 * Default term rewrite slug.
	 */
	const DEFAULT_TERM_SLUG = 'term';

	/**
	 * Option key for deferred rewrite flush.
	 */
	const OPTION_REWRITE_FLUSH_NEEDED = 'cat_rewrite_flush_needed';

	/**
	 * Wire settings hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'update_option_' . self::OPTION_TERM_SLUG, array( $this, 'request_rewrite_flush_on_slug_change' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_PERMALINK_INCLUDE_CATEGORY, array( $this, 'request_rewrite_flush_on_permalink_change' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_CATEGORIES_ENABLED, array( $this, 'request_rewrite_flush_on_categories_change' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION_TERM_SLUG, array( $this, 'request_rewrite_flush_on_slug_add' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION_PERMALINK_INCLUDE_CATEGORY, array( $this, 'request_rewrite_flush_on_permalink_add' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION_CATEGORIES_ENABLED, array( $this, 'request_rewrite_flush_on_categories_add' ), 10, 2 );
	}

	/**
	 * Enqueue settings-screen-only assets.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_settings_assets( $hook_suffix ) {
		$expected_hook = Cat_Glossary_Admin::POST_TYPE . '_page_' . self::PAGE_SLUG;
		if ( $expected_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'cat-term-settings',
			CAT_TOOLKIT_URL . 'assets/js/term-settings.js',
			array(),
			CAT_TOOLKIT_VERSION,
			true
		);
	}

	/**
	 * Register Term → Settings submenu under the glossary CPT menu.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . Cat_Glossary_Admin::POST_TYPE,
			__( 'Term Settings', 'context-authority-toolkit' ),
			__( 'Settings', 'context-authority-toolkit' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register Settings API options and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_TERM_SLUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_term_slug' ),
				'default'           => self::DEFAULT_TERM_SLUG,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_CATEGORIES_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_boolean_option' ),
				'default'           => false,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_PERMALINK_INCLUDE_CATEGORY,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_boolean_option' ),
				'default'           => false,
			)
		);

		add_settings_section(
			'cat_term_structure_section',
			__( 'Glossary structure', 'context-authority-toolkit' ),
			array( $this, 'render_settings_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_TERM_SLUG,
			__( 'Slug name', 'context-authority-toolkit' ),
			array( $this, 'render_term_slug_field' ),
			self::PAGE_SLUG,
			'cat_term_structure_section'
		);

		add_settings_field(
			self::OPTION_CATEGORIES_ENABLED,
			__( 'Categories', 'context-authority-toolkit' ),
			array( $this, 'render_categories_enabled_field' ),
			self::PAGE_SLUG,
			'cat_term_structure_section'
		);

		add_settings_field(
			self::OPTION_PERMALINK_INCLUDE_CATEGORY,
			__( 'Permalink structure', 'context-authority-toolkit' ),
			array( $this, 'render_permalink_include_category_field' ),
			self::PAGE_SLUG,
			'cat_term_structure_section'
		);
	}

	/**
	 * Output settings intro text.
	 *
	 * @return void
	 */
	public function render_settings_intro() {
		echo '<p>' . esc_html__( 'Configure glossary URLs and Category support. Schema delivery settings remain under Settings → CAT Schema.', 'context-authority-toolkit' ) . '</p>';
	}

	/**
	 * Render term slug field.
	 *
	 * @return void
	 */
	public function render_term_slug_field() {
		$slug    = self::get_term_slug();
		$preview = home_url( '/' . $slug . '/example-term/' );

		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s" pattern="[a-z0-9\\-]+" />',
			esc_attr( self::OPTION_TERM_SLUG ),
			esc_attr( $slug )
		);
		echo '<p class="description">' . esc_html__( 'Lowercase letters, numbers, and hyphens only. Changing this updates term permalinks after save.', 'context-authority-toolkit' ) . '</p>';
		printf(
			'<p class="description"><strong>%1$s</strong> <code id="cat-term-slug-preview" data-home-url="%2$s">%3$s</code></p>',
			esc_html__( 'Preview:', 'context-authority-toolkit' ),
			esc_url( home_url( '/' ) ),
			esc_html( $preview )
		);
	}

	/**
	 * Render categories enable checkbox.
	 *
	 * @return void
	 */
	public function render_categories_enabled_field() {
		printf(
			'<input type="hidden" name="%1$s" value="0" />',
			esc_attr( self::OPTION_CATEGORIES_ENABLED )
		);
		printf(
			'<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_CATEGORIES_ENABLED ),
			checked( self::are_categories_enabled(), true, false ),
			esc_html__( 'Enable Categories for glossary terms', 'context-authority-toolkit' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, Categories appear under Term and can be assigned on glossary terms.', 'context-authority-toolkit' ) . '</p>';
	}

	/**
	 * Render permalink include-category checkbox.
	 *
	 * @return void
	 */
	public function render_permalink_include_category_field() {
		$categories_enabled = self::are_categories_enabled();
		$include            = (bool) self::sanitize_boolean_option( get_option( self::OPTION_PERMALINK_INCLUDE_CATEGORY, false ) );

		printf(
			'<input type="hidden" name="%1$s" value="0" />',
			esc_attr( self::OPTION_PERMALINK_INCLUDE_CATEGORY )
		);
		printf(
			'<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s %3$s /> %4$s</label>',
			esc_attr( self::OPTION_PERMALINK_INCLUDE_CATEGORY ),
			checked( $include, true, false ),
			disabled( $categories_enabled, false, false ),
			esc_html__( 'Include Category in term permalinks', 'context-authority-toolkit' )
		);

		if ( $categories_enabled ) {
			$slug = self::get_term_slug();
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: example permalink path */
					__( 'Example when enabled: %s', 'context-authority-toolkit' ),
					home_url( '/' . $slug . '/example-category/example-term/' )
				)
			) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Enable Categories first to configure this option.', 'context-authority-toolkit' ) . '</p>';
		}
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Term Settings', 'context-authority-toolkit' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the configured term rewrite slug.
	 *
	 * @return string
	 */
	public static function get_term_slug() {
		$slug = get_option( self::OPTION_TERM_SLUG, self::DEFAULT_TERM_SLUG );
		return self::sanitize_term_slug( $slug );
	}

	/**
	 * Whether CAT Categories are enabled.
	 *
	 * @return bool
	 */
	public static function are_categories_enabled() {
		$enabled = get_option( self::OPTION_CATEGORIES_ENABLED, false );
		return (bool) self::sanitize_boolean_option( $enabled );
	}

	/**
	 * Whether category slugs should appear in term permalinks.
	 *
	 * Returns false when categories are disabled, regardless of stored value.
	 *
	 * @return bool
	 */
	public static function should_include_category_in_permalink() {
		if ( ! self::are_categories_enabled() ) {
			return false;
		}

		$include = get_option( self::OPTION_PERMALINK_INCLUDE_CATEGORY, false );
		return (bool) self::sanitize_boolean_option( $include );
	}

	/**
	 * Sanitize term rewrite slug.
	 *
	 * @param mixed $slug Submitted slug.
	 * @return string
	 */
	public static function sanitize_term_slug( $slug ) {
		if ( ! is_string( $slug ) ) {
			return self::DEFAULT_TERM_SLUG;
		}

		$slug = strtolower( trim( $slug ) );
		$slug = sanitize_title( $slug );
		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );

		if ( ! is_string( $slug ) || '' === $slug ) {
			return self::DEFAULT_TERM_SLUG;
		}

		return $slug;
	}

	/**
	 * Sanitize a boolean option value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_boolean_option( $value ) {
		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Request a deferred rewrite flush (runs on next init after CPT/taxonomy register).
	 *
	 * @return void
	 */
	public static function request_rewrite_flush() {
		update_option( self::OPTION_REWRITE_FLUSH_NEEDED, 1, false );
	}

	/**
	 * Flush rewrite rules once when flagged.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites() {
		if ( ! get_option( self::OPTION_REWRITE_FLUSH_NEEDED ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::OPTION_REWRITE_FLUSH_NEEDED );
	}

	/**
	 * Queue flush when term slug is first added with a non-default value.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function request_rewrite_flush_on_slug_add( $option, $value ) {
		unset( $option );
		$new = self::sanitize_term_slug( $value );
		if ( self::DEFAULT_TERM_SLUG !== $new ) {
			self::request_rewrite_flush();
		}
	}

	/**
	 * Queue flush when permalink-include option is first added as true.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function request_rewrite_flush_on_permalink_add( $option, $value ) {
		unset( $option );
		if ( self::sanitize_boolean_option( $value ) ) {
			self::request_rewrite_flush();
		}
	}

	/**
	 * Queue flush when categories are first added as enabled.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function request_rewrite_flush_on_categories_add( $option, $value ) {
		unset( $option );
		if ( self::sanitize_boolean_option( $value ) ) {
			self::request_rewrite_flush();
		}
	}

	/**
	 * Queue flush only when the term slug actually changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function request_rewrite_flush_on_slug_change( $old_value, $value ) {
		$old = self::sanitize_term_slug( $old_value );
		$new = self::sanitize_term_slug( $value );

		if ( $old !== $new ) {
			self::request_rewrite_flush();
		}
	}

	/**
	 * Queue flush only when permalink-include actually changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function request_rewrite_flush_on_permalink_change( $old_value, $value ) {
		$old = self::sanitize_boolean_option( $old_value );
		$new = self::sanitize_boolean_option( $value );

		if ( $old !== $new ) {
			self::request_rewrite_flush();
		}
	}

	/**
	 * Queue flush when categories enabled actually changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function request_rewrite_flush_on_categories_change( $old_value, $value ) {
		$old = self::sanitize_boolean_option( $old_value );
		$new = self::sanitize_boolean_option( $value );

		if ( $old !== $new ) {
			self::request_rewrite_flush();
		}
	}
}
