<?php
/**
 * Per-post and per-term SEO fields.
 *
 * @package SolSEO
 */

namespace SolSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and registers the plugin's own meta keys.
 */
class Meta {

	const PREFIX = '_solseo_';

	/**
	 * Field name to sanitiser.
	 *
	 * @var array
	 */
	protected static $fields = array(
		'title'               => 'text',
		'description'         => 'text',
		'canonical'           => 'url',
		'focus_keyword'       => 'text',
		'keywords'            => 'list',
		'robots_noindex'      => 'bool',
		'robots_nofollow'     => 'bool',
		'robots_advanced'     => 'list',
		'og_title'            => 'text',
		'og_description'      => 'text',
		'og_image'            => 'int',
		'twitter_title'       => 'text',
		'twitter_description' => 'text',
		'twitter_image'       => 'int',
		'schema_type'         => 'text',
		'score'               => 'int',
		'score_summary'       => 'array',
	);

	/**
	 * Register meta so the REST API and the editor can see it.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_post_meta' ), 20 );
	}

	/**
	 * Declare the post meta keys against every public post type.
	 */
	public static function register_post_meta() {
		foreach ( solseo_post_types() as $post_type ) {
			self::allow_meta_in_rest( $post_type );

			foreach ( self::$fields as $field => $type ) {
				if ( 'score_summary' === $field ) {
					continue;
				}

				register_post_meta(
					$post_type,
					self::PREFIX . $field,
					array(
						'type'              => self::rest_type( $type ),
						'single'            => true,
						'show_in_rest'      => 'list' === $type ? array( 'schema' => array( 'items' => array( 'type' => 'string' ) ) ) : true,

						/*
						 * The editor panel writes these through the REST API,
						 * which does not go anywhere near Meta::save(). Without
						 * a sanitiser here, the only cleaning in the plugin
						 * would be on the path the block editor does not use.
						 */
						'sanitize_callback' => function ( $value ) use ( $field ) {
							return self::sanitise_field( $value, $field );
						},

						/*
						 * Whether somebody may edit posts is not the question.
						 * The question is whether they may edit this one.
						 */
						'auth_callback'     => function ( $allowed, $meta_key, $object_id ) {
							unset( $allowed, $meta_key );

							return current_user_can( 'edit_post', (int) $object_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Make sure a post type's meta reaches the REST API at all.
	 *
	 * The posts controller only puts a meta property in its schema for a post
	 * type that supports custom fields. Without it the editor panel reads an
	 * empty object, every edit goes nowhere, and nothing anywhere says so.
	 * Posts and pages have this support; plenty of custom post types do not.
	 *
	 * @param string $post_type Post type name.
	 */
	protected static function allow_meta_in_rest( $post_type ) {
		if ( post_type_supports( $post_type, 'custom-fields' ) ) {
			return;
		}

		/**
		 * Filter whether SolSEO adds custom field support to a post type.
		 *
		 * Returning false means the editor panel cannot save on that post
		 * type, so only turn it off for a type you do not manage.
		 *
		 * @param bool   $add       Whether to add it.
		 * @param string $post_type Post type name.
		 */
		if ( ! apply_filters( 'solseo_add_custom_fields_support', true, $post_type ) ) {
			return;
		}

		add_post_type_support( $post_type, 'custom-fields' );
	}

	/**
	 * Clean one named field, whichever path it arrived by.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $field Field name without the prefix.
	 * @return mixed
	 */
	public static function sanitise_field( $value, $field ) {
		$type = isset( self::$fields[ $field ] ) ? self::$fields[ $field ] : 'text';

		if ( 'robots_advanced' === $field ) {
			$list = self::sanitise( $value, 'list' );

			return array_values( array_intersect( $list, array( 'noimageindex', 'noarchive', 'nosnippet', 'notranslate' ) ) );
		}

		return self::sanitise( $value, $type );
	}

	/**
	 * Read one field from a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   Field name without the prefix.
	 * @return mixed
	 */
	public static function get( $post_id, $field ) {
		$value = get_post_meta( $post_id, self::PREFIX . $field, true );

		return self::cast( $value, $field );
	}

	/**
	 * Read one field from a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $field   Field name without the prefix.
	 * @return mixed
	 */
	public static function get_term( $term_id, $field ) {
		$value = get_term_meta( $term_id, self::PREFIX . $field, true );

		return self::cast( $value, $field );
	}

	/**
	 * Write a set of fields to a post. Empty values delete the row.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values  Field name to value.
	 */
	public static function save( $post_id, array $values ) {
		$changed = array();

		foreach ( $values as $field => $value ) {
			if ( ! isset( self::$fields[ $field ] ) ) {
				continue;
			}

			$value = self::sanitise( $value, self::$fields[ $field ] );
			$was   = self::get( $post_id, $field );

			if ( '' === $value || array() === $value || ( 'bool' === self::$fields[ $field ] && ! $value ) ) {
				delete_post_meta( $post_id, self::PREFIX . $field );
			} else {
				update_post_meta( $post_id, self::PREFIX . $field, $value );
			}

			if ( $was !== $value ) {
				$changed[] = $field;
			}
		}

		if ( $changed ) {
			self::log_change( $post_id, $changed );
		}
	}

	/**
	 * Note a field change in the change log.
	 *
	 * One entry per save rather than one per field, and a run that writes to
	 * many posts merges into one entry, because a log with four hundred lines
	 * in it is a log nobody opens.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $changed Field names that moved.
	 */
	protected static function log_change( $post_id, array $changed ) {
		$title = get_the_title( $post_id );

		Change_Log::record(
			array(
				'what'  => sprintf(
					/* translators: %s: a list of field names. */
					__( 'Changed %s', 'solseo' ),
					implode( ', ', $changed )
				),
				'label' => $title ? $title : sprintf( '#%d', (int) $post_id ),
				'merge' => 'meta:' . implode( ',', $changed ),
			)
		);
	}

	/**
	 * Write a set of fields to a term.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $values  Field name to value.
	 */
	public static function save_term( $term_id, array $values ) {
		foreach ( $values as $field => $value ) {
			if ( ! isset( self::$fields[ $field ] ) ) {
				continue;
			}

			$value = self::sanitise( $value, self::$fields[ $field ] );

			if ( '' === $value || array() === $value || ( 'bool' === self::$fields[ $field ] && ! $value ) ) {
				delete_term_meta( $term_id, self::PREFIX . $field );
				continue;
			}

			update_term_meta( $term_id, self::PREFIX . $field, $value );
		}
	}

	/**
	 * The field list, for screens that render every field.
	 *
	 * @return array
	 */
	public static function fields() {
		return self::$fields;
	}

	/**
	 * Clean a value for storage.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  One of the types in $fields.
	 * @return mixed
	 */
	protected static function sanitise( $value, $type ) {
		switch ( $type ) {
			case 'url':
				return esc_url_raw( trim( (string) $value ) );

			case 'int':
				return (int) $value;

			case 'bool':
				return (bool) $value;

			case 'list':
				$list = is_array( $value ) ? $value : explode( ',', (string) $value );
				$list = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $list ) ) );

				return array_values( array_unique( $list ) );

			case 'array':
				return is_array( $value ) ? $value : array();

			default:
				return sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}

	/**
	 * Give a stored value the shape the rest of the plugin expects.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $field Field name.
	 * @return mixed
	 */
	protected static function cast( $value, $field ) {
		$type = isset( self::$fields[ $field ] ) ? self::$fields[ $field ] : 'text';

		if ( 'list' === $type || 'array' === $type ) {
			return is_array( $value ) ? $value : array();
		}

		if ( 'bool' === $type ) {
			return ! empty( $value );
		}

		if ( 'int' === $type ) {
			return (int) $value;
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Translate an internal type to a REST schema type.
	 *
	 * @param string $type Internal type.
	 * @return string
	 */
	protected static function rest_type( $type ) {
		$map = array(
			'int'   => 'integer',
			'bool'  => 'boolean',
			'list'  => 'array',
			'array' => 'object',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'string';
	}
}
