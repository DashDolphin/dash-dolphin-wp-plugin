<?php
/**
 * WPForms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_WPForms
 *
 * Detects whether WPForms is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_WPForms extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return function_exists( 'wpforms' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'WPForms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'wpforms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — query wpforms()->form->get() to return form list.
		return array();
	}
}
