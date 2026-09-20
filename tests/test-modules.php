<?php
/**
 * A module that is off is off.
 *
 * The switchboard's whole promise is that a site running four features pays for
 * four. These are the assertions that keep it true as thirty seven more arrive.
 *
 * @package SolSEO
 */

use SolSEO\Modules;

/* EVERY MODULE CAN ACTUALLY BE BOOTED. A typo in a class name is a fatal on somebody's site. */
$solseo_modules = Modules::all();

solseo_assert( count( $solseo_modules ) > 0, 'the plugin has modules' );

foreach ( $solseo_modules as $solseo_slug => $solseo_module ) {
	solseo_assert( is_callable( $solseo_module['boot'] ), $solseo_slug . ' can be booted' );
	solseo_assert( ! empty( $solseo_module['label'] ), $solseo_slug . ' has a label' );
	solseo_assert( ! empty( $solseo_module['blurb'] ), $solseo_slug . ' says what it does' );

	solseo_assert(
		in_array( $solseo_module['context'], array( Modules::ANY, Modules::ADMIN ), true ),
		$solseo_slug . ' says when it runs'
	);

	if ( isset( $solseo_module['facts'] ) ) {
		solseo_assert( is_callable( $solseo_module['facts'] ), $solseo_slug . ' can report what it holds' );
	}
}

/* CORE IS NOT A MODULE. A switch that turns the plugin off is called Deactivate. */
foreach ( array( 'core', 'titles', 'sitemap', 'schema', 'redirects', 'robots' ) as $solseo_never ) {
	solseo_assert( ! isset( $solseo_modules[ $solseo_never ] ), $solseo_never . ' is not something to switch off' );
}

/* AN ENTRY THAT CANNOT BE BOOTED IS DROPPED, RATHER THAN FATALING LATER. */
add_filter(
	'solseo_modules',
	static function ( $modules ) {
		$modules['broken'] = array(
			'label'   => 'Broken',
			'blurb'   => 'Points at nothing.',
			'default' => true,
			'context' => Modules::ANY,
			'boot'    => 'solseo_no_such_function',
		);

		$modules['works'] = array(
			'label'   => 'Works',
			'blurb'   => 'Points at something.',
			'default' => false,
			'context' => Modules::ANY,
			'boot'    => 'solseo_test_module_boot',
		);

		return $modules;
	}
);

/**
 * A module body that does nothing.
 */
function solseo_test_module_boot() {
}

$solseo_filtered = Modules::all();

solseo_assert( ! isset( $solseo_filtered['broken'] ), 'a module that cannot be booted is dropped' );
solseo_assert( isset( $solseo_filtered['works'] ), 'and one that can is kept' );

/* A DEFAULT IS A DEFAULT UNTIL SOMEBODY SAYS OTHERWISE. */
solseo_assert( Modules::enabled( 'links' ), 'the link index runs unless it is switched off' );
solseo_assert( ! Modules::enabled( 'works' ), 'a module that ships off stays off' );
solseo_assert( ! Modules::enabled( 'no-such-module' ), 'and a module that does not exist is never on' );

/*
 * ONLY A CHOICE THAT DIFFERS FROM THE DEFAULT IS STORED.
 *
 * A site that never opened the screen has an empty option, so a default that
 * changes in a later release reaches it, while a site that made a choice keeps
 * the choice. Storing every slug would freeze every default at install time.
 */
update_option( Modules::OPTION, array() );

Modules::set( 'links', true );
solseo_assert_same( array(), get_option( Modules::OPTION ), 'agreeing with the default writes nothing' );

Modules::set( 'links', false );
solseo_assert_same( array( 'links' => false ), get_option( Modules::OPTION ), 'and disagreeing with it writes one key' );
solseo_assert( ! Modules::enabled( 'links' ), 'a module switched off reads as off' );

Modules::set( 'links', true );
solseo_assert_same( array(), get_option( Modules::OPTION ), 'and switching it back removes the key again' );

Modules::set( 'no-such-module', true );
solseo_assert_same( array(), get_option( Modules::OPTION ), 'a module that does not exist cannot be stored' );

/* ONE OPTION, NOT ONE PER MODULE. */
$solseo_module_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-modules.php' );

solseo_assert_same(
	1,
	preg_match_all( "/const [A-Z_]+ *= *'solseo_/", $solseo_module_src ),
	'the registry keeps one option and not one per module'
);

/*
 * A MODULE THAT IS OFF IS NEVER BOOTED.
 *
 * Booting is the only thing that adds a hook, so this is the whole promise of
 * the screen in one assertion.
 */
$GLOBALS['solseo_booted'] = 0;

/**
 * Count a boot.
 */
function solseo_test_counting_boot() {
	++$GLOBALS['solseo_booted'];
}

remove_all_filters( 'solseo_modules' );

add_filter(
	'solseo_modules',
	static function ( $modules ) {
		return array(
			'on'  => array(
				'label'   => 'On',
				'blurb'   => 'Runs.',
				'default' => true,
				'context' => Modules::ANY,
				'boot'    => 'solseo_test_counting_boot',
			),
			'off' => array(
				'label'   => 'Off',
				'blurb'   => 'Does not run.',
				'default' => false,
				'context' => Modules::ANY,
				'boot'    => 'solseo_test_counting_boot',
			),
		);
	}
);

Modules::boot( Modules::ANY );

solseo_assert_same( 1, $GLOBALS['solseo_booted'], 'the module that is on booted, and the one that is off did not' );

Modules::boot( Modules::ADMIN );

solseo_assert_same( 1, $GLOBALS['solseo_booted'], 'and a module booted nothing in a context it does not run in' );

remove_all_filters( 'solseo_modules' );
update_option( Modules::OPTION, array() );

/*
 * THE MEASUREMENT COSTS NOTHING ON A REQUEST THAT IS NOT READING IT.
 *
 * Counting every callback on every hook is cheap once and wasteful on every
 * page load, so only the screen that prints the numbers measures them.
 */
solseo_assert(
	false !== strpos( $solseo_module_src, "'solseo-settings' === \$page" ),
	'only the screen that prints the numbers measures them'
);

solseo_assert(
	null === Modules::hooks_added( 'links' ),
	'and a request that did not measure says so rather than showing a zero'
);

/*
 * THE SCREEN DRAWS WHAT THE REGISTRY SAYS, AND NOTHING LOCKED.
 *
 * Rendered into a string rather than looked at, so a later release cannot
 * quietly grow a padlock, a disabled row or a price on the one screen whose
 * job is to be believed.
 */
update_option( \SolSEO\Modules::OPTION, array( 'columns' => false ) );

ob_start();
\SolSEO\Admin\Features_Tab::render();
$solseo_features_html = (string) ob_get_clean();

update_option( \SolSEO\Modules::OPTION, array() );

solseo_assert( false !== strpos( $solseo_features_html, 'Internal links' ), 'the features screen lists a module' );
solseo_assert( false !== strpos( $solseo_features_html, 'name="solseo_modules[]" value="links"' ), 'and gives it a switch' );
solseo_assert( false !== strpos( $solseo_features_html, 'Always on' ), 'core is there and has no switch' );

solseo_assert(
	substr_count( $solseo_features_html, 'name="solseo_modules[]"' ) === count( \SolSEO\Modules::all() ),
	'one switch per module and no more'
);

solseo_assert(
	false !== strpos( $solseo_features_html, 'Off. It adds nothing to any page.' ),
	'a module that is off says it costs nothing'
);

foreach ( array( 'disabled', 'padlock', 'Locked', 'Unlock' ) as $solseo_forbidden ) {
	solseo_assert(
		false === strpos( $solseo_features_html, $solseo_forbidden ),
		'the features screen draws nothing ' . strtolower( $solseo_forbidden )
	);
}

solseo_assert(
	1 >= substr_count( $solseo_features_html, 'Pro' ),
	'and it mentions the paid tiers once, in one line at the bottom'
);

/*
 * BOOTING ASKS FOR NO TRANSLATION.
 *
 * `Modules::boot()` runs on `plugins_loaded`, which is before `init`. Every
 * `__()` before `init` makes WordPress 6.7 load the whole text domain early and
 * write a notice about it into every site's debug.log, and this plugin was
 * writing one on every request because the module registry carried its own
 * labels. Found in debug.log on a real WordPress by the lane next door.
 *
 * So the words live apart from the registry, and the paths that run before init
 * may not reach for them. A slug is not a word, which is why the list of our own
 * modules is a constant rather than the keys of the labels: the first fix read
 * the labels to find out which slugs were ours and translated all three of them
 * doing it, and the notice was still in the log afterwards.
 */
$solseo_modules_src = (string) file_get_contents( SOLSEO_PATH . 'includes/class-modules.php' );

$solseo_early = array();

foreach ( array( 'boot', 'enabled' ) as $solseo_name ) {
	preg_match( '#function ' . $solseo_name . '\(.*?\n\t\}#s', $solseo_modules_src, $solseo_body );

	$solseo_code = isset( $solseo_body[0] ) ? solseo_code_only( '<?php ' . $solseo_body[0] ) : '';

	solseo_assert( '' !== $solseo_code, 'Modules::' . $solseo_name . '() is there to read' );

	if ( false !== strpos( $solseo_code, 'self::words()' ) || false !== strpos( $solseo_code, 'self::all()' ) ) {
		$solseo_early[] = 'Modules::' . $solseo_name . '()';
	}
}

solseo_assert_same(
	array(),
	$solseo_early,
	'nothing that runs before init reaches for a translated word'
);

/* AND THE ONE PLACE THAT DOES ASK FOR THEM ONLY ASKS WHEN IT WAS ASKED TO. */
preg_match( '#function registered\(.*?\n\t\}#s', $solseo_modules_src, $solseo_body );

$solseo_code = isset( $solseo_body[0] ) ? solseo_code_only( '<?php ' . $solseo_body[0] ) : '';

solseo_assert( '' !== $solseo_code, 'Modules::registered() is there to read' );

solseo_assert_same(
	1,
	substr_count( $solseo_code, 'self::words()' ),
	'the registry reads the words in one place'
);

solseo_assert(
	preg_match( '/if\s*\(\s*\$described\s*\)\s*\{[^}]*self::words\(\)/s', $solseo_code ),
	'and only when it was asked to describe anything'
);

solseo_assert_same(
	array( 'links', 'columns', 'widget' ),
	\SolSEO\Modules::OURS,
	'the modules this plugin ships are named as slugs, which cost no translation'
);

solseo_assert_same(
	\SolSEO\Modules::OURS,
	array_keys( \SolSEO\Modules::words() ),
	'and every one of them has its words written somewhere that runs later'
);
