<?php
/**
 * Term Adder
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2024-03-12
 */

declare(strict_types=1);

namespace wpinc\taxo;

/** phpcs:ignore
 * Adds terms to a specific taxonomy.
 *
 * phpcs:ignore
 * @param array{
 *     taxonomy        : string,
 *     slug_to_label?  : array<string, string|array{string, array<string, mixed>}>,
 *     do_force_update?: bool,
 *     meta?           : array{ delimiter: non-empty-string, keys: string[] }|null,
 *     orders?         : array|null,
 *     order_key?      : string,
 * } $args Arguments.
 *
 * $args {
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
// phpcs:ignore
function add_terms( array $args ): void {  // @phpstan-ignore-line
	$args += array(
		'taxonomy'        => '',
		'slug_to_label'   => array(),
		'do_force_update' => false,
		'meta'            => null,
		'orders'          => null,
		'order_key'       => '_menu_order',
	);
	if ( ! $args['meta'] ) {
		$args['meta'] = array();
	}
	$args['meta'] += array(
		'delimiter' => '|',
		'keys'      => array(),
	);
	/** @psalm-suppress InvalidArgument */  // phpcs:ignore
	_add_terms( $args, $args['slug_to_label'] );
}

/** phpcs:ignore
 * Adds terms to a specific taxonomy recursively.
 *
 * @access private
 * phpcs:ignore
 * @param array{
 *     taxonomy       : string,
 *     do_force_update: bool,
 *     meta           : array{ delimiter: non-empty-string, keys: string[] },
 *     orders         : array|null,
 *     order_key      : string,
 * } $args Arguments.
 * @param array<string, string|array{string, array<string, mixed>}> $slug_to_label An array of slug to label.
 * @param int                                                       $parent_id     Term ID of the parent term. Default 0.
 * @param int                                                       $parent_idx    Index of the parent term. Default 0.
 * @param int                                                       $depth         Depth of order. Default 0.
 */
// phpcs:ignore
function _add_terms( array $args, array $slug_to_label, int $parent_id = 0, int $parent_idx = 0, int $depth = 0 ): void {  // @phpstan-ignore-line
	$cur_order = is_array( $args['orders'] ) ? $args['orders'][ $depth ] : array( 1, 1 );

	list( $order_bgn, $order_inc ) = $cur_order;

	$idx = $parent_idx + $order_bgn;

	foreach ( $slug_to_label as $slug => $data ) {
		list( $l, $sl ) = is_array( $data ) ? $data : array( $data, null );
		/** @psalm-suppress InvalidArgument */  // phpcs:ignore
		$term_id = _add_term_one( $args, $slug, $l, $parent_id, $idx );
		if ( is_int( $term_id ) && is_array( $sl ) ) {
			_add_terms( $args, $sl, $term_id, $idx, $depth + 1 );  // @phpstan-ignore-line
		}
		$idx += $order_inc;
	}
}

/** phpcs:ignore
 * Adds a term to a specific taxonomy.
 *
 * @access private
 * phpcs:ignore
 * @param array{
 *     taxonomy       : string,
 *     do_force_update: bool,
 *     meta           : array{ delimiter: non-empty-string, keys: string[] }|null,
 *     order_key      : string,
 * } $args Arguments.
 * @param string $slug      Slug.
 * @param string $label     Label.
 * @param int    $parent_id Term ID of the parent term.
 * @param int    $idx       Index.
 * @return int|null The term ID of inserted or updated term or null.
 */
function _add_term_one( array $args, string $slug, string $label, int $parent_id, int $idx ): ?int {
	$meta = $args['meta'];
	if ( $meta ) {
		$meta_keys = $meta['keys'];
		$meta_vals = explode( $meta['delimiter'], $label );
		/** @psalm-suppress RedundantConditionGivenDocblockType */  // phpcs:ignore
		if ( is_array( $meta_vals ) ) {  // For PHP 7.
			$label = array_shift( $meta_vals );
		}
	}
	$t = get_term_by( 'slug', $slug, $args['taxonomy'] );

	$ret = null;
	if ( false === $t ) {
		$ret = wp_insert_term(
			$label,
			$args['taxonomy'],
			array(
				'slug'   => $slug,
				'parent' => $parent_id,
			)
		);
	} elseif ( $t instanceof \WP_Term && $args['do_force_update'] ) {
		/** @psalm-suppress InvalidArgument */  // phpcs:ignore
		$ret = wp_update_term( $t->term_id, $args['taxonomy'], array( 'name' => $label ) );
	}
	if ( is_wp_error( $ret ) ) {
		$ret = null;
	}
	if ( is_array( $ret ) ) {
		if ( $args['order_key'] ) {
			update_term_meta( $ret['term_id'], $args['order_key'], $idx );
		}
		if ( $meta ) {
			/**
			 * When (bool) $meta is true, $meta_vals and $meta_vals are not null.
			 *
			 * @psalm-suppress PossiblyUndefinedVariable
			 */
			if ( is_array( $meta_vals ) && 0 < count( $meta_vals ) ) {
				$count = min( count( $meta_keys ), count( $meta_vals ) );
				for ( $i = 0; $i < $count; ++$i ) {
					update_term_meta( $ret['term_id'], $meta_keys[ $i ], $meta_vals[ $i ] );
				}
			}
		}
	}
	return is_array( $ret ) ? $ret['term_id'] : null;
}
