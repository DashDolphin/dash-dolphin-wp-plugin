<?php
/**
 * Dash Dolphin API client.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_API_Client
 *
 * Handles all outbound HTTP communication with the Dash Dolphin backend API.
 *
 * The plugin uses Dash Dolphin's email-forwarding ingestion. Each row in the
 * server-side form_connections table owns a unique forwarding address of the
 * shape {12-char alphanumeric}@app.dashdolphin.com (or the configured staging
 * inbound domain). The site owner maps each WordPress form to one of these
 * connections and pastes that connection's address into the form plugin's
 * own notification settings. This client only reads account state; it never
 * POSTs form submissions and it does not create form_connections rows.
 *
 * @since 0.1.0
 */
class DD_API_Client {

	/**
	 * Transient prefix for cached responses.
	 */
	const CACHE_PREFIX = 'dd_api_';

	/**
	 * Default cache lifetime in seconds.
	 */
	const CACHE_TTL = 300;

	/**
	 * API key used for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key The Dash Dolphin API key.
	 * @since 0.1.0
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Verify the stored API key against the Dash Dolphin API.
	 *
	 * Calls the `api-verify-key` edge function. A 200 response means the key
	 * is valid and active.
	 *
	 * @return array|WP_Error Decoded response on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function verify_key() {
		return $this->request( 'GET', 'api-verify-key', false );
	}

	/**
	 * Fetch account information (label, plan, status).
	 *
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return array|WP_Error Associative array on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function get_account_info( bool $force_refresh = false ) {
		return $this->request( 'GET', 'api-account-info', ! $force_refresh );
	}

	/**
	 * Fetch the list of form connections (forwarding addresses) for this
	 * account. Each entry contains the connection id, name, email_address,
	 * form_type, authorized_domain, and created_at.
	 *
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return array|WP_Error Array on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function get_forwarding_emails( bool $force_refresh = false ) {
		return $this->request( 'GET', 'api-forwarding-emails', ! $force_refresh );
	}

	/**
	 * Fetch the most recent form submissions for the account.
	 *
	 * Each entry contains the submission id, created_at timestamp, smart
	 * inquiry summary, SMS send status, the form_connection_id it arrived
	 * through, and the form connection's name and form_type. Raw email
	 * content is intentionally not returned: the site owner opens the Dash
	 * Dolphin dashboard to see the full inquiry.
	 *
	 * @param int  $limit         Number of rows to return (1..20, default 10).
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return array|WP_Error Decoded response on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function get_recent_requests( int $limit = 10, bool $force_refresh = false ) {
		$limit = max( 1, min( 20, $limit ) );
		return $this->request(
			'GET',
			'api-recent-requests',
			! $force_refresh,
			array( 'limit' => (string) $limit )
		);
	}

	/**
	 * Fetch the catalog of published integration guides.
	 *
	 * Each entry contains the slug, name, platform group, Guidde playbook id,
	 * setup_url, sort_order, and updated_at. The plugin uses this list to
	 * render the Setup tab. The detector slug for an installed form plugin
	 * matches the guide's slug (e.g. "gravity-forms", "elementor").
	 *
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return array|WP_Error Decoded response on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function get_integration_guides( bool $force_refresh = false ) {
		return $this->request( 'GET', 'api-integration-guides', ! $force_refresh );
	}

	/**
	 * Clear all cached responses tied to the current API key.
	 *
	 * @since 0.1.0
	 */
	public function clear_cache(): void {
		$endpoints = array(
			'api-verify-key',
			'api-account-info',
			'api-forwarding-emails',
			'api-integration-guides',
		);
		foreach ( $endpoints as $endpoint ) {
			delete_transient( $this->cache_key( $endpoint ) );
		}
		// Recent-requests cache keys also vary by limit; clear common limits.
		foreach ( array( 1, 5, 10, 20 ) as $limit ) {
			delete_transient( $this->cache_key( 'api-recent-requests?limit=' . $limit ) );
		}
	}

	/**
	 * Build the transient cache key for a given endpoint.
	 *
	 * The API key is hashed into the cache key so switching keys does not leak
	 * cached data across accounts.
	 *
	 * @param string $endpoint Edge function slug (may include a ?query suffix
	 *                         to namespace per-parameter responses).
	 * @return string
	 * @since 0.1.0
	 */
	private function cache_key( string $endpoint ): string {
		return self::CACHE_PREFIX . md5( $endpoint . '|' . $this->api_key );
	}

	/**
	 * Execute an HTTP request against a Dash Dolphin edge function.
	 *
	 * @param string $method    HTTP method (GET or POST).
	 * @param string $endpoint  Edge function slug (no leading slash).
	 * @param bool   $use_cache Whether to read/write the transient cache.
	 * @param array  $query     Optional query string params to append.
	 * @return array|WP_Error   Decoded JSON body on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	private function request( string $method, string $endpoint, bool $use_cache = true, array $query = array() ) {
		if ( '' === $this->api_key ) {
			return new WP_Error( 'dd_missing_key', __( 'No Dash Dolphin API key is configured.', 'dash-dolphin' ) );
		}

		// Distinct cache key per query string so /api-recent-requests?limit=5
		// and ?limit=10 do not collide.
		$cache_endpoint = $endpoint;
		if ( ! empty( $query ) ) {
			$cache_endpoint .= '?' . http_build_query( $query );
		}
		$cache_key = $this->cache_key( $cache_endpoint );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = trailingslashit( DD_API_BASE_URL ) . 'functions/v1/' . ltrim( $endpoint, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
				'User-Agent'    => 'DashDolphin-WP/' . DD_VERSION,
			),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error'] )
				? (string) $data['error']
				: sprintf( /* translators: %d HTTP status code */ __( 'Dash Dolphin API returned status %d.', 'dash-dolphin' ), $code );
			return new WP_Error( 'dd_http_' . $code, $message, array( 'status' => $code ) );
		}

		if ( null === $data ) {
			$data = array();
		}

		if ( $use_cache ) {
			set_transient( $cache_key, $data, self::CACHE_TTL );
		}

		return $data;
	}
}
