<?php
/**
 * Classic widget for the CAT term panel.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic widget that prints the shared CAT term panel on term singles.
 */
class Cat_Term_Panel_Widget extends \WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			Cat_Term_Panel::WIDGET_ID_BASE,
			__( 'CAT Term Panel', 'context-authority-toolkit' ),
			array(
				'description' => __( 'Shows glossary term metadata (aliases, related, authority links, sources, cite this) on term singles.', 'context-authority-toolkit' ),
			)
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		unset( $instance );

		if ( ! Cat_Term_Panel::is_enabled() || ! is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			return;
		}

		$term_id = (int) get_queried_object_id();
		if ( $term_id <= 0 ) {
			return;
		}

		$html = Cat_Term_Panel::render_panel_for_term( $term_id );
		if ( '' === $html || Cat_Term_Panel::was_panel_printed() ) {
			return;
		}

		if ( isset( $args['before_widget'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-provided widget chrome.
			echo $args['before_widget'];
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_panel_html escapes fragments.
		echo $html;
		Cat_Term_Panel::mark_panel_printed();

		if ( isset( $args['after_widget'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-provided widget chrome.
			echo $args['after_widget'];
		}
	}

	/**
	 * Back-end widget form (no settings beyond description).
	 *
	 * @param array $instance Current settings.
	 * @return void
	 */
	public function form( $instance ) {
		unset( $instance );
		echo '<p>' . esc_html__( 'Displays the CAT term panel on glossary term singles. Section visibility is controlled in the Customizer (CAT Term Panel).', 'context-authority-toolkit' ) . '</p>';
	}

	/**
	 * Sanitize widget form values.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		unset( $new_instance );
		return is_array( $old_instance ) ? $old_instance : array();
	}
}
