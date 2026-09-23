<?php
/**
 * What needs work: four counts about this site's pages, and anything switched
 * on that stops the site being found at all.
 *
 * Every figure here is worked out on this site and needs no account, no plan
 * and no network.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<ul class="solseo-widget-stats">
	<li>
		<strong><?php echo esc_html( number_format_i18n( $data['counts']['scored'] ) ); ?></strong>
		<span><?php esc_html_e( 'pages scored', 'solseo' ); ?></span>
	</li>
	<li<?php echo $data['counts']['unscored'] ? ' class="is-warn"' : ''; ?>>
		<strong><?php echo esc_html( number_format_i18n( $data['counts']['unscored'] ) ); ?></strong>
		<span><?php esc_html_e( 'never scored', 'solseo' ); ?></span>
	</li>
	<li<?php echo $data['counts']['no_summary'] ? ' class="is-warn"' : ''; ?>>
		<strong><?php echo esc_html( number_format_i18n( $data['counts']['no_summary'] ) ); ?></strong>
		<span><?php esc_html_e( 'no search summary', 'solseo' ); ?></span>
	</li>
	<li<?php echo $data['counts']['hidden'] ? ' class="is-warn"' : ''; ?>>
		<strong><?php echo esc_html( number_format_i18n( $data['counts']['hidden'] ) ); ?></strong>
		<span><?php esc_html_e( 'hidden from search', 'solseo' ); ?></span>
	</li>
</ul>

<?php if ( $data['checks'] || $data['counts']['not_found'] ) : ?>
	<ul class="solseo-widget-flags">
		<?php foreach ( $data['checks'] as $check ) : ?>
			<li class="solseo-status-<?php echo esc_attr( $check['status'] ); ?>">
				<span class="solseo-dot"></span>
				<a href="<?php echo esc_url( $check['href'] ); ?>"><?php echo esc_html( $check['label'] ); ?></a>
				<span class="description"><?php echo esc_html( $check['note'] ); ?></span>
			</li>
		<?php endforeach; ?>

		<?php if ( $data['counts']['not_found'] ) : ?>
			<li class="solseo-status-fair">
				<span class="solseo-dot"></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-redirects' ) ); ?>">
					<?php
					printf(
						/* translators: %s: number of addresses that found nothing. */
						esc_html( _n( '%s address found nothing', '%s addresses found nothing', (int) $data['counts']['not_found'], 'solseo' ) ),
						esc_html( number_format_i18n( (int) $data['counts']['not_found'] ) )
					);
					?>
				</a>
			</li>
		<?php endif; ?>
	</ul>
<?php endif; ?>
