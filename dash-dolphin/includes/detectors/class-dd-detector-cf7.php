<?php
/**
 * Contact Form 7 detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_CF7
 *
 * Detects whether Contact Form 7 is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_CF7 extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'WPCF7' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Contact Form 7';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'contact-form-7';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — query WPCF7_ContactForm::find() to return form list.
		return array();
	}
}
