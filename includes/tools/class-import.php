<?php
/**
 * Copies SEO fields from another plugin into SolSEO.
 *
 * Nothing is moved. The fields it reads are left exactly as they were, so the
 * site can be put back by switching the other plugin on again.
 *
 * This class is the orchestrator. Where the fields actually live is the
 * reader's problem: most plugins keep them in post meta, one keeps them in a
 * table of its own, and a source says which by carrying a `table` key.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads another plugin's fields and writes ours.
 */
class Import {

	/**
	 * The sources that have something to import, with their names and counts.
	 *
	 * @return array Keyed by prefix. Each entry has name, installed and found.
	 */
	public static function sources() {
		$out = array();

		foreach ( self::definitions() as $prefix => $source ) {
			$found = self::count( $prefix );

			if ( ! $found ) {
				continue;
			}

			$out[ $prefix ] = array(
				'name'      => self::name( $source['plugins'], $prefix ),
				'installed' => self::installed( $source['plugins'] ),
				'found'     => $found,
				'plugin'    => self::active_plugin( $source['plugins'] ),
				'plugins'   => self::active_plugins( $source['plugins'] ),
			);
		}

		return $out;
	}

	/**
	 * Every source whose plugin is switched on, whether it holds data or not.
	 *
	 * Two plugins writing title tags is a problem even when one of them has
	 * nothing stored, so this asks a different question from sources().
	 *
	 * @return array Keyed by prefix. Each entry has name, plugin and found.
	 */
	public static function conflicts() {
		$out = array();

		foreach ( self::definitions() as $prefix => $source ) {
			$plugin = self::active_plugin( $source['plugins'] );

			if ( ! $plugin ) {
				continue;
			}

			$out[ $prefix ] = array(
				'name'    => self::name( $source['plugins'], $prefix ),
				'plugin'  => $plugin,
				'plugins' => self::active_plugins( $source['plugins'] ),
				'found'   => self::count( $prefix ),
			);
		}

		return $out;
	}

	/**
	 * How many posts hold a title or description from this source.
	 *
	 * @param string $prefix Source prefix.
	 * @return int
	 */
	public static function count( $prefix ) {
		$source = self::definition( $prefix );

		if ( ! $source ) {
			return 0;
		}

		$reader = self::reader( $source );

		return $reader::count( $source );
	}

	/**
	 * The next posts to work through, after a cursor.
	 *
	 * @param string $prefix Source prefix.
	 * @param int    $after  The last post ID finished.
	 * @param int    $limit  How many to return.
	 * @return array Post IDs, ascending.
	 */
	public static function ids_after( $prefix, $after, $limit ) {
		$source = self::definition( $prefix );

		if ( ! $source ) {
			return array();
		}

		$reader = self::reader( $source );

		return $reader::ids_after( $source, $after, $limit );
	}

	/**
	 * Posts that hold a title or description here and neither in SolSEO.
	 *
	 * This is what the verification pass asks after the last chunk. An empty
	 * answer is the only thing that earns the offer to switch the other
	 * plugin off.
	 *
	 * @param string $prefix Source prefix.
	 * @param int    $limit  How many to return.
	 * @return array Post IDs.
	 */
	public static function unverified( $prefix, $limit = 200 ) {
		$source = self::definition( $prefix );

		if ( ! $source ) {
			return array();
		}

		$reader = self::reader( $source );

		return $reader::unverified( $source, $limit );
	}

	/**
	 * A sample of what the import would change.
	 *
	 * @param string $prefix Source prefix.
	 * @param int    $limit  How many rows to show.
	 * @return array
	 */
	public static function preview( $prefix, $limit = 20 ) {
		$rows = array();

		foreach ( self::ids_after( $prefix, 0, $limit ) as $post_id ) {
			foreach ( self::read( $prefix, $post_id ) as $field => $value ) {
				if ( '' === $value || is_bool( $value ) ) {
					continue;
				}

				$rows[] = array(
					'post_id' => $post_id,
					'title'   => get_the_title( $post_id ),
					'field'   => $field,
					'value'   => $value,
					'current' => Meta::get( $post_id, $field ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Copy one post.
	 *
	 * @param string $prefix    Source prefix.
	 * @param int    $post_id   Post ID.
	 * @param bool   $overwrite Whether to replace fields SolSEO already holds.
	 * @return bool Whether anything was written.
	 */
	public static function copy( $prefix, $post_id, $overwrite ) {
		$changes = array();

		foreach ( self::read( $prefix, $post_id ) as $field => $value ) {
			if ( '' === $value || array() === $value ) {
				continue;
			}

			if ( ! $overwrite && '' !== (string) Meta::get( $post_id, $field ) && ! is_bool( $value ) ) {
				continue;
			}

			$changes[ $field ] = $value;
		}

		if ( ! $changes ) {
			return false;
		}

		Meta::save( $post_id, $changes );

		return true;
	}

	/**
	 * Read one post's fields from a source and put them in our shape.
	 *
	 * @param string $prefix  Source prefix.
	 * @param int    $post_id Post ID.
	 * @return array
	 */
	public static function read( $prefix, $post_id ) {
		$source = self::definition( $prefix );

		if ( ! $source ) {
			return array();
		}

		$reader = self::reader( $source );

		return $reader::read( $source, $post_id );
	}

	/**
	 * Turn a source's raw values into ours.
	 *
	 * Shared by both readers, and pure, so what a row becomes is decided in
	 * one place and tested without a database.
	 *
	 * @param array $raw    Field name to stored value.
	 * @param array $source Source definition.
	 * @return array
	 */
	public static function shape( array $raw, array $source ) {
		$style  = isset( $source['variables'] ) ? $source['variables'] : 'percent';
		$images = isset( $source['image_as'] ) ? $source['image_as'] : 'id';
		$values = array();

		foreach ( $raw as $field => $stored ) {
			if ( 'og_image' === $field || 'twitter_image' === $field ) {
				$id = 'url' === $images ? self::image_id( (string) $stored ) : (int) $stored;

				/*
				 * A URL we cannot resolve to something in this library is worth
				 * nothing to us, and writing 0 would look like a chosen image
				 * that had been deleted.
				 */
				if ( $id ) {
					$values[ $field ] = $id;
				}

				continue;
			}

			/*
			 * Some sources keep a list here. Ours is one phrase, which is what
			 * the editor says and what every keyword check measures against,
			 * so the first is taken and the rest are left behind.
			 */
			if ( 'focus_keyword' === $field ) {
				$parts            = explode( ',', (string) $stored );
				$values[ $field ] = trim( $parts[0] );

				continue;
			}

			$values[ $field ] = self::translate( (string) $stored, $style );
		}

		return $values;
	}

	/**
	 * The attachment behind an address, where there is one here.
	 *
	 * @param string $url Image address.
	 * @return int
	 */
	protected static function image_id( $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			return 0;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );

		// Somebody else's image is not in this library, so do not go looking.
		if ( $host && $home && $host !== $home ) {
			return 0;
		}

		return (int) attachment_url_to_postid( $url );
	}

	/**
	 * Turn another plugin's placeholders into ours.
	 *
	 * Anything with no equivalent is left alone, so a template that cannot be
	 * translated is obvious on the settings screen rather than silently wrong.
	 *
	 * @param string $template Template from the source.
	 * @param string $style    How that source writes a placeholder.
	 * @return string
	 */
	public static function translate( $template, $style = 'percent' ) {
		/*
		 * One source stores the finished title rather than a template, so a
		 * per cent sign in somebody's title is a per cent sign and nothing
		 * else. Touching it would corrupt the one thing we were copying.
		 */
		if ( 'none' === $style ) {
			return $template;
		}

		$map = self::variable_map();

		if ( 'hash' === $style ) {
			return preg_replace_callback(
				'/#([a-z_]+)/',
				function ( $found ) use ( $map ) {
					return isset( $map[ $found[1] ] ) ? '{' . $map[ $found[1] ] . '}' : $found[0];
				},
				$template
			);
		}

		return preg_replace_callback(
			'/%%?([a-z_]+)%%?/',
			function ( $found ) use ( $map ) {
				return isset( $map[ $found[1] ] ) ? '{' . $map[ $found[1] ] . '}' : $found[0];
			},
			$template
		);
	}

	/**
	 * Every name another plugin gives a placeholder we also have.
	 *
	 * @return array
	 */
	protected static function variable_map() {
		return array(
			'title'                => 'title',
			'post_title'           => 'title',
			'sitename'             => 'sitename',
			'site_title'           => 'sitename',
			'sitetitle'            => 'sitename',
			'blog_title'           => 'sitename',
			'sitedesc'             => 'sitedesc',
			'tagline'              => 'sitedesc',
			'site_description'     => 'sitedesc',
			'sep'                  => 'sep',
			'separator'            => 'sep',
			'separator_sa'         => 'sep',
			'excerpt'              => 'excerpt',
			'excerpt_only'         => 'excerpt',
			'post_excerpt'         => 'excerpt',
			'post_excerpt_only'    => 'excerpt',
			'category'             => 'category',
			'categories'           => 'category',
			'post_category'        => 'category',
			'primary_category'     => 'primary_category',
			'tag'                  => 'tag',
			'post_tag'             => 'tag',
			'term'                 => 'term',
			'term_title'           => 'term',
			'taxonomy_title'       => 'term',
			'term_description'     => 'term_description',
			'taxonomy_description' => 'term_description',
			'name'                 => 'author',
			'author'               => 'author',
			'author_name'          => 'author',
			'post_author'          => 'author',
			'date'                 => 'date',
			'post_date'            => 'date',
			'modified'             => 'modified',
			'post_modified_date'   => 'modified',
			'page'                 => 'page',
			'page_number'          => 'page',
			'current_pagination'   => 'page',
			'searchphrase'         => 'searchphrase',
			'search_query'         => 'searchphrase',
			'search_term'          => 'searchphrase',
			'search_keywords'      => 'searchphrase',
			'currentyear'          => 'currentyear',
			'current_year'         => 'currentyear',
			'currentmonth'         => 'currentmonth',
			'current_month'        => 'currentmonth',
			'price'                => 'price',
			'wc_single_price'      => 'price',
			'sku'                  => 'sku',
			'wc_sku'               => 'sku',
		);
	}

	/**
	 * Which reader a source needs.
	 *
	 * @param array $source Source definition.
	 * @return string Class name.
	 */
	public static function reader( array $source ) {
		return empty( $source['table'] ) ? __NAMESPACE__ . '\\Meta_Source' : __NAMESPACE__ . '\\Table_Source';
	}

	/**
	 * All the source definitions.
	 *
	 * @return array
	 */
	protected static function definitions() {
		static $sources = null;

		if ( null === $sources ) {
			$sources = (array) require SOLSEO_PATH . 'includes/tools/data/sources.php';
		}

		return $sources;
	}

	/**
	 * One source definition.
	 *
	 * @param string $prefix Source prefix.
	 * @return array|null
	 */
	public static function definition( $prefix ) {
		$sources = self::definitions();

		return isset( $sources[ $prefix ] ) ? $sources[ $prefix ] : null;
	}

	/**
	 * Every plugin file any source names, so a posted one can be checked.
	 *
	 * @return array
	 */
	public static function known_plugins() {
		$out = array();

		foreach ( self::definitions() as $source ) {
			foreach ( $source['plugins'] as $plugin ) {
				$out[] = $plugin;
			}
		}

		return $out;
	}

	/**
	 * Make sure the plugin functions we lean on are loaded.
	 *
	 * These live in an admin only file, and the job endpoint is a REST
	 * request, where it is not loaded.
	 */
	protected static function need_plugin_functions() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Whether any of a source's plugins is active.
	 *
	 * @param array $plugins Plugin files.
	 * @return bool
	 */
	protected static function installed( array $plugins ) {
		return '' !== self::active_plugin( $plugins );
	}

	/**
	 * Which of a source's plugins is switched on.
	 *
	 * @param array $plugins Plugin files.
	 * @return string Plugin file, or an empty string.
	 */
	protected static function active_plugin( array $plugins ) {
		$active = self::active_plugins( $plugins );

		return $active ? $active[0] : '';
	}

	/**
	 * Every one of a source's plugins that is switched on.
	 *
	 * A source is a family, not a file: a free plugin and the paid add-on that
	 * loads on top of it. The add-on cannot run without the plugin underneath
	 * it, so switching off the first file in the list and leaving the rest on
	 * is how a site is taken down. A customer site answered every request with
	 * a 500, front end and admin both, on 2026-09-20 because the offer to
	 * switch a source off switched off one of the two files that were running.
	 *
	 * The order is the order they are defined in, which is the plugin first and
	 * its add-ons after it.
	 *
	 * @param array $plugins Plugin files.
	 * @return array Plugin files that are switched on.
	 */
	public static function active_plugins( array $plugins ) {
		self::need_plugin_functions();

		$out = array();

		foreach ( $plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$out[] = $plugin;
			}
		}

		return $out;
	}

	/**
	 * The name to show for a source, taken from the plugin's own header.
	 *
	 * @param array  $plugins Plugin files.
	 * @param string $prefix  Source prefix, used when nothing is installed.
	 * @return string
	 */
	public static function name( array $plugins, $prefix ) {
		self::need_plugin_functions();

		$installed = get_plugins();

		foreach ( $plugins as $plugin ) {
			if ( isset( $installed[ $plugin ]['Name'] ) ) {
				return $installed[ $plugin ]['Name'];
			}
		}

		/* translators: %s: the meta key prefix the fields are stored under. */
		return sprintf( __( 'Fields stored as %s', 'solseo' ), $prefix );
	}
}
