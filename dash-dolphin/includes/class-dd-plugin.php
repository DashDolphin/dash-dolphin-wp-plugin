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
	const PAGE_DASHBOARD    = 'dash-dolphin';
	const PAGE_SETUP        = 'dash-dolphin-setup';        // Form-to-address wiring. Pre-0.3.2 this was 'Connections'.
	const PAGE_INTEGRATIONS = 'dash-dolphin-integrations'; // Platform walkthrough guides. Pre-0.3.2 this was 'Setup'.
	const PAGE_LICENSE      = 'dash-dolphin-license';

	/**
	 * Legacy slugs we redirect on admin_init so any inbound link or bookmark
	 * from before the 0.3.2 nomenclature change still lands on the right page.
	 * Map: legacy => current.
	 */
	const LEGACY_SLUG_MAP = array(
		'dash-dolphin-connections' => self::PAGE_SETUP,        // "Connections" became "Setup".
		'dash-dolphin-setup-old'   => self::PAGE_INTEGRATIONS, // Reserved; not used in any released version, here to document intent.
	);

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
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_slug' ), 1 );
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
		// Full-color brand icon for the WP admin sidebar. WordPress renders
		// menu icons at 20x20 via background-image, and does NOT apply its
		// auto-color treatment when the icon is provided as a data URI with
		// explicit fills (only fill="currentColor" or dashicon classes get
		// recolored). That means the brand gradients survive intact in both
		// the default and hover/active sidebar states, which is what we want
		// here: the menu mark should always read as the Dash Dolphin brand.
		$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGluZz0ndXRmLTgnPz48c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHdpZHRoPSIyMTguMTUyIiBoZWlnaHQ9IjIxOC4zMDUiIHZpZXdCb3g9IjAgMCAyMTguMTUyIDIxOC4zMDUiPjxkZWZzPjxsaW5lYXJHcmFkaWVudCBpZD0iYSIgeTE9IjAuNSIgeDI9IjEiIHkyPSIwLjUiIGdyYWRpZW50VW5pdHM9Im9iamVjdEJvdW5kaW5nQm94Ij48c3RvcCBvZmZzZXQ9IjAiIHN0b3AtY29sb3I9IiNmYTY2MjAiLz48c3RvcCBvZmZzZXQ9IjAuMzQxIiBzdG9wLWNvbG9yPSIjZmExZjU0Ii8+PHN0b3Agb2Zmc2V0PSIwLjY4MiIgc3RvcC1jb2xvcj0iI2I0MDBjYSIvPjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iIzQwMDBmMCIvPjwvbGluZWFyR3JhZGllbnQ+PHJhZGlhbEdyYWRpZW50IGlkPSJiIiBjeD0iMC4yODYiIGN5PSIwLjUyMiIgcj0iMC44MzgiIGdyYWRpZW50VHJhbnNmb3JtPSJtYXRyaXgoMS4wOTYsIDAuMDI1LCAtMC4wMjQsIDAuODg2LCAtMC4wMTUsIDAuMDUzKSIgZ3JhZGllbnRVbml0cz0ib2JqZWN0Qm91bmRpbmdCb3giPjxzdG9wIG9mZnNldD0iMCIgc3RvcC1jb2xvcj0iIzlmMzNjMCIgc3RvcC1vcGFjaXR5PSIwIi8+PHN0b3Agb2Zmc2V0PSIwLjEzIiBzdG9wLWNvbG9yPSIjOTkzMWJkIiBzdG9wLW9wYWNpdHk9IjAuMTI5Ii8+PHN0b3Agb2Zmc2V0PSIwLjI5NSIgc3RvcC1jb2xvcj0iIzhhMmNiMyIgc3RvcC1vcGFjaXR5PSIwLjI5NCIvPjxzdG9wIG9mZnNldD0iMC40NzkiIHN0b3AtY29sb3I9IiM3MDI0YTMiIHN0b3Atb3BhY2l0eT0iMC40NzgiLz48c3RvcCBvZmZzZXQ9IjAuNjc4IiBzdG9wLWNvbG9yPSIjNGIxODhjIiBzdG9wLW9wYWNpdHk9IjAuNjc4Ii8+PHN0b3Agb2Zmc2V0PSIwLjg4NiIgc3RvcC1jb2xvcj0iIzFkMDk3MCIgc3RvcC1vcGFjaXR5PSIwLjg4NiIvPjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iIzAwMDA1ZSIvPjwvcmFkaWFsR3JhZGllbnQ+PGxpbmVhckdyYWRpZW50IGlkPSJjIiB4MT0iLTAuMjM2IiB5MT0iMC4yODIiIHgyPSIwLjg1NCIgeTI9IjAuNjU1IiBncmFkaWVudFVuaXRzPSJvYmplY3RCb3VuZGluZ0JveCI+PHN0b3Agb2Zmc2V0PSIwIiBzdG9wLWNvbG9yPSIjZmEyZTQ5IiBzdG9wLW9wYWNpdHk9IjAiLz48c3RvcCBvZmZzZXQ9IjAuMTQ1IiBzdG9wLWNvbG9yPSIjZTcyOTQxIiBzdG9wLW9wYWNpdHk9IjAuMTQ1Ii8+PHN0b3Agb2Zmc2V0PSIwLjQzOCIgc3RvcC1jb2xvcj0iI2I3MWMyZCIgc3RvcC1vcGFjaXR5PSIwLjQzOSIvPjxzdG9wIG9mZnNldD0iMC44NDgiIHN0b3AtY29sb3I9IiM2YTA4MGQiIHN0b3Atb3BhY2l0eT0iMC44NDciLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiM0YzAwMDAiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtODkuMSAtODguOCkiPjxwYXRoIGQ9Ik0yMTUuMyw5MC4yYTExMi45ODcsMTEyLjk4NywwLDAsMC0xNy42LTEuNGMtMjkuMy4xLTU1LjMsMjYuNC01NS4yLDQyLjItOS44LDAtMjMuNSwyLjktMjcuNSw5LjgtMy41LDYuMyw0LjcsMTIuNywxOC4xLDEyLjYsMjMuNy0uMSw0MiwyLjgsNTUuOCw4YTQyLjQ4MSw0Mi40ODEsMCwwLDEsNC45LDIuMSw0OS43NDcsNDkuNzQ3LDAsMCwxLDEwLjcsNi44Yy0xOC4zLDkuNC0yNC45LDMxLjItMjEuMyw0Mi41LDQuMy0xMS4yLDIxLjMtMjUuNSwzNS0yNS4zLjgsMS42LDEuNCwzLjEsMi4xLDQuOGEzOS4yMjQsMzkuMjI0LDAsMCwxLDE1LjIsNC4zLDM0LjM5LDM0LjM5LDAsMCwwLTEzLjktLjRjLTE0LjYsMy41LTM2LjYsMTMuNC0zOC4yLDM4LjYsNy4yLTMuOCwyNC42LTExLjMsMzcuMy02LjhhNTUuMDA2LDU1LjAwNiwwLDAsMS05LjYsMTYuNiw3MC44NzksNzAuODc5LDAsMCwwLDE4LjYtMjAuNGM3LjItMTEuOCwxMC42LTI1LjYsMTAuOC0zOS41LjEtMTQtMy4yLTI4LTkuNy00Mi4zLDEyLjUsOS42LDIwLjUsMjUuMiwyMi4yLDQxLjYsMS43LDE2LjUtMywzMy41LTEyLjIsNDcuM2E3NS4wMjEsNzUuMDIxLDAsMCwxLTM4LjQsMjkuNGMtMTIuMyw0LjEtMjUuMiwzLjMtMzcuNiwxLjdhNTIuNTc5LDUyLjU3OSwwLDAsMS00My40LTM2LjcsMi43NDMsMi43NDMsMCwwLDEsMi43LTMuNWMxMy41LjYsMjYuNywxLjgsMzcuMi0xNS41LDUtMTMuOC00My45LS4xLTU1LjMtMzkuOGEuNzU4Ljc1OCwwLDAsMC0uMi0uNGMtMi43LTUuNS0xMS00LjQtMTIuNiwxLjZxLTEuMDUsMy43NS0xLjgsNy41YTExMy43NDcsMTEzLjc0NywwLDAsMC0yLjMsMjIuOEExMDkuMTEsMTA5LjExLDAsMCwwLDIwNi40LDMwNi43YzU1LjItNC4xLDk5LjEtNTAsMTAwLjgtMTA1LjNBMTA5LjExMSwxMDkuMTExLDAsMCwwLDIxNS4zLDkwLjJaTTE5MSwxMzEuM2E3LjIsNy4yLDAsMSwxLDcuMi03LjJBNy4xNyw3LjE3LDAsMCwxLDE5MSwxMzEuM1oiIGZpbGw9InVybCgjYSkiLz48cGF0aCBkPSJNMTUwLjgsMTEyLjRjLTUuMyw2LjUtOC40LDEzLjItOC40LDE4LjYtOS44LDAtMjMuNSwyLjktMjcuNSw5LjgtMy41LDYuMyw0LjcsMTIuNywxOC4yLDEyLjYsMjMuNy0uMSw0MiwyLjgsNTUuOCw4YTQyLjQ4MSw0Mi40ODEsMCwwLDEsNC45LDIuMSw0OS43NDcsNDkuNzQ3LDAsMCwxLDEwLjcsNi44Yy0xOC4zLDkuNC0yNC45LDMxLjItMjEuMiw0Mi41LDQuMy0xMS4yLDIxLjMtMjUuNSwzNC45LTI1LjNhNDkuOTY2LDQ5Ljk2NiwwLDAsMSwyLjEsNC44LDM5LjIyNCwzOS4yMjQsMCwwLDEsMTUuMiw0LjMsMzQuODMzLDM0LjgzMywwLDAsMC0xNC0uNGMtMTQuNywzLjUtMzYuNiwxMy40LTM4LjIsMzguNiw3LjItMy44LDI0LjYtMTEuMywzNy4zLTYuOGE1NS4wMDYsNTUuMDA2LDAsMCwxLTkuNiwxNi42LDcyLjIsNzIuMiwwLDAsMCwxOC42LTIwLjRjNy4zLTExLjgsMTAuNi0yNS42LDEwLjgtMzkuNS4xLTE0LTMuMi0yOC05LjYtNDIuMywxMi41LDkuNiwyMC41LDI1LjIsMjIuMiw0MS42LDEuNywxNi41LTMsMzMuNS0xMi4yLDQ3LjNhNzQuNjIsNzQuNjIsMCwwLDEtMzguNCwyOS40Yy0xLjcuNS0zLjQsMS01LjEsMS40YTg3LjM3MSw4Ny4zNzEsMCwwLDAsMjUuOC0xNjNDMTk4LjgsODYuMiwxNjguMiw5MS4xLDE1MC44LDExMi40Wm00MS42LDE4LjdhNy4xMzUsNy4xMzUsMCwwLDEtOC40LTguNCw3LjAzNywwLDAsMSw1LjYtNS42LDcuMTM1LDcuMTM1LDAsMCwxLDguNCw4LjRBNy4wMzcsNy4wMzcsMCwwLDEsMTkyLjQsMTMxLjFaIiBvcGFjaXR5PSIwLjgiIGZpbGw9InVybCgjYikiLz48Y2lyY2xlIGN4PSI0LjUiIGN5PSI0LjUiIHI9IjQuNSIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTg5LjQgMTE0LjkpIiBmaWxsPSIjYjYxNzg5Ii8+PHBhdGggZD0iTTIxNC44LDMwNS44Yy0yLjguNC01LjYuNy04LjQsMUExMDkuMTEsMTA5LjExLDAsMCwxLDg5LjEsMTk4LjVhMTE0LjUsMTE0LjUsMCwwLDEsMi4zLTIyLjhjLjUtMi41LDEuMi01LDEuOS03LjUsMS42LTUuOSw5LjktNy4xLDEyLjYtMS42LjEuMi4xLjMuMi40LDQsMTQsMTIuNywyMS4zLDIxLjksMjUuNiwxNy4yLDcuOSwzNi42LDUuMywzMy4zLDE0LjItMTAuNSwxNy4zLTIzLjcsMTYtMzcuMiwxNS41YTIuNzM4LDIuNzM4LDAsMCwwLTIuOSwyLjdoMGE4MS41NTcsODEuNTU3LDAsMCwwLDgxLjYsODEuNkE5NC4yMTEsOTQuMjExLDAsMCwwLDIxNC44LDMwNS44WiIgb3BhY2l0eT0iMC44IiBmaWxsPSJ1cmwoI2MpIi8+PHBhdGggZD0iTTMwNy4yLDIwMS40Yy0xLjYsNTIuNS00MS4yLDk2LjUtOTIuNCwxMDQuMy0xLC4yLTIsLjMtMi45LjQtMywuMy02LC41LTksLjVBODEuNTU3LDgxLjU1NywwLDAsMSwxMjEuMywyMjVhMi40ODUsMi40ODUsMCwwLDAsLjEuOCw1Mi43MzEsNTIuNzMxLDAsMCwwLDQzLjQsMzYuN2MxMC44LDEuNSwyMi4xLDIuMiwzMi45LS4zQTg3LjM1Miw4Ny4zNTIsMCwwLDAsMjIzLjEsOTkuM2MtMjMuMy0xMi41LTUyLjQtOC40LTcwLjEsMTAuOCwxMC4yLTExLjIsMjYuOS0yMSw0NC44LTIxLjFhMTAxLjk4OCwxMDEuOTg4LDAsMCwxLDE3LjYsMS40QTEwOC43NjQsMTA4Ljc2NCwwLDAsMSwzMDcuMiwyMDEuNFoiIGZpbGw9IiNmZmYiIG9wYWNpdHk9IjAuMiIvPjwvZz48L3N2Zz4=';

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

		// Note: in 0.3.2 we renamed the two middle items. The screen that lists
		// the per-form connection addresses is now called "Setup" (matches the
		// web app's nomenclature), and the screen with the platform walkthrough
		// videos is now called "Integrations". Method names follow the new
		// labels (render_setup and render_integrations). Legacy slugs from
		// pre-0.3.2 builds are handled by maybe_redirect_legacy_slug().
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
			__( 'Integrations', 'dash-dolphin' ),
			__( 'Integrations', 'dash-dolphin' ),
			self::REQUIRED_CAP,
			self::PAGE_INTEGRATIONS,
			array( $this->settings_page, 'render_integrations' )
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
	 * Send pre-0.3.2 admin URLs (notably ?page=dash-dolphin-connections) to
	 * their renamed counterparts. We hook this at admin_init before WP renders
	 * any page chrome, and use wp_safe_redirect to a same-host admin URL so
	 * it cannot bounce off-site. Other query args (filters, anchors, etc.)
	 * are preserved.
	 *
	 * @since 0.3.2
	 */
	public function maybe_redirect_legacy_slug(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
			return;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( self::LEGACY_SLUG_MAP[ $page ] ) ) {
			return;
		}
		$args         = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args['page'] = self::LEGACY_SLUG_MAP[ $page ];
		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args ) ), 301 );
		exit;
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
	 * Return the URL to the bundled Dash Dolphin wordmark logo (white SVG).
	 * This is the canonical brand asset shipped from dashdolphin.com and used
	 * inside the hero header on every plugin page.
	 */
	public function get_logo_url(): string {
		return DD_PLUGIN_URL . 'assets/img/dash-dolphin-logo-wh.svg';
	}

	/**
	 * Return the URL to the dolphin-only brand icon (white SVG). Used as a
	 * compact mark inside the hero header alongside the page title, and any
	 * other surface that needs the dolphin glyph without the wordmark text.
	 */
	public function get_icon_url(): string {
		return DD_PLUGIN_URL . 'assets/img/dash-dolphin-icon-wh.svg';
	}

	/**
	 * Return the URL to one of our admin pages by slug.
	 *
	 * @param string $page_slug One of PAGE_DASHBOARD, PAGE_SETUP, PAGE_INTEGRATIONS, PAGE_LICENSE.
	 */
	public function get_page_url( string $page_slug ): string {
		return admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) );
	}
}
