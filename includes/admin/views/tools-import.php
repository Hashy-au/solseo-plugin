<?php
/**
 * Copying an existing set of SEO fields across.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_sources = $data['sources'];
$solseo_chosen  = $data['chosen'];
$solseo_state   = $data['state'];
$solseo_source  = $solseo_chosen && isset( $solseo_sources[ $solseo_chosen ] ) ? $solseo_sources[ $solseo_chosen ] : null;
$solseo_rows    = $data['preview'];

$solseo_ran      = $solseo_chosen && 'done' === $solseo_state['status'] && isset( $solseo_state['args']['source'] ) && $solseo_state['args']['source'] === $solseo_chosen;
$solseo_clean    = $solseo_ran && $solseo_state['verified'] && ! $solseo_state['failed_total'];
$solseo_can_stop = $solseo_source && $solseo_source['plugin'] && current_user_can( 'activate_plugins' );

/*
 * Everything in the family that is running, not just the first file. An add-on
 * left on with the plugin under it switched off fatals the site on the next
 * request.
 */
$solseo_stop_files = $solseo_source && ! empty( $solseo_source['plugins'] ) ? (array) $solseo_source['plugins'] : array();
$solseo_also       = array();

foreach ( array_slice( $solseo_stop_files, 1 ) as $solseo_stop_file ) {
	$solseo_also[] = \SolSEO\Tools\Import::name( array( $solseo_stop_file ), '' );
}
?>
<div class="solseo-card solseo-card-wide">

	<?php $solseo_off = \SolSEO\Admin\Tools_Screen::switched_off(); ?>

	<?php if ( '' !== $solseo_off ) : ?>
		<div class="solseo-note solseo-handover">
			<p><strong><?php echo esc_html( $solseo_off ); ?></strong></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $solseo_sources ) : ?>
		<h2><?php esc_html_e( 'Nothing to import', 'solseo' ); ?></h2>
		<p><?php esc_html_e( 'No SEO fields from another plugin were found on this site.', 'solseo' ); ?></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Copy existing SEO fields', 'solseo' ); ?></h2>

		<p class="description"><?php esc_html_e( 'Titles, descriptions, keywords, canonical addresses and visibility settings are copied across. The originals are left untouched, so you can switch back at any time.', 'solseo' ); ?></p>

		<?php if ( count( $solseo_sources ) > 1 ) : ?>
			<p class="solseo-source-picker">
				<?php foreach ( $solseo_sources as $solseo_prefix => $solseo_entry ) : ?>
					<a
						class="button<?php echo $solseo_prefix === $solseo_chosen ? ' button-primary' : ''; ?>"
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page'   => 'solseo-tools',
									'tab'    => 'import',
									'source' => $solseo_prefix,
								),
								admin_url( 'admin.php' )
							)
						);
						?>
								"
					><?php echo esc_html( $solseo_entry['name'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ( $solseo_source ) : ?>
			<p class="solseo-summary">
				<?php
				printf(
					/* translators: 1: plugin name, 2: number of pages. */
					esc_html( _n( '%1$s holds SEO fields on %2$s page.', '%1$s holds SEO fields on %2$s pages.', (int) $solseo_source['found'], 'solseo' ) ),
					'<strong>' . esc_html( $solseo_source['name'] ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $solseo_source['found'] ) ) . '</strong>'
				);
				?>
			</p>

			<?php if ( $solseo_rows ) : ?>
				<details class="solseo-disclosure">
					<summary><?php esc_html_e( 'See what would change', 'solseo' ); ?></summary>

					<div class="table-wrap">
						<table class="widefat striped solseo-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Page', 'solseo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Field', 'solseo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Coming in', 'solseo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Already here', 'solseo' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $solseo_rows as $solseo_row ) : ?>
									<tr>
										<td class="wrap"><?php echo esc_html( $solseo_row['title'] ); ?></td>
										<td><?php echo esc_html( $solseo_row['field'] ); ?></td>
										<td class="wrap"><?php echo esc_html( is_bool( $solseo_row['value'] ) ? '' : (string) $solseo_row['value'] ); ?></td>
										<td class="wrap"><?php echo esc_html( (string) $solseo_row['current'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<p class="description"><?php esc_html_e( 'The first few pages, so you can see the shape of it before anything is written.', 'solseo' ); ?></p>
				</details>
			<?php endif; ?>

			<?php
			ob_start();
			?>
			<p>
				<label>
					<input type="checkbox" data-solseo-arg="overwrite" value="1">
					<?php esc_html_e( 'Replace fields SolSEO already holds', 'solseo' ); ?>
				</label>
			</p>
			<?php
			$solseo_fields = ob_get_clean();

			\SolSEO\Admin\Screen::view(
				'job-progress',
				array(
					'job'    => 'import',
					'start'  => __( 'Copy them all across', 'solseo' ),
					'args'   => array( 'source' => $solseo_chosen ),
					'fields' => $solseo_fields,
					'reload' => true,
				)
			);
			?>

			<?php if ( $solseo_ran ) : ?>
				<div class="solseo-note">
					<p><strong><?php echo esc_html( $solseo_state['message'] ); ?></strong></p>

					<?php if ( ! $solseo_clean && $solseo_state['failed'] ) : ?>
						<ul class="solseo-failed">
							<?php foreach ( array_slice( $solseo_state['failed'], 0, 20 ) as $solseo_id ) : ?>
								<li>
									<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $solseo_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $solseo_id ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>

						<p class="description"><?php esc_html_e( 'The offer to switch the other plugin off is not shown while anything is outstanding, because that offer is a promise that nothing was left behind.', 'solseo' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $solseo_clean && $solseo_can_stop ) : ?>
				<div class="solseo-note solseo-handover">
					<h3><?php esc_html_e( 'Two plugins, one set of tags', 'solseo' ); ?></h3>

					<p>
						<?php
						printf(
							/* translators: %s: the name of the other plugin. */
							esc_html__( '%s is still switched on. Both plugins are writing title tags and meta descriptions, so this site is sending Google two of each. Now that everything is copied and checked, it is safe to switch off.', 'solseo' ),
							'<strong>' . esc_html( $solseo_source['name'] ) . '</strong>'
						);
						?>
					</p>

					<?php if ( $solseo_also ) : ?>
						<p>
							<?php
							printf(
								/* translators: 1: the add-ons that go off too, 2: the name of the other plugin. */
								esc_html__( '%1$s goes off with it, because it cannot run with %2$s switched off.', 'solseo' ),
								'<strong>' . esc_html( implode( ', ', $solseo_also ) ) . '</strong>',
								esc_html( $solseo_source['name'] )
							);
							?>
						</p>
					<?php endif; ?>

					<form
						method="post"
						data-solseo-confirm="<?php echo esc_attr( sprintf( /* translators: %s: the name of the other plugin. */ __( 'Switch off %s? Nothing belonging to it is deleted. Every field it stores stays where it is, and you can switch it back on from the Plugins screen at any time.', 'solseo' ), $solseo_also ? $solseo_source['name'] . ' and ' . implode( ', ', $solseo_also ) : $solseo_source['name'] ) ); ?>"
					>
						<?php wp_nonce_field( 'solseo_deactivate', '_solseo_nonce' ); ?>
						<?php foreach ( $solseo_stop_files as $solseo_stop_file ) : ?>
							<input type="hidden" name="solseo_deactivate[]" value="<?php echo esc_attr( $solseo_stop_file ); ?>">
						<?php endforeach; ?>

						<p>
							<button type="submit" class="button button-primary">
								<?php
								printf(
									/* translators: %s: the name of the other plugin. */
									esc_html__( 'Switch off %s', 'solseo' ),
									esc_html( $solseo_source['name'] )
								);
								?>
							</button>
						</p>
					</form>

					<p class="description"><?php esc_html_e( 'Nothing of theirs is deleted. Switching it back on puts the site as it was.', 'solseo' ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
