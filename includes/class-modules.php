<?php
/**
 * What this plugin is running, and what each part of it costs.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * The module registry.
 *
 * A module that is off is never booted, so it adds no hook, no query and no
 * filter. That is the whole point: thirty eight features are coming and a site
 * that wants four of them should pay for four.
 *
 * Core is not a module. Titles, meta, sitemaps, schema, the score, redirects,
 * robots and the importer are what the plugin is, and a switch that turns the
 * plugin off is called Deactivate.
 *
 * A module that owns a tab registers it from its own boot, through
 * `solseo_screen_tabs`, rather than from `Tabs::defaults()`. Then the tab goes
 * when the module goes and neither of them has to know about the other.
 */
class Modules {

	/** Slugs whose state differs from their default. Small, and read every request. */
	const OPTION = 'solseo_modules';

	/** Booted on every request. */
	const ANY = 'any';

	/** Booted only on an admin request. */
	const ADMIN = 'admin';

	/**
	 * The modules this plugin ships, as slugs and nothing else.
	 *
	 * A slug is not a word, so reading this costs no translation. That matters
	 * because booting happens on `plugins_loaded` and every `__()` before
	 * `init` makes WordPress 6.7 load the whole text domain early and write a
	 * notice about it into the site's debug.log.
	 */
	const OURS = array( 'links', 'columns', 'widget' );

	/**
	 * Hooks added by each module, when this request is measuring.
	 *
	 * @var array
	 */
	protected static $measured = array();

	/**
	 * Whether this request counts what it boots.
	 *
	 * @var bool|null
	 */
	protected static $measuring = null;

	/**
	 * Every module this plugin knows about, with the words that describe it.
	 *
	 * @return array
	 */
	public static function all() {
		return self::registered( true );
	}

	/**
	 * What each of our own modules is called, in somebody's own language.
	 *
	 * These live apart from the registry, and that is the whole point.
	 * `Modules::boot()` runs on `plugins_loaded`, which is before `init`, and
	 * WordPress 6.7 warns about a translation asked for before then because it
	 * has to load the whole text domain early to answer. Booting needs a slug,
	 * a context and a callable. It does not need the words, so it does not ask
	 * for them, and the screen that does ask runs long after `init`.
	 *
	 * Found on a real WordPress, in `debug.log`, by the lane next door.
	 *
	 * @return array Slug to label and blurb.
	 */
	public static function words() {
		return array(
			'links'   => array(
				'label' => __( 'Internal links', 'solseo' ),
				'blurb' => __( 'Records which of your pages link to which, so the score can count them and the posts list can show them.', 'solseo' ),
			),
			'columns' => array(
				'label' => __( 'Posts list column', 'solseo' ),
				'blurb' => __( 'The score, the keyword, the kind of structured data and the link counts, as a column on the posts and products lists, with editing in it.', 'solseo' ),
			),
			'widget'  => array(
				'label' => __( 'Dashboard widget', 'solseo' ),
				'blurb' => __( 'A summary of the site score on the WordPress dashboard.', 'solseo' ),
			),
		);
	}

	/**
	 * The modules, described or not.
	 *
	 * @param bool $described Whether our own entries carry their words.
	 * @return array
	 */
	protected static function registered( $described ) {
		$modules = array(
			'links'   => array(
				'default' => true,
				'context' => self::ANY,
				'boot'    => array( __NAMESPACE__ . '\\Links\\Indexer', 'init' ),
				'facts'   => array( __CLASS__, 'link_facts' ),
			),
			'columns' => array(
				'default' => true,
				'context' => self::ADMIN,
				'boot'    => array( __NAMESPACE__ . '\\Admin\\Columns', 'init' ),
			),
			'widget'  => array(
				'default' => true,
				'context' => self::ADMIN,
				'boot'    => array( __NAMESPACE__ . '\\Admin\\Dashboard_Widget', 'init' ),
			),
		);

		if ( $described ) {
			foreach ( self::words() as $slug => $words ) {
				$modules[ $slug ] = array_merge( $modules[ $slug ], $words );
			}
		}

		/**
		 * Filter the modules this site can switch on and off.
		 *
		 * An add-on registers its modules here. Each entry needs a label, a
		 * sentence, a default, a context and a boot callable, and may carry a
		 * `facts` callable returning one short line about what it holds.
		 *
		 * This runs more than once in a request, and it is deliberately not
		 * cached: an add-on that registers late would otherwise be missed for
		 * the rest of the request, and a module that quietly does not exist is
		 * the worst failure this screen can have. A callback here returns an
		 * array and does no work.
		 *
		 * @param array $modules Module slug to definition.
		 */
		$modules = (array) apply_filters( 'solseo_modules', $modules );

		/*
		 * The slugs, not the words. Asking words() which modules are ours would
		 * translate three labels to find out three slugs, which is the thing
		 * this whole arrangement exists to avoid.
		 */
		return array_filter(
			$modules,
			static function ( $module, $slug ) {
				if ( ! is_array( $module ) || empty( $module['boot'] ) || ! is_callable( $module['boot'] ) ) {
					return false;
				}

				// One of ours is described by words(); anybody else's describes itself.
				return in_array( $slug, self::OURS, true ) || ! empty( $module['label'] );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Whether a module runs on this site.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public static function enabled( $slug ) {
		return self::is_on( $slug, self::registered( false ) );
	}

	/**
	 * Whether a module runs, against a list already in hand.
	 *
	 * @param string $slug    Module slug.
	 * @param array  $modules Every module.
	 * @return bool
	 */
	protected static function is_on( $slug, array $modules ) {
		if ( ! isset( $modules[ $slug ] ) ) {
			return false;
		}

		$stored = self::stored();

		if ( array_key_exists( $slug, $stored ) ) {
			return (bool) $stored[ $slug ];
		}

		return ! empty( $modules[ $slug ]['default'] );
	}

	/**
	 * Switch a module on or off.
	 *
	 * Only a choice that differs from the default is written, so a module
	 * whose default changes in a later release follows it unless somebody has
	 * said otherwise about that module on that site.
	 *
	 * @param string $slug Module slug.
	 * @param bool   $on   Whether it runs.
	 */
	public static function set( $slug, $on ) {
		$modules = self::all();

		if ( ! isset( $modules[ $slug ] ) ) {
			return;
		}

		$stored = self::stored();
		$on     = (bool) $on;

		if ( (bool) ! empty( $modules[ $slug ]['default'] ) === $on ) {
			unset( $stored[ $slug ] );
		} else {
			$stored[ $slug ] = $on;
		}

		update_option( self::OPTION, $stored );
	}

	/**
	 * Boot every module that runs in this context.
	 *
	 * @param string $context Modules::ANY or Modules::ADMIN.
	 */
	public static function boot( $context ) {
		$measuring = self::measuring();

		/*
		 * Undescribed on purpose. This runs on `plugins_loaded`, which is
		 * before `init`, and asking for a translation there makes WordPress
		 * load the whole text domain early and write a notice about it into
		 * everybody's debug.log. Booting needs a callable, not a sentence.
		 */
		$modules = self::registered( false );

		foreach ( $modules as $slug => $module ) {
			$wanted = isset( $module['context'] ) ? $module['context'] : self::ANY;

			if ( $wanted !== $context || ! self::is_on( $slug, $modules ) ) {
				continue;
			}

			if ( ! $measuring ) {
				call_user_func( $module['boot'] );
				continue;
			}

			$before = self::hook_count();

			call_user_func( $module['boot'] );

			$added = self::hook_count() - $before;

			self::$measured[ $slug ] = isset( self::$measured[ $slug ] )
				? self::$measured[ $slug ] + $added
				: $added;
		}
	}

	/**
	 * How many hooks a module added, or null when nothing measured it.
	 *
	 * @param string $slug Module slug.
	 * @return int|null
	 */
	public static function hooks_added( $slug ) {
		return isset( self::$measured[ $slug ] ) ? self::$measured[ $slug ] : null;
	}

	/**
	 * One short line about what a module holds, when it has something to say.
	 *
	 * @param string $slug Module slug.
	 * @return string
	 */
	public static function facts( $slug ) {
		$modules = self::all();

		if ( empty( $modules[ $slug ]['facts'] ) || ! is_callable( $modules[ $slug ]['facts'] ) ) {
			return '';
		}

		return (string) call_user_func( $modules[ $slug ]['facts'] );
	}

	/**
	 * How many links the index is holding.
	 *
	 * @return string
	 */
	public static function link_facts() {
		$rows = Links\Counts::total();

		if ( $rows < 1 ) {
			return '';
		}

		/* translators: %s: a number of links. */
		return sprintf( _n( 'Holding %s link.', 'Holding %s links.', $rows, 'solseo' ), number_format_i18n( $rows ) );
	}

	/**
	 * Whether this request is counting what it boots.
	 *
	 * Only the screen that prints the numbers measures them, so every other
	 * request in the site's life pays nothing for the measurement. It also
	 * means the figures are this request's own rather than an average of
	 * requests that happened on other screens, and the screen says so.
	 *
	 * @return bool
	 */
	protected static function measuring() {
		if ( null !== self::$measuring ) {
			return self::$measuring;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen is being drawn, before WordPress has worked it out.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		self::$measuring = is_admin() && 'solseo-settings' === $page;

		return self::$measuring;
	}

	/**
	 * How many callbacks are registered right now, across every hook.
	 *
	 * @return int
	 */
	protected static function hook_count() {
		global $wp_filter;

		$count = 0;

		foreach ( (array) $wp_filter as $hook ) {
			if ( ! is_object( $hook ) || ! isset( $hook->callbacks ) ) {
				continue;
			}

			foreach ( (array) $hook->callbacks as $callbacks ) {
				$count += count( (array) $callbacks );
			}
		}

		return $count;
	}

	/**
	 * The choices this site has made.
	 *
	 * @return array
	 */
	protected static function stored() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}
}
