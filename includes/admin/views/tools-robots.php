<?php
/**
 * The robots.txt screen: what is served, a few ready made sets, and the
 * way back to how it was.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_rules    = $data['rules'];
$solseo_disk     = $data['on_disk'];
$solseo_takeover = $data['takeover'];
$solseo_backup   = $data['backup'];
$solseo_moved    = \SolSEO\Robots_Backup::moved_file();
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'robots.txt', 'solseo' ); ?></h2>

	<p class="description"><?php esc_html_e( 'This file asks crawlers where they may go. It is a request, not a lock: the well behaved ones follow it and the rest do not.', 'solseo' ); ?></p>

	<?php if ( ! $data['public'] ) : ?>
		<div class="solseo-warning">
			<p><strong><?php esc_html_e( 'This site is set to discourage search engines.', 'solseo' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: %s: link to the WordPress reading settings. */
					esc_html__( 'While that setting is on, WordPress serves a robots.txt asking every crawler to stay off the whole site, and none of the lines below are served at all. The setting is at %s.', 'solseo' ),
					'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings, Reading', 'solseo' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( null !== $solseo_disk ) : ?>
		<div class="solseo-warning">
			<p><strong><?php esc_html_e( 'There is a robots.txt file on the server.', 'solseo' ); ?></strong></p>
			<p><?php esc_html_e( 'While it is there, WordPress generates nothing and none of the lines below are served. This is what the file says:', 'solseo' ); ?></p>

			<pre class="solseo-pre"><?php echo esc_html( '' === $solseo_disk ? __( '(the file is empty)', 'solseo' ) : $solseo_disk ); ?></pre>

			<?php if ( $solseo_takeover['possible'] ) : ?>
				<p><?php esc_html_e( 'SolSEO can take over. It copies the file\'s lines into the box below so nothing is lost, then renames the file to robots.txt.solseo-backup. The file is not deleted and its contents are not changed. Putting it back is one button.', 'solseo' ); ?></p>
				<p><?php esc_html_e( 'The file already carries its own rules for every crawler, and WordPress writes some too, so check the preview after you save.', 'solseo' ); ?></p>

				<form method="post" data-solseo-confirm="<?php esc_attr_e( 'Rename robots.txt to robots.txt.solseo-backup and manage its contents here? The file is not deleted.', 'solseo' ); ?>">
					<?php wp_nonce_field( 'solseo_robots_takeover', '_solseo_nonce' ); ?>
					<input type="hidden" name="solseo_robots_takeover" value="1">

					<p>
						<label>
							<input type="checkbox" name="solseo_understood" value="1">
							<?php esc_html_e( 'I understand that robots.txt will be renamed', 'solseo' ); ?>
						</label>
					</p>

					<p><button type="submit" class="button"><?php esc_html_e( 'Rename robots.txt and manage it here', 'solseo' ); ?></button></p>
				</form>
			<?php elseif ( $solseo_takeover['reason'] ) : ?>
				<p><?php echo esc_html( $solseo_takeover['reason'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h3><?php esc_html_e( 'What this site is serving', 'solseo' ); ?></h3>

	<pre class="solseo-pre solseo-robots-preview" id="solseo-robots-preview" aria-live="polite"><?php echo esc_html( $data['served'] ); ?></pre>

	<p class="description">
		<?php esc_html_e( 'Written by WordPress and by SolSEO, and it follows what you type below before you save it. Another plugin that only adds to robots.txt on the front of the site does not show up here.', 'solseo' ); ?>
	</p>

	<h3><?php esc_html_e( 'A starting point', 'solseo' ); ?></h3>

	<p class="description"><?php esc_html_e( 'Each of these fills the box. Nothing is written until you press Save, so the preview above shows what a choice does before you make it.', 'solseo' ); ?></p>

	<div class="solseo-presets">
		<?php foreach ( $data['presets'] as $solseo_key => $solseo_preset ) : ?>
			<div class="solseo-preset">
				<button
					type="button"
					class="button solseo-preset-apply"
					data-solseo-preset="<?php echo esc_attr( $solseo_preset['body'] ); ?>"
				><?php echo esc_html( $solseo_preset['label'] ); ?></button>
				<p class="description"><?php echo esc_html( $solseo_preset['summary'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="solseo-note">
		<p><strong><?php esc_html_e( 'Worth knowing before you choose one of the AI sets', 'solseo' ); ?></strong></p>
		<ul>
			<li><?php esc_html_e( 'Two of the eight named crawlers do not fetch anything. They decide what may be done with pages an ordinary search crawler already read, so saying no to them costs nothing in Google or in Siri.', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'One of the eight is a search crawler whose owner says it does not train on what it reads. Saying no to it takes this site out of that product\'s answers and saves no training use at all.', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'One of the eight has been seen fetching addresses it was told to stay off. The line is a statement of what you want. Making it stick is a job for your server or your CDN.', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'The crawlers that fetch a page because somebody asked a question about it are left alone on purpose. Blocking those takes the site out of the answers people actually read.', 'solseo' ); ?></li>
		</ul>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'solseo_robots', '_solseo_nonce' ); ?>

		<p>
			<label for="solseo-robots-rules"><?php esc_html_e( 'Your rules', 'solseo' ); ?></label>
			<textarea id="solseo-robots-rules" name="solseo_robots_rules" rows="10" class="large-text code" spellcheck="false"><?php echo esc_textarea( $solseo_rules ); ?></textarea>
		</p>

		<div class="solseo-warning solseo-robots-danger" id="solseo-robots-danger" hidden>
			<p><strong><?php esc_html_e( 'This removes the site from Google.', 'solseo' ); ?></strong></p>
			<p><?php esc_html_e( 'Pages already in the index drop out over the following days and do not come back the moment you undo it.', 'solseo' ); ?></p>
		</div>

		<?php submit_button( __( 'Save', 'solseo' ) ); ?>
	</form>

	<?php if ( $data['public'] ) : ?>
		<form method="post" data-solseo-confirm="<?php esc_attr_e( 'Tell WordPress to discourage search engines from this site?', 'solseo' ); ?>">
			<?php wp_nonce_field( 'solseo_robots_public', '_solseo_nonce' ); ?>
			<input type="hidden" name="solseo_robots_public" value="1">

			<p class="description"><?php esc_html_e( 'If you are hiding the site because it is not ready, WordPress has its own switch for it, and search engines treat that one as the authority.', 'solseo' ); ?></p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Hide the site in the WordPress setting as well', 'solseo' ); ?></button></p>
		</form>
	<?php endif; ?>

	<?php if ( $solseo_backup ) : ?>
		<h3><?php esc_html_e( 'Putting it back', 'solseo' ); ?></h3>

		<?php if ( $solseo_moved ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: the name the file was given. */
					esc_html__( 'Your original file is on the server as %s. This puts it back where it was and empties the box.', 'solseo' ),
					'<code>' . esc_html( $solseo_moved ) . '</code>'
				);
				?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'This empties the box. WordPress then serves what it did before SolSEO was installed, which was:', 'solseo' ); ?></p>

			<pre class="solseo-pre"><?php echo esc_html( isset( $solseo_backup['served'] ) ? (string) $solseo_backup['served'] : '' ); ?></pre>

			<p class="description">
				<?php
				printf(
					/* translators: %s: the date the record was made. */
					esc_html__( 'Recorded on %s, when the plugin was switched on. Another plugin that only adds to robots.txt on the front of the site is not in that record.', 'solseo' ),
					esc_html( isset( $solseo_backup['taken'] ) ? gmdate( 'j F Y', strtotime( (string) $solseo_backup['taken'] ) ) : '' )
				);
				?>
			</p>
		<?php endif; ?>

		<form method="post" data-solseo-confirm="<?php esc_attr_e( 'Put robots.txt back the way it was before SolSEO?', 'solseo' ); ?>">
			<?php wp_nonce_field( 'solseo_robots_restore', '_solseo_nonce' ); ?>
			<input type="hidden" name="solseo_robots_restore" value="1">

			<p><button type="submit" class="button"><?php esc_html_e( 'Put it back as it was', 'solseo' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
