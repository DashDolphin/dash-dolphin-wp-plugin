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
 * Singleton entry point for the Dash Dolphin plugin. v0.2.0 promotes the
 * three tabs to first-class WordPress submenu pages under a single top-level
 * Dash Dolphin menu:
 *
 *   Dash Dolphin
 *     -> Dashboard    (account summary + recent inquiries)
 *     -> Connections  (connection addresses to paste into form BCC)
 *     -> Setup        (Guidde walkthroughs filtered to detected platforms)
 *     -> License      (API key + plan/status)
 *
 * @since 0.1.0
 */
class DD_Plugin {

	/**
	 * WordPress option name that stores the Dash Dolphin API key.
	 */
	const OPTION_API_KEY = 'dd_api_key';

	/**
	 * Top-level admin menu slug.
	 */
	const MENU_SLUG = 'dash-dolphin';

	/**
	 * Submenu slugs (also used as page identifiers).
	 */
	const PAGE_DASHBOARD   = 'dash-dolphin';
	const PAGE_CONNECTIONS = 'dash-dolphin-connections';
	const PAGE_SETUP       = 'dash-dolphin-setup';
	const PAGE_LICENSE     = 'dash-dolphin-license';

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
	 * Hook suffixes returned by add_submenu_page, used to gate asset enqueue.
	 *
	 * @var string[]
	 */
	private array $page_hooks = array();

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
	 */
	private function __clone() {}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'dash-dolphin',
			false,
			dirname( plugin_basename( DD_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register the top-level Dash Dolphin admin menu plus four submenu pages.
	 *
	 * Each submenu page gets its own renderer method on DD_Settings_Page so
	 * navigation lives in the WordPress sidebar, not inside a tab strip.
	 *
	 * @since 0.2.0
	 */
	public function register_admin_menu(): void {
		// Use the bundled Dash Dolphin logo as the top-level menu icon. It is
		// already round and centered, so it sits cleanly in the sidebar.
		$icon_url = DD_PLUGIN_URL . 'assets/images/dash-dolphin-logo.png';

		add_menu_page(
			__( 'Dash Dolphin', 'dash-dolphin' ),
			__( 'Dash Dolphin', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_DASHBOARD,
			array( $this->settings_page, 'render_dashboard' ),
			$icon_url,
			81
		);

		$this->page_hooks[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Dashboard', 'dash-dolphin' ),
			__( 'Dashboard', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_DASHBOARD,
			array( $this->settings_page, 'render_dashboard' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Connections', 'dash-dolphin' ),
			__( 'Connections', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_CONNECTIONS,
			array( $this->settings_page, 'render_connections' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Setup', 'dash-dolphin' ),
			__( 'Setup', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_SETUP,
			array( $this->settings_page, 'render_setup' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'License', 'dash-dolphin' ),
			__( 'License', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_LICENSE,
			array( $this->settings_page, 'render_license' )
		);
	}

	/**
	 * Enqueue admin CSS/JS on any of our submenu pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @since 0.1.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
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
	 */
	public function get_api_key(): string {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Return whether an API key is currently configured.
	 */
	public function has_api_key(): bool {
		return '' !== $this->get_api_key();
	}

	/**
	 * Return a DD_API_Client bound to the stored API key.
	 */
	public function api_client(): DD_API_Client {
		return new DD_API_Client( $this->get_api_key() );
	}

	/**
	 * Return the canonical Dash Dolphin web dashboard URL. Staging users
	 * can override via the `dd_dashboard_url` filter.
	 */
	public function get_dashboard_url(): string {
		return (string) apply_filters( 'dd_dashboard_url', 'https://app.dashdolphin.com' );
	}

	/**
	 * Return the URL to the plugin logo asset.
	 */
	public function get_logo_url(): string {
		return DD_PLUGIN_URL . 'assets/images/dash-dolphin-logo.png';
	}

	/**
	 * Return the URL to one of our admin pages by slug.
	 *
	 * @param string $page_slug One of PAGE_DASHBOARD, PAGE_CONNECTIONS, ...
	 */
	public function get_page_url( string $page_slug ): string {
		return admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) );
	}
}
