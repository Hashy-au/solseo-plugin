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
