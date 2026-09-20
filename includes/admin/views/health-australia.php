<?php
/**
 * Health, Australia.
 *
 * @package SolSEO
 */

use SolSEO\Compliance\AU_Check;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Selling online from Australia', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'Six things an Australian business selling online is asked for, read out of this WordPress. Every row links to the page the rule is written on, because the useful part is knowing who asked, not the tick.', 'solseo' ); ?></p>

		<p class="description"><?php esc_html_e( 'Nothing is sent anywhere and nothing is changed.', 'solseo' ); ?></p>
	</div>

	<div class="solseo-card solseo-card-wide">
		<div class="table-wrap">
			<table class="solseo-table widefat">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Where it stands', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What was read', 'solseo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'The rule', 'solseo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['checks'] as $check ) : ?>
						<tr>
							<td class="wrap"><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
							<td><?php echo esc_html( AU_Check::status_label( (string) $check['status'] ) ); ?></td>
							<td class="wrap">
								<?php echo esc_html( (string) $check['says'] ); ?>

								<?php if ( 'consent' === $check['id'] && 'unknown' === $check['status'] ) : ?>
									<a href="<?php echo esc_url( (string) $data['tag_url'] ); ?>"><?php esc_html_e( 'Open Connections', 'solseo' ); ?></a>
								<?php endif; ?>
							</td>
							<td class="wrap">
								<a href="<?php echo esc_url( (string) $check['source'] ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( (string) $check['source_label'] ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="solseo-card solseo-card-wide">
		<p class="description"><?php echo esc_html( AU_Check::DISCLAIMER ); ?></p>
	</div>
</div>
<?php
// phpcs:enable
