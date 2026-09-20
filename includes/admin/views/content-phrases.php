<?php
/**
 * Content, Phrases.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Screen;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Two pages going for one phrase', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'When two of your pages are aimed at the same phrase, the links and the signals split between them and Google picks one. It is usually not the one you would have picked.', 'solseo' ); ?></p>

		<p class="description"><?php esc_html_e( 'This reads the focus keyword on your published pages. Nothing is sent anywhere and nothing is changed.', 'solseo' ); ?></p>
	</div>

	<?php if ( ! $data['rows'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<p><?php esc_html_e( 'No two pages on the same side of your site are going for the same phrase.', 'solseo' ); ?></p>
		</div>
	<?php else : ?>
		<div class="solseo-card solseo-card-wide">
			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Phrase', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pages going for it', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<tr>
								<td class="wrap"><strong><?php echo esc_html( $row['phrase'] ); ?></strong></td>
								<td class="wrap">
									<ul class="solseo-plain-list">
										<?php foreach ( $row['competing'] as $one ) : ?>
											<li>
												<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $one['id'] ) ); ?>">
													<?php echo esc_html( $one['title'] ); ?>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>

									<?php if ( $row['alongside'] ) : ?>
										<span class="description">
											<?php
											printf(
												/* translators: %s: a list of page names. */
												esc_html__( '%s is going for it too, on the other side of the site, which is usually fine.', 'solseo' ),
												esc_html( implode( ', ', wp_list_pluck( $row['alongside'], 'title' ) ) )
											);
											?>
										</span>
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

	<?php if ( $data['alongside'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'A listing and an article, on the same phrase', 'solseo' ); ?></h2>

			<p><?php esc_html_e( 'These are not a problem. Somebody ready to buy and somebody still reading are asking different questions, and the results have room for both answers. They are here so you know this screen looked at them and decided.', 'solseo' ); ?></p>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Phrase', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pages', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['alongside'] as $row ) : ?>
							<tr>
								<td class="wrap"><?php echo esc_html( $row['phrase'] ); ?></td>
								<td class="wrap">
									<?php echo esc_html( implode( ', ', wp_list_pluck( $row['pages'], 'title' ) ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
<?php
// phpcs:enable
