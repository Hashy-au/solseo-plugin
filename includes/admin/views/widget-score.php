<?php
/**
 * The content score: the dial, the band it falls in, and how the pages spread.
 *
 * Drawn on its own as the "content score" widget and as the top of the full
 * panel. No heading: standing alone the widget's own title says it, and inside
 * the panel it is the lead.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$bands  = $data['summary']['bands'];
$scored = max( 1, (int) $data['summary']['total'] );
?>
<div class="solseo-widget-score">
	<div class="solseo-dial solseo-dial-small solseo-band-<?php echo esc_attr( $data['band'] ); ?>" style="--solseo-dial:<?php echo (int) $data['summary']['average']; ?>">
		<span class="solseo-dial-value"><?php echo (int) $data['summary']['average']; ?></span>
	</div>
	<div>
		<strong><?php echo esc_html( $data['label'] ); ?></strong>
		<span class="description">
			<?php
			printf(
				/* translators: %d: number of pages with a score. */
				esc_html( _n( 'Average across %d page', 'Average across %d pages', (int) $data['summary']['total'], 'solseo' ) ),
				(int) $data['summary']['total']
			);
			?>
		</span>

		<?php if ( $data['summary']['total'] ) : ?>
			<span class="solseo-widget-bar">
				<?php
				$segments = array(
					'excellent' => __( 'Excellent', 'solseo' ),
					'good'      => __( 'Good', 'solseo' ),
					'fair'      => __( 'Fair', 'solseo' ),
					'poor'      => __( 'Needs work', 'solseo' ),
				);

				foreach ( $segments as $key => $name ) :
					if ( ! $bands[ $key ] ) {
						continue;
					}
					?>
					<span
						class="solseo-band-<?php echo esc_attr( $key ); ?>"
						style="width:<?php echo esc_attr( round( ( $bands[ $key ] / $scored ) * 100, 2 ) ); ?>%"
						title="<?php echo esc_attr( $name . ': ' . number_format_i18n( $bands[ $key ] ) ); ?>"
					></span>
				<?php endforeach; ?>
			</span>
		<?php endif; ?>
	</div>
</div>
