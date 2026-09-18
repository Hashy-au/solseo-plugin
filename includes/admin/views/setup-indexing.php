<?php
/**
 * Setup, step three: what should turn up in a search.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'What should turn up in a search?', 'solseo' ); ?></h2>

	<p class="description"><?php esc_html_e( 'Anything unticked is asked to stay out of search results. It stays on your site and people can still reach it: it just does not come up in Google.', 'solseo' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'solseo_setup_indexing', '_solseo_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Pages and posts', 'solseo' ); ?></th>
				<td>
					<?php foreach ( $data['post_types'] as $solseo_row ) : ?>
						<label class="solseo-tick">
							<input type="checkbox" name="solseo[found][post_types][]" value="<?php echo esc_attr( $solseo_row['name'] ); ?>" <?php checked( $solseo_row['found'] ); ?>>
							<?php echo esc_html( $solseo_row['label'] ); ?>
						</label><br>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Category and tag pages', 'solseo' ); ?></th>
				<td>
					<?php foreach ( $data['taxonomies'] as $solseo_row ) : ?>
						<label class="solseo-tick">
							<input type="checkbox" name="solseo[found][taxonomies][]" value="<?php echo esc_attr( $solseo_row['name'] ); ?>" <?php checked( $solseo_row['found'] ); ?>>
							<?php echo esc_html( $solseo_row['label'] ); ?>
						</label><br>
					<?php endforeach; ?>

					<p class="description"><?php esc_html_e( 'Category pages are usually worth having in search. Tag pages often repeat what a category page already says, which is why they start off unticked.', 'solseo' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="solseo-setup-buttons">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save and carry on', 'solseo' ); ?></button>
			<a class="button-link" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'solseo-setup',
						'step' => 4,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Skip this', 'solseo' ); ?></a>
		</p>
	</form>
</div>
