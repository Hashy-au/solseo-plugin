<?php
/**
 * Test runner. Run with: php tests/run.php
 *
 * @package SolSEO
 */

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['solseo_tests'] = array(
	'passed' => 0,
	'failed' => array(),
);

/**
 * Assert that something is true.
 *
 * @param bool   $condition What is being asserted.
 * @param string $message   What it means when it fails.
 */
function solseo_assert( $condition, $message ) {
	if ( $condition ) {
		++$GLOBALS['solseo_tests']['passed'];
		return;
	}

	$GLOBALS['solseo_tests']['failed'][] = $message;
}

/**
 * Assert that two values match.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  What it means when it fails.
 */
function solseo_assert_same( $expected, $actual, $message ) {
	solseo_assert(
		$expected === $actual,
		$message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')'
	);
}

/**
 * A file's PHP with its prose taken out, so a word in a sentence is not a call.
 *
 * Two guards read source looking for calls somebody must not make, and both of
 * them sit beside screens whose whole job is to promise they do not make them.
 * "No image is resized, converted, renamed or deleted" read as a call to
 * rename() fails the build on the sentence saying the thing being checked for.
 *
 * It lives here rather than in one of the test files because the second guard
 * that wanted it loads first, and a helper that only exists once the file
 * defining it has been reached is a fatal error waiting for an alphabet.
 *
 * IT USES PHP'S OWN TOKENISER, and the two regular expressions it used to use
 * are why. The first took the // in 'https://example.test/' for the start of a
 * comment and deleted the rest of that line, including the quote closing the
 * string. The second then read the next quote anywhere in the file as the end
 * of that string and deleted everything in between. On a file with a
 * documentation address in it, whole methods disappeared before the guard read
 * a byte, and the guard passed. A guard that silently stops seeing the thing it
 * is looking for is worse than no guard, which is what this one nearly was.
 *
 * @param string $source A PHP file.
 * @return string
 */
function solseo_code_only( $source ) {
	$prose = array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML );
	$code  = '';

	foreach ( token_get_all( (string) $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			$code .= $token;

			continue;
		}

		$code .= in_array( $token[0], $prose, true ) ? ' ' : $token[1];
	}

	// A method called unlink() is a declaration, not a call to PHP's unlink().
	return (string) preg_replace( '/\bfunction\s+&?\w+/', 'function x', $code );
}

/**
 * A file's PHP with its comments taken out and everything else kept.
 *
 * THE OPPOSITE OF solseo_code_only(), AND BOTH ARE NEEDED. That one drops
 * string literals and inline HTML along with the comments, because the guards
 * that use it look for calls somebody must not make. The guards that use this
 * one look for markup somebody must not write, and markup is a string literal
 * or inline HTML and nothing else, so running those through the other helper is
 * reading a file with the answer already removed. It passed on a sitemap
 * transform carrying a style block, which is how that was found.
 *
 * It lives here rather than in a test file for the reason above: a helper that
 * only exists once the file defining it has been reached is a fatal error
 * waiting for an alphabet.
 *
 * @param string $source A PHP file.
 * @return string
 */
function solseo_without_comments( $source ) {
	$code = '';

	foreach ( token_get_all( (string) $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			$code .= $token;

			continue;
		}

		$code .= in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? ' ' : $token[1];
	}

	return $code;
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	require $file;
}

$results = $GLOBALS['solseo_tests'];

echo $results['passed'], " passed\n";

if ( $results['failed'] ) {
	echo count( $results['failed'] ), " failed\n";

	foreach ( $results['failed'] as $failure ) {
		echo '  ', $failure, "\n";
	}

	exit( 1 );
}

exit( 0 );
