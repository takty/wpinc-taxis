<?php
/**
 * Simple Taxonomy UI
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-22
 */

namespace wpinc\taxo\simple_ui;

/**
 * Activates simple taxonomy UIs.
 *
 * @param string|string[] $taxonomy_s  (Optional) A taxonomy slug or array of taxonomy slugs.
 */
function activate( $taxonomy_s = array() ): void {
	if ( ! is_admin() ) {
		return;
	}
	$inst = _get_instance();
	$txs  = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );

	static $activated = false;
	if ( ! $activated ) {
		$activated = true;
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
		// Change term selection UI from textarea to checkboxes for classic editor and list.
		add_action( 'admin_init', '\wpinc\taxo\simple_ui\_cb_admin_init' );
	}
	add_action( 'current_screen', '\wpinc\taxo\simple_ui\_cb_current_screen' );
}

/**
 * Callback function for 'admin_init' action.
 *
 * @access private
 */
function _cb_admin_init(): void {
	$inst = _get_instance();

	$tx_objs = _taxonomy_slugs_to_objects( $inst->txs );
	foreach ( $tx_objs as &$obj ) {
		$obj->hierarchical = true;
		$obj->meta_box_cb  = 'post_categories_meta_box';
	}
}

/**
 * Converts taxonomy slugs to objects.
 *
 * @access private
 *
 * @param string[] $tx_slugs Taxonomy slugs.
 * @return \WP_Taxonomy[] Taxonomy objects.
 */
function _taxonomy_slugs_to_objects( array $tx_slugs ): array {
	global $wp_taxonomies;
	if ( empty( $tx_slugs ) ) {
		return $wp_taxonomies;
	}
	$ret = array();
	foreach ( $tx_slugs as $slug ) {
		$obj = get_taxonomy( $slug );
		if ( $obj ) {
			$ret[ $slug ] = $obj;
		}
	}
	return $ret;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'current_screen' action.
 */
function _cb_current_screen(): void {
	global $pagenow;

	if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		if ( get_current_screen()->is_block_editor() ) {
			add_action( 'enqueue_block_editor_assets', '\wpinc\taxo\simple_ui\_cb_enqueue_block_editor_assets' );
		} else {
			// Remove UI elements from metabox for classic editor.
			add_action( 'admin_head', '\wpinc\taxo\simple_ui\_cb_admin_head_ce' );
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
	$inst = _get_instance();

	$url_to = untrailingslashit( \wpinc\get_file_uri( __DIR__ ) );
	wp_enqueue_script(
		'wpinc-custom-taxonomy',
		\wpinc\abs_url( $url_to, './assets/js/custom-taxonomy.min.js' ),
		array( 'wp-i18n', 'wp-data', 'wp-components', 'wp-compose', 'wp-element', 'wp-url' ),
		filemtime( __DIR__ . '/assets/js/custom-taxonomy.min.js' ),
		true
	);
	$val  = empty( $inst->txs ) ? "'*'" : wp_json_encode( $inst->txs );
	$data = "var wpinc_custom_taxonomy_inclusive = $val;";
	wp_add_inline_script( 'wpinc-custom-taxonomy', $data, 'before' );
}


// -------------------------------------- Callback Functions for Classic Editor.


/**
 * Callback function for 'admin_head' action.
 *
 * @access private
 */
function _cb_admin_head_ce(): void {
	$inst = _get_instance();

	$s = '';
	if ( empty( $inst->txs ) ) {
		$s .= '.categorydiv div[id$="-adder"], .category-tabs{display:none;}';
		$s .= '.categorydiv div.tabs-panel{border:none;padding:0;}';
		$s .= '.categorychecklist{margin-top:4px;}';
	} else {
		foreach ( $inst->txs as $tx ) {
			$s .= "#$tx-adder,#$tx-tabs{display:none;}";
			$s .= "#$tx-all{border:none;padding:0;}";
			$s .= "#{$tx}checklist{margin-top:4px;}";
		}
	}
	echo wp_kses( '<style>' . $s . '</style>', array( 'style' => array() ) );
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
		 * @var array|null
		 */
		public $txs = array();
	};
	return $values;
}
