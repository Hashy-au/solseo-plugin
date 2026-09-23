<?php
/**
 * The published pages with the lowest scores, each linking into the editor.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

if ( ! $data['weakest'] ) {
	return;
}
?>
<h4><?php esc_html_e( 'Worth looking at first', 'solseo' ); ?></h4>
<ul class="solseo-widget-list">
	<?php foreach ( $data['weakest'] as $row ) : ?>
		<li>
			<span class="solseo-pill solseo-band-<?php echo esc_attr( \SolSEO\Analysis\Analyser::band( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span>
			<?php if ( $row['edit'] ) : ?>
				<a href="<?php echo esc_url( $row['edit'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $row['title'] ); ?>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
