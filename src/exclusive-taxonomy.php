<?php
/**
 * Exclusive Taxonomy
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-15
 */

namespace wpinc\taxo\exclusive_taxonomy;

/**
 * Makes taxonomies exclusive.
 *
 * @param string|string[] $taxonomy_s A taxonomy slug or array of taxonomy slugs.
 */
function add_taxonomy( $taxonomy_s ): void {
	if ( ! is_admin() ) {
		return;
	}
	$inst = _get_instance();
	$txs  = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );

	if ( empty( $inst->txs ) ) {
		_initialize_hooks();
	}
	array_push( $inst->txs, ...$txs );
}

/**
 * Initializes hooks.
 */
function _initialize_hooks(): void {
	global $pagenow;
	if ( 'edit.php' === $pagenow || 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		add_action( 'admin_print_footer_scripts', '\wpinc\taxo\exclusive_taxonomy\_cb_admin_print_footer_scripts' );
	}
	add_action( 'set_object_terms', '\wpinc\taxo\exclusive_taxonomy\_cb_set_object_terms', 10, 6 );

	// For block editor.
	add_action( 'current_screen', '\wpinc\taxo\exclusive_taxonomy\_cb_current_screen' );  // For using is_block_editor().
	add_action( 'save_post', '\wpinc\taxo\exclusive_taxonomy\_cb_save_post', 10, 2 );
}

/**
 * Callback function for 'admin_print_footer_scripts' action.
 */
function _cb_admin_print_footer_scripts(): void {
	$inst    = _get_instance();
	$txs_str = implode( "','", $inst->txs );
	?>
	<script type="text/javascript">
	jQuery(function ($) {
		const txs = ['<?php echo $txs_str;  // phpcs:ignore ?>'];

		// For edit screen of the classic editor.
		for (const tx of txs) {
			$(`#taxonomy-${tx} input[type=checkbox]`).each(function () { $(this).attr('type', 'radio'); });
		}

		// For quick edit.
		for (const tx of txs) {
			const cl = $(`.${tx}-checklist input[type=checkbox]`);
			cl.each(function () { $(this).prop('type', 'radio'); });
		}
		$('#the-list').on('click', 'button.editinline', function () {
			const post_id = inlineEditPost.getId(this);
			const rowData = $('#inline_'+ post_id);
			$('.post_category', rowData).each(function () {
				const tx = $(this).attr('id').replace('_' + post_id, '');
				if (txs.includes(tx)) {
					let term_ids = $(this).text();
					term_ids = term_ids.trim() !== '' ? term_ids.trim() : '0';
					let term_id = term_ids.split(',');
					term_id = term_id ? term_id[0] : '0';
					if (term_id === '0') {
						$(`.${tx}-checklist li input:radio`).prop('checked', false);
					} else {
						$(`li#${tx}-${term_id}`).find('input:radio').first().prop('checked', true);
					}
				}
			});
		});

		// For bulk edit.
		$('#doaction, #doaction2').on('click', function(e) {
			const n = $(this).attr('id').substr(2);
			if ('edit' === $(`select[name="${n}"]`).val()) {
				e.preventDefault();
				$('.cat-checklist').each(function () {
					if ($(this).find('input[type="radio"]').length) {
						$(this).find('input[type="radio"]').prop('checked', false);
						$(this).prev('input').remove();
					}
				});
			}
		});
	});
	</script>
	<?php
}

/**
 * Callback function for 'set_object_terms' action.
 *
 * @param int    $object_id  Object ID.
 * @param array  $terms      An array of object term IDs or slugs.
 * @param array  $tt_ids     An array of term taxonomy IDs.
 * @param string $taxonomy   Taxonomy slug.
 * @param bool   $append     Whether to append new terms to the old terms.
 * @param array  $old_tt_ids Old array of term taxonomy IDs.
 */
function _cb_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
	$inst = _get_instance();

	if ( in_array( $taxonomy, $inst->txs, true ) ) {
		$old_tt_ids = array_map( 'intval', $old_tt_ids );
		$tt_ids     = array_map( 'intval', $tt_ids );

		$ai = array_values( array_intersect( $old_tt_ids, $tt_ids ) );
		if ( count( $ai ) === count( $tt_ids ) ) {
			return;  // No change happens.
		}
		$ad = array_values( array_diff( $tt_ids, $ai ) );
		if ( 1 < count( $ad ) ) {
			$ad = _sort_term_taxonomy_ids( $ad, $taxonomy );
			array_shift( $ad );
			$ai = array_merge( $ai, $ad );
		}
		if ( ! empty( $ai ) ) {
			wp_remove_object_terms( $object_id, array_values( $ai ), $taxonomy );
		}
	}
}

/**
 * Sorts term taxonomy ids when ordered term is activated.
 *
 * @param int[]  $tt_ids   Array of term_taxonomy_ids.
 * @param string $taxonomy Taxonomy slug.
 * @return array Sorted term_taxonomy_ids.
 */
function _sort_term_taxonomy_ids( array $tt_ids, string $taxonomy ): array {
	if ( function_exists( '\wpinc\taxo\ordered_term\sort_terms' ) ) {
		$ts = array_map(
			function ( $tt_id ) {
				return get_term_by( 'term_taxonomy_id', $tt_id );
			},
			$tt_ids
		);
		$ts = \wpinc\taxo\ordered_term\sort_terms( $ts, $taxonomy );

		$tt_ids = array_column( $ts, 'term_taxonomy_id' );
	}
	return $tt_ids;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'current_screen' action.
 */
function _cb_current_screen(): void {
	global $pagenow;
	if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		if ( get_current_screen()->is_block_editor() ) {
			$inst = _get_instance();
			foreach ( $inst->txs as $tx ) {
				$tx_obj = get_taxonomy( $tx );
				if ( $tx_obj ) {
					$tx_obj->show_in_rest = false;
				}
			}
			add_action( 'add_meta_boxes', '\wpinc\taxo\exclusive_taxonomy\_cb_add_meta_boxes', 10, 2 );
		}
	}
}

/**
 * Callback function for 'add_meta_boxes' action.
 * For adding metabox to block editor.
 *
 * @param string   $post_type Post type.
 * @param \WP_Post $post      Post object.
 */
function _cb_add_meta_boxes( string $post_type, \WP_Post $post ): void {
	$inst = _get_instance();

	foreach ( $inst->txs as $tx ) {
		$tx_obj = get_taxonomy( $tx );
		if ( ! $tx_obj || ! in_array( $post_type, $tx_obj->object_type, true ) ) {
			continue;
		}
		if ( ! $tx_obj->show_ui ) {
			continue;
		}
		add_meta_box(
			"_wpinc_ex_tx_{$tx}_mb",
			$tx_obj->labels->name,
			function ( \WP_Post $post ) use ( $tx ) {
				_cb_output_html( $post, $tx );
			},
			$post_type,
			'side'
		);
	}
}

/**
 * Callback function for 'post_submitbox_misc_actions' action.
 *
 * @access private
 *
 * @param \WP_Post $post WP_Post object for the current post.
 * @param string   $tx   Taxonomy slug.
 */
function _cb_output_html( \WP_Post $post, string $tx ): void {
	$ts = get_terms(
		array(
			'taxonomy'   => $tx,
			'hide_empty' => false,
		)
	);

	$curs = get_the_terms( $post, $tx );
	if ( ! is_array( $curs ) ) {
		$curs = array();
	}
	$curs = array_column( $curs, 'term_id' );

	wp_nonce_field( "_wpinc_ex_tx_$tx", "_wpinc_ex_tx_{$tx}_nonce" );
	echo '<ul style="margin:0;">';
	foreach ( $ts as $t ) {
		$name   = "_wpinc_ex_tx[$tx][]";
		$is_sel = in_array( $t->term_id, $curs, true );
		?>
		<li>
			<label>
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $t->term_id ); ?>" <?php echo checked( $is_sel, true, false ); ?>>
				<span><?php echo esc_html( $t->name ); ?></span>
			</label>
		</li>
		<?php
	}
	echo '</ul>';
}

/**
 * Callback function for 'save_post' action.
 *
 * @access private
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    WP_Post object for the current post.
 */
function _cb_save_post( int $post_id, \WP_Post $post ): void {
	$inst = _get_instance();

	foreach ( $inst->txs as $tx ) {
		$tx_obj = get_taxonomy( $tx );
		if ( ! $tx_obj || ! in_array( $post->post_type, $tx_obj->object_type, true ) ) {
			continue;
		}
		if ( ! $tx_obj->show_ui ) {
			continue;
		}
		if (
			! isset( $_POST[ "_wpinc_ex_tx_{$tx}_nonce" ] ) ||
			! wp_verify_nonce( sanitize_key( $_POST[ "_wpinc_ex_tx_{$tx}_nonce" ] ), "_wpinc_ex_tx_{$tx}" ) ||
			defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
		) {
			continue;
		}
		if ( isset( $_POST['_wpinc_ex_tx'][ $tx ] ) ) {
			$tags = $_POST['_wpinc_ex_tx'][ $tx ];  // phpcs:ignore
			if ( is_array( $tags ) ) {
				$tags = array_filter( $tags );
			}
			if ( current_user_can( $tx_obj->cap->assign_terms ) ) {
				wp_set_post_terms( $post_id, $tags, $tx );
			}
		}
	}
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
		 * The target taxonomies.
		 *
		 * @var array
		 */
		public $txs = array();
	};
	return $values;
}
