<?php
/**
 * How the site is read by a crawler.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Technical screen.
 *
 * Holds the sitemap and robots.txt today. Indexing, the crawler and the link
 * check land here as tabs rather than as menu items.
 */
class Technical_Screen extends Tabbed_Screen {

	const PAGE = 'solseo-technical';
}
