<?php
/**
 * Health, Media.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Screen;
use SolSEO\Media\Report;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.

$report = $data['report'];
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'What your images cost', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'This reads every published page and looks at the images on it: whether the file is bigger than the space it is drawn in, whether the tag says how big it is, whether anything says what it is, and whether anything uses it at all.', 'solseo' ); ?></p>

		<p class="description"><?php esc_html_e( 'It changes nothing. No image is resized, converted, renamed or deleted, and nothing is written to your media library.', 'solseo' ); ?></p>

		<?php
		Screen::view(
			'job-progress',
			array(
				'job'    => 'media',
				'start'  => __( 'Look at my images', 'solseo' ),
				'reload' => true,
			)
		);
		?>
	</div>

	<?php if ( $report['finished'] ) : ?>
		<div class="solseo-card">
			<h2><?php esc_html_e( 'What it found', 'solseo' ); ?></h2>

			<?php if ( ! $data['found'] ) : ?>
				<p><?php esc_html_e( 'Nothing worth changing.', 'solseo' ); ?></p>
			<?php else : ?>
				<ul class="solseo-plain-list">
					<?php foreach ( $report['totals'] as $kind => $count ) : ?>
						<?php if ( ! $count ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<li><?php echo esc_html( Report::kind_label( $kind ) . ': ' . number_format_i18n( (int) $count ) ); ?></li>
					<?php endforeach; ?>
				</ul>

				<?php if ( (int) $report['saving'] > 0 ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: a file size such as 4.2 MB. */
							esc_html__( 'Drawing every oversized image at the size it is shown at would save about %s across those pages.', 'solseo' ),
							esc_html( $data['saving'] )
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<p class="description">
				<?php
				printf(
					/* translators: 1: a number of pages. 2: a date. */
					esc_html__( 'Read %1$s pages, last on %2$s.', 'solseo' ),
					esc_html( number_format_i18n( (int) $report['pages'] ) ),
					esc_html( wp_date( 'j F Y', (int) $report['finished'] ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $data['rows'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'Every image worth a look', 'solseo' ); ?></h2>

			<?php if ( $data['found'] > $data['kept'] ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: how many are listed. 2: how many there are. */
						esc_html__( 'The first %1$s of %2$s. The counts above are the whole of it.', 'solseo' ),
						esc_html( number_format_i18n( (int) $data['kept'] ) ),
						esc_html( number_format_i18n( (int) $data['found'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Image', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What is wrong', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Would save', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'On', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<tr>
								<td class="wrap">
									<?php if ( $row['id'] ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['id'] ) ); ?>"><?php echo esc_html( basename( (string) $row['src'] ) ); ?></a>
									<?php else : ?>
										<?php echo esc_html( basename( (string) $row['src'] ) ); ?>
									<?php endif; ?>
								</td>
								<td class="wrap">
									<strong><?php echo esc_html( Report::kind_label( (string) $row['kind'] ) ); ?></strong>
									<span class="description"><?php echo esc_html( (string) $row['says'] ); ?></span>
								</td>
								<td><?php echo esc_html( (int) $row['saving'] ? Report::readable( (int) $row['saving'] ) : '' ); ?></td>
								<td class="wrap">
									<?php if ( ! empty( $row['post_id'] ) ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] ); ?>
		</div>
	<?php endif; ?>
</div>
