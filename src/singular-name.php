<?php
/**
 * Singular Name
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2023-10-20
 */

declare(strict_types=1);

namespace wpinc\taxo {  // phpcs:ignore
	/** phpcs:ignore
	 * Enables singular name for each terms.
	 *
	 * phpcs:ignore
	 * @param array{
	 *     taxonomies?       : string[],
	 *     singular_name_key?: string,
	 * } $args Configuration arguments.
	 *
	 * $args {
	 *     Configuration arguments.
	 *
	 *     @type string[] 'taxonomies'        Array of taxonomy slugs.
	 *     @type string   'singular_name_key' Key of term metadata for a singular name. Default '_singular_name'.
	 * }
	 */
	function enable_singular_name( array $args ): void {
		$args += array(
			'taxonomies'        => array(),
			'singular_name_key' => '_singular_name',
		);

		$txs = $args['taxonomies'];

		foreach ( $txs as $tx ) {
			add_action( "{$tx}_edit_form_fields", '\wpinc\taxo\singular_name\_cb_tx_edit_form_fields', 10, 1 );
			add_action( "edited_{$tx}", '\wpinc\taxo\singular_name\_cb_edited_tx', 10, 1 );
		}

		global $pagenow;
		if ( ! is_admin() || in_array( $pagenow, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {
			foreach ( $txs as $tx ) {
				add_filter( "get_{$tx}", '\wpinc\taxo\singular_name\_cb_get_taxonomy', 10 );
			}
		}

		$inst = \wpinc\taxo\singular_name\_get_instance();
		foreach ( $txs as $tx ) {
			$inst->txs[] = $tx;  // @phpstan-ignore-line
		}
		$inst->key = $args['singular_name_key'];  // @phpstan-ignore-line
	}
}

namespace wpinc\taxo\singular_name {  // phpcs:ignore
	/**
	 * Callback function for '{$taxonomy}_edit_form_fields' action.
	 *
	 * @param \WP_Term $term Current taxonomy term object.
	 */
	function _cb_tx_edit_form_fields( \WP_Term $term ): void {
		$key = _get_instance()->key;
		$val = get_term_meta( $term->term_id, $key, true );
		$val = is_string( $val ) ? $val : '';
		?>
		<tr class="form-field">
			<th>
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html_x( 'Name (Singular Form)', 'singular name', 'wpinc_taxo' ); ?></label>
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
	function _cb_edited_tx( int $term_id ): void {
		$key = _get_instance()->key;
		if ( ! isset( $_POST[ $key ] ) ) {  // phpcs:ignore
			return;  // When called through bulk edit.
		}
		$val = $_POST[ $key ];  // phpcs:ignore
		if ( empty( $val ) ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $val );
		}
	}

	/**
	 * Callback function for 'get_{$taxonomy}' filter.
	 *
	 * @access private
	 * @psalm-suppress UndefinedPropertyAssignment
	 *
	 * @param \WP_Term $t Term object.
	 * @return \WP_Term The filtered term.
	 */
	function _cb_get_taxonomy( \WP_Term $t ): \WP_Term {
		if ( in_array( $t->taxonomy, _get_instance()->txs, true ) ) {
			if ( ! isset( $t->singular_name ) ) {
				$sn = get_term_meta( $t->term_id, _get_instance()->key, true );

				$t->singular_name = empty( $sn ) ? $t->name : $sn;  // @phpstan-ignore-line
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
	 * @return object{
	 *     key: string,
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
			 * The term meta key for a singular name.
			 *
			 * @var string
			 */
			public $key = '';

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
