<?php
/**
 * Addresses that found nothing.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<div class="solseo-card">
	<form method="post" class="solseo-inline-form">
		<?php wp_nonce_field( 'solseo_log_settings', '_solseo_nonce' ); ?>

		<label>
			<input type="checkbox" name="solseo[log_not_found]" value="1" <?php checked( $data['options']['log_not_found'] ); ?>>
			<?php esc_html_e( 'Keep a log of addresses that found nothing', 'solseo' ); ?>
		</label>

		<label class="solseo-inline-number">
			<?php esc_html_e( 'Forget entries after', 'solseo' ); ?>
			<input type="number" class="small-text" name="solseo[log_retention_days]" value="<?php echo (int) $data['options']['log_retention_days']; ?>" min="1" max="365">
			<?php esc_html_e( 'days', 'solseo' ); ?>
		</label>

		<button type="submit" class="button"><?php esc_html_e( 'Save', 'solseo' ); ?></button>
	</form>

	<p class="description"><?php esc_html_e( 'The address and the page it was linked from are kept. Nothing about the visitor is.', 'solseo' ); ?></p>
</div>

<?php if ( ! $data['entries'] ) : ?>
	<p><?php esc_html_e( 'Nothing logged yet.', 'solseo' ); ?></p>
	<?php return; ?>
<?php endif; ?>

<table class="wp-list-table widefat fixed striped">
	<thead>
	<tr>
		<th scope="col"><?php esc_html_e( 'Address', 'solseo' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Linked from', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-narrow"><?php esc_html_e( 'Times', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-narrow"><?php esc_html_e( 'Last seen', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-actions"></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $data['entries'] as $entry ) : ?>
		<tr>
			<td><code><?php echo esc_html( $entry['url'] ); ?></code></td>
			<td class="solseo-wrap"><?php echo $entry['referrer'] ? esc_html( $entry['referrer'] ) : '&#8212;'; ?></td>
			<td><?php echo (int) $entry['hits']; ?></td>
			<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $entry['last_seen'] ) ); ?></td>
			<td>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'   => 'solseo-redirects',
							'source' => rawurlencode( $entry['url'] ),
						),
						admin_url( 'admin.php' )
					)
				);
				?>
							">
					<?php esc_html_e( 'Redirect', 'solseo' ); ?>
				</a>
				<a class="solseo-delete" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'          => 'solseo-redirects',
								'tab'           => 'not_found',
								'solseo_action' => 'delete_miss',
								'id'            => (int) $entry['id'],
							),
							admin_url( 'admin.php' )
						),
						'solseo_delete_miss_' . (int) $entry['id']
					)
				);
				?>
												">
					<?php esc_html_e( 'Remove', 'solseo' ); ?>
				</a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php \SolSEO\Admin\Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] ); ?>

<p>
	<a class="button" href="
	<?php
	echo esc_url(
		wp_nonce_url(
			add_query_arg(
				array(
					'page'          => 'solseo-redirects',
					'tab'           => 'not_found',
					'solseo_action' => 'clear_log',
					'id'            => 0,
				),
				admin_url( 'admin.php' )
			),
			'solseo_clear_log_0'
		)
	);
	?>
	">
		<?php esc_html_e( 'Empty the log', 'solseo' ); ?>
	</a>
</p>
