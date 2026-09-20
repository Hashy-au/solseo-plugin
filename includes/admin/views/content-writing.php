<?php
/**
 * The writing profile.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card solseo-card-wide">
	<h2><?php esc_html_e( 'How this site writes', 'solseo' ); ?></h2>

	<p><?php esc_html_e( 'These run as part of the score on every post, page and product. They are about house style rather than about search, and nothing here changes what a search engine sees.', 'solseo' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="solseo-variant"><?php esc_html_e( 'Spelling', 'solseo' ); ?></label></th>
			<td>
				<select id="solseo-variant" name="solseo[writing_variant]">
					<?php foreach ( $data['labels'] as $solseo_key => $solseo_label ) : ?>
						<option value="<?php echo esc_attr( $solseo_key ); ?>" <?php selected( $data['variant'], $solseo_key ); ?>><?php echo esc_html( $solseo_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'A word spelled the way another country spells it is flagged in the editor. Australian and British spelling are checked against the same list: the handful of words where the two genuinely differ mean different things in different sentences, so none of them is checked.', 'solseo' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="solseo-sentence"><?php esc_html_e( 'Long sentence', 'solseo' ); ?></label></th>
			<td>
				<input type="number" class="small-text" id="solseo-sentence" min="10" max="60" step="1" name="solseo[writing_sentence]" value="<?php echo esc_attr( (string) $data['sentence'] ); ?>">
				<?php esc_html_e( 'words', 'solseo' ); ?>
				<p class="description"><?php esc_html_e( 'A sentence longer than this counts as a long one. The readability check marks a page down when too many of them are.', 'solseo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="solseo-ease"><?php esc_html_e( 'Reading ease target', 'solseo' ); ?></label></th>
			<td>
				<input type="number" class="small-text" id="solseo-ease" min="20" max="90" step="1" name="solseo[writing_reading_ease]" value="<?php echo esc_attr( (string) $data['ease'] ); ?>">
				<p class="description"><?php esc_html_e( 'Out of a hundred, higher is easier. Sixty is plain English for a general audience. Raise it for a shop, lower it for a technical or professional readership.', 'solseo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="solseo-banned"><?php esc_html_e( 'Words to avoid', 'solseo' ); ?></label></th>
			<td>
				<textarea class="large-text code" rows="6" id="solseo-banned" name="solseo[writing_banned]" placeholder="<?php esc_attr_e( 'leverage&#10;synergy&#10;world-class', 'solseo' ); ?>"><?php echo esc_textarea( $data['banned'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line. Any of them in a post is flagged in the editor. This ships empty, because the words a business should not use are that business\'s own decision.', 'solseo' ); ?></p>
			</td>
		</tr>
	</table>
</div>
