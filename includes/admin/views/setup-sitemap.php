<?php
/**
 * Setup, step four: the sitemap.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_options = $data['options'];
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'A list of your pages, for search engines', 'solseo' ); ?></h2>

	<p class="description"><?php esc_html_e( 'A sitemap is a file listing every page worth finding, so a search engine does not have to guess by following links. Most sites want one.', 'solseo' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'solseo_setup_sitemap', '_solseo_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sitemap', 'solseo' ); ?></th>
				<td>
					<label class="solseo-tick">
						<input type="checkbox" name="solseo[sitemap_enabled]" value="1" <?php checked( $solseo_options['sitemap_enabled'] ); ?>>
						<?php esc_html_e( 'Serve a sitemap and list it in robots.txt', 'solseo' ); ?>
					</label>

					<p class="description">
						<?php
						printf(
							/* translators: %s: the address of the sitemap. */
							esc_html__( 'It appears at %s, and it takes the place of the one WordPress generates.', 'solseo' ),
							'<code>' . esc_html( home_url( '/sitemap.xml' ) ) . '</code>'
						);
						?>
					</p>

					<p class="description"><?php esc_html_e( 'Once the site is live, paste that address into Google Search Console. That is the one step nobody remembers and the only one that makes any of this arrive faster.', 'solseo' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="solseo-setup-buttons">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save and carry on', 'solseo' ); ?></button>
			<a class="button-link" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'solseo-setup',
						'step' => 5,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Skip this', 'solseo' ); ?></a>
		</p>
	</form>
</div>
