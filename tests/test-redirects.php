<?php
/**
 * Redirect rules: what is stored is what was typed.
 *
 * A REGULAR EXPRESSION IS NOT A PATH, AND Manager::save() USED TO TREAT IT AS
 * ONE. `normalise()` exists to put a path into the one shape rules are stored
 * in, which means gluing a leading slash on. Run over `^/old-shop/(.*)$` it
 * produces `/^/old-shop/(.*)$`, and a `^` that is not at the start of a
 * pattern, with no `m` modifier, can never match anything. The rule then sits
 * on the Rules tab looking exactly like the one that was typed, with a hit
 * count that stays on zero for ever.
 *
 * These are the two halves. A regex comes back out of the database byte for
 * byte, and an exact rule is still normalised, because the second half is what
 * makes the first a fix rather than the removal of a feature.
 *
 * THE DOUBLE RECORDS RATHER THAN STORES. bootstrap.php's $wpdb answers every
 * read with nothing, which is right for the rest of the suite and useless
 * here: this file needs to see what save() handed the database. It swaps the
 * global for a recorder, and it puts the old one back at the end of the file,
 * per D-157.2.
 *
 * @package SolSEO
 */

use SolSEO\Redirects\Manager;

/**
 * A $wpdb that keeps what it was given.
 */
class SolSEO_Test_Redirect_Db extends SolSEO_Test_Db {

	/**
	 * Every insert, in order. Each entry: table, data.
	 *
	 * @var array
	 */
	public $inserts = array();

	/**
	 * Every update, in order. Each entry: table, data, where.
	 *
	 * @var array
	 */
	public $updates = array();

	/**
	 * What the next insert will call itself.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Rows a read answers with.
	 *
	 * @var array
	 */
	public $rows = array();

	/**
	 * Record an insert.
	 *
	 * @param string $table Table name.
	 * @param array  $data  Column values.
	 * @return int
	 */
	public function insert( $table, $data ) {
		$this->inserts[] = array(
			'table' => $table,
			'data'  => $data,
		);

		++$this->insert_id;

		return 1;
	}

	/**
	 * Record an update.
	 *
	 * @param string $table Table name.
	 * @param array  $data  Column values.
	 * @param array  $where Which row.
	 * @return int
	 */
	public function update( $table, $data, $where ) {
		$this->updates[] = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);

		return 1;
	}

	/**
	 * Answer a read with whatever was planted.
	 *
	 * @param string $query  Query.
	 * @param string $output Output type.
	 * @return array
	 */
	public function get_results( $query, $output = null ) {
		return $this->rows;
	}

	/**
	 * Swallow a write nobody is asserting on.
	 *
	 * @param string $query Query.
	 * @return int
	 */
	public function query( $query ) {
		return 0;
	}
}

$solseo_redirect_db_was  = $GLOBALS['wpdb'];
$solseo_redirect_db      = new SolSEO_Test_Redirect_Db();
$GLOBALS['wpdb']         = $solseo_redirect_db;
$GLOBALS['solseo_r_tbl'] = 'wp_solseo_redirects';

/*
 * A REGEX COMES BACK OUT AS IT WENT IN.
 *
 * Nothing is trimmed off it, nothing is glued on to it, and it is not put
 * through esc_url_raw() on the way past either.
 */
foreach ( array(
	'an anchored one'            => '^/old-shop/(.*)$',
	'one with no leading slash'  => 'product/([0-9]+)\.html',
	'one that starts at a slash' => '/range/(.*)/old$',
	'one with a query in it'     => '^/index\.php\?p=([0-9]+)$',
) as $solseo_what => $solseo_typed ) {
	$solseo_redirect_db->inserts = array();

	Manager::save(
		array(
			'source'      => $solseo_typed,
			'target'      => '/shop/$1',
			'status_code' => 301,
			'match_type'  => 'regex',
			'enabled'     => 1,
		)
	);

	solseo_assert_same(
		$solseo_typed,
		isset( $solseo_redirect_db->inserts[0]['data']['source'] )
			? $solseo_redirect_db->inserts[0]['data']['source']
			: null,
		'a regex rule is stored exactly as it was typed: ' . $solseo_what
	);

	solseo_assert_same(
		'regex',
		isset( $solseo_redirect_db->inserts[0]['data']['match_type'] )
			? $solseo_redirect_db->inserts[0]['data']['match_type']
			: null,
		'and it is stored as a regex: ' . $solseo_what
	);
}

/*
 * AND AN EXACT RULE IS STILL NORMALISED, which is the half that makes the
 * above a fix. A path typed without its slash, with spare space around it, or
 * pasted as a whole address is the ordinary case and it still lands in one
 * shape.
 */
foreach ( array(
	'no leading slash' => array( 'old-page', '/old-page' ),
	'spare space'      => array( '  /old-page  ', '/old-page' ),
	'a whole address'  => array( 'https://example.test/old-page', '/old-page' ),
	'already in shape' => array( '/old-page', '/old-page' ),
	'a doubled slash'  => array( '//old-page', '/old-page' ),
) as $solseo_what => $solseo_pair ) {
	$solseo_redirect_db->inserts = array();

	Manager::save(
		array(
			'source'      => $solseo_pair[0],
			'target'      => '/new-page',
			'status_code' => 301,
			'match_type'  => 'exact',
			'enabled'     => 1,
		)
	);

	solseo_assert_same(
		$solseo_pair[1],
		isset( $solseo_redirect_db->inserts[0]['data']['source'] )
			? $solseo_redirect_db->inserts[0]['data']['source']
			: null,
		'an exact rule is still put into one shape: ' . $solseo_what
	);
}

/*
 * A RULE WITH NOTHING IN IT IS STILL REFUSED, on both match types, because the
 * new branch is a second way into the same guard clause.
 */
foreach ( array( 'exact', 'regex' ) as $solseo_type ) {
	$solseo_redirect_db->inserts = array();

	solseo_assert_same(
		false,
		Manager::save(
			array(
				'source'      => '   ',
				'target'      => '/new-page',
				'status_code' => 301,
				'match_type'  => $solseo_type,
				'enabled'     => 1,
			)
		),
		'a rule with an empty source is refused, ' . $solseo_type
	);

	solseo_assert_same( 0, count( $solseo_redirect_db->inserts ), 'and nothing is written for it, ' . $solseo_type );
}

/*
 * AN EDIT TAKES THE SAME PATH AS AN ADD. save() with an id updates instead of
 * inserting, and the bug was in the line above the branch, so both arms of it
 * have to be asserted or half the fix is untested.
 */
$solseo_redirect_db->updates = array();

Manager::save(
	array(
		'source'      => '^/old-shop/(.*)$',
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
		'enabled'     => 1,
	),
	7
);

solseo_assert_same(
	'^/old-shop/(.*)$',
	isset( $solseo_redirect_db->updates[0]['data']['source'] )
		? $solseo_redirect_db->updates[0]['data']['source']
		: null,
	'editing a regex rule stores it as it was typed too'
);

/*
 * AND THE PATTERN THAT WAS BROKEN NOW MATCHES. The two halves above are about
 * what is in the row; this is about what the row then does, which is the thing
 * the customer was missing. match() reads the rules out of the database and
 * builds `#...#i` around each source.
 */
$solseo_redirect_db->rows = array(
	array(
		'id'          => 1,
		'source'      => '^/old-shop/(.*)$',
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
	),
);

$solseo_hit = Manager::match( '/old-shop/quivers' );

solseo_assert(
	is_array( $solseo_hit ),
	'a regex rule stored as it was typed matches the address it was written for'
);

solseo_assert_same(
	'/shop/quivers',
	is_array( $solseo_hit ) ? $solseo_hit['target'] : null,
	'and the capture goes through into the target'
);

/* AND THE SAME RULE WITH THE STRAY SLASH ON IT MATCHES NOTHING, ever. */
$solseo_redirect_db->rows = array(
	array(
		'id'          => 1,
		'source'      => '/^/old-shop/(.*)$',
		'target'      => '/shop/$1',
		'status_code' => 301,
		'match_type'  => 'regex',
	),
);

solseo_assert_same(
	null,
	Manager::match( '/old-shop/quivers' ),
	'and the same rule with the stray slash glued on matches nothing, which is the bug'
);

/*
 * THE REPAIR RUNS ONCE AND TOUCHES ONLY WHAT IS PROVABLY DEAD.
 *
 * `/^` at the front of a pattern can never match: the engine has to consume a
 * slash before an assertion that nothing has been consumed. So a regex rule
 * whose source starts `/^` is a rule that does nothing today, and taking the
 * slash off it cannot change an answer anybody is getting. Every other shape
 * is left alone, because `/foo` in the column is the same bytes whether it was
 * typed with the slash or without it, and there is no way to tell them apart.
 */
$solseo_has_repair = method_exists( Manager::class, 'repair_regex_sources' );

solseo_assert( $solseo_has_repair, 'there is a repair for rules already stored with the stray slash' );

solseo_assert_same(
	array( '/^/old-shop/(.*)$' => '^/old-shop/(.*)$' ),
	$solseo_has_repair ? Manager::repair_regex_sources( array( '/^/old-shop/(.*)$' ) ) : null,
	'the repair takes the stray slash off a pattern that starts /^'
);

solseo_assert_same(
	array(),
	$solseo_has_repair
		? Manager::repair_regex_sources(
			array(
				'/product/([0-9]+)',
				'^/already-right$',
				'/.*\.html$',
				'/',
				'',
			)
		)
		: null,
	'and it leaves every other shape alone, because a stored slash is not evidence of anything'
);

$GLOBALS['wpdb'] = $solseo_redirect_db_was;
