=== Simple Scheduled Maintenance ===
Contributors: tulips
Tags: maintenance, scheduled maintenance, maintenance mode, multilingual, wpml, polylang, countdown
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin for scheduled maintenance mode with multi-language support, countdown timer, and flexible configuration.

== Description ==

Simple Scheduled Maintenance allows you to schedule maintenance windows for your WordPress site with support for multiple languages, brand-specific messages, and a beautiful countdown timer. The plugin automatically detects WPML or Polylang for language management, or allows manual language configuration.

**Key Features:**
* Scheduled maintenance windows with start/end dates and timezones
* Multi-language support (automatic detection via WPML/Polylang or manual configuration)
* Simplified UX for single-language sites (auto-configured)
* Rich text editor for maintenance messages with HTML support
* Optional countdown timer showing time until maintenance ends
* Custom maintenance image upload with toggle option
* Remove image functionality with AJAX (no page reload)
* Self-contained template (no external dependencies)
* Language-specific messages for each configured language
* Automatic language detection based on current site language
* Tabbed admin interface for easy management
* Email notifications with customizable subject and message (WordPress native wp_mail)
* Debug information tab for troubleshooting
* Plugin-specific cache clearing (doesn't affect site-wide cache)
* Optimized performance: Zero resource usage when disabled or maintenance window ended
* Settings link in plugin list for quick access
* Success notifications with timestamps and auto-dismiss

== Installation ==

1. Upload the `simple-scheduled-maintenance` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Maintenance Mode to configure
4. (Multilingual sites only) On first activation, you'll be prompted to configure languages

== Configuration ==

**First-Time Setup:**

For Multilingual Sites:
When you first activate the plugin, you'll see a language configuration modal:
* Automatic Detection: If WPML or Polylang is installed, select this to automatically detect all configured languages
* Manual Configuration: Add languages manually with codes (e.g., 'en', 'sv', 'no') and names (e.g., 'English', 'Swedish')

For Single-Language Sites:
The plugin automatically configures English - no setup needed! Just start configuring your maintenance settings.

**General Settings:**
* Enable Maintenance Mode: Toggle maintenance mode on/off
* Start Date/Time: When maintenance should begin
* End Date/Time: When maintenance should end
* Time Zone: Select your timezone (important for accurate scheduling)
* Show Maintenance Image: Toggle to show/hide the maintenance image
* Maintenance Image: Upload a custom image for the maintenance page (with remove button)
* Show Countdown: Enable/disable the countdown timer

**Email Notifications:**
* Enable Email Notifications: Toggle email alerts on/off
* Email Addresses: Comma-separated list of recipients (defaults to admin email)
* Maintenance Start Email: Customize subject and message for start notifications
* Maintenance End Email: Customize subject and message for end notifications
* Placeholders: Use {site_name}, {site_url}, {start_time}, {end_time}, {duration}, {timezone} in messages
* Uses WordPress native wp_mail() function for reliable email delivery

**Language-Specific Messages:**
* Default Language Tab: Set default messages that will be used as fallback
* Language Tabs: Configure heading and description for each language
* All descriptions support rich text formatting, images, and HTML

**Reconfigure Languages:**
Click the "Reconfigure Languages" button (multilingual sites only) to reset language configuration and choose a different method (automatic or manual). A confirmation modal will appear before proceeding.

**Reset All Plugin Data:**
Use the "Reset Plugin Data" section to permanently delete all plugin settings, configurations, and uploaded files. A confirmation modal will appear to prevent accidental deletions. This action cannot be undone.

== Usage ==

**Setting Up Maintenance:**
1. Navigate to Settings → Maintenance Mode
2. Go to the General Settings tab
3. Enable maintenance mode
4. Set your start and end dates/times
5. Select your timezone
6. (Optional) Toggle maintenance image display on/off
7. (Optional) Upload a custom maintenance image (or use default 404.svg)
8. (Optional) Enable countdown timer
9. Configure messages in the Default Language tab
10. Configure language-specific messages in other language tabs
11. Click Save Settings

**How It Works:**
The plugin automatically shows the maintenance page when:
* Maintenance mode is enabled
* Current time is between start and end times
* User is not logged in as administrator or editor

The maintenance page will:
* Automatically detect the current language
* Show language-specific messages if configured
* Fall back to default messages if language-specific not found
* Display countdown timer (if enabled)
* Show custom maintenance image

**Cache Management:**
The plugin only clears its own configuration cache, not site-wide cache. This ensures:
* Maintenance settings update immediately
* Other site cache remains unaffected
* Better performance

== Frequently Asked Questions ==

= How does the scheduler work? =

The plugin uses WordPress's `template_redirect` hook to check maintenance status on every page load. No cron jobs or external schedulers needed - it works automatically.

**Performance Optimizations:**
* Conditional hook attach: When "Enable Maintenance Mode" is OFF, the frontend `template_redirect` hook is not attached at all
* Zero resource usage when disabled: If "Enable Maintenance Mode" is unchecked, the plugin exits immediately with only 1 database query
* Zero resource usage when window ended: Once maintenance window ends, a cached transient prevents all date parsing and checks
* Early exits: All functions check enabled/ended status first before any processing
* No background checks: The plugin only runs checks when a page is requested, not in the background

= Can I use it without WPML/Polylang? =

Yes! You can manually configure languages. The plugin will work with any number of languages you configure.

= What happens if I deactivate the plugin? =

The maintenance page will stop showing, and your configuration is preserved. When you reactivate, your settings remain intact.

= Can I remove all plugin data? =

Yes, there's a "Remove All Plugin Data" section at the bottom of the settings page. This will permanently delete all settings and configurations.

= Does it clear site-wide cache? =

No. The plugin only clears its own configuration cache to ensure settings update properly. It does not affect your site's overall cache.

= Does it run background checks when disabled? =

No. When maintenance mode is disabled or the maintenance window has ended, the plugin:
* Exits immediately with minimal database queries (1 query when disabled, 1 transient check when ended)
* Does not parse dates or perform timezone calculations
* Does not send emails or perform any background processing
* Uses cached transients to prevent redundant checks when window has ended
* Hooks are registered (WordPress requirement) but exit immediately on first check

= What happens when I delete the plugin? =

When you delete the plugin from WordPress, all plugin data (options, settings, configurations, transients, and uploaded files) will be automatically removed from the database. This ensures a clean uninstall.

== Screenshots ==

1. Language Configuration Modal
2. General Settings Tab
3. Default Language Tab with Rich Text Editor
4. Language-Specific Tabs
5. Debug Information Tab
6. Maintenance Page Frontend

== Changelog ==

= 2.6 =
* Admin preview: Preview maintenance page anytime (when “Enable Maintenance Mode” is enabled) via `?ssm_preview=1` (admin/editor only)
* Performance: Faster during active maintenance window using cached active state (reduces DateTime parsing on every request)
* Performance: Maintenance page renders as a minimal response (skips wp_head/wp_footer) to avoid heavy theme/plugin assets
* Cleanup: Removed leftover debug output from settings page

= 2.5 =
* Added Email Notifications feature with customizable subject and message templates
* New Email Notifications tab in admin settings
* Email alerts when maintenance mode starts and ends
* Placeholder support in email templates: {site_name}, {site_url}, {start_time}, {end_time}, {duration}, {timezone}
* Uses WordPress native wp_mail() function for reliable email delivery
* Major performance optimizations: Zero resource usage when disabled or window ended
* Early exit optimizations: All functions check enabled/ended status before processing
* Cached window status: Transient caching prevents redundant date parsing when window ended
* Optimized email functions: Check maintenance status before processing
* Improved code comments explaining performance optimizations

= 2.4 =
* Simplified UX for single-language sites - auto-configure English, no manual config needed
* Added Settings link in plugin list for quick access
* Success messages now show timestamps and auto-dismiss after 5 seconds
* Improved language code validation with auto-trimming of whitespace
* Enhanced reconfigure modal with better confirmation dialog (replaced JS alert)
* Custom reset confirmation modal for data deletion (replaced JS alert)
* Fixed form validation issues with hidden required fields
* Improved cache management for language configurations
* Comprehensive data deletion on reset and plugin uninstall
* Code cleanup and optimization

= 2.3 =
* Refactored to use separate template.php file (self-contained, no CDN dependencies)
* Added image toggle option (show/hide maintenance image)
* Added AJAX image removal functionality (no page reload)
* Changed default image to local 404.svg file
* Improved responsive design with custom CSS
* Added plugin-specific cache clearing (doesn't affect site-wide cache)
* Improved error handling for maintenance page
* Added activation/deactivation hooks
* Added data removal option
* Improved language configuration flow

= 2.2 =
* Added flexible language configuration
* Removed brand-specific messages
* Improved manual language setup

= 2.1 =
* Added countdown timer option
* Removed custom HTML field (using description with rich text instead)
* Added tabbed interface
* Improved multilingual detection

= 2.0 =
* Initial release with multi-language support
* Brand-specific messages
* Timezone handling improvements

== Upgrade Notice ==

= 2.6 =
Admin preview anytime (when enabled) plus faster maintenance-page performance during active windows.

= 2.5 =
New email notifications feature! Get alerts when maintenance starts/ends. Major performance improvements - zero resource usage when disabled or window ended. Customizable email templates with placeholders.

= 2.4 =
Improved UX for single-language sites. Better notification system with timestamps. Professional modal dialogs. Comprehensive data deletion on reset and uninstall.

= 2.3 =
Plugin-specific cache clearing only - no impact on site-wide cache. Improved error handling.

= 2.2 =
Improved language configuration. More flexible setup options.

= 2.1 =
New countdown timer feature. Improved admin interface with tabs.

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.0 or higher
* (Optional) WPML or Polylang for automatic language detection

== Support ==

For issues, feature requests, or questions, please contact the plugin author.

== Credits ==

Developed by Tulips

