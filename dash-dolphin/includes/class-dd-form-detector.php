<?php
/**
 * Abstract base class for form plugin detectors.
 *
 * @package DashDolphin
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DD_Form_Detector
 *
 * Each supported form plugin has a concrete subclass of this class. Subclasses
 * report whether the form plugin is active and provide a list of forms it has
 * defined on this site.
 *
 * @since 0.1.0
 */
abstract class DD_Form_Detector {

	/**
	 * Determine whether the host form plugin is installed and active.
	 *
	 * @return bool True if the form plugin is available.
	 * @since 0.1.0
	 */
	abstract public function is_active(): bool;

	/**
	 * Return the human-readable label for this form plugin.
	 *
	 * Example: "Gravity Forms"
	 *
	 * @return string
	 * @since 0.1.0
	 */
	abstract public function get_label(): string;

	/**
	 * Return the integration slug used on dashdolphin.com.
	 *
	 * The full integration URL is https://dashdolphin.com/integrations/{slug}.
	 *
	 * @return string
	 * @since 0.1.0
	 */
	abstract public function get_slug(): string;

	/**
	 * Return all forms defined in the host form plugin.
	 *
	 * Each entry in the returned array must be an associative array with at
	 * least the keys 'id' and 'name'.
	 *
	 * @return array Array of form descriptors: [ ['id' => mixed, 'name' => string], ... ]
	 * @since 0.1.0
	 */
	abstract public function get_forms(): array;

	/**
	 * Return instances of all supported form detectors.
	 *
	 * @return DD_Form_Detector[]
	 * @since 0.1.0
	 */
	public static function get_all_detectors(): array {
		return array(
			new DD_Detector_Gravity_Forms(),
			new DD_Detector_WPForms(),
			new DD_Detector_CF7(),
			new DD_Detector_Elementor(),
			new DD_Detector_Fluent_Forms(),
			new DD_Detector_Forminator(),
			new DD_Detector_Ninja_Forms(),
			new DD_Detector_Formidable(),
		);
	}
}
