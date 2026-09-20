<?php
/**
 * Health, Accessibility.
 *
 * @package SolSEO
 */

use SolSEO\A11y\Editor_Checks;
use SolSEO\Admin\Screen;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'What the writing makes hard to read', 'solseo' ); ?></h2>

		<p><?php echo esc_html( $data['covers'] ); ?></p>

		<p class="description">
			<?php
			printf(
				/* translators: 1: how many pages this screen read. 2: how many published pages there are. */
				esc_html__( 'Read %1$s of your %2$s published pages, just now. Use the pager to read the next set.', 'solseo' ),
				esc_html( number_format_i18n( (int) $data['read'] ) ),
				esc_html( number_format_i18n( (int) $data['total'] ) )
			);
			?>
		</p>
	</div>

	<?php if ( ! $data['rows'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<p><?php esc_html_e( 'Nothing on these pages. Every image says what it is, the headings go in order, the links say where they go, and the tables and fields are named.', 'solseo' ); ?></p>

			<?php Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] ); ?>
		</div>
	<?php else : ?>
		<div class="solseo-card solseo-card-wide">
			<h2>
				<?php
				printf(
					/* translators: %s: a number of findings. */
					esc_html__( '%s to look at on these pages', 'solseo' ),
					esc_html( number_format_i18n( (int) $data['found'] ) )
				);
				?>
			</h2>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Page', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What is wrong', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Where', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Rule', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<?php foreach ( $row['findings'] as $index => $finding ) : ?>
								<tr>
									<td class="wrap">
										<?php if ( 0 === $index ) : ?>
											<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['id'] ) ); ?>">
												<?php echo esc_html( $row['title'] ); ?>
											</a>
										<?php endif; ?>
									</td>
									<td class="wrap">
										<strong><?php echo esc_html( Editor_Checks::rule_label( (string) $finding['rule'] ) ); ?></strong>
										<span class="description"><?php echo esc_html( (string) $finding['says'] ); ?></span>
									</td>
									<td class="wrap"><code><?php echo esc_html( (string) $finding['element'] ); ?></code></td>
									<td><?php echo esc_html( (string) $finding['guideline'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] ); ?>
		</div>
	<?php endif; ?>

	<div class="solseo-card solseo-card-wide">
		<p class="description"><?php esc_html_e( 'The rule numbers are the Web Content Accessibility Guidelines, which are the thing Australian government and education sites are measured against. Fixing what is listed here fixes what the writing caused, and says nothing about the rest of the site.', 'solseo' ); ?></p>
	</div>
</div>
<?php
// phpcs:enable
