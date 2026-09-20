<?php
/**
 * Health, Findings.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'What your shopping feed will come back refused for', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'A feed goes to Google and between one and three days later part of it comes back disapproved, with a reason written for somebody who builds feeds for a living. Every one of the twelve things below can be known from the products themselves, now.', 'solseo' ); ?></p>

		<?php if ( $data['owners'] ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: the name of a plugin. */
					esc_html__( 'Your feed is made by %s. This reads your products and changes nothing: not the feed, not that plugin\'s settings, and not the products.', 'solseo' ),
					esc_html( implode( ', ', wp_list_pluck( $data['owners'], 'name' ) ) )
				);
				?>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No plugin on this site looks like it makes a shopping feed. The checks below still apply: they are about the products, and whatever ends up sending them will send these.', 'solseo' ); ?></p>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'solseo_feed_audit', '_solseo_nonce' ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Look at my products', 'solseo' ); ?></button>
		</form>

		<?php if ( '' !== $data['trouble'] ) : ?>
			<p class="description"><?php echo esc_html( $data['trouble'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $data['report'] ) : ?>
		<?php $report = $data['report']; ?>

		<div class="solseo-card">
			<h2><?php esc_html_e( 'What it found', 'solseo' ); ?></h2>

			<?php if ( ! $report['rows'] ) : ?>
				<p><?php esc_html_e( 'Nothing. Every product it read carries what a feed row needs.', 'solseo' ); ?></p>
			<?php else : ?>
				<ul class="solseo-plain-list">
					<?php foreach ( $report['totals'] as $rule => $count ) : ?>
						<li>
							<?php
							echo esc_html(
								( isset( $data['rules'][ $rule ] ) ? $data['rules'][ $rule ] : $rule )
								. ': ' . number_format_i18n( (int) $count )
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="description">
				<?php
				printf(
					/* translators: 1: how many products were read. 2: how many published products there are. */
					esc_html__( 'Read %1$s of your %2$s published products.', 'solseo' ),
					esc_html( number_format_i18n( (int) $report['read'] ) ),
					esc_html( number_format_i18n( (int) $report['total'] ) )
				);
				?>
			</p>
		</div>

		<?php if ( $report['rows'] ) : ?>
			<div class="solseo-card solseo-card-wide">
				<h2><?php esc_html_e( 'Every product worth a look', 'solseo' ); ?></h2>

				<div class="table-wrap">
					<table class="solseo-table widefat">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Product', 'solseo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What is wrong', 'solseo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What to do', 'solseo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $report['rows'], 0, (int) $data['per_page'] ) as $row ) : ?>
								<tr>
									<td class="wrap">
										<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['id'] ) ); ?>">
											<?php echo esc_html( (string) $row['title'] ); ?>
										</a>
									</td>
									<td class="wrap">
										<strong><?php echo esc_html( (string) $row['label'] ); ?></strong>
										<span class="description"><?php echo esc_html( (string) $row['says'] ); ?></span>
									</td>
									<td class="wrap"><?php echo esc_html( (string) $row['fix'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ( count( $report['rows'] ) > (int) $data['per_page'] ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: how many are listed. 2: how many there are. */
							esc_html__( 'The first %1$s of %2$s. The counts above are the whole of what it read.', 'solseo' ),
							esc_html( number_format_i18n( (int) $data['per_page'] ) ),
							esc_html( number_format_i18n( count( $report['rows'] ) ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="solseo-card solseo-card-wide">
		<p class="description">
			<?php esc_html_e( 'Every rule here is from Google\'s own product data specification.', 'solseo' ); ?>
			<a href="<?php echo esc_url( (string) $data['spec'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Read it', 'solseo' ); ?></a>
		</p>
	</div>
</div>
<?php
// phpcs:enable
