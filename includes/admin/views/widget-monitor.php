<?php
/**
 * What the service last measured about this site from outside it.
 *
 * Every figure was measured before the question was asked: the widget reads the
 * last probe out of the sync's transient and opens no connection of its own.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$watch = $data['monitor'];

if ( ! $watch ) {
	return;
}
?>
<h4><?php esc_html_e( 'Monitoring', 'solseo' ); ?></h4>

<ul class="solseo-widget-rows">
	<li>
		<span><?php esc_html_e( 'Answering', 'solseo' ); ?></span>
		<span class="<?php echo empty( $watch['up'] ) ? 'solseo-status-poor' : 'solseo-status-good'; ?>">
			<span class="solseo-dot"></span>
			<?php if ( ! empty( $watch['up'] ) ) : ?>
				<?php
				if ( ! empty( $watch['ms'] ) ) {
					printf(
						/* translators: %s: how long the site took to answer, in milliseconds. */
						esc_html__( 'yes, in %s ms', 'solseo' ),
						esc_html( number_format_i18n( (int) $watch['ms'] ) )
					);
				} else {
					esc_html_e( 'yes', 'solseo' );
				}
				?>
			<?php else : ?>
				<?php esc_html_e( 'no', 'solseo' ); ?>
			<?php endif; ?>
		</span>
	</li>

	<?php if ( isset( $watch['ssl_days_left'] ) && null !== $watch['ssl_days_left'] ) : ?>
		<li>
			<span><?php esc_html_e( 'Certificate', 'solseo' ); ?></span>
			<span class="<?php echo (int) $watch['ssl_days_left'] < 14 ? 'solseo-status-poor' : 'solseo-status-good'; ?>">
				<?php
				printf(
					/* translators: %s: days until the certificate expires. */
					esc_html( _n( '%s day left', '%s days left', (int) $watch['ssl_days_left'], 'solseo' ) ),
					esc_html( number_format_i18n( (int) $watch['ssl_days_left'] ) )
				);
				?>
			</span>
		</li>
	<?php endif; ?>

	<?php if ( ! empty( $watch['sitemap_url'] ) ) : ?>
		<li>
			<span><?php esc_html_e( 'Sitemap', 'solseo' ); ?></span>
			<span class="<?php echo empty( $watch['sitemap_ok'] ) ? 'solseo-status-poor' : 'solseo-status-good'; ?>">
				<?php echo empty( $watch['sitemap_ok'] ) ? esc_html__( 'not answering', 'solseo' ) : esc_html__( 'working', 'solseo' ); ?>
			</span>
		</li>
	<?php endif; ?>

	<?php if ( ! empty( $watch['index_blocked'] ) ) : ?>
		<li>
			<span><?php esc_html_e( 'Search engines', 'solseo' ); ?></span>
			<span class="solseo-status-poor">
				<span class="solseo-dot"></span>
				<?php esc_html_e( 'being told to stay away', 'solseo' ); ?>
			</span>
		</li>
	<?php endif; ?>
</ul>
