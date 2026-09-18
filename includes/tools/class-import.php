<?php
/**
 * Copies SEO fields from another plugin into SolSEO.
 *
 * Nothing is moved. The fields it reads are left exactly as they were, so the
 * site can be put back by switching the other plugin on again.
 *
 * @package SolSEO
 */

namespace SolSEO\Tools;

use SolSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads another plugin's meta and writes ours.
 */
class Import {

	/** How many posts one pass works through. */
	const BATCH = 100;

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
		global $wpdb;

		$source = self::definition( $prefix );

		if ( ! $source ) {
			return 0;
		}

		$keys = array( $source['post']['title'], $source['post']['description'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) AND meta_value != ''",
				$keys[0],
				$keys[1]
			)
		);
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

		foreach ( self::post_ids( $prefix, $limit, 0 ) as $post_id ) {
			$incoming = self::read( $prefix, $post_id );

			foreach ( $incoming as $field => $value ) {
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
	 * Copy one batch.
	 *
	 * @param string $prefix    Source prefix.
	 * @param bool   $overwrite Whether to replace fields SolSEO already holds.
	 * @param int    $offset    Where to start.
	 * @return array Keys: copied, offset, remaining.
	 */
	public static function run( $prefix, $overwrite, $offset = 0 ) {
		$ids    = self::post_ids( $prefix, self::BATCH, $offset );
		$copied = 0;

		foreach ( $ids as $post_id ) {
			$incoming = self::read( $prefix, $post_id );
			$changes  = array();

			foreach ( $incoming as $field => $value ) {
				if ( '' === $value || array() === $value ) {
					continue;
				}

				if ( ! $overwrite && '' !== (string) Meta::get( $post_id, $field ) && ! is_bool( $value ) ) {
					continue;
				}

				$changes[ $field ] = $value;
			}

			if ( $changes ) {
				Meta::save( $post_id, $changes );
				++$copied;
			}
		}

		$offset += count( $ids );

		return array(
			'copied'    => $copied,
			'offset'    => $offset,
			'remaining' => max( 0, self::count( $prefix ) - $offset ),
		);
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

		$values = array();

		foreach ( $source['post'] as $field => $key ) {
			$stored = get_post_meta( $post_id, $key, true );

			if ( '' === $stored || null === $stored ) {
				continue;
			}

			$values[ $field ] = in_array( $field, array( 'og_image' ), true )
				? (int) $stored
				: self::translate( (string) $stored );
		}

		foreach ( isset( $source['flags'] ) ? $source['flags'] : array() as $field => $flag ) {
			if ( (string) get_post_meta( $post_id, $flag[0], true ) === $flag[1] ) {
				$values[ $field ] = true;
			}
		}

		if ( ! empty( $source['robots'] ) ) {
			$robots = get_post_meta( $post_id, $source['robots'], true );

			if ( is_array( $robots ) ) {
				if ( in_array( 'noindex', $robots, true ) ) {
					$values['robots_noindex'] = true;
				}

				if ( in_array( 'nofollow', $robots, true ) ) {
					$values['robots_nofollow'] = true;
				}
			}
		}

		return $values;
	}

	/**
	 * Turn another plugin's placeholders into ours.
	 *
	 * Anything with no equivalent is left alone, so a template that cannot be
	 * translated is obvious on the settings screen rather than silently wrong.
	 *
	 * @param string $template Template from the source.
	 * @return string
	 */
	public static function translate( $template ) {
		$map = array(
			'title'            => 'title',
			'sitename'         => 'sitename',
			'site_title'       => 'sitename',
			'sitedesc'         => 'sitedesc',
			'sep'              => 'sep',
			'separator_sa'     => 'sep',
			'excerpt'          => 'excerpt',
			'excerpt_only'     => 'excerpt',
			'category'         => 'category',
			'categories'       => 'category',
			'primary_category' => 'primary_category',
			'tag'              => 'tag',
			'term'             => 'term',
			'term_title'       => 'term',
			'term_description' => 'term_description',
			'name'             => 'author',
			'author'           => 'author',
			'date'             => 'date',
			'modified'         => 'modified',
			'page'             => 'page',
			'searchphrase'     => 'searchphrase',
			'search_query'     => 'searchphrase',
			'currentyear'      => 'currentyear',
			'current_year'     => 'currentyear',
			'currentmonth'     => 'currentmonth',
			'current_month'    => 'currentmonth',
			'price'            => 'price',
			'sku'              => 'sku',
		);

		return preg_replace_callback(
			'/%%?([a-z_]+)%%?/',
			function ( $found ) use ( $map ) {
				return isset( $map[ $found[1] ] ) ? '{' . $map[ $found[1] ] . '}' : $found[0];
			},
			$template
		);
	}

	/**
	 * Posts holding fields from a source.
	 *
	 * @param string $prefix   Source prefix.
	 * @param int    $limit    How many.
	 * @param int    $offset   Where to start.
	 * @return array Post IDs.
	 */
	protected static function post_ids( $prefix, $limit, $offset ) {
		global $wpdb;

		$source = self::definition( $prefix );

		if ( ! $source ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_key IN (%s, %s) AND meta_value != ''
				ORDER BY post_id ASC LIMIT %d OFFSET %d",
				$source['post']['title'],
				$source['post']['description'],
				$limit,
				$offset
			)
		);

		return array_map( 'intval', (array) $ids );
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
	protected static function definition( $prefix ) {
		$sources = self::definitions();

		return isset( $sources[ $prefix ] ) ? $sources[ $prefix ] : null;
	}

	/**
	 * Whether any of a source's plugins is active.
	 *
	 * @param array $plugins Plugin files.
	 * @return bool
	 */
	protected static function installed( array $plugins ) {
		foreach ( $plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The name to show for a source, taken from the plugin's own header.
	 *
	 * @param array  $plugins Plugin files.
	 * @param string $prefix  Source prefix, used when nothing is installed.
	 * @return string
	 */
	protected static function name( array $plugins, $prefix ) {
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
