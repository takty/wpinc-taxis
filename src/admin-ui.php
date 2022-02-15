<?php
/**
 * Custom UI
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-15
 */

namespace wpinc\taxo;

/**
 * Simplifies taxonomy metaboxes.
 *
 * @param string[] $taxonomies (Optional) Taxonomy slugs.
 * @param string[] $post_types (Optional) Post types.
 */
function simplify_taxonomy_metabox( array $taxonomies = array(), array $post_types = array() ): void {
	global $pagenow, $post_type;

	if ( is_admin() ) {
		// Remove UI elements from metabox for both classic editor and block editor.
		if (
			( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) &&
			( empty( $post_types ) || in_array( $post_type, $post_types, true ) )
		) {
			add_action(
				'admin_head',
				function () use ( $taxonomies ) {
					_cb_admin_head__simplify_taxonomy_metabox( $taxonomies );
				}
			);
		}

		// Change term selection UI from textarea to checkboxes for classic editor and list.
		if (
			( 'edit.php' === $pagenow || 'post-new.php' === $pagenow || 'post.php' === $pagenow ) &&
			( empty( $post_types ) || in_array( $post_type, $post_types, true ) )
		) {
			add_action(
				'admin_init',
				function () use ( $taxonomies ) {
					_cb_admin_init__simplify_taxonomy_metabox( $taxonomies );
				}
			);
		}
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
 * @param string[] $tx_slugs Taxonomy slugs.
 */
function _cb_admin_head__simplify_taxonomy_metabox( array $tx_slugs ): void {
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
	// For block editor.
	$s .= '.editor-post-taxonomies__hierarchical-terms-add{display:none;}';
	echo wp_kses( '<style>' . $s . '</style>', array( 'style' => array() ) );
}

/**
 * Callback function for 'admin_init' action.
 *
 * @access private
 *
 * @param string[] $tx_slugs Taxonomy slugs.
 */
function _cb_admin_init__simplify_taxonomy_metabox( array $tx_slugs ): void {
	$tx_objs = _taxonomy_slugs_to_objects( $tx_slugs );
	foreach ( $tx_objs as &$obj ) {
		$obj->hierarchical = true;
		$obj->meta_box_cb  = 'post_categories_meta_box';
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
 * Disables sorting in taxonomy metaboxes for classic editor.
 */
function disable_taxonomy_metabox_sorting(): void {
	global $pagenow;
	if ( ! is_admin() || ( 'post-new.php' !== $pagenow && 'post.php' !== $pagenow ) ) {
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
	global $pagenow;
	if ( ! is_admin() ) {
		return;
	}
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
		add_action(
			'admin_head',
			function () use ( $txs ) {
				global $taxonomy;
				if ( in_array( $taxonomy, $txs, true ) ) {
					?>
					<script>jQuery(function ($) { $('.term-description-wrap').remove(); });</script>
					<?php
				}
			},
			99
		);
	}
	// The below is called when both $pagenow is 'edit-tags.php' and AJAX call.
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
