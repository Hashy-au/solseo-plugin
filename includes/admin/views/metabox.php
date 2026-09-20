<?php
/**
 * The editor box.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this file is included from inside Screen::view(), so what looks like a global here is local to that method.

$edited    = $data['post'];
$meta      = $data['meta'];
$analysis  = $data['analysis'];
$permalink = get_permalink( $edited );
?>
<div class="solseo-box" data-post="<?php echo (int) $edited->ID; ?>" data-slug="<?php echo esc_attr( $edited->post_name ); ?>">

	<div class="solseo-box-head">
		<div class="solseo-snippet">
			<span class="solseo-snippet-url"><?php echo esc_html( str_replace( array( 'https://', 'http://' ), '', (string) $permalink ) ); ?></span>
			<span class="solseo-snippet-title" data-solseo-preview="title"><?php echo esc_html( $meta['title'] ? $meta['title'] : get_the_title( $edited ) ); ?></span>
			<span class="solseo-snippet-description" data-solseo-preview="description"><?php echo esc_html( $meta['description'] ? $meta['description'] : \SolSEO\Content::summary( $edited->ID, 25 ) ); ?></span>
		</div>

		<div class="solseo-dial solseo-band-<?php echo esc_attr( $analysis['band'] ); ?>" data-solseo-dial style="--solseo-dial:<?php echo (int) $analysis['score']; ?>">
			<span class="solseo-dial-value"><?php echo (int) $analysis['score']; ?></span>
			<span class="solseo-dial-label"><?php echo esc_html( \SolSEO\Analysis\Analyser::band_label( $analysis['band'] ) ); ?></span>
		</div>
	</div>

	<?php if ( ! empty( $analysis['source']['slug'] ) && 'stored' !== $analysis['source']['slug'] ) : ?>
		<p class="solseo-standing-source">
			<?php

			if ( '' !== $analysis['source']['note'] ) {
				echo esc_html( $analysis['source']['note'] );
			} else {
				printf(
					/* translators: %s: the page builder's name, such as Elementor. */
					esc_html__( 'Scored on the page %s draws, not on what is stored.', 'solseo' ),
					esc_html( $analysis['source']['label'] )
				);
			}

			?>
		</p>
	<?php endif; ?>

	<div class="solseo-box-tabs">
		<button type="button" class="solseo-tab is-active" data-solseo-tab="general"><?php esc_html_e( 'General', 'solseo' ); ?></button>
		<button type="button" class="solseo-tab" data-solseo-tab="social"><?php esc_html_e( 'Social', 'solseo' ); ?></button>
		<button type="button" class="solseo-tab" data-solseo-tab="advanced"><?php esc_html_e( 'Advanced', 'solseo' ); ?></button>
		<?php if ( ! empty( $data['google']['connected'] ) ) : ?>
			<button type="button" class="solseo-tab" data-solseo-tab="search"><?php esc_html_e( 'In Google', 'solseo' ); ?></button>
		<?php endif; ?>
		<?php

		/**
		 * Print another tab button in the editor box.
		 *
		 * A button carries data-solseo-tab and its panel carries a matching
		 * data-solseo-panel. The switching is already wired.
		 *
		 * @param \WP_Post $post The post being edited.
		 */
		do_action( 'solseo_metabox_tabs', $edited );

		?>
	</div>

	<div class="solseo-panel is-active" data-solseo-panel="general">
		<p>
			<label for="solseo-focus-keyword"><?php esc_html_e( 'Focus keyword', 'solseo' ); ?></label>
			<input type="text" id="solseo-focus-keyword" name="solseo[focus_keyword]" value="<?php echo esc_attr( $meta['focus_keyword'] ); ?>" data-solseo-field="keyword" class="widefat">
			<span class="description"><?php esc_html_e( 'The phrase this page should be found for. One phrase, not a list.', 'solseo' ); ?></span>
		</p>

		<p>
			<label for="solseo-keywords"><?php esc_html_e( 'Other phrases', 'solseo' ); ?></label>
			<input type="text" id="solseo-keywords" name="solseo[keywords]" value="<?php echo esc_attr( $meta['keywords'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Separated by commas', 'solseo' ); ?>">
		</p>

		<p>
			<label for="solseo-title"><?php esc_html_e( 'SEO title', 'solseo' ); ?></label>
			<input type="text" id="solseo-title" name="solseo[title]" value="<?php echo esc_attr( $meta['title'] ); ?>" data-solseo-field="title" class="widefat" placeholder="<?php echo esc_attr( $data['settings']['title'] ); ?>">
			<span class="solseo-gauge" data-solseo-gauge="title"><span></span></span>
		</p>

		<p>
			<label for="solseo-description"><?php esc_html_e( 'Meta description', 'solseo' ); ?></label>
			<textarea id="solseo-description" name="solseo[description]" rows="3" data-solseo-field="description" class="widefat"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
			<span class="solseo-gauge" data-solseo-gauge="description"><span></span></span>
		</p>

		<div class="solseo-checks" data-solseo-checks>
			<?php require __DIR__ . '/metabox-checks.php'; ?>
		</div>

		<?php
		/*
		 * Where the duplicate phrase warning and the accessibility findings are
		 * drawn. Empty on load and filled by the same answer that paints the
		 * checks above it, so the classic box says the same things the block
		 * editor panel does without a second request.
		 */
		?>
		<div class="solseo-notes" data-solseo-notes></div>

		<?php

		/**
		 * Print anything that belongs under the list of checks.
		 *
		 * @param array    $analysis The analysis just run.
		 * @param \WP_Post $post     The post being edited.
		 */
		do_action( 'solseo_metabox_after_checks', $analysis, $edited );

		?>
	</div>

	<div class="solseo-panel" data-solseo-panel="social">
		<p>
			<label for="solseo-og-title"><?php esc_html_e( 'Title when shared', 'solseo' ); ?></label>
			<input type="text" id="solseo-og-title" name="solseo[og_title]" value="<?php echo esc_attr( $meta['og_title'] ); ?>" class="widefat">
		</p>
		<p>
			<label for="solseo-og-description"><?php esc_html_e( 'Description when shared', 'solseo' ); ?></label>
			<textarea id="solseo-og-description" name="solseo[og_description]" rows="2" class="widefat"><?php echo esc_textarea( $meta['og_description'] ); ?></textarea>
		</p>
		<p>
			<span class="solseo-label"><?php esc_html_e( 'Image when shared', 'solseo' ); ?></span>
			<?php \SolSEO\Admin\Fields::image( 'solseo[og_image]', (int) $meta['og_image'] ); ?>
			<span class="description"><?php esc_html_e( 'Falls back to the featured image, then to the site default.', 'solseo' ); ?></span>
		</p>
		<p>
			<label for="solseo-twitter-title"><?php esc_html_e( 'Card title', 'solseo' ); ?></label>
			<input type="text" id="solseo-twitter-title" name="solseo[twitter_title]" value="<?php echo esc_attr( $meta['twitter_title'] ); ?>" class="widefat">
		</p>
		<p>
			<label for="solseo-twitter-description"><?php esc_html_e( 'Card description', 'solseo' ); ?></label>
			<textarea id="solseo-twitter-description" name="solseo[twitter_description]" rows="2" class="widefat"><?php echo esc_textarea( $meta['twitter_description'] ); ?></textarea>
		</p>
	</div>

	<div class="solseo-panel" data-solseo-panel="advanced">
		<p>
			<label for="solseo-canonical"><?php esc_html_e( 'Canonical address', 'solseo' ); ?></label>
			<input type="url" id="solseo-canonical" name="solseo[canonical]" value="<?php echo esc_attr( $meta['canonical'] ); ?>" class="widefat" placeholder="<?php echo esc_attr( (string) $permalink ); ?>">
			<span class="description"><?php esc_html_e( 'Point elsewhere only when this page repeats another one.', 'solseo' ); ?></span>
		</p>

		<fieldset class="solseo-fieldset">
			<legend><?php esc_html_e( 'Search engines', 'solseo' ); ?></legend>
			<label><input type="checkbox" name="solseo[robots_noindex]" value="1" <?php checked( $meta['robots_noindex'] ); ?>> <?php esc_html_e( 'Keep this page out of search results', 'solseo' ); ?></label>
			<label><input type="checkbox" name="solseo[robots_nofollow]" value="1" <?php checked( $meta['robots_nofollow'] ); ?>> <?php esc_html_e( 'Do not follow the links on this page', 'solseo' ); ?></label>
			<label><input type="checkbox" name="solseo[robots_advanced][]" value="noimageindex" <?php checked( in_array( 'noimageindex', (array) $meta['robots_advanced'], true ) ); ?>> <?php esc_html_e( 'Keep the images out of image search', 'solseo' ); ?></label>
			<label><input type="checkbox" name="solseo[robots_advanced][]" value="noarchive" <?php checked( in_array( 'noarchive', (array) $meta['robots_advanced'], true ) ); ?>> <?php esc_html_e( 'Do not keep a cached copy', 'solseo' ); ?></label>
		</fieldset>

		<p>
			<label for="solseo-schema-type"><?php esc_html_e( 'Structured data', 'solseo' ); ?></label>
			<select id="solseo-schema-type" name="solseo[schema_type]" class="widefat">
				<option value=""><?php esc_html_e( 'Use the default for this post type', 'solseo' ); ?></option>
				<?php foreach ( \SolSEO\Admin\Titles_Screen::schema_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['schema_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	</div>

	<?php if ( ! empty( $data['google']['connected'] ) ) : ?>
		<?php
		/*
		 * Empty on purpose. Asking Google here would mean a call on every
		 * editor load for a panel most people never open, and the answer is
		 * worth six hours, not one page view. editor.js fills it the first
		 * time somebody opens this tab, from the same route the block
		 * editor's panel reads.
		 */
		?>
		<div class="solseo-panel" data-solseo-panel="search">
			<div data-solseo-search-console data-post="<?php echo (int) $edited->ID; ?>">
				<p class="description"><?php esc_html_e( 'Asking Google about this page', 'solseo' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php

	/**
	 * Print another panel in the editor box.
	 *
	 * A panel carries data-solseo-panel matching the button added on
	 * solseo_metabox_tabs.
	 *
	 * @param WP_Post $post     The post being edited.
	 * @param array   $analysis The analysis just run.
	 */
	do_action( 'solseo_metabox_panels', $edited, $analysis );

	?>
</div>
