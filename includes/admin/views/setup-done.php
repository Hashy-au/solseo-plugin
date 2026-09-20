<?php
/**
 * Setup, the last page.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Your setup is complete', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'Everything below is switched on and working. Nothing here needs any more attention from you unless you want to change something.', 'solseo' ); ?></p>

	<div class="solseo-grid">
		<div class="solseo-card">
			<h3><?php esc_html_e( 'A score on every page', 'solseo' ); ?></h3>
			<p><?php esc_html_e( 'Open any post, page or product and the panel in the editor tells you what to change, as you type. The same score is a sortable column on the posts list, so the pages that need work are one click away.', 'solseo' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'See your posts', 'solseo' ); ?></a></p>
		</div>

		<div class="solseo-card">
			<h3><?php esc_html_e( 'Titles, meta and structured data', 'solseo' ); ?></h3>
			<p><?php esc_html_e( 'Every page has a title and description written for it from a template, and carries the structured data a search result is built from. A shop gets one offer per variation, with its own price and stock.', 'solseo' ); ?></p>
			<p><a href="<?php echo esc_url( add_query_arg( 'page', 'solseo-titles', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Titles and Meta', 'solseo' ); ?></a></p>
		</div>

		<div class="solseo-card">
			<h3><?php esc_html_e( 'Nothing found a dead end', 'solseo' ); ?></h3>
			<p><?php esc_html_e( 'Every address that finds nothing is logged with the page that linked to it, and one click turns a dead link into a redirect. It keeps nothing about the visitor.', 'solseo' ); ?></p>
			<p><a href="<?php echo esc_url( add_query_arg( 'page', 'solseo-redirects', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Redirects', 'solseo' ); ?></a></p>
		</div>
	</div>

	<h3><?php esc_html_e( 'The one thing left worth doing', 'solseo' ); ?></h3>

	<p>
		<?php
		printf(
			/* translators: %s: the address of the sitemap. */
			esc_html__( 'Add %s to Google Search Console. It is free, it takes two minutes, and it is what gets your pages looked at sooner rather than whenever Google gets round to it.', 'solseo' ),
			'<code>' . esc_html( home_url( '/sitemap.xml' ) ) . '</code>'
		);
		?>
	</p>

	<?php if ( ! $data['connected'] ) : ?>
		<div class="solseo-note">
			<h3><?php esc_html_e( 'Want to know whether any of it is working?', 'solseo' ); ?></h3>

			<p><?php esc_html_e( 'This plugin makes your pages right. What it cannot tell you from inside your own site is where you actually rank, who is above you, and whether last month was better than this one.', 'solseo' ); ?></p>

			<p><?php esc_html_e( 'A free account at solseo.com.au tracks your positions, checks the whole site for problems once a week, and emails you a report. Connecting takes a pasted code and nothing leaves this site until you do.', 'solseo' ); ?></p>

			<p>
				<a class="button button-primary" href="https://solseo.com.au" target="_blank" rel="noopener"><?php esc_html_e( 'Look at solseo.com.au', 'solseo' ); ?></a>
				<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => 'solseo-settings',
							'tab'  => 'connections',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
				"><?php esc_html_e( 'I have a code', 'solseo' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p class="solseo-setup-buttons">
		<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'solseo', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Go to the SolSEO dashboard', 'solseo' ); ?></a>
		<a class="button-link" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'solseo-setup',
					'step' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		?>
		"><?php esc_html_e( 'Run through it again', 'solseo' ); ?></a>
	</p>
</div>
