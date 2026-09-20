<?php
/**
 * Content, Links.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Pages that could link to this one', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'Pick a page. This reads the pages you have already published, finds the ones that mention what it is about and do not link to it, and shows you the sentence it would change.', 'solseo' ); ?></p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $data['page'] ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $data['tab'] ); ?>">

			<label for="solseo-suggestion-post"><?php esc_html_e( 'Page', 'solseo' ); ?></label>

			<select name="post" id="solseo-suggestion-post">
				<?php foreach ( $data['pages'] as $one ) : ?>
					<option value="<?php echo (int) $one['id']; ?>" <?php selected( (int) $one['id'], (int) $data['post_id'] ); ?>>
						<?php echo esc_html( $one['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Look', 'solseo' ); ?></button>
		</form>

		<?php if ( $data['post_id'] && '' !== $data['phrase'] ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: a focus keyword or a page title. */
					esc_html__( 'Looking for pages that mention "%s".', 'solseo' ),
					esc_html( $data['phrase'] )
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $data['suggestions'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'What it found', 'solseo' ); ?></h2>

			<div class="table-wrap">
				<table class="solseo-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Page', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'The sentence it would change', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Words to link', 'solseo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Do it', 'solseo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['suggestions'] as $one ) : ?>
							<tr>
								<td class="wrap">
									<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $one['source_id'] ) ); ?>">
										<?php echo esc_html( $one['title'] ); ?>
									</a>
								</td>
								<td class="wrap"><?php echo esc_html( $one['sentence'] ); ?></td>
								<td class="wrap"><code><?php echo esc_html( $one['anchor'] ); ?></code></td>
								<td>
									<form method="post">
										<?php wp_nonce_field( 'solseo_suggestion_accept', '_solseo_nonce' ); ?>
										<input type="hidden" name="solseo_source" value="<?php echo (int) $one['source_id']; ?>">
										<input type="hidden" name="solseo_target" value="<?php echo (int) $one['target_id']; ?>">
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Add this link', 'solseo' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p class="description"><?php esc_html_e( 'Adding one writes a real link into that page and stores a revision, so the page as it was is one click away in its editor. It never puts a link inside another link, a heading or a shortcode.', 'solseo' ); ?></p>
		</div>
	<?php elseif ( $data['post_id'] ) : ?>
		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'What it found', 'solseo' ); ?></h2>

			<p><?php esc_html_e( 'Nothing. No other published page of yours mentions this in a sentence that could carry a link, or the ones that do already link here.', 'solseo' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $data['already'] ) : ?>
		<div class="solseo-card">
			<h2><?php esc_html_e( 'Already linking here', 'solseo' ); ?></h2>

			<ul class="solseo-plain-list">
				<?php foreach ( $data['already'] as $one ) : ?>
					<li>
						<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $one['id'] ) ); ?>">
							<?php echo esc_html( $one['title'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="description"><?php esc_html_e( 'This is the history: a page you added a link from turns up here the next time this screen is drawn. Who added what and when is on the change log, under Settings.', 'solseo' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<?php
// phpcs:enable
