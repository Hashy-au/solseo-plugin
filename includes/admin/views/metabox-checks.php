<?php
/**
 * The list of checks inside the editor box.
 *
 * Rendered on load and again, from the same shape, by the editor script.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

foreach ( $analysis['groups'] as $group ) :
	?>
	<div class="solseo-check-group">
		<h4><?php echo esc_html( $group['label'] ); ?></h4>
		<ul>
			<?php foreach ( $group['checks'] as $check ) : ?>
				<li class="solseo-check solseo-status-<?php echo esc_attr( $check['status'] ); ?>">
					<span class="solseo-dot"></span>
					<?php echo esc_html( $check['note'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
endforeach;
