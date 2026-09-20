<?php
/**
 * The Connect screen.
 *
 * @package SolSEO
 */

use SolSEO\Analytics\Tag_Check;
use SolSEO\Connect\Google;
use SolSEO\Connect\Keys;

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<?php if ( $data['connected'] ) : ?>
			<h2><?php esc_html_e( 'Connected', 'solseo' ); ?></h2>

			<table class="solseo-table">
				<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Site number', 'solseo' ); ?></th>
					<td><?php echo (int) ( isset( $data['summary']['site_id'] ) ? $data['summary']['site_id'] : 0 ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Plan', 'solseo' ); ?></th>
					<td><?php echo esc_html( ! empty( $data['summary']['plan'] ) ? $data['summary']['plan'] : __( 'Not known yet', 'solseo' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last checked', 'solseo' ); ?></th>
					<td>
						<?php
						echo esc_html(
							! empty( $data['summary']['checked_at'] )
								? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $data['summary']['checked_at'] )
								: __( 'Never', 'solseo' )
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Key', 'solseo' ); ?></th>
					<td><code><?php echo esc_html( $data['hint'] ); ?></code>
						<span class="description"><?php esc_html_e( 'The first part of a fingerprint. The key itself is never shown again.', 'solseo' ); ?></span>
					</td>
				</tr>
				</tbody>
			</table>

			<?php if ( ! empty( $data['summary']['status'] ) ) : ?>
				<p class="solseo-warning"><?php echo esc_html( $data['summary']['status'] ); ?></p>
			<?php endif; ?>

			<form method="post" class="solseo-inline-form">
				<?php wp_nonce_field( 'solseo_refresh', '_solseo_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Check now', 'solseo' ); ?></button>
			</form>

			<form method="post" class="solseo-inline-form">
				<?php wp_nonce_field( 'solseo_disconnect', '_solseo_nonce' ); ?>
				<button type="submit" class="button-link solseo-delete"><?php esc_html_e( 'Disconnect this site', 'solseo' ); ?></button>
			</form>
		<?php else : ?>
			<h2><?php esc_html_e( 'Connect this site', 'solseo' ); ?></h2>

			<p><?php esc_html_e( 'Everything on the other screens works without an account. Connecting adds rank tracking, site audits and reports from solseo.com.au.', 'solseo' ); ?></p>

			<form method="post" class="solseo-connect-form">
				<?php wp_nonce_field( 'solseo_connect', '_solseo_nonce' ); ?>

				<label for="solseo-code"><?php esc_html_e( 'Pairing code', 'solseo' ); ?></label>
				<input type="text" id="solseo-code" name="solseo_code" placeholder="SOL-0000-0000" autocomplete="off" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'solseo' ); ?></button>

				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the SolSEO site. */
						esc_html__( 'Add this site at %s and it will give you a code. The code lasts fifteen minutes and works once.', 'solseo' ),
						'<a href="https://solseo.com.au/app" target="_blank" rel="noopener">solseo.com.au</a>'
					);
					?>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Google Search Console', 'solseo' ); ?></h2>

		<?php if ( $data['google']['connected'] ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: a Search Console property, such as sc-domain:example.com.au. */
					esc_html__( 'Connected, reading %s.', 'solseo' ),
					'<code>' . esc_html( '' !== $data['google']['property'] ? $data['google']['property'] : __( 'nothing yet', 'solseo' ) ) . '</code>'
				);
				?>
			</p>

			<div class="table-wrap">
				<table class="solseo-table">
					<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Permission', 'solseo' ); ?></th>
						<td class="wrap">
							<code><?php echo esc_html( $data['scope'] ); ?></code>
							<span class="description"><?php esc_html_e( 'Read only. It cannot add a property, remove one, or submit a sitemap.', 'solseo' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connected', 'solseo' ); ?></th>
						<td><?php echo esc_html( wp_date( 'j F Y', (int) $data['google']['connected_at'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token', 'solseo' ); ?></th>
						<td>
							<code><?php echo esc_html( $data['google']['fingerprint'] ); ?></code>
							<span class="description"><?php esc_html_e( 'The first part of a fingerprint. The token itself is scrambled and never shown.', 'solseo' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Through', 'solseo' ); ?></th>
						<td class="wrap">
							<?php
							echo esc_html(
								'own' === $data['google']['mode']
									? __( 'Your own Google app. Nothing in this handshake touches solseo.com.au.', 'solseo' )
									: __( 'The SolSEO relay, which exchanges the token and stores none of it.', 'solseo' )
							);
							?>
						</td>
					</tr>
					</tbody>
				</table>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'solseo_google_property', '_solseo_nonce' ); ?>

				<label for="solseo-google-property"><strong><?php esc_html_e( 'Which property is this site', 'solseo' ); ?></strong></label>
				<select id="solseo-google-property" name="solseo_google_property">
					<option value=""><?php esc_html_e( 'None, stop reading', 'solseo' ); ?></option>
					<?php foreach ( (array) $data['properties'] as $solseo_offered ) : ?>
						<option value="<?php echo esc_attr( $solseo_offered ); ?>" <?php selected( $solseo_offered, $data['google']['property'] ); ?>>
							<?php echo esc_html( $solseo_offered ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Use this one', 'solseo' ); ?></button>
			</form>

			<form method="post" class="solseo-inline-form">
				<?php wp_nonce_field( 'solseo_google_properties', '_solseo_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Check the list again', 'solseo' ); ?></button>
			</form>

			<form method="post" class="solseo-inline-form">
				<?php wp_nonce_field( 'solseo_google_disconnect', '_solseo_nonce' ); ?>
				<button type="submit" class="button-link solseo-delete"><?php esc_html_e( 'Disconnect Google', 'solseo' ); ?></button>
			</form>

			<p class="description"><?php esc_html_e( 'Disconnecting deletes the stored token, tells Google to forget the permission and stops the reading in the same click.', 'solseo' ); ?></p>
		<?php else : ?>
			<?php if ( $data['google']['revoked'] ) : ?>
				<p class="solseo-warning"><?php esc_html_e( 'Google no longer accepts this connection. It was either revoked in your Google account or it expired, and nothing is being read. Connect again to start it.', 'solseo' ); ?></p>
			<?php endif; ?>

			<p><?php esc_html_e( 'Connect the Google account that owns this site in Search Console, and the editor will show you what each page actually did in Google over the last twenty eight days: clicks, impressions, average position, and the searches that brought them.', 'solseo' ); ?></p>

			<p class="description">
				<?php
				printf(
					/* translators: %s: the Google permission being asked for. */
					esc_html__( 'The permission asked for is %s, which is read only. Nothing is ever written to your Google account.', 'solseo' ),
					'<code>' . esc_html( $data['scope'] ) . '</code>'
				);
				?>
			</p>

			<?php if ( 'own' === $data['google']['mode'] ) : ?>
				<p class="description"><?php esc_html_e( 'Your own Google app is set up, so this handshake will not touch solseo.com.au at all.', 'solseo' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'The handshake passes through solseo.com.au, because a Google client secret cannot live on your own server. It exchanges the code, hands the token straight to this site, and keeps nothing. If you would rather it did not, set up your own Google app below.', 'solseo' ); ?></p>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'solseo_google_connect', '_solseo_nonce' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect Google', 'solseo' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Use my own Google app', 'solseo' ); ?></h2>

		<p class="description"><?php esc_html_e( 'Optional, and for people who would rather solseo.com.au were not in the middle of their Google connection at all. Create an OAuth client in your own Google Cloud project, paste it here, and this site talks to Google directly with nothing of ours in the loop.', 'solseo' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'solseo_google_own', '_solseo_nonce' ); ?>

			<p>
				<label for="solseo-google-client-id"><strong><?php esc_html_e( 'Client id', 'solseo' ); ?></strong></label>
				<input
					type="text"
					id="solseo-google-client-id"
					name="solseo_google_client_id"
					class="widefat"
					autocomplete="off"
					spellcheck="false"
					value="<?php echo esc_attr( $data['google']['own_client_id'] ); ?>"
				>
				<span class="description"><?php esc_html_e( 'From APIs and services, Credentials, OAuth 2.0 Client IDs, in your own project. Clear this field to go back to the SolSEO relay.', 'solseo' ); ?></span>
			</p>

			<p>
				<label for="solseo-google-client-secret"><strong><?php esc_html_e( 'Client secret', 'solseo' ); ?></strong></label>
				<input
					type="text"
					id="solseo-google-client-secret"
					name="solseo_google_client_secret"
					class="widefat"
					autocomplete="off"
					spellcheck="false"
					value=""
					placeholder="<?php echo Keys::has( Google::OWN_SECRET ) ? esc_attr( str_repeat( '*', 24 ) ) : ''; ?>"
				>
				<span class="description"><?php esc_html_e( 'Scrambled before it is written, the same as every other credential on this screen. Leave it blank to keep the one saved.', 'solseo' ); ?></span>
			</p>

			<p>
				<label for="solseo-google-own-redirect"><strong><?php esc_html_e( 'Authorised redirect URI', 'solseo' ); ?></strong></label>
				<input
					type="text"
					id="solseo-google-own-redirect"
					class="widefat code"
					readonly
					onfocus="this.select()"
					value="<?php echo esc_attr( $data['google']['redirect_uri'] ); ?>"
				>
				<span class="description"><?php esc_html_e( 'Copy this into your own OAuth client, under Authorised redirect URIs. It is the one step everybody forgets, and leaving it out is a redirect_uri_mismatch when you press Connect.', 'solseo' ); ?></span>
			</p>

			<?php submit_button( __( 'Save my own app', 'solseo' ) ); ?>
		</form>

		<p class="description"><?php esc_html_e( 'Your project needs the Google Search Console API switched on. Without it every call comes back as a 403 against an otherwise perfect setup.', 'solseo' ); ?></p>
	</div>

	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Keys you hold elsewhere', 'solseo' ); ?></h2>

		<p class="description"><?php esc_html_e( 'These are yours, from the companies that issue them. Nothing here is sent to SolSEO, and each one only does anything on the screen that uses it.', 'solseo' ); ?></p>

		<?php if ( '' === $data['sealing'] ) : ?>
			<p class="solseo-warning"><?php esc_html_e( 'This server has neither libsodium nor OpenSSL, so a key cannot be stored safely and nothing here will save. Your host can switch either one on.', 'solseo' ); ?></p>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'solseo_save_keys', '_solseo_nonce' ); ?>

			<?php foreach ( $data['keys'] as $solseo_name => $solseo_key ) : ?>
				<p>
					<label for="solseo-key-<?php echo esc_attr( $solseo_name ); ?>"><strong><?php echo esc_html( $solseo_key['label'] ); ?></strong></label>
					<input
						type="text"
						id="solseo-key-<?php echo esc_attr( $solseo_name ); ?>"
						name="solseo_key_<?php echo esc_attr( $solseo_name ); ?>"
						class="widefat"
						autocomplete="off"
						spellcheck="false"
						value=""
						placeholder="<?php echo $solseo_key['set'] ? esc_attr( str_repeat( '*', 24 ) . $solseo_key['hint'] ) : ''; ?>"
					>

					<span class="description">
						<?php echo esc_html( $solseo_key['blurb'] ); ?>
						<a href="<?php echo esc_url( $solseo_key['where'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Where to get one', 'solseo' ); ?></a>
						&middot;
						<a href="<?php echo esc_url( $solseo_key['restrict'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Restrict it so nobody else can spend it', 'solseo' ); ?></a>
					</span>

					<?php if ( $solseo_key['set'] ) : ?>
						<span class="description">
							<?php
							printf(
								/* translators: %s: the first eight characters of a fingerprint. */
								esc_html__( 'One is saved. Fingerprint %s. Leave this blank to keep it, or clear it by saving a single space.', 'solseo' ),
								'<code>' . esc_html( $solseo_key['fingerprint'] ) . '</code>'
							);
							?>
						</span>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save keys', 'solseo' ) ); ?>
		</form>

		<p class="description"><?php esc_html_e( 'A key is scrambled before it is written to the database, using a secret taken from this site\'s own wp-config.php. That protects it if a copy of the database gets out on its own, which is the usual way one does. It protects nothing if wp-config.php goes with it, which is why the link above matters more than this sentence does.', 'solseo' ); ?></p>
	</div>

	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'What is measuring your visitors', 'solseo' ); ?></h2>

		<p><?php esc_html_e( 'Six plugins can each add the same analytics tag and none of them mentions the others, which is how a shop ends up counting every sale twice. This reads your own home page and says what is on it, whether it starts once or twice, and whether a consent plugin is holding it back.', 'solseo' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'solseo_tag_check', '_solseo_nonce' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Read my home page', 'solseo' ); ?></button>
		</form>

		<?php if ( (int) $data['tags']['read_at'] ) : ?>
			<?php if ( ! $data['tags']['tags'] ) : ?>
				<p><?php esc_html_e( 'Nothing this knows about is measuring your visitors.', 'solseo' ); ?></p>
			<?php else : ?>
				<div class="table-wrap">
					<table class="solseo-table widefat">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Tag', 'solseo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Measurement id', 'solseo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'What it is doing', 'solseo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['tags']['tags'] as $solseo_found_tag ) : ?>
								<tr>
									<td class="wrap">
										<a href="<?php echo esc_url( (string) $solseo_found_tag['docs'] ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( (string) $solseo_found_tag['label'] ); ?>
										</a>
									</td>
									<td class="wrap"><code><?php echo esc_html( implode( ', ', (array) $solseo_found_tag['ids'] ) ); ?></code></td>
									<td class="wrap"><?php echo esc_html( Tag_Check::describe( $solseo_found_tag ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( $data['tags']['markup'] ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: a list of plugin names. */
						esc_html__( 'The page says these put a tag on it: %s.', 'solseo' ),
						esc_html( implode( ', ', (array) $data['tags']['markup'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $data['tags']['installed'] ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: a list of plugin names. */
						esc_html__( 'Active on this site and able to add one: %s.', 'solseo' ),
						esc_html( implode( ', ', (array) $data['tags']['installed'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="description">
				<?php
				printf(
					/* translators: %s: a date. */
					esc_html__( 'Read on %s. This asks your own server for your own home page and nothing else, and it changes nothing.', 'solseo' ),
					esc_html( wp_date( 'j F Y', (int) $data['tags']['read_at'] ) )
				);
				?>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nothing has been read yet. This asks your own server for your own home page and nothing else, and it changes nothing.', 'solseo' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="solseo-card">
		<h2><?php esc_html_e( 'What is sent', 'solseo' ); ?></h2>

		<p class="description"><?php esc_html_e( 'Nothing leaves this site until you paste a code. After that, twice a day the plugin sends:', 'solseo' ); ?></p>

		<ul class="solseo-plain-list">
			<li><?php esc_html_e( 'The site address and time zone', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'WordPress, PHP, WooCommerce and plugin versions', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'How many posts, pages and products are published', 'solseo' ); ?></li>
			<li><?php esc_html_e( 'The permalink structure', 'solseo' ); ?></li>
		</ul>

		<p class="description"><?php esc_html_e( 'No page content, no customer data and nothing about your visitors. Disconnecting stops it at once.', 'solseo' ); ?></p>

		<p>
			<a href="https://solseo.com.au/privacy" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'solseo' ); ?></a>
			&middot;
			<a href="https://solseo.com.au/terms" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of service', 'solseo' ); ?></a>
		</p>
	</div>
</div>
