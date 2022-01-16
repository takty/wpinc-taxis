<?php
/**
 * Utilities
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-01-16
 */

namespace wpinc\taxo;

/**
 * Creates a taxonomy object.
 *
 * @param array  $args      Arguments.
 * @param string $post_type Post type.
 * @param string $suffix    Suffix of taxonomy slug and rewrite slug.
 * @param string $slug      (Optional) Prefix of rewrite slug. Default is $post_type.
 */
function register_post_type_specific_taxonomy( array $args, string $post_type, string $suffix = 'category', string $slug = null ): void {
	$slug  = $slug ?? $post_type;
	$args += array(
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array(),
	);

	$args['rewrite'] += array(
		'with_front' => false,
		'slug'       => "$slug/$suffix",
	);
	register_taxonomy( "{$post_type}_$suffix", $post_type, $args );
	set_taxonomy_post_type_specific( array( "{$post_type}_$suffix" ), $post_type );
}


// -----------------------------------------------------------------------------


/**
 * Retrieves root term of term hierarchy.
 *
 * @param \WP_Term $term    Term.
 * @param int      $count   (Optional) Size of retrieved array. Default 1.
 * @param int      $root_id (Optional) Term ID regarded as the root. Default 0.
 * @return array Array of terms. The first element is the root.
 */
function get_term_root( \WP_Term $term, int $count = 1, int $root_id = 0 ): array {
	$as = get_ancestors( $term->term_id, $term->taxonomy );

	$end = count( $as );
	foreach ( $as as $idx => $a ) {
		if ( $root_id === $a ) {
			$end = $idx;
			break;
		}
	}
	$as     = array_reverse( array_slice( $as, 0, $end ) );  // The first is root.
	$as_sub = array_slice( $as, 0, $count );
	return array_map(
		function ( $a ) use ( $term ) {
			return get_term( $a, $term->taxonomy );
		},
		$as_sub
	);
}
