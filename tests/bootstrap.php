<?php
/**
 * Enough of WordPress to run the analysis tests from the command line.
 *
 * @package SolSEO
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SOLSEO_VERSION', '2.0.0' );
define( 'SOLSEO_PATH', dirname( __DIR__ ) . '/' );
define( 'SOLSEO_URL', 'https://example.test/wp-content/plugins/solseo/' );
define( 'SOLSEO_FILE', SOLSEO_PATH . 'solseo.php' );

/*
 * The salts wp-config.php carries on every real install. The credential store
 * derives its secret from these, so without them the tests would exercise the
 * "this host cannot seal anything" path instead of the one every site takes.
 */
define( 'AUTH_KEY', 'iQ8]x2!vTz#6Lp@w9Rn*Ke4$Mb7^Hs1' );
define( 'SECURE_AUTH_SALT', 'Zc3&Yv5(Ub8)Nm0_Qw2+Ea6-Rt9=Yu4' );
define( 'LOGGED_IN_KEY', 'Pl7%Ok3~Ij9|Uh5Yg1Tf6Rd2Se8Wa4' );

define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'OBJECT', 'OBJECT' );

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );

$GLOBALS['solseo_test_options'] = array();
$GLOBALS['solseo_test_meta']    = array();

/**
 * A no-op database that answers every lookup with nothing.
 */
class SolSEO_Test_Db {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

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

	/**
	 * Stand in for wpdb::get_row().
	 *
	 * @param string $query  Query.
	 * @param string $output Output type.
	 * @return null
	 */
	public function get_row( $query, $output = null ) {
		return null;
	}

	/**
	 * Stand in for wpdb::get_results().
	 *
	 * @param string $query  Query.
	 * @param string $output Output type.
	 * @return array
	 */
	public function get_results( $query, $output = null ) {
		return array();
	}

	/**
	 * Stand in for wpdb::get_col().
	 *
	 * @param string $query Query.
	 * @return array
	 */
	public function get_col( $query ) {
		return array();
	}
}

/**
 * The post an address points at. Nothing resolves, under test.
 *
 * @param string $url An address.
 * @return int
 */
function url_to_postid( $url ) {
	return isset( $GLOBALS['solseo_test_urls'][ $url ] ) ? (int) $GLOBALS['solseo_test_urls'][ $url ] : 0;
}

$GLOBALS['solseo_test_urls'] = array();

$GLOBALS['wpdb'] = new SolSEO_Test_Db();

/**
 * Translate.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	if ( ! isset( $GLOBALS['solseo_test_translations'] ) ) {
		$GLOBALS['solseo_test_translations'] = 0;
	}

	++$GLOBALS['solseo_test_translations'];

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

$GLOBALS['solseo_filters'] = array();

/**
 * Run whatever is listening, in priority order.
 *
 * These were a pass through and a no-op until 1.4.0, which meant an extension
 * point could not be tested, only read. The tab registry is an extension point
 * and its whole job is to let somebody else add something, so the filters are
 * real here now.
 *
 * @param string $tag     Filter name.
 * @param mixed  $value   Value.
 * @param mixed  ...$args Extra arguments.
 * @return mixed
 */
function apply_filters( $tag, $value, ...$args ) {
	if ( empty( $GLOBALS['solseo_filters'][ $tag ] ) ) {
		return $value;
	}

	$listening = $GLOBALS['solseo_filters'][ $tag ];
	ksort( $listening );

	foreach ( $listening as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$passed = array_slice( array_merge( array( $value ), $args ), 0, max( 1, (int) $entry[1] ) );
			$value  = call_user_func_array( $entry[0], $passed );
		}
	}

	return $value;
}

/**
 * Register a filter.
 *
 * @param string   $tag      Filter name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return bool
 */
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['solseo_filters'][ $tag ][ (int) $priority ][] = array( $callback, $args );

	return true;
}

/**
 * An admin request only when a test says so.
 *
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['solseo_test_is_admin'] );
}

/**
 * A cron run only when a test says so.
 *
 * @return bool
 */
function wp_doing_cron() {
	return ! empty( $GLOBALS['solseo_test_doing_cron'] );
}

/**
 * An admin-ajax request only when a test says so.
 *
 * @return bool
 */
function wp_doing_ajax() {
	return ! empty( $GLOBALS['solseo_test_doing_ajax'] );
}

/**
 * A REST request only when a test says so.
 *
 * @return bool
 */
function wp_is_serving_rest_request() {
	return ! empty( $GLOBALS['solseo_test_is_rest'] );
}

/**
 * Something that went wrong, the way WordPress carries it.
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- this
 * file is the stand-in for WordPress, and the pieces of WordPress it stands in
 * for are a class here and a function there. Splitting them across files would
 * put the harness in more places than the thing it is testing.
 */
class WP_Error {

	/**
	 * Code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Build.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * The code.
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * The message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

/**
 * Whether something went wrong.
 *
 * @param mixed $thing Anything.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

$GLOBALS['solseo_test_http'] = array(
	'queue' => array(),
	'sent'  => array(),
);

/**
 * Line up the next HTTP answer.
 *
 * @param int    $code    Status code.
 * @param mixed  $body    Body, encoded if it is an array.
 * @param string $note    An error string instead of a response.
 * @param array  $headers Response headers, lower cased.
 */
function solseo_test_http_next( $code, $body = '', $note = '', array $headers = array() ) {
	$GLOBALS['solseo_test_http']['queue'][] = array(
		'code'    => $code,
		'body'    => is_array( $body ) ? wp_json_encode( $body ) : (string) $body,
		'note'    => $note,
		'headers' => array_change_key_case( $headers ),
	);
}

/**
 * Every request made since the last reset.
 *
 * @return array
 */
function solseo_test_http_sent() {
	return $GLOBALS['solseo_test_http']['sent'];
}

/**
 * Forget what was sent and what was queued.
 */
function solseo_test_http_reset() {
	$GLOBALS['solseo_test_http'] = array(
		'queue' => array(),
		'sent'  => array(),
	);
}

/**
 * An HTTP request, answered from the queue.
 *
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return array|WP_Error
 */
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['solseo_test_http']['sent'][] = array(
		'url'  => $url,
		'args' => $args,
	);

	$next = array_shift( $GLOBALS['solseo_test_http']['queue'] );

	if ( ! $next ) {
		return new WP_Error( 'http_request_failed', 'nothing queued for ' . $url );
	}

	if ( '' !== $next['note'] ) {
		return new WP_Error( 'http_request_failed', $next['note'] );
	}

	return array(
		'response' => array( 'code' => (int) $next['code'] ),
		'body'     => $next['body'],
		'headers'  => isset( $next['headers'] ) ? $next['headers'] : array(),
	);
}

/**
 * One header off a response.
 *
 * @param mixed  $response Response.
 * @param string $name     Header name.
 * @return string
 */
function wp_remote_retrieve_header( $response, $name ) {
	$name = strtolower( (string) $name );

	return is_array( $response ) && isset( $response['headers'][ $name ] )
		? (string) $response['headers'][ $name ]
		: '';
}

/**
 * A GET.
 *
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return array|WP_Error
 */
function wp_remote_get( $url, $args = array() ) {
	$args['method'] = 'GET';

	return wp_remote_request( $url, $args );
}

/**
 * A POST.
 *
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return array|WP_Error
 */
function wp_remote_post( $url, $args = array() ) {
	$args['method'] = 'POST';

	return wp_remote_request( $url, $args );
}

/**
 * The status code off a response.
 *
 * @param mixed $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * The body off a response.
 *
 * @param mixed $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * Store something for a while.
 *
 * @param string $name    Name.
 * @param mixed  $value   Value.
 * @param int    $seconds How long.
 * @return bool
 */
function set_transient( $name, $value, $seconds = 0 ) {
	unset( $seconds );

	$GLOBALS['solseo_test_transients'][ $name ] = $value;

	return true;
}

/**
 * Read something stored for a while.
 *
 * @param string $name Name.
 * @return mixed
 */
function get_transient( $name ) {
	return isset( $GLOBALS['solseo_test_transients'][ $name ] ) ? $GLOBALS['solseo_test_transients'][ $name ] : false;
}

/**
 * Forget it.
 *
 * @param string $name Name.
 * @return bool
 */
function delete_transient( $name ) {
	unset( $GLOBALS['solseo_test_transients'][ $name ] );

	return true;
}

/**
 * Escaped translation.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );

	return esc_html( $text );
}

/**
 * One trailing slash.
 *
 * @param string $value Value.
 * @return string
 */
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

$GLOBALS['solseo_users'] = array();

/**
 * A field on a user, from whatever the test put there.
 *
 * @param string $field   Field name.
 * @param int    $user_id User ID.
 * @return string
 */
function get_the_author_meta( $field, $user_id = 0 ) {
	$user = isset( $GLOBALS['solseo_users'][ $user_id ] ) ? $GLOBALS['solseo_users'][ $user_id ] : array();

	return isset( $user[ $field ] ) ? $user[ $field ] : '';
}

/**
 * A user's own meta, from the same place.
 *
 * @param int    $user_id User ID.
 * @param string $key     Meta key.
 * @param bool   $single  Ignored.
 * @return string
 */
function get_user_meta( $user_id, $key = '', $single = false ) {
	return get_the_author_meta( $key, $user_id );
}

/**
 * Where a user's posts are listed.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_author_posts_url( $user_id ) {
	return 'https://example.test/author/' . (int) $user_id . '/';
}

/*
 * Enough of the admin to render a screen into a string. A tab that can only be
 * looked at in a browser is a tab nothing checks between releases.
 */

/**
 * Print an escaped, translated string.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html( $text );
}

/**
 * Escape a URL.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return esc_url_raw( $url );
}

/**
 * Print a checked attribute when the two match.
 *
 * @param mixed $checked Value.
 * @param mixed $current What it is compared with.
 * @param bool  $display Whether to print it.
 * @return string
 */
function checked( $checked, $current = true, $display = true ) {
	$html = (string) $checked === (string) $current ? " checked='checked'" : '';

	if ( $display ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed attribute.
	}

	return $html;
}

/**
 * An admin address.
 *
 * The base is a global so a test can put the admin somewhere other than the
 * usual place. A WordPress in a subdirectory, or one whose admin folder has
 * been moved, is exactly what the robots.txt rules have to survive, and a
 * harness that can only be one site cannot ask that question.
 *
 * @param string $path Path under wp-admin.
 * @return string
 */
function admin_url( $path = '' ) {
	$base = isset( $GLOBALS['solseo_test_admin_url'] )
		? (string) $GLOBALS['solseo_test_admin_url']
		: 'https://example.test/wp-admin/';

	return $base . ltrim( (string) $path, '/' );
}

/**
 * Add query arguments to a URL.
 *
 * @param mixed  $key   Key, or an array of them.
 * @param mixed  $value Value, or the URL when the first argument is an array.
 * @param string $url   URL.
 * @return string
 */
function add_query_arg( $key, $value = null, $url = '' ) {
	$args = is_array( $key ) ? $key : array( $key => $value );
	$url  = is_array( $key ) ? (string) $value : (string) $url;

	$join = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $join . http_build_query( $args );
}

/**
 * Print a nonce field.
 *
 * @param string $action Action.
 * @param string $name   Field name.
 */
function wp_nonce_field( $action = '', $name = '_wpnonce' ) {
	printf( '<input type="hidden" name="%s" value="test-nonce">', esc_attr( $name ) );
}

/**
 * Print a submit button.
 *
 * @param string $text Label.
 */
function submit_button( $text = null ) {
	printf( '<p class="submit"><input type="submit" name="submit" class="button button-primary" value="%s"></p>', esc_attr( null === $text ? 'Save Changes' : $text ) );
}

/**
 * Forget everything listening to one filter, so one test cannot reach another.
 *
 * @param string $tag Filter name.
 * @return bool
 */
function remove_all_filters( $tag ) {
	unset( $GLOBALS['solseo_filters'][ $tag ] );

	return true;
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

$GLOBALS['solseo_test_actions'] = array();
$GLOBALS['solseo_test_removed'] = array();

/**
 * Fire an action. Nothing listens, because add_action above keeps nothing,
 * but every firing is written down so a test can prove a hook fired, how
 * many times, and with what.
 *
 * @param string $tag     Action name.
 * @param mixed  ...$args Arguments.
 */
function do_action( $tag, ...$args ) {
	$GLOBALS['solseo_test_actions'][] = array(
		'tag'  => $tag,
		'args' => $args,
	);
}

/**
 * Every firing of one action so far.
 *
 * @param string $tag Action name.
 * @return array
 */
function solseo_test_actions_fired( $tag ) {
	return array_values(
		array_filter(
			$GLOBALS['solseo_test_actions'],
			function ( $fired ) use ( $tag ) {
				return $fired['tag'] === $tag;
			}
		)
	);
}

/**
 * Unhook a callback. Written down rather than done, for the same reason.
 *
 * @param string   $tag      Action name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function remove_action( $tag, $callback, $priority = 10 ) {
	$GLOBALS['solseo_test_removed'][] = array(
		'tag'      => $tag,
		'callback' => $callback,
		'priority' => (int) $priority,
	);

	return true;
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
 * A REST route's address, in the pretty permalink spelling.
 *
 * The other spelling, ?rest_route=, is what a site without pretty permalinks
 * serves, and the hub accepts both. This stub picks one; the two spellings are
 * checked against the hub's own rule in hub/bin/selftest.php, which is where
 * that rule lives.
 *
 * @param string $path A route, without the leading slash.
 * @return string
 */
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
}

/**
 * Whether WooCommerce is loaded.
 *
 * @return bool
 */
function solseo_has_woocommerce() {
	/*
	 * FALSE UNLESS A TEST SAYS OTHERWISE. Most of the estate is tested on a
	 * site with no shop, which is why this was a flat `false` for a long time.
	 * The Shop pack has to prove what happens to the free plugin's feed audit
	 * on a site that does have one, and a `WooCommerce` class declared in a
	 * test file cannot be undeclared afterwards.
	 */
	return ! empty( $GLOBALS['solseo_test_woocommerce'] );
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

$GLOBALS['solseo_test_posts'] = array();

/**
 * Fetch a post. Most tests build papers directly and store nothing.
 *
 * @param mixed $post Post or ID.
 * @return object|null
 */
function get_post( $post = null ) {
	if ( is_object( $post ) ) {
		return $post;
	}

	$id = (int) $post;

	return isset( $GLOBALS['solseo_test_posts'][ $id ] ) ? $GLOBALS['solseo_test_posts'][ $id ] : null;
}

/**
 * Put a post where get_post() can find it.
 *
 * @param array $fields Anything a WP_Post carries. ID is required.
 * @return object
 */
function solseo_test_post( array $fields ) {
	$post = (object) array_merge(
		array(
			'ID'            => 0,
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => 'A page',
			'post_name'     => 'a-page',
			'post_content'  => '',
			'post_excerpt'  => '',
			'post_modified' => '2026-09-19 00:00:00',
		),
		$fields
	);

	$GLOBALS['solseo_test_posts'][ (int) $post->ID ] = $post;

	return $post;
}

/**
 * Write a post meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['solseo_test_meta'][ $post_id ][ $key ] = $value;

	return true;
}

/**
 * Forget a post meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return bool
 */
function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['solseo_test_meta'][ $post_id ][ $key ] );

	return true;
}

/**
 * Resolve shortcodes. The fixtures hold rendered markup, so this is a no-op.
 *
 * @param string $content Content.
 * @return string
 */
function do_shortcode( $content ) {
	return (string) $content;
}

/**
 * Set the global post. Nothing in these tests reads it.
 *
 * @param mixed $post Post.
 * @return bool
 */
function setup_postdata( $post ) {
	unset( $post );

	return true;
}

/**
 * Put the global post back.
 */
function wp_reset_postdata() {
	return true;
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
	$GLOBALS['solseo_test_options'][ $name ]  = $value;
	$GLOBALS['solseo_test_autoload'][ $name ] = $autoload;

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
	unset( $deprecated );

	if ( isset( $GLOBALS['solseo_test_options'][ $name ] ) ) {
		return false;
	}

	$GLOBALS['solseo_test_options'][ $name ]  = $value;
	$GLOBALS['solseo_test_autoload'][ $name ] = $autoload;

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
 * @param string   $format    Date format.
 * @param int|null $timestamp When, or null for now.
 * @return string
 */
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
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
 * The flags are carried through rather than dropped. A guard on the structured
 * data asks whether a closing script tag in a title survives encoding, and a
 * stub that ignored the flags would answer that question about the stub.
 *
 * @param mixed $value Any value.
 * @param int   $flags Encoding flags.
 * @param int   $depth How deep to go.
 * @return string
 */
function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return (string) json_encode( $value, (int) $flags, (int) $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

/**
 * The script tag WordPress builds for inline code.
 *
 * Tracks wp_get_inline_script_tag(), including the line that strips the comment
 * and CDATA wrappers older themes used, because that strip is the one thing in
 * there that could touch a JSON-LD payload.
 *
 * @param string $data       What goes inside the tag.
 * @param array  $attributes Tag attributes.
 * @return string
 */
function wp_get_inline_script_tag( $data, $attributes = array() ) {
	$printed = '';

	foreach ( (array) $attributes as $name => $value ) {
		if ( false === $value ) {
			continue;
		}

		$printed .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( $value ) . '"';
	}

	$data = trim( (string) preg_replace( '#(?:<!--|//\s*<!\[CDATA\[)\s*|\s*(?://\s*\]\]>|-->)#', '', (string) $data ) );

	return sprintf( "<script%s>\n%s\n</script>\n", $printed, $data );
}

/**
 * Print what wp_get_inline_script_tag() builds.
 *
 * @param string $data       What goes inside the tag.
 * @param array  $attributes Tag attributes.
 */
function wp_print_inline_script_tag( $data, $attributes = array() ) {
	echo wp_get_inline_script_tag( $data, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above.
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

/**
 * The screen object WordPress hands a meta box filter.
 */
class SolSEO_Test_Screen {

	/**
	 * Screen id.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Build one.
	 *
	 * @param string $id Screen id.
	 */
	public function __construct( $id ) {
		$this->id = $id;
	}
}
