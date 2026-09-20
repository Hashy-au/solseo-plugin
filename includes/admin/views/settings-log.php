<?php
/**
 * The change log.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'What changed', 'solseo' ); ?></h2>

	<?php if ( ! $data['entries'] ) : ?>
		<p><?php esc_html_e( 'Nothing yet. Editing an SEO field or a redirect writes a line here.', 'solseo' ); ?></p>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: %s: how many entries are kept. */
				esc_html__( 'The last %s changes this plugin made. A run that touches many pages is one line, not one line per page.', 'solseo' ),
				esc_html( number_format_i18n( $data['limit'] ) )
			);
			?>
		</p>

		<div class="table-wrap">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'What', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Where', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Who', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'When', 'solseo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['entries'] as $solseo_entry ) : ?>
						<tr>
							<td class="wrap">
								<?php echo esc_html( $solseo_entry['what'] ); ?>
								<?php if ( (int) $solseo_entry['count'] > 1 ) : ?>
									<strong>
										<?php
										printf(
											/* translators: %s: how many times. */
											esc_html__( '(%s times)', 'solseo' ),
											esc_html( number_format_i18n( (int) $solseo_entry['count'] ) )
										);
										?>
									</strong>
								<?php endif; ?>
							</td>
							<td class="wrap"><?php echo esc_html( $solseo_entry['label'] ); ?></td>
							<td><?php echo esc_html( $solseo_entry['who'] ); ?></td>
							<td><?php echo esc_html( wp_date( 'j M Y, g:ia', (int) $solseo_entry['when'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<form method="post" data-solseo-confirm="<?php esc_attr_e( 'Empty the change log?', 'solseo' ); ?>">
			<?php wp_nonce_field( 'solseo_log_clear', '_solseo_nonce' ); ?>
			<input type="hidden" name="solseo_log_clear" value="1">

			<p><button type="submit" class="button"><?php esc_html_e( 'Empty the log', 'solseo' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
