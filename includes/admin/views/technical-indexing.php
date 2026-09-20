<?php
/**
 * Technical, Indexing.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Tell the search engines when a page changes', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'Normally a search engine finds a change when it next happens to crawl you, which on a small site can be weeks. IndexNow tells them the moment you press Update. One message reaches Bing, Yandex, Seznam and Naver, which all read the same address.', 'solseo' ); ?></p>

		<p class="description"><?php esc_html_e( 'Google does not take part. Nothing here speeds up Google, and anybody telling you otherwise is selling something.', 'solseo' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'solseo_indexing_save', '_solseo_nonce' ); ?>

			<p>
				<label>
					<input type="checkbox" name="solseo_indexnow_enabled" value="1" <?php checked( $data['on'] ); ?>>
					<?php esc_html_e( 'Tell them when a page is published or updated', 'solseo' ); ?>
				</label>
			</p>

			<?php submit_button( __( 'Save', 'solseo' ) ); ?>
		</form>

		<?php if ( $data['on'] ) : ?>
			<h3><?php esc_html_e( 'The key file', 'solseo' ); ?></h3>

			<p><?php esc_html_e( 'The engines check that the message came from whoever runs this site by reading a file at your root. This plugin makes it and serves it, so there is nothing for you to create.', 'solseo' ); ?></p>

			<p>
				<a href="<?php echo esc_url( $data['key_file'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $data['key_file'] ); ?></code></a>
			</p>

			<p class="description"><?php esc_html_e( 'Open it. If it shows a line of letters and numbers, everything is in order. If it shows anything else, a caching or security plugin is standing in front of it and submissions will be refused.', 'solseo' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="solseo-card">
		<h2><?php esc_html_e( 'How it behaves', 'solseo' ); ?></h2>

		<ul class="solseo-plain-list">
			<li><?php esc_html_e( 'Drafts, private pages and anything you have asked to stay out of search results are never sent.', 'solseo' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %d: a number of seconds. */
					esc_html__( 'The same page is not sent twice inside %d seconds, because saving fires more than once on its own.', 'solseo' ),
					(int) $data['floor']
				);
				?>
			</li>
			<li><?php esc_html_e( 'Editing forty products at once is one message, not forty.', 'solseo' ); ?></li>
		</ul>

		<?php if ( $data['on'] ) : ?>
			<h3><?php esc_html_e( 'Tell them about everything', 'solseo' ); ?></h3>

			<p class="description">
				<?php
				printf(
					/* translators: 1: how many pages would be sent, 2: the most that one press covers. */
					esc_html__( 'Worth doing once, after a move or the first time you switch this on. It sends the %1$d most recently changed pages, up to %2$d.', 'solseo' ),
					(int) $data['waiting'],
					(int) $data['cap']
				);
				?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'solseo_indexing_bulk', '_solseo_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Send them all now', 'solseo' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
