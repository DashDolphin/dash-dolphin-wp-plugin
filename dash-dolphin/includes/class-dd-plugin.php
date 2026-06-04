<?php
/**
 * Main plugin class.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Plugin
 *
 * Singleton entry point for the Dash Dolphin plugin. Phase 1 will fill in
 * detectors, settings UI, and form-submission hooks. For now this is a
 * minimal scaffold that loads safely on activation.
 *
 * @since 0.1.0
 */
class DD_Plugin {

	/**
	 * Single instance of this class.
	 *
	 * @var DD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Return (and create if needed) the singleton instance.
	 *
	 * @since 0.1.0
	 * @return DD_Plugin
	 */
	public static function instance(): DD_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers WordPress hooks.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @since 0.1.0
	 */
	private function __clone() {}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'dash-dolphin',
			false,
			dirname( plugin_basename( DD_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
