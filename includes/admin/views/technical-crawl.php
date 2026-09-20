<?php
/**
 * Technical, Crawl.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Screen;
use SolSEO\Crawl\Crawler;
use SolSEO\Crawl\Pages;
use SolSEO\Crawl\Selection;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.

$summary = $data['summary'];
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Read this site the way a search engine does', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'This asks your own server for your own pages, one at a time, and writes down what came back: whether the page answered, where it sent the visitor if it did not, what it calls itself, and how much of it is writing.', 'solseo' ); ?></p>

		<p class="description">
			<?php
			printf(
				/* translators: 1: a number of pages. 2: a number of minutes. 3: a number of days. */
				esc_html__( 'Up to %1$d pages, which at this pace takes about %2$d minutes with this tab open. One crawl every %3$d days.', 'solseo' ),
				(int) Crawler::BUDGET,
				(int) $data['minutes'],
				(int) $data['every_days']
			);
			?>
		</p>

		<?php if ( $data['may_start'] ) : ?>
			<?php
			Screen::view(
				'job-progress',
				array(
					'job'    => 'crawl',
					'start'  => __( 'Start reading', 'solseo' ),
					'reload' => true,
				)
			);
			?>
		<?php else : ?>
			<p>
				<?php
				printf(
					/* translators: %s: a date. */
					esc_html__( 'The last crawl finished recently. The next one can start on %s.', 'solseo' ),
					esc_html( wp_date( 'j F Y', (int) $data['next_at'] ) )
				);
				?>
			</p>
			<p class="description"><?php esc_html_e( 'What the last one found is still below.', 'solseo' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="solseo-card">
		<h2><?php esc_html_e( 'How fast to ask', 'solseo' ); ?></h2>

		<p class="description"><?php esc_html_e( 'Every page this reads is a page your server has to build, on top of whatever your visitors are doing. Slower is kinder and takes longer. There is no setting for as fast as possible.', 'solseo' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'solseo_crawl_pace', '_solseo_nonce' ); ?>

			<p>
				<label for="solseo-crawl-pace"><?php esc_html_e( 'Pages a minute', 'solseo' ); ?></label>
				<select id="solseo-crawl-pace" name="solseo_crawl_per_minute">
					<?php foreach ( $data['paces'] as $rate => $says ) : ?>
						<option value="<?php echo esc_attr( $rate ); ?>" <?php selected( (int) $rate, (int) $data['per_minute'] ); ?>>
							<?php echo esc_html( sprintf( '%1$d a minute, %2$s', (int) $rate, $says ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php submit_button( __( 'Save', 'solseo' ) ); ?>
		</form>
	</div>

	<?php if ( $data['crawled'] > 0 ) : ?>
		<div class="solseo-card">
			<h2><?php esc_html_e( 'What came back', 'solseo' ); ?></h2>

			<p><?php echo esc_html( $data['sentence'] ); ?></p>

			<?php
			$bands = array(
				'ok'          => (int) $summary['ok'],
				'moved'       => (int) $summary['moved'],
				'gone'        => (int) $summary['gone'] + (int) $summary['broken'],
				'unreachable' => (int) $summary['unreachable'],
				'waiting'     => (int) $summary['waiting'],
			);
			?>

			<ul class="solseo-plain-list">
				<?php foreach ( $bands as $band => $count ) : ?>
					<?php if ( ! $count ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'band'  => $band,
									'paged' => 1,
								)
							)
						);
						?>
									"><?php echo esc_html( Pages::band_label( $band ) ); ?></a>
						<?php echo esc_html( number_format_i18n( $count ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $summary['links'] > 0 ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: a number of addresses. */
						esc_html__( 'Plus %s addresses your pages link to, which were checked but not read.', 'solseo' ),
						esc_html( number_format_i18n( (int) $summary['links'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $data['band'] ) : ?>
				<p><a href="<?php echo esc_url( remove_query_arg( array( 'band', 'paged' ) ) ); ?>"><?php esc_html_e( 'Show everything', 'solseo' ); ?></a></p>
			<?php endif; ?>
		</div>

		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'Every address', 'solseo' ); ?></h2>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Address', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Answer', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Title', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Words', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Chosen because', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<tr>
								<td class="wrap"><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['url'] ); ?></a></td>
								<td>
									<?php if ( null === $row['fetched_at'] ) : ?>
										<?php esc_html_e( 'Not looked at yet', 'solseo' ); ?>
									<?php elseif ( 0 === (int) $row['status_code'] ) : ?>
										<?php echo esc_html( '' === $row['note'] ? __( 'No answer', 'solseo' ) : $row['note'] ); ?>
									<?php else : ?>
										<?php echo esc_html( (int) $row['status_code'] ); ?>
										<?php if ( '' !== $row['redirect_to'] ) : ?>
											<span class="description"><?php echo esc_html( $row['redirect_to'] ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td class="wrap"><?php echo esc_html( $row['title'] ); ?></td>
								<td><?php echo esc_html( (int) $row['words'] ? number_format_i18n( (int) $row['words'] ) : '' ); ?></td>
								<td><?php echo esc_html( Selection::rule_label( (string) $row['chosen_by'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] ); ?>
		</div>
	<?php endif; ?>
</div>
