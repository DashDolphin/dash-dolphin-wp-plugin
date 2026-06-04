<?php
/**
 * Elementor Forms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Elementor
 *
 * Detects whether Elementor (Pro) is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Elementor extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return (bool) did_action( 'elementor/loaded' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Elementor Forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'elementor';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — query posts with Elementor form widgets to return form list.
		return array();
	}
}
