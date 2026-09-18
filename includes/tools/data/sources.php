<?php
/**
 * Where an existing set of SEO fields can be read from.
 *
 * Each entry is keyed by the meta key prefix the fields are stored under. The
 * `plugins` list is used to work out whether the source is installed, and to
 * read the name to show from that plugin's own header. Nothing here holds a
 * name of its own.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	'_yoast_wpseo_' => array(
		'plugins' => array(
			'wordpress-seo/wp-seo.php',
			'wordpress-seo-premium/wp-seo-premium.php',
		),
		'post'    => array(
			'title'               => '_yoast_wpseo_title',
			'description'         => '_yoast_wpseo_metadesc',
			'focus_keyword'       => '_yoast_wpseo_focuskw',
			'canonical'           => '_yoast_wpseo_canonical',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'og_image'            => '_yoast_wpseo_opengraph-image-id',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
		),
		'flags'   => array(
			'robots_noindex'  => array( '_yoast_wpseo_meta-robots-noindex', '1' ),
			'robots_nofollow' => array( '_yoast_wpseo_meta-robots-nofollow', '1' ),
		),
		'term'    => array(
			'title'       => 'wpseo_title',
			'description' => 'wpseo_desc',
			'canonical'   => 'wpseo_canonical',
		),
	),

	'rank_math_'    => array(
		'plugins' => array(
			'seo-by-rank-math/rank-math.php',
			'seo-by-rank-math-pro/rank-math-pro.php',
		),
		'post'    => array(
			'title'               => 'rank_math_title',
			'description'         => 'rank_math_description',
			'focus_keyword'       => 'rank_math_focus_keyword',
			'canonical'           => 'rank_math_canonical_url',
			'og_title'            => 'rank_math_facebook_title',
			'og_description'      => 'rank_math_facebook_description',
			'og_image'            => 'rank_math_facebook_image_id',
			'twitter_title'       => 'rank_math_twitter_title',
			'twitter_description' => 'rank_math_twitter_description',
		),
		'robots'  => 'rank_math_robots',
		'term'    => array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
			'canonical'   => 'rank_math_canonical_url',
		),
	),
);
