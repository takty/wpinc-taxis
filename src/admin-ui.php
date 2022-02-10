<?php
/**
 * Custom UI
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-10
 */

namespace wpinc\taxo;

/**
 * Simplifies taxonomy metaboxes.
 *
 * @param string[] $taxonomies (Optional) Taxonomy slugs.
 * @param string[] $post_types (Optional) Post types.
 */
function simplify_taxonomy_metabox( array $taxonomies = array(), array $post_types = array() ): void {
	if ( is_admin() ) {
		add_action(
			'admin_head',
			function () use ( $taxonomies, $post_types ) {
				_cb_admin_head__simplify_taxonomy_metabox( $taxonomies, $post_types );
			}
		);

		// For classic editor.
		add_action(
			'admin_init',
			function () use ( $taxonomies, $post_types ) {
				_cb_admin_init__simplify_taxonomy_metabox( $taxonomies, $post_types );
			}
		);
	}
	if ( _is_rest() ) {
		// For block editor.
		add_action(
			'rest_api_init',
			function () use ( $taxonomies ) {
				$tx_objs = _taxonomy_slugs_to_objects( $taxonomies );
				foreach ( $tx_objs as &$obj ) {
					$obj->hierarchical = true;
				}
			}
		);
	}
}

/**
 * Callback function for 'admin_head' action.
 *
 * @access private
 *
 * @param string[] $tx_slugs   Taxonomy slugs.
 * @param string[] $post_types Post types.
 */
function _cb_admin_head__simplify_taxonomy_metabox( array $tx_slugs, array $post_types ): void {
	global $pagenow, $post_type;

	if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		if ( empty( $post_types ) || in_array( $post_type, $post_types, true ) ) {
			$s = '';
			if ( empty( $tx_slugs ) ) {
				$s .= '.categorydiv div[id$="-adder"], .category-tabs{display:none;}';
				$s .= '.categorydiv div.tabs-panel{border:none;padding:0;}';
				$s .= '.categorychecklist{margin-top:4px;}';
			} else {
				foreach ( $tx_slugs as $tx ) {
					$s .= "#$tx-adder,#$tx-tabs{display:none;}";
					$s .= "#$tx-all{border:none;padding:0;}";
					$s .= "#{$tx}checklist{margin-top:4px;}";
				}
			}
			// For Gutenberg.
			$s .= '.editor-post-taxonomies__hierarchical-terms-add{display:none;}';
			echo wp_kses( '<style>' . $s . '</style>', array( 'style' => array() ) );
		}
	}
}

/**
 * Callback function for 'admin_init' action.
 *
 * @access private
 *
 * @param string[] $tx_slugs   Taxonomy slugs.
 * @param string[] $post_types Post types.
 */
function _cb_admin_init__simplify_taxonomy_metabox( array $tx_slugs, array $post_types ): void {
	global $pagenow, $post_type;

	if ( 'edit-tags.php' !== $pagenow && ( empty( $post_types ) || in_array( $post_type, $post_types, true ) ) ) {
		$tx_objs = _taxonomy_slugs_to_objects( $tx_slugs );
		foreach ( $tx_objs as &$obj ) {
			$obj->hierarchical = true;
			$obj->meta_box_cb  = 'post_categories_meta_box';
		}
	}
}

/**
 * Converts taxonomy slugs to objects.
 *
 * @access private
 *
 * @param string[] $tx_slugs   Taxonomy slugs.
 * @return \WP_Taxonomy[] Taxonomy objects.
 */
function _taxonomy_slugs_to_objects( array $tx_slugs ): array {
	global $wp_taxonomies;
	if ( empty( $tx_slugs ) ) {
		return $wp_taxonomies;
	}
	$ret = array();
	foreach ( $tx_slugs as $slug ) {
		$obj = get_taxonomy( $slug );
		if ( $obj ) {
			$ret[ $slug ] = $obj;
		}
	}
	return $ret;
}

/**
 * Checks whether the current request is a WP REST API request.
 *
 * @return bool True if the current request is a WP REST API request.
 */
function _is_rest() {
	$rest_url    = wp_parse_url( trailingslashit( rest_url() ) );
	$current_url = wp_parse_url( add_query_arg( array() ) );
	return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
}


// -----------------------------------------------------------------------------


/**
 * Disables sorting in taxonomy metaboxes.
 */
function disable_taxonomy_metabox_sorting(): void {
	if ( ! is_admin() ) {
		return;
	}
	add_filter(
		'wp_terms_checklist_args',
		function ( $args ) {
			$args['checked_ontop'] = false;
			return $args;
		}
	);
}


// -----------------------------------------------------------------------------


/**
 * Removes term description form from admin.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or an array of taxonomy slugs.
 */
function remove_term_description( $taxonomy_s ): void {
	if ( ! is_admin() ) {
		return;
	}
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	add_action(
		'admin_head',
		function () use ( $txs ) {
			global $current_screen;
			if ( strpos( $current_screen->id, 'edit-' ) !== 0 ) {
				return;
			}
			$id_tax = substr( $current_screen->id, 5 );
			if ( in_array( $id_tax, $txs, true ) ) {
				?>
				<script>jQuery(function($){$('.term-description-wrap').remove();});</script>
				<?php
			}
		},
		99
	);
	foreach ( $txs as $tx ) {
		add_filter(
			"manage_edit-{$tx}_columns",
			function ( $columns ) {
				unset( $columns['description'] );
				return $columns;
			}
		);
		add_filter(
			"manage_edit-{$tx}_sortable_columns",
			function ( $sortable ) {
				unset( $sortable['description'] );
				return $sortable;
			}
		);
	}
}
