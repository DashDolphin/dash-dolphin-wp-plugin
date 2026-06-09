=== SMS Notifications for WordPress Forms: Dash Dolphin ===
Contributors: dashdolphin, caboodlemedia
Tags: sms, form notifications, sms alerts, contact form sms, lead alerts
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SMS notifications for WordPress form submissions. Get instant text alerts for Contact Form 7, Gravity Forms, WPForms, Elementor Forms, and more.

== Description ==

Dash Dolphin sends you an SMS the moment someone submits a form on your WordPress site, so you can respond before your competitors do.

**78% of customers hire the first business that responds.** With Dash Dolphin you get a text in seconds, not hours, with a smart inquiry summary that surfaces the lead's name, intent, and contact info, not raw form fields.

= Works with the WordPress form plugins you already use =

Dash Dolphin connects to your existing forms with zero changes to your form setup or styling. Supported form plugins:

* Contact Form 7
* Gravity Forms
* WPForms
* Elementor Forms
* Fluent Forms
* Forminator
* Ninja Forms
* Formidable Forms

You paste a connection address into the BCC field of your form's existing admin notification. That's it. No new field mappings, no new shortcodes, no new form to rebuild.

= What you get =

* **Instant SMS notifications** for every form submission on your site
* **Smart inquiry summaries** that pull the lead's name, intent, and contact info to the top of every alert
* **Smart filtering** that routes only the submissions you care about, so spam and junk requests stay out of your phone
* **Email alerts** as a built-in fallback when SMS is not available
* **Multi-form, multi-site** support: connect any number of WordPress sites and form plugins to one Dash Dolphin account
* **No data leaves your site** until you explicitly paste a connection address into a form

= Why response speed matters =

Service business leads do not wait. Independent research consistently shows that the business that responds first wins the job, and that response windows measured in minutes outperform response windows measured in hours by an order of magnitude. Dash Dolphin exists to close that gap for small service businesses that cannot watch an inbox all day.

= Plugin scope =

This plugin is intentionally focused. It connects WordPress forms to Dash Dolphin. It does not embed the dashboard, run billing inside WordPress, modify your forms, or send marketing messages. Manage your inquiries, billing, and team in the Dash Dolphin web dashboard at app.dashdolphin.com.

= Requirements =

A Dash Dolphin account is required. Sign up at https://dashdolphin.com.

== Installation ==

1. Install the plugin from your WordPress admin via Plugins > Add New, search for "Dash Dolphin", and click Install Now. Or upload the `dash-dolphin` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in your WordPress admin.
3. In your WordPress admin sidebar, click Dash Dolphin > License.
4. Paste your Dash Dolphin API key. You can create one at https://app.dashdolphin.com/api-keys.
5. Visit Dash Dolphin > Setup, copy a connection address, and paste it into the BCC field of any form's admin notification email. Save the form.
6. Submit a test entry. You should receive an SMS within seconds.

== Frequently Asked Questions ==

= Do I need a Dash Dolphin account? =

Yes. A Dash Dolphin account is required for the plugin to send any alerts. You can sign up at https://dashdolphin.com. The plugin transmits no data until you save an API key in the License screen.

= Which form plugins are supported? =

Contact Form 7, Gravity Forms, WPForms, Elementor Forms, Fluent Forms, Forminator, Ninja Forms, and Formidable Forms. If you use a different form plugin, contact support at https://dashdolphin.com/help-support and we will evaluate adding it.

= Will this modify my forms? =

No. Dash Dolphin does not edit your form fields, validation, styling, or behavior. It receives a copy of admin notification emails via the BCC address you paste in. Your forms keep working exactly as before.

= How fast are the SMS alerts? =

Most alerts arrive within 5 to 15 seconds of form submission, depending on your carrier and the form plugin's email send latency. Dash Dolphin processes the submission, generates the smart summary, and dispatches the SMS through Twilio.

= Does the plugin send any data before I configure it? =

No. Until you save an API key, the plugin makes zero outbound requests to Dash Dolphin. Once an API key is configured, the only data transmitted is what arrives via the BCC connection address you paste into your form notifications.

= Can I connect multiple WordPress sites? =

Yes. Each site runs its own copy of the plugin and uses its own API key. Multiple sites and multiple form plugins can feed into one Dash Dolphin account.

= How do I rotate or revoke my API key? =

Manage your API keys at https://app.dashdolphin.com/api-keys. Save the new key in your WordPress admin under Dash Dolphin > License. The plugin clears its cached account info automatically when the key changes.

= Does Dash Dolphin work with WooCommerce order notifications? =

The current focus is contact and lead-capture forms. WooCommerce order notifications are not yet a first-class supported source. Contact support if this is on your roadmap.

= I clicked View details and got a 404. Why? =

That happens when your dashboard browser session is signed into a different Dash Dolphin account than the one this WordPress site is connected to. The plugin now shows the account the inquiry belongs to before you click. If you still hit a 404, open https://app.dashdolphin.com, switch accounts, then click View details again.

== Screenshots ==

1. The Dash Dolphin Dashboard tab in WordPress: recent inquiries, smart summaries, and SMS delivery status at a glance.
2. The Setup tab: paste a single BCC address into any form to wire it up.
3. The Setup tab: step-by-step walkthroughs for each supported form plugin, with the platforms you actually use auto-detected and surfaced first.
4. A sample SMS alert: lead name, intent, and contact info, delivered in seconds.

== Changelog ==

= 0.3.2 =
* New: Renamed menu items for clarity. The wiring page is now "Setup" (where you grab connection addresses for your forms) and the walkthrough page is now "Integrations" (step-by-step videos for each form plugin).
* New: Per-row Walkthrough links on the Setup table jump straight to the matching Integrations walkthrough for that form plugin.
* New: Setup page cross-links to Integrations from the intro copy when a step-by-step is helpful.
* New: Color Dash Dolphin menu icon in the WordPress admin sidebar.
* Improved: Walkthrough video player now uses a 16:9 aspect ratio so it scales correctly on wide screens instead of stretching.
* Improved: Hero header on every plugin page now leads with the Dash Dolphin brand mark and eyebrow.
* Fixed: Outbound links from Recent inquiries, Connection addresses, and the empty-state CTA now route to live dashboard routes instead of 404 pages.
* Compatibility: Old "dash-dolphin-connections" admin URL automatically 301-redirects to "dash-dolphin-setup" so bookmarks keep working.

= 0.3.0 =
* New: Speed-of-response value-prop panel on the Dashboard tab with proof points from dashdolphin.com.
* New: Connection-address links in alerts now indicate which Dash Dolphin account they belong to so View details never opens a stale account.
* New: Bundled official Dash Dolphin SVG logo for crisp display on Retina screens.
* Improved: Recent inquiries and connections tables restyled to match the native WordPress 7.x admin list-table look (borders, row heights, font weight).
* Improved: "SMS sent" badge no longer wraps onto two lines on narrow screens.
* Improved: Connections table cells vertically centered.
* Improved: Outbound-link copy clarified ("Open in Dash Dolphin dashboard" with external-link affordance) so it's obvious clicks leave WordPress.
* Improved: readme.txt rewritten for WordPress.org plugin directory search ranking on terms like "sms form notifications," "contact form sms alerts," and "lead alerts."

= 0.2.1 =
* Fixed: Plugin crashed when staging filter ran before define() (404 on activate).
* Fixed: Visit support link now points to /help-support/ instead of a stale URL.
* Improved: Sidebar menu icon swapped for a 20x20 base64 SVG so WordPress doesn't render the full logo at native size.
* Improved: Brand gradient updated to the deeper violet used on dashdolphin.com.

= 0.2.0 =
* Initial branded admin shell: hero header, two-column layout with right-rail marketing cards, account chip, plan/status pills.
* Setup tab with Guidde walkthroughs, auto-filtered to detected form platforms.

= 0.1.0 =
* Initial development scaffold.

== Upgrade Notice ==

= 0.3.2 =
Clearer menu labels (Setup for connection addresses, Integrations for walkthroughs), per-row walkthrough links, color sidebar icon, fixed video aspect ratio, and corrected outbound dashboard links. Safe upgrade. Old bookmarks redirect automatically.

= 0.3.0 =
Faster setup, cleaner UI, and clearer messaging about which Dash Dolphin account each inquiry belongs to. Safe upgrade.

== Privacy ==

This plugin transmits data to Dash Dolphin only after you have explicitly saved an API key in the plugin settings. Data transmitted is limited to form submission content that arrives at your BCC connection address. No data is collected or transmitted before an API key is configured.

For full details, see the Dash Dolphin privacy policy at https://dashdolphin.com/privacy.
