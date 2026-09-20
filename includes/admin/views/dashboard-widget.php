<?php
/**
 * The dashboard widget.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<div class="solseo-widget">

	<div class="solseo-widget-score">
		<div class="solseo-dial solseo-dial-small solseo-band-<?php echo esc_attr( $data['band'] ); ?>" style="--solseo-dial:<?php echo (int) $data['summary']['average']; ?>">
			<span class="solseo-dial-value"><?php echo (int) $data['summary']['average']; ?></span>
		</div>
		<div>
			<strong><?php echo esc_html( $data['label'] ); ?></strong>
			<span class="description">
				<?php
				printf(
					/* translators: %d: number of pages with a score. */
					esc_html( _n( 'Average across %d page', 'Average across %d pages', (int) $data['summary']['total'], 'solseo' ) ),
					(int) $data['summary']['total']
				);
				?>
			</span>
		</div>
	</div>

	<?php if ( $data['weakest'] ) : ?>
		<ul class="solseo-widget-list">
			<?php foreach ( $data['weakest'] as $row ) : ?>
				<li>
					<span class="solseo-pill solseo-band-<?php echo esc_attr( \SolSEO\Analysis\Analyser::band( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span>
					<?php if ( $row['edit'] ) : ?>
						<a href="<?php echo esc_url( $row['edit'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $row['title'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $data['connected'] ) : ?>
		<?php if ( $data['sites'] ) : ?>
			<h4><?php esc_html_e( 'Your sites', 'solseo' ); ?></h4>
			<table class="solseo-table">
				<tbody>
				<?php foreach ( $data['sites'] as $site ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $site['name'] ) ? $site['name'] : $site['home_url'] ); ?></td>
						<td class="solseo-cell-score">
							<?php if ( isset( $site['health_score'] ) ) : ?>
								<span class="solseo-pill solseo-band-<?php echo esc_attr( \SolSEO\Analysis\Analyser::band( (int) $site['health_score'] ) ); ?>"><?php echo (int) $site['health_score']; ?></span>
							<?php endif; ?>
						</td>
						<td class="solseo-cell-score">
							<?php if ( isset( $site['keywords'] ) ) : ?>
								<?php
								printf(
									/* translators: %d: number of tracked keywords. */
									esc_html( _n( '%d keyword', '%d keywords', (int) $site['keywords'], 'solseo' ) ),
									(int) $site['keywords']
								);
								?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="solseo-widget-footer">
			<a href="https://solseo.com.au/app" target="_blank" rel="noopener"><?php esc_html_e( 'Open the SolSEO dashboard', 'solseo' ); ?></a>
		</p>
	<?php else : ?>
		<p class="solseo-widget-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-settings&tab=connections' ) ); ?>"><?php esc_html_e( 'Connect a SolSEO account', 'solseo' ); ?></a>
			<span class="description"><?php esc_html_e( 'for rank tracking and site audits', 'solseo' ); ?></span>
		</p>
	<?php endif; ?>
</div>
