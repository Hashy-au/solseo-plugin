<?php
/**
 * Who may do what.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Who may do what', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'These sit on top of what WordPress already allows and can only narrow it. Somebody who cannot edit a post cannot edit its SEO fields, whatever this says. An administrator can always do everything, so a site cannot lock itself out of its own settings.', 'solseo' ); ?></p>

	<div class="table-wrap">
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'What', 'solseo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Who', 'solseo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['rows'] as $solseo_row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $solseo_row['label'] ); ?></strong>
							<p class="description"><?php echo esc_html( $solseo_row['blurb'] ); ?></p>
						</td>
						<td class="wrap"><?php echo esc_html( implode( ', ', $solseo_row['roles'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="description"><?php esc_html_e( 'Changing who holds each of these is part of SolSEO Pro.', 'solseo' ); ?></p>
</div>
