<?php
/**
 * The SEO column on the post and product lists.
 *
 * One column, at the end, holding everything worth knowing about a page at a
 * glance: what it scores, what it is aimed at, what kind of thing it says it
 * is, and how it sits in the site's own links. The pencil in the header turns
 * the same column into a place to fix the two fields that matter most.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Analysis\Analyser;
use SolSEO\Links\Counts;
use SolSEO\Meta;
use SolSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a sortable score column with the detail beside it.
 */
class Columns {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Attach to every post type the plugin manages.
	 */
	public static function register() {
		foreach ( solseo_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'sortable' ) );
		}

		add_action( 'pre_get_posts', array( __CLASS__, 'order_by_score' ) );
	}

	/**
	 * Add the column at the end.
	 *
	 * At the end rather than after the title, because it is a block rather
	 * than a badge now, and a wide block in the second column pushes the date
	 * and the author off a laptop screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['solseo_score'] = __( 'SEO', 'solseo' );

		return $columns;
	}

	/**
	 * Draw a cell.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render( $column, $post_id ) {
		if ( 'solseo_score' !== $column ) {
			return;
		}

		$score   = get_post_meta( $post_id, '_solseo_score', true );
		$keyword = Meta::get( $post_id, 'focus_keyword' );
		$band    = '' === $score ? 'none' : Analyser::band( (int) $score );
		$links   = Counts::of( $post_id );

		echo '<div class="solseo-cell">';

		// The bubble and the word for it, on one line.
		printf(
			'<div class="solseo-cell-score"><span class="solseo-pill solseo-band-%1$s">%2$s</span><span class="solseo-cell-band">%3$s</span></div>',
			esc_attr( $band ),
			'' === $score ? '&#8212;' : (int) $score,
			esc_html( '' === $score ? __( 'Never checked', 'solseo' ) : Analyser::band_label( $band ) )
		);

		self::line(
			__( 'Keyword', 'solseo' ),
			esc_html( $keyword ? $keyword : __( 'Not set', 'solseo' ) ),
			$keyword ? '' : 'is-missing'
		);

		self::line( __( 'Type', 'solseo' ), esc_html( self::schema_label( $post_id ) ) );

		self::line( __( 'Links', 'solseo' ), self::link_counts( $links ) );

		echo '</div>';

		self::edit_fields( $post_id );
	}

	/**
	 * One labelled row of the cell.
	 *
	 * @param string $label What it is.
	 * @param string $value Already escaped markup or text.
	 * @param string $tone  Extra class for the value.
	 */
	protected static function line( $label, $value, $tone = '' ) {
		printf(
			'<div class="solseo-cell-line"><span class="solseo-cell-label">%1$s:</span> <span class="solseo-cell-value %2$s">%3$s</span></div>',
			esc_html( $label ),
			esc_attr( $tone ),
			$value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every caller escapes its own value.
		);
	}

	/**
	 * The three link numbers, each behind the icon for what it counts.
	 *
	 * Three numbers on one line, because a sentence here wraps in every row and
	 * a list screen is read by scanning down a column rather than across it.
	 * Each icon carries its meaning in words for anybody hovering or listening.
	 *
	 * @param array $links Internal, outbound and incoming.
	 * @return string Escaped markup.
	 */
	protected static function link_counts( array $links ) {
		if ( null === $links['internal'] && null === $links['incoming'] ) {
			return esc_html__( 'not counted yet', 'solseo' );
		}

		$parts = array(
			array( 'admin-links', (int) $links['internal'], __( 'links from this page to other pages on this site', 'solseo' ) ),
			array( 'external', (int) $links['outbound'], __( 'links from this page out to other sites', 'solseo' ) ),
			array( 'arrow-left-alt', (int) $links['incoming'], __( 'pages on this site linking to this one', 'solseo' ) ),
		);

		$out = '';

		foreach ( $parts as $part ) {
			$out .= sprintf(
				'<span class="solseo-cell-link" title="%1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span>%3$s<span class="screen-reader-text"> %1$s</span></span>',
				esc_attr( $part[2] ),
				esc_attr( $part[0] ),
				esc_html( number_format_i18n( $part[1] ) )
			);
		}

		return $out;
	}

	/**
	 * What kind of thing this page says it is.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	protected static function schema_label( $post_id ) {
		$chosen = (string) Meta::get( $post_id, 'schema_type' );

		if ( '' === $chosen ) {
			$chosen = (string) Options::post_type( get_post_type( $post_id ) )['schema'];
		}

		$types = Titles_Screen::schema_types();

		if ( '' === $chosen || 'none' === $chosen ) {
			return __( 'No structured data', 'solseo' );
		}

		return isset( $types[ $chosen ] ) ? $types[ $chosen ] : $chosen;
	}

	/**
	 * The fields the pencil reveals, for a row this person may edit.
	 *
	 * Rendered here and hidden with CSS rather than fetched when the pencil is
	 * pressed: the values are already to hand, correctly decoded, and it saves
	 * a round trip for something somebody is about to type into.
	 *
	 * Printing them only for rows somebody may edit is a courtesy. The control
	 * is the check the endpoint makes, once per row.
	 *
	 * @param int $post_id Post ID.
	 */
	protected static function edit_fields( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			printf(
				'<div class="solseo-cell-edit" data-post="%1$d" data-readonly="1" hidden><p class="description">%2$s</p></div>',
				(int) $post_id,
				esc_html__( 'You are not allowed to edit this one.', 'solseo' )
			);

			return;
		}

		$settings = Options::post_type( get_post_type( $post_id ) );

		printf(
			'<div class="solseo-cell-edit" data-post="%1$d" hidden>
				<label class="screen-reader-text" for="solseo-row-title-%1$d">%2$s</label>
				<input type="text" id="solseo-row-title-%1$d" class="widefat" data-solseo-row="title" value="%3$s" placeholder="%4$s">
				<label class="screen-reader-text" for="solseo-row-desc-%1$d">%5$s</label>
				<textarea id="solseo-row-desc-%1$d" class="widefat" rows="2" data-solseo-row="description" placeholder="%6$s">%7$s</textarea>
			</div>',
			(int) $post_id,
			esc_html__( 'SEO title', 'solseo' ),
			esc_attr( (string) Meta::get( $post_id, 'title' ) ),
			esc_attr( $settings['title'] ),
			esc_html__( 'Meta description', 'solseo' ),
			esc_attr( $settings['description'] ),
			esc_textarea( (string) Meta::get( $post_id, 'description' ) )
		);
	}

	/**
	 * Let the column be sorted.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['solseo_score'] = 'solseo_score';

		return $columns;
	}

	/**
	 * Sort by the stored score when the column is clicked.
	 *
	 * A plain meta_key sort makes WP_Query join postmeta the strict way, and
	 * every page that has never been scored drops out of the list. Those are
	 * exactly the pages somebody sorting by score is looking for. A named
	 * meta_query with an OR relation and a NOT EXISTS arm keeps the join loose,
	 * so nothing disappears and the unscored sort as empty.
	 *
	 * @param \WP_Query $query The query about to run.
	 */
	public static function order_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'solseo_score' !== $query->get( 'orderby' ) ) {
			return;
		}

		$existing = (array) $query->get( 'meta_query' );

		$existing['relation'] = isset( $existing['relation'] ) ? $existing['relation'] : 'AND';

		$existing['solseo_scored'] = array(
			'relation' => 'OR',
			array(
				'key'     => '_solseo_score',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_solseo_score',
				'compare' => 'NOT EXISTS',
			),
		);

		$query->set( 'meta_query', $existing ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query->set( 'orderby', array( 'solseo_scored' => $query->get( 'order' ) ? $query->get( 'order' ) : 'DESC' ) );
	}
}
