<?php
/**
 * What a link to this site looks like when it is shared.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$options = $data['options'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Open Graph', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[og_enabled]" value="1" <?php checked( $options['og_enabled'] ); ?>> <?php esc_html_e( 'Add the tags social networks read', 'solseo' ); ?></label>
			<p class="description"><?php esc_html_e( 'Without these, a shared link shows whatever the network can scrape.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Cards', 'solseo' ); ?></th>
		<td>
			<label><input type="checkbox" name="solseo[twitter_enabled]" value="1" <?php checked( $options['twitter_enabled'] ); ?>> <?php esc_html_e( 'Add card tags', 'solseo' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-card"><?php esc_html_e( 'Card size', 'solseo' ); ?></label></th>
		<td>
			<select name="solseo[twitter_card]" id="solseo-card">
				<option value="summary_large_image" <?php selected( $options['twitter_card'], 'summary_large_image' ); ?>><?php esc_html_e( 'Large image', 'solseo' ); ?></option>
				<option value="summary" <?php selected( $options['twitter_card'], 'summary' ); ?>><?php esc_html_e( 'Small image', 'solseo' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="solseo-twitter-site"><?php esc_html_e( 'Account handle', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="solseo-twitter-site" name="solseo[twitter_site]" value="<?php echo esc_attr( $options['twitter_site'] ); ?>" placeholder="@yoursite">
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Fallback image', 'solseo' ); ?></th>
		<td>
			<?php \SolSEO\Admin\Fields::image( 'solseo[default_image]', (int) $options['default_image'] ); ?>
			<p class="description"><?php esc_html_e( 'Used when a page has no featured image of its own. Aim for 1200 by 630 pixels.', 'solseo' ); ?></p>
		</td>
	</tr>
</table>
