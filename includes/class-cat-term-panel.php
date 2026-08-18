<?php
/**
 * Classic term panel placement: widget, Customizer, sidebar/content inject, FSE single-term template.
 *
 * This class only decides where/whether to print. All HTML fragments come from
 * Cat_Term_Single_Chrome (+ Cat_Cite_This_Block for cite-this).
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Places the CAT term panel on classic themes and registers the FSE template.
 */
class Cat_Term_Panel {
	/**
	 * Master enable option.
	 */
	const OPTION_ENABLED = 'cat_term_panel_enabled';

	/**
	 * Show aliases section option.
	 */
	const OPTION_SHOW_ALIASES = 'cat_term_panel_show_aliases';

	/**
	 * Show related terms section option.
	 */
	const OPTION_SHOW_RELATED = 'cat_term_panel_show_related';

	/**
	 * Show sameAs / authority links section option.
	 */
	const OPTION_SHOW_SAME_AS = 'cat_term_panel_show_same_as';

	/**
	 * Show sources section option.
	 */
	const OPTION_SHOW_SOURCES = 'cat_term_panel_show_sources';

	/**
	 * Show cite-this section option.
	 */
	const OPTION_SHOW_CITE_THIS = 'cat_term_panel_show_cite_this';

	/**
	 * Block pattern name.
	 */
	const PATTERN_NAME = 'cat-toolkit/term-panel';

	/**
	 * Dynamic block name used by the FSE pattern.
	 */
	const BLOCK_NAME = 'cat-toolkit/term-panel';

	/**
	 * Plugin-registered block template name (`plugin_slug//template_slug`).
	 */
	const BLOCK_TEMPLATE_NAME = 'context-authority-toolkit//single-term';

	/**
	 * Block template slug used by the FSE hierarchy (`single-{post_type}`).
	 */
	const BLOCK_TEMPLATE_SLUG = 'single-term';

	/**
	 * Design-panel two-column starter pattern (templateTypes: single-term only).
	 */
	const PATTERN_SINGLE_TERM_COLUMNS = 'cat-toolkit/single-term';

	/**
	 * Design-panel stacked starter pattern (templateTypes: single-term only).
	 */
	const PATTERN_SINGLE_TERM_STACKED = 'cat-toolkit/single-term-stacked';

	/**
	 * Widget id base.
	 */
	const WIDGET_ID_BASE = 'cat_term_panel';

	/**
	 * Preferred classic sidebar id hint.
	 */
	const PRIMARY_SIDEBAR_HINT = 'sidebar-1';

	/**
	 * Customizer section id.
	 */
	const CUSTOMIZER_SECTION = 'cat_term_panel';

	/**
	 * Whether the panel HTML was already printed this request.
	 *
	 * @var bool
	 */
	private static $panel_printed = false;

	/**
	 * Wire placement hooks.
	 */
	public function __construct() {
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'customize_register', array( $this, 'register_customizer' ) );
		add_action( 'init', array( $this, 'register_block_and_pattern' ) );
		add_filter( 'default_template_types', array( $this, 'register_default_template_type' ) );
		add_filter( 'get_block_templates', array( $this, 'filter_block_templates' ), 10, 3 );
		add_filter( 'get_block_file_template', array( $this, 'filter_block_file_template' ), 10, 3 );
		add_action( 'dynamic_sidebar_before', array( $this, 'maybe_print_in_sidebar' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'maybe_append_to_content' ), 40 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_cite_assets' ) );
	}

	/**
	 * Register the classic sidebar widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( __NAMESPACE__ . '\\Cat_Term_Panel_Widget' );
	}

	/**
	 * Register Customizer controls backed by plugin options (not theme_mods).
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public function register_customizer( $wp_customize ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_customize->add_section(
			self::CUSTOMIZER_SECTION,
			array(
				'title'    => __( 'CAT Term Panel', 'context-authority-toolkit' ),
				'priority' => 160,
			)
		);

		$settings = array(
			self::OPTION_ENABLED        => array(
				'label'   => __( 'Enable CAT term panel', 'context-authority-toolkit' ),
				'default' => true,
			),
			self::OPTION_SHOW_ALIASES   => array(
				'label'   => __( 'Show aliases', 'context-authority-toolkit' ),
				'default' => true,
			),
			self::OPTION_SHOW_RELATED   => array(
				'label'   => __( 'Show related terms', 'context-authority-toolkit' ),
				'default' => true,
			),
			self::OPTION_SHOW_SAME_AS   => array(
				'label'   => __( 'Show authority links', 'context-authority-toolkit' ),
				'default' => true,
			),
			self::OPTION_SHOW_SOURCES   => array(
				'label'   => __( 'Show sources', 'context-authority-toolkit' ),
				'default' => true,
			),
			self::OPTION_SHOW_CITE_THIS => array(
				'label'   => __( 'Show cite this', 'context-authority-toolkit' ),
				'default' => true,
			),
		);

		foreach ( $settings as $option_key => $config ) {
			$wp_customize->add_setting(
				$option_key,
				array(
					'type'              => 'option',
					'capability'        => 'manage_options',
					'default'           => $config['default'],
					'sanitize_callback' => array( __CLASS__, 'sanitize_boolean_option' ),
					'transport'         => 'refresh',
				)
			);

			$wp_customize->add_control(
				$option_key,
				array(
					'label'   => $config['label'],
					'section' => self::CUSTOMIZER_SECTION,
					'type'    => 'checkbox',
				)
			);
		}
	}

	/**
	 * Register the FSE dynamic block, patterns, and single-term template.
	 *
	 * @return void
	 */
	public function register_block_and_pattern() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'cat-term-panel-block-editor',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-term-panel/index.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-element', 'wp-i18n' ),
			CAT_TOOLKIT_VERSION,
			true
		);

		wp_register_style(
			'cat-term-panel-block-editor',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-term-panel/editor.css',
			array(),
			CAT_TOOLKIT_VERSION
		);

		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			register_block_type(
				CAT_TOOLKIT_DIR . 'assets/blocks/cat-term-panel',
				array(
					'render_callback' => array( $this, 'render_panel_block' ),
				)
			);
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category(
				'cat-toolkit',
				array(
					'label' => __( 'Context & Authority Toolkit', 'context-authority-toolkit' ),
				)
			);
		}

		if ( function_exists( 'register_block_pattern' ) ) {
			$patterns = \WP_Block_Patterns_Registry::get_instance();

			if ( ! $patterns->is_registered( self::PATTERN_NAME ) ) {
				register_block_pattern(
					self::PATTERN_NAME,
					array(
						'title'       => __( 'CAT Term Panel', 'context-authority-toolkit' ),
						'description' => __( 'Inserts the shared CAT term panel (aliases, related, authority links, sources, cite this).', 'context-authority-toolkit' ),
						'categories'  => array( 'cat-toolkit' ),
						'content'     => '<!-- wp:cat-toolkit/term-panel /-->',
					)
				);
			}

			$columns_markup = $this->get_plugin_template_markup( 'single-term.html' );
			if ( '' !== $columns_markup && ! $patterns->is_registered( self::PATTERN_SINGLE_TERM_COLUMNS ) ) {
				register_block_pattern(
					self::PATTERN_SINGLE_TERM_COLUMNS,
					array(
						'title'         => __( 'Term with panel (two columns)', 'context-authority-toolkit' ),
						'description'   => __( 'Glossary term layout with the CAT term panel in a complementary column.', 'context-authority-toolkit' ),
						'categories'    => array( 'cat-toolkit' ),
						'templateTypes' => array( self::BLOCK_TEMPLATE_SLUG ),
						'inserter'      => false,
						'content'       => $columns_markup,
					)
				);
			}

			$stacked_markup = $this->get_plugin_template_markup( 'single-term-stacked.html' );
			if ( '' !== $stacked_markup && ! $patterns->is_registered( self::PATTERN_SINGLE_TERM_STACKED ) ) {
				register_block_pattern(
					self::PATTERN_SINGLE_TERM_STACKED,
					array(
						'title'         => __( 'Term with panel (stacked)', 'context-authority-toolkit' ),
						'description'   => __( 'Glossary term layout with the CAT term panel stacked below the definition.', 'context-authority-toolkit' ),
						'categories'    => array( 'cat-toolkit' ),
						'templateTypes' => array( self::BLOCK_TEMPLATE_SLUG ),
						'inserter'      => false,
						'content'       => $stacked_markup,
					)
				);
			}
		}

		$this->register_single_term_block_template();
	}

	/**
	 * Register the plugin `single-term` block template (WP 6.7+).
	 *
	 * Theme `single-term` files still win. Hierarchy query has no post_type, so
	 * the slug is enough for frontend resolution.
	 *
	 * @return void
	 */
	public function register_single_term_block_template() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		$registry = \WP_Block_Templates_Registry::get_instance();
		if ( $registry->is_registered( self::BLOCK_TEMPLATE_NAME ) ) {
			return;
		}

		$content = $this->get_plugin_template_markup( 'single-term.html' );
		if ( '' === $content ) {
			return;
		}

		register_block_template(
			self::BLOCK_TEMPLATE_NAME,
			array(
				'title'       => __( 'Single Term', 'context-authority-toolkit' ),
				'description' => __( 'Displays a glossary term, including the CAT term panel.', 'context-authority-toolkit' ),
				'content'     => $content,
				'post_types'  => array( Cat_Glossary_Admin::POST_TYPE ),
			)
		);
	}

	/**
	 * Label `single-term` as a default template type in the Site Editor.
	 *
	 * @param array $types Default template types.
	 * @return array
	 */
	public function register_default_template_type( $types ) {
		if ( ! is_array( $types ) ) {
			return $types;
		}

		$types[ self::BLOCK_TEMPLATE_SLUG ] = array(
			'title'       => __( 'Single Term', 'context-authority-toolkit' ),
			'description' => __( 'Displays a glossary term, including the CAT term panel.', 'context-authority-toolkit' ),
		);

		return $types;
	}

	/**
	 * Inject the plugin single-term template on WP 6.4–6.6 (no registry API).
	 *
	 * @param \WP_Block_Template[] $query_result Found templates.
	 * @param array                $query        Template query.
	 * @param string               $template_type Template type.
	 * @return \WP_Block_Template[]
	 */
	public function filter_block_templates( $query_result, $query, $template_type ) {
		if ( function_exists( 'register_block_template' ) || 'wp_template' !== $template_type ) {
			return $query_result;
		}

		$query = is_array( $query ) ? $query : array();
		$slug  = self::BLOCK_TEMPLATE_SLUG;

		if ( ! empty( $query['slug__in'] ) && ! in_array( $slug, (array) $query['slug__in'], true ) ) {
			return $query_result;
		}

		if ( ! empty( $query['post_type'] ) && Cat_Glossary_Admin::POST_TYPE !== $query['post_type'] ) {
			return $query_result;
		}

		if ( ! is_array( $query_result ) ) {
			$query_result = array();
		}

		foreach ( $query_result as $existing ) {
			if ( $existing instanceof \WP_Block_Template && $slug === $existing->slug ) {
				return $query_result;
			}
		}

		$query_result[] = $this->build_plugin_block_template();
		return $query_result;
	}

	/**
	 * Resolve the plugin single-term template by id on WP 6.4–6.6.
	 *
	 * @param \WP_Block_Template|null $block_template Found template.
	 * @param string                  $id             Template id (`theme//slug`).
	 * @param string                  $template_type  Template type.
	 * @return \WP_Block_Template|null
	 */
	public function filter_block_file_template( $block_template, $id, $template_type ) {
		if ( $block_template || function_exists( 'register_block_template' ) || 'wp_template' !== $template_type ) {
			return $block_template;
		}

		$parts = explode( '//', (string) $id );
		$slug  = isset( $parts[1] ) ? $parts[1] : '';
		if ( self::BLOCK_TEMPLATE_SLUG !== $slug ) {
			return $block_template;
		}

		return $this->build_plugin_block_template();
	}

	/**
	 * Dynamic block render callback — delegates to chrome.
	 *
	 * @param array $attributes Block attributes (unused).
	 * @return string
	 */
	public function render_panel_block( $attributes = array() ) {
		unset( $attributes );

		$term_id = $this->get_context_term_post_id();
		if ( $term_id <= 0 || ! self::is_enabled() ) {
			return '';
		}

		$html = $this->build_panel_html( $term_id );
		if ( '' !== $html ) {
			self::mark_panel_printed();
		}

		return $html;
	}

	/**
	 * Print the panel at the start of the primary active classic sidebar.
	 *
	/**
	 * Skipped on block themes (plugin `single-term` template owns placement).
	 *
	 * @param int|string $index       Sidebar index / id.
	 * @param bool       $has_widgets Whether the sidebar has widgets.
	 * @return void
	 */
	public function maybe_print_in_sidebar( $index, $has_widgets ) {
		if ( ! $has_widgets || self::$panel_printed || wp_is_block_theme() ) {
			return;
		}

		if ( ! self::is_enabled() || ! is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			return;
		}

		$primary = $this->get_primary_sidebar_id();
		if ( '' === $primary || (string) $index !== (string) $primary ) {
			return;
		}

		$term_id = $this->get_context_term_post_id();
		if ( $term_id <= 0 ) {
			return;
		}

		$html = $this->build_panel_html( $term_id );
		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_panel_html escapes fragments.
		echo $html;
		self::mark_panel_printed();
	}

	/**
	 * Append an in-content aside when classic sidebar injection did not run.
	 *
	 * Runs after Peacekeeper semantic chrome (priority 30). Guarded by the
	 * `cat-term-panel` class marker against double inject.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function maybe_append_to_content( $content ) {
		if ( self::$panel_printed || wp_is_block_theme() ) {
			return $content;
		}

		if ( ! self::is_enabled() || ! is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			return $content;
		}

		if ( false !== strpos( (string) $content, 'cat-term-panel' ) ) {
			return $content;
		}

		// Prefer sidebar when the primary sidebar is active; otherwise fall back.
		if ( $this->has_active_primary_sidebar() ) {
			return $content;
		}

		$term_id = $this->get_context_term_post_id();
		if ( $term_id <= 0 ) {
			return $content;
		}

		$html = $this->build_panel_html( $term_id );
		if ( '' === $html ) {
			return $content;
		}

		self::mark_panel_printed();
		return (string) $content . $html;
	}

	/**
	 * Enqueue cite-this view assets when the panel will include cite-this.
	 *
	 * The cite-this block is not in post content on classic inject or the FSE
	 * single-term template, so its view assets would not load otherwise.
	 *
	 * @return void
	 */
	public function maybe_enqueue_cite_assets() {
		if ( ! self::is_enabled() || ! self::show_cite_this() ) {
			return;
		}

		if ( ! is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_script( 'cat-cite-this-view' );
		wp_enqueue_style( 'cat-cite-this-style' );
	}

	/**
	 * Build panel HTML from chrome using current section options.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string
	 */
	public function build_panel_html( $term_post_id ) {
		return self::render_panel_for_term( $term_post_id );
	}

	/**
	 * Render panel HTML for a term using current Customizer section options.
	 *
	 * Safe to call without constructing Cat_Term_Panel (avoids re-hooking).
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string
	 */
	public static function render_panel_for_term( $term_post_id ) {
		$chrome = new Cat_Term_Single_Chrome();

		return $chrome->render_panel_html(
			$term_post_id,
			array(
				'aliases'   => self::show_aliases(),
				'related'   => self::show_related(),
				'same_as'   => self::show_same_as(),
				'sources'   => self::show_sources(),
				'cite_this' => self::show_cite_this(),
			)
		);
	}

	/**
	 * Whether the CAT term panel master switch is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_ENABLED, true ) );
	}

	/**
	 * Whether aliases should appear in the panel.
	 *
	 * @return bool
	 */
	public static function show_aliases() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_SHOW_ALIASES, true ) );
	}

	/**
	 * Whether related terms should appear in the panel.
	 *
	 * @return bool
	 */
	public static function show_related() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_SHOW_RELATED, true ) );
	}

	/**
	 * Whether sameAs / authority links should appear in the panel.
	 *
	 * @return bool
	 */
	public static function show_same_as() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_SHOW_SAME_AS, true ) );
	}

	/**
	 * Whether sources should appear in the panel.
	 *
	 * @return bool
	 */
	public static function show_sources() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_SHOW_SOURCES, true ) );
	}

	/**
	 * Whether cite-this should appear in the panel.
	 *
	 * @return bool
	 */
	public static function show_cite_this() {
		return (bool) self::sanitize_boolean_option( get_option( self::OPTION_SHOW_CITE_THIS, true ) );
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
	 * Mark the panel as printed for this request (shared across widget + inject).
	 *
	 * @return void
	 */
	public static function mark_panel_printed() {
		self::$panel_printed = true;
	}

	/**
	 * Whether the panel was already printed this request.
	 *
	 * @return bool
	 */
	public static function was_panel_printed() {
		return self::$panel_printed;
	}

	/**
	 * Reset the printed flag (tests only).
	 *
	 * @return void
	 */
	public static function reset_panel_printed_flag() {
		self::$panel_printed = false;
	}

	/**
	 * Resolve the primary classic sidebar id.
	 *
	 * Prefers sidebar-1 when active; otherwise the first active registered sidebar.
	 *
	 * @return string Empty when none active.
	 */
	public function get_primary_sidebar_id() {
		global $wp_registered_sidebars;

		if ( is_active_sidebar( self::PRIMARY_SIDEBAR_HINT ) ) {
			return self::PRIMARY_SIDEBAR_HINT;
		}

		if ( ! is_array( $wp_registered_sidebars ) ) {
			return '';
		}

		foreach ( array_keys( $wp_registered_sidebars ) as $sidebar_id ) {
			if ( is_active_sidebar( $sidebar_id ) ) {
				return (string) $sidebar_id;
			}
		}

		return '';
	}

	/**
	 * Whether a primary classic sidebar is currently active.
	 *
	 * @return bool
	 */
	private function has_active_primary_sidebar() {
		return '' !== $this->get_primary_sidebar_id();
	}

	/**
	 * Load plugin-owned block template markup.
	 *
	 * @param string $filename File under plugin `templates/`.
	 * @return string
	 */
	private function get_plugin_template_markup( $filename ) {
		$filename = sanitize_file_name( (string) $filename );
		if ( '' === $filename ) {
			return '';
		}

		$path = CAT_TOOLKIT_DIR . 'templates/' . $filename;
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$markup = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin template file.
		return is_string( $markup ) ? $markup : '';
	}

	/**
	 * Build a WP_Block_Template object for the plugin single-term layout.
	 *
	 * @return \WP_Block_Template
	 */
	private function build_plugin_block_template() {
		$template              = new \WP_Block_Template();
		$template->id          = get_stylesheet() . '//' . self::BLOCK_TEMPLATE_SLUG;
		$template->theme       = get_stylesheet();
		$template->plugin      = 'context-authority-toolkit';
		$template->content     = $this->get_plugin_template_markup( 'single-term.html' );
		$template->source      = 'plugin';
		$template->slug        = self::BLOCK_TEMPLATE_SLUG;
		$template->type        = 'wp_template';
		$template->title       = __( 'Single Term', 'context-authority-toolkit' );
		$template->description = __( 'Displays a glossary term, including the CAT term panel.', 'context-authority-toolkit' );
		$template->status      = 'publish';
		$template->origin      = 'plugin';
		$template->is_custom   = false;
		$template->post_types  = array( Cat_Glossary_Admin::POST_TYPE );

		return $template;
	}

	/**
	 * Resolve the current term post ID in singular context.
	 *
	 * @return int
	 */
	private function get_context_term_post_id() {
		if ( ! is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			$post_id = (int) get_the_ID();
			if ( $post_id <= 0 ) {
				return 0;
			}

			$post = get_post( $post_id );
			if ( ! $post || Cat_Glossary_Admin::POST_TYPE !== $post->post_type ) {
				return 0;
			}

			return $post_id;
		}

		return (int) get_queried_object_id();
	}
}
