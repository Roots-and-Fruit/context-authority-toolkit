<?php
/**
 * Default-first schema transport and integration module.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps CAT as canonical schema source across delivery modes.
 */
class Cat_SEO_Peacekeeper {
	/**
	 * Schema output mode option key.
	 */
	const OPTION_SCHEMA_OUTPUT_MODE = 'cat_schema_output_mode';

	/**
	 * Breadcrumb integration option key.
	 */
	const OPTION_BREADCRUMB_INTEGRATION = 'cat_breadcrumb_integration';

	/**
	 * Auto mode.
	 */
	const MODE_AUTO = 'auto';

	/**
	 * Standalone mode.
	 */
	const MODE_STANDALONE = 'standalone';

	/**
	 * Off mode.
	 */
	const MODE_OFF = 'off';

	/**
	 * SEOPress filter used for term schema injection.
	 */
	const SEOPRESS_SCHEMA_FILTER = 'seopress_schemas_auto_article_json';

	/**
	 * Term-single chrome renderer.
	 *
	 * @var Cat_Term_Single_Chrome
	 */
	private $term_chrome;

	/**
	 * Register module hooks.
	 */
	public function __construct() {
		$this->term_chrome = new Cat_Term_Single_Chrome();

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'wp_head', array( $this, 'output_standalone_json_ld' ), 99 );
		add_filter( 'the_content', array( $this, 'add_semantic_term_markup' ), 30 );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_yoast_meta_description' ) );
		add_filter( 'wpseo_schema_graph_pieces', array( $this, 'inject_yoast_graph_piece' ), 20, 2 );
		add_filter( 'wpseo_schema_graph', array( $this, 'attach_yoast_main_entity' ), 20, 2 );
		add_filter( 'wpseo_schema_needs_breadcrumb', array( $this, 'filter_yoast_breadcrumb_setting' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_rank_math_meta_description' ) );
		add_filter( 'rank_math/json_ld', array( $this, 'inject_rank_math_json_ld' ), 20, 2 );
		add_filter( 'rank_math/json_ld/breadcrumbs_enabled', array( $this, 'filter_rank_math_breadcrumb_setting' ) );
		add_filter( self::SEOPRESS_SCHEMA_FILTER, array( $this, 'inject_seopress_schema' ) );
	}

	/**
	 * Register module settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'cat_schema_settings',
			self::OPTION_SCHEMA_OUTPUT_MODE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_schema_output_mode' ),
				'default'           => self::MODE_AUTO,
			)
		);

		register_setting(
			'cat_schema_settings',
			self::OPTION_BREADCRUMB_INTEGRATION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		add_settings_section(
			'cat_schema_settings_section',
			__( 'SEO Peacekeeper', 'context-authority-toolkit' ),
			array( $this, 'render_settings_intro' ),
			'cat-schema-settings'
		);

		add_settings_field(
			self::OPTION_SCHEMA_OUTPUT_MODE,
			__( 'Schema Output', 'context-authority-toolkit' ),
			array( $this, 'render_schema_output_mode_field' ),
			'cat-schema-settings',
			'cat_schema_settings_section'
		);

		add_settings_field(
			self::OPTION_BREADCRUMB_INTEGRATION,
			__( 'Breadcrumb Integration', 'context-authority-toolkit' ),
			array( $this, 'render_breadcrumb_integration_field' ),
			'cat-schema-settings',
			'cat_schema_settings_section'
		);
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'CAT Schema Settings', 'context-authority-toolkit' ),
			__( 'CAT Schema', 'context-authority-toolkit' ),
			'manage_options',
			'cat-schema-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Output settings intro text.
	 *
	 * @return void
	 */
	public function render_settings_intro() {
		echo '<p>' . esc_html__( 'Control how glossary DefinedTerm schema is delivered and how CAT coordinates breadcrumbs with SEO plugins.', 'context-authority-toolkit' ) . '</p>';
	}

	/**
	 * Render schema mode setting.
	 *
	 * @return void
	 */
	public function render_schema_output_mode_field() {
		$current = $this->get_schema_output_mode_setting();
		$options = array(
			self::MODE_AUTO       => __( 'Enabled (Auto-Detect)', 'context-authority-toolkit' ),
			self::MODE_STANDALONE => __( 'Standalone Only', 'context-authority-toolkit' ),
			self::MODE_OFF        => __( 'Off', 'context-authority-toolkit' ),
		);

		echo '<select name="' . esc_attr( self::OPTION_SCHEMA_OUTPUT_MODE ) . '">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render breadcrumb setting.
	 *
	 * @return void
	 */
	public function render_breadcrumb_integration_field() {
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_BREADCRUMB_INTEGRATION ),
			checked( $this->is_breadcrumb_integration_enabled(), true, false ),
			esc_html__( 'Enable interoperability with Yoast/Rank Math breadcrumb schema controls.', 'context-authority-toolkit' )
		);
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
			<h1><?php esc_html_e( 'CAT Schema Settings', 'context-authority-toolkit' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'cat_schema_settings' );
				do_settings_sections( 'cat-schema-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize schema mode setting.
	 *
	 * @param string $mode Submitted mode.
	 * @return string
	 */
	public function sanitize_schema_output_mode( $mode ) {
		$mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : '';

		if ( ! in_array( $mode, array( self::MODE_AUTO, self::MODE_STANDALONE, self::MODE_OFF ), true ) ) {
			return self::MODE_AUTO;
		}

		return $mode;
	}

	/**
	 * Get saved schema mode.
	 *
	 * @return string
	 */
	private function get_schema_output_mode_setting() {
		$mode = get_option( self::OPTION_SCHEMA_OUTPUT_MODE, self::MODE_AUTO );
		return $this->sanitize_schema_output_mode( $mode );
	}

	/**
	 * Get resolved schema mode after external overrides.
	 *
	 * @return string
	 */
	private function get_resolved_schema_output_mode() {
		$mode = $this->get_schema_output_mode_setting();
		$mode = apply_filters( 'context_authority_toolkit_schema_output_mode', $mode );

		return $this->sanitize_schema_output_mode( $mode );
	}

	/**
	 * Check whether breadcrumb integration is enabled.
	 *
	 * @return bool
	 */
	private function is_breadcrumb_integration_enabled() {
		$enabled = (bool) rest_sanitize_boolean( get_option( self::OPTION_BREADCRUMB_INTEGRATION, true ) );
		return (bool) apply_filters( 'context_authority_toolkit_schema_breadcrumb_integration_enabled', $enabled );
	}

	/**
	 * Resolve transport mode from settings and active SEO plugins.
	 *
	 * @return string
	 */
	private function resolve_transport_mode() {
		$mode = $this->get_resolved_schema_output_mode();

		if ( self::MODE_OFF === $mode ) {
			return self::MODE_OFF;
		}

		if ( self::MODE_STANDALONE === $mode ) {
			return self::MODE_STANDALONE;
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rank-math';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			if ( $this->has_seopress_transport_support() ) {
				return 'seopress';
			}

			// If SEOPress is active but no compatible schema hook is available,
			// keep CAT in standalone mode to preserve schema parity.
			return self::MODE_STANDALONE;
		}

		return self::MODE_STANDALONE;
	}

	/**
	 * Build canonical term schema from CAT-owned data.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return array
	 */
	public function get_canonical_term_schema( $term_post_id ) {
		$term_post = get_post( absint( $term_post_id ) );
		if ( ! $term_post || Cat_Glossary_Admin::POST_TYPE !== $term_post->post_type ) {
			return array();
		}

		$name        = trim( (string) $term_post->post_title );
		$description = get_post_meta( $term_post->ID, Cat_Glossary_Admin::TOOLTIP_META_KEY, true );
		$description = is_string( $description ) ? trim( $description ) : '';
		$term_url    = get_permalink( $term_post->ID );
		$defined_set = Cat_Term_Category::get_defined_term_set_url_for_post( $term_post->ID );
		$same_as_raw = get_post_meta( $term_post->ID, Cat_Glossary_Admin::SAME_AS_META_KEY, true );
		$sources_raw = get_post_meta( $term_post->ID, Cat_Glossary_Admin::SOURCES_META_KEY, true );
		$read_aloud  = $this->prepare_read_aloud_text( $description ? $description : (string) $term_post->post_content );
		$alias_props = $this->map_alternatives_to_schema( $term_post->ID, $name );
		$canonical   = array(
			'@type'            => 'DefinedTerm',
			'@id'              => trailingslashit( (string) $term_url ) . '#definedterm',
			'name'             => $name,
			'description'      => $description ? $description : $read_aloud,
			'url'              => (string) $term_url,
			'inDefinedTermSet' => (string) $defined_set,
			'alternateName'    => $alias_props['alternateName'],
			'sameAs'           => $this->normalize_same_as_array( $same_as_raw ),
			'citation'         => $this->normalize_citation_array( $sources_raw ),
		);

		if ( ! empty( $alias_props['termCode'] ) ) {
			$canonical['termCode'] = ( 1 === count( $alias_props['termCode'] ) )
				? $alias_props['termCode'][0]
				: $alias_props['termCode'];
		}

		$canonical = apply_filters( 'context_authority_toolkit_schema_canonical_term_data', $canonical, $term_post );

		return $this->remove_empty_schema_properties( $canonical );
	}

	/**
	 * Map cat_alternatives into alternateName / termCode schema properties.
	 *
	 * Title duplicates are skipped. Abbreviations (2–6 chars, A–Z/0–9 only,
	 * fully uppercase) also become termCode.
	 *
	 * @param int    $term_post_id Term post ID.
	 * @param string $term_title   Unused; retained for call-site clarity.
	 * @return array{alternateName:string[],termCode:string[]}
	 */
	public function map_alternatives_to_schema( $term_post_id, $term_title = '' ) {
		unset( $term_title );

		$chrome  = $this->term_chrome instanceof Cat_Term_Single_Chrome ? $this->term_chrome : new Cat_Term_Single_Chrome();
		$aliases = $chrome->get_display_aliases( $term_post_id );

		$alternate_name = array();
		$term_codes     = array();

		foreach ( $aliases as $alias ) {
			$alternate_name[] = $alias;
			if ( $chrome->is_term_code_alias( $alias ) ) {
				$term_codes[] = $alias;
			}
		}

		return array(
			'alternateName' => array_values( array_unique( $alternate_name ) ),
			'termCode'      => array_values( array_unique( $term_codes ) ),
		);
	}

	/**
	 * Build standalone @graph nodes for a term single (WebPage + DefinedTerm).
	 *
	 * @param int $term_post_id Term post ID.
	 * @return array[]
	 */
	public function build_standalone_term_graph( $term_post_id ) {
		$defined_term = $this->get_canonical_term_schema( $term_post_id );
		if ( empty( $defined_term ) ) {
			return array();
		}

		$webpage = array(
			'@type'      => 'WebPage',
			'url'        => isset( $defined_term['url'] ) ? (string) $defined_term['url'] : '',
			'mainEntity' => isset( $defined_term['@id'] ) ? (string) $defined_term['@id'] : '',
		);

		$webpage = $this->remove_empty_schema_properties( $webpage );

		return array( $webpage, $defined_term );
	}

	/**
	 * Normalize sameAs values into unique URL list.
	 *
	 * @param mixed $same_as Raw meta value.
	 * @return array
	 */
	public function normalize_same_as_array( $same_as ) {
		if ( is_string( $same_as ) ) {
			$same_as = preg_split( '/[\r\n,]+/', $same_as );
		}

		if ( ! is_array( $same_as ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $same_as as $entry ) {
			$url = $this->sanitize_schema_url( $entry );
			if ( '' !== $url ) {
				$normalized[] = $url;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize source repeater data into citation property.
	 *
	 * @param mixed $sources Raw sources meta.
	 * @return array
	 */
	public function normalize_citation_array( $sources ) {
		if ( ! is_array( $sources ) ) {
			return array();
		}

		$citations = array();
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$url = isset( $source['url'] ) ? $this->sanitize_schema_url( $source['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}

			$title          = isset( $source['title'] ) ? sanitize_text_field( $source['title'] ) : '';
			$publisher_name = isset( $source['publisher'] ) ? sanitize_text_field( $source['publisher'] ) : '';
			$date           = isset( $source['datePublished'] ) ? $this->sanitize_schema_date( $source['datePublished'] ) : '';

			if ( '' === $title && '' === $publisher_name && '' === $date ) {
				$citations[] = $url;
				continue;
			}

			$citation = array(
				'@type' => 'CreativeWork',
				'url'   => $url,
			);

			if ( '' !== $title ) {
				$citation['name'] = $title;
			}

			if ( '' !== $publisher_name ) {
				$citation['publisher'] = array(
					'@type' => 'Organization',
					'name'  => $publisher_name,
				);
			}

			if ( '' !== $date ) {
				$citation['datePublished'] = $date;
			}

			$citations[] = $citation;
		}

		return $citations;
	}

	/**
	 * Emit standalone JSON-LD if this module is the transport owner.
	 *
	 * @return void
	 */
	public function output_standalone_json_ld() {
		if ( self::MODE_STANDALONE !== $this->resolve_transport_mode() ) {
			return;
		}

		$graph_nodes = array();
		$term_id     = $this->get_context_term_post_id();
		if ( $term_id > 0 ) {
			$graph_nodes = $this->build_standalone_term_graph( $term_id );
		} else {
			$set_node = $this->get_context_defined_term_set_schema();
			if ( ! empty( $set_node ) ) {
				$graph_nodes = array( $set_node );
			}
		}

		if ( empty( $graph_nodes ) ) {
			return;
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph_nodes,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	/**
	 * Inject CAT-defined term node into Yoast graph pieces.
	 *
	 * @param array $pieces  Existing pieces.
	 * @param mixed $context Yoast schema context object.
	 * @return array
	 */
	public function inject_yoast_graph_piece( $pieces, $context ) {
		unset( $context );

		if ( 'yoast' !== $this->resolve_transport_mode() ) {
			return $pieces;
		}

		if ( ! class_exists( '\Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ) ) {
			return $pieces;
		}

		$node = $this->get_canonical_term_schema( $this->get_context_term_post_id() );
		if ( empty( $node ) ) {
			$node = $this->get_context_defined_term_set_schema();
		}
		if ( empty( $node ) ) {
			return $pieces;
		}

		$pieces[] = new class( $node ) extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {
			/**
			 * Node payload.
			 *
			 * @var array
			 */
			private $node = array();

			/**
			 * Constructor.
			 *
			 * @param array $node Schema node.
			 */
			public function __construct( $node ) {
				$this->node = $node;
			}

			/**
			 * Always needed when node exists.
			 *
			 * @return bool
			 */
			public function is_needed() {
				return ! empty( $this->node );
			}

			/**
			 * Return the final node.
			 *
			 * @return array
			 */
			public function generate() {
				return $this->node;
			}
		};

		return $pieces;
	}

	/**
	 * Attach DefinedTerm mainEntity onto Yoast's existing page node.
	 *
	 * Prefer mutating the SEO plugin WebPage (or similar) instead of appending
	 * a second WebPage. DefinedTerm itself is still added via graph pieces.
	 *
	 * @param array $graph   Assembled Yoast schema graph.
	 * @param mixed $context Yoast schema context object.
	 * @return array
	 */
	public function attach_yoast_main_entity( $graph, $context ) {
		unset( $context );

		if ( 'yoast' !== $this->resolve_transport_mode() || ! is_array( $graph ) ) {
			return $graph;
		}

		$node = $this->get_canonical_term_schema( $this->get_context_term_post_id() );
		if ( empty( $node ) || empty( $node['@id'] ) ) {
			return $graph;
		}

		return $this->attach_main_entity_to_graph( $graph, (string) $node['@id'] );
	}

	/**
	 * Inject CAT-defined node into Rank Math JSON-LD payload.
	 *
	 * @param array $data   Existing JSON-LD data.
	 * @param mixed $jsonld Rank Math JsonLD object.
	 * @return array
	 */
	public function inject_rank_math_json_ld( $data, $jsonld ) {
		unset( $jsonld );

		if ( 'rank-math' !== $this->resolve_transport_mode() ) {
			return $data;
		}

		$node = $this->get_canonical_term_schema( $this->get_context_term_post_id() );
		if ( empty( $node ) ) {
			$node = $this->get_context_defined_term_set_schema();
		}
		if ( empty( $node ) ) {
			return $data;
		}

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			if ( ! empty( $node['@id'] ) && 'DefinedTerm' === ( isset( $node['@type'] ) ? $node['@type'] : '' ) ) {
				$data['@graph'] = $this->attach_main_entity_to_graph( $data['@graph'], (string) $node['@id'] );
			}
			$data['@graph'][] = $node;
			return $data;
		}

		if ( $this->is_sequential_array( $data ) ) {
			if ( ! empty( $node['@id'] ) && 'DefinedTerm' === ( isset( $node['@type'] ) ? $node['@type'] : '' ) ) {
				$data = $this->attach_main_entity_to_graph( $data, (string) $node['@id'] );
			}
			$data[] = $node;
			return $data;
		}

		$data['CAT_DefinedTerm'] = $node;
		return $data;
	}

	/**
	 * Inject CAT-defined node into documented SEOPress schema filter path.
	 *
	 * @param array $schema Existing SEOPress schema array.
	 * @return array
	 */
	public function inject_seopress_schema( $schema ) {
		if ( 'seopress' !== $this->resolve_transport_mode() || ! is_array( $schema ) ) {
			return $schema;
		}

		$node = $this->get_canonical_term_schema( $this->get_context_term_post_id() );
		if ( empty( $node ) ) {
			$node = $this->get_context_defined_term_set_schema();
		}
		if ( empty( $node ) ) {
			return $schema;
		}

		if ( isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ) {
			if ( ! empty( $node['@id'] ) && 'DefinedTerm' === ( isset( $node['@type'] ) ? $node['@type'] : '' ) ) {
				$schema['@graph'] = $this->attach_main_entity_to_graph( $schema['@graph'], (string) $node['@id'] );
			}
			$schema['@graph'][] = $node;
			return $schema;
		}

		if ( $this->is_sequential_array( $schema ) ) {
			if ( ! empty( $node['@id'] ) && 'DefinedTerm' === ( isset( $node['@type'] ) ? $node['@type'] : '' ) ) {
				$schema = $this->attach_main_entity_to_graph( $schema, (string) $node['@id'] );
			}
			$schema[] = $node;
			return $schema;
		}

		$schema['CAT_DefinedTerm'] = $node;
		return $schema;
	}

	/**
	 * Determine if compatible SEOPress schema transport is available.
	 *
	 * @return bool
	 */
	private function has_seopress_transport_support() {
		// In practice, SEOPress Free does not provide CAT-compatible schema transport
		// for this DefinedTerm integration path. Require PRO before treating SEOPress
		// as schema transport owner in auto mode.
		$has_pro_support = defined( 'SEOPRESS_PRO_VERSION' ) || defined( 'SEOPRESS_PRO_PLUGIN_DIR_PATH' );
		$has_filter      = false !== has_filter( self::SEOPRESS_SCHEMA_FILTER );
		$is_available    = ( $has_pro_support && $has_filter );

		/**
		 * Allow transport compatibility overrides for custom SEOPress builds.
		 *
		 * @param bool $is_available Whether compatible transport was detected.
		 */
		return (bool) apply_filters( 'context_authority_toolkit_seopress_transport_available', $is_available );
	}

	/**
	 * Add semantic microdata wrapper on term pages.
	 *
	 * @param string $content Term content.
	 * @return string
	 */
	public function add_semantic_term_markup( $content ) {
		$term_id = $this->get_context_term_post_id();
		if ( $term_id <= 0 || false !== strpos( $content, 'cat-defined-term-semantic' ) ) {
			return $content;
		}

		$term_post = get_post( $term_id );
		if ( ! $term_post ) {
			return $content;
		}

		$title = trim( (string) $term_post->post_title );
		if ( '' === $title ) {
			return $content;
		}

		$chrome        = $this->term_chrome instanceof Cat_Term_Single_Chrome ? $this->term_chrome : new Cat_Term_Single_Chrome();
		$lead_html     = $chrome->render_lead_html( $term_id, $content );
		$aliases_html  = $chrome->render_aliases_html( $term_id );
		$term_label_id = sprintf( 'cat-defined-term-name-%d', (int) $term_id );
		$semantic      = $this->inject_defined_term_name_markup( $content, $title, $term_label_id );
		$name_id       = isset( $semantic['name_id'] ) ? trim( (string) $semantic['name_id'] ) : '';
		$description   = isset( $semantic['content'] ) ? (string) $semantic['content'] : $content;

		$article_attributes = ' class="cat-defined-term-semantic" itemscope itemtype="https://schema.org/DefinedTerm"';
		if ( '' !== $name_id ) {
			$article_attributes .= ' aria-labelledby="' . esc_attr( $name_id ) . '"';
		}

		return sprintf(
			'<article%1$s><div itemprop="description" role="definition">%2$s%3$s</div>%4$s</article>',
			$article_attributes,
			$lead_html,
			$description,
			$aliases_html
		);
	}

	/**
	 * Inject term name microdata into first matching first-paragraph occurrence.
	 *
	 * If a manual <dfn> tag already exists, annotate that tag instead of adding
	 * another one.
	 *
	 * @param string $content       Term content.
	 * @param string $term_name     Term title.
	 * @param string $fallback_name Fallback semantic ID.
	 * @return array{content:string,name_id:string}
	 */
	private function inject_defined_term_name_markup( $content, $term_name, $fallback_name ) {
		$content = (string) $content;
		if ( '' === $content || '' === trim( $term_name ) ) {
			return array(
				'content' => $content,
				'name_id' => '',
			);
		}

		if ( preg_match( '/<dfn\b/i', $content ) ) {
			$updated_content = preg_replace_callback(
				'/<dfn\b([^>]*)>/i',
				function ( $matches ) use ( $fallback_name ) {
					$attributes = isset( $matches[1] ) ? trim( (string) $matches[1] ) : '';

					if ( ! preg_match( '/\bitemprop\s*=\s*(["\'])name\1/i', $attributes ) ) {
						$attributes .= ' itemprop="name"';
					}

					if ( ! preg_match( '/\bid\s*=\s*(["\']).*?\1/i', $attributes ) ) {
						$attributes .= ' id="' . esc_attr( $fallback_name ) . '"';
					}

					return '<dfn ' . trim( $attributes ) . '>';
				},
				$content,
				1
			);

			$name_id = '';
			if (
				is_string( $updated_content ) &&
				preg_match( '/<dfn\b[^>]*\bid\s*=\s*(["\'])([^"\']+)\1/i', $updated_content, $id_matches ) &&
				! empty( $id_matches[2] )
			) {
				$name_id = (string) $id_matches[2];
			}

			return array(
				'content' => is_string( $updated_content ) ? $updated_content : $content,
				'name_id' => $name_id,
			);
		}

		$first_paragraph = array(
			'html'  => '',
			'open'  => '',
			'body'  => '',
			'close' => '',
		);
		if (
			! preg_match(
				'/(<p\b[^>]*>)(.*?)(<\/p>)/is',
				$content,
				$paragraph_matches
			)
		) {
			return array(
				'content' => $content,
				'name_id' => '',
			);
		}

		$first_paragraph['html']  = isset( $paragraph_matches[0] ) ? (string) $paragraph_matches[0] : '';
		$first_paragraph['open']  = isset( $paragraph_matches[1] ) ? (string) $paragraph_matches[1] : '';
		$first_paragraph['body']  = isset( $paragraph_matches[2] ) ? (string) $paragraph_matches[2] : '';
		$first_paragraph['close'] = isset( $paragraph_matches[3] ) ? (string) $paragraph_matches[3] : '';

		if ( '' === $first_paragraph['body'] || false === stripos( wp_strip_all_tags( $first_paragraph['body'] ), $term_name ) ) {
			return array(
				'content' => $content,
				'name_id' => '',
			);
		}

		$pattern      = '/' . preg_quote( $term_name, '/' ) . '/i';
		$replacement  = '<dfn id="' . esc_attr( $fallback_name ) . '" itemprop="name">$0</dfn>';
		$updated_body = preg_replace( $pattern, $replacement, $first_paragraph['body'], 1, $replace_count );

		if ( ! is_string( $updated_body ) || 1 !== $replace_count ) {
			return array(
				'content' => $content,
				'name_id' => '',
			);
		}

		$updated_paragraph = $first_paragraph['open'] . $updated_body . $first_paragraph['close'];
		$updated_content   = preg_replace(
			'/' . preg_quote( $first_paragraph['html'], '/' ) . '/',
			$updated_paragraph,
			$content,
			1
		);

		return array(
			'content' => is_string( $updated_content ) ? $updated_content : $content,
			'name_id' => $fallback_name,
		);
	}

	/**
	 * Sanitize definition text for read-aloud use.
	 *
	 * @param string $raw_text Raw text.
	 * @return string
	 */
	public function prepare_read_aloud_text( $raw_text ) {
		$text = strip_shortcodes( (string) $raw_text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/[^\P{C}\n\t]+/u', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return (string) apply_filters( 'context_authority_toolkit_schema_read_aloud_text', $text, $raw_text );
	}

	/**
	 * Allow CAT setting to disable Yoast breadcrumb graph output.
	 *
	 * @param bool $needs_breadcrumb Existing value.
	 * @return bool
	 */
	public function filter_yoast_breadcrumb_setting( $needs_breadcrumb ) {
		if ( ! $this->is_breadcrumb_integration_enabled() ) {
			return false;
		}

		return $needs_breadcrumb;
	}

	/**
	 * Allow CAT setting to disable Rank Math breadcrumb graph output.
	 *
	 * @param bool $enabled Existing value.
	 * @return bool
	 */
	public function filter_rank_math_breadcrumb_setting( $enabled ) {
		if ( ! $this->is_breadcrumb_integration_enabled() ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Provide Yoast meta description fallback from term excerpt.
	 *
	 * @param string $meta_description Existing meta description.
	 * @return string
	 */
	public function filter_yoast_meta_description( $meta_description ) {
		if ( '' !== trim( (string) $meta_description ) ) {
			return $meta_description;
		}

		$fallback = $this->get_context_meta_description_text();
		if ( '' !== $fallback ) {
			return $fallback;
		}

		return $meta_description;
	}

	/**
	 * Provide Rank Math meta description fallback from term excerpt.
	 *
	 * @param string $description Existing meta description.
	 * @return string
	 */
	public function filter_rank_math_meta_description( $description ) {
		if ( '' !== trim( (string) $description ) ) {
			return $description;
		}

		$fallback = $this->get_context_meta_description_text();
		if ( '' !== $fallback ) {
			return $fallback;
		}

		return $description;
	}

	/**
	 * Resolve current context term ID.
	 *
	 * @return int
	 */
	private function get_context_term_post_id() {
		if ( is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			$post_id = get_queried_object_id();
			return $post_id > 0 ? absint( $post_id ) : 0;
		}

		$post_id = get_the_ID();
		if ( $post_id > 0 && Cat_Glossary_Admin::POST_TYPE === get_post_type( $post_id ) ) {
			return absint( $post_id );
		}

		global $post;
		if ( $post instanceof \WP_Post && Cat_Glossary_Admin::POST_TYPE === $post->post_type ) {
			return absint( $post->ID );
		}

		return 0;
	}

	/**
	 * Get sanitized meta description fallback for current term context.
	 *
	 * @return string
	 */
	private function get_context_meta_description_text() {
		$term_id = $this->get_context_term_post_id();
		if ( $term_id <= 0 ) {
			return '';
		}

		$excerpt = trim( (string) get_post_field( 'post_excerpt', $term_id ) );
		if ( '' === $excerpt ) {
			return '';
		}

		return $this->prepare_read_aloud_text( $excerpt );
	}

	/**
	 * Get glossary archive URL for inDefinedTermSet.
	 *
	 * @return string
	 */
	private function get_defined_term_set_url() {
		return Cat_Term_Category::get_defined_term_set_url_for_post( 0 );
	}

	/**
	 * Build DefinedTermSet schema for the current Category archive context.
	 *
	 * @return array
	 */
	private function get_context_defined_term_set_schema() {
		if ( ! Cat_Term_Settings::are_categories_enabled() ) {
			return array();
		}

		if ( ! is_tax( Cat_Term_Category::TAXONOMY ) ) {
			return array();
		}

		$category = get_queried_object();
		if ( ! ( $category instanceof \WP_Term ) ) {
			return array();
		}

		return Cat_Term_Category::get_canonical_defined_term_set_schema( $category );
	}

	/**
	 * Set mainEntity on the first WebPage-like node in a schema graph.
	 *
	 * Does not append a WebPage when none exists.
	 *
	 * @param array  $graph             Schema graph nodes.
	 * @param string $defined_term_id   DefinedTerm @id.
	 * @return array
	 */
	public function attach_main_entity_to_graph( array $graph, $defined_term_id ) {
		$defined_term_id = trim( (string) $defined_term_id );
		if ( '' === $defined_term_id ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$type = isset( $node['@type'] ) ? $node['@type'] : '';
			if ( ! $this->is_schema_page_type( $type ) ) {
				continue;
			}

			$graph[ $index ]['mainEntity'] = $defined_term_id;
			return $graph;
		}

		return $graph;
	}

	/**
	 * Whether a schema @type is a page document type (WebPage or similar).
	 *
	 * @param mixed $type Schema @type string or list.
	 * @return bool
	 */
	public function is_schema_page_type( $type ) {
		$types      = is_array( $type ) ? $type : array( $type );
		$page_types = array(
			'WebPage',
			'CollectionPage',
			'ItemPage',
			'AboutPage',
			'ProfilePage',
			'ContactPage',
			'SearchResultsPage',
			'FAQPage',
			'QAPage',
		);

		foreach ( $types as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}
			if ( in_array( $candidate, $page_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove empty values from schema payload.
	 *
	 * @param array $schema Schema payload.
	 * @return array
	 */
	private function remove_empty_schema_properties( array $schema ) {
		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) && empty( $value ) ) {
				unset( $schema[ $key ] );
				continue;
			}

			if ( ! is_array( $value ) && '' === trim( (string) $value ) ) {
				unset( $schema[ $key ] );
			}
		}

		return $schema;
	}

	/**
	 * Check whether array has sequential integer keys.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private function is_sequential_array( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Normalize schema-compatible date values.
	 *
	 * @param string $date Candidate date.
	 * @return string
	 */
	private function sanitize_schema_date( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$parsed = \DateTime::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTime::getLastErrors();
		if (
			false === $parsed ||
			( is_array( $errors ) && ! empty( $errors['warning_count'] ) ) ||
			( is_array( $errors ) && ! empty( $errors['error_count'] ) )
		) {
			return '';
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Normalize schema URLs to valid public HTTP(S) URLs.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string
	 */
	private function sanitize_schema_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return '';
		}

		return $url;
	}
}
