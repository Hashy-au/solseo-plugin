<?php
/**
 * The author fields on the user profile screen.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'SolSEO', 'solseo' ); ?></h2>

<p class="description">
	<?php esc_html_e( 'These are published as structured data on the posts this person wrote, so a search engine can tell who wrote them. The biography above is used as well, so there is no second one here.', 'solseo' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="solseo-job-title"><?php esc_html_e( 'Job title', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="solseo-job-title" name="_solseo_job_title" value="<?php echo esc_attr( $data['title'] ); ?>">
			<p class="description"><?php esc_html_e( 'For example, Head Coach, or Senior Bowyer.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="solseo-credentials"><?php esc_html_e( 'Letters after the name', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="solseo-credentials" name="_solseo_credentials" value="<?php echo esc_attr( $data['credentials'] ); ?>">
			<p class="description"><?php esc_html_e( 'For example, BSc, or MAICD. Leave it empty if there are none.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="solseo-knows"><?php esc_html_e( 'Knows about', 'solseo' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="solseo-knows" name="_solseo_knows_about" value="<?php echo esc_attr( $data['knows'] ); ?>">
			<p class="description"><?php esc_html_e( 'A few subjects, separated by commas. Only what this person can genuinely be said to know about.', 'solseo' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="solseo-profiles"><?php esc_html_e( 'Profiles elsewhere', 'solseo' ); ?></label></th>
		<td>
			<textarea class="large-text code" rows="4" id="solseo-profiles" name="_solseo_profiles"><?php echo esc_textarea( $data['profiles'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One address per line. A professional body, a university page, an author page on another site.', 'solseo' ); ?></p>
		</td>
	</tr>
</table>
