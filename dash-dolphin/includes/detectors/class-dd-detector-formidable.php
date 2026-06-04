<?php
/**
 * Formidable Forms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Formidable
 *
 * Detects whether Formidable Forms is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Formidable extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'FrmForm' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Formidable Forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'formidable';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — use FrmForm::getAll() to return form list.
		return array();
	}
}
