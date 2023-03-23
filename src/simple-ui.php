<?php
/**
 * Simple Taxonomy UI
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-03-23
 */

namespace wpinc\taxo\simple_ui;

/**
 * Activates simple taxonomy UIs.
 *
 * @param string|string[] $taxonomy_s  (Optional) A taxonomy slug or array of taxonomy slugs.
 */
function activate( $taxonomy_s = array() ): void {
	$inst = _get_instance();

	static $activated = false;
	if ( ! $activated ) {
		$activated = true;
		if ( is_admin() ) {
			_initialize_hooks();
		}
		_initialize_rest_hooks();
	}

	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );
	if ( ! empty( $txs ) ) {
		array_push( $inst->txs, ...$txs );
	}
}

/**
 * Initializes hooks for REST.
 */
function _initialize_rest_hooks(): void {
	add_filter( 'rest_prepare_taxonomy', '\wpinc\taxo\simple_ui\_cb_rest_prepare_taxonomy', 10, 3 );
}

/**
 * Callback function for 'rest_prepare_taxonomy' hook.
 *
 * @param \WP_REST_Response $response The response object.
 * @param \WP_Taxonomy      $item     The original taxonomy object.
 * @param \WP_REST_Request  $request  Request used to generate the response.
 * @return \WP_REST_Response Response object.
 */
function _cb_rest_prepare_taxonomy( \WP_REST_Response $response, \WP_Taxonomy $item, \WP_REST_Request $request ): \WP_REST_Response {
	$ctx = empty( $request['context'] ) ? 'view' : $request['context'];

	if ( 'edit' === $ctx && false === $item->meta_box_cb ) {
		$data = $response->get_data();

		$data['visibility']['show_ui'] = false;
		$response->set_data( $data );
	}
	return $response;
}

/**
 * Initializes hooks.
 */
function _initialize_hooks(): void {
	global $pagenow;

	if ( wp_doing_ajax() || 'edit.php' === $pagenow || 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
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
		if ( false !== $obj->meta_box_cb ) {
			$obj->hierarchical = true;
			$obj->meta_box_cb  = 'post_categories_meta_box';
		}
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
	$val  = _get_target_taxonomy_slugs_json( $inst->txs );
	$data = "var wpinc_custom_taxonomy_inclusive = $val;";
	wp_add_inline_script( 'wpinc-custom-taxonomy', $data, 'before' );
}

/**
 * Makes JSON string of target taxonomies.
 *
 * @param string[] $tx_slugs Taxonomy slugs.
 * @return string JSON string.
 */
function _get_target_taxonomy_slugs_json( array $tx_slugs ): string {
	global $wp_taxonomies;
	$objs = array();

	if ( empty( $tx_slugs ) ) {
		$objs = $wp_taxonomies;
	} else {
		foreach ( $tx_slugs as $slug ) {
			$obj = get_taxonomy( $slug );
			if ( $obj ) {
				$objs[] = $obj;
			}
		}
	}
	$ret = array();
	foreach ( $objs as $obj ) {
		if ( false !== $obj->meta_box_cb ) {
			$ret[] = $obj->name;
		}
	}
	if ( count( $wp_taxonomies ) === count( $ret ) ) {
		return "'*'";
	}
	return wp_json_encode( $ret );
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
