<?php
/**
 * Sitemap settings and the list of sitemaps the site is serving.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$options = $data['options'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'XML sitemap', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[sitemap_enabled]" value="1" <?php checked( $options['sitemap_enabled'] ); ?>> <?php esc_html_e( 'Serve a sitemap and list it in robots.txt', 'solseo' ); ?></label>
			<p class="description">
				<?php
				printf(
					'<a href="%1$s" target="_blank" rel="noopener">%1$s</a>',
					esc_url( home_url( '/sitemap.xml' ) )
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Images', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[sitemap_images]" value="1" <?php checked( $options['sitemap_images'] ); ?>> <?php esc_html_e( 'List the images on each page', 'solseo' ); ?></label>
			<p class="description"><?php esc_html_e( 'Product galleries are listed as well, which is how a shop gets its photographs into image search.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Authors', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[sitemap_authors]" value="1" <?php checked( $options['sitemap_authors'] ); ?>> <?php esc_html_e( 'Include author archives', 'solseo' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-per-page"><?php esc_html_e( 'Addresses per sitemap', 'solseo' ); ?></label></th>
		<td>
			<input type="number" class="small-text" id="solseo-per-page" name="solseo[sitemap_per_page]" value="<?php echo (int) $options['sitemap_per_page']; ?>" min="50" max="2000" step="50">
			<p class="description"><?php esc_html_e( 'Lower this if a large sitemap times out on shared hosting.', 'solseo' ); ?></p>
		</td>
	</tr>
</table>

<?php if ( $data['sections'] ) : ?>
	<h2><?php esc_html_e( 'What is being listed', 'solseo' ); ?></h2>

	<table class="solseo-table">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Sitemap', 'solseo' ); ?></th>
			<th><?php esc_html_e( 'Addresses', 'solseo' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $data['sections'] as $section => $count ) : ?>
			<tr>
				<td>
					<a href="<?php echo esc_url( home_url( '/sitemap-' . $section . '-1.xml' ) ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( 'sitemap-' . $section . '-1.xml' ); ?>
					</a>
				</td>
				<td><?php echo (int) $count; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
