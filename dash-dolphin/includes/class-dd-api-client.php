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
 * @since 0.1.0
 */
class DD_API_Client {

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
	 * Send a form submission payload to the Dash Dolphin API.
	 *
	 * @param array $payload Submission data to forward.
	 * @return array|WP_Error Response body on success, WP_Error on failure.
	 * @since 0.1.0
	 */
	public function send_submission( array $payload ) {
		// TODO: implement HTTP POST to DD_API_BASE_URL . '/submissions'.
		return array();
	}

	/**
	 * Validate the stored API key against the Dash Dolphin API.
	 *
	 * @return bool True if the key is valid, false otherwise.
	 * @since 0.1.0
	 */
	public function validate_api_key(): bool {
		// TODO: implement API key validation request.
		return false;
	}
}
