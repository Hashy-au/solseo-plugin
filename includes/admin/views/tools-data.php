<?php
/**
 * What happens to the plugin's data when it is removed.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'When the plugin is deleted', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'By default everything is left in place, so deleting the plugin and installing it again puts the site back exactly as it was.', 'solseo' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'solseo_data', '_solseo_nonce' ); ?>

		<label>
			<input type="checkbox" name="solseo[remove_data]" value="1" <?php checked( $data['remove'] ); ?>>
			<?php esc_html_e( 'Remove the settings, the SEO fields and the redirect tables when the plugin is deleted', 'solseo' ); ?>
		</label>

		<p class="description"><?php esc_html_e( 'This cannot be undone. Deactivating the plugin never removes anything.', 'solseo' ); ?></p>

		<?php submit_button(); ?>
	</form>
</div>
