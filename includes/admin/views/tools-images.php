<?php
/**
 * Bulk image alt text.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Images with nothing written about them', 'solseo' ); ?></h2>

	<?php if ( ! $data['missing'] ) : ?>
		<p><?php esc_html_e( 'Every image in the library has alt text.', 'solseo' ); ?></p>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: %d: number of images with no alt text. */
				esc_html( _n( '%d image has no alt text.', '%d images have no alt text.', (int) $data['missing'], 'solseo' ) ),
				(int) $data['missing']
			);
			?>
		</p>

		<p class="description"><?php esc_html_e( 'The text is taken from the post or product the image is attached to, or from a tidied up file name. Anything already written is left alone.', 'solseo' ); ?></p>

		<form method="post" class="solseo-inline-form">
			<?php wp_nonce_field( 'solseo_alt_text', '_solseo_nonce' ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Describe the next hundred', 'solseo' ); ?></button>
		</form>
	<?php endif; ?>
</div>
