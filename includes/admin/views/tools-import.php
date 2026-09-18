<?php
/**
 * Import from another SEO plugin.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- paging through a preview.
$offset = isset( $_GET['offset'] ) ? (int) $_GET['offset'] : 0;
?>
<?php if ( ! $data['sources'] ) : ?>
	<div class="solseo-card">
		<h2><?php esc_html_e( 'Nothing to import', 'solseo' ); ?></h2>
		<p><?php esc_html_e( 'No SEO fields from another plugin were found on this site.', 'solseo' ); ?></p>
	</div>
	<?php return; ?>
<?php endif; ?>

<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Copy existing SEO fields', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'Titles, descriptions, keywords, canonical addresses and visibility settings are copied across. The originals are left untouched, so you can switch back at any time.', 'solseo' ); ?></p>

	<table class="solseo-table">
		<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Source', 'solseo' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Pages with fields', 'solseo' ); ?></th>
			<th scope="col"></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $data['sources'] as $prefix => $source ) : ?>
			<tr<?php echo $prefix === $data['chosen'] ? ' class="solseo-row-active"' : ''; ?>>
				<td><?php echo esc_html( $source['name'] ); ?></td>
				<td><?php echo (int) $source['found']; ?></td>
				<td>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'   => 'solseo-tools',
								'tab'    => 'import',
								'source' => rawurlencode( $prefix ),
							),
							admin_url( 'admin.php' )
						)
					);
					?>
								">
						<?php esc_html_e( 'Preview', 'solseo' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $data['preview'] ) : ?>
	<div class="solseo-card solseo-card-wide">
		<h3><?php esc_html_e( 'What would change', 'solseo' ); ?></h3>

		<table class="solseo-table">
			<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Page', 'solseo' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Field', 'solseo' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Coming in', 'solseo' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Already here', 'solseo' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $data['preview'] as $row ) : ?>
				<tr>
					<td class="solseo-wrap"><?php echo esc_html( $row['title'] ); ?></td>
					<td><code><?php echo esc_html( $row['field'] ); ?></code></td>
					<td class="solseo-wrap"><?php echo esc_html( $row['value'] ); ?></td>
					<td class="solseo-wrap"><?php echo $row['current'] ? esc_html( $row['current'] ) : '&#8212;'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" class="solseo-inline-form">
			<?php wp_nonce_field( 'solseo_import', '_solseo_nonce' ); ?>
			<input type="hidden" name="solseo[source]" value="<?php echo esc_attr( $data['chosen'] ); ?>">
			<input type="hidden" name="solseo[offset]" value="<?php echo (int) $offset; ?>">

			<label>
				<input type="checkbox" name="solseo[overwrite]" value="1">
				<?php esc_html_e( 'Replace fields SolSEO already holds', 'solseo' ); ?>
			</label>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Import the next hundred', 'solseo' ); ?></button>
		</form>

		<?php if ( $offset ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: how many pages have been worked through. */
					esc_html__( 'Worked through %d pages so far.', 'solseo' ),
					(int) $offset
				);
				?>
			</p>
		<?php endif; ?>
	</div>
<?php endif; ?>
