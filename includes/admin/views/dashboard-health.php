<?php
/**
 * The site level checks.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="solseo-card">
	<h2><?php esc_html_e( 'Site checks', 'solseo' ); ?></h2>

	<ul class="solseo-checks">
		<?php foreach ( $data['checks'] as $check ) : ?>
			<li>
				<span class="solseo-dot solseo-band-<?php echo esc_attr( 'good' === $check['status'] ? 'excellent' : $check['status'] ); ?>"></span>
				<div>
					<strong><?php echo esc_html( $check['label'] ); ?></strong>
					<span class="description">
					<?php
					echo wp_kses(
						$check['note'],
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
								'rel'    => array(),
							),
						)
					);
					?>
												</span>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
