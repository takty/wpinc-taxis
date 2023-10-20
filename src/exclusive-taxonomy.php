<?php
/**
 * Exclusive Taxonomy
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-10-20
 */

namespace wpinc\taxo\exclusive_taxonomy;

require_once __DIR__ . '/assets/asset-url.php';

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
	if ( ! empty( $txs ) ) {
		array_push( $inst->txs, ...$txs );
	}
}

/**
 * Initializes hooks.
 */
function _initialize_hooks(): void {
	global $pagenow;
	if ( 'edit.php' === $pagenow ) {
		add_action( 'admin_print_footer_scripts', '\wpinc\taxo\exclusive_taxonomy\_cb_admin_print_footer_scripts' );
	}
	add_action( 'set_object_terms', '\wpinc\taxo\exclusive_taxonomy\_cb_set_object_terms', 10, 6 );

	add_action( 'current_screen', '\wpinc\taxo\exclusive_taxonomy\_cb_current_screen', 10, 0 );
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
 * @param int            $object_id  Object ID.
 * @param int[]|string[] $_terms     An array of object term IDs or slugs.
 * @param int[]          $tt_ids     An array of term taxonomy IDs.
 * @param string         $taxonomy   Taxonomy slug.
 * @param bool           $_append    Whether to append new terms to the old terms.
 * @param int[]          $old_tt_ids Old array of term taxonomy IDs.
 */
function _cb_set_object_terms( int $object_id, array $_terms, array $tt_ids, string $taxonomy, bool $_append, array $old_tt_ids ): void {
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
 * @access private
 * @psalm-suppress UnusedParam, UnusedVariable
 *
 * @param int[]  $tt_ids   Array of term_taxonomy_ids.
 * @param string $taxonomy Taxonomy slug.
 * @return int[] Sorted term_taxonomy_ids.
 */
function _sort_term_taxonomy_ids( array $tt_ids, string $taxonomy ): array {
	if ( function_exists( '\wpinc\taxo\ordered_term\sort_terms' ) ) {
		$ts = array();
		foreach ( $tt_ids as $tt_id ) {
			$t = get_term_by( 'term_taxonomy_id', $tt_id );
			if ( $t instanceof \WP_Term ) {
				$ts[] = $t;
			}
		}
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
		$cs = get_current_screen();
		if ( $cs && $cs->is_block_editor() ) {
			add_action( 'enqueue_block_editor_assets', '\wpinc\taxo\exclusive_taxonomy\_cb_enqueue_block_editor_assets' );
		} else {
			add_action( 'admin_print_footer_scripts', '\wpinc\taxo\exclusive_taxonomy\_cb_admin_print_footer_scripts_ce' );
		}
	}
}


// ---------------------------------------- Callback Functions for Block Editor.


/**
 * Callback function for 'enqueue_block_editor_assets' action.
 *
 * @access private
 */
function _cb_enqueue_block_editor_assets(): void {
	$inst   = _get_instance();
	$url_to = untrailingslashit( \wpinc\get_file_uri( __DIR__ ) );
	wp_enqueue_script(
		'wpinc-custom-taxonomy',
		\wpinc\abs_url( $url_to, './assets/js/custom-taxonomy.min.js' ),
		array( 'wp-i18n', 'wp-data', 'wp-components', 'wp-compose', 'wp-element', 'wp-url' ),
		(string) filemtime( __DIR__ . '/assets/js/custom-taxonomy.min.js' ),
		true
	);
	$val  = empty( $inst->txs ) ? "'*'" : wp_json_encode( $inst->txs );
	$data = "var wpinc_custom_taxonomy_exclusive = $val;";
	wp_add_inline_script( 'wpinc-custom-taxonomy', $data, 'before' );
}


// -------------------------------------- Callback Functions for Classic Editor.


/**
 * Callback function for 'admin_print_footer_scripts' action.
 */
function _cb_admin_print_footer_scripts_ce(): void {
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
	});
	</script>
	<?php
}


// -----------------------------------------------------------------------------


/**
 * Gets instance.
 *
 * @access private
 *
 * @return object{
 *     txs: string[],
 * } Instance.
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
		 * @var string[]
		 */
		public $txs = array();
	};
	return $values;
}
