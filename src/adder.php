<?php
/**
 * Term Adder
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-01-16
 */

namespace wpinc\taxo;

/**
 * Adds terms to a specific taxonomy.
 *
 * @param array $args {
 *     Arguments.
 *
 *     @type string     'taxonomy'        Taxonomy.
 *     @type array      'slug_to_label'   An array of slug to label. Default empty.
 *     @type bool       'do_force_update' Whether to force update. Default false.
 *     @type array|null 'meta' {
 *         (Optional) Term meta arguments.
 *
 *         @type string 'delimiter' Delimiter of meta values.
 *         @type array  'keys'      An array of term meta keys.
 *     }
 *     @type array|null 'orders'          An array of pairs of initial value and increment value.
 *     @type string     'order_key'       (Optional) Default '_menu_order'.
 * }
 */
function add_terms( array $args ): void {
	$args += array(
		'taxonomy'        => '',
		'slug_to_label'   => array(),
		'do_force_update' => false,
		'meta'            => null,
		'orders'          => null,
		'order_key'       => '_menu_order',
	);
	if ( $args['meta'] ) {
		$args['meta'] += array(
			'delimiter' => '|',
			'keys'      => array(),
		);
	}
	_add_terms( $args, $args['slug_to_label'] );
}

/**
 * Adds terms to a specific taxonomy recursively.
 *
 * @access private
 *
 * @param array $args          Arguments.
 * @param array $slug_to_label An array of slug to label.
 * @param int   $parent_id     Term ID of the parent term. Default 0.
 * @param int   $parent_idx    Index of the parent term. Default 0.
 * @param int   $depth         Depth of order. Default 0.
 */
function _add_terms( array $args, array $slug_to_label, int $parent_id = 0, int $parent_idx = 0, int $depth = 0 ): void {
	$cur_order = ( $args['orders'] ) ? array( 1, 1 ) : $args['orders'][ $depth ];

	list( $order_bgn, $order_inc ) = $cur_order;

	$idx = $parent_idx + $order_bgn;

	foreach ( $slug_to_label as $slug => $data ) {
		list( $l, $sl ) = is_array( $data ) ? $data : array( $data, null );

		$ret = _add_term_one( $args, $slug, $l, $parent_id, $idx );
		if ( $ret && ! is_wp_error( $ret ) && $sl ) {
			_add_terms( $args, $sl, $ret['term_id'], $idx, $depth + 1 );
		}
		$idx += $order_inc;
	}
}

/**
 * Adds a term to a specific taxonomy.
 *
 * @access private
 *
 * @param array  $args      Arguments.
 * @param string $slug      Slug.
 * @param string $label     Label.
 * @param int    $parent_id Term ID of the parent term.
 * @param int    $idx       Index.
 * @return array Return value of wp_insert_term or wp_update_term.
 */
function _add_term_one( array $args, string $slug, string $label, int $parent_id, int $idx ): array {
	$meta = $args['meta'];
	if ( $meta ) {
		$meta_keys = $meta['keys'];
		$meta_vals = explode( $meta['delimiter'], $l );
		$label     = array_shift( $meta_vals );
	}
	$t = get_term_by( 'slug', $slug, $args['taxonomy'] );

	$ret = false;
	if ( false === $t ) {
		$ret = wp_insert_term(
			$label,
			$args['taxonomy'],
			array(
				'slug'   => $slug,
				'parent' => $parent_id,
			)
		);
	} elseif ( $args['do_force_update'] ) {
		$ret = wp_update_term( $t->term_id, $args['taxonomy'], array( 'name' => $label ) );
	}
	if ( $ret && ! is_wp_error( $ret ) ) {
		if ( $args['order_key'] ) {
			update_term_meta( $ret['term_id'], $args['order_key'], $idx );
		}
		if ( $meta && 0 < count( $meta_vals ) ) {
			$count = min( count( $meta_keys ), count( $meta_vals ) );
			for ( $i = 0; $i < $count; ++$i ) {
				update_term_meta( $ret['term_id'], $meta_keys[ $i ], $meta_vals[ $i ] );
			}
		}
	}
	return $ret;
}
