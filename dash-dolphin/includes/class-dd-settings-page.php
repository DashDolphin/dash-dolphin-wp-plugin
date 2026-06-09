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
	 * Dashboard page: value-prop hero + account summary + recent inquiries.
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

		// Value-prop panel: lead with WHY responding fast matters, not WHAT
		// the plugin does. Stats are sourced from dashdolphin.com (Inquiry
		// Connect Study, Velocity Research, industry benchmark).
		$this->render_speed_value_prop();

		$client      = $this->plugin->api_client();
		$recent_resp = $client->get_recent_requests( 10 );

		echo '<section class="dd-section">';
		echo '<div class="dd-section-head">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Recent inquiries', 'dash-dolphin' ) . '</h2>';
		$this->render_dashboard_link(
			$this->plugin->get_dashboard_url() . '/requests',
			__( 'See all inquiries', 'dash-dolphin' )
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
	 * Speed-of-response value-prop panel.
	 *
	 * Sourced from dashdolphin.com's "Cost of Being Second" section. Stats
	 * are presented as the three contrasting realities (industry average,
	 * Dash Dolphin's window, conversion lift) plus a one-line proof of
	 * outcome. Keeps the brand voice (no exclamation points, no em dashes,
	 * no "AI" word, leads with outcomes).
	 */
	private function render_speed_value_prop(): void {
		echo '<section class="dd-section dd-speed">';
		echo '<div class="dd-speed-card">';
		echo '<div class="dd-speed-eyebrow">' . esc_html__( 'Why speed wins', 'dash-dolphin' ) . '</div>';
		echo '<h2 class="dd-speed-title">' . esc_html__( 'Speed is the entire sales process for service businesses.', 'dash-dolphin' ) . '</h2>';
		echo '<p class="dd-speed-lede">' . esc_html__( 'The business that responds first wins the job. Dash Dolphin sends you a text in seconds, before your competitor checks their email.', 'dash-dolphin' ) . '</p>';

		echo '<div class="dd-speed-stats">';
		echo '<div class="dd-speed-stat">';
		echo '<div class="dd-speed-stat-num">' . esc_html__( '78%', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-label">' . esc_html__( 'of customers hire the first business that responds', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-src">' . esc_html__( 'Inquiry Connect Study', 'dash-dolphin' ) . '</div>';
		echo '</div>';

		echo '<div class="dd-speed-stat">';
		echo '<div class="dd-speed-stat-num">' . esc_html__( '391%', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-label">' . esc_html__( 'more conversions when you respond within 1 minute', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-src">' . esc_html__( 'Velocity Research', 'dash-dolphin' ) . '</div>';
		echo '</div>';

		echo '<div class="dd-speed-stat">';
		echo '<div class="dd-speed-stat-num">' . esc_html__( '47 hrs', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-label">' . esc_html__( 'average response time for a small business', 'dash-dolphin' ) . '</div>';
		echo '<div class="dd-speed-stat-src">' . esc_html__( 'Industry benchmark', 'dash-dolphin' ) . '</div>';
		echo '</div>';
		echo '</div>'; // .dd-speed-stats

		echo '<div class="dd-speed-proof">';
		echo '<blockquote class="dd-speed-quote">' . esc_html__( 'We were closing maybe 1 or 2 out of 100 form requests. Now we are closing 9.5 out of 10.', 'dash-dolphin' ) . '</blockquote>';
		echo '<cite class="dd-speed-cite">' . esc_html__( 'Tristan, Mile High Garage Door Specialists, Colorado', 'dash-dolphin' ) . '</cite>';
		echo '</div>';

		echo '</div>'; // .dd-speed-card
		echo '</section>';
	}

	/**
	 * Setup page (formerly "Connections" pre-0.3.2).
	 *
	 * Lists each form's connection address and shows the paste-into-BCC
	 * instructions. Renamed to match the web app's vocabulary: in app.
	 * dashdolphin.com the equivalent screen is also called "Setup".
	 *
	 * Each row in the connections table now also links to the relevant
	 * walkthrough on the Integrations page, so users with a detected form
	 * platform can jump straight to a per-platform video without scrolling.
	 */
	public function render_setup(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_SETUP );

		if ( ! $this->plugin->has_api_key() ) {
			$this->render_no_key_notice_compact();
			$this->close_shell( DD_Plugin::PAGE_SETUP );
			return;
		}

		$client    = $this->plugin->api_client();
		$resp      = $client->get_forwarding_emails();
		$dashboard = $this->plugin->get_dashboard_url();

		$integrations_url       = $this->plugin->get_page_url( DD_Plugin::PAGE_INTEGRATIONS );
		$detected_platforms     = $this->get_detected_platforms();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'How setup works', 'dash-dolphin' ) . '</h2>';
		echo '<div class="dd-card">';
		echo '<ol class="dd-steps">';
		echo '<li>' . esc_html__( 'Copy the connection address for the form you want to wire up.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Open that form in your form plugin and edit its admin notification email.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the connection address into the BCC field, then save.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Submit a test to confirm alerts arrive.', 'dash-dolphin' ) . '</li>';
		echo '</ol>';
		echo '<p class="dd-help">' . esc_html__( 'BCC keeps the address hidden from your customer and from your existing notification recipients.', 'dash-dolphin' ) . '</p>';

		// Detected form plugins, rendered as pills inside the same card. Each
		// pill deep-links to the Integrations page filtered to that plugin's
		// walkthrough. We surface this row only when at least one supported
		// form plugin is active on the site; otherwise the generic
		// "see the Integrations page" line below carries the load.
		if ( ! empty( $detected_platforms ) ) {
			echo '<div class="dd-pill-row">';
			echo '<span class="dd-pill-row-label">' . esc_html__( 'Detected form plugins:', 'dash-dolphin' ) . '</span>';
			foreach ( $detected_platforms as $platform ) {
				printf(
					'<a class="dd-pill" href="%s">%s</a>',
					esc_url( add_query_arg( 'guide', $platform['slug'], $integrations_url ) ),
					esc_html( $platform['label'] )
				);
			}
			echo '</div>';
		}

		// Walkthrough line, always on its own line below the BCC sentence and
		// (when present) below the detected-platform pills.
		printf(
			'<p class="dd-help dd-help--block">%s</p>',
			sprintf(
				/* translators: %s is a link to the Integrations page. */
				wp_kses( __( 'Need a step-by-step walkthrough? See the %s page.', 'dash-dolphin' ), array( 'a' => array( 'href' => array() ) ) ),
				'<a href="' . esc_url( $integrations_url ) . '">' . esc_html__( 'Integrations', 'dash-dolphin' ) . '</a>'
			)
		);
		echo '</div>';
		echo '</section>';

		echo '<section class="dd-section">';
		echo '<div class="dd-section-head">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Connection addresses', 'dash-dolphin' ) . '</h2>';
		$this->render_dashboard_link(
			$dashboard . '/dashboard',
			__( 'Manage connections in dashboard', 'dash-dolphin' )
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

		$this->close_shell( DD_Plugin::PAGE_SETUP );
	}

	/**
	 * Setup page: Guidde walkthroughs, detected first, accordion + filter.
	 */
	public function render_integrations(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$this->open_shell( DD_Plugin::PAGE_INTEGRATIONS );

		if ( ! $this->plugin->has_api_key() ) {
			$this->render_no_key_notice_compact();
			$this->close_shell( DD_Plugin::PAGE_INTEGRATIONS );
			return;
		}

		$client = $this->plugin->api_client();
		$resp   = $client->get_integration_guides();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Integration walkthroughs', 'dash-dolphin' ) . '</h2>';
		echo '<p class="dd-subhead">' .
			esc_html__( 'Detected platforms are expanded first. Use the dropdown to see any other one.', 'dash-dolphin' ) .
			'</p>';
		echo '</section>';

		if ( is_wp_error( $resp ) ) {
			$this->render_error( $resp, __( 'We could not load setup guides.', 'dash-dolphin' ) );
			$this->close_shell( DD_Plugin::PAGE_INTEGRATIONS );
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
		echo '<input type="hidden" name="page" value="' . esc_attr( DD_Plugin::PAGE_INTEGRATIONS ) . '" />';
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
			$this->close_shell( DD_Plugin::PAGE_INTEGRATIONS );
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

		$this->close_shell( DD_Plugin::PAGE_INTEGRATIONS );
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

		// Pointer to the user profile in the Dash Dolphin web app. Deep-links to
		// the Dashboard with ?section=settings so the Settings tab opens directly.
		$profile_url = $this->plugin->get_dashboard_url() . '/dashboard?section=settings';
		echo '<div class="dd-card dd-card--muted dd-profile-callout">';
		echo '<p class="dd-help">';
		printf(
			/* translators: %s is a link to the user profile page in the Dash Dolphin dashboard. */
			wp_kses(
				__( 'Need to update your name, email, phone number, or password? Visit the %s in the Dash Dolphin dashboard.', 'dash-dolphin' ),
				array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $profile_url ),
				esc_html__( 'user profile page', 'dash-dolphin' )
			)
		);
		echo '</p>';
		echo '</div>';
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
	 * Hero header card: logo wordmark + page title/subtitle, account chip.
	 *
	 * In v0.3.0 the official Dash Dolphin SVG wordmark replaces the previous
	 * uppercase "DASH DOLPHIN" eyebrow text. We render the SVG inline so the
	 * gradient fill survives without a fetch, and so the asset still has
	 * proper alt text and a sensible height for screen readers. The SVG
	 * itself is white-on-transparent and reads correctly on the dark hero
	 * gradient. A subtle dolphin watermark continues to sit at the far right.
	 */
	private function render_hero( string $title, string $subtitle ): void {
		$icon_url = $this->plugin->get_icon_url();
		echo '<header class="dd-hero">';
		echo '<div class="dd-hero-brand">';
		printf(
			'<img class="dd-hero-mark" src="%s" alt="" width="48" height="48" aria-hidden="true" />',
			esc_url( $icon_url )
		);
		echo '<div class="dd-hero-titles">';
		echo '<p class="dd-hero-eyebrow">' . esc_html__( 'Dash Dolphin', 'dash-dolphin' ) . '</p>';
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
		if ( DD_Plugin::PAGE_SETUP !== $current_page ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $this->plugin->get_page_url( DD_Plugin::PAGE_SETUP ) ),
				esc_html__( 'Wire up a new form', 'dash-dolphin' )
			);
		}
		if ( DD_Plugin::PAGE_INTEGRATIONS !== $current_page ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $this->plugin->get_page_url( DD_Plugin::PAGE_INTEGRATIONS ) ),
				esc_html__( 'Integration walkthroughs', 'dash-dolphin' )
			);
		}
		echo '<li>';
		$this->render_dashboard_link(
			$dashboard . '/requests',
			__( 'See all inquiries at app.dashdolphin.com', 'dash-dolphin' )
		);
		echo '</li>';
		echo '<li>';
		$this->render_dashboard_link(
			$dashboard,
			__( 'Manage API keys at app.dashdolphin.com', 'dash-dolphin' )
		);
		echo '</li>';
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
			case DD_Plugin::PAGE_SETUP:
				return __( 'Setup', 'dash-dolphin' );
			case DD_Plugin::PAGE_INTEGRATIONS:
				return __( 'Integrations', 'dash-dolphin' );
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
			case DD_Plugin::PAGE_SETUP:
				return __( 'Drop a connection address into the BCC field of any form notification.', 'dash-dolphin' );
			case DD_Plugin::PAGE_INTEGRATIONS:
				return __( 'Step-by-step walkthroughs for every supported form platform.', 'dash-dolphin' );
			case DD_Plugin::PAGE_LICENSE:
				return __( 'Manage the Dash Dolphin API key this site uses.', 'dash-dolphin' );
			case DD_Plugin::PAGE_DASHBOARD:
			default:
				return __( 'Smart inquiry summaries and instant alerts for your forms.', 'dash-dolphin' );
		}
	}

	/**
	 * Full-page sales pitch for Dashboard when no API key is set yet.
	 *
	 * Users land here straight from the WordPress plugin directory and may
	 * have no context on what Dash Dolphin is. We give them: a clear value
	 * lead-in, the speed-of-response stats, a customer outcome quote, and
	 * dual CTAs (start a trial vs. paste a key they already have). Setup,
	 * Integrations, and License keep the lighter notice (see
	 * render_no_key_notice_compact below).
	 */
	private function render_no_key_notice(): void {
		$license_url  = $this->plugin->get_page_url( DD_Plugin::PAGE_LICENSE );
		// Signup with UTM so we can attribute trials to the WP plugin admin pre-key state.
		$trial_url    = 'https://app.dashdolphin.com/signup?utm_source=wordpress&utm_medium=plugin&utm_campaign=prekey_dashboard';

		echo '<section class="dd-section">';

		// Sales hero: explainer + dual CTA.
		echo '<div class="dd-prekey-hero">';
		echo '<div class="dd-prekey-eyebrow">' . esc_html__( 'What is Dash Dolphin?', 'dash-dolphin' ) . '</div>';
		echo '<h2 class="dd-prekey-title">' . esc_html__( 'Instant SMS alerts and smart summaries the moment a form is submitted.', 'dash-dolphin' ) . '</h2>';
		echo '<p class="dd-prekey-lede">' . esc_html__( 'When a lead fills out a form on your site, Dash Dolphin reads it, writes a one-line summary, and texts you in seconds, before your competitor checks their email.', 'dash-dolphin' ) . '</p>';

		echo '<div class="dd-prekey-ctas">';
		printf(
			'<a href="%s" class="button button-primary dd-prekey-cta-primary" target="_blank" rel="noopener">%s</a>',
			esc_url( $trial_url ),
			esc_html__( 'Start free trial', 'dash-dolphin' )
		);
		printf(
			'<a href="%s" class="button dd-prekey-cta-secondary">%s</a>',
			esc_url( $license_url ),
			esc_html__( 'I already have a key', 'dash-dolphin' )
		);
		echo '</div>';

		echo '<p class="dd-prekey-foot">' . esc_html__( '14-day free trial backed by a 60-day money-back guarantee.', 'dash-dolphin' ) . '</p>';
		echo '</div>'; // .dd-prekey-hero

		echo '</section>';

		// Re-use the existing speed-wins block so the proof and stats are the
		// first thing a curious admin sees, even before they've added a key.
		$this->render_speed_value_prop();

		// Sample SMS mockup to show what they're getting.
		echo '<section class="dd-section">';
		echo '<div class="dd-prekey-mockup-wrap">';
		echo '<div class="dd-prekey-mockup-copy">';
		echo '<h3 class="dd-h3">' . esc_html__( 'What you actually get on your phone', 'dash-dolphin' ) . '</h3>';
		echo '<p>' . esc_html__( 'No more digging through email. A clean summary, the customer\'s phone, and a tap to call, all in the first text.', 'dash-dolphin' ) . '</p>';
		echo '<ul class="dd-prekey-list">';
		echo '<li>' . esc_html__( 'One-line inquiry summary written for you', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Customer phone pre-formatted so a tap calls them back', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'SMS scheduling so off-hours leads wait until you\'re ready', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Smart filtering catches spam and test submissions', 'dash-dolphin' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Mock SMS bubble (CSS-only, no external image, so it always renders).
		echo '<div class="dd-prekey-phone" role="img" aria-label="' . esc_attr__( 'Example Dash Dolphin alert text message', 'dash-dolphin' ) . '">';
		echo '<div class="dd-prekey-phone-bezel">';
		echo '<div class="dd-prekey-phone-bar"><span class="dd-prekey-phone-handle">🟣 Dash Dolphin</span></div>';
		echo '<div class="dd-prekey-phone-msg">';
		echo esc_html__( 'New Request Form: Jamie L. wants a garage door spring replaced ASAP for a double door at zip code 74103. Phone: (501) 555-0123.', 'dash-dolphin' );
		echo '</div>';
		echo '<div class="dd-prekey-phone-time">' . esc_html__( '12 seconds after submission', 'dash-dolphin' ) . '</div>';
		echo '</div>';
		echo '</div>';

		echo '</div>'; // .dd-prekey-mockup-wrap
		echo '</section>';

		// Final CTA strip.
		echo '<section class="dd-section">';
		echo '<div class="dd-prekey-finalcta">';
		echo '<div class="dd-prekey-finalcta-copy">';
		echo '<h3 class="dd-h3">' . esc_html__( 'Ready to stop missing leads?', 'dash-dolphin' ) . '</h3>';
		echo '<p>' . esc_html__( 'Add your Dash Dolphin API key on the License page to connect this site. New here? Start a trial first, then come back and paste your key.', 'dash-dolphin' ) . '</p>';
		echo '</div>';
		echo '<div class="dd-prekey-finalcta-buttons">';
		printf(
			'<a href="%s" class="button button-primary" target="_blank" rel="noopener">%s</a>',
			esc_url( $trial_url ),
			esc_html__( 'Start free trial', 'dash-dolphin' )
		);
		printf(
			'<a href="%s" class="button">%s</a>',
			esc_url( $license_url ),
			esc_html__( 'Add API key', 'dash-dolphin' )
		);
		echo '</div>';
		echo '</div>'; // .dd-prekey-finalcta
		echo '</section>';
	}

	/**
	 * Compact "no API key" notice used on Setup, Integrations, and (when
	 * called from those page handlers) any other inner page. The full
	 * sales pitch lives on the Dashboard.
	 */
	private function render_no_key_notice_compact(): void {
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
		$profile_link = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $this->plugin->get_dashboard_url() . '/dashboard?section=settings' ),
			esc_html__( 'user profile page', 'dash-dolphin' )
		);
		echo '<p class="dd-help">' . wp_kses(
			sprintf(
				/* translators: %s is an <a> link to the user profile page in the Dash Dolphin dashboard. */
				__( 'Generate or rotate keys on the %s in your Dash Dolphin dashboard.', 'dash-dolphin' ),
				$profile_link
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
		echo '<p class="dd-help">';
		$this->render_dashboard_link(
			$dashboard_url,
			__( 'Manage billing at app.dashdolphin.com', 'dash-dolphin' )
		);
		echo '</p>';
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

		echo '<table class="widefat striped dd-table dd-table--wp7">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'When', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Summary', 'dash-dolphin' ) . '</th>';
		echo '<th class="dd-col-alert">' . esc_html__( 'Alert', 'dash-dolphin' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$created           = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
			$form_name         = isset( $row['form_name'] ) ? (string) $row['form_name'] : __( 'Unknown form', 'dash-dolphin' );
			$summary           = isset( $row['summary'] ) ? (string) $row['summary'] : '';
			$sms_sent          = ! empty( $row['sms_sent'] );
			$sms_status        = isset( $row['sms_delivery_status'] ) ? (string) $row['sms_delivery_status'] : '';
			$processing_status = isset( $row['processing_status'] ) ? (string) $row['processing_status'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $this->format_relative_time( $created ) ) . '</td>';
			echo '<td>' . esc_html( $form_name ) . '</td>';
			echo '<td class="dd-cell-summary">' . esc_html( $summary !== '' ? $summary : __( '(no summary)', 'dash-dolphin' ) ) . '</td>';
			echo '<td class="dd-col-alert">' . wp_kses_post(
				$this->render_alert_badge( $sms_sent, $sms_status, $processing_status, $summary )
			) . '</td>';
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

		echo '<table class="widefat striped dd-table dd-table--wp7 dd-table--withdetail">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Connection', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Connection Type', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Connection address', 'dash-dolphin' ) . '</th>';
		echo '<th class="dd-col-actions"></th>';
		echo '</tr></thead><tbody>';
		foreach ( $connections as $conn ) {
			$name       = isset( $conn['name'] ) ? (string) $conn['name'] : __( 'Untitled', 'dash-dolphin' );
			$form_type  = isset( $conn['form_type'] ) ? (string) $conn['form_type'] : '';
			$address    = isset( $conn['email_address'] ) ? (string) $conn['email_address'] : '';
			$type_label = $form_type !== '' ? $this->humanize_form_type( $form_type ) : '—';
			$phone      = isset( $conn['phone_number'] ) ? (string) $conn['phone_number'] : '';
			$schedule   = isset( $conn['weekly_schedule'] ) && is_array( $conn['weekly_schedule'] ) ? $conn['weekly_schedule'] : array();
			$timezone   = isset( $conn['timezone'] ) ? (string) $conn['timezone'] : '';

			echo '<tr class="dd-row-main">';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $type_label ) . '</td>';
			echo '<td class="dd-cell-code"><code class="dd-code">' . esc_html( $address ) . '</code></td>';
			echo '<td class="dd-col-actions">';
			if ( $address !== '' ) {
				printf(
					'<button type="button" class="button dd-copy" data-dd-copy="%s">%s</button>',
					esc_attr( $address ),
					esc_html__( 'Copy', 'dash-dolphin' )
				);
			}
			echo '</td>';
			echo '</tr>';

			// Always-visible detail row: phone + schedule for this connection.
			echo '<tr class="dd-row-detail">';
			echo '<td colspan="4" class="dd-detail-cell">';
			echo '<div class="dd-detail-grid">';

			// Phone block.
			echo '<div class="dd-detail-item">';
			echo '<div class="dd-detail-label">' . esc_html__( 'Routes alerts to', 'dash-dolphin' ) . '</div>';
			if ( $phone !== '' ) {
				echo '<div class="dd-detail-value">' . esc_html( $this->format_phone_number( $phone ) ) . '</div>';
			} else {
				echo '<div class="dd-detail-value dd-detail-value--muted">' . esc_html__( 'No phone assigned yet', 'dash-dolphin' ) . '</div>';
			}
			echo '</div>';

			// Schedule block.
			echo '<div class="dd-detail-item">';
			echo '<div class="dd-detail-label">' . esc_html__( 'When SMS sends', 'dash-dolphin' ) . '</div>';
			echo '<div class="dd-detail-value">' . esc_html( $this->humanize_schedule( $schedule, $timezone ) ) . '</div>';
			echo '</div>';

			echo '</div>'; // .dd-detail-grid

			// Link out for changes (read-only plugin).
			echo '<p class="dd-detail-help">';
			$this->render_dashboard_link(
				$this->plugin->get_dashboard_url() . '/dashboard',
				__( 'Edit phone or schedule in dashboard', 'dash-dolphin' )
			);
			echo '</p>';

			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	// -----------------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------------

	/**
	 * Render an outbound link that leaves the host (WordPress, or any other
	 * surface this plugin gets embedded into in the future) and lands in the
	 * Dash Dolphin web app.
	 *
	 * Three responsibilities:
	 *
	 *  1. Carry an external-link affordance, so the user sees they are about
	 *     to leave the current admin surface (this is the v1 fix for the
	 *     "View details just gave me a 404" report).
	 *  2. Attach the connected account's name as a data attribute, so JS
	 *     can show a one-time confirmation interstitial telling the user
	 *     which account this link will open in. If the user is signed in
	 *     to a different Dash Dolphin account in the same browser, the app
	 *     itself will surface a "Switch account" prompt; this interstitial
	 *     just lets them cancel before that.
	 *  3. Stay host-agnostic. We intentionally don't reference WordPress or
	 *     wp-admin here so the same component can be reused if we embed
	 *     this UI in other CMSes later.
	 *
	 * @param string $url   Fully qualified destination URL.
	 * @param string $label Visible link text.
	 * @param string $extra Optional extra CSS classes.
	 */
	private function render_dashboard_link( string $url, string $label, string $extra = '' ): void {
		$account_name = '';
		$payload      = $this->get_account_payload();
		if ( ! is_wp_error( $payload ) ) {
			$account = isset( $payload['account'] ) ? (array) $payload['account'] : array();
			if ( isset( $account['name'] ) && '' !== (string) $account['name'] ) {
				$account_name = (string) $account['name'];
			}
		}

		$classes = trim( 'dd-link dd-link-ext ' . $extra );

		/* translators: %s is the connected Dash Dolphin account name. */
		$title = $account_name !== ''
			? sprintf( __( 'Opens in %s on app.dashdolphin.com', 'dash-dolphin' ), $account_name )
			: __( 'Opens on app.dashdolphin.com', 'dash-dolphin' );

		// Inline SVG external-link affordance. Stroke uses currentColor so it
		// inherits the link color whether the link sits on a light card or in
		// the hero.
		$icon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';

		printf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="noopener" data-dd-dashboard-link="1" data-dd-account-name="%3$s" title="%4$s">%5$s<span class="dd-link-ext-icon">%6$s</span><span class="screen-reader-text">%7$s</span></a>',
			esc_attr( $classes ),
			esc_url( $url ),
			esc_attr( $account_name ),
			esc_attr( $title ),
			esc_html( $label ),
			$icon,
			esc_html__( '(opens in a new tab)', 'dash-dolphin' )
		);
	}

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
	 * Detected form plugins with both slug and human label.
	 *
	 * Used to render the "Detected form plugins" pill row on the Setup page.
	 * Each entry: [ 'slug' => 'elementor', 'label' => 'Elementor Pro Forms' ].
	 *
	 * @return array<int, array{slug: string, label: string}>
	 */
	private function get_detected_platforms(): array {
		$platforms = array();
		foreach ( DD_Form_Detector::get_all_detectors() as $detector ) {
			if ( $detector->is_active() ) {
				$platforms[] = array(
					'slug'  => $detector->get_slug(),
					'label' => $detector->get_label(),
				);
			}
		}
		return $platforms;
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
	 * Format a stored E.164-ish phone string as a friendly US number when
	 * possible. Falls back to the raw value for non-US numbers, which the
	 * Dash Dolphin app supports but we don't try to pretty-print here.
	 */
	private function format_phone_number( string $raw ): string {
		$digits = preg_replace( '/[^0-9]/', '', $raw );
		if ( null === $digits || '' === $digits ) {
			return $raw;
		}
		if ( strlen( $digits ) === 11 && $digits[0] === '1' ) {
			return sprintf( '(%s) %s-%s', substr( $digits, 1, 3 ), substr( $digits, 4, 3 ), substr( $digits, 7, 4 ) );
		}
		if ( strlen( $digits ) === 10 ) {
			return sprintf( '(%s) %s-%s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6, 4 ) );
		}
		return $raw;
	}

	/**
	 * Translate an IANA timezone like "America/Chicago" into the short
	 * abbreviation a service-business owner expects ("CT"). We don't try
	 * to be daylight-saving aware here: the alerts dashboard owns the
	 * authoritative schedule, this is a label for the read-only summary.
	 */
	private function timezone_short_label( string $iana ): string {
		$map = array(
			'America/New_York'    => 'ET',
			'America/Detroit'     => 'ET',
			'America/Chicago'     => 'CT',
			'America/Denver'      => 'MT',
			'America/Phoenix'     => 'MT',
			'America/Los_Angeles' => 'PT',
			'America/Anchorage'   => 'AKT',
			'Pacific/Honolulu'    => 'HT',
		);
		if ( isset( $map[ $iana ] ) ) {
			return $map[ $iana ];
		}
		if ( strpos( $iana, '/' ) !== false ) {
			$parts = explode( '/', $iana );
			return str_replace( '_', ' ', end( $parts ) );
		}
		return $iana;
	}

	/**
	 * Format an HH:MM clock string into a compact display like "9am" or
	 * "5:30pm". Used by humanize_schedule for custom windows.
	 */
	private function format_clock_time( string $hhmm ): string {
		$ts = strtotime( '2000-01-01 ' . $hhmm );
		if ( ! $ts ) {
			return $hhmm;
		}
		$min = (int) gmdate( 'i', $ts );
		return gmdate( $min === 0 ? 'ga' : 'g:ia', $ts );
	}

	/**
	 * Render a weekly_schedule jsonb blob as a one-line human summary, e.g.
	 * "24/7 (CT)" or "Mon-Fri 9am-5pm CT, Sat 10am-2pm CT".
	 *
	 * Per-day shape (keys may be capitalized or lowercase):
	 *   { openTime: '24hours' }              -> always-on that day
	 *   { openTime: 'HH:MM', closeTime: ...} -> custom window
	 *   absent / disabled                     -> off that day
	 *
	 * An empty {} blob is treated as 24/7 because the routing engine sends
	 * alerts whenever no schedule is configured.
	 */
	private function humanize_schedule( array $schedule, string $timezone ): string {
		$tz_label = $this->timezone_short_label( $timezone !== '' ? $timezone : 'America/New_York' );

		if ( empty( $schedule ) ) {
			return sprintf( '24/7 (%s)', $tz_label );
		}

		$days_order = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$day_short  = array(
			'monday'    => 'Mon',
			'tuesday'   => 'Tue',
			'wednesday' => 'Wed',
			'thursday'  => 'Thu',
			'friday'    => 'Fri',
			'saturday'  => 'Sat',
			'sunday'    => 'Sun',
		);

		// Normalize keys to lowercase so we accept both "Monday" and "monday".
		$norm = array();
		foreach ( $schedule as $k => $v ) {
			$norm[ strtolower( (string) $k ) ] = $v;
		}

		// Per-day status: 'off' | '24h' | 'HH:MM-HH:MM'.
		$status_by_day = array();
		foreach ( $days_order as $day ) {
			$cfg = isset( $norm[ $day ] ) && is_array( $norm[ $day ] ) ? $norm[ $day ] : null;
			if ( null === $cfg ) {
				$status_by_day[ $day ] = '24h';
				continue;
			}
			$open = isset( $cfg['openTime'] ) ? (string) $cfg['openTime'] : '';
			if ( '24hours' === $open ) {
				$status_by_day[ $day ] = '24h';
			} elseif ( '' === $open || 'disabled' === $open ) {
				$status_by_day[ $day ] = 'off';
			} else {
				$close = isset( $cfg['closeTime'] ) ? (string) $cfg['closeTime'] : '';
				if ( '' === $close ) {
					$status_by_day[ $day ] = 'off';
				} else {
					$status_by_day[ $day ] = $open . '-' . $close;
				}
			}
		}

		// Easy cases.
		$all_24h = true;
		$all_off = true;
		foreach ( $status_by_day as $s ) {
			if ( '24h' !== $s ) { $all_24h = false; }
			if ( 'off' !== $s ) { $all_off = false; }
		}
		if ( $all_24h ) { return sprintf( '24/7 (%s)', $tz_label ); }
		if ( $all_off ) { return __( 'No days scheduled', 'dash-dolphin' ); }

		// Group contiguous runs that share the same status.
		$runs = array();
		$cur  = null;
		foreach ( $days_order as $idx => $day ) {
			$s = $status_by_day[ $day ];
			if ( null === $cur ) {
				$cur = array( 'start' => $idx, 'end' => $idx, 'status' => $s );
			} elseif ( $cur['status'] === $s ) {
				$cur['end'] = $idx;
			} else {
				$runs[] = $cur;
				$cur    = array( 'start' => $idx, 'end' => $idx, 'status' => $s );
			}
		}
		if ( null !== $cur ) { $runs[] = $cur; }

		$parts = array();
		foreach ( $runs as $run ) {
			if ( 'off' === $run['status'] ) {
				continue;
			}
			$start_label = $day_short[ $days_order[ $run['start'] ] ];
			$end_label   = $day_short[ $days_order[ $run['end'] ] ];
			$range_label = $run['start'] === $run['end'] ? $start_label : $start_label . '-' . $end_label;

			if ( '24h' === $run['status'] ) {
				$parts[] = $range_label . ' ' . __( '24h', 'dash-dolphin' );
			} else {
				$times   = explode( '-', $run['status'], 2 );
				$parts[] = $range_label . ' ' . $this->format_clock_time( $times[0] ) . '-' . $this->format_clock_time( $times[1] );
			}
		}

		if ( empty( $parts ) ) {
			return __( 'No days scheduled', 'dash-dolphin' );
		}

		return implode( ', ', $parts ) . ' ' . $tz_label;
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
	 *
	 * Translates known machine error slugs from the Dash Dolphin API into
	 * human-readable copy so users never see raw underscored codes like
	 * "invalid_api_key" or "key_not_found". Unknown slugs are softened by
	 * humanizing the snake_case as a fallback.
	 */
	private function render_error( WP_Error $err, string $prelude ): void {
		$msg    = $err->get_error_message();
		$code   = $err->get_error_code();
		$status = (int) ( $err->get_error_data()['status'] ?? 0 );

		$friendly = $this->friendly_api_message( $msg, $status );

		echo '<div class="dd-card dd-card--error">';
		echo '<p><strong>' . esc_html( $prelude ) . '</strong></p>';
		if ( '' !== $friendly ) {
			echo '<p class="dd-help">' . esc_html( $friendly ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Translate a Dash Dolphin API error string into human-friendly copy.
	 *
	 * The Supabase edge functions return short machine slugs (invalid_api_key,
	 * key_not_found, account_inactive, etc.) that bubble straight up to the
	 * admin UI. This helper maps the known ones to friendly sentences and
	 * softens unknown slugs by humanizing the snake_case so users never see
	 * raw lowercase-underscored codes.
	 */
	private function friendly_api_message( string $raw, int $status = 0 ): string {
		$key = strtolower( trim( $raw ) );

		$map = array(
			'invalid_api_key'        => __( "The API key you entered isn't valid for this site. Double-check that you copied the full key from your Dash Dolphin dashboard. If you're testing with a staging build, make sure the key was issued in your staging account, not production.", 'dash-dolphin' ),
			'missing_api_key'        => __( 'No API key is configured yet. Add your key on the License page to get started.', 'dash-dolphin' ),
			'dd_missing_key'         => __( 'No API key is configured yet. Add your key on the License page to get started.', 'dash-dolphin' ),
			'key_not_found'          => __( "We couldn't find that API key. It may have been rotated or revoked. Generate a new one in your Dash Dolphin dashboard.", 'dash-dolphin' ),
			'key_revoked'            => __( 'This API key was revoked. Generate a new one in your Dash Dolphin dashboard.', 'dash-dolphin' ),
			'key_expired'            => __( 'This API key has expired. Generate a new one in your Dash Dolphin dashboard.', 'dash-dolphin' ),
			'account_inactive'       => __( 'Your Dash Dolphin account is inactive. Check your billing or contact support to reactivate.', 'dash-dolphin' ),
			'account_suspended'      => __( 'Your Dash Dolphin account is suspended. Contact support to reactivate.', 'dash-dolphin' ),
			'subscription_canceled'  => __( 'Your subscription is canceled. Reactivate it in the Dash Dolphin dashboard to resume alerts.', 'dash-dolphin' ),
			'subscription_expired'   => __( 'Your subscription has expired. Renew in the Dash Dolphin dashboard to resume alerts.', 'dash-dolphin' ),
			'rate_limited'           => __( "You're sending requests faster than the rate limit allows. Try again in a minute.", 'dash-dolphin' ),
			'forbidden'              => __( "You don't have permission to view this. Confirm you're signed in with the right Dash Dolphin account.", 'dash-dolphin' ),
			'unauthorized'           => __( 'Sign-in to Dash Dolphin appears to have expired. Re-enter your API key to reconnect.', 'dash-dolphin' ),
			'not_found'              => __( "We couldn't find that resource in Dash Dolphin.", 'dash-dolphin' ),
		);

		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		// HTTP status fallback when the body didn't include a slug we recognize.
		if ( '' === $key || $key === (string) (int) $key ) {
			if ( 401 === $status || 403 === $status ) {
				return __( "We couldn't authenticate with Dash Dolphin. Double-check your API key.", 'dash-dolphin' );
			}
			if ( $status >= 500 ) {
				return __( 'Dash Dolphin is having trouble responding right now. Please try again in a moment.', 'dash-dolphin' );
			}
		}

		// Heuristic: if the message looks like a raw slug (only lowercase /
		// digits / underscores), turn it into a sentence-cased phrase so the
		// user sees "Invalid api key" instead of "invalid_api_key". Otherwise
		// it's already a real sentence and we pass it through unchanged.
		if ( '' !== $key && preg_match( '/^[a-z0-9_]+$/', $key ) ) {
			return ucfirst( str_replace( '_', ' ', $key ) ) . '.';
		}

		return $raw;
	}
}
