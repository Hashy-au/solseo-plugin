<?php
/**
 * The six Australian checks, and the sentence at the foot of them.
 *
 * The judging is pure and the reading is not, so this drives the judging with
 * facts of its own. What the reading does with a real database is the thing the
 * box has to say, and it says it on the Health screen.
 *
 * @package SolSEO
 */

use SolSEO\Compliance\AU_Check;

/* THE ABN CHECK DIGITS ARE THE ATO'S OWN ALGORITHM. */
solseo_assert( AU_Check::abn_valid( '51824753556' ), 'the ATO example ABN passes' );
solseo_assert( AU_Check::abn_valid( '51 824 753 556' ), 'and it passes with the spaces in' );
solseo_assert( ! AU_Check::abn_valid( '51824753557' ), 'one digit out and it does not' );
solseo_assert( ! AU_Check::abn_valid( '12345678901' ), 'and eleven digits in a row are not an ABN' );
solseo_assert( ! AU_Check::abn_valid( '5182475355' ), 'and ten digits are not one either' );

solseo_assert_same( '51 824 753 556', AU_Check::pretty_abn( '51824753556' ), 'an ABN is written the way it is written' );

solseo_assert_same(
	'51824753556',
	AU_Check::find_abn( 'Registered in Victoria. ABN 51 824 753 556. Call us on 03 9000 0000.' ),
	'the ABN is found in a sentence with a phone number beside it'
);

solseo_assert_same(
	'',
	AU_Check::find_abn( 'Call us on 0400 000 000 or 03 9000 0000.' ),
	'and a page of phone numbers holds no ABN'
);

/* REFUND WORDING THAT ARGUES WITH THE GUARANTEES. */
solseo_assert_same(
	'no refunds',
	AU_Check::find_refund_wording( '<p>Sorry, no refunds on sale items.</p>' ),
	'a blanket refusal is found'
);

solseo_assert_same(
	'all sales are final',
	AU_Check::find_refund_wording( 'All sales are final once the order has shipped.' ),
	'and so is the other way of saying it'
);

solseo_assert_same(
	'',
	AU_Check::find_refund_wording( 'We refund change of mind within 30 days, on top of your rights under the Australian Consumer Law.' ),
	'and a policy that offers more than the law does is not reported'
);

/* A STATED DELIVERY TIME. */
solseo_assert( AU_Check::states_a_time( 'Orders go out in 2 to 5 business days.' ), 'a range of business days is a time' );
solseo_assert( AU_Check::states_a_time( 'Allow 14 days for delivery.' ), 'and so is a number of days' );
solseo_assert( ! AU_Check::states_a_time( 'We post orders as quickly as we can.' ), 'and as quickly as we can is not' );

/* WHICH PAGE IS WHICH. */
solseo_assert( AU_Check::looks_like( 'Contact us', 'contact-us', 'contact|get in touch|reach us' ), 'a contact page is recognised by its title' );
solseo_assert( AU_Check::looks_like( 'Talk to a person', 'get-in-touch', 'contact|get in touch|reach us' ), 'and by its slug' );
solseo_assert( ! AU_Check::looks_like( 'About the workshop', 'about', 'contact|get in touch|reach us' ), 'and an about page is not one' );

/* THE PRIVACY POLICY CHECK SAYS THREE DIFFERENT THINGS. */
$solseo_au = AU_Check::judge_privacy( array( 'privacy_page' => false ) );

solseo_assert_same( 'look', $solseo_au['status'], 'no privacy policy set is worth a look' );
solseo_assert( false !== strpos( $solseo_au['says'], 'Settings, Privacy' ), 'and it says where the setting is' );

$solseo_au = AU_Check::judge_privacy(
	array(
		'privacy_page'      => true,
		'privacy_published' => false,
	)
);

solseo_assert( false !== strpos( $solseo_au['says'], 'not published' ), 'a draft privacy policy is named as a draft' );

$solseo_au = AU_Check::judge_privacy(
	array(
		'privacy_page'      => true,
		'privacy_published' => true,
		'privacy_linked'    => false,
	)
);

solseo_assert_same( 'look', $solseo_au['status'], 'a policy nothing links to is worth a look' );

$solseo_au = AU_Check::judge_privacy(
	array(
		'privacy_page'      => true,
		'privacy_published' => true,
		'privacy_linked'    => true,
	)
);

solseo_assert_same( 'pass', $solseo_au['status'], 'and one a visitor can find is found' );

/* THE CONSENT CHECK KNOWS THE DIFFERENCE BETWEEN NO AND NOT ASKED. */
$solseo_au = AU_Check::judge_consent( array( 'tags_read' => false ) );

solseo_assert_same( 'unknown', $solseo_au['status'], 'a site nobody has run the tag check on cannot be answered' );

$solseo_au = AU_Check::judge_consent(
	array(
		'tags_read' => true,
		'analytics' => false,
	)
);

solseo_assert_same( 'pass', $solseo_au['status'], 'a site measuring nobody has nothing to ask about' );

$solseo_au = AU_Check::judge_consent(
	array(
		'tags_read' => true,
		'analytics' => true,
		'consent'   => '',
	)
);

solseo_assert_same( 'look', $solseo_au['status'], 'a tag with nothing holding it is worth a look' );

$solseo_au = AU_Check::judge_consent(
	array(
		'tags_read' => true,
		'analytics' => true,
		'consent'   => 'Complianz',
	)
);

solseo_assert_same( 'pass', $solseo_au['status'], 'and one being held is not' );
solseo_assert( false !== strpos( $solseo_au['says'], 'Complianz' ), 'and the plugin holding it is named' );

/* THE DELIVERY CHECK. */
solseo_assert_same(
	'unknown',
	AU_Check::judge_delivery( array( 'sells' => false ) )['status'],
	'a site that posts nothing is not asked how long posting takes'
);

solseo_assert_same(
	'look',
	AU_Check::judge_delivery(
		array(
			'sells'         => true,
			'delivery_page' => true,
			'delivery_time' => false,
		)
	)['status'],
	'a delivery page with no time on it is worth a look'
);

/* EVERY CHECK NAMES A REGULATOR AND LINKS TO IT. */
$solseo_au_facts = array(
	'privacy_page'      => true,
	'privacy_published' => true,
	'privacy_linked'    => true,
	'contact_page'      => true,
	'contact_details'   => true,
	'abn'               => '51824753556',
	'refund_wording'    => array(),
	'delivery_page'     => true,
	'delivery_time'     => true,
	'sells'             => true,
	'tags_read'         => true,
	'analytics'         => false,
	'consent'           => '',
);

$solseo_au_checks = array(
	AU_Check::judge_privacy( $solseo_au_facts ),
	AU_Check::judge_contact( $solseo_au_facts ),
	AU_Check::judge_abn( $solseo_au_facts ),
	AU_Check::judge_refunds( $solseo_au_facts ),
	AU_Check::judge_consent( $solseo_au_facts ),
	AU_Check::judge_delivery( $solseo_au_facts ),
);

solseo_assert_same( 6, count( $solseo_au_checks ), 'there are six checks' );

$solseo_au_bad = array();

foreach ( $solseo_au_checks as $solseo_au ) {
	if ( ! preg_match( '#^https://www\.(accc|oaic|ato)\.gov\.au/#', (string) $solseo_au['source'] ) ) {
		$solseo_au_bad[] = $solseo_au['id'] . ' links to ' . $solseo_au['source'];
	}

	if ( '' === trim( (string) $solseo_au['source_label'] ) || '' === trim( (string) $solseo_au['says'] ) ) {
		$solseo_au_bad[] = $solseo_au['id'] . ' says nothing';
	}

	if ( ! in_array( $solseo_au['status'], array( 'pass', 'look', 'unknown' ), true ) ) {
		$solseo_au_bad[] = $solseo_au['id'] . ' has a status nobody drew';
	}

	if ( AU_Check::status_label( $solseo_au['status'] ) === $solseo_au['status'] ) {
		$solseo_au_bad[] = $solseo_au['id'] . ' has a status with no label';
	}
}

solseo_assert_same( array(), $solseo_au_bad, 'every check links to the ACCC, the OAIC or the ATO and says something' );

/* AND NOTHING ANYWHERE IN THEM CALLS A CHECK A FAILURE. */
$solseo_au_bad = array();

foreach ( $solseo_au_checks as $solseo_au ) {
	if ( preg_match( '/\b(illegal|breach|unlawful|you must)\b/i', (string) $solseo_au['says'] ) ) {
		$solseo_au_bad[] = $solseo_au['id'];
	}
}

solseo_assert_same( array(), $solseo_au_bad, 'and none of them tells somebody they are breaking the law' );
