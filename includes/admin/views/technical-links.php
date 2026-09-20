<?php
/**
 * Technical, Links checked.
 *
 * @package SolSEO
 */

use SolSEO\Links\Checker;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.

?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Links on this site that go nowhere', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'These are links from one of your pages to another address on your own site. Links going to other people\'s sites are not checked here, because that means asking their servers, over and over, on a schedule.', 'solseo' ); ?></p>

		<?php if ( ! $data['crawled'] ) : ?>
			<p><?php esc_html_e( 'Nothing has been checked yet. The crawl asks your site about each of these addresses and writes down what it said.', 'solseo' ); ?></p>

			<p><a class="button button-primary" href="<?php echo esc_url( $data['crawl_url'] ); ?>"><?php esc_html_e( 'Go to the crawl', 'solseo' ); ?></a></p>

			<?php if ( $data['suspects'] ) : ?>
				<h3><?php esc_html_e( 'Worth asking about', 'solseo' ); ?></h3>

				<p class="description"><?php esc_html_e( 'No page of yours was found at these addresses. That is not proof: an address can work without being a page. The crawl settles it.', 'solseo' ); ?></p>

				<ul class="solseo-plain-list">
					<?php foreach ( array_slice( array_keys( $data['suspects'] ), 0, 20 ) as $suspect ) : ?>
						<li><?php echo esc_html( $suspect ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php elseif ( ! $data['dead'] ) : ?>
			<p><?php esc_html_e( 'Every internal link on the pages that were crawled answered. That is a good result.', 'solseo' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Each one names the pages it is written on, and offers three ways out.', 'solseo' ); ?></p>

			<?php foreach ( $data['dead'] as $row ) : ?>
				<?php $fixes = Checker::fixes( $row, $row['sources'] ); ?>

				<div class="solseo-finding">
					<h3><?php echo esc_html( $row['url'] ); ?></h3>

					<p>
						<?php if ( 0 === $row['status_code'] ) : ?>
							<?php echo esc_html( '' === $row['note'] ? __( 'Your site did not answer for this address.', 'solseo' ) : $row['note'] ); ?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %d: an HTTP status code such as 404. */
								esc_html__( 'Your site answered %d for this address.', 'solseo' ),
								(int) $row['status_code']
							);
							?>
						<?php endif; ?>
						<?php echo esc_html( Checker::describe( $row, $row['sources'] ) ); ?>
					</p>

					<?php if ( ! $fixes ) : ?>
						<p class="description"><?php esc_html_e( 'Nothing links to it any more, so there is nothing to fix.', 'solseo' ); ?></p>
					<?php else : ?>
						<ul class="solseo-plain-list">
							<?php foreach ( $row['sources'] as $source ) : ?>
								<li>
									<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $source['id'] ) ); ?>">
										<?php
										printf(
											/* translators: %s: a page name. */
											esc_html__( 'Edit %s', 'solseo' ),
											esc_html( $source['title'] )
										);
										?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>

						<form method="post" class="solseo-inline-form">
							<?php wp_nonce_field( 'solseo_links_unlink', '_solseo_nonce' ); ?>
							<input type="hidden" name="solseo_links_path" value="<?php echo esc_attr( $row['path'] ); ?>">
							<button type="submit" class="button"><?php esc_html_e( 'Take the link out and keep the words', 'solseo' ); ?></button>
							<span class="description"><?php esc_html_e( 'The page as it is now stays in that page\'s revisions, so this can be undone in the editor.', 'solseo' ); ?></span>
						</form>

						<form method="post" class="solseo-inline-form">
							<?php wp_nonce_field( 'solseo_links_redirect', '_solseo_nonce' ); ?>
							<input type="hidden" name="solseo_links_path" value="<?php echo esc_attr( $row['path'] ); ?>">
							<label for="solseo-send-<?php echo esc_attr( md5( $row['path'] ) ); ?>"><?php esc_html_e( 'Or send this address to', 'solseo' ); ?></label>
							<input type="url" id="solseo-send-<?php echo esc_attr( md5( $row['path'] ) ); ?>" name="solseo_links_target" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
							<button type="submit" class="button"><?php esc_html_e( 'Send it there', 'solseo' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Links that go the long way', 'solseo' ); ?></h2>

		<?php if ( ! $data['chains'] ) : ?>
			<p><?php esc_html_e( 'No internal link takes more than one hop to arrive. One redirect is fine and every site has them.', 'solseo' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'These addresses redirect, and then redirect again. Pointing the link at the last address in the chain saves the visitor the wait and stops the hops eating into what the link is worth.', 'solseo' ); ?></p>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'The link points at', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'It ends up at', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Hops', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Written on', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['chains'] as $chain ) : ?>
							<tr>
								<td class="wrap"><?php echo esc_html( $chain['path'] ); ?></td>
								<td class="wrap">
									<?php if ( $chain['loops'] ) : ?>
										<?php esc_html_e( 'Nowhere. It comes back to where it started.', 'solseo' ); ?>
									<?php else : ?>
										<?php echo esc_html( end( $chain['hops'] ) ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( count( $chain['hops'] ) - 1 ) ); ?></td>
								<td class="wrap">
									<?php foreach ( $chain['sources'] as $source ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $source['id'] ) ); ?>"><?php echo esc_html( $source['title'] ); ?></a>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
