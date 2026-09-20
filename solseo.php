<?php
/**
 * Plugin Name:       SolSEO
 * Plugin URI:        https://solseo.com.au/plugin
 * Description:       Titles, meta, sitemaps, structured data, redirects and a content score for posts, pages and products.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Solkarra Group
 * Author URI:        https://solkarra.com.au
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       solseo
 * Domain Path:       /languages
 *
 * @package SolSEO
 */

defined( 'ABSPATH' ) || exit;

define( 'SOLSEO_VERSION', '2.0.0' );
define( 'SOLSEO_FILE', __FILE__ );
define( 'SOLSEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOLSEO_URL', plugin_dir_url( __FILE__ ) );

require_once SOLSEO_PATH . 'includes/autoload.php';
require_once SOLSEO_PATH . 'includes/helpers.php';

add_action( 'plugins_loaded', array( 'SolSEO\\Plugin', 'boot' ), 5 );

register_activation_hook( __FILE__, array( 'SolSEO\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SolSEO\\Install', 'deactivate' ) );
