<?php
/**
 * United States spellings, and what they are outside the United States.
 *
 * ONE LIST, READ IN BOTH DIRECTIONS. A site set to Australian or British
 * spelling is warned about the keys; a site set to United States spelling is
 * warned about the values. Two lists would disagree with each other eventually.
 *
 * Australian and British spelling share this list. The handful of words where
 * the two genuinely differ are almost all ambiguous in context, so none of them
 * is here and the screen says the two are checked against the same list rather
 * than pretending otherwise.
 *
 * WHAT IS DELIBERATELY ABSENT. A word is only here when one spelling is wrong
 * everywhere the other is right, whatever the sentence is doing. These are out:
 *
 * - licence and license, practice and practise: the noun and the verb differ,
 *   so the right spelling depends on the sentence.
 * - program: a computer program is a program in Australia too.
 * - meter: a parking meter is a meter, a hundred centimetres is a metre.
 * - tire: a tyre is a tyre, but tire also means to grow weary.
 * - check, draft, curb, story, judgment: all mean different things in different
 *   places, and a checker that cries wolf is switched off within a week.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	// The -our words.
	'armor'          => 'armour',
	'behavior'       => 'behaviour',
	'behaviors'      => 'behaviours',
	'color'          => 'colour',
	'colors'         => 'colours',
	'colored'        => 'coloured',
	'coloring'       => 'colouring',
	'colorful'       => 'colourful',
	'endeavor'       => 'endeavour',
	'favor'          => 'favour',
	'favorable'      => 'favourable',
	'favorite'       => 'favourite',
	'favorites'      => 'favourites',
	'flavor'         => 'flavour',
	'flavors'        => 'flavours',
	'harbor'         => 'harbour',
	'honor'          => 'honour',
	'humor'          => 'humour',
	'labor'          => 'labour',
	'neighbor'       => 'neighbour',
	'neighbors'      => 'neighbours',
	'odor'           => 'odour',
	'rumor'          => 'rumour',
	'savior'         => 'saviour',
	'splendor'       => 'splendour',
	'vapor'          => 'vapour',
	'vigor'          => 'vigour',

	// The -ise words.
	'analyze'        => 'analyse',
	'analyzed'       => 'analysed',
	'apologize'      => 'apologise',
	'authorize'      => 'authorise',
	'authorized'     => 'authorised',
	'capitalize'     => 'capitalise',
	'categorize'     => 'categorise',
	'criticize'      => 'criticise',
	'customize'      => 'customise',
	'customized'     => 'customised',
	'emphasize'      => 'emphasise',
	'finalize'       => 'finalise',
	'itemize'        => 'itemise',
	'maximize'       => 'maximise',
	'memorize'       => 'memorise',
	'minimize'       => 'minimise',
	'modernize'      => 'modernise',
	'normalize'      => 'normalise',
	'optimize'       => 'optimise',
	'optimized'      => 'optimised',
	'optimizing'     => 'optimising',
	'organization'   => 'organisation',
	'organizations'  => 'organisations',
	'organize'       => 'organise',
	'organized'      => 'organised',
	'paralyze'       => 'paralyse',
	'personalize'    => 'personalise',
	'prioritize'     => 'prioritise',
	'realize'        => 'realise',
	'realized'       => 'realised',
	'recognize'      => 'recognise',
	'recognized'     => 'recognised',
	'socialize'      => 'socialise',
	'specialize'     => 'specialise',
	'specialized'    => 'specialised',
	'stabilize'      => 'stabilise',
	'standardize'    => 'standardise',
	'sterilize'      => 'sterilise',
	'subsidize'      => 'subsidise',
	'summarize'      => 'summarise',
	'sympathize'     => 'sympathise',
	'synchronize'    => 'synchronise',
	'visualize'      => 'visualise',

	// The -re words.
	'caliber'        => 'calibre',
	'center'         => 'centre',
	'centers'        => 'centres',
	'centered'       => 'centred',
	'fiber'          => 'fibre',
	'liter'          => 'litre',
	'liters'         => 'litres',
	'somber'         => 'sombre',
	'specter'        => 'spectre',
	'theater'        => 'theatre',

	// The -ogue words.
	'analog'         => 'analogue',
	'catalog'        => 'catalogue',
	'catalogs'       => 'catalogues',
	'dialog'         => 'dialogue',
	'monolog'        => 'monologue',

	// The -ce nouns.
	'defense'        => 'defence',
	'offense'        => 'offence',
	'pretense'       => 'pretence',

	// The doubled l.
	'canceled'       => 'cancelled',
	'canceling'      => 'cancelling',
	'counselor'      => 'counsellor',
	'fueled'         => 'fuelled',
	'fulfill'        => 'fulfil',
	'installment'    => 'instalment',
	'jeweler'        => 'jeweller',
	'labeled'        => 'labelled',
	'labeling'       => 'labelling',
	'marvelous'      => 'marvellous',
	'modeling'       => 'modelling',
	'signaled'       => 'signalled',
	'skillful'       => 'skilful',
	'totaled'        => 'totalled',
	'traveled'       => 'travelled',
	'traveler'       => 'traveller',
	'traveling'      => 'travelling',

	// The rest.
	'acknowledgment' => 'acknowledgement',
	'aging'          => 'ageing',
	'aluminum'       => 'aluminium',
	'anesthetic'     => 'anaesthetic',
	'archeology'     => 'archaeology',
	'artifact'       => 'artefact',
	'artifacts'      => 'artefacts',
	'cozy'           => 'cosy',
	'esthetic'       => 'aesthetic',
	'gray'           => 'grey',
	'jewelry'        => 'jewellery',
	'maneuver'       => 'manoeuvre',
	'mold'           => 'mould',
	'mustache'       => 'moustache',
	'omelet'         => 'omelette',
	'orthopedic'     => 'orthopaedic',
	'pajamas'        => 'pyjamas',
	'pediatric'      => 'paediatric',
	'plow'           => 'plough',
	'skeptic'        => 'sceptic',
	'skeptical'      => 'sceptical',
	'smolder'        => 'smoulder',
	'sulfur'         => 'sulphur',
);
