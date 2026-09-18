<?php
/**
 * Setup, step five: bring anything across, connect, check the pages.
 *
 * Three panels, all optional, none of them in each other's way.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_sources = $data['sources'];
$solseo_first   = $solseo_sources ? array_keys( $solseo_sources )[0] : '';
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Nearly there', 'solseo' ); ?></h2>

	<?php if ( $solseo_sources ) : ?>
		<h3><?php esc_html_e( 'Bring your existing fields across', 'solseo' ); ?></h3>

		<p>
			<?php
			printf(
				/* translators: 1: plugin name, 2: number of pages. */
				esc_html( _n( '%1$s holds SEO fields on %2$s page on this site.', '%1$s holds SEO fields on %2$s pages on this site.', (int) $solseo_sources[ $solseo_first ]['found'], 'solseo' ) ),
				'<strong>' . esc_html( $solseo_sources[ $solseo_first ]['name'] ) . '</strong>',
				'<strong>' . esc_html( number_format_i18n( (int) $solseo_sources[ $solseo_first ]['found'] ) ) . '</strong>'
			);
			?>
		</p>

		<p class="description"><?php esc_html_e( 'Copying leaves the originals exactly where they are. Nothing is deleted and nothing is switched off here.', 'solseo' ); ?></p>

		<?php
		\SolSEO\Admin\Screen::view(
			'job-progress',
			array(
				'job'   => 'import',
				'start' => __( 'Copy them all across', 'solseo' ),
				'args'  => array( 'source' => $solseo_first ),
			)
		);
		?>

		<p class="description">
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'solseo-tools',
						'tab'  => 'import',
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						"><?php esc_html_e( 'See what would change, and switch the other plugin off afterwards', 'solseo' ); ?></a>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Check your pages', 'solseo' ); ?></h3>

	<p class="description"><?php esc_html_e( 'Goes through every published page and works out its score, so the SEO column and the dashboard have something in them the first time you look. It also rebuilds the sitemap.', 'solseo' ); ?></p>

	<?php
	\SolSEO\Admin\Screen::view(
		'job-progress',
		array(
			'job'   => 'score',
			'start' => __( 'Check every page', 'solseo' ),
			'args'  => array(
				'scope'   => 'all',
				'sitemap' => true,
			),
		)
	);
	?>

	<h3><?php esc_html_e( 'A free SolSEO account', 'solseo' ); ?></h3>

	<?php if ( $data['connected'] ) : ?>
		<p><?php esc_html_e( 'This site is connected.', 'solseo' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Everything on the other screens works without an account. Connecting adds rank tracking, site health checks and monthly reports from solseo.com.au.', 'solseo' ); ?></p>

		<p class="description"><?php esc_html_e( 'Nothing leaves this site until you paste a code in below. The plugin cannot make an account for you, so the first step opens the site in a new tab.', 'solseo' ); ?></p>

		<p>
			<a class="button" href="https://solseo.com.au/app" target="_blank" rel="noopener"><?php esc_html_e( 'Open solseo.com.au in a new tab', 'solseo' ); ?></a>
		</p>

		<p class="description"><?php esc_html_e( 'Sign in or sign up, add this site, and it gives you a code. The code lasts fifteen minutes and works once.', 'solseo' ); ?></p>

		<form method="post" class="solseo-inline-form">
			<?php wp_nonce_field( 'solseo_setup_connect', '_solseo_nonce' ); ?>

			<label for="solseo-code" class="screen-reader-text"><?php esc_html_e( 'Pairing code', 'solseo' ); ?></label>
			<input type="text" id="solseo-code" name="solseo[code]" class="regular-text" placeholder="SOL-0000-0000" autocomplete="off">
			<button type="submit" class="button"><?php esc_html_e( 'Connect', 'solseo' ); ?></button>
		</form>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'solseo_setup_finish', '_solseo_nonce' ); ?>
		<input type="hidden" name="solseo_finish" value="1">

		<p class="solseo-setup-buttons">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Finish', 'solseo' ); ?></button>
		</p>
	</form>
</div>
