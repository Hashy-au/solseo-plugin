<?php
/**
 * The way out of the widget: the plugin's own screens, and the service.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<p class="solseo-widget-footer">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo' ) ); ?>"><?php esc_html_e( 'Open SolSEO', 'solseo' ); ?></a>

	<?php if ( $data['connected'] ) : ?>
		<a href="https://solseo.com.au/app" target="_blank" rel="noopener"><?php esc_html_e( 'Rank tracking and audits', 'solseo' ); ?></a>
	<?php else : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-settings&tab=connections' ) ); ?>"><?php esc_html_e( 'Connect a SolSEO account', 'solseo' ); ?></a>
	<?php endif; ?>
</p>
