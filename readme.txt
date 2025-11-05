=== Simple Scheduled Maintenance ===
Contributors: tulips
Tags: maintenance, scheduled maintenance, maintenance mode, multilingual, wpml, polylang, countdown
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 2.4
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
* Debug information tab for troubleshooting
* Plugin-specific cache clearing (doesn't affect site-wide cache)
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

= Can I use it without WPML/Polylang? =

Yes! You can manually configure languages. The plugin will work with any number of languages you configure.

= What happens if I deactivate the plugin? =

The maintenance page will stop showing, and your configuration is preserved. When you reactivate, your settings remain intact.

= Can I remove all plugin data? =

Yes, there's a "Remove All Plugin Data" section at the bottom of the settings page. This will permanently delete all settings and configurations.

= Does it clear site-wide cache? =

No. The plugin only clears its own configuration cache to ensure settings update properly. It does not affect your site's overall cache.

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

