<?php
/**
 * Plugin Name:       SMS Notifications for WordPress Forms: Dash Dolphin
 * Plugin URI:        https://dashdolphin.com/integrations/wordpress
 * Description:       SMS notifications for WordPress form submissions. Get instant text alerts for Contact Form 7, Gravity Forms, WPForms, Elementor Forms, Fluent Forms, Forminator, Ninja Forms, and Formidable Forms, with smart inquiry summaries and smart filtering. Powered by Dash Dolphin.
 * Version:           0.3.2
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
define( 'DD_VERSION', '0.3.2' );

// Absolute path to the plugin file.
define( 'DD_PLUGIN_FILE', __FILE__ );

// Absolute path to the plugin directory (trailing slash).
define( 'DD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// URL to the plugin directory (trailing slash).
define( 'DD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Base URL for the Dash Dolphin API (Supabase project URL, no trailing slash).
 *
 * The client appends `/functions/v1/<endpoint>` to this base. Defaults to the
 * production Supabase project. Developers can override this via the
 * `dd_api_base_url` filter to point at staging during development, e.g.
 *
 *     add_filter( 'dd_api_base_url', function () {
 *         return 'https://dcunazzebgjqpjzikzqb.supabase.co';
 *     } );
 *
 * @since 0.1.0
 */

// === STAGING BUILD: auto-injected filters (registered BEFORE the define so the filter actually applies) ===
// === END STAGING BUILD ===

define( 'DD_API_BASE_URL', apply_filters( 'dd_api_base_url', 'https://jsvbijuodbkquylaoqba.supabase.co' ) );

// Bootstrap the plugin on plugins_loaded so all other plugins are available.
require_once DD_PLUGIN_DIR . 'includes/class-dd-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		DD_Plugin::instance();
	}
);
