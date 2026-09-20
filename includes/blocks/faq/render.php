<?php
/**
 * The questions and answers block, on the front of the site.
 *
 * Rendered here rather than saved into the post, so the markup can be corrected
 * in a later release without every page that uses it turning into a block
 * validation error in somebody's editor.
 *
 * @package SolSEO
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$solseo_items = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : array();
$solseo_pairs = \SolSEO\Frontend\Faq::pairs( $solseo_items );

if ( ! $solseo_pairs ) {
	return;
}

$solseo_heading = isset( $attributes['heading'] ) ? trim( (string) $attributes['heading'] ) : '';
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'solseo-faq' ) ) ); ?>>
	<?php if ( '' !== $solseo_heading ) : ?>
		<h2 class="solseo-faq__heading"><?php echo esc_html( $solseo_heading ); ?></h2>
	<?php endif; ?>

	<div class="solseo-faq__list">
		<?php foreach ( $solseo_pairs as $solseo_pair ) : ?>
			<details class="solseo-faq__item">
				<summary class="solseo-faq__question"><?php echo esc_html( $solseo_pair['question'] ); ?></summary>
				<div class="solseo-faq__answer"><?php echo wp_kses_post( wpautop( $solseo_pair['answer'] ) ); ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</div>
