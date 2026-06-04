<?php
/**
 * Plugin Name:       Dash Dolphin: SMS and Notification Alerts for WordPress Forms
 * Plugin URI:        https://dashdolphin.com/integrations/wordpress
 * Description:       Connect WordPress forms to your Dash Dolphin account. Get SMS and email alerts on every form submission, with smart inquiry summaries and smart filtering.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Dash Dolphin
 * Author URI:        https://dashdolphin.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dash-dolphin
 * Tested up to:      6.6
 *
 * @package DashDolphin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'DD_VERSION', '0.1.0' );

// Absolute path to the plugin file.
define( 'DD_PLUGIN_FILE', __FILE__ );

// Absolute path to the plugin directory (trailing slash).
define( 'DD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// URL to the plugin directory (trailing slash).
define( 'DD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Base URL for the Dash Dolphin API.
 *
 * Developers can override this via the `dd_api_base_url` filter, e.g. for
 * pointing at a staging environment during development.
 *
 * @since 0.1.0
 */
define( 'DD_API_BASE_URL', apply_filters( 'dd_api_base_url', 'https://api.dashdolphin.com' ) );

// Bootstrap the plugin on plugins_loaded so all other plugins are available.
require_once DD_PLUGIN_DIR . 'includes/class-dd-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		DD_Plugin::instance();
	}
);
