# Changelog

All notable changes to this project will be documented here. The format is based on Keep a Changelog and this project adheres to Semantic Versioning.

## [0.2.1] - 2026-06-05

### Fixed
- Critical: staging build now registers the `dd_api_base_url` filter BEFORE `define('DD_API_BASE_URL', ...)`. In 0.2.0 the filter was registered inside `plugins_loaded`, which fires after the define, so staging builds were silently hitting production Supabase and 404ing on every endpoint.
- Setup tab: 'Visit support' link now points to `https://dashdolphin.com/help-support/` (was `/support`).
- Admin sidebar: top-level menu icon is now a 20x20 inline SVG instead of the full PNG logo, which WordPress was rendering at natural size and wrapping below the menu label.

### Changed
- Hero gradient retuned to the deeper violet palette (`#14082A`, `#4338CA`, `#7C3AED`) used on dashdolphin.com instead of the orange-pink-violet ramp.
- Dash Dolphin logo now appears as a subtle right-side watermark behind the hero, instead of as a foreground avatar.

## [0.1.0] - 2026-06-04

### Added
- Initial repository scaffold.
- Plugin bootstrap with WordPress plugin header.
- Eight form-detector skeletons: Gravity Forms, WPForms, Contact Form 7, Elementor, Fluent Forms, Forminator, Ninja Forms, Formidable Forms.
- WordPress.org readme.txt with required sections.
- PHPCS configuration for WordPress coding standards.
- PHPUnit configuration and a placeholder test.
- GitHub Actions lint and test workflows.
- Build script (bin/build-zip.sh) producing an upload-ready zip.
