# Simple Scheduled Maintenance

A powerful WordPress plugin for scheduled maintenance mode with multi-language support, countdown timer, and flexible configuration options.

## Description

Simple Scheduled Maintenance allows you to schedule maintenance windows for your WordPress site with support for multiple languages, brand-specific messages, and a beautiful countdown timer. The plugin automatically detects WPML or Polylang for language management, or allows manual language configuration.

**Key Features:**
- ✅ Scheduled maintenance windows with start/end dates and timezones
- ✅ Multi-language support (automatic detection via WPML/Polylang or manual configuration)
- ✅ Rich text editor for maintenance messages with HTML support
- ✅ Optional countdown timer showing time until maintenance ends
- ✅ Custom maintenance image upload
- ✅ Language-specific messages for each configured language
- ✅ Automatic language detection based on current site language
- ✅ Tabbed admin interface for easy management
- ✅ Debug information tab for troubleshooting
- ✅ Plugin-specific cache clearing (doesn't affect site-wide cache)

## Installation

1. Upload the `simple-scheduled-maintenance` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Maintenance Mode to configure
4. On first activation, you'll be prompted to configure languages

## Configuration

### First-Time Setup

When you first activate the plugin, you'll see a language configuration modal:

1. **Automatic Detection**: If WPML or Polylang is installed, select this to automatically detect all configured languages
2. **Manual Configuration**: Add languages manually with codes (e.g., 'en', 'sv', 'no') and names (e.g., 'English', 'Swedish')

### General Settings

- **Enable Maintenance Mode**: Toggle maintenance mode on/off
- **Start Date/Time**: When maintenance should begin
- **End Date/Time**: When maintenance should end
- **Time Zone**: Select your timezone (important for accurate scheduling)
- **Maintenance Image**: Upload a custom image for the maintenance page
- **Show Countdown**: Enable/disable the countdown timer

### Language-Specific Messages

- **Default Language Tab**: Set default messages that will be used as fallback
- **Language Tabs**: Configure heading and description for each language
- All descriptions support rich text formatting, images, and HTML

### Reconfigure Languages

Click the "Reconfigure Languages" button to reset language configuration and choose a different method (automatic or manual).

## Usage

### Setting Up Maintenance

1. Navigate to **Settings → Maintenance Mode**
2. Go to the **General Settings** tab
3. Enable maintenance mode
4. Set your start and end dates/times
5. Select your timezone
6. (Optional) Upload a maintenance image
7. (Optional) Enable countdown timer
8. Configure messages in the **Default Language** tab
9. Configure language-specific messages in other language tabs
10. Click **Save Settings**

### How It Works

The plugin automatically shows the maintenance page when:
- Maintenance mode is enabled
- Current time is between start and end times
- User is not logged in as administrator or editor

The maintenance page will:
- Automatically detect the current language
- Show language-specific messages if configured
- Fall back to default messages if language-specific not found
- Display countdown timer (if enabled)
- Show custom maintenance image

### Cache Management

The plugin only clears its own configuration cache, not site-wide cache. This ensures:
- Maintenance settings update immediately
- Other site cache remains unaffected
- Better performance

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- (Optional) WPML or Polylang for automatic language detection

## Frequently Asked Questions

### How does the scheduler work?

The plugin uses WordPress's `template_redirect` hook to check maintenance status on every page load. No cron jobs or external schedulers needed - it works automatically.

### Can I use it without WPML/Polylang?

Yes! You can manually configure languages. The plugin will work with any number of languages you configure.

### What happens if I deactivate the plugin?

The maintenance page will stop showing, and your configuration is preserved. When you reactivate, your settings remain intact.

### Can I remove all plugin data?

Yes, there's a "Remove All Plugin Data" section at the bottom of the settings page. This will permanently delete all settings and configurations.

### Does it clear site-wide cache?

No. The plugin only clears its own configuration cache to ensure settings update properly. It does not affect your site's overall cache.

## Support

For issues, feature requests, or questions, please contact the plugin author.

## Changelog

### 2.3
- Added plugin-specific cache clearing (doesn't affect site-wide cache)
- Improved error handling for maintenance page
- Added activation/deactivation hooks
- Added data removal option
- Improved language configuration flow

### 2.2
- Added flexible language configuration
- Removed brand-specific messages
- Improved manual language setup

### 2.1
- Added countdown timer option
- Removed custom HTML field (using description with rich text instead)
- Added tabbed interface
- Improved multilingual detection

### 2.0
- Initial release with multi-language support
- Brand-specific messages
- Timezone handling improvements

## Credits

Developed by Tulips

## License

This plugin is proprietary software. All rights reserved.

