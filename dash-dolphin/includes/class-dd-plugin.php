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

require_once DD_PLUGIN_DIR . 'includes/class-dd-api-client.php';
require_once DD_PLUGIN_DIR . 'includes/class-dd-form-detector.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-gravity-forms.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-wpforms.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-cf7.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-elementor.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-fluent-forms.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-forminator.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-ninja-forms.php';
require_once DD_PLUGIN_DIR . 'includes/detectors/class-dd-detector-formidable.php';
require_once DD_PLUGIN_DIR . 'includes/class-dd-settings-page.php';

/**
 * Class DD_Plugin
 *
 * Singleton entry point for the Dash Dolphin plugin. Phase 1 wires up the
 * admin menu, three-tab settings page (Overview / Connections / Setup), and
 * the small set of WordPress options the plugin owns.
 *
 * @since 0.1.0
 */
class DD_Plugin {

	/**
	 * WordPress option name that stores the Dash Dolphin API key.
	 */
	const OPTION_API_KEY = 'dd_api_key';

	/**
	 * WordPress option name that stores the active tab (purely cosmetic).
	 */
	const OPTION_ACTIVE_TAB = 'dd_active_tab';

	/**
	 * Top-level admin menu slug.
	 */
	const MENU_SLUG = 'dash-dolphin';

	/**
	 * Required capability for the settings page.
	 */
	const REQUIRED_CAP = 'manage_options';

	/**
	 * Single instance of this class.
	 *
	 * @var DD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings page renderer.
	 *
	 * @var DD_Settings_Page|null
	 */
	private ?DD_Settings_Page $settings_page = null;

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
		$this->settings_page = new DD_Settings_Page( $this );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this->settings_page, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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

	/**
	 * Register the top-level Dash Dolphin admin menu.
	 *
	 * @since 0.1.0
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Dash Dolphin', 'dash-dolphin' ),
			__( 'Dash Dolphin', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::MENU_SLUG,
			array( $this->settings_page, 'render' ),
			'dashicons-format-chat',
			81
		);
	}

	/**
	 * Enqueue admin CSS/JS only on the plugin's own page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @since 0.1.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// add_menu_page returns "toplevel_page_<slug>" as the hook suffix.
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'dd-admin',
			DD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DD_VERSION
		);

		wp_enqueue_script(
			'dd-admin',
			DD_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			DD_VERSION,
			true
		);

		wp_localize_script(
			'dd-admin',
			'dashDolphinAdmin',
			array(
				'dashboardUrl' => esc_url_raw( $this->get_dashboard_url() ),
			)
		);
	}

	/**
	 * Return the stored API key (may be empty).
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function get_api_key(): string {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Return a DD_API_Client bound to the stored API key.
	 *
	 * @return DD_API_Client
	 * @since 0.1.0
	 */
	public function api_client(): DD_API_Client {
		return new DD_API_Client( $this->get_api_key() );
	}

	/**
	 * Return the canonical Dash Dolphin web dashboard URL. Staging users
	 * can override via the `dd_dashboard_url` filter.
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function get_dashboard_url(): string {
		return (string) apply_filters( 'dd_dashboard_url', 'https://app.dashdolphin.com' );
	}
}
