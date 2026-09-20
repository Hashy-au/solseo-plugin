<?php
/**
 * Credentials the site owner pastes, and what happens to them at rest.
 *
 * @package SolSEO
 */

use SolSEO\Connect\Keys;

/*
 * SEALING WORKS ON WHATEVER THE HOST HAS.
 *
 * libsodium ships with PHP 7.2 and up and hosts switch it off anyway: the PHP
 * this test suite runs on has no sodium and does have OpenSSL, which is the
 * common shape. Both paths are authenticated, so both fail closed on a changed
 * byte, and a host with neither is told rather than quietly given plaintext.
 */
solseo_assert( Keys::sealing() !== '', 'this PHP can seal a credential at all' );

$solseo_secret = str_repeat( 'k', 32 );
$solseo_plain  = 'AIzaSyD-not-a-real-key-0123456789abcdef';

$solseo_sealed = Keys::seal( $solseo_plain, $solseo_secret );

solseo_assert( is_string( $solseo_sealed ) && '' !== $solseo_sealed, 'a credential seals' );
solseo_assert( false === strpos( $solseo_sealed, $solseo_plain ), 'and the sealed form does not carry the plain one' );
solseo_assert_same( $solseo_plain, Keys::unseal( $solseo_sealed, $solseo_secret ), 'and it comes back out again' );

/* A CHANGED BYTE FAILS CLOSED, RATHER THAN RETURNING RUBBISH. */
$solseo_tampered    = $solseo_sealed;
$solseo_tampered[8] = ( 'a' === $solseo_tampered[8] ) ? 'b' : 'a';

solseo_assert_same( null, Keys::unseal( $solseo_tampered, $solseo_secret ), 'a tampered credential does not come back out' );
solseo_assert_same( null, Keys::unseal( $solseo_sealed, str_repeat( 'j', 32 ) ), 'and neither does one sealed under another key' );
solseo_assert_same( null, Keys::unseal( $solseo_sealed, '' ), 'and a missing secret unseals nothing' );

/* TWO SEALS OF THE SAME THING DO NOT MATCH, SO THE STORE LEAKS NO EQUALITY. */
solseo_assert(
	Keys::seal( $solseo_plain, $solseo_secret ) !== $solseo_sealed,
	'sealing the same credential twice gives two different strings'
);

/* AND NOTHING IS STORED IN THE CLEAR WHEN THERE IS NO WAY TO SEAL IT. */
solseo_assert_same( null, Keys::seal( $solseo_plain, '' ), 'with no secret there is no sealed form, rather than a plain one' );

/*
 * STORING ONE PUTS NOTHING READABLE IN THE OPTIONS TABLE.
 *
 * The case this protects against is the one that actually happens: a database
 * that leaves without the filesystem, through a backup on an open bucket or a
 * query somebody can reach. It protects against nothing at all once wp-config
 * has gone with it, and the screen says so rather than implying otherwise.
 */
$solseo_stored = Keys::set( Keys::GOOGLE, $solseo_plain );

solseo_assert( true === $solseo_stored, 'a credential saves' );

$solseo_raw = wp_json_encode( get_option( Keys::OPTION ) );

solseo_assert( false === strpos( (string) $solseo_raw, $solseo_plain ), 'and the options table does not hold it in the clear' );
solseo_assert_same( $solseo_plain, Keys::get( Keys::GOOGLE ), 'and the code that makes the call can still read it' );
solseo_assert( Keys::has( Keys::GOOGLE ), 'and the screen can tell that one is set' );

/* AND IT IS NEVER AUTOLOADED, BECAUSE EVERY PAGE LOAD WOULD CARRY IT. */
solseo_assert_same(
	false,
	isset( $GLOBALS['solseo_test_autoload'][ Keys::OPTION ] ) ? $GLOBALS['solseo_test_autoload'][ Keys::OPTION ] : null,
	'the credentials option is not autoloaded'
);

/*
 * WHAT THE SCREEN IS GIVEN IS A FINGERPRINT AND A HINT, NEVER THE CREDENTIAL.
 *
 * CLAUDE.md, and the same shape the pairing key already uses: enough for
 * support to tell two keys apart and for the owner to recognise theirs, and
 * not enough to use.
 */
$solseo_report = Keys::report( Keys::GOOGLE );

solseo_assert( true === $solseo_report['set'], 'the report says one is set' );
solseo_assert_same( substr( hash( 'sha256', $solseo_plain ), 0, 8 ), $solseo_report['fingerprint'], 'it carries a fingerprint' );
solseo_assert_same( 'cdef', $solseo_report['hint'], 'and the last four characters, so the owner knows which key it is' );

solseo_assert(
	false === strpos( wp_json_encode( $solseo_report ), $solseo_plain ),
	'and nothing else, because a report is a thing that gets printed'
);

$solseo_blank = Keys::report( 'no-such-key' );

solseo_assert( false === $solseo_blank['set'], 'a credential that was never set reports as not set' );
solseo_assert_same( '', $solseo_blank['fingerprint'], 'with no fingerprint' );
solseo_assert_same( '', $solseo_blank['hint'], 'and no hint' );

/* FORGETTING ONE FORGETS IT. */
Keys::forget( Keys::GOOGLE );

solseo_assert( ! Keys::has( Keys::GOOGLE ), 'removing a credential removes it' );
solseo_assert_same( '', Keys::get( Keys::GOOGLE ), 'and there is nothing left to read' );

/* AN EMPTY VALUE IS A REMOVAL, NOT A CREDENTIAL MADE OF NOTHING. */
Keys::set( Keys::GOOGLE, $solseo_plain );
Keys::set( Keys::GOOGLE, '   ' );

solseo_assert( ! Keys::has( Keys::GOOGLE ), 'saving a blank field clears the credential rather than storing a blank one' );

/*
 * AN EMPTY FIELD IS NOT AN INSTRUCTION TO FORGET THE KEY.
 *
 * The field is never filled in with the credential, because printing one is
 * the thing this whole class exists to avoid, so it arrives empty on every
 * save whether or not one is stored. Reading that as "remove it" wipes a
 * working key the first time somebody saves the Connections screen for any
 * other reason. That is what happened the first time this screen was opened on
 * a real WordPress, and the line under the field had been promising the
 * opposite since it was written.
 */
solseo_assert_same( 'keep', Keys::instruction( '' ), 'a field nobody typed in changes nothing' );
solseo_assert_same( 'clear', Keys::instruction( ' ' ), 'a field holding a space is how one is removed' );
solseo_assert_same( 'clear', Keys::instruction( PHP_EOL ), 'and so is a stray newline, because nobody can see which whitespace they pasted' );
solseo_assert_same( 'set', Keys::instruction( 'AIzaSyD-something' ), 'and anything else is a new credential' );

/*
 * EVERY CREDENTIAL IN THE CATALOGUE SAYS WHERE TO GET ONE.
 *
 * A field labelled "API key" with no link is a support ticket. Every entry
 * carries the page it is created on, and the one that matters more: the page
 * where it is restricted, because a PageSpeed key that is not restricted to
 * PageSpeed is a key somebody else can spend.
 */
foreach ( Keys::names() as $solseo_name => $solseo_entry ) {
	solseo_assert( ! empty( $solseo_entry['label'] ), $solseo_name . ' has a label' );
	solseo_assert( ! empty( $solseo_entry['blurb'] ), $solseo_name . ' says what it is for' );

	solseo_assert(
		0 === strpos( (string) $solseo_entry['where'], 'https://' ),
		$solseo_name . ' says where to create one'
	);

	solseo_assert(
		0 === strpos( (string) $solseo_entry['restrict'], 'https://' ),
		$solseo_name . ' says where to restrict one'
	);
}

/*
 * NOTHING PRINTS A CREDENTIAL.
 *
 * Reading one is what a caller does; printing one is what a screen does. No
 * view, no log line and no admin class may call the reader, so a credential
 * cannot reach a page even by accident. The list of callers is short and it is
 * named here.
 */
$solseo_readers = array();

foreach ( array_merge( glob( SOLSEO_PATH . 'includes/*/*.php' ), glob( SOLSEO_PATH . 'includes/*/*/*.php' ) ) as $solseo_file ) {
	$solseo_body = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents( $solseo_file ) );

	if ( false === strpos( $solseo_body, 'Keys::get(' ) ) {
		continue;
	}

	$solseo_readers[] = str_replace( '\\', '/', str_replace( SOLSEO_PATH, '', $solseo_file ) );
}

sort( $solseo_readers );

/*
 * TWO SINCE FreeC1. class-google.php reads three: the access token to put in
 * an Authorization header, the refresh token to spend, and the site owner's
 * own client secret on the advanced path. All three are read at the moment
 * they are used, in the file that makes the call, which is the rule this
 * guard is about. Nothing else in the Google connection touches the store:
 * class-search-console.php asks Google::access_token() for a string and never
 * opens Keys at all, and the screen is handed a fingerprint and a hint.
 */
solseo_assert_same(
	array( 'includes/connect/class-google.php', 'includes/connect/class-psi.php' ),
	$solseo_readers,
	'the only thing that reads a credential is the thing that makes the call with it'
);

/* AND NO SCREEN GOES NEAR THE OPTION ITSELF. */
$solseo_peekers = array();

foreach ( array_merge( glob( SOLSEO_PATH . 'includes/admin/*.php' ), glob( SOLSEO_PATH . 'includes/admin/views/*.php' ) ) as $solseo_file ) {
	if ( false !== strpos( (string) file_get_contents( $solseo_file ), Keys::OPTION ) ) {
		$solseo_peekers[] = basename( $solseo_file );
	}
}

solseo_assert_same( array(), $solseo_peekers, 'no screen reads the credentials option directly' );
