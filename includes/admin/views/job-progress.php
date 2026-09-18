<?php
/**
 * The bar, the buttons and the sentence for a running job.
 *
 * Shared by every job, so a fourth one gets the same screen for nothing.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

$solseo_job   = $data['job'];
$solseo_args  = isset( $data['args'] ) ? (array) $data['args'] : array();
$solseo_start = isset( $data['start'] ) ? $data['start'] : __( 'Start', 'solseo' );
?>
<div
	class="solseo-job"
	data-solseo-job="<?php echo esc_attr( $solseo_job ); ?>"
	data-solseo-args="<?php echo esc_attr( wp_json_encode( $solseo_args ) ); ?>"
	<?php echo empty( $data['reload'] ) ? '' : 'data-solseo-reload'; ?>
>

	<?php if ( ! empty( $data['fields'] ) ) : ?>
		<?php echo $data['fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the calling view, escaped there. ?>
	<?php endif; ?>

	<p class="solseo-job-buttons">
		<button type="button" class="button button-primary" data-solseo-job-start><?php echo esc_html( $solseo_start ); ?></button>
		<button type="button" class="button" data-solseo-job-stop hidden><?php esc_html_e( 'Stop', 'solseo' ); ?></button>
	</p>

	<div class="solseo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
		<span class="solseo-progress-bar" data-solseo-job-bar></span>
	</div>

	<p class="solseo-job-status" data-solseo-job-message aria-live="polite"></p>
	<p class="description solseo-job-counts" data-solseo-job-counts></p>

	<p class="description"><?php esc_html_e( 'This keeps going while the page is open. Leave this tab open until the bar fills. If you close it, it stops where it is and you can carry on later.', 'solseo' ); ?></p>

	<div data-solseo-job-done hidden>
		<?php if ( ! empty( $data['done'] ) ) : ?>
			<?php echo $data['done']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the calling view, escaped there. ?>
		<?php endif; ?>
	</div>
</div>
