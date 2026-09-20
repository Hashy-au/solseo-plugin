<?php
/**
 * Health, Speed.
 *
 * @package SolSEO
 */

use SolSEO\Admin\Speed_Tab;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included from inside Screen::view(), so this is local to that method.

$result = $data['result'];
?>
<div class="solseo-grid">
	<div class="solseo-card solseo-card-wide">
		<h2><?php esc_html_e( 'Check one page', 'solseo' ); ?></h2>

		<?php if ( ! $data['has_key'] ) : ?>
			<p><?php esc_html_e( 'This check asks Google to load one of your pages and report back. It needs a Google API key, which is free and takes about two minutes to create.', 'solseo' ); ?></p>

			<p class="description"><?php esc_html_e( 'Google documents the key as optional. It is not: without one the shared allowance is already spent and every check is refused.', 'solseo' ); ?></p>

			<p><a class="button button-primary" href="<?php echo esc_url( $data['settings'] ); ?>"><?php esc_html_e( 'Add a Google key', 'solseo' ); ?></a></p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'solseo_speed_run', '_solseo_nonce' ); ?>

				<p>
					<label for="solseo-speed-url"><?php esc_html_e( 'Address', 'solseo' ); ?></label>
					<input type="url" id="solseo-speed-url" name="solseo_speed_url" class="widefat" value="<?php echo esc_attr( $data['url'] ); ?>" required>
				</p>

				<p>
					<label>
						<input type="radio" name="solseo_speed_strategy" value="mobile" <?php checked( 'mobile', $data['strategy'] ); ?>>
						<?php esc_html_e( 'As a phone', 'solseo' ); ?>
					</label>
					&nbsp;
					<label>
						<input type="radio" name="solseo_speed_strategy" value="desktop" <?php checked( 'desktop', $data['strategy'] ); ?>>
						<?php esc_html_e( 'As a desktop', 'solseo' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Check this page', 'solseo' ); ?></button>
					<span class="description"><?php esc_html_e( 'Takes about twenty seconds. The page will sit there until it comes back.', 'solseo' ); ?></span>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( $result ) : ?>
		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'What Google measured', 'solseo' ); ?></h2>

			<?php if ( null !== $result['score'] ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: a score out of a hundred. */
						esc_html__( 'A test run on Google\'s own machine scored this page %d out of 100.', 'solseo' ),
						(int) $result['score']
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			$measured = array(
				'field'  => __( 'What real visitors to this page measured', 'solseo' ),
				'origin' => __( 'What real visitors to the whole site measured', 'solseo' ),
			);
			?>

			<?php foreach ( $measured as $which => $heading ) : ?>
				<?php if ( empty( $result[ $which ]['has'] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>

				<h3><?php echo esc_html( $heading ); ?></h3>

				<div class="table-wrap">
					<table class="solseo-table">
						<tbody>
						<?php foreach ( $result[ $which ]['metrics'] as $metric => $reading ) : ?>
							<?php $about = Speed_Tab::measure( $metric ); ?>
							<tr>
								<th scope="row"><?php echo esc_html( $about['label'] ); ?></th>
								<td><?php echo esc_html( Speed_Tab::said( $reading['value'], $about['unit'] ) ); ?></td>
								<td><?php echo esc_html( Speed_Tab::band( $reading['band'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

			<?php if ( empty( $result['field']['has'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'Google has no measurements from real visitors to this page yet. That needs a few thousand visits, so a quiet page never gets them, and the test above is the whole answer.', 'solseo' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="solseo-card solseo-card-wide">
			<h2><?php esc_html_e( 'What is making it slow', 'solseo' ); ?></h2>

			<?php if ( ! $result['findings'] ) : ?>
				<p><?php esc_html_e( 'Nothing worth changing came back. That is a good result.', 'solseo' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Worst first. Each one names the plugin, theme or file behind it, or says it could not tell.', 'solseo' ); ?></p>

				<?php foreach ( $result['findings'] as $finding ) : ?>
					<h3><?php echo esc_html( $finding['title'] ); ?></h3>

					<?php if ( '' !== $finding['says'] ) : ?>
						<p class="description"><?php echo esc_html( $finding['says'] ); ?></p>
					<?php endif; ?>

					<ul class="solseo-plain-list">
						<?php foreach ( $finding['causes'] as $cause ) : ?>
							<li><?php echo esc_html( $cause['says'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
