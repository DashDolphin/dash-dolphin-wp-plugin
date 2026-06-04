<?php
/**
 * Activity log renderer for the Dash Dolphin admin.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Activity_Log
 *
 * Fetches and displays a log of recent form submissions and their alert
 * delivery status in the WordPress admin.
 *
 * @since 0.1.0
 */
class DD_Activity_Log {

	/**
	 * Fetch recent log entries from the Dash Dolphin API.
	 *
	 * @param int $limit Maximum number of entries to retrieve.
	 * @return array Array of log entry objects/arrays.
	 * @since 0.1.0
	 */
	public function get_entries( int $limit = 25 ): array {
		// TODO: implement API call to fetch activity log entries.
		return array();
	}

	/**
	 * Render the activity log as an HTML table.
	 *
	 * @since 0.1.0
	 */
	public function render(): void {
		// TODO: implement activity log HTML table render.
	}
}
