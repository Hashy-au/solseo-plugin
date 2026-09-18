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
 * @param string $name  Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['solseo_test_options'][ $name ] = $value;

	return true;
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
