<?php
/**
 * Uninstall Dash Dolphin.
 *
 * This file is executed when the plugin is deleted from the WordPress admin.
 * It removes all plugin data, options, and database entries.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

// Only run when WordPress triggers an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// TODO: Delete plugin options.
// delete_option( 'dd_api_key' );
// delete_option( 'dd_settings' );

// TODO: Drop any custom database tables created by this plugin.

// TODO: Clear any scheduled cron events registered by this plugin.
