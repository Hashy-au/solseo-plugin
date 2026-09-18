<?php
/**
 * Extra robots.txt rules.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$on_disk = file_exists( ABSPATH . 'robots.txt' );
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'robots.txt', 'solseo' ); ?></h2>

	<?php if ( $on_disk ) : ?>
		<p class="solseo-warning"><?php esc_html_e( 'There is a robots.txt file on the server. While it is there, WordPress does not generate one and nothing typed below has any effect.', 'solseo' ); ?></p>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'solseo_robots', '_solseo_nonce' ); ?>

		<p>
			<label for="solseo-robots-rules"><?php esc_html_e( 'Rules to add', 'solseo' ); ?></label>
			<textarea id="solseo-robots-rules" name="solseo_robots_rules" rows="8" class="large-text code"><?php echo esc_textarea( $data['rules'] ); ?></textarea>
		</p>

		<p class="description"><?php esc_html_e( 'Added to what WordPress already writes. The sitemap line is added for you.', 'solseo' ); ?></p>

		<p>
			<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See the result', 'solseo' ); ?></a>
		</p>

		<?php submit_button(); ?>
	</form>
</div>
