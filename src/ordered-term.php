<?php
/**
 * Ordered Term (Adding Order Field (Term Meta) to Taxonomies)
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-09-01
 */

namespace wpinc\taxo\ordered_term;

/**
 * Adds taxonomy.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or an array of taxonomy slugs.
 */
function add_taxonomy( $taxonomy_s ): void {
	$txs  = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	$inst = _get_instance();

	$inst->txs = array_merge( $inst->txs, $txs );

	if ( $inst->is_activated && is_admin() ) {
		foreach ( $txs as $tx ) {
			_add_hook_for_specific_taxonomy( $tx );
		}
	}
}

/**
 * Activate ordered terms.
 *
 * @param array<string, mixed> $args {
 *     (Optional) Configuration arguments.
 *
 *     @type string 'order_key' Key of term metadata for order. Default '_menu_order'.
 * }
 */
function activate( array $args = array() ): void {
	$inst = _get_instance();
	if ( $inst->is_activated ) {
		return;
	}
	$inst->is_activated = true;

	$args += array( 'order_key' => '_menu_order' );

	$inst->key_order = $args['order_key'];

	if ( is_admin() ) {
		foreach ( $inst->txs as $tx ) {
			_add_hook_for_specific_taxonomy( $tx );
		}
		global $pagenow;
		if ( 'edit-tags.php' === $pagenow ) {
			add_action( 'admin_head', '\wpinc\taxo\ordered_term\_cb_admin_head' );
			add_action( 'quick_edit_custom_box', '\wpinc\taxo\ordered_term\_cb_quick_edit_custom_box', 10, 3 );
		}
	}
	add_filter( 'terms_clauses', '\wpinc\taxo\ordered_term\_cb_terms_clauses', 10, 3 );
	add_filter( 'get_the_terms', '\wpinc\taxo\ordered_term\_cb_get_the_terms', 10, 3 );
}

/**
 * Add hooks for the specific taxonomy.
 *
 * @access private
 *
 * @param string $tx A taxonomy slug.
 */
function _add_hook_for_specific_taxonomy( string $tx ): void {
	add_filter( "manage_edit-{$tx}_columns", '\wpinc\taxo\ordered_term\_cb_manage_edit_taxonomy_columns' );
	add_filter( "manage_edit-{$tx}_sortable_columns", '\wpinc\taxo\ordered_term\_cb_manage_edit_taxonomy_sortable_columns' );
	add_filter( "manage_{$tx}_custom_column", '\wpinc\taxo\ordered_term\_cb_manage_taxonomy_custom_column', 10, 3 );
	add_action( "{$tx}_edit_form_fields", '\wpinc\taxo\ordered_term\_cb_taxonomy_edit_form_fields' );
	add_action( "edited_{$tx}", '\wpinc\taxo\ordered_term\_cb_edited_taxonomy', 10, 2 );
}

/**
 * Retrieves term order.
 *
 * @param int|\WP_Term $term_id_obj Term object or term ID.
 * @return int Order.
 */
function get_order( $term_id_obj ): int {
	$inst    = _get_instance();
	$term_id = is_numeric( $term_id_obj ) ? $term_id_obj : $term_id_obj->term_id;
	return (int) get_term_meta( $term_id, $inst->key_order, true );
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'manage_{$this->screen->id}_columns' filter.
 *
 * @access private
 *
 * @param string[] $columns Sortable columns.
 * @return string[] Columns.
 */
function _cb_manage_edit_taxonomy_columns( array $columns ): array {
	$inst = _get_instance();

	$columns[ $inst->key_order ] = __( 'Order', 'default' );
	return $columns;
}

/**
 * Callback function for 'manage_{$this->screen->id}_sortable_columns' filter.
 *
 * @access private
 *
 * @param string[] $sortable_columns Sortable columns.
 * @return string[] Sortable columns.
 */
function _cb_manage_edit_taxonomy_sortable_columns( array $sortable_columns ): array {
	$inst = _get_instance();

	$sortable_columns[ $inst->key_order ] = $inst->key_order;
	return $sortable_columns;
}

/**
 * Callback function for 'manage_{$this->screen->taxonomy}_custom_column' filter.
 *
 * @access private
 *
 * @param string $string      Blank string.
 * @param string $column_name Name of the column.
 * @param int    $term_id     Term ID.
 * @return string String.
 */
function _cb_manage_taxonomy_custom_column( string $string, string $column_name, int $term_id ): string {
	$inst = _get_instance();
	if ( $column_name !== $inst->key_order ) {
		return $string;
	}
	$idx = get_term_meta( absint( $term_id ), $inst->key_order, true );
	if ( false !== $idx && '' !== $idx ) {  // DO NOT USE 'empty'.
		$string .= esc_html( $idx );
	}
	return $string;
}

/**
 * Callback function for '{$taxonomy}_edit_form_fields' action.
 *
 * @access private
 * @param \WP_Term $term Current taxonomy term object.
 */
function _cb_taxonomy_edit_form_fields( \WP_Term $term ): void {
	$inst = _get_instance();
	$idx  = get_term_meta( $term->term_id, $inst->key_order, true );
	$val  = ( false !== $idx ) ? $idx : '';
	$key  = $inst->key_order;
	?>
	<tr class="form-field">
		<th>
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html_x( 'Order', 'ordered term', 'wpinc_taxo' ); ?></label>
		</th>
		<td>
			<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" size="40" value="<?php echo esc_attr( $val ); ?>">
		</td>
	</tr>
	<?php
}

/**
 * Callback function for 'edited_{$taxonomy}' action.
 *
 * @access private
 *
 * @param int $term_id Term ID.
 * @param int $tt_id   Term taxonomy ID.
 */
function _cb_edited_taxonomy( int $term_id, int $tt_id ): void {
	$inst = _get_instance();
	$val  = $_POST[ $inst->key_order ] ?? null;  // phpcs:ignore
	if ( null !== $val ) {
		update_term_meta( $term_id, $inst->key_order, (int) $val );

		if ( $inst->is_post_term_order_enabled ) {
			_cb_edited_taxonomy__post_term_order( $term_id, $tt_id );
		}
	}
}

/**
 * Callback function for 'admin_head' filter.
 *
 * @access private
 */
function _cb_admin_head(): void {
	$inst = _get_instance();
	global $taxonomy;
	if ( ! in_array( $taxonomy, $inst->txs, true ) ) {
		return;
	}
	?>
	<style>
	#<?php echo esc_html( $inst->key_order ); ?> {width: 4rem;}
	.column-<?php echo esc_html( $inst->key_order ); ?> {text-align: right;}
	#posts {width: 90px;}
	</style>
	<script>
	jQuery(document).ready(function ($) {
		const wp_inline_edit = inlineEditTax.edit;
		inlineEditTax.edit = function (id) {
			wp_inline_edit.apply(this, arguments);
			if (typeof(id) === 'object') id = parseInt(this.getId(id));
			if (id > 0) {
				const tag_row = $('#tag-' + id);
				const order = $('.column-<?php echo esc_html( $inst->key_order ); ?>', tag_row).html();
				const input = document.querySelector('input[name="<?php echo esc_html( $inst->key_order ); ?>"]');
				input.value = order;
			}
			return false;
		};
	});
	</script>
	<?php
}

/**
 * Callback function for 'quick_edit_custom_box' action.
 *
 * @param string $column_name Name of the column to edit.
 * @param string $post_type   The post type slug, or current screen name if this is a taxonomy list table.
 * @param string $taxonomy    The taxonomy name, if any.
 */
function _cb_quick_edit_custom_box( string $column_name, string $post_type, string $taxonomy ): void {
	$inst = _get_instance();
	if ( $column_name !== $inst->key_order || ! in_array( $taxonomy, $inst->txs, true ) ) {
		return;
	}
	static $print_nonce = true;
	if ( $print_nonce ) {
		$print_nonce = false;
		wp_nonce_field( 'quick_edit_action', "{$column_name}_edit_nonce" );
	}
	?>
	<fieldset>
		<div id="<?php echo esc_attr( $inst->key_order ); ?>-content" class="inline-edit-col">
			<label>
				<span class="title"><?php echo esc_html_x( 'Order', 'ordered term', 'wpinc_taxo' ); ?></span>
				<span class="input-text-wrap"><input type="text" name="<?php echo esc_attr( $column_name ); ?>" class="ptitle" value=""></span>
			</label>
		</div>
	</fieldset>
	<?php
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'terms_clauses' filter.
 *
 * @param string[]             $pieces Array of query SQL clauses.
 * @param string[]             $txs    An array of taxonomy names.
 * @param array<string, mixed> $args   An array of term query arguments.
 * @return array<string, mixed> Filtered clauses.
 */
function _cb_terms_clauses( array $pieces, array $txs, array $args ): array {
	$inst = _get_instance();
	if ( count( $txs ) === 0 ) {
		return $pieces;
	}
	foreach ( $txs as $tx ) {
		if ( ! in_array( $tx, $inst->txs, true ) ) {
			return $pieces;
		}
	}
	$orderby = $args['orderby'] ?? '';
	$order   = $args['order'] ?? 'ASC';

	if ( 'name' !== $orderby && $inst->key_order !== $orderby ) {
		return $pieces;
	}
	global $wpdb;
	$pieces['fields'] .= ', tm.meta_key, tm.meta_value';
	$pieces['join']   .= " LEFT OUTER JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id AND tm.meta_key = '{$inst->key_order}'";
	$pieces['orderby'] = str_replace( 'ORDER BY', "ORDER BY tm.meta_value+0 $order,", $pieces['orderby'] );
	return $pieces;
}

/**
 * Callback function for 'get_the_terms' filter.
 *
 * @param \WP_Term[]|\WP_Error $terms    Array of attached terms, or WP_Error on failure.
 * @param int                  $post_id  Post ID.
 * @param string               $taxonomy Taxonomy slug.
 * @return \WP_Term[]|\WP_Error Filtered terms.
 */
function _cb_get_the_terms( $terms, int $post_id, string $taxonomy ) {
	if ( ! is_wp_error( $terms ) ) {
		$terms = sort_terms( $terms, $taxonomy );
	}
	return $terms;
}

/**
 * Sorts terms by order.
 *
 * @param \WP_Term[] $terms    Array of WP_Terms.
 * @param string     $taxonomy Taxonomy slug.
 * @return \WP_Term[] Sorted terms.
 */
function sort_terms( array $terms, string $taxonomy ): array {
	$inst = _get_instance();
	if ( ! in_array( $taxonomy, $inst->txs, true ) ) {
		return $terms;
	}
	$tos = array();
	foreach ( $terms as $t ) {
		$idx   = (int) get_term_meta( $t->term_id, $inst->key_order, true );
		$tos[] = array( $idx, $t );
	}
	usort(
		$tos,
		function ( $a, $b ) {
			return $a[0] <=> $b[0];
		}
	);
	return array_column( $tos, 1 );
}

/**
 * Sorts term ids by order.
 *
 * @param int[]  $term_ids Array of term IDs.
 * @param string $taxonomy Taxonomy slug.
 * @return int[] Sorted terms.
 */
function sort_term_ids( array $term_ids, string $taxonomy ): array {
	$inst = _get_instance();
	if ( ! in_array( $taxonomy, $inst->txs, true ) ) {
		return $term_ids;
	}
	$tos = array();
	foreach ( $term_ids as $tid ) {
		$idx   = (int) get_term_meta( $tid, $inst->key_order, true );
		$tos[] = array( $idx, $tid );
	}
	usort(
		$tos,
		function ( $a, $b ) {
			return $a[0] <=> $b[0];
		}
	);
	return array_column( $tos, 1 );
}


// -----------------------------------------------------------------------------


/**
 * Enables post term order.
 *
 * @param string|string[] $post_type_s A post type or array of post types.
 */
function enable_post_term_order( $post_type_s ): void {
	$pts  = is_array( $post_type_s ) ? $post_type_s : array( $post_type_s );
	$inst = _get_instance();

	foreach ( $pts as $pt ) {
		$inst->post_term_order_post_types[] = $pt;
	}
	if ( ! $inst->is_post_term_order_enabled ) {
		add_action( 'save_post', '\wpinc\taxo\ordered_term\_cb_save_post' );
		$inst->is_post_term_order_enabled = true;
	}
}

/**
 * Makes post meta key of post term order.
 *
 * @param string $taxonomy Taxonomy.
 * @return string Post meta key.
 */
function get_post_meta_key_of_post_term_order( string $taxonomy ): string {
	$inst = _get_instance();
	return "_$taxonomy{$inst->key_order}";
}

/**
 * Callback function for 'save_post' action.
 *
 * @param int $post_id Post ID.
 */
function _cb_save_post( int $post_id ): void {
	$inst = _get_instance();

	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, $inst->post_term_order_post_types, true ) ) {
		return;
	}
	foreach ( $inst->txs as $tx ) {
		_update_order_post_meta( $post_id, $tx );
	}
}

/**
 * Callback function for 'edited_{$taxonomy}' action.
 *
 * @access private
 *
 * @param int $term_id Term ID.
 * @param int $tt_id   Term taxonomy ID.
 */
function _cb_edited_taxonomy__post_term_order( int $term_id, int $tt_id ): void {
	$inst = _get_instance();
	$t    = get_term_by( 'term_taxonomy_id', $tt_id );

	if ( ! ( $t instanceof \WP_Term ) ) {
		return;
	}
	$ps = get_posts(
		array(
			'post_type' => $inst->post_term_order_post_types,
			'tax_query' => array(  // phpcs:ignore
				array(
					'taxonomy' => $t->taxonomy,
					'terms'    => $term_id,
				),
			),
		)
	);
	if ( ! empty( $ps ) ) {
		foreach ( $ps as $p ) {
			_update_order_post_meta( $p->ID, $t->taxonomy );
		}
	}
}

/**
 * Updates term order assigned as post meta.
 *
 * @access private
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 */
function _update_order_post_meta( int $post_id, string $taxonomy ): void {
	$key = get_post_meta_key_of_post_term_order( $taxonomy );
	$ts  = wp_get_post_terms( $post_id, $taxonomy );
	if ( is_wp_error( $ts ) || empty( $ts ) ) {
		delete_post_meta( $post_id, $key );
		return;
	}
	update_post_meta( $post_id, $key, get_order( $ts[0] ) );
}


// -----------------------------------------------------------------------------


/**
 * Gets instance.
 *
 * @access private
 *
 * @return object Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {
		/**
		 * The key of term metadata of order.
		 *
		 * @var string
		 */
		public $key_order = '';

		/**
		 * Taxonomies with order.
		 *
		 * @var string[]
		 */
		public $txs = array();

		/**
		 * Whether hooks are activated.
		 *
		 * @var bool
		 */
		public $is_activated = false;


		// ---------------------------------------------------------------------


		/**
		 * Array of post types that post term order is enabled.
		 *
		 * @var string[]
		 */
		public $post_term_order_post_types = array();

		/**
		 * Whether post term order is enabled.
		 *
		 * @var bool
		 */
		public $is_post_term_order_enabled = false;
	};
	return $values;
}
