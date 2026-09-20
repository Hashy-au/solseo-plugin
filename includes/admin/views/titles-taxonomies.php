<?php
/**
 * Templates for each taxonomy.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<p class="description"><?php esc_html_e( 'Category and tag archives are real landing pages. Give them titles worth clicking.', 'solseo' ); ?></p>

<?php foreach ( solseo_taxonomies() as $name ) : ?>
	<?php
	$object   = get_taxonomy( $name );
	$settings = \SolSEO\Options::taxonomy( $name );

	if ( ! $object ) {
		continue;
	}
	?>
	<h2><?php echo esc_html( $object->labels->name ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Title', 'solseo' ); ?></th>
			<td><?php \SolSEO\Admin\Fields::template( 'solseo[taxonomies][' . $name . '][title]', $settings['title'], $object->labels->name . ' title' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Description', 'solseo' ); ?></th>
			<td><?php \SolSEO\Admin\Fields::template( 'solseo[taxonomies][' . $name . '][description]', $settings['description'], $object->labels->name . ' description' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Visibility', 'solseo' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="solseo[taxonomies][<?php echo esc_attr( $name ); ?>][noindex]" value="1" <?php checked( $settings['noindex'] ); ?>>
					<?php esc_html_e( 'Keep these archives out of search results', 'solseo' ); ?>
				</label>
			</td>
		</tr>
	</table>
<?php endforeach; ?>
