<?php
/**
 * Utilities for Posts with Taxonomies
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-01-16
 */

namespace wpinc\taxo;

/**
 * Enables 'taxonomy' and 'term' arguments of wp_get_archives.
 */
function limit_archive_links_by_terms(): void {
	add_filter(
		'getarchives_join',
		function ( string $join, array $parsed_args ) use ( $post_type ) {
			if ( isset( $parsed_args['taxonomy'] ) && isset( $parsed_args['term'] ) ) {
				global $wpdb;
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
				if ( $t ) {
					global $wpdb;
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
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 * @param string          $post_type  Post type.
 */
function limit_adjacent_post_with_multiple_taxonomy( $taxonomy_s, string $post_type ): void {
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );

	foreach ( array( 'next', 'previous' ) as $adj ) {
		add_filter(
			"get_{$adj}_post_join",
			function ( $join, $in_same_term, $excluded_terms, $taxonomy, $post ) use ( $txs, $post_type ) {
				_cb_get_adjacent_post_join( $join, $in_same_term, $excluded_terms, $taxonomy, $post, $txs, $post_type );
			},
			10,
			5
		);
		add_filter(
			"get_{$adj}_post_where",
			function ( $where, $in_same_term, $excluded_terms, $taxonomy, $post ) use ( $txs, $post_type ) {
				_cb_get_adjacent_post_where( $join, $in_same_term, $excluded_terms, $taxonomy, $post, $txs, $post_type );
			},
			10,
			5
		);
	}
}

/**
 * Callback function for 'get_{$adjacent}_post_join' filter.
 *
 * @param string   $join             The JOIN clause in the SQL.
 * @param bool     $in_same_term     Whether post should be in a same taxonomy term.
 * @param array    $excluded_terms   Array of excluded term IDs.
 * @param string   $taxonomy         Taxonomy. Used to identify the term used when `$in_same_term` is true.
 * @param \WP_Post $post             WP_Post object.
 * @param array    $target_txs       Target taxonomies.
 * @param string   $target_post_type Target post type.
 */
function _cb_get_adjacent_post_join( string $join, bool $in_same_term, array $excluded_terms, string $taxonomy, \WP_Post $post, array $target_txs, string $target_post_type ): string {
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
 * @param array    $excluded_terms   Array of excluded term IDs.
 * @param string   $taxonomy         Taxonomy. Used to identify the term used when `$in_same_term` is true.
 * @param \WP_Post $post             WP_Post object.
 * @param array    $target_txs       Target taxonomies.
 * @param string   $target_post_type Target post type.
 */
function _cb_get_adjacent_post_where( string $where, bool $in_same_term, array $excluded_terms, string $taxonomy, \WP_Post $post, array $target_txs, string $target_post_type ): string {
	if ( ! $in_same_term || $post->post_type !== $target_post_type ) {
		return $where;
	}
	foreach ( $target_txs as $i => $tx ) {
		$tt_ids = wp_get_object_terms( $post->ID, $tx, array( 'fields' => 'tt_ids' ) );
		if ( $tt_ids && ! is_wp_error( $tt_ids ) ) {
			$where .= " AND tr_wpinc_taxo$i.term_taxonomy_id IN (" . implode( ',', $tt_ids ) . ')';
		}
	}
	return $where;
}


// -----------------------------------------------------------------------------


/**
 * Counts posts with the term.
 *
 * @param \WP_Term $term  Term.
 * @param array    $posts An array of posts to be counted.
 *
 * @return array An array of term slugs to count.
 */
function count_post_with_term( \WP_Term $term, array $posts ): array {
	$ts_pids = array();
	foreach ( $posts as $p ) {
		$slugs = wp_get_object_terms( $p->ID, $term->taxonomy, array( 'fields' => 'slugs' ) );
		foreach ( $slugs as $s ) {
			if ( ! isset( $ts_pids[ $s ] ) ) {
				$ts_pids[ $s ] = array();
			}
			$ts_pids[ $s ][ $p->ID ] = 1;
		}
	}
	$root = get_term_by( 'slug', $term->slug, $term->taxonomy );
	_count_post_with_child_term( $root, $ts_pids );

	$counts = array();
	foreach ( $ts_pids as $slug => $ids ) {
		$c = count( $ids );
		if ( $c > 0 ) {
			$counts[ $slug ] = $c;
		}
	}
	return $counts;
}

/**
 * Counts posts with child terms.
 *
 * @param \WP_Term $term    Term.
 * @param array    $ts_pids An array of term slug to post IDs.
 */
function _count_post_with_child_term( \WP_Term $term, array &$ts_pids ): void {
	$child = get_terms(
		$term->taxonomy,
		array(
			'hide_empty' => false,
			'parent'     => $term->term_id,
		)
	);

	$pids = array();
	foreach ( $child as $c ) {
		_count_post_with_child_term( $c, $ts_pids );
		if ( isset( $ts_pids[ $c->slug ] ) ) {
			$pids = array_merge( $pids, $ts_pids[ $c->slug ] );
		}
	}
	$ts_pids[ $term->slug ] = array_merge( $ts_pids[ $term->slug ] ?? array(), $pids );
}
