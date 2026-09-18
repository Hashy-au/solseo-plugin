<?php
/**
 * The Connect screen.
 *
 * @package SolSEO
 */

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
