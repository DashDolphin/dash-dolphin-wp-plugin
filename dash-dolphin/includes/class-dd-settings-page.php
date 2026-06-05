<?php
/**
 * Admin settings page for Dash Dolphin.
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
 * Renders the three-tab Dash Dolphin admin page (Overview / Connections /
 * Setup). The plugin is read-only against the Dash Dolphin backend:
 *
 *   - Overview:    account summary + 10 most recent inquiries
 *   - Connections: directory of forwarding addresses to paste into form BCC
 *   - Setup:       Guidde walkthroughs, auto-filtered to detected form plugins
 *
 * No form configuration is performed here. The user pastes a forwarding
 * address into their form plugin's own admin notification BCC field; Dash
 * Dolphin's inbound parser does the rest.
 *
 * @since 0.1.0
 */
class DD_Settings_Page {

	/**
	 * Default tab when none is selected.
	 */
	const DEFAULT_TAB = 'overview';

	/**
	 * Owning plugin instance (gives us API key + dashboard URL).
	 *
	 * @var DD_Plugin
	 */
	private DD_Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param DD_Plugin $plugin Owning plugin instance.
	 * @since 0.1.0
	 */
	public function __construct( DD_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register the api_key option with the Settings API so it persists via
	 * options.php with capability and nonce checks built in.
	 *
	 * @since 0.1.0
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
	 * Sanitize a submitted API key. Strips whitespace and quotes; if the
	 * sanitized value differs from the persisted one, also clears cached
	 * API responses so the new account's data shows immediately.
	 *
	 * @param string $input Raw value from the form.
	 * @return string Sanitized key.
	 * @since 0.1.0
	 */
	public function sanitize_api_key( $input ): string {
		$clean = trim( (string) $input );
		$clean = trim( $clean, "\"' " );
		$clean = sanitize_text_field( $clean );

		if ( $clean !== $this->plugin->get_api_key() ) {
			// Old client will read the old key for cache invalidation.
			$old_client = new DD_API_Client( $this->plugin->get_api_key() );
			$old_client->clear_cache();
		}

		return $clean;
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @since 0.1.0
	 */
	public function render(): void {
		if ( ! current_user_can( DD_Plugin::REQUIRED_CAP ) ) {
			return;
		}

		$active_tab = $this->get_active_tab();
		$tabs       = $this->get_tabs();
		$api_key    = $this->plugin->get_api_key();
		$has_key    = '' !== $api_key;

		echo '<div class="wrap dd-wrap">';
		echo '<h1 class="dd-heading">' . esc_html__( 'Dash Dolphin', 'dash-dolphin' ) . '</h1>';
		echo '<p class="dd-subhead">' . esc_html__( 'Smart inquiry summaries and instant alerts for your forms.', 'dash-dolphin' ) . '</p>';

		$this->render_api_key_form( $api_key );

		if ( ! $has_key ) {
			echo '<div class="dd-card dd-card--muted"><p>' .
				esc_html__( 'Save your API key above to load your account.', 'dash-dolphin' ) .
				'</p></div></div>';
			return;
		}

		// Tab navigation.
		echo '<nav class="dd-tabs nav-tab-wrapper" aria-label="' . esc_attr__( 'Dash Dolphin sections', 'dash-dolphin' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$class = 'nav-tab' . ( $slug === $active_tab ? ' nav-tab-active' : '' );
			$url   = add_query_arg(
				array(
					'page' => DD_Plugin::MENU_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<div class="dd-tab-content">';
		switch ( $active_tab ) {
			case 'connections':
				$this->render_connections_tab();
				break;
			case 'setup':
				$this->render_setup_tab();
				break;
			case 'overview':
			default:
				$this->render_overview_tab();
				break;
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Tabs for the settings page (slug => label).
	 *
	 * @return array<string,string>
	 * @since 0.1.0
	 */
	private function get_tabs(): array {
		return array(
			'overview'    => __( 'Overview', 'dash-dolphin' ),
			'connections' => __( 'Connections', 'dash-dolphin' ),
			'setup'       => __( 'Setup', 'dash-dolphin' ),
		);
	}

	/**
	 * Resolve the active tab from the request, falling back to default.
	 *
	 * @return string
	 * @since 0.1.0
	 */
	private function get_active_tab(): string {
		$tabs = $this->get_tabs();
		$raw  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
		return isset( $tabs[ $raw ] ) ? $raw : self::DEFAULT_TAB;
	}

	/**
	 * Render the API key form (always visible at the top of every tab).
	 *
	 * @param string $api_key Current stored API key.
	 * @since 0.1.0
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

	// -----------------------------------------------------------------------
	// Overview tab
	// -----------------------------------------------------------------------

	/**
	 * Render the Overview tab: account summary + 10 most recent inquiries.
	 *
	 * @since 0.1.0
	 */
	private function render_overview_tab(): void {
		$client       = $this->plugin->api_client();
		$account_resp = $client->get_account_info();
		$recent_resp  = $client->get_recent_requests( 10 );

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Account', 'dash-dolphin' ) . '</h2>';
		if ( is_wp_error( $account_resp ) ) {
			$this->render_error( $account_resp, __( 'We could not load your account.', 'dash-dolphin' ) );
		} else {
			$this->render_account_card( $account_resp );
		}
		echo '</section>';

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
			$this->render_recent_table( isset( $recent_resp['recent_requests'] ) ? (array) $recent_resp['recent_requests'] : array() );
		}
		echo '</section>';
	}

	/**
	 * Render the account info card.
	 *
	 * @param array $account Account payload from api-account-info.
	 * @since 0.1.0
	 */
	private function render_account_card( array $account ): void {
		$label  = isset( $account['label'] ) ? (string) $account['label'] : '';
		$plan   = isset( $account['plan'] ) ? (string) $account['plan'] : '';
		$status = isset( $account['status'] ) ? (string) $account['status'] : '';

		echo '<div class="dd-card dd-grid dd-grid-3">';
		$this->render_stat( __( 'Account', 'dash-dolphin' ), $label !== '' ? $label : __( 'Unnamed account', 'dash-dolphin' ) );
		$this->render_stat( __( 'Plan', 'dash-dolphin' ), $plan !== '' ? ucfirst( $plan ) : '—' );
		$this->render_stat( __( 'Status', 'dash-dolphin' ), $status !== '' ? ucfirst( $status ) : '—' );
		echo '</div>';
	}

	/**
	 * Render a single stat label/value pair.
	 *
	 * @param string $label Stat label.
	 * @param string $value Stat value.
	 * @since 0.1.0
	 */
	private function render_stat( string $label, string $value ): void {
		echo '<div class="dd-stat">';
		echo '<div class="dd-stat-label">' . esc_html( $label ) . '</div>';
		echo '<div class="dd-stat-value">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	/**
	 * Render the recent requests table (max 10 rows).
	 *
	 * @param array $rows Recent request rows from the API.
	 * @since 0.1.0
	 */
	private function render_recent_table( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<div class="dd-card dd-card--muted"><p>' .
				esc_html__( 'No inquiries yet. Once a form is connected, new submissions show up here.', 'dash-dolphin' ) .
				'</p></div>';
			return;
		}

		echo '<table class="widefat striped dd-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'When', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Summary', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Alert', 'dash-dolphin' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$created    = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
			$form_name  = isset( $row['form_name'] ) ? (string) $row['form_name'] : __( 'Unknown form', 'dash-dolphin' );
			$summary    = isset( $row['summary'] ) ? (string) $row['summary'] : '';
			$sms_sent   = ! empty( $row['sms_sent'] );
			$sms_status = isset( $row['sms_delivery_status'] ) ? (string) $row['sms_delivery_status'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $this->format_relative_time( $created ) ) . '</td>';
			echo '<td>' . esc_html( $form_name ) . '</td>';
			echo '<td>' . esc_html( $summary !== '' ? $summary : __( '(no summary)', 'dash-dolphin' ) ) . '</td>';
			echo '<td>' . wp_kses_post( $this->render_sms_badge( $sms_sent, $sms_status ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Build a colored badge representing SMS delivery state.
	 *
	 * @param bool   $sms_sent   Whether an SMS was sent.
	 * @param string $sms_status Delivery status string.
	 * @return string HTML.
	 * @since 0.1.0
	 */
	private function render_sms_badge( bool $sms_sent, string $sms_status ): string {
		if ( ! $sms_sent ) {
			return '<span class="dd-badge dd-badge--muted">' . esc_html__( 'Email only', 'dash-dolphin' ) . '</span>';
		}
		$status_lc = strtolower( $sms_status );
		$class     = 'dd-badge';
		if ( in_array( $status_lc, array( 'delivered', 'sent' ), true ) ) {
			$class .= ' dd-badge--success';
		} elseif ( in_array( $status_lc, array( 'failed', 'undelivered' ), true ) ) {
			$class .= ' dd-badge--error';
		} else {
			$class .= ' dd-badge--info';
		}
		$label = $sms_status !== '' ? ucfirst( $sms_status ) : __( 'SMS sent', 'dash-dolphin' );
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	// -----------------------------------------------------------------------
	// Connections tab
	// -----------------------------------------------------------------------

	/**
	 * Render the Connections tab: forwarding addresses + paste instructions.
	 *
	 * @since 0.1.0
	 */
	private function render_connections_tab(): void {
		$client    = $this->plugin->api_client();
		$resp      = $client->get_forwarding_emails();
		$dashboard = $this->plugin->get_dashboard_url();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'How connections work', 'dash-dolphin' ) . '</h2>';
		echo '<div class="dd-card">';
		echo '<ol class="dd-steps">';
		echo '<li>' . esc_html__( 'Copy the forwarding address for the connection you want to use.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Open the form in your form plugin and edit its admin notification email.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the forwarding address into the BCC field, then save.', 'dash-dolphin' ) . '</li>';
		echo '<li>' . esc_html__( 'Submit a test to confirm alerts arrive.', 'dash-dolphin' ) . '</li>';
		echo '</ol>';
		echo '<p class="dd-help">' . esc_html__( 'BCC keeps the address hidden from your customer and from your existing notification recipients.', 'dash-dolphin' ) . '</p>';
		echo '</div>';
		echo '</section>';

		echo '<section class="dd-section">';
		echo '<div class="dd-section-head">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Your forwarding addresses', 'dash-dolphin' ) . '</h2>';
		printf(
			'<a class="dd-link" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $dashboard . '/connections' ),
			esc_html__( 'Manage in dashboard', 'dash-dolphin' )
		);
		echo '</div>';

		if ( is_wp_error( $resp ) ) {
			$this->render_error( $resp, __( 'We could not load your forwarding addresses.', 'dash-dolphin' ) );
		} else {
			$connections = isset( $resp['forwarding_emails'] ) ? (array) $resp['forwarding_emails'] : ( isset( $resp['connections'] ) ? (array) $resp['connections'] : array() );
			$this->render_connections_table( $connections );
		}
		echo '</section>';
	}

	/**
	 * Render the connections table with copy-to-clipboard buttons.
	 *
	 * @param array $connections Connection rows.
	 * @since 0.1.0
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
		echo '<th>' . esc_html__( 'Form plugin', 'dash-dolphin' ) . '</th>';
		echo '<th>' . esc_html__( 'Forwarding address (paste into BCC)', 'dash-dolphin' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		foreach ( $connections as $conn ) {
			$name      = isset( $conn['name'] ) ? (string) $conn['name'] : __( 'Untitled', 'dash-dolphin' );
			$form_type = isset( $conn['form_type'] ) ? (string) $conn['form_type'] : '';
			$address   = isset( $conn['email_address'] ) ? (string) $conn['email_address'] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $form_type !== '' ? $form_type : '—' ) . '</td>';
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
	// Setup tab
	// -----------------------------------------------------------------------

	/**
	 * Render the Setup tab: Guidde playbooks, detected first, accordion.
	 *
	 * @since 0.1.0
	 */
	private function render_setup_tab(): void {
		$client = $this->plugin->api_client();
		$resp   = $client->get_integration_guides();

		echo '<section class="dd-section">';
		echo '<h2 class="dd-h2">' . esc_html__( 'Setup walkthroughs', 'dash-dolphin' ) . '</h2>';
		echo '<p class="dd-subhead">' .
			esc_html__( 'Detected form plugins are expanded first. Use the dropdown to see any other platform.', 'dash-dolphin' ) .
			'</p>';
		echo '</section>';

		if ( is_wp_error( $resp ) ) {
			$this->render_error( $resp, __( 'We could not load setup guides.', 'dash-dolphin' ) );
			return;
		}

		$guides           = isset( $resp['guides'] ) ? (array) $resp['guides'] : array();
		$detected_slugs   = $this->get_detected_slugs();
		$filter_slug      = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.

		// Sort: detected guides first (preserve their relative order), then everything else.
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
		echo '<input type="hidden" name="page" value="' . esc_attr( DD_Plugin::MENU_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="setup" />';
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

		// Apply filter if a specific guide was requested.
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
			return;
		}

		// Accordion. When filtering or when there is exactly one detected
		// guide, that guide is open by default. All others are closed.
		$open_count = 0;
		foreach ( $guides as $guide ) {
			$slug        = (string) ( $guide['slug'] ?? '' );
			$name        = (string) ( $guide['name'] ?? '' );
			$platform    = (string) ( $guide['platform'] ?? '' );
			$playbook_id = (string) ( $guide['guidde_playbook_id'] ?? '' );
			$setup_url   = (string) ( $guide['setup_url'] ?? '' );
			$is_detected = in_array( $slug, $detected_slugs, true );

			// Open the first detected guide, or the only guide when filtering.
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
	}

	// -----------------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------------

	/**
	 * Return the slugs of all form-plugin detectors that are currently active
	 * on this site.
	 *
	 * @return string[]
	 * @since 0.1.0
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
	 *
	 * @param string $platform Platform key from integration_guides.platform.
	 * @return string
	 * @since 0.1.0
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
	 * Pretty-print an ISO timestamp as relative time (e.g. "5 minutes ago").
	 *
	 * @param string $iso ISO 8601 timestamp.
	 * @return string
	 * @since 0.1.0
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
	 * @param WP_Error $err     The error.
	 * @param string   $prelude Friendly intro sentence.
	 * @since 0.1.0
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
