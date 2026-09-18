<?php
/**
 * Connection state and the counts from the redirect tables.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card">
	<h2><?php esc_html_e( 'Account', 'solseo' ); ?></h2>

	<?php if ( $data['connected'] ) : ?>
		<p class="solseo-connected">
			<span class="solseo-dot solseo-band-excellent"></span>
			<?php esc_html_e( 'This site is connected to SolSEO.', 'solseo' ); ?>
		</p>

		<?php if ( ! empty( $data['summary']['plan'] ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: plan name. */
					esc_html__( 'Plan: %s', 'solseo' ),
					esc_html( $data['summary']['plan'] )
				);
				?>
			</p>
		<?php endif; ?>

		<p>
			<a class="button button-primary" href="https://solseo.com.au/app" target="_blank" rel="noopener">
				<?php esc_html_e( 'Open the SolSEO dashboard', 'solseo' ); ?>
			</a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'The plugin works on its own. Connecting a SolSEO account adds rank tracking and site audits from solseo.com.au.', 'solseo' ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-connect' ) ); ?>">
				<?php esc_html_e( 'Connect this site', 'solseo' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Redirects', 'solseo' ); ?></h3>
	<ul class="solseo-counts">
		<li>
			<strong><?php echo (int) $data['redirects']; ?></strong>
			<span><?php esc_html_e( 'rules', 'solseo' ); ?></span>
		</li>
		<li>
			<strong><?php echo (int) $data['not_found']; ?></strong>
			<span><?php esc_html_e( 'addresses found nothing', 'solseo' ); ?></span>
		</li>
	</ul>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-redirects' ) ); ?>">
			<?php esc_html_e( 'Manage redirects', 'solseo' ); ?>
		</a>
	</p>
</div>
