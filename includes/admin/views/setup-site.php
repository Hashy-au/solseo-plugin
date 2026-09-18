<?php
/**
 * Setup, step one: who the site is.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_options = $data['options'];
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'Who is behind this site?', 'solseo' ); ?></h2>

	<p class="description"><?php esc_html_e( 'This goes into the structured data every page carries, which is how a search engine works out that a name, a logo and a set of profiles all belong to one business.', 'solseo' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'solseo_setup_site', '_solseo_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'This site represents', 'solseo' ); ?></th>
				<td>
					<label><input type="radio" name="solseo[entity_type]" value="organisation" <?php checked( 'organisation', $solseo_options['entity_type'] ); ?>> <?php esc_html_e( 'An organisation', 'solseo' ); ?></label><br>
					<label><input type="radio" name="solseo[entity_type]" value="person" <?php checked( 'person', $solseo_options['entity_type'] ); ?>> <?php esc_html_e( 'A person', 'solseo' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="solseo-entity-name"><?php esc_html_e( 'Name', 'solseo' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="solseo-entity-name" name="solseo[entity_name]" value="<?php echo esc_attr( $solseo_options['entity_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<p class="description"><?php esc_html_e( 'The trading name, spelled the way you want it to appear.', 'solseo' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><span class="solseo-label"><?php esc_html_e( 'Logo', 'solseo' ); ?></span></th>
				<td>
					<?php \SolSEO\Admin\Fields::image( 'solseo[entity_logo]', (int) $solseo_options['entity_logo'] ); ?>
					<p class="description"><?php esc_html_e( 'Square works best. It is what a search result shows beside your name.', 'solseo' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="solseo-profiles"><?php esc_html_e( 'Profiles elsewhere', 'solseo' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="4" id="solseo-profiles" name="solseo[entity_profiles]" placeholder="https://www.facebook.com/yourbusiness&#10;https://www.instagram.com/yourbusiness&#10;https://www.youtube.com/@yourbusiness&#10;https://au.linkedin.com/company/yourbusiness"><?php echo esc_textarea( implode( "\n", (array) $solseo_options['entity_profiles'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One address per line, starting with https://. These tell a search engine that the same business is behind each profile.', 'solseo' ); ?></p>
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
						'step' => 2,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Skip this', 'solseo' ); ?></a>
		</p>
	</form>
</div>
