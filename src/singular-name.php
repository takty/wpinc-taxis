<?php
/**
 * Singular Name
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-02-07
 */

namespace wpinc\taxo {
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
		$args += array(
			'taxonomies'        => array(),
			'singular_name_key' => '_singular_name',
		);

		$txs = $args['taxonomies'];

		foreach ( $txs as $tx ) {
			add_action( "{$tx}_edit_form_fields", '\wpinc\taxo\singular_name\_cb_tx_edit_form_fields', 10, 2 );
			add_action( "edited_{$tx}", '\wpinc\taxo\singular_name\_cb_edited_tx', 10, 1 );
		}

		global $pagenow;
		if ( ! is_admin() || ( is_admin() && in_array( $pagenow, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) ) {
			foreach ( $txs as $tx ) {
				add_filter( "get_{$tx}", '\wpinc\taxo\singular_name\_cb_get_taxonomy', 10 );
			}
		}

		$inst = \wpinc\taxo\singular_name\_get_instance();
		foreach ( $txs as $tx ) {
			$inst->txs[] = $tx;
		}
		$inst->key = $args['default_singular_name_key'];
	}
}

namespace wpinc\taxo\singular_name {
	/**
	 * Callback function for '{$taxonomy}_edit_form_fields' action.
	 *
	 * @param \WP_Term $term     Current taxonomy term object.
	 * @param string   $taxonomy Current taxonomy slug.
	 */
	function _cb_tx_edit_form_fields( \WP_Term $term, string $taxonomy ): void {
		$key = _get_instance()->key;
		$val = get_term_meta( $term->term_id, $key, true );
		?>
		<tr class="form-field">
			<th>
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html_x( 'Name (Singular Form)', 'singular name', 'taxo' ); ?></label>
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
	 */
	function _cb_edited_tx( int $term_id ) {
		$key = _get_instance()->key;
		$val = $_POST[ $key ] ?? '';  // phpcs:ignore

		if ( empty( $val ) ) {
			return delete_term_meta( $term_id, $key );
		}
		return update_term_meta( $term_id, $key, $val );
	}

	/**
	 * Callback function for 'get_{$taxonomy}' filter.
	 *
	 * @access private
	 *
	 * @param \WP_Term $t Term object.
	 * @return \WP_Term The filtered term.
	 */
	function _cb_get_taxonomy( \WP_Term $t ): \WP_Term {
		if ( in_array( $t->taxonomy, _get_instance()->txs, true ) ) {
			if ( ! isset( $t->singular_name ) ) {
				$sn = get_term_meta( $t->term_id, _get_instance()->key, true );

				$t->singular_name = empty( $sn ) ? $t->name : $sn;
			}
		}
		return $t;
	}


	// -------------------------------------------------------------------------


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
			 * The term meta key for a singular name.
			 *
			 * @var string
			 */
			public $key = null;

			/**
			 * The taxonomies.
			 *
			 * @var string[]
			 */
			public $txs = array();
		};
		return $values;
	}
}
