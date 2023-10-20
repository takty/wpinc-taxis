<?php
/**
 * Custom Taxonomy
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-10-20
 */

namespace wpinc\taxo;

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
			if ( is_array( $args ) ) {
				$args['checked_ontop'] = false;
			}
			return $args;
		}
	);
}

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
			function ( array $columns ): array {
				unset( $columns['description'] );
				return $columns;
			}
		);
		add_filter(
			"manage_edit-{$tx}_sortable_columns",
			function ( array $sortable ): array {
				unset( $sortable['description'] );
				return $sortable;
			}
		);
	}
}


// -----------------------------------------------------------------------------


/**
 * Sets taxonomies to be specific to the post type.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 * @param string          $post_type  Post type.
 */
function set_taxonomy_post_type_specific( $taxonomy_s, string $post_type ): void {
	if ( is_admin() ) {
		return;
	}
	$txs = (array) $taxonomy_s;
	add_action(
		'pre_get_posts',
		function ( $query ) use ( $txs, $post_type ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}
			foreach ( $txs as $tx ) {
				if ( $query->is_tax( $tx ) ) {
					$query->set( 'post_type', $post_type );
					break;
				}
			}
		},
		9
	);
}

/**
 * Sets a default term to a taxonomy.
 *
 * @param string               $taxonomy          A taxonomy slug.
 * @param string               $default_term_slug Default term slug.
 * @param string|string[]|null $post_type_s       (Optional) A post type or array of post types.
 */
function set_taxonomy_default_term( string $taxonomy, string $default_term_slug, $post_type_s = null ): void {
	if ( ! is_admin() ) {
		return;
	}
	if ( $post_type_s ) {
		$pts = is_array( $post_type_s ) ? $post_type_s : array( $post_type_s );
		foreach ( $pts as $pt ) {
			add_action(
				"save_post_$pt",
				function ( int $post_id ) use ( $taxonomy, $default_term_slug ) {
					_cb_save_post__set_taxonomy_default_term( $post_id, $taxonomy, $default_term_slug );
				},
				10
			);
		}
	} else {
		add_action(
			'save_post',
			function ( int $post_id ) use ( $taxonomy, $default_term_slug ) {
				_cb_save_post__set_taxonomy_default_term( $post_id, $taxonomy, $default_term_slug );
			},
			10
		);
	}
}

/**
 * Callback function for 'save_post' action.
 *
 * @access private
 *
 * @param int    $post_id           Post ID.
 * @param string $taxonomy          Taxonomy slug.
 * @param string $default_term_slug Default term slug.
 */
function _cb_save_post__set_taxonomy_default_term( int $post_id, string $taxonomy, string $default_term_slug ): void {
	$ts = wp_get_object_terms( $post_id, $taxonomy );
	if ( is_array( $ts ) && empty( $ts ) ) {
		wp_set_object_terms( $post_id, $default_term_slug, $taxonomy );
	}
}
