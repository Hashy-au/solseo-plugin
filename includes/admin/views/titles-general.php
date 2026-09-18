<?php
/**
 * Site wide title settings.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$options = $data['options'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="solseo-separator"><?php esc_html_e( 'Title separator', 'solseo' ); ?></label></th>
		<td>
			<select name="solseo[separator]" id="solseo-separator">
				<?php foreach ( \SolSEO\Variables::separators() as $key => $character ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['separator'], $key ); ?>>
						<?php echo esc_html( html_entity_decode( $character, ENT_QUOTES, 'UTF-8' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Stands in for {sep} in every template.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-home-title"><?php esc_html_e( 'Front page title', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="large-text" id="solseo-home-title" name="solseo[home_title]" value="<?php echo esc_attr( $options['home_title'] ); ?>">
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-home-description"><?php esc_html_e( 'Front page description', 'solseo' ); ?></label></th>
		<td>
			<textarea class="large-text" rows="2" id="solseo-home-description" name="solseo[home_description]"><?php echo esc_textarea( $options['home_description'] ); ?></textarea>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Archives', 'solseo' ); ?></th>
		<td>
			<fieldset>
				<label><input type="checkbox" name="solseo[author_archives]" value="1" <?php checked( $options['author_archives'] ); ?>> <?php esc_html_e( 'Let author archives be indexed', 'solseo' ); ?></label><br>
				<label><input type="checkbox" name="solseo[date_archives]" value="1" <?php checked( $options['date_archives'] ); ?>> <?php esc_html_e( 'Let date archives be indexed', 'solseo' ); ?></label><br>
				<label><input type="checkbox" name="solseo[search_noindex]" value="1" <?php checked( $options['search_noindex'] ); ?>> <?php esc_html_e( 'Keep search result pages out of the index', 'solseo' ); ?></label><br>
				<label><input type="checkbox" name="solseo[paged_noindex]" value="1" <?php checked( $options['paged_noindex'] ); ?>> <?php esc_html_e( 'Keep page two and beyond out of the index', 'solseo' ); ?></label><br>
				<label><input type="checkbox" name="solseo[home_noindex]" value="1" <?php checked( $options['home_noindex'] ); ?>> <?php esc_html_e( 'Keep the front page out of the index', 'solseo' ); ?></label>
			</fieldset>
			<p class="description"><?php esc_html_e( 'A site with one author gains nothing from an author archive that repeats the blog.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Attachment pages', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[attachment_redirect]" value="1" <?php checked( $options['attachment_redirect'] ); ?>> <?php esc_html_e( 'Send attachment pages to the post they belong to', 'solseo' ); ?></label>
			<p class="description"><?php esc_html_e( 'An attachment page holds one image and no writing, and it competes with the page that uses the image.', 'solseo' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'The site itself', 'solseo' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Structured data', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[schema_enabled]" value="1" <?php checked( $options['schema_enabled'] ); ?>> <?php esc_html_e( 'Describe pages to search engines in JSON-LD', 'solseo' ); ?></label><br>
			<label><input type="checkbox" name="solseo[schema_search_action]" value="1" <?php checked( $options['schema_search_action'] ); ?>> <?php esc_html_e( 'Offer the site search box in results', 'solseo' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-entity-type"><?php esc_html_e( 'This site represents', 'solseo' ); ?></label></th>
		<td>
			<select name="solseo[entity_type]" id="solseo-entity-type">
				<option value="organisation" <?php selected( $options['entity_type'], 'organisation' ); ?>><?php esc_html_e( 'An organisation', 'solseo' ); ?></option>
				<option value="person" <?php selected( $options['entity_type'], 'person' ); ?>><?php esc_html_e( 'A person', 'solseo' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-entity-name"><?php esc_html_e( 'Name', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="solseo-entity-name" name="solseo[entity_name]" value="<?php echo esc_attr( $options['entity_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Logo', 'solseo' ); ?></th>
		<td>
			<?php
			\SolSEO\Admin\Fields::image( 'solseo[entity_logo]', (int) $options['entity_logo'] );
			?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-profiles"><?php esc_html_e( 'Profiles elsewhere', 'solseo' ); ?></label></th>
		<td>
			<textarea class="large-text code" rows="4" id="solseo-profiles" name="solseo[entity_profiles]"><?php echo esc_textarea( implode( "\n", (array) $options['entity_profiles'] ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One address per line. These tell a search engine that the same business is behind each profile.', 'solseo' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Breadcrumbs', 'solseo' ); ?></h2>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[breadcrumbs_enabled]" value="1" <?php checked( $options['breadcrumbs_enabled'] ); ?>> <?php esc_html_e( 'Build the trail and describe it to search engines', 'solseo' ); ?></label>
			<p class="description">
				<?php
				printf(
					/* translators: 1: shortcode, 2: template tag. */
					esc_html__( 'Place it with %1$s or, in a template, %2$s', 'solseo' ),
					'<code>[solseo_breadcrumbs]</code>',
					'<code>&lt;?php solseo_breadcrumbs(); ?&gt;</code>'
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-crumb-home"><?php esc_html_e( 'Name of the first crumb', 'solseo' ); ?></label></th>
		<td><input type="text" class="regular-text" id="solseo-crumb-home" name="solseo[breadcrumbs_home]" value="<?php echo esc_attr( $options['breadcrumbs_home'] ); ?>" placeholder="<?php esc_attr_e( 'Home', 'solseo' ); ?>"></td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-crumb-sep"><?php esc_html_e( 'Separator', 'solseo' ); ?></label></th>
		<td><input type="text" class="small-text" id="solseo-crumb-sep" name="solseo[breadcrumbs_sep]" value="<?php echo esc_attr( $options['breadcrumbs_sep'] ); ?>"></td>
	</tr>
</table>
