<?php
/**
 * Custom Taxonomy
 *
 * @package Sample
 * @author Takuto Yanagida
 * @version 2022-02-22
 */

namespace sample {
	require_once __DIR__ . '/taxo/adder.php';
	require_once __DIR__ . '/taxo/customize.php';
	require_once __DIR__ . '/taxo/post.php';
	require_once __DIR__ . '/taxo/singular-name.php';
	require_once __DIR__ . '/taxo/template-tag.php';
	require_once __DIR__ . '/taxo/term-content.php';
	require_once __DIR__ . '/taxo/utility.php';

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
		\wpinc\taxo\add_terms( $args );
	}


	// -------------------------------------------------------------------------


	/**
	 * Disables sorting in taxonomy metaboxes.
	 */
	function disable_taxonomy_metabox_sorting(): void {
		\wpinc\taxo\disable_taxonomy_metabox_sorting();
	}

	/**
	 * Removes term description form from admin.
	 *
	 * @param string|string[] $taxonomy_s A taxonomy slug or an array of taxonomy slugs.
	 */
	function remove_term_description( $taxonomy_s ): void {
		\wpinc\taxo\remove_term_description( $taxonomy_s );
	}


	// -------------------------------------------------------------------------


	/**
	 * Sets taxonomies to be specific to the post type.
	 *
	 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
	 * @param string          $post_type  Post type.
	 */
	function set_taxonomy_post_type_specific( $taxonomy_s, string $post_type ): void {
		\wpinc\taxo\set_taxonomy_post_type_specific( $taxonomy_s, $post_type );
	}

	/**
	 * Sets a default term to a taxonomy.
	 *
	 * @param string               $taxonomy          A taxonomy slug.
	 * @param string               $default_term_slug Default term slug.
	 * @param string|string[]|null $post_type_s       (Optional) A post type or array of post types.
	 */
	function set_taxonomy_default_term( string $taxonomy, string $default_term_slug, $post_type_s = null ): void {
		\wpinc\taxo\set_taxonomy_default_term( $taxonomy, $default_term_slug, $post_type_s );
	}


	// -------------------------------------------------------------------------


	/**
	 * Enables 'taxonomy' and 'term' arguments of wp_get_archives.
	 */
	function limit_archive_links_by_terms(): void {
		\wpinc\taxo\limit_archive_links_by_terms();
	}

	/**
	 * Limits adjacent posts with multiple taxonomies.
	 *
	 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
	 * @param string          $post_type  Post type.
	 */
	function limit_adjacent_post_with_multiple_taxonomy( $taxonomy_s, string $post_type ): void {
		\wpinc\taxo\limit_adjacent_post_with_multiple_taxonomy( $taxonomy_s, $post_type );
	}

	/**
	 * Counts posts with the term.
	 *
	 * @param \WP_Term $term  Term.
	 * @param array    $posts An array of posts to be counted.
	 *
	 * @return array An array of term slugs to count.
	 */
	function count_post_with_term( \WP_Term $term, array $posts ): array {
		return \wpinc\taxo\count_post_with_term( $term, $posts );
	}


	// -------------------------------------------------------------------------


	/**
	 * Enables singular name for each terms.
	 *
	 * @param array $args {
	 *     Configuration arguments.
	 *
	 *     @type array  'taxonomies'        Array of taxonomy slugs.
	 *     @type string 'singular_name_key' Key of term metadata for default singular names. Default '_singular_name'.
	 * }
	 */
	function enable_singular_name( array $args ): void {
		\wpinc\taxo\enable_singular_name( $args );
	}


	// -------------------------------------------------------------------------


	/**
	 * Retrieves term name.
	 *
	 * @param \WP_Term $term     Term.
	 * @param bool     $singular Whether to get singular name. Default false.
	 * @return string Term name.
	 */
	function get_term_name( \WP_Term $term, bool $singular = false ): string {
		return \wpinc\taxo\get_term_name( $term, $singular );
	}

	/**
	 * Retrieves the term names.
	 *
	 * @param string|array $taxonomy Taxonomy slug or arguments for get_terms.
	 * @param bool         $singular Whether to get singular name. Default false.
	 * @return string[] Array of term names on success.
	 */
	function get_term_names( $taxonomy, bool $singular = false ): array {
		return \wpinc\taxo\get_term_names( $taxonomy, $singular );
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
		return \wpinc\taxo\get_the_term_names( $post_id_obj, $taxonomy, $singular );
	}

	/**
	 * Makes term list.
	 *
	 * @param array $args_get_terms Arguments for get_terms.
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type string     'before'       Content to prepend to the output. Default ''.
	 *     @type string     'after'        Content to append to the output. Default ''.
	 *     @type string     'separator'    Separator among the term tags. Default ''.
	 *     @type WP_Term    'current_term' Current term. Default queried object.
	 *     @type bool       'do_add_link'  Whether to make items link.
	 *     @type bool       'singular'     Whether to use singular label when available.
	 *     @type callable   'filter'       Filter function for escaping for HTML.
	 * }
	 * @return string The term list.
	 */
	function get_term_list( array $args_get_terms, array $args ): string {
		return \wpinc\taxo\get_term_list( $args_get_terms, $args );
	}

	/**
	 * Makes the term list.
	 *
	 * @param int|\WP_Post $post_id_obj Post ID or object.
	 * @param string       $taxonomy    Taxonomy slug.
	 * @param array        $args {
	 *     Arguments.
	 *
	 *     @type string     'before'       Content to prepend to the output. Default ''.
	 *     @type string     'after'        Content to append to the output. Default ''.
	 *     @type string     'separator'    Separator among the term tags. Default ''.
	 *     @type WP_Term    'current_term' Current term. Default queried object.
	 *     @type bool       'do_add_link'  Whether to make items link.
	 *     @type bool       'singular'     Whether to use singular label when available.
	 *     @type callable   'filter'       Filter function for escaping for HTML.
	 * }
	 * @return string The term list.
	 */
	function get_the_term_list( $post_id_obj, string $taxonomy, array $args ): string {
		return \wpinc\taxo\get_the_term_list( $post_id_obj, $taxonomy, $args );
	}

	/**
	 * Outputs the term list.
	 *
	 * @param int|\WP_Post $post_id_obj Post ID or object.
	 * @param string       $taxonomy    Taxonomy slug.
	 * @param array        $args {
	 *     Arguments.
	 *
	 *     @type string     'before'       Content to prepend to the output. Default ''.
	 *     @type string     'after'        Content to append to the output. Default ''.
	 *     @type string     'separator'    Separator among the term tags. Default ''.
	 *     @type WP_Term    'current_term' Current term. Default queried object.
	 *     @type bool       'do_add_link'  Whether to make items link.
	 *     @type bool       'singular'     Whether to use singular label when available.
	 *     @type callable   'filter'       Filter function for escaping for HTML.
	 * }
	 */
	function the_term_list( $post_id_obj, string $taxonomy, array $args ): void {
		\wpinc\taxo\the_term_list( $post_id_obj, $taxonomy, $args );
	}

	/**
	 * Makes term tag array.
	 *
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type \WP_Term[] 'terms'        Terms.
	 *     @type WP_Term    'current_term' Current term.
	 *     @type bool       'do_add_link'  Whether to make items link.
	 *     @type bool       'singular'     Whether to use singular label when available.
	 *     @type callable   'filter'       Filter function for escaping for HTML.
	 * }
	 * @return array Array of term tags.
	 */
	function make_term_list( array $args = array() ): array {
		return \wpinc\taxo\make_term_list( $args );
	}


	// -------------------------------------------------------------------------


	/**
	 * Adds term content field.
	 *
	 * @param string $taxonomy      Taxonomy.
	 * @param string $key           Term meta key.
	 * @param string $label_postfix The postfix shown after label 'Content'. Default empty.
	 * @param int    $priority      Priority of action '{$taxonomy}_edit_form_fields'. Default 10.
	 */
	function add_term_content_field( string $taxonomy, string $key, string $label_postfix = '', int $priority = 10 ): void {
		\wpinc\taxo\add_term_content_field( $taxonomy, $key, $label_postfix, $priority );
	}

	/**
	 * Retrieves term content.
	 *
	 * @param \WP_Term $term Term.
	 * @param string   $key  Term meta key.
	 * @return string the term content.
	 */
	function get_term_content( \WP_Term $term, string $key ): string {
		return \wpinc\taxo\get_term_content( $term, $key );
	}


	// -------------------------------------------------------------------------


	/**
	 * Creates a taxonomy object.
	 *
	 * @param array  $args      Arguments.
	 * @param string $post_type Post type.
	 * @param string $suffix    Suffix of taxonomy slug and rewrite slug.
	 * @param string $slug      (Optional) Prefix of rewrite slug. Default is $post_type.
	 */
	function register_post_type_specific_taxonomy( array $args, string $post_type, string $suffix = 'category', string $slug = null ): void {
		\wpinc\taxo\register_post_type_specific_taxonomy( $args, $post_type, $suffix, $slug );
	}

	/**
	 * Retrieves root term of term hierarchy.
	 *
	 * @param \WP_Term $term    Term.
	 * @param int      $count   (Optional) Size of retrieved array. Default 1.
	 * @param int      $root_id (Optional) Term ID regarded as the root. Default 0.
	 * @return array Array of terms. The first element is the root.
	 */
	function get_term_root( \WP_Term $term, int $count = 1, int $root_id = 0 ): array {
		return \wpinc\taxo\get_term_root( $term, $count, $root_id );
	}
}

namespace sample\exclusive_taxonomy {
	require_once __DIR__ . '/taxo/exclusive-taxonomy.php';

	/**
	 * Makes taxonomies exclusive.
	 *
	 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
	 */
	function add_taxonomy( $taxonomy_s ): void {
		\wpinc\taxo\exclusive_taxonomy\add_taxonomy( $taxonomy_s );
	}
}

namespace sample\ordered_term {
	require_once __DIR__ . '/taxo/ordered-term.php';

	/**
	 * Adds taxonomy.
	 *
	 * @param string|string[] $taxonomy_s A taxonomy slug or an array of taxonomy slugs.
	 */
	function add_taxonomy( $taxonomy_s ): void {
		\wpinc\taxo\ordered_term\add_taxonomy( $taxonomy_s );
	}

	/**
	 * Activate ordered terms.
	 *
	 * @param array $args {
	 *     (Optional) Configuration arguments.
	 *
	 *     @type string 'order_key' Key of term metadata for order. Default '_menu_order'.
	 * }
	 */
	function activate( array $args = array() ): void {
		\wpinc\taxo\ordered_term\activate( $args );
	}

	/**
	 * Retrieves term order.
	 *
	 * @param int|\WP_Term $term_id_obj Term object or term ID.
	 * @return int Order.
	 */
	function get_order( $term_id_obj ): int {
		return \wpinc\taxo\ordered_term\get_order( $term_id_obj );
	}

	/**
	 * Sorts terms.
	 *
	 * @param int[]|\WP_Term[] $terms_id_obj Array of WP_Terms or term_ids.
	 * @param string           $taxonomy     Taxonomy slug.
	 */
	function sort_terms( array $terms_id_obj, string $taxonomy ): array {
		return \wpinc\taxo\ordered_term\sort_terms( $terms_id_obj, $taxonomy );
	}

	/**
	 * Enables post term order.
	 *
	 * @param string|string[] $post_type_s A post type or array of post types.
	 */
	function enable_post_term_order( $post_type_s ): void {
		\wpinc\taxo\ordered_term\enable_post_term_order( $post_type_s );
	}

	/**
	 * Makes post meta key of post term order.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string Post meta key.
	 */
	function get_post_meta_key_of_post_term_order( string $taxonomy ): string {
		return \wpinc\taxo\ordered_term\get_post_meta_key_of_post_term_order( $taxonomy );
	}
}

namespace sample\simple_ui {
	require_once __DIR__ . '/taxo/simple-ui.php';

	/**
	 * Activates simple taxonomy UIs.
	 *
	 * @param string|string[] $taxonomy_s  (Optional) A taxonomy slug or array of taxonomy slugs.
	 */
	function activate( $taxonomy_s = array() ): void {
		\wpinc\taxo\simple_ui\activate( $taxonomy_s, $post_type_s );
	}
}
