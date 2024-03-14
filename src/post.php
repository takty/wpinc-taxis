<?php
/**
 * Utilities for Posts with Taxonomies
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2024-03-13
 */

declare(strict_types=1);

namespace wpinc\taxo;

/**
 * Adds term ancestors as class names to post class.
 */
function add_term_ancestors_to_post_class(): void {
	add_filter(
		'post_class',
		function ( array $classes, array $cls, int $post_id ) {
			$txs = get_taxonomies( array( 'public' => true ) );
			$txs = apply_filters( 'post_class_taxonomies', $txs, $post_id, $classes, $cls );

			$cs = array();
			foreach ( $txs as $tx ) {
				$pt = get_post_type( $post_id );
				if ( is_string( $pt ) && is_object_in_taxonomy( $pt, $tx ) ) {
					$as = array();
					$ts = get_the_terms( $post_id, $tx );
					if ( is_array( $ts ) ) {
						foreach ( $ts as $t ) {
							$as += get_ancestors( $t->term_id, $tx );
						}
					}
					foreach ( $as as $a ) {
						$t = get_term( $a );
						if ( ! ( $t instanceof \WP_Term ) || '' === $t->slug ) {
							continue;
						}

						$term_class = sanitize_html_class( $t->slug, (string) $t->term_id );
						if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
							$term_class = $t->term_id;
						}

						if ( 'post_tag' === $tx ) {
							$cs[] = 'tag-' . $term_class;
						} else {
							$cs[] = sanitize_html_class( $tx . '-' . $term_class, $tx . '-' . $t->term_id );
						}
					}
				}
			}
			return array_merge( $classes, array_map( 'esc_attr', $cs ) );
		},
		10,
		3
	);
}


// -----------------------------------------------------------------------------


/**
 * Enables 'taxonomy' and 'term' arguments of wp_get_archives.
 *
 * @global \wpdb $wpdb
 */
function limit_archive_links_by_terms(): void {
	add_filter(
		'getarchives_join',
		function ( string $join, array $parsed_args ) {
			global $wpdb;
			if ( isset( $parsed_args['taxonomy'] ) && isset( $parsed_args['term'] ) ) {
				$join .= " INNER JOIN $wpdb->term_relationships AS tr_wpinc_taxo ON (p.ID = tr_wpinc_taxo.object_id)";
			}
			return $join;
		},
		10,
		2
	);
	add_filter(
		'getarchives_where',
		function ( string $where, array $parsed_args ) {
			if ( isset( $parsed_args['taxonomy'] ) && isset( $parsed_args['term'] ) ) {
				$t = get_term_by( 'slug', $parsed_args['term'], $parsed_args['taxonomy'] );
				if ( $t instanceof \WP_Term ) {
					$where .= " AND tr_wpinc_taxo.term_taxonomy_id = {$t->term_taxonomy_id}";
				}
			}
			return $where;
		},
		10,
		2
	);
}


// -----------------------------------------------------------------------------


/**
 * Limits adjacent posts with multiple taxonomies.
 *
 * @psalm-suppress MissingClosureParamType
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 * @param string          $post_type  Post type.
 */
function limit_adjacent_post_with_multiple_taxonomy( $taxonomy_s, string $post_type ): void {
	$txs = (array) $taxonomy_s;

	foreach ( array( 'next', 'previous' ) as $adj ) {
		add_filter(
			"get_{$adj}_post_join",
			function ( string $join, bool $in_same_term, $_excluded_terms, string $_taxonomy, \WP_Post $post ) use ( $txs, $post_type ) {
				return _cb_get_adjacent_post_join( $join, $in_same_term, $post, $txs, $post_type );
			},
			10,
			5
		);
		add_filter(
			"get_{$adj}_post_where",
			function ( string $where, bool $in_same_term, $_excluded_terms, string $_taxonomy, \WP_Post $post ) use ( $txs, $post_type ) {
				return _cb_get_adjacent_post_where( $where, $in_same_term, $post, $txs, $post_type );
			},
			10,
			5
		);
	}
}

/**
 * Callback function for 'get_{$adjacent}_post_join' filter.
 *
 * @global \wpdb $wpdb
 *
 * @param string   $join             The JOIN clause in the SQL.
 * @param bool     $in_same_term     Whether post should be in a same taxonomy term.
 * @param \WP_Post $post             WP_Post object.
 * @param string[] $target_txs       Target taxonomies.
 * @param string   $target_post_type Target post type.
 */
function _cb_get_adjacent_post_join( string $join, bool $in_same_term, \WP_Post $post, array $target_txs, string $target_post_type ): string {
	if ( ! $in_same_term || $post->post_type !== $target_post_type ) {
		return $join;
	}
	global $wpdb;
	$count = count( $target_txs );
	for ( $i = 0; $i < $count; ++$i ) {
		$join .= " INNER JOIN $wpdb->term_relationships AS tr_wpinc_taxo$i ON (p.ID = tr_wpinc_taxo$i.object_id)";
	}
	return $join;
}

/**
 * Callback function for 'get_{$adjacent}_post_where' filter.
 *
 * @param string   $where            The JOIN clause in the SQL.
 * @param bool     $in_same_term     Whether post should be in a same taxonomy term.
 * @param \WP_Post $post             WP_Post object.
 * @param string[] $target_txs       Target taxonomies.
 * @param string   $target_post_type Target post type.
 */
function _cb_get_adjacent_post_where( string $where, bool $in_same_term, \WP_Post $post, array $target_txs, string $target_post_type ): string {
	if ( ! $in_same_term || $post->post_type !== $target_post_type ) {
		return $where;
	}
	foreach ( $target_txs as $i => $tx ) {
		/**
		 *  Term taxonomy IDs. This is determined by $args['fields'] being 'tt_ids'.
		 *
		 * @var int[]|\WP_Error $tt_ids
		 */
		$tt_ids = wp_get_object_terms( $post->ID, $tx, array( 'fields' => 'tt_ids' ) );
		if ( is_array( $tt_ids ) ) {
			$where .= " AND tr_wpinc_taxo$i.term_taxonomy_id IN (" . implode( ',', $tt_ids ) . ')';
		}
	}
	return $where;
}


// -----------------------------------------------------------------------------


/**
 * Counts posts with the term.
 *
 * @param \WP_Term   $term  Term.
 * @param \WP_Post[] $posts An array of posts to be counted.
 * @return array<string, int> An array of term slugs to count.
 */
function count_post_with_term( \WP_Term $term, array $posts ): array {
	$ts_pids = array();
	foreach ( $posts as $p ) {
		/**
		 * Slugs. This is determined by $args['fields'] being 'slugs'.
		 *
		 * @var string[]|\WP_Error $slugs
		 */
		$slugs = wp_get_object_terms( $p->ID, $term->taxonomy, array( 'fields' => 'slugs' ) );
		if ( is_array( $slugs ) ) {
			foreach ( $slugs as $s ) {
				if ( ! isset( $ts_pids[ $s ] ) ) {
					$ts_pids[ $s ] = array();
				}
				$ts_pids[ $s ][ $p->ID ] = 1;
			}
		}
	}
	$root = get_term_by( 'slug', $term->slug, $term->taxonomy );
	if ( $root instanceof \WP_Term ) {
		_count_post_with_child_term( $root, $ts_pids );
	}

	$counts = array();
	foreach ( $ts_pids as $slug => $ids ) {
		$c = count( $ids );
		if ( $c > 0 ) {
			$counts[ $slug ] = $c;
		}
	}
	return $counts;  // @phpstan-ignore-line
}

/**
 * Counts posts with child terms.
 *
 * @param \WP_Term                     $term    Term.
 * @param array<string, array<int, 1>> $ts_pids An array of term slug to post IDs.
 */
function _count_post_with_child_term( \WP_Term $term, array &$ts_pids ): void {
	/**
	 * Terms. This is determined by $args['fields'] being 'all'.
	 *
	 * @var \WP_Term[]|\WP_Error $child
	 */
	$child = get_terms(
		array(
			'taxonomy'   => $term->taxonomy,
			'hide_empty' => false,
			'parent'     => $term->term_id,
		)
	);
	if ( ! is_array( $child ) ) {
		return;
	}
	$pids = array();
	foreach ( $child as $c ) {
		_count_post_with_child_term( $c, $ts_pids );
		if ( isset( $ts_pids[ $c->slug ] ) ) {
			$pids = array_merge( $pids, $ts_pids[ $c->slug ] );
		}
	}
	$ts_pids[ $term->slug ] = array_merge( $ts_pids[ $term->slug ] ?? array(), $pids );
}
