<?php
/**
 * Template Tags for Handling Taxonomies
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2024-03-14
 */

declare(strict_types=1);

namespace wpinc\taxo;

require_once __DIR__ . '/utility.php';

/**
 * Retrieves term name.
 *
 * @psalm-suppress UndefinedMagicPropertyFetch
 *
 * @param \WP_Term $term     Term.
 * @param bool     $singular Whether to get singular name. Default false.
 * @return string Term name.
 */
function get_term_name( \WP_Term $term, bool $singular = false ): string {
	if ( $singular ) {
		$name_s = $term->singular_name ?? '';
		if ( is_string( $name_s ) && '' !== $name_s ) {  // Check for non-empty-string.
			return $name_s;
		}
	}
	return $term->name;
}


// -----------------------------------------------------------------------------


/**
 * Retrieves the term names.
 *
 * @psalm-suppress ArgumentTypeCoercion
 *
 * @param array<string, mixed>|string $args     Arguments for get_terms.
 * @param bool                        $singular Whether to get singular name. Default false.
 * @return string[] Array of term names on success.
 */
function get_term_names( $args, bool $singular = false ): array {
	if ( ! is_array( $args ) ) {
		$args = array( 'taxonomy' => $args );
	}
	/**
	 * Terms. This is determined by $args['fields'] being 'all'.
	 *
	 * @var \WP_Term[]|\WP_Error $ts
	 */
	$ts = get_terms( array( 'fields' => 'all' ) + $args );  // @phpstan-ignore-line
	if ( ! is_array( $ts ) ) {
		return array();
	}
	$ns = array();
	foreach ( $ts as $t ) {
		$ns[] = get_term_name( $t, $singular );
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


/** phpcs:ignore
 * Makes term list.
 *
 * @psalm-suppress ArgumentTypeCoercion
 *
 * @global \WP_Query $wp_query
 *
 * @param array<string, mixed> $args_get_terms Arguments for get_terms.
 * phpcs:ignore
 * @param array{
 *     before?        : string,
 *     after?         : string,
 *     separator?     : string,
 *     current_term?  : \WP_Term|null,
 *     do_add_link?   : bool,
 *     singular?      : bool,
 *     filter?        : callable,
 *     do_insert_root?: bool,
 * } $args Arguments.
 *
 * $args {
 *     Arguments.
 *
 *     @type string        'before'         Content to prepend to the output. Default ''.
 *     @type string        'after'          Content to append to the output. Default ''.
 *     @type string        'separator'      Separator among the term tags. Default ''.
 *     @type \WP_Term|null 'current_term'   Current term. Default queried object.
 *     @type bool          'do_add_link'    Whether to make items link. Default false.
 *     @type bool          'singular'       Whether to use singular label when available. Default false.
 *     @type callable      'filter'         Filter function for escaping for HTML. Default 'esc_html'.
 *     @type bool          'do_insert_root' Whether to insert root terms. Default false.
 * }
 * @return string The term list.
 */
function get_term_list( array $args_get_terms, array $args ): string {
	$args += array(
		'before'         => '',
		'after'          => '',
		'separator'      => '',
		'current_term'   => null,
		'do_add_link'    => false,
		'singular'       => false,
		'filter'         => 'esc_html',
		'do_insert_root' => false,
	);

	/**
	 * Terms. This is determined by $args_get_terms['fields'] being 'all'.
	 *
	 * @var \WP_Term[]|\WP_Error $ts
	 */
	$ts = get_terms( array( 'fields' => 'all' ) + $args_get_terms );  // @phpstan-ignore-line
	if ( ! is_array( $ts ) ) {
		return '';
	}
	if ( $args['do_insert_root'] ) {
		$ts = _insert_root( $ts );
	}
	global $wp_query;
	if ( ! $args['current_term'] ) {
		$qo = $wp_query->queried_object;
		if ( $qo instanceof \WP_Term ) {
			$args['current_term'] = $qo;
		} elseif ( is_object( $qo ) && property_exists( $qo, 'term_id' ) ) {
			$args['current_term'] = new \WP_Term( $qo );
		}
	}
	$tags = make_term_list( $ts, $args );
	return $args['before'] . join( $args['separator'], $tags ) . $args['after'];
}

/** phpcs:ignore
 * Makes the term list.
 *
 * @param int|\WP_Post $post_id_obj Post ID or object.
 * @param string       $taxonomy    Taxonomy slug.
 * phpcs:ignore
 * @param array{
 *     before?        : string,
 *     after?         : string,
 *     separator?     : string,
 *     current_term?  : \WP_Term|null,
 *     do_add_link?   : bool,
 *     singular?      : bool,
 *     filter?        : callable,
 *     do_insert_root?: bool,
 * } $args Arguments.
 *
 * $args {
 *     Arguments.
 *
 *     @type string        'before'         Content to prepend to the output. Default ''.
 *     @type string        'after'          Content to append to the output. Default ''.
 *     @type string        'separator'      Separator among the term tags. Default ''.
 *     @type \WP_Term|null 'current_term'   Current term. Default queried object.
 *     @type bool          'do_add_link'    Whether to make items link. Default false.
 *     @type bool          'singular'       Whether to use singular label when available. Default false.
 *     @type callable      'filter'         Filter function for escaping for HTML. Default 'esc_html'.
 *     @type bool          'do_insert_root' Whether to insert root terms. Default false.
 * }
 * @return string The term list.
 */
function get_the_term_list( $post_id_obj, string $taxonomy, array $args ): string {
	$args += array(
		'before'         => '',
		'after'          => '',
		'separator'      => '',
		'current_term'   => null,
		'do_add_link'    => false,
		'singular'       => false,
		'filter'         => 'esc_html',
		'do_insert_root' => false,
	);

	$ts = get_the_terms( $post_id_obj, $taxonomy );
	if ( ! is_array( $ts ) ) {
		return '';
	}
	if ( $args['do_insert_root'] ) {
		$ts = _insert_root( $ts );
	}
	$tags = make_term_list( $ts, $args );
	return $args['before'] . join( $args['separator'], $tags ) . $args['after'];
}

/** phpcs:ignore
 * Outputs the term list.
 *
 * @param int|\WP_Post $post_id_obj Post ID or object.
 * @param string       $taxonomy    Taxonomy slug.
 * phpcs:ignore
 * @param array{
 *     before?        : string,
 *     after?         : string,
 *     separator?     : string,
 *     current_term?  : \WP_Term,
 *     do_add_link?   : bool,
 *     singular?      : bool,
 *     filter?        : callable,
 *     do_insert_root?: bool,
 * } $args Arguments.
 *
 * $args {
 *     Arguments.
 *
 *     @type string     'before'         Content to prepend to the output. Default ''.
 *     @type string     'after'          Content to append to the output. Default ''.
 *     @type string     'separator'      Separator among the term tags. Default ''.
 *     @type \WP_Term   'current_term'   Current term. Default queried object.
 *     @type bool       'do_add_link'    Whether to make items link. Default false.
 *     @type bool       'singular'       Whether to use singular label when available. Default false.
 *     @type callable   'filter'         Filter function for escaping for HTML. Default 'esc_html'.
 *     @type bool       'do_insert_root' Whether to insert root terms. Default false.
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

/** phpcs:ignore
 * Makes term tag array.
 *
 * @param \WP_Term[] $terms Terms.
 * phpcs:ignore
 * @param array{
 *     current_term?: \WP_Term|null,
 *     do_add_link? : bool,
 *     singular?    : bool,
 *     filter?      : callable,
 * } $args Arguments.
 *
 * $args {
 *     Arguments.
 *
 *     @type \WP_Term|null 'current_term' Current term.
 *     @type bool          'do_add_link'  Whether to make items link.
 *     @type bool          'singular'     Whether to use singular label when available.
 *     @type callable      'filter'       Filter function for escaping for HTML.
 * }
 * @return string[] Array of term tags.
 */
function make_term_list( array $terms, array $args = array() ): array {
	$args += array(
		'current_term' => null,
		'do_add_link'  => false,
		'singular'     => false,
		'filter'       => 'esc_html',
	);
	$links = array();
	foreach ( $terms as $t ) {
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
	if ( ! empty( $terms ) ) {
		$links = apply_filters( "term_links-{$terms[0]->taxonomy}", $links );  // phpcs:ignore
	}
	return $links;
}
