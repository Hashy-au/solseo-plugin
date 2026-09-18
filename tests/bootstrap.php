<?php
/**
 * Enough of WordPress to run the analysis tests from the command line.
 *
 * @package SolSEO
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SOLSEO_VERSION', '1.0.0' );
define( 'SOLSEO_PATH', dirname( __DIR__ ) . '/' );
define( 'SOLSEO_URL', 'https://example.test/wp-content/plugins/solseo/' );
define( 'SOLSEO_FILE', SOLSEO_PATH . 'solseo.php' );

$GLOBALS['solseo_test_options'] = array();
$GLOBALS['solseo_test_meta']    = array();

/**
 * A no-op database that answers every lookup with nothing.
 */
class SolSEO_Test_Db {

	/**
	 * Post meta table name.
	 *
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';

	/**
	 * Posts table name.
	 *
	 * @var string
	 */
	public $posts = 'wp_posts';

	/**
	 * Stand in for wpdb::prepare().
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query ), $args );
	}

	/**
	 * Stand in for wpdb::get_var().
	 *
	 * @param string $query Query.
	 * @return null
	 */
	public function get_var( $query ) {
		return null;
	}
}

$GLOBALS['wpdb'] = new SolSEO_Test_Db();

/**
 * Translate.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	return $text;
}

/**
 * Translate with a plural form.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Count.
 * @param string $domain Text domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

/**
 * Pass a value through unchanged.
 *
 * @param string $tag   Filter name.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra arguments.
 * @return mixed
 */
function apply_filters( $tag, $value, ...$args ) {
	return $value;
}

/**
 * Register a filter. Nothing runs filters in these tests.
 *
 * @param string   $tag      Filter name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 */
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
}

/**
 * Register an action.
 *
 * @param string   $tag      Action name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 */
function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
}

/**
 * Strip every tag from a string.
 *
 * @param string $text Markup.
 * @return string
 */
function wp_strip_all_tags( $text ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

	return trim( strip_tags( $text ) );
}

/**
 * Wrap double line breaks in paragraphs.
 *
 * @param string $text Text.
 * @return string
 */
function wpautop( $text ) {
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	if ( false !== strpos( $text, '<p' ) ) {
		return $text;
	}

	$blocks = preg_split( '/\n\s*\n/', $text );
	$out    = '';

	foreach ( $blocks as $block ) {
		$block = trim( $block );

		if ( '' === $block ) {
			continue;
		}

		$out .= preg_match( '#^<(h[1-6]|ul|ol|table|blockquote|figure|div)#i', $block ) ? $block : '<p>' . $block . '</p>';
	}

	return $out;
}

/**
 * Trim text to a number of words.
 *
 * @param string $text  Text.
 * @param int    $words Word limit.
 * @param string $more  Appended when trimmed.
 * @return string
 */
function wp_trim_words( $text, $words = 55, $more = '...' ) {
	$list = preg_split( '/\s+/', trim( $text ) );

	if ( count( $list ) <= $words ) {
		return trim( $text );
	}

	return implode( ' ', array_slice( $list, 0, $words ) ) . $more;
}

/**
 * Parse one component out of a URL.
 *
 * @param string $url       URL.
 * @param int    $component Component constant.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/**
 * The site address.
 *
 * @param string $path Path to append.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

/**
 * Whether WooCommerce is loaded.
 *
 * @return bool
 */
function solseo_has_woocommerce() {
	return false;
}

/**
 * Pull the values out of a list of arrays or objects.
 *
 * @param array  $list  List.
 * @param string $field Field name.
 * @return array
 */
function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $item ) use ( $field ) {
			return is_object( $item ) ? $item->$field : $item[ $field ];
		},
		$list
	);
}

/**
 * Attachment URL. No attachments exist in these tests.
 *
 * @param int    $id   Attachment ID.
 * @param string $size Size name.
 * @return string
 */
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return '';
}

/**
 * Post meta. Backed by the test array.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param bool   $single  Single value.
 * @return mixed
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	return isset( $GLOBALS['solseo_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['solseo_test_meta'][ $post_id ][ $key ] : '';
}

/**
 * Whether a string holds block markup.
 *
 * @param string $content Content.
 * @return bool
 */
function has_blocks( $content ) {
	return false !== strpos( (string) $content, '<!-- wp:' );
}

/**
 * Render blocks. The tests pass plain HTML.
 *
 * @param string $content Content.
 * @return string
 */
function do_blocks( $content ) {
	return $content;
}

/**
 * Remove shortcodes.
 *
 * @param string $content Content.
 * @return string
 */
function strip_shortcodes( $content ) {
	return preg_replace( '/\[[^\]]*\]/', '', (string) $content );
}

/**
 * Fetch a post. The tests build papers directly, so nothing is stored.
 *
 * @param mixed $post Post or ID.
 * @return null
 */
function get_post( $post = null ) {
	return null;
}

require_once SOLSEO_PATH . 'includes/autoload.php';

/**
 * Site option, backed by the test array.
 *
 * @param string $name    Option name.
 * @param mixed  $default Returned when unset.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return isset( $GLOBALS['solseo_test_options'][ $name ] ) ? $GLOBALS['solseo_test_options'][ $name ] : $default;
}

/**
 * Write a site option.
 *
 * @param string $name     Option name.
 * @param mixed  $value    Value.
 * @param mixed  $autoload Unused here.
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );

	$GLOBALS['solseo_test_options'][ $name ] = $value;

	return true;
}

/**
 * Write a site option, but only when there is not one already.
 *
 * The refusal is the point: the snapshot of robots.txt leans on it.
 *
 * @param string $name     Option name.
 * @param mixed  $value    Value.
 * @param string $deprecated Unused.
 * @param bool   $autoload Unused here.
 * @return bool Whether anything was written.
 */
function add_option( $name, $value, $deprecated = '', $autoload = true ) {
	unset( $deprecated, $autoload );

	if ( isset( $GLOBALS['solseo_test_options'][ $name ] ) ) {
		return false;
	}

	$GLOBALS['solseo_test_options'][ $name ] = $value;

	return true;
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Forget a site option.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['solseo_test_options'][ $name ] );

		return true;
	}
}

/**
 * Site information.
 *
 * @param string $field Field name.
 * @return string
 */
function get_bloginfo( $field = 'name' ) {
	$values = array(
		'name'        => 'Asiatic Bows',
		'description' => 'Traditional archery, shipped from Perth',
	);

	return isset( $values[ $field ] ) ? $values[ $field ] : '';
}

/**
 * Format a date in the site time zone.
 *
 * @param string $format Date format.
 * @return string
 */
function wp_date( $format ) {
	return gmdate( $format );
}

/**
 * The current search term.
 *
 * @return string
 */
function get_search_query() {
	return '';
}

/**
 * Whether the request is a paged archive.
 *
 * @return bool
 */
function is_paged() {
	return false;
}

/**
 * Query variables. None are set in these tests.
 *
 * @param string $name Variable name.
 * @return string
 */
function get_query_var( $name ) {
	return '';
}

/**
 * Terms attached to a post. None are in these tests.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 * @return array
 */
function get_the_terms( $post_id, $taxonomy ) {
	return array();
}

/**
 * Escape for HTML output.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Clean a URL for storage.
 *
 * @param string $url      Address.
 * @param array  $protocols Allowed protocols.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );

	return preg_match( '#^(https?://|/)#', $url ) ? $url : '';
}

/**
 * Clean a one line string.
 *
 * @param string $text Text.
 * @return string
 */
function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}

/**
 * Remove slashes added by the request.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * The site address.
 *
 * @param string $path Appended to it.
 * @return string
 */
function site_url( $path = '' ) {
	return 'https://example.test' . $path;
}

/**
 * Drop a trailing slash.
 *
 * @param string $value Any string.
 * @return string
 */
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/' );
}

/**
 * Text that is already valid here.
 *
 * @param string $text Any text.
 * @return string
 */
function wp_check_invalid_utf8( $text ) {
	return (string) $text;
}

/**
 * Nothing in the library, under test.
 *
 * @param string $url Image address.
 * @return int
 */
function attachment_url_to_postid( $url ) {
	return isset( $GLOBALS['solseo_test_attachments'][ $url ] ) ? (int) $GLOBALS['solseo_test_attachments'][ $url ] : 0;
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * The time, as the database keeps it.
	 *
	 * @param string $format Ignored.
	 * @param bool   $gmt    Ignored.
	 * @return string
	 */
	function current_time( $format = 'mysql', $gmt = 0 ) {
		unset( $format, $gmt );

		return gmdate( 'Y-m-d H:i:s' );
	}
}

/**
 * Reduce a string to a key.
 *
 * @param string $value Any string.
 * @return string
 */
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

/**
 * A whole number, never negative.
 *
 * @param mixed $value Any value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * JSON, without the options WordPress adds.
 *
 * @param mixed $value Any value.
 * @return string
 */
function wp_json_encode( $value ) {
	return (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

/**
 * A number with separators.
 *
 * @param float $number   The number.
 * @param int   $decimals How many decimal places.
 * @return string
 */
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

/**
 * Escape for an attribute.
 *
 * @param string $text Any text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escape for the inside of a textarea.
 *
 * @param string $text Any text.
 * @return string
 */
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
