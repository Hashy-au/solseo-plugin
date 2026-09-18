<?php
/**
 * The redirect rules table and the form that adds one.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card">
	<h2><?php esc_html_e( 'Add a redirect', 'solseo' ); ?></h2>

	<form method="post" class="solseo-redirect-form">
		<?php wp_nonce_field( 'solseo_redirect_add', '_solseo_nonce' ); ?>

		<div class="solseo-field-row">
			<label>
				<span><?php esc_html_e( 'From', 'solseo' ); ?></span>
				<input type="text" name="solseo[source]" value="<?php echo esc_attr( $data['prefill'] ); ?>" placeholder="/old-page/" required>
			</label>

			<label>
				<span><?php esc_html_e( 'To', 'solseo' ); ?></span>
				<input type="text" name="solseo[target]" placeholder="<?php echo esc_attr( home_url( '/new-page/' ) ); ?>">
			</label>

			<label>
				<span><?php esc_html_e( 'Answer', 'solseo' ); ?></span>
				<select name="solseo[status_code]">
					<option value="301"><?php esc_html_e( '301 moved for good', 'solseo' ); ?></option>
					<option value="302"><?php esc_html_e( '302 moved for now', 'solseo' ); ?></option>
					<option value="307"><?php esc_html_e( '307 moved for now, same method', 'solseo' ); ?></option>
					<option value="410"><?php esc_html_e( '410 gone', 'solseo' ); ?></option>
				</select>
			</label>

			<label>
				<span><?php esc_html_e( 'Match', 'solseo' ); ?></span>
				<select name="solseo[match_type]">
					<option value="exact"><?php esc_html_e( 'Exact address', 'solseo' ); ?></option>
					<option value="regex"><?php esc_html_e( 'Pattern', 'solseo' ); ?></option>
				</select>
			</label>

			<input type="hidden" name="solseo[enabled]" value="1">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'solseo' ); ?></button>
		</div>

		<p class="description"><?php esc_html_e( 'A 410 tells a search engine the page is gone on purpose, which drops it from the index faster than letting it 404.', 'solseo' ); ?></p>
	</form>
</div>

<?php if ( ! $data['rules'] ) : ?>
	<p><?php esc_html_e( 'No redirects yet.', 'solseo' ); ?></p>
	<?php return; ?>
<?php endif; ?>

<table class="wp-list-table widefat fixed striped">
	<thead>
	<tr>
		<th scope="col"><?php esc_html_e( 'From', 'solseo' ); ?></th>
		<th scope="col"><?php esc_html_e( 'To', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-narrow"><?php esc_html_e( 'Answer', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-narrow"><?php esc_html_e( 'Used', 'solseo' ); ?></th>
		<th scope="col" class="solseo-col-narrow"></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $data['rules'] as $rule ) : ?>
		<tr>
			<td>
				<code><?php echo esc_html( $rule['source'] ); ?></code>
				<?php if ( 'regex' === $rule['match_type'] ) : ?>
					<span class="solseo-tag"><?php esc_html_e( 'pattern', 'solseo' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="solseo-wrap"><?php echo esc_html( $rule['target'] ); ?></td>
			<td><?php echo (int) $rule['status_code']; ?></td>
			<td><?php echo (int) $rule['hits']; ?></td>
			<td>
				<a class="solseo-delete" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'          => 'solseo-redirects',
								'solseo_action' => 'delete_rule',
								'id'            => (int) $rule['id'],
							),
							admin_url( 'admin.php' )
						),
						'solseo_delete_rule_' . (int) $rule['id']
					)
				);
				?>
												">
					<?php esc_html_e( 'Delete', 'solseo' ); ?>
				</a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php
\SolSEO\Admin\Screen::pagination( (int) $data['total'], (int) $data['per_page'], (int) $data['page'] );
