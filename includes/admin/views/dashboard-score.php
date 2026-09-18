<?php
/**
 * Average content score and the pages that need the most work.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$bands = $data['summary']['bands'];
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Content score', 'solseo' ); ?></h2>

	<div class="solseo-score-summary">
		<div class="solseo-dial solseo-band-<?php echo esc_attr( $data['band'] ); ?>" style="--solseo-dial:<?php echo esc_attr( (int) $data['summary']['average'] ); ?>">
			<span class="solseo-dial-value"><?php echo (int) $data['summary']['average']; ?></span>
		</div>

		<div class="solseo-score-legend">
			<p class="solseo-score-band"><?php echo esc_html( $data['label'] ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: %d: number of pages with a score. */
					esc_html( _n( 'Average across %d page.', 'Average across %d pages.', (int) $data['summary']['total'], 'solseo' ) ),
					(int) $data['summary']['total']
				);
				?>
			</p>

			<ul class="solseo-band-counts">
				<li><span class="solseo-dot solseo-band-excellent"></span><?php esc_html_e( 'Excellent', 'solseo' ); ?> <strong><?php echo (int) $bands['excellent']; ?></strong></li>
				<li><span class="solseo-dot solseo-band-good"></span><?php esc_html_e( 'Good', 'solseo' ); ?> <strong><?php echo (int) $bands['good']; ?></strong></li>
				<li><span class="solseo-dot solseo-band-fair"></span><?php esc_html_e( 'Fair', 'solseo' ); ?> <strong><?php echo (int) $bands['fair']; ?></strong></li>
				<li><span class="solseo-dot solseo-band-poor"></span><?php esc_html_e( 'Needs work', 'solseo' ); ?> <strong><?php echo (int) $bands['poor']; ?></strong></li>
			</ul>
		</div>
	</div>

	<?php if ( $data['weakest'] ) : ?>
		<h3><?php esc_html_e( 'Worth looking at first', 'solseo' ); ?></h3>
		<table class="solseo-table">
			<tbody>
			<?php foreach ( $data['weakest'] as $row ) : ?>
				<tr>
					<td>
						<?php if ( $row['edit'] ) : ?>
							<a href="<?php echo esc_url( $row['edit'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $row['title'] ); ?>
						<?php endif; ?>
					</td>
					<td class="solseo-cell-score">
						<span class="solseo-pill solseo-band-<?php echo esc_attr( \SolSEO\Analysis\Analyser::band( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $data['unscored'] ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: number of pages with no score yet. */
				esc_html( _n( '%s published page has never been scored.', '%s published pages have never been scored.', (int) $data['unscored'], 'solseo' ) ),
				'<strong>' . esc_html( number_format_i18n( (int) $data['unscored'] ) ) . '</strong>'
			);
			?>
		</p>

		<?php
		\SolSEO\Admin\Screen::view(
			'job-progress',
			array(
				'job'    => 'score',
				'start'  => __( 'Score every page that has none', 'solseo' ),
				'args'   => array( 'scope' => 'missing' ),
				'reload' => true,
			)
		);
		?>
	<?php endif; ?>
</div>
