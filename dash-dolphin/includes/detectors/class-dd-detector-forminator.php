<?php
/**
 * Forminator detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Forminator
 *
 * Detects whether Forminator is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Forminator extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'Forminator' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Forminator';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'forminator';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — use Forminator_API::get_forms() to return form list.
		return array();
	}
}
