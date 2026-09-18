<?php
/**
 * The five questions worth asking a new site, on five screens.
 *
 * It is reached from the menu. Nothing redirects anybody here when the plugin
 * is switched on, and no notice asks them to come. The plugin works without a
 * single one of these answers, which is what makes a wizard a courtesy rather
 * than a toll gate.
 *
 * Every step writes the same settings the ordinary screens write, so running
 * it twice is harmless and answering a question here and changing it later on
 * its own screen are the same act.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

use SolSEO\Hub\Connection;
use SolSEO\Options;
use SolSEO\Tools\Import;
use SolSEO\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * The setup screen.
 */
class Setup_Screen extends Screen {

	const PAGE = 'solseo-setup';

	/** Where the progress is kept. */
	const OPTION = 'solseo_setup';

	/** How many steps there are. */
	const STEPS = 5;

	/**
	 * Handle the forms.
	 */
	public static function load() {
		if ( self::submitted( 'solseo_setup_site' ) ) {
			self::save_site();
		}

		if ( self::submitted( 'solseo_setup_titles' ) ) {
			self::save_titles();
		}

		if ( self::submitted( 'solseo_setup_indexing' ) ) {
			self::save_indexing();
		}

		if ( self::submitted( 'solseo_setup_sitemap' ) ) {
			self::save_sitemap();
		}

		if ( self::submitted( 'solseo_setup_connect' ) ) {
			self::save_connect();
		}

		if ( self::submitted( 'solseo_setup_finish' ) ) {
			self::mark( self::STEPS, true );
			self::go_back( self::PAGE, array( 'step' => 'done' ) );
		}
	}

	/**
	 * Draw the screen.
	 */
	public static function render() {
		self::notice();

		$progress = self::progress();
		$step     = self::step( $progress );

		if ( 'done' === $step ) {
			self::view( 'setup-done', array( 'connected' => Connection::is_connected() ) );

			return;
		}

		self::rail( $step, $progress );

		$options = Options::all();

		if ( 1 === $step ) {
			self::view( 'setup-site', array( 'options' => $options ) );

			return;
		}

		if ( 2 === $step ) {
			self::view(
				'setup-titles',
				array(
					'options'    => $options,
					'separators' => Variables::separators(),
				)
			);

			return;
		}

		if ( 3 === $step ) {
			self::view(
				'setup-indexing',
				array(
					'post_types' => self::post_type_rows(),
					'taxonomies' => self::taxonomy_rows(),
				)
			);

			return;
		}

		if ( 4 === $step ) {
			self::view( 'setup-sitemap', array( 'options' => $options ) );

			return;
		}

		self::view(
			'setup-finish',
			array(
				'sources'   => Import::sources(),
				'connected' => Connection::is_connected(),
				'summary'   => Connection::summary(),
			)
		);
	}

	/**
	 * Which step is being drawn.
	 *
	 * @param array $progress What has been done so far.
	 * @return int|string A step number, or done.
	 */
	protected static function step( array $progress ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which step to draw.
		$asked = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';

		if ( 'done' === $asked ) {
			return 'done';
		}

		if ( $asked && (int) $asked >= 1 && (int) $asked <= self::STEPS ) {
			return (int) $asked;
		}

		// Finished once means start again from the top, not stop for ever.
		if ( ! empty( $progress['done'] ) ) {
			return 1;
		}

		return max( 1, min( self::STEPS, (int) $progress['step'] ) );
	}

	/**
	 * The row of steps across the top.
	 *
	 * @param int   $step     The step being drawn.
	 * @param array $progress What has been done so far.
	 */
	protected static function rail( $step, array $progress ) {
		$labels = array(
			1 => __( 'Who the site is', 'solseo' ),
			2 => __( 'Titles', 'solseo' ),
			3 => __( 'What gets found', 'solseo' ),
			4 => __( 'Sitemap', 'solseo' ),
			5 => __( 'Finish', 'solseo' ),
		);

		echo '<ol class="solseo-steps">';

		foreach ( $labels as $number => $label ) {
			$classes = 'solseo-step';

			if ( $number === $step ) {
				$classes .= ' is-current';
			} elseif ( $number < (int) $progress['step'] || ! empty( $progress['done'] ) ) {
				$classes .= ' is-done';
			}

			printf(
				'<li class="%1$s"><a href="%2$s"><span class="solseo-step-number">%3$d</span> %4$s</a></li>',
				esc_attr( $classes ),
				esc_url(
					add_query_arg(
						array(
							'page' => self::PAGE,
							'step' => $number,
						),
						admin_url( 'admin.php' )
					)
				),
				(int) $number,
				esc_html( $label )
			);
		}

		echo '</ol>';
	}

	/**
	 * Step one: who the site is.
	 */
	protected static function save_site() {
		$input = self::input();

		Options::update(
			array(
				'entity_type'     => in_array( $input['entity_type'], array( 'organisation', 'person' ), true ) ? $input['entity_type'] : 'organisation',
				'entity_name'     => sanitize_text_field( $input['entity_name'] ),
				'entity_logo'     => (int) $input['entity_logo'],
				'entity_profiles' => self::lines( $input['entity_profiles'] ),
			)
		);

		self::onward( 1 );
	}

	/**
	 * Step two: titles and the separator.
	 */
	protected static function save_titles() {
		$input      = self::input();
		$separators = Variables::separators();
		$chosen     = sanitize_text_field( $input['separator'] );

		Options::update(
			array(
				'separator'        => isset( $separators[ $chosen ] ) ? $chosen : '-',
				'home_title'       => sanitize_text_field( $input['home_title'] ),
				'home_description' => sanitize_text_field( $input['home_description'] ),
			)
		);

		self::onward( 2 );
	}

	/**
	 * Step three: what gets found.
	 *
	 * Writes only the noindex flag on each row, merged into whatever else the
	 * Titles and Meta screen holds for that post type, so a template somebody
	 * has already written is never touched by a wizard step.
	 */
	protected static function save_indexing() {
		$input   = self::input();
		$found   = isset( $input['found'] ) && is_array( $input['found'] ) ? $input['found'] : array();
		$types   = Options::get( 'post_types', array() );
		$taxes   = Options::get( 'taxonomies', array() );
		$chosen  = isset( $found['post_types'] ) ? (array) $found['post_types'] : array();
		$chosen  = array_map( 'sanitize_key', $chosen );
		$tchosen = isset( $found['taxonomies'] ) ? (array) $found['taxonomies'] : array();
		$tchosen = array_map( 'sanitize_key', $tchosen );

		foreach ( solseo_post_types() as $type ) {
			$row            = isset( $types[ $type ] ) ? (array) $types[ $type ] : array();
			$row['noindex'] = ! in_array( $type, $chosen, true );
			$types[ $type ] = $row;
		}

		foreach ( solseo_taxonomies() as $taxonomy ) {
			$row                = isset( $taxes[ $taxonomy ] ) ? (array) $taxes[ $taxonomy ] : array();
			$row['noindex']     = ! in_array( $taxonomy, $tchosen, true );
			$taxes[ $taxonomy ] = $row;
		}

		Options::update(
			array(
				'post_types' => $types,
				'taxonomies' => $taxes,
			)
		);

		self::onward( 3 );
	}

	/**
	 * Step four: the sitemap.
	 */
	protected static function save_sitemap() {
		$input = self::input();

		Options::update( array( 'sitemap_enabled' => ! empty( $input['sitemap_enabled'] ) ) );

		\SolSEO\Sitemaps\Controller::clear_cache();
		flush_rewrite_rules( false );

		self::onward( 4 );
	}

	/**
	 * Step five: the pairing code, when somebody pastes one in.
	 */
	protected static function save_connect() {
		$input = self::input();
		$code  = sanitize_text_field( $input['code'] );

		if ( '' === $code ) {
			self::remember( __( 'Paste the code from your account first.', 'solseo' ), 'error' );
			self::go_back( self::PAGE, array( 'step' => 5 ) );
		}

		$joined = Connection::connect( $code );

		if ( is_wp_error( $joined ) ) {
			self::remember( $joined->get_error_message(), 'error' );
		} else {
			self::remember( __( 'Connected.', 'solseo' ) );
		}

		self::go_back( self::PAGE, array( 'step' => 5 ) );
	}

	/**
	 * Note a step is behind us and draw the next one.
	 *
	 * @param int $step The step just finished.
	 */
	protected static function onward( $step ) {
		self::mark( $step + 1, false );
		self::remember( __( 'Saved.', 'solseo' ) );
		self::go_back( self::PAGE, array( 'step' => min( self::STEPS, $step + 1 ) ) );
	}

	/**
	 * Where somebody has got to.
	 *
	 * @return array Keys: step, done, updated_at.
	 */
	public static function progress() {
		$stored = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'step'       => 1,
				'done'       => false,
				'updated_at' => '',
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * Write down where somebody has got to.
	 *
	 * @param int  $step The furthest step reached.
	 * @param bool $done Whether the last step was finished.
	 */
	protected static function mark( $step, $done ) {
		$progress = self::progress();

		update_option(
			self::OPTION,
			array(
				'step'       => max( (int) $progress['step'], min( self::STEPS, (int) $step ) ),
				'done'       => $done ? true : ! empty( $progress['done'] ),
				'updated_at' => current_time( 'mysql', true ),
			),
			false
		);
	}

	/**
	 * Whether the wizard has ever been finished.
	 *
	 * @return bool
	 */
	public static function finished() {
		$progress = self::progress();

		return ! empty( $progress['done'] );
	}

	/**
	 * The posted fields, with every key present.
	 *
	 * @return array
	 */
	protected static function input() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised by its caller.
		$input = isset( $_POST['solseo'] ) ? wp_unslash( $_POST['solseo'] ) : array();

		return array_merge(
			array(
				'entity_type'      => 'organisation',
				'entity_name'      => '',
				'entity_logo'      => 0,
				'entity_profiles'  => '',
				'separator'        => '-',
				'home_title'       => '',
				'home_description' => '',
				'sitemap_enabled'  => false,
				'code'             => '',
			),
			is_array( $input ) ? $input : array()
		);
	}

	/**
	 * One address per line, cleaned up.
	 *
	 * @param string $text What was typed.
	 * @return array
	 */
	protected static function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$lines = array_map( 'trim', (array) $lines );
		$lines = array_map( 'esc_url_raw', $lines );

		return array_values( array_filter( $lines ) );
	}

	/**
	 * The post types, with whether each is currently found.
	 *
	 * @return array
	 */
	protected static function post_type_rows() {
		$rows = array();

		foreach ( solseo_post_types() as $type ) {
			$object = get_post_type_object( $type );

			$rows[] = array(
				'name'  => $type,
				'label' => $object ? $object->labels->name : $type,
				'found' => ! Options::post_type( $type )['noindex'],
			);
		}

		return $rows;
	}

	/**
	 * The taxonomies, with whether each is currently found.
	 *
	 * @return array
	 */
	protected static function taxonomy_rows() {
		$rows = array();

		foreach ( solseo_taxonomies() as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );

			$rows[] = array(
				'name'  => $taxonomy,
				'label' => $object ? $object->labels->name : $taxonomy,
				'found' => ! Options::taxonomy( $taxonomy )['noindex'],
			);
		}

		return $rows;
	}
}
