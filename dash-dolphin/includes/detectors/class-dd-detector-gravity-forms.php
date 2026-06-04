<?php
/**
 * Gravity Forms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Gravity_Forms
 *
 * Detects whether Gravity Forms is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Gravity_Forms extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'GFAPI' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Gravity Forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'gravity-forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — use GFAPI::get_forms() to return form list.
		return array();
	}
}
