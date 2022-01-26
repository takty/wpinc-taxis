<?php
/**
 * Custom UI
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-01-26
 */

namespace wpinc\taxo;

/**
 * Simplifies taxonomy metaboxes.
 *
 * @param array $taxonomies (Optional) Taxonomies.
 * @param array $post_types (Optional) Post types.
 */
function simplify_taxonomy_metabox( array $taxonomies = array(), array $post_types = array() ): void {
	add_action(
		'admin_head',
		function () use ( $taxonomies, $post_types ) {
			_cb_admin_head__simplify_taxonomy_metabox( $taxonomies, $post_types );
		}
	);
}

/**
 * Callback function for 'admin_head' action.
 *
 * @access private
 *
 * @param array $taxonomies Taxonomies.
 * @param array $post_types Post types.
 */
function _cb_admin_head__simplify_taxonomy_metabox( array $taxonomies, array $post_types ): void {
	global $pagenow, $post_type;

	if ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) {
		if ( empty( $post_types ) || in_array( $post_type, $post_types, true ) ) {
			$s = '';
			if ( empty( $taxonomies ) ) {
				$s .= '.categorydiv div[id$="-adder"], .category-tabs{display:none;}';
				$s .= '.categorydiv div.tabs-panel{border:none;padding:0;}';
				$s .= '.categorychecklist{margin-top:4px;}';
			} else {
				foreach ( $taxonomies as $tx ) {
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
 * Disables sorting in taxonomy metaboxes.
 */
function disable_taxonomy_metabox_sorting(): void {
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
