<?php
/**
 * Fluent Forms detector.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Detector_Fluent_Forms
 *
 * Detects whether Fluent Forms is active and provides a list of its forms.
 *
 * @since 0.1.0
 */
class DD_Detector_Fluent_Forms extends DD_Form_Detector {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return defined( 'FLUENTFORM' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return 'Fluent Forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'fluent-forms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_forms(): array {
		// TODO: implement — query FluentForm\App\Models\Form to return form list.
		return array();
	}
}
