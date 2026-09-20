<?php
/**
 * One row per module, with what it adds to this screen beside it.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'What this site is running', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'Switching something off stops it running. Nothing it has already written is removed, so switching it back on picks up where it left off.', 'solseo' ); ?></p>

	<table class="solseo-modules">
		<tbody>
			<tr class="solseo-module solseo-module-core">
				<td class="solseo-module-switch"><span class="solseo-always"><?php esc_html_e( 'Always on', 'solseo' ); ?></span></td>
				<td>
					<strong><?php esc_html_e( 'Core', 'solseo' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Titles, meta, sitemaps, structured data, the score, redirects, robots.txt and the importer. This is what the plugin is.', 'solseo' ); ?></p>
				</td>
			</tr>

			<?php foreach ( $data['rows'] as $solseo_slug => $solseo_row ) : ?>
				<tr class="solseo-module">
					<td class="solseo-module-switch">
						<label>
							<input type="checkbox" name="solseo_modules[]" value="<?php echo esc_attr( $solseo_slug ); ?>" <?php checked( $solseo_row['enabled'] ); ?>>
							<span class="screen-reader-text"><?php echo esc_html( $solseo_row['label'] ); ?></span>
						</label>
					</td>
					<td>
						<strong><?php echo esc_html( $solseo_row['label'] ); ?></strong>

						<?php if ( $solseo_row['blurb'] ) : ?>
							<p class="description"><?php echo esc_html( $solseo_row['blurb'] ); ?></p>
						<?php endif; ?>

						<p class="solseo-module-cost">
							<?php
							if ( ! $solseo_row['enabled'] ) {
								esc_html_e( 'Off. It adds nothing to any page.', 'solseo' );
							} elseif ( null === $solseo_row['hooks'] ) {
								esc_html_e( 'On.', 'solseo' );
							} else {
								printf(
									/* translators: %s: a number of hooks. */
									esc_html( _n( 'On. It added %s hook to this screen.', 'On. It added %s hooks to this screen.', (int) $solseo_row['hooks'], 'solseo' ) ),
									esc_html( number_format_i18n( (int) $solseo_row['hooks'] ) )
								);
							}

							if ( $solseo_row['facts'] ) {
								echo ' ' . esc_html( $solseo_row['facts'] );
							}
							?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( 'The count beside each one is measured while this screen loads, on this site, rather than taken from a table we wrote. It is what the module adds to an admin request.', 'solseo' ); ?>
	</p>
</div>

<?php if ( $data['upsell'] ) : ?>
	<p class="solseo-more-modules">
		<?php esc_html_e( 'More modules come with SolSEO Pro, Pro Shop and Pro Complete.', 'solseo' ); ?>
		<a href="<?php echo esc_url( $data['upgrade'] ); ?>"><?php esc_html_e( 'See what they do', 'solseo' ); ?></a>
	</p>
<?php endif; ?>
