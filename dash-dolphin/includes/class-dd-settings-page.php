<?php
/**
 * Admin settings pages for Dash Dolphin.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Settings_Page
 *
 * Renders the four Dash Dolphin admin submenu pages and the branded shell that
 * wraps them. The plugin is read-only against the Dash Dolphin backend:
 *
 *   - Dashboard:    account summary + 10 most recent inquiries
 *   - Connections:  directory of connection addresses to paste into form BCC
 *   - Setup:        Guidde walkthroughs, auto-filtered to detected platforms
 *   - License:      Dash Dolphin API key + plan/status
 *
 * No form configuration is performed here. The user pastes a connection
 * address into their form plugin's own admin notification BCC field; the
 * Dash Dolphin inbound parser does the rest.
 *
 * @since 0.1.0
 */
class DD_Settings_Page {

	/**
	 * Owning plugin instance (gives us API key + dashboard URL + logo).
	 *
	 * @var DD_Plugin
	 */
	private DD_Plugin $plugin;

	/**
	 * Cached account payload so a single page render does not hit the API
	 * more than once when both the hero header and the body need it.
	 *
	 * @var array|WP_Error|null
	 */
	private $account_cache = null;

	/**
	 * Constructor.
	 */
	public function __construct( DD_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	// -----------------------------------------------------------------------
	// Settings API
	// -----------------------------------------------------------------------

	/**
	 * Register the api_key option with the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'dd_settings',
			DD_Plugin::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize a submitted API key. Strips whitespace/quotes and busts the
	 * client cache when the value changes so the new account's data shows
	 * immediately.
	 */
	public function sanitize_api_key( $input ): string {
		$clean = trim( (string) $input );
		$clean = trim( $clean, "\"' " );
		$clean = sanitize_text_field( $clean );

		if ( $clean !== $this->plugin->get_api_key() ) {
			$old_client = new DD_API_Client( $this->plugin->get_api_key() );
			$old_client->clear_cache();
		}

		return $clean;
	}

	// -----------------------------------------------------------------------
	// Page renderers (one per submenu)
	// -----------------------------------------------------------------------

	/**
	 * Dashboard page: account summary + recent inquiries.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_DASHBOARD );

		if ( ! $this->plugin->has_api_key() ) {
			$this->render_no_key_notice();
			$this->close_shell( DD_Plugin::PAGE_DASHBOARD );
			return;
		}

		$client      = $this->plugin->api_client();
		$recent_resp = $client->get_recent_requests( 10 );

		echo '<section class="dd-section">';
		echo '<div class="dd-section-head">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Recent inquiries', 'dash-dolphin' ) . '</h2>';
		printf(
			'<a class="dd-link" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $this->plugin->get_dashboard_url() . '/inquiries' ),
			esc_html__( 'See all in dashboard', 'dash-dolphin' )
		);
		echo '</div>';
		if ( is_wp_error( $recent_resp ) ) {
			$this->render_error( $recent_resp, __( 'We could not load recent inquiries.', 'dash-dolphin' ) );
		} else {
			$rows = isset( $recent_resp['recent_requests'] ) ? (array) $recent_resp['recent_requests'] : array();
			$this->render_recent_table( $rows );
		}
		echo '</section>';

		$this->close_shell( DD_Plugin::PAGE_DASHBOARD );
	}

	/**
	 * Connections page: connection addresses + paste instructions.
	 */
	public function render_connections(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_CONNECTIONS );

		if ( ! $this->plugin->has_api_key() ) {
			$this->render_no_key_notice();
			$this->close_shell( DD_Plugin::PAGE_CONNECTIONS );
			return;
		}

		$client    = $this->plugin->api_client();
		$resp      = $client->get_forwarding_emails();
		$dashboard = $this->plugin->get_dashboard_url();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'How connections work', 'dash-dolphin' ) . '</h2>';
		echo '<div class="dd-card">';
		echo '<ol class="dd-steps">';
		echo '<li>' . esc_html__( 'Copy the connection address for the form you want to wire up.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Open that form in your form plugin and edit its admin notification email.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the connection address into the BCC field, then save.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Submit a test to confirm alerts arrive.', 'dash-dolphin' ) . '</li>';
		echo '</ol>';
		echo '<p class="dd-help">' . esc_html__( 'BCC keeps the address hidden from your customer and from your existing notification recipients.', 'dash-dolphin' ) . '</p>';
		echo '</div>';
		echo '</section>';

		echo '<section class="dd-section">';
		echo '<div class="dd-section-head">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Connections', 'dash-dolphin' ) . '</h2>';
		printf(
			'<a class="dd-link" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $dashboard . '/connections' ),
			esc_html__( 'Manage in dashboard', 'dash-dolphin' )
		);
		echo '</div>';

		if ( is_wp_error( $resp ) ) {
			$this->render_error( $resp, __( 'We could not load your connections.', 'dash-dolphin' ) );
		} else {
			$connections = isset( $resp['forwarding_emails'] )
				? (array) $resp['forwarding_emails']
				: ( isset( $resp['connections'] ) ? (array) $resp['connections'] : array() );
			$this->render_connections_table( $connections );
		}
		echo '</section>';

		$this->close_shell( DD_Plugin::PAGE_CONNECTIONS );
	}

	/**
	 * Setup page: Guidde walkthroughs, detected first, accordion + filter.
	 */
	public function render_setup(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_SETUP );

		if ( ! $this->plugin->has_api_key() ) {
			$this->render_no_key_notice();
			$this->close_shell( DD_Plugin::PAGE_SETUP );
			return;
		}

		$client = $this->plugin->api_client();
		$resp   = $client->get_integration_guides();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Setup walkthroughs', 'dash-dolphin' ) . '</h2>';
		echo '<p class="dd-subhead">' .
			esc_html__( 'Detected platforms are expanded first. Use the dropdown to see any other one.', 'dash-dolphin' ) .
			'</p>';
		echo '</section>';

		if ( is_wp_error( $resp ) ) {
			$this->render_error( $resp, __( 'We could not load setup guides.', 'dash-dolphin' ) );
			$this->close_shell( DD_Plugin::PAGE_SETUP );
			return;
		}

		$guides         = isset( $resp['guides'] ) ? (array) $resp['guides'] : array();
		$detected_slugs = $this->get_detected_slugs();
		$filter_slug    = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.

		// Sort detected guides first.
		usort(
			$guides,
			static function ( $a, $b ) use ( $detected_slugs ) {
				$a_detected = in_array( $a['slug'] ?? '', $detected_slugs, true ) ? 0 : 1;
				$b_detected = in_array( $b['slug'] ?? '', $detected_slugs, true ) ? 0 : 1;
				if ( $a_detected !== $b_detected ) {
					return $a_detected - $b_detected;
				}
				$a_order = (int) ( $a['sort_order'] ?? 0 );
				$b_order = (int) ( $b['sort_order'] ?? 0 );
				if ( $a_order !== $b_order ) {
					return $a_order - $b_order;
				}
				return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		// Filter dropdown.
		echo '<form method="get" class="dd-filter">';
		echo '<input type="hidden" name="page" value="' . esc_attr( DD_Plugin::PAGE_SETUP ) . '" />';
		echo '<label for="dd-guide-filter" class="dd-label dd-label--inline">' .
			esc_html__( 'Show guide for', 'dash-dolphin' ) .
			'</label>';
		echo '<select name="guide" id="dd-guide-filter" class="dd-select" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'All platforms', 'dash-dolphin' ) . '</option>';
		foreach ( $guides as $g ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) ( $g['slug'] ?? '' ) ),
				selected( $filter_slug, (string) ( $g['slug'] ?? '' ), false ),
				esc_html( (string) ( $g['name'] ?? '' ) )
			);
		}
		echo '</select>';
		echo '</form>';

		if ( $filter_slug !== '' ) {
			$guides = array_values(
				array_filter(
					$guides,
					static function ( $g ) use ( $filter_slug ) {
						return ( $g['slug'] ?? '' ) === $filter_slug;
					}
				)
			);
		}

		if ( empty( $guides ) ) {
			echo '<div class="dd-card dd-card--muted"><p>' .
				esc_html__( 'No guides match that filter.', 'dash-dolphin' ) .
				'</p></div>';
			$this->close_shell( DD_Plugin::PAGE_SETUP );
			return;
		}

		$open_count = 0;
		foreach ( $guides as $guide ) {
			$slug        = (string) ( $guide['slug'] ?? '' );
			$name        = (string) ( $guide['name'] ?? '' );
			$platform    = (string) ( $guide['platform'] ?? '' );
			$playbook_id = (string) ( $guide['guidde_playbook_id'] ?? '' );
			$setup_url   = (string) ( $guide['setup_url'] ?? '' );
			$is_detected = in_array( $slug, $detected_slugs, true );

			$open = ( $filter_slug !== '' && count( $guides ) === 1 ) || ( $is_detected && 0 === $open_count );
			if ( $open ) {
				++$open_count;
			}

			echo '<details class="dd-accordion"' . ( $open ? ' open' : '' ) . '>';
			echo '<summary class="dd-accordion-summary">';
			echo '<span class="dd-accordion-title">' . esc_html( $name ) . '</span>';
			if ( $is_detected ) {
				echo ' <span class="dd-badge dd-badge--success">' . esc_html__( 'Detected on this site', 'dash-dolphin' ) . '</span>';
			}
			if ( $platform !== '' ) {
				echo ' <span class="dd-badge dd-badge--muted">' . esc_html( $this->humanize_platform( $platform ) ) . '</span>';
			}
			echo '</summary>';

			if ( '' === $playbook_id ) {
				echo '<div class="dd-card dd-card--muted"><p>' .
					esc_html__( 'A walkthrough for this platform is on the way.', 'dash-dolphin' ) .
					'</p></div>';
			} else {
				$embed_url = 'https://embed.app.guidde.com/playbooks/' . rawurlencode( $playbook_id ) . '?mode=videoAndDoc';
				echo '<div class="dd-guide-embed">';
				printf(
					'<iframe src="%s" title="%s" loading="lazy" allow="clipboard-write" allowfullscreen referrerpolicy="unsafe-url"></iframe>',
					esc_url( $embed_url ),
					esc_attr(
						sprintf(
							/* translators: %s is the integration name. */
							__( '%s setup walkthrough', 'dash-dolphin' ),
							$name
						)
					)
				);
				echo '</div>';
			}

			if ( $setup_url !== '' ) {
				printf(
					'<p class="dd-accordion-footer"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
					esc_url( $setup_url ),
					esc_html__( 'Open the full setup page on dashdolphin.com', 'dash-dolphin' )
				);
			}

			echo '</details>';
		}

		$this->close_shell( DD_Plugin::PAGE_SETUP );
	}

	/**
	 * License page: API key form + plan/status detail.
	 */
	public function render_license(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_LICENSE );

		$api_key = $this->plugin->get_api_key();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'API key', 'dash-dolphin' ) . '</h2>';
		$this->render_api_key_form( $api_key );
		echo '</section>';

		if ( $this->plugin->has_api_key() ) {
			echo '<section class="dd-section">';
			echo '<h2 class="dd-h2">' . esc_html__( 'Plan', 'dash-dolphin' ) . '</h2>';
			$account_resp = $this->get_account_payload();
			if ( is_wp_error( $account_resp ) ) {
				$this->render_error( $account_resp, __( 'We could not load your plan.', 'dash-dolphin' ) );
			} else {
				$account = isset( $account_resp['account'] ) ? (array) $account_resp['account'] : array();
				$this->render_plan_card( $account, $this->plugin->get_dashboard_url() );
			}
			echo '</section>';
		}

		$this->close_shell( DD_Plugin::PAGE_LICENSE );
	}

	// -----------------------------------------------------------------------
	// Branded shell (hero + two-column layout + sidebar marketing cards)
	// -----------------------------------------------------------------------

	/**
	 * Open the branded shell: outer wrap, hero header, and main column.
	 *
	 * @param string $page_slug The submenu slug currently rendering.
	 */
	private function open_shell( string $page_slug ): void {
		$title    = $this->page_title( $page_slug );
		$subtitle = $this->page_subtitle( $page_slug );

		echo '<div class="wrap dd-wrap">';
		$this->render_hero( $title, $subtitle );
		echo '<div class="dd-layout">';
		echo '<div class="dd-main">';
	}

	/**
	 * Close the branded shell: main column, sidebar column, wrap.
	 *
	 * @param string $page_slug The submenu slug currently rendering.
	 */
	private function close_shell( string $page_slug ): void {
		echo '</div>'; // .dd-main
		echo '<aside class="dd-aside">';
		$this->render_sidebar_cards( $page_slug );
		echo '</aside>';
		echo '</div>'; // .dd-layout
		echo '</div>'; // .wrap
	}

	/**
	 * Hero header card: logo, product name, page title/subtitle, account chip.
	 */
	private function render_hero( string $title, string $subtitle ): void {
		// Inject the logo asset URL as a CSS custom property so admin.css can
		// render it as a subtle watermark on the right side of the hero. The
		// foreground <img.dd-hero-logo> stays in markup (hidden via CSS) to
		// preserve alt text for screen readers.
		$logo_url = $this->plugin->get_logo_url();
		printf(
			'<header class="dd-hero" style="--dd-hero-logo: url(%s);">',
			esc_url( $logo_url )
		);
		echo '<div class="dd-hero-brand">';
		printf(
			'<img src="%s" alt="%s" class="dd-hero-logo" />',
			esc_url( $logo_url ),
			esc_attr__( 'Dash Dolphin logo', 'dash-dolphin' )
		);
		echo '<div class="dd-hero-titles">';
		echo '<div class="dd-hero-eyebrow">' . esc_html__( 'Dash Dolphin', 'dash-dolphin' ) . '</div>';
		echo '<h1 class="dd-hero-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $subtitle ) {
			echo '<p class="dd-hero-subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';
		echo '</div>';

		if ( $this->plugin->has_api_key() ) {
			$payload = $this->get_account_payload();
			if ( ! is_wp_error( $payload ) ) {
				$account = isset( $payload['account'] ) ? (array) $payload['account'] : array();
				$this->render_hero_chip( $account );
			}
		}

		echo '</header>';
	}

	/**
	 * Right-side chip in the hero: account name + plan + status pill.
	 */
	private function render_hero_chip( array $account ): void {
		$name   = isset( $account['name'] ) ? (string) $account['name'] : '';
		$plan   = isset( $account['subscription_plan'] ) ? (string) $account['subscription_plan'] : '';
		$status = isset( $account['status'] ) ? (string) $account['status'] : '';

		$status_class = 'dd-status-pill dd-status-pill--' . sanitize_html_class( $status !== '' ? $status : 'unknown' );

		echo '<div class="dd-hero-chip">';
		echo '<div class="dd-hero-chip-label">' . esc_html__( 'Account', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-hero-chip-name">' . esc_html( $name !== '' ? $name : __( 'Unnamed account', 'dash-dolphin' ) ) . '</div>';
		echo '<div class="dd-hero-chip-meta">';
		if ( $plan !== '' ) {
			echo '<span class="dd-plan-pill">' . esc_html( ucfirst( $plan ) ) . '</span>';
		}
		if ( $status !== '' ) {
			echo '<span class="' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the right-side marketing/help cards. Cards are static for now;
	 * we deliberately do not phone home to fetch a feed.
	 */
	private function render_sidebar_cards( string $current_page ): void {
		$dashboard = $this->plugin->get_dashboard_url();

		// What's new card. Plain links, no exclamation points, no em dashes.
		echo '<div class="dd-aside-card dd-aside-card--brand">';
		echo '<div class="dd-aside-card-eyebrow">' . esc_html__( "What's new", 'dash-dolphin' ) . '</div>';
		echo '<h3 class="dd-aside-card-title">' . esc_html__( 'Smart inquiry summaries', 'dash-dolphin' ) . '</h3>';
		echo '<p class="dd-aside-card-body">' . esc_html__( 'Every submission is summarized so your phone alert leads with the name, intent, and contact info, not raw form fields.', 'dash-dolphin' ) . '</p>';
		printf(
			'<a class="dd-aside-card-cta" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://dashdolphin.com/features' ),
			esc_html__( 'See all features', 'dash-dolphin' )
		);
		echo '</div>';

		// Quick links card.
		echo '<div class="dd-aside-card">';
		echo '<h3 class="dd-aside-card-title">' . esc_html__( 'Quick links', 'dash-dolphin' ) . '</h3>';
		echo '<ul class="dd-aside-card-list">';
		if ( DD_Plugin::PAGE_CONNECTIONS !== $current_page ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $this->plugin->get_page_url( DD_Plugin::PAGE_CONNECTIONS ) ),
				esc_html__( 'Wire up a new form', 'dash-dolphin' )
			);
		}
		if ( DD_Plugin::PAGE_SETUP !== $current_page ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $this->plugin->get_page_url( DD_Plugin::PAGE_SETUP ) ),
				esc_html__( 'Walkthroughs by platform', 'dash-dolphin' )
			);
		}
		printf(
			'<li><a href="%s" target="_blank" rel="noopener">%s</a></li>',
			esc_url( $dashboard . '/inquiries' ),
			esc_html__( 'Open inquiries in dashboard', 'dash-dolphin' )
		);
		printf(
			'<li><a href="%s" target="_blank" rel="noopener">%s</a></li>',
			esc_url( $dashboard . '/api-keys' ),
			esc_html__( 'Manage API keys', 'dash-dolphin' )
		);
		echo '</ul>';
		echo '</div>';

		// Support card.
		echo '<div class="dd-aside-card">';
		echo '<h3 class="dd-aside-card-title">' . esc_html__( 'Need a hand', 'dash-dolphin' ) . '</h3>';
		echo '<p class="dd-aside-card-body">' . esc_html__( 'Read setup guides, ping support, or talk to the Dash Dolphin team.', 'dash-dolphin' ) . '</p>';
		printf(
			'<a class="dd-aside-card-cta" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://dashdolphin.com/help-support/' ),
			esc_html__( 'Visit support', 'dash-dolphin' )
		);
		echo '</div>';
	}

	/**
	 * Page-specific titles for the hero.
	 */
	private function page_title( string $page_slug ): string {
		switch ( $page_slug ) {
			case DD_Plugin::PAGE_CONNECTIONS:
				return __( 'Connections', 'dash-dolphin' );
			case DD_Plugin::PAGE_SETUP:
				return __( 'Setup', 'dash-dolphin' );
			case DD_Plugin::PAGE_LICENSE:
				return __( 'License', 'dash-dolphin' );
			case DD_Plugin::PAGE_DASHBOARD:
			default:
				return __( 'Dashboard', 'dash-dolphin' );
		}
	}

	/**
	 * Page-specific subtitles for the hero.
	 */
	private function page_subtitle( string $page_slug ): string {
		switch ( $page_slug ) {
			case DD_Plugin::PAGE_CONNECTIONS:
				return __( 'Drop a connection address into the BCC field of any form notification.', 'dash-dolphin' );
			case DD_Plugin::PAGE_SETUP:
				return __( 'Step-by-step walkthroughs for every supported platform.', 'dash-dolphin' );
			case DD_Plugin::PAGE_LICENSE:
				return __( 'Manage the Dash Dolphin API key this site uses.', 'dash-dolphin' );
			case DD_Plugin::PAGE_DASHBOARD:
			default:
				return __( 'Smart inquiry summaries and instant alerts for your forms.', 'dash-dolphin' );
		}
	}

	/**
	 * Notice that appears on Dashboard/Connections/Setup when no API key
	 * is set yet, with a direct link to the License page.
	 */
	private function render_no_key_notice(): void {
		echo '<div class="dd-card dd-card--muted">';
		echo '<p>' . esc_html__( 'Connect this site to your Dash Dolphin account to see your data.', 'dash-dolphin' ) . '</p>';
		printf(
			'<a href="%s" class="button button-primary">%s</a>',
			esc_url( $this->plugin->get_page_url( DD_Plugin::PAGE_LICENSE ) ),
			esc_html__( 'Add API key', 'dash-dolphin' )
		);
		echo '</div>';
	}

	// -----------------------------------------------------------------------
	// License page helpers
	// -----------------------------------------------------------------------

	/**
	 * Render the API key form on the License page only.
	 */
	private function render_api_key_form( string $api_key ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="dd-card dd-api-key-form">';
		settings_fields( 'dd_settings' );
		echo '<label for="dd_api_key" class="dd-label">' . esc_html__( 'Dash Dolphin API key', 'dash-dolphin' ) . '</label>';
		echo '<div class="dd-row">';
		printf(
			'<input type="password" id="dd_api_key" name="%s" value="%s" autocomplete="off" spellcheck="false" class="regular-text dd-input" placeholder="ddk_live_..." />',
			esc_attr( DD_Plugin::OPTION_API_KEY ),
			esc_attr( $api_key )
		);
		submit_button( __( 'Save key', 'dash-dolphin' ), 'primary', 'submit', false );
		echo '</div>';
		$dashboard_link = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $this->plugin->get_dashboard_url() . '/api-keys' ),
			esc_html__( 'Dash Dolphin dashboard', 'dash-dolphin' )
		);
		echo '<p class="dd-help">' . wp_kses(
			sprintf(
				/* translators: %s is an <a> link to the Dash Dolphin dashboard. */
				__( 'Generate or rotate keys in your %s.', 'dash-dolphin' ),
				$dashboard_link
			),
			array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
		) . '</p>';
		echo '</form>';
	}

	/**
	 * Render the Plan card on the License page.
	 */
	private function render_plan_card( array $account, string $dashboard_url ): void {
		$name   = isset( $account['name'] ) ? (string) $account['name'] : '';
		$plan   = isset( $account['subscription_plan'] ) ? (string) $account['subscription_plan'] : '';
		$status = isset( $account['status'] ) ? (string) $account['status'] : '';

		echo '<div class="dd-card dd-grid dd-grid-3">';
		$this->render_stat( __( 'Account', 'dash-dolphin' ), $name !== '' ? $name : __( 'Unnamed account', 'dash-dolphin' ) );
		$this->render_stat( __( 'Plan', 'dash-dolphin' ), $plan !== '' ? ucfirst( $plan ) : '—' );
		$this->render_stat( __( 'Status', 'dash-dolphin' ), $status !== '' ? ucfirst( $status ) : '—' );
		echo '</div>';
		printf(
			'<p class="dd-help"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( $dashboard_url . '/settings/billing' ),
			esc_html__( 'Manage billing in dashboard', 'dash-dolphin' )
		);
	}

	private function render_stat( string $label, string $value ): void {
		echo '<div class="dd-stat">';
		echo '<div class="dd-stat-label">' . esc_html( $label ) . '</div>';
		echo '<div class="dd-stat-value">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	// -----------------------------------------------------------------------
	// Dashboard helpers (recent inquiries table)
	// -----------------------------------------------------------------------

	/**
	 * Render the recent inquiries table (max 10 rows).
	 */
	private function render_recent_table( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<div class="dd-card dd-card--muted"><p>' .
				esc_html__( 'No inquiries yet. Once a form is connected, new submissions show up here.', 'dash-dolphin' ) .
				'</p></div>';
			return;
		}

		$dashboard = $this->plugin->get_dashboard_url();

		echo '<table class="widefat striped dd-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'When', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Summary', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Alert', 'dash-dolphin' ) . '</th>';
		echo '<th class="dd-col-actions"></th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$created           = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
			$form_name         = isset( $row['form_name'] ) ? (string) $row['form_name'] : __( 'Unknown form', 'dash-dolphin' );
			$summary           = isset( $row['summary'] ) ? (string) $row['summary'] : '';
			$sms_sent          = ! empty( $row['sms_sent'] );
			$sms_status        = isset( $row['sms_delivery_status'] ) ? (string) $row['sms_delivery_status'] : '';
			$processing_status = isset( $row['processing_status'] ) ? (string) $row['processing_status'] : '';
			$row_id            = isset( $row['id'] ) ? (string) $row['id'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $this->format_relative_time( $created ) ) . '</td>';
			echo '<td>' . esc_html( $form_name ) . '</td>';
			echo '<td>' . esc_html( $summary !== '' ? $summary : __( '(no summary)', 'dash-dolphin' ) ) . '</td>';
			echo '<td>' . wp_kses_post(
				$this->render_alert_badge( $sms_sent, $sms_status, $processing_status, $summary )
			) . '</td>';
			echo '<td class="dd-col-actions">';
			if ( $row_id !== '' ) {
				printf(
					'<a class="dd-link" href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $dashboard . '/inquiries/' . rawurlencode( $row_id ) ),
					esc_html__( 'View details', 'dash-dolphin' )
				);
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Build the "Alert" pill. We mirror the dashboard's logic: when the row
	 * has been fully processed (a summary exists) we treat it as delivered
	 * regardless of the raw Twilio SMS state, because the user has already
	 * received the alert via at least the email channel. Only when processing
	 * is incomplete or SMS truly failed do we surface a non-success state.
	 *
	 * @param bool   $sms_sent          Whether an SMS was sent at all.
	 * @param string $sms_status        Raw Twilio status.
	 * @param string $processing_status `pending|processing|completed|failed`.
	 * @param string $summary           AI summary (presence implies completion).
	 */
	private function render_alert_badge( bool $sms_sent, string $sms_status, string $processing_status, string $summary ): string {
		$proc_lc = strtolower( $processing_status );
		$sms_lc  = strtolower( $sms_status );

		// Hard fail beats everything.
		if ( in_array( $sms_lc, array( 'failed', 'undelivered' ), true ) ) {
			return '<span class="dd-badge dd-badge--error">' . esc_html__( 'Failed', 'dash-dolphin' ) . '</span>';
		}
		if ( 'failed' === $proc_lc ) {
			return '<span class="dd-badge dd-badge--error">' . esc_html__( 'Failed', 'dash-dolphin' ) . '</span>';
		}

		// Processing not yet finished.
		if ( $proc_lc !== '' && $proc_lc !== 'completed' ) {
			return '<span class="dd-badge dd-badge--info">' . esc_html__( 'Processing', 'dash-dolphin' ) . '</span>';
		}

		// Completed path. If we have a summary, the user has been alerted via
		// at least one channel, so we present success even if Twilio still
		// reports an intermediate state like "queued".
		if ( $summary !== '' ) {
			if ( $sms_sent ) {
				return '<span class="dd-badge dd-badge--success">' . esc_html__( 'SMS sent', 'dash-dolphin' ) . '</span>';
			}
			return '<span class="dd-badge dd-badge--muted">' . esc_html__( 'Email only', 'dash-dolphin' ) . '</span>';
		}

		// Fallback: no summary yet and processing reported complete (rare).
		return '<span class="dd-badge dd-badge--info">' . esc_html__( 'Processing', 'dash-dolphin' ) . '</span>';
	}

	// -----------------------------------------------------------------------
	// Connections helpers
	// -----------------------------------------------------------------------

	/**
	 * Render the connections table with copy-to-clipboard buttons.
	 */
	private function render_connections_table( array $connections ): void {
		if ( empty( $connections ) ) {
			echo '<div class="dd-card dd-card--muted"><p>' .
				esc_html__( 'No connections yet. Create one in the Dash Dolphin dashboard, then it will appear here.', 'dash-dolphin' ) .
				'</p></div>';
			return;
		}

		echo '<table class="widefat striped dd-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Connection', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Connection Type', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Connection address', 'dash-dolphin' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		foreach ( $connections as $conn ) {
			$name      = isset( $conn['name'] ) ? (string) $conn['name'] : __( 'Untitled', 'dash-dolphin' );
			$form_type = isset( $conn['form_type'] ) ? (string) $conn['form_type'] : '';
			$address   = isset( $conn['email_address'] ) ? (string) $conn['email_address'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $form_type !== '' ? $this->humanize_form_type( $form_type ) : '—' ) . '</td>';
			echo '<td><code class="dd-code">' . esc_html( $address ) . '</code></td>';
			echo '<td>';
			if ( $address !== '' ) {
				printf(
					'<button type="button" class="button dd-copy" data-dd-copy="%s">%s</button>',
					esc_attr( $address ),
					esc_html__( 'Copy', 'dash-dolphin' )
				);
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	// -----------------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------------

	/**
	 * Pull the account payload once per request (memoized).
	 *
	 * @return array|WP_Error
	 */
	private function get_account_payload() {
		if ( null === $this->account_cache ) {
			$this->account_cache = $this->plugin->api_client()->get_account_info();
		}
		return $this->account_cache;
	}

	/**
	 * Slugs of all form-plugin detectors currently active on this site.
	 *
	 * @return string[]
	 */
	private function get_detected_slugs(): array {
		$slugs = array();
		foreach ( DD_Form_Detector::get_all_detectors() as $detector ) {
			if ( $detector->is_active() ) {
				$slugs[] = $detector->get_slug();
			}
		}
		return $slugs;
	}

	/**
	 * Convert a stored platform key into a human label.
	 */
	private function humanize_platform( string $platform ): string {
		$labels = array(
			'wordpress'        => __( 'WordPress form plugin', 'dash-dolphin' ),
			'wp-builder'       => __( 'WordPress site builder', 'dash-dolphin' ),
			'website-builder'  => __( 'Website builder', 'dash-dolphin' ),
			'standalone-form'  => __( 'Standalone form', 'dash-dolphin' ),
			'ecommerce'        => __( 'Ecommerce', 'dash-dolphin' ),
			'email-marketing'  => __( 'Email marketing', 'dash-dolphin' ),
			'crm'              => __( 'CRM', 'dash-dolphin' ),
		);
		return $labels[ $platform ] ?? ucfirst( str_replace( '-', ' ', $platform ) );
	}

	/**
	 * Pretty label for a form_type string used in the Connections table.
	 */
	private function humanize_form_type( string $form_type ): string {
		$labels = array(
			'cf7'           => __( 'Contact Form 7', 'dash-dolphin' ),
			'gravity-forms' => __( 'Gravity Forms', 'dash-dolphin' ),
			'wpforms'       => __( 'WPForms', 'dash-dolphin' ),
			'elementor'     => __( 'Elementor', 'dash-dolphin' ),
			'fluent-forms'  => __( 'Fluent Forms', 'dash-dolphin' ),
			'forminator'    => __( 'Forminator', 'dash-dolphin' ),
			'ninja-forms'   => __( 'Ninja Forms', 'dash-dolphin' ),
			'formidable'    => __( 'Formidable Forms', 'dash-dolphin' ),
		);
		return $labels[ $form_type ] ?? ucfirst( str_replace( '-', ' ', $form_type ) );
	}

	/**
	 * Pretty-print an ISO timestamp as relative time (e.g. "5 minutes ago").
	 */
	private function format_relative_time( string $iso ): string {
		if ( '' === $iso ) {
			return '—';
		}
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return $iso;
		}
		return sprintf(
			/* translators: %s is a human time difference such as "5 minutes". */
			__( '%s ago', 'dash-dolphin' ),
			human_time_diff( $ts, time() )
		);
	}

	/**
	 * Render an error notice for a failed API call.
	 */
	private function render_error( WP_Error $err, string $prelude ): void {
		$msg = $err->get_error_message();
		echo '<div class="dd-card dd-card--error">';
		echo '<p><strong>' . esc_html( $prelude ) . '</strong></p>';
		if ( '' !== $msg ) {
			echo '<p class="dd-help">' . esc_html( $msg ) . '</p>';
		}
		echo '</div>';
	}
}
