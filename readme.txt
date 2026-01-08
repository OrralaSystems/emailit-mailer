=== Emailit API WordPress Plugin ===
Contributors: orralasystems
Tags: email, smtp, mailer, emailit, api
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WordPress plugin that replaces wp_mail() to send all emails through the EmailIT API with Bearer authentication.

== Description ==

**Emailit API WordPress Plugin** is an expert-level WordPress plugin developed by Orrala Systems that completely replaces the native WordPress email function (wp_mail) to send all emails through the EmailIT API.

= Main Features =

* **Automatic wp_mail() replacement** - All WordPress emails are sent through EmailIT
* **Bearer authentication** - Secure connection using API Key
* **Complete settings panel** - Easy configuration from WordPress admin
* **Logging system** - Record of the last 100+ emails with status
* **Test email** - Verify configuration with one click
* **On/off toggle** - Enable/disable without deactivating the plugin
* **Attachment support** - Send attachments without issues
* **Secure** - Nonces, sanitization and validation throughout the code

= Why use EmailIT? =

* Improves email deliverability
* Avoids spam filters
* Detailed sending statistics
* Easy integration

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Active EmailIT account with API Key

== Installation ==

1. Download the plugin
2. Upload the `emailit-mailer` folder to `/wp-content/plugins/`
3. Activate the plugin from **Plugins → Installed Plugins**
4. Go to **Settings → EmailIT Mailer** to configure

= Initial Configuration =

1. Obtain your API Key from [EmailIT](https://emailit.com)
2. Enter your API Key in the settings
3. Configure the sender email and name
4. Send a test email to verify the configuration

== Frequently Asked Questions ==

= Where do I get my EmailIT API Key? =

Log in to your EmailIT account at emailit.com and go to the credentials panel to generate a new API type credential.

= Can I use any sender email? =

The sender email should be verified in your EmailIT account for better deliverability.

= What happens if I disable the plugin? =

When you disable the plugin toggle, WordPress will use its default sending method (PHPMailer).

= Are logs stored permanently? =

No, logs are automatically deleted according to your settings (default: 30 days, maximum 100 entries).

== Changelog ==

= 1.2.0 =
* Plugin fully localized to English (US)
* Improved code documentation
* Various stability improvements

= 1.1.0 =
* Added toggle to enable/disable plugin without deactivating
* When disabled, WordPress uses its native sending method
* UI improvements in the settings panel

= 1.0.0 =
* Initial plugin version
* Complete EmailIT API integration
* Full settings panel
* Logging system
* Test email functionality
* Attachment support
* Automatic log cleanup

== Upgrade Notice ==

= 1.2.0 =
This version includes English localization and stability improvements.

= 1.1.0 =
Adds ability to enable/disable the plugin without deactivating it.
