<?php
/**
 * The placeholders a template can use.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.
?>
<div class="solseo-card solseo-variables">
	<h2><?php esc_html_e( 'Placeholders', 'solseo' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Anything that cannot be worked out for a page is left out, and the spare separators go with it.', 'solseo' ); ?></p>

	<table class="solseo-table">
		<tbody>
		<?php foreach ( $data['variables'] as $name => $description ) : ?>
			<tr>
				<td><code>{<?php echo esc_html( $name ); ?>}</code></td>
				<td><?php echo esc_html( $description ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
