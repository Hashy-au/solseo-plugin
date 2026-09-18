<?php
/**
 * Import tests.
 *
 * @package SolSEO
 */

use SolSEO\Redirects\Manager;
use SolSEO\Tools\Import;

solseo_assert_same(
	'{title} {sep} {sitename}',
	Import::translate( '%%title%% %%sep%% %%sitename%%' ),
	'a double wrapped template is translated'
);

solseo_assert_same(
	'{title} {sep} {sitename}',
	Import::translate( '%title% %sep% %sitename%' ),
	'a single wrapped template is translated'
);

solseo_assert_same(
	'{primary_category} archive',
	Import::translate( '%%primary_category%% archive' ),
	'placeholders with underscores survive'
);

solseo_assert_same(
	'Buy %%custom_field(size)%% now',
	Import::translate( 'Buy %%custom_field(size)%% now' ),
	'a placeholder with no equivalent is left where it is'
);

solseo_assert_same( '/old-page/', Manager::normalise( '/old-page/' ), 'a path is left alone' );
solseo_assert_same( '/old-page/', Manager::normalise( 'old-page/' ), 'a missing leading slash is added' );
solseo_assert_same( '/old-page/', Manager::normalise( 'https://example.test/old-page/' ), 'a full address is reduced to its path' );
solseo_assert_same( '', Manager::normalise( '   ' ), 'an empty address stays empty' );
