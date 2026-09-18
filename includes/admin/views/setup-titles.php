<?php
/**
 * Setup, step two: the front page title and the separator.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_options = $data['options'];
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'What a search result says', 'solseo' ); ?></h2>

	<p class="description"><?php esc_html_e( 'Words in braces are filled in for each page. Every other page on the site has its own template, and they all use the separator you pick here.', 'solseo' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'solseo_setup_titles', '_solseo_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Separator', 'solseo' ); ?></th>
				<td>
					<?php foreach ( $data['separators'] as $solseo_key => $solseo_mark ) : ?>
						<label class="solseo-separator">
							<input type="radio" name="solseo[separator]" value="<?php echo esc_attr( $solseo_key ); ?>" <?php checked( $solseo_key, $solseo_options['separator'] ); ?>>
							<span><?php echo wp_kses( $solseo_mark, array() ); ?></span>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'The mark between the page name and the site name.', 'solseo' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="solseo-home-title"><?php esc_html_e( 'Front page title', 'solseo' ); ?></label></th>
				<td>
					<?php \SolSEO\Admin\Fields::template( 'solseo[home_title]', $solseo_options['home_title'], __( 'Front page title', 'solseo' ) ); ?>
					<p class="description"><?php esc_html_e( 'Available here: {sitename}, {sitedesc}, {sep}.', 'solseo' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="solseo-home-description"><?php esc_html_e( 'Front page description', 'solseo' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="solseo-home-description" name="solseo[home_description]"><?php echo esc_textarea( $solseo_options['home_description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One or two sentences saying what the site sells or does. Around 155 characters is what a search result shows.', 'solseo' ); ?></p>
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
						'step' => 3,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Skip this', 'solseo' ); ?></a>
		</p>
	</form>
</div>
