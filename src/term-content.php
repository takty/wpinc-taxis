<?php
/**
 * Term Content (Rich Editor)
 *
 * @package Wpinc Taxo
 * @author Takuto Yanagida
 * @version 2022-01-16
 */

namespace wpinc\taxo;

/**
 * Adds term content field.
 *
 * @param string $taxonomy      Taxonomy.
 * @param string $key           Term meta key.
 * @param string $label_postfix The postfix shown after label 'Content'. Default empty.
 * @param int    $priority      Priority of action '{$taxonomy}_edit_form_fields'. Default 10.
 */
function add_term_content_field( string $taxonomy, string $key, string $label_postfix = '', int $priority = 10 ): void {
	add_action(
		"{$taxonomy}_edit_form_fields",
		function ( $term ) use ( $key, $label_postfix ) {
			$cont = get_term_meta( $term->term_id, $key, true );
			?>
			<tr class="form-field">
				<th scope="row">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html_x( 'Content', 'term content', 'taxo' ); ?><?php echo esc_html( $label_postfix ); ?></label>
				</th>
				<td><?php wp_editor( $cont, $key, array( 'textarea_rows' => '8' ) ); ?></td>
			</tr>
			<?php
		},
		$priority
	);
	add_action(
		"edited_$taxonomy",
		function ( $term_id ) use ( $key ) {
			$val = $_POST[ $key ] ?? null;  // phpcs:ignore
			if ( null !== $val ) {
				if ( empty( $val ) ) {
					delete_term_meta( $term_id, $key );
				} else {
					$val = apply_filters( 'content_save_pre', $val );
					update_term_meta( $term_id, $key, $val );
				}
			}
		}
	);
}

/**
 * Retrieves term content.
 *
 * @param \WP_Term $term Term.
 * @param string   $key  Term meta key.
 * @return string the term content.
 */
function get_term_content( \WP_Term $term, string $key ): string {
	$c = get_term_meta( $term->term_id, $key, true );
	if ( empty( $c ) ) {
		return '';
	}
	// Apply the filters for 'the_content'.
	if ( function_exists( 'do_blocks' ) ) {
		$c = do_blocks( $c );
	}
	$c = wptexturize( $c );
	$c = wpautop( $c );
	$c = shortcode_unautop( $c );
	$c = prepend_attachment( $c );
	$c = wp_make_content_images_responsive( $c );
	$c = capital_P_dangit( $c );
	$c = do_shortcode( $c );
	$c = convert_smilies( $c );

	return str_replace( ']]>', ']]&gt;', $c );
}
