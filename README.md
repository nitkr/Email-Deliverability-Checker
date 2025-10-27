# Email-Deliverability-Checker
 A WordPress plugin to check email deliverability status, integrate with Site Health, send test emails, and more. Designed for future extensions like email logging.

=== Email Deliverability Checker ===
Contributors: Nithin K R
Tags: email, deliverability, spf, dmarc, smtp, site health
Requires at least: 5.0
Tested up to: 6.8.3
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular plugin to check email deliverability, integrate with Site Health, send test emails, and prepare for future features like email logging.

== Description ==

This plugin helps diagnose email sending issues in WordPress by checking DNS records (SPF, DMARC) and email provider configurations (via WP Mail SMTP). It adds checks to Site Health, provides a beautiful admin page for status overview, and a page to send test emails. Designed to be modular for easy extension.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/email-deliverability-checker` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the 'Email Checker' menu in the admin dashboard to use the features.

== Frequently Asked Questions ==

= Does this work with other SMTP plugins? =

Currently focused on WP Mail SMTP, but can be extended.

== Changelog ==

= 1.0.0 =
* Initial release.
