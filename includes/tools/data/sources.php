<?php
/**
 * Where an existing set of SEO fields can be read from.
 *
 * Each entry is keyed by the meta key prefix the fields are stored under. The
 * `plugins` list is used to work out whether the source is installed, and to
 * read the name to show from that plugin's own header. Nothing here holds a
 * name of its own.
 *
 * Optional keys, and what they are for:
 *
 * - `table`          the fields live in a table of this name rather than in
 *                    post meta, so Table_Source reads it instead.
 * - `columns`        field name to column name, for a table source.
 * - `keyphrases`     a column holding JSON with the chosen phrase inside it.
 * - `robots_gate`    a column that is set when the site defaults apply, so the
 *                    per page robots settings beside it mean nothing.
 * - `robots_columns` field name to column name, read only when the gate is off.
 * - `image_as`       `id` or `url`. A source storing an address rather than an
 *                    attachment number needs the address resolved, and dropped
 *                    when it belongs to somebody else's site.
 * - `variables`      `percent`, `hash` or `none`. How that source writes a
 *                    placeholder, or that it stores finished text and must not
 *                    be touched.
 * - `title_suffix`   meta key, the value meaning the stored title is the whole
 *                    title, and what to append when it is not.
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	'_yoast_wpseo_' => array(
		'plugins'   => array(
			'wordpress-seo/wp-seo.php',
			'wordpress-seo-premium/wp-seo-premium.php',
		),
		'image_as'  => 'id',
		'variables' => 'percent',
		'post'      => array(
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
		'flags'     => array(
			'robots_noindex'  => array( '_yoast_wpseo_meta-robots-noindex', '1' ),
			'robots_nofollow' => array( '_yoast_wpseo_meta-robots-nofollow', '1' ),
		),
		'term'      => array(
			'title'       => 'wpseo_title',
			'description' => 'wpseo_desc',
			'canonical'   => 'wpseo_canonical',
		),
	),

	'rank_math_'    => array(
		'plugins'   => array(
			'seo-by-rank-math/rank-math.php',
			'seo-by-rank-math-pro/rank-math-pro.php',
		),
		'image_as'  => 'id',
		'variables' => 'percent',
		'post'      => array(
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
		'robots'    => 'rank_math_robots',
		'term'      => array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
			'canonical'   => 'rank_math_canonical_url',
		),
	),

	/*
	 * The one source that keeps its fields in a table of its own. It also
	 * writes placeholders with a hash rather than per cent signs, and stores
	 * an image as an address.
	 */
	'aioseo_'       => array(
		'plugins'        => array(
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
		),
		'table'          => 'aioseo_posts',
		'image_as'       => 'url',
		'variables'      => 'hash',
		'columns'        => array(
			'title'               => 'title',
			'description'         => 'description',
			'canonical'           => 'canonical_url',
			'focus_keyword'       => 'focus_keyword',
			'keywords'            => 'keywords',
			'og_title'            => 'og_title',
			'og_description'      => 'og_description',
			'og_image'            => 'og_image_custom_url',
			'twitter_title'       => 'twitter_title',
			'twitter_description' => 'twitter_description',
		),
		'keyphrases'     => 'keyphrases',
		'robots_gate'    => 'robots_default',
		'robots_columns' => array(
			'robots_noindex'  => 'robots_noindex',
			'robots_nofollow' => 'robots_nofollow',
		),
	),

	/*
	 * Note the two robots flags below. In this source a value of yes means
	 * keep the page out, not put it in. Reading them the other way round would
	 * hide an entire site from search on import.
	 */
	'_seopress_'    => array(
		'plugins'   => array(
			'wp-seopress/seopress.php',
			'wp-seopress-pro/seopress-pro.php',
		),
		'image_as'  => 'url',
		'variables' => 'percent',
		'post'      => array(
			'title'               => '_seopress_titles_title',
			'description'         => '_seopress_titles_desc',
			'canonical'           => '_seopress_robots_canonical',
			'focus_keyword'       => '_seopress_analysis_target_kw',
			'og_title'            => '_seopress_social_fb_title',
			'og_description'      => '_seopress_social_fb_desc',
			'og_image'            => '_seopress_social_fb_img',
			'twitter_title'       => '_seopress_social_twitter_title',
			'twitter_description' => '_seopress_social_twitter_desc',
		),
		'flags'     => array(
			'robots_noindex'  => array( '_seopress_robots_index', 'yes' ),
			'robots_nofollow' => array( '_seopress_robots_follow', 'yes' ),
		),
		'term'      => array(
			'title'       => '_seopress_titles_title',
			'description' => '_seopress_titles_desc',
			'canonical'   => '_seopress_robots_canonical',
		),
	),

	/*
	 * This source stores the finished title rather than a template, so its
	 * values are copied across untouched. It adds the site name itself at the
	 * point the page is built, unless the flag below says the stored title is
	 * the whole of it, which is why the suffix rule exists.
	 */
	'_genesis_'     => array(
		'plugins'      => array(
			'autodescription/autodescription.php',
		),
		'image_as'     => 'id',
		'variables'    => 'none',
		'post'         => array(
			'title'               => '_genesis_title',
			'description'         => '_genesis_description',
			'canonical'           => '_genesis_canonical_uri',
			'og_title'            => '_open_graph_title',
			'og_description'      => '_open_graph_description',
			'og_image'            => '_social_image_id',
			'twitter_title'       => '_twitter_title',
			'twitter_description' => '_twitter_description',
		),
		'flags'        => array(
			'robots_noindex'  => array( '_genesis_noindex', '1' ),
			'robots_nofollow' => array( '_genesis_nofollow', '1' ),
		),
		'title_suffix' => array( '_tsf_title_no_blogname', '1', ' {sep} {sitename}' ),
	),
);
