<?php
/**
 * Template Tags for Handling Taxonomies
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-08-31
 */

namespace wpinc\taxo;

/**
 * Retrieves term name.
 *
 * @param \WP_Term $term     Term.
 * @param bool     $singular Whether to get singular name. Default false.
 * @return string Term name.
 */
function get_term_name( \WP_Term $term, bool $singular = false ): string {
	if ( $singular ) {
		$name_s = $term->singular_name;
		if ( ! empty( $name_s ) ) {
			return $name_s;
		}
	}
	return $term->name;
}


// -----------------------------------------------------------------------------


/**
 * Retrieves the term names.
 *
 * @param array<string, mixed>|string $args     Arguments for get_terms.
 * @param bool                        $singular Whether to get singular name. Default false.
 * @return string[] Array of term names on success.
 */
function get_term_names( $args, bool $singular = false ): array {
	if ( ! is_array( $args ) ) {
		$args = array( 'taxonomy' => $args );
	}
	$args['fields'] = 'all';

	$ts = get_terms( $args );
	if ( ! is_array( $ts ) ) {
		return array();
	}
	$ns = array();
	foreach ( $ts as $t ) {
		$ns[] = ( $t instanceof \WP_Term ) ? get_term_name( $t, $singular ) : '';
	}
	return $ns;
}

/**
 * Retrieves the names of the terms attached to the post.
 *
 * @param int|\WP_Post $post_id_obj Post ID or object.
 * @param string       $taxonomy    Taxonomy slug.
 * @param bool         $singular    Whether to get singular name. Default false.
 * @return string[] Array of term names on success.
 */
function get_the_term_names( $post_id_obj, string $taxonomy, bool $singular = false ): array {
	$ts = get_the_terms( $post_id_obj, $taxonomy );
	if ( ! is_array( $ts ) ) {
		return array();
	}
	return array_map(
		function ( $t ) use ( $singular ) {
			return get_term_name( $t, $singular );
		},
		$ts
	);
}


// -----------------------------------------------------------------------------


/**
 * Makes term list.
 *
 * @param array<string, mixed> $args_get_terms Arguments for get_terms.
 * @param array<string, mixed> $args {
 *     Arguments.
 *
 *     @type string     'before'       Content to prepend to the output. Default ''.
 *     @type string     'after'        Content to append to the output. Default ''.
 *     @type string     'separator'    Separator among the term tags. Default ''.
 *     @type \WP_Term   'current_term' Current term. Default queried object.
 *     @type bool       'do_add_link'  Whether to make items link.
 *     @type bool       'singular'     Whether to use singular label when available.
 *     @type callable   'filter'       Filter function for escaping for HTML.
 * }
 * @return string The term list.
 */
function get_term_list( array $args_get_terms, array $args ): string {
	$ts = get_terms( $args_get_terms );
	if ( ! is_array( $ts ) ) {
		return '';
	}
	$args += array(
		'before'       => '',
		'after'        => '',
		'separator'    => '',
		'current_term' => null,
		'do_add_link'  => false,
		'singular'     => false,
		'filter'       => 'esc_html',
		'terms'        => $ts,
	);
	if ( ! $args['current_term'] ) {
		global $wp_query;
		$qo = $wp_query->queried_object;
		if ( $qo instanceof \WP_Term || ( is_object( $qo ) && property_exists( $qo, 'term_id' ) ) ) {
			$args['current_term'] = $qo;
		}
	}
	$tags = make_term_list( $args );
	return $args['before'] . join( $args['separator'], $tags ) . $args['after'];
}

/**
 * Makes the term list.
 *
 * @param int|\WP_Post         $post_id_obj Post ID or object.
 * @param string               $taxonomy    Taxonomy slug.
 * @param array<string, mixed> $args {
 *     Arguments.
 *
 *     @type string     'before'       Content to prepend to the output. Default ''.
 *     @type string     'after'        Content to append to the output. Default ''.
 *     @type string     'separator'    Separator among the term tags. Default ''.
 *     @type \WP_Term   'current_term' Current term. Default queried object.
 *     @type bool       'do_add_link'  Whether to make items link.
 *     @type bool       'singular'     Whether to use singular label when available.
 *     @type callable   'filter'       Filter function for escaping for HTML.
 * }
 * @return string The term list.
 */
function get_the_term_list( $post_id_obj, string $taxonomy, array $args ): string {
	$ts = get_the_terms( $post_id_obj, $taxonomy );
	if ( ! is_array( $ts ) ) {
		return '';
	}
	$args += array(
		'before'         => '',
		'after'          => '',
		'separator'      => '',
		'current_term'   => null,
		'do_add_link'    => false,
		'singular'       => false,
		'filter'         => 'esc_html',
		'terms'          => $ts,
		'do_insert_root' => false,
	);
	if ( $args['do_insert_root'] ) {
		$args['terms'] = _insert_root( $ts );
	}
	$tags = make_term_list( $args );
	return $args['before'] . join( $args['separator'], $tags ) . $args['after'];
}

/**
 * Outputs the term list.
 *
 * @param int|\WP_Post         $post_id_obj Post ID or object.
 * @param string               $taxonomy    Taxonomy slug.
 * @param array<string, mixed> $args {
 *     Arguments.
 *
 *     @type string     'before'       Content to prepend to the output. Default ''.
 *     @type string     'after'        Content to append to the output. Default ''.
 *     @type string     'separator'    Separator among the term tags. Default ''.
 *     @type \WP_Term   'current_term' Current term. Default queried object.
 *     @type bool       'do_add_link'  Whether to make items link.
 *     @type bool       'singular'     Whether to use singular label when available.
 *     @type callable   'filter'       Filter function for escaping for HTML.
 * }
 */
function the_term_list( $post_id_obj, string $taxonomy, array $args ): void {
	echo wp_kses_post( get_the_term_list( $post_id_obj, $taxonomy, $args ) );
}

/**
 * Inserts root terms.
 *
 * @access private
 *
 * @param \WP_Term[] $terms Terms.
 * @return \WP_Term[] Terms.
 */
function _insert_root( array $terms ): array {
	$new_ts = array();
	$added  = array();
	foreach ( $terms as $t ) {
		if ( 0 !== $t->parent ) {
			$as = \wpinc\taxo\get_term_root( $t );
			if ( ! $as ) {
				continue;
			}
			$r = $as[0];
			if ( ! isset( $added[ $r->term_id ] ) ) {
				$new_ts[]             = $r;
				$added[ $r->term_id ] = true;
			}
		}
		$new_ts[]             = $t;
		$added[ $t->term_id ] = true;
	}
	return $new_ts;
}

/**
 * Makes term tag array.
 *
 * @param array<string, mixed> $args {
 *     Arguments.
 *
 *     @type \WP_Term[] 'terms'        Terms.
 *     @type \WP_Term   'current_term' Current term.
 *     @type bool       'do_add_link'  Whether to make items link.
 *     @type bool       'singular'     Whether to use singular label when available.
 *     @type callable   'filter'       Filter function for escaping for HTML.
 * }
 * @return string[] Array of term tags.
 */
function make_term_list( array $args = array() ): array {
	$args += array(
		'terms'        => array(),
		'current_term' => null,
		'do_add_link'  => false,
		'singular'     => false,
		'filter'       => 'esc_html',
	);
	$links = array();
	foreach ( $args['terms'] as $t ) {
		$cs = array( "$t->taxonomy-{$t->slug}" );
		if ( 0 === $t->parent ) {
			$cs[] = 'root';
		}
		if ( 0 === $t->count ) {
			$cs[] = 'empty';
		}
		if ( $args['current_term'] && $args['current_term']->term_id === $t->term_id ) {
			$cs[] = 'current';
		}
		$cls = 'class="' . implode( ' ', $cs ) . '"';

		$name = $args['filter']( get_term_name( $t, $args['singular'] ) );
		if ( $args['do_add_link'] ) {
			$url = get_term_link( $t );
			if ( ! is_wp_error( $url ) ) {
				$links[] = '<a href="' . esc_url( $url ) . "\" rel=\"tag\" $cls>$name</a>";
			}
		} else {
			$links[] = "<span $cls>$name</span>";
		}
	}
	if ( is_array( $args['terms'] ) && ! empty( $args['terms'] ) && $args['terms'][0] instanceof \WP_Term ) {
		$links = apply_filters( "term_links-{$args['terms'][0]->taxonomy}", $links );  // phpcs:ignore
	}
	return $links;
}
