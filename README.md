# Dash Dolphin: SMS and Notification Alerts for WordPress Forms

Developer and internal documentation for the `dash-dolphin` WordPress plugin repository.

---

## Purpose

This plugin connects WordPress forms to a Dash Dolphin account so site owners receive SMS and email alerts on every form submission. It supports smart inquiry summaries and smart filtering across eight of the most popular WordPress form plugins.

---

## Repo Layout

```
dash-dolphin-wp-plugin/
├── README.md                    # This file
├── LICENSE                      # GPL v2
├── CHANGELOG.md                 # Keep-a-Changelog format
├── composer.json                # Dev dependencies (PHPUnit, PHPCS/WPCS)
├── phpcs.xml.dist               # WordPress coding standards config
├── phpunit.xml.dist             # PHPUnit config
├── .github/
│   └── workflows/
│       ├── lint.yml             # PHPCS lint on push and PR
│       └── test.yml             # PHPUnit tests on push and PR
├── bin/
│   ├── build-zip.sh             # Produces dash-dolphin.zip for upload
│   └── deploy-svn.sh            # Placeholder: SVN deploy (TBD)
└── dash-dolphin/                # The installable plugin folder
    ├── dash-dolphin.php         # Plugin bootstrap and header
    ├── readme.txt               # WordPress.org listing readme
    ├── uninstall.php            # Cleanup on uninstall
    ├── includes/
    │   ├── class-dd-plugin.php
    │   ├── class-dd-api-client.php
    │   ├── class-dd-settings-page.php
    │   ├── class-dd-form-detector.php
    │   ├── class-dd-activity-log.php
    │   └── detectors/
    │       ├── class-dd-detector-gravity-forms.php
    │       ├── class-dd-detector-wpforms.php
    │       ├── class-dd-detector-cf7.php
    │       ├── class-dd-detector-elementor.php
    │       ├── class-dd-detector-fluent-forms.php
    │       ├── class-dd-detector-forminator.php
    │       ├── class-dd-detector-ninja-forms.php
    │       └── class-dd-detector-formidable.php
    ├── assets/
    │   ├── css/admin.css
    │   ├── js/admin.js
    │   └── images/.gitkeep
    ├── languages/.gitkeep
    └── tests/
        ├── bootstrap.php
        └── test-form-detector.php
```

---

## Local Dev Setup

### Prerequisites

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/)
- A local WordPress install (e.g., [LocalWP](https://localwp.com/), DDEV, or Lando)

### Steps

1. Clone this repo:
   ```bash
   git clone <repo-url> dash-dolphin-wp-plugin
   cd dash-dolphin-wp-plugin
   ```

2. Install dev dependencies:
   ```bash
   composer install
   ```

3. Symlink or copy the plugin folder into your local WordPress install:
   ```bash
   ln -s /path/to/dash-dolphin-wp-plugin/dash-dolphin /path/to/wordpress/wp-content/plugins/dash-dolphin
   ```

4. Activate the plugin in your WordPress admin or via WP-CLI:
   ```bash
   wp plugin activate dash-dolphin
   ```

5. Run PHPCS lint:
   ```bash
   composer lint
   ```

6. Run PHPUnit tests:
   ```bash
   composer test
   ```

7. Build the upload zip:
   ```bash
   bash bin/build-zip.sh
   ```

---

## Release Process

See `bin/deploy-svn.sh` for the eventual WordPress.org SVN deploy workflow. This is a placeholder as of v0.1.0 and will be completed when the plugin is ready for public release.

For a full build and release plan, refer to `dash_dolphin_wp_plugin_plan.md` (local workspace document).

---

## Brand Voice Rules

All copy in this plugin, including settings labels, admin notices, and readme text, must follow these rules:

1. "Dash Dolphin" is always two words, exact case.
2. No exclamation points anywhere.
3. No em dashes anywhere. Use colons or commas instead.
4. No emojis.
5. Avoid the word "AI". Use "smart inquiry summaries" or "smart filtering" instead.
6. Lead with outcomes, not features.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
