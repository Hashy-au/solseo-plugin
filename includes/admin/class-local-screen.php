<?php
/**
 * The business behind the site.
 *
 * @package SolSEO
 */

namespace SolSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Local screen.
 *
 * A slot, on the same terms as the Shop one: declared here, filled by an
 * add-on, absent from the sidebar until it holds something.
 */
class Local_Screen extends Tabbed_Screen {

	const PAGE = 'solseo-local';
}
