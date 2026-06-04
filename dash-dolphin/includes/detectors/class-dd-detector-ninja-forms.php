<?php
/**
 * Ninja Forms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Ninja_Forms
 *
 * Detects whether Ninja Forms is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Ninja_Forms extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return function_exists( 'Ninja_Forms' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Ninja Forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'ninja-forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — use Ninja_Forms()->form()->get_forms() to return form list.
		return array();
	}
}
