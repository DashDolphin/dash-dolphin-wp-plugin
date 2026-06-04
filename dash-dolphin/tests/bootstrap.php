<?php
/**
 * PHPUnit bootstrap for the Dash Dolphin plugin test suite.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

// Define ABSPATH so plugin files can be loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
}

// Load the plugin bootstrap file.
require_once dirname( __DIR__ ) . '/dash-dolphin.php';
