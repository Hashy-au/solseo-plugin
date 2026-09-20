<?php
/**
 * The Upgrade screen.
 *
 * Text and two links. Nothing here is fetched, counted or reported back.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<div class="solseo-card solseo-card-wide">
	<p><?php esc_html_e( 'Everything on this menu works without an account and without a key. SolSEO Pro is a separate add-on that installs beside this plugin and adds what is listed below.', 'solseo' ); ?></p>
</div>

<div class="solseo-grid">
	<?php foreach ( $data['features'] as $feature ) : ?>
		<div class="solseo-card">
			<h2><?php echo esc_html( $feature['title'] ); ?></h2>
			<p><?php echo esc_html( $feature['text'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>

<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'How to get it', 'solseo' ); ?></h2>
	<p><?php esc_html_e( 'The add-on comes with a paid SolSEO plan, or on its own as an annual licence key, which covers a set number of sites. It installs the way any plugin does, and it keeps itself up to date afterwards.', 'solseo' ); ?></p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'What is in SolSEO Pro', 'solseo' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( $data['home'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'This plugin on the web', 'solseo' ); ?>
		</a>
	</p>
	<p class="description">
		<?php esc_html_e( 'Rank tracking, site audits and monthly reports come from the SolSEO service rather than from the add-on.', 'solseo' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=solseo-settings&tab=connections' ) ); ?>"><?php esc_html_e( 'The Connect screen covers that side.', 'solseo' ); ?></a>
	</p>
</div>
