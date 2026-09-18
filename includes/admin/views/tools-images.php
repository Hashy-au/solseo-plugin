<?php
/**
 * Alt text for the images that have none.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_missing = (int) $data['missing'];
$solseo_rows    = $data['rows'];
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Images with nothing written about them', 'solseo' ); ?></h2>

	<?php if ( ! $solseo_missing ) : ?>
		<p><?php esc_html_e( 'Every image in the library has alt text.', 'solseo' ); ?></p>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: %s: number of images with no alt text. */
				esc_html( _n( '%s image has no alt text.', '%s images have no alt text.', $solseo_missing, 'solseo' ) ),
				'<strong>' . esc_html( number_format_i18n( $solseo_missing ) ) . '</strong>'
			);
			?>
		</p>

		<p class="description"><?php esc_html_e( 'The text is taken from the post or product the image is attached to, or from a tidied up file name. Anything already written is left alone.', 'solseo' ); ?></p>

		<?php
		\SolSEO\Admin\Screen::view(
			'job-progress',
			array(
				'job'    => 'images',
				'start'  => __( 'Describe every image', 'solseo' ),
				'reload' => true,
			)
		);
		?>

		<?php if ( $solseo_rows ) : ?>
			<h3><?php esc_html_e( 'Or choose which ones', 'solseo' ); ?></h3>

			<p class="description"><?php esc_html_e( 'What we would write is beside each image. Untick anything you would rather write yourself, then describe the rest.', 'solseo' ); ?></p>

			<div class="solseo-job" data-solseo-job="images" data-solseo-reload>
				<div class="table-wrap">
					<table class="widefat striped solseo-table solseo-image-table">
						<thead>
							<tr>
								<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Include', 'solseo' ); ?></span></th>
								<th scope="col"><?php esc_html_e( 'Image', 'solseo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What we would write', 'solseo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $solseo_rows as $solseo_row ) : ?>
								<tr>
									<td class="check-column">
										<input
											type="checkbox"
											data-solseo-arg="only[]"
											value="<?php echo (int) $solseo_row['id']; ?>"
											<?php checked( '' !== $solseo_row['suggestion'] ); ?>
											<?php disabled( '' === $solseo_row['suggestion'] ); ?>
										>
									</td>
									<td>
										<?php if ( $solseo_row['thumb'] ) : ?>
											<img src="<?php echo esc_url( (string) $solseo_row['thumb'] ); ?>" alt="" width="48" height="48" class="solseo-thumb">
										<?php endif; ?>
										<span class="solseo-image-name"><?php echo esc_html( $solseo_row['title'] ); ?></span>
									</td>
									<td class="wrap">
										<?php if ( '' === $solseo_row['suggestion'] ) : ?>
											<em><?php esc_html_e( 'Nothing to go on: no title, no parent page, and a file name that reads as nothing. Worth writing this one yourself.', 'solseo' ); ?></em>
										<?php else : ?>
											<?php echo esc_html( $solseo_row['suggestion'] ); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<p class="solseo-job-buttons">
					<button type="button" class="button" data-solseo-job-start><?php esc_html_e( 'Describe the ticked images', 'solseo' ); ?></button>
					<button type="button" class="button" data-solseo-job-stop hidden><?php esc_html_e( 'Stop', 'solseo' ); ?></button>
				</p>

				<div class="solseo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<span class="solseo-progress-bar" data-solseo-job-bar></span>
				</div>

				<p class="solseo-job-status" data-solseo-job-message aria-live="polite"></p>
				<p class="description solseo-job-counts" data-solseo-job-counts></p>
			</div>

			<?php if ( count( $solseo_rows ) < $solseo_missing ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: number of images shown in the table. */
						esc_html__( 'Showing the first %s. The button above the table works through the whole library.', 'solseo' ),
						esc_html( number_format_i18n( count( $solseo_rows ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
