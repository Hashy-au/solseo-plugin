<?php
/**
 * SEO fields on category and tag screens.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Adds title, description and visibility fields to a term.
 */
class Term_Fields {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Attach to every taxonomy the plugin manages.
	 */
	public static function register() {
		foreach ( solseo_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render' ), 20 );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save' ) );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save' ) );
		}
	}

	/**
	 * Draw the fields.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function render( $term ) {
		wp_nonce_field( 'solseo_term', 'solseo_term_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="solseo-term-title"><?php esc_html_e( 'SEO title', 'solseo' ); ?></label></th>
			<td>
				<input type="text" id="solseo-term-title" name="solseo[title]" value="<?php echo esc_attr( Meta::get_term( $term->term_id, 'title' ) ); ?>" class="large-text">
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="solseo-term-description"><?php esc_html_e( 'Meta description', 'solseo' ); ?></label></th>
			<td>
				<textarea id="solseo-term-description" name="solseo[description]" rows="3" class="large-text"><?php echo esc_textarea( Meta::get_term( $term->term_id, 'description' ) ); ?></textarea>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="solseo-term-canonical"><?php esc_html_e( 'Canonical address', 'solseo' ); ?></label></th>
			<td>
				<input type="url" id="solseo-term-canonical" name="solseo[canonical]" value="<?php echo esc_attr( Meta::get_term( $term->term_id, 'canonical' ) ); ?>" class="large-text">
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Visibility', 'solseo' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="solseo[robots_noindex]" value="1" <?php checked( Meta::get_term( $term->term_id, 'robots_noindex' ) ); ?>>
					<?php esc_html_e( 'Keep this archive out of search results', 'solseo' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST['solseo_term_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['solseo_term_nonce'] ) ), 'solseo_term' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Meta::save_term sanitises by field type.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		if ( ! is_array( $input ) ) {
			return;
		}

		Meta::save_term(
			$term_id,
			array(
				'title'          => isset( $input['title'] ) ? $input['title'] : '',
				'description'    => isset( $input['description'] ) ? $input['description'] : '',
				'canonical'      => isset( $input['canonical'] ) ? $input['canonical'] : '',
				'robots_noindex' => ! empty( $input['robots_noindex'] ),
			)
		);
	}
}
