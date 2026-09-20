<?php
/**
 * Templates for each post type.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$schema_types = \SolSEO\Admin\Titles_Screen::schema_types();
?>
<p class="description"><?php esc_html_e( 'These are used whenever a page has nothing written in its own SEO fields.', 'solseo' ); ?></p>

<?php foreach ( solseo_post_types() as $slug ) : ?>
	<?php
	$object   = get_post_type_object( $slug );
	$settings = \SolSEO\Options::post_type( $slug );

	if ( ! $object ) {
		continue;
	}
	?>
	<h2><?php echo esc_html( $object->labels->name ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Title', 'solseo' ); ?></th>
			<td><?php \SolSEO\Admin\Fields::template( 'solseo[post_types][' . $slug . '][title]', $settings['title'], $object->labels->name . ' title' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Description', 'solseo' ); ?></th>
			<td><?php \SolSEO\Admin\Fields::template( 'solseo[post_types][' . $slug . '][description]', $settings['description'], $object->labels->name . ' description' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Structured data', 'solseo' ); ?></th>
			<td>
				<select name="solseo[post_types][<?php echo esc_attr( $slug ); ?>][schema]">
					<?php foreach ( $schema_types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['schema'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Visibility', 'solseo' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="solseo[post_types][<?php echo esc_attr( $slug ); ?>][noindex]" value="1" <?php checked( $settings['noindex'] ); ?>>
					<?php esc_html_e( 'Keep this whole post type out of search results', 'solseo' ); ?>
				</label>
			</td>
		</tr>
	</table>
<?php endforeach; ?>
