<?php
/**
 * This site's own figures from the SolSEO service.
 *
 * ONE SITE, AND IT IS THIS ONE. There is no loop over sites in this file and
 * there must not be: `$data['site']` is the row the widget class already
 * matched to this install, and a list would be somebody else's business on
 * somebody else's dashboard. See Dashboard_Widget::this_site().
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$site = $data['site'];

if ( ! $site ) {
	return;
}
?>
<h4><?php echo esc_html( isset( $site['name'] ) && '' !== $site['name'] ? $site['name'] : wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></h4>

<ul class="solseo-widget-rows">
	<li>
		<span><?php esc_html_e( 'Site health', 'solseo' ); ?></span>
		<span>
			<?php if ( isset( $site['health_score'] ) && null !== $site['health_score'] ) : ?>
				<span class="solseo-pill solseo-band-<?php echo esc_attr( \SolSEO\Analysis\Analyser::band( (int) $site['health_score'] ) ); ?>"><?php echo (int) $site['health_score']; ?></span>
				<?php
				$before = isset( $site['previous_health_score'] ) ? $site['previous_health_score'] : null;
				$change = null === $before ? 0 : (int) $site['health_score'] - (int) $before;

				if ( $change > 0 ) :
					?>
					<em class="is-up">
						<?php
						printf(
							/* translators: %s: points the health score rose by since the audit before. */
							esc_html__( 'up %s', 'solseo' ),
							esc_html( number_format_i18n( $change ) )
						);
						?>
					</em>
				<?php elseif ( $change < 0 ) : ?>
					<em class="is-down">
						<?php
						printf(
							/* translators: %s: points the health score fell by since the audit before. */
							esc_html__( 'down %s', 'solseo' ),
							esc_html( number_format_i18n( absint( $change ) ) )
						);
						?>
					</em>
				<?php endif; ?>
			<?php else : ?>
				<span class="description"><?php esc_html_e( 'not audited yet', 'solseo' ); ?></span>
			<?php endif; ?>
		</span>
	</li>
	<li>
		<span><?php esc_html_e( 'Tracked keywords', 'solseo' ); ?></span>
		<span><strong><?php echo esc_html( number_format_i18n( isset( $site['keywords'] ) ? (int) $site['keywords'] : 0 ) ); ?></strong></span>
	</li>
	<li>
		<span><?php esc_html_e( 'Last audit', 'solseo' ); ?></span>
		<span>
			<?php if ( ! empty( $site['last_crawl_at'] ) ) : ?>
				<?php
				printf(
					/* translators: %s: how long ago the last audit ran, such as "2 days". */
					esc_html__( '%s ago', 'solseo' ),
					esc_html( human_time_diff( strtotime( (string) $site['last_crawl_at'] ), time() ) )
				);
				?>
			<?php else : ?>
				<span class="description"><?php esc_html_e( 'never', 'solseo' ); ?></span>
			<?php endif; ?>
		</span>
	</li>
</ul>
