<?php
/**
 * Plugin Name: Simple Scheduled Maintenance
 * Plugin URI: https://github.com/sagartulips/simple-scheduled-maintenance
 * Description: A powerful WordPress plugin for scheduled maintenance mode with multi-language support, countdown timer, and flexible configuration. Automatically detects WPML/Polylang or allows manual language setup.
 * Version: 2.6
 * Author: Tulips
 * Author URI: https://github.com/sagartulips
 * Developer: Sagar GC
 * Developer Email: sagar@tulipstechnlogies.com
 * Company: Tulips
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-scheduled-maintenance
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Network: false
 * 
 * @package SimpleScheduledMaintenance
 * @version 2.5
 * @author Sagar GC
 * @company Tulips
 * @email sagar@tulipstechnlogies.com
 * @copyright Copyright (c) 2024 Tulips
 * 
 * Features:
 * - Scheduled maintenance windows with start/end dates and timezones
 * - Multi-language support (automatic detection via WPML/Polylang or manual configuration)
 * - Rich text editor for maintenance messages with HTML support
 * - Optional countdown timer showing time until maintenance ends
 * - Custom maintenance image upload
 * - Language-specific messages for each configured language
 * - Automatic language detection based on current site language
 * - Tabbed admin interface for easy management
 * - Debug information tab for troubleshooting
 * - Plugin-specific cache clearing (doesn't affect site-wide cache)
 */

defined('ABSPATH') || exit;

define('SSM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSM_VERSION', '2.6');

// Register activation hook
register_activation_hook(__FILE__, 'ssm_plugin_activation');
register_deactivation_hook(__FILE__, 'ssm_plugin_deactivation');
register_uninstall_hook(__FILE__, 'ssm_plugin_uninstall');

/**
 * Plugin activation hook
 */
function ssm_plugin_activation() {
    // Clear any caches
    ssm_clear_cache();
    
    // Set flag to show configuration on first activation
    if (!get_option('ssm_languages_configured')) {
        update_option('ssm_plugin_just_activated', true);
        update_option('ssm_plugin_version', SSM_VERSION);
    }
    
    // Set default options if not exist
    if (!get_option('ssm_show_countdown')) {
        update_option('ssm_show_countdown', 1);
    }
    
    // Flush rewrite rules if needed
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
function ssm_plugin_deactivation() {
    // Clear caches on deactivation
    ssm_clear_cache();
    
    // Set flag for deactivation
    update_option('ssm_plugin_deactivated', true);
}

/**
 * Plugin uninstall hook
 * This runs when the plugin is deleted from WordPress
 * Deletes all plugin data from the database
 */
function ssm_plugin_uninstall() {
    // Get image URL before deleting options (to delete the file)
    $image_url = get_option('ssm_image', '');
    
    // Delete ALL plugin options from database using comprehensive SQL query
    // This catches ALL plugin-related options including:
    // - All main options (ssm_enabled, ssm_start_time, etc.)
    // - Language-specific options (ssm_heading_*, ssm_description_*, ssm_countdown_text_*)
    // - Any cached or dynamically created options
    global $wpdb;
    
    // Delete all options starting with 'ssm_' (catches everything)
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('ssm_') . '%'
    ));
    
    // Delete all transients related to the plugin
    // Transients are stored with prefix '_transient_' and '_transient_timeout_'
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_ssm_') . '%'
    ));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_ssm_') . '%'
    ));
    
    // Delete uploaded image file if it exists
    if (!empty($image_url) && strpos($image_url, content_url()) !== false) {
        // Convert URL to file path
        $image_path = str_replace(content_url(), WP_CONTENT_DIR, $image_url);
        if (file_exists($image_path)) {
            @unlink($image_path);
        }
    }
    
    // Clear all WordPress object cache entries related to plugin
    wp_cache_flush();
}

/**
 * Clear plugin-specific cache only (not site-wide cache)
 */
function ssm_clear_cache() {
    // Clear plugin-specific transients
    delete_transient('ssm_maintenance_status');
    delete_transient('ssm_window_ended');
    delete_transient('ssm_maintenance_active');
    
    // Clear plugin-specific object cache group if available
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('ssm');
    }
    
    // Clear plugin's cached options from WordPress object cache
    // This only clears our plugin's cached options, not site-wide cache
    wp_cache_delete('ssm_enabled', 'options');
    wp_cache_delete('ssm_start_time', 'options');
    wp_cache_delete('ssm_end_time', 'options');
    wp_cache_delete('ssm_timezone', 'options');
    wp_cache_delete('ssm_heading', 'options');
    wp_cache_delete('ssm_description', 'options');
    wp_cache_delete('ssm_image', 'options');
    wp_cache_delete('ssm_show_image', 'options');
    wp_cache_delete('ssm_show_countdown', 'options');
    wp_cache_delete('ssm_configured_languages', 'options');
    wp_cache_delete('ssm_default_language', 'options');
    wp_cache_delete('ssm_language_mode', 'options');
    wp_cache_delete('ssm_languages_configured', 'options');
    wp_cache_delete('ssm_plugin_just_activated', 'options');
    wp_cache_delete('ssm_plugin_deactivated', 'options');
    wp_cache_delete('ssm_plugin_version', 'options');
    
    // Clear all ssm_ prefixed options from WordPress object cache
    global $wpdb;
    $options = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ssm_%'");
    foreach ($options as $option) {
        wp_cache_delete($option, 'options');
    }
    
    // Clear language-specific cached options
    $languages = ssm_get_available_languages();
    foreach ($languages as $lang_code => $lang_name) {
        wp_cache_delete("ssm_heading_{$lang_code}", 'options');
        wp_cache_delete("ssm_description_{$lang_code}", 'options');
    }
}

/**
 * Send email notification when maintenance mode starts
 * Optimized: Early checks to prevent unnecessary processing
 */
function ssm_send_maintenance_start_email() {
    // Early return if maintenance mode is disabled
    if (!get_option('ssm_enabled')) {
        return; // Don't send emails if maintenance mode is disabled
    }
    
    // Early return if email notifications are disabled
    $email_enabled = get_option('ssm_email_notifications', 0);
    if (!$email_enabled) {
        return;
    }
    
    $email_addresses = get_option('ssm_email_addresses', '');
    // Fallback to admin email if no addresses configured
    if (empty($email_addresses)) {
        $email_addresses = get_option('admin_email', '');
        if (empty($email_addresses)) {
            return;
        }
    }
    
    // Check if we already sent notification for this maintenance window
    $start_time = get_option('ssm_start_time');
    $end_time = get_option('ssm_end_time');
    $notification_key = 'ssm_email_sent_' . md5($start_time . $end_time);
    
    if (get_transient($notification_key)) {
        return; // Already sent notification for this window
    }
    
    $emails = array_map('trim', explode(',', $email_addresses));
    $emails = array_filter($emails, 'is_email');
    
    if (empty($emails)) {
        return;
    }
    
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $timezone = get_option('ssm_timezone', get_option('timezone_string', 'UTC'));
    
    try {
        $tz = new DateTimeZone($timezone);
        $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start_time, $tz);
        if (!$start_dt) {
            $start_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $start_time), $tz);
        }
        $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_time, $tz);
        if (!$end_dt) {
            $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end_time), $tz);
        }
        
        if ($start_dt && $end_dt) {
            $start_formatted = $start_dt->format('Y-m-d H:i:s T');
            $end_formatted = $end_dt->format('Y-m-d H:i:s T');
            $duration = $start_dt->diff($end_dt);
            $duration_text = $duration->format('%h hours %i minutes');
        } else {
            $start_formatted = $start_time;
            $end_formatted = $end_time;
            $duration_text = 'Unknown';
        }
    } catch (Exception $e) {
        $start_formatted = $start_time;
        $end_formatted = $end_time;
        $duration_text = 'Unknown';
    }
    
    // Get custom subject and message, or use defaults
    $custom_subject = get_option('ssm_email_subject_start', '');
    $custom_message = get_option('ssm_email_message_start', '');
    
    if (!empty($custom_subject)) {
        $subject = str_replace(
            ['{site_name}', '{site_url}', '{start_time}', '{end_time}', '{duration}', '{timezone}'],
            [$site_name, $site_url, $start_formatted, $end_formatted, $duration_text, $timezone],
            $custom_subject
        );
    } else {
        $subject = sprintf('[%s] Maintenance Mode Activated', $site_name);
    }
    
    if (!empty($custom_message)) {
        $message = str_replace(
            ['{site_name}', '{site_url}', '{start_time}', '{end_time}', '{duration}', '{timezone}'],
            [$site_name, $site_url, $start_formatted, $end_formatted, $duration_text, $timezone],
            $custom_message
        );
        // Convert HTML to plain text if needed
        $message = wp_strip_all_tags($message);
    } else {
        $message = sprintf(
            "Maintenance mode has been ACTIVATED for %s\n\n" .
            "Site: %s\n" .
            "Start Time: %s\n" .
            "End Time: %s\n" .
            "Duration: %s\n" .
            "Timezone: %s\n\n" .
            "Your site is now showing a maintenance page to all visitors (except administrators and editors).\n\n" .
            "You can manage maintenance settings at: %s\n",
            $site_name,
            $site_url,
            $start_formatted,
            $end_formatted,
            $duration_text,
            $timezone,
            admin_url('options-general.php?page=ssm-settings')
        );
    }
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $from_email = get_option('admin_email');
    if ($from_email) {
        $headers[] = sprintf('From: %s <%s>', $site_name, $from_email);
    }
    
    $sent = wp_mail($emails, $subject, $message, $headers);
    
    if ($sent) {
        // Set transient to prevent duplicate emails (expires when maintenance ends + 1 hour)
        if ($end_dt) {
            $expiry = $end_dt->getTimestamp() - time() + 3600; // End time + 1 hour
            if ($expiry > 0) {
                set_transient($notification_key, true, $expiry);
            }
        }
    }
}

/**
 * Send email notification when maintenance mode ends
 * Optimized: Early checks to prevent unnecessary processing
 */
function ssm_send_maintenance_end_email() {
    // Early return if maintenance mode is disabled
    if (!get_option('ssm_enabled')) {
        return; // Don't send emails if maintenance mode is disabled
    }
    
    // Early return if email notifications are disabled
    $email_enabled = get_option('ssm_email_notifications', 0);
    if (!$email_enabled) {
        return;
    }
    
    $notify_on_end = get_option('ssm_email_notify_end', 0);
    if (!$notify_on_end) {
        return;
    }
    
    $email_addresses = get_option('ssm_email_addresses', '');
    // Fallback to admin email if no addresses configured
    if (empty($email_addresses)) {
        $email_addresses = get_option('admin_email', '');
        if (empty($email_addresses)) {
            return;
        }
    }
    
    // Check if we already sent end notification
    $end_notification_key = 'ssm_email_end_sent_' . md5(get_option('ssm_start_time') . get_option('ssm_end_time'));
    if (get_transient($end_notification_key)) {
        return;
    }
    
    $emails = array_map('trim', explode(',', $email_addresses));
    $emails = array_filter($emails, 'is_email');
    
    if (empty($emails)) {
        return;
    }
    
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    // Get custom subject and message, or use defaults
    $custom_subject = get_option('ssm_email_subject_end', '');
    $custom_message = get_option('ssm_email_message_end', '');
    
    if (!empty($custom_subject)) {
        $subject = str_replace(
            ['{site_name}', '{site_url}'],
            [$site_name, $site_url],
            $custom_subject
        );
    } else {
        $subject = sprintf('[%s] Maintenance Mode Completed', $site_name);
    }
    
    if (!empty($custom_message)) {
        $message = str_replace(
            ['{site_name}', '{site_url}'],
            [$site_name, $site_url],
            $custom_message
        );
        // Convert HTML to plain text if needed
        $message = wp_strip_all_tags($message);
    } else {
        $message = sprintf(
            "Maintenance mode has been COMPLETED for %s\n\n" .
            "Site: %s\n" .
            "Your site is now accessible to all visitors.\n\n" .
            "You can manage maintenance settings at: %s\n",
            $site_name,
            $site_url,
            admin_url('options-general.php?page=ssm-settings')
        );
    }
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $from_email = get_option('admin_email');
    if ($from_email) {
        $headers[] = sprintf('From: %s <%s>', $site_name, $from_email);
    }
    
    $sent = wp_mail($emails, $subject, $message, $headers);
    
    if ($sent) {
        // Set transient for 24 hours to prevent duplicate emails
        set_transient($end_notification_key, true, 24 * HOUR_IN_SECONDS);
    }
}

/**
 * Check if maintenance should be active (better error handling)
 * Optimized: Returns immediately if disabled or window ended to save resources
 */
function ssm_should_show_maintenance() {
    // Early return if maintenance mode is disabled - no further checks needed
    $enabled = get_option('ssm_enabled');
    if (!$enabled) {
        return false;
    }
    
    // Early return if window has ended (cached check - avoids date parsing)
    $window_ended = get_transient('ssm_window_ended');
    if ($window_ended === 'yes') {
        return false; // Window has ended - return immediately
    }
    
    $start = get_option('ssm_start_time');
    $end = get_option('ssm_end_time');
    
    if (!$start || !$end) {
        return false; // No dates configured - return immediately
    }
    
    $timezone = get_option('ssm_timezone', get_option('timezone_string', 'UTC'));
    
    try {
        $tz = new DateTimeZone($timezone);
        
        // Get current time in the specified timezone
        $current_time = new DateTime('now', $tz);
        
        // Parse the datetime-local format (stored as Y-m-d\TH:i)
        // datetime-local input stores without timezone, so we interpret it in the selected timezone
        $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start, $tz);
        $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz);
        
        // Try alternative formats if first attempt failed
        if (!$start_dt) {
            // Try with space instead of T
            $start_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $start), $tz);
        }
        if (!$start_dt) {
            // Try with seconds included
            $start_dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $start, $tz);
        }
        if (!$start_dt) {
            // Try Y-m-d H:i:s format
            $start_dt = DateTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $start), $tz);
        }
        
        if (!$end_dt) {
            // Try with space instead of T
            $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
        }
        if (!$end_dt) {
            // Try with seconds included
            $end_dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $end, $tz);
        }
        if (!$end_dt) {
            // Try Y-m-d H:i:s format
            $end_dt = DateTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $end), $tz);
        }
        
        if (!$start_dt || !$end_dt) {
            return false; // Invalid date format - return immediately
        }
        
        // Compare times in the same timezone
        $is_active = ($current_time >= $start_dt && $current_time <= $end_dt);
        
        return $is_active;
        
    } catch (Exception $e) {
        return false; // Error parsing dates - return false silently
    }
}

/**
 * Check if site has multilingual support
 */
function ssm_is_multilingual_active() {
    // WPML support
    if (defined('ICL_LANGUAGE_CODE') || function_exists('icl_get_languages')) {
        return true;
    }
    
    // Polylang support
    if (function_exists('pll_current_language') || function_exists('pll_languages_list')) {
        return true;
    }
    
    return false;
}

/**
 * Get configured languages (manual configuration when no multilingual plugin)
 */
function ssm_get_configured_languages() {
    // Clear cache to ensure we get fresh data
    wp_cache_delete('ssm_configured_languages', 'options');
    $configured = get_option('ssm_configured_languages', []);
    
    // If no manual configuration, return default English
    if (empty($configured)) {
        return ['en' => 'English'];
    }
    
    return $configured;
}

/**
 * Get current language code (WPML, Polylang, custom, or configured)
 */
function ssm_get_current_language() {
    // WPML support
    if (defined('ICL_LANGUAGE_CODE')) {
        return ICL_LANGUAGE_CODE;
    }
    
    // Polylang support
    if (function_exists('pll_current_language')) {
        $lang = pll_current_language();
        if ($lang) {
            return $lang;
        }
    }
    
    // Try to detect from locale
    $locale = get_locale();
    if ($locale) {
        // Extract language code from locale (e.g., 'sv_SE' -> 'sv')
        $lang_parts = explode('_', $locale);
        $detected_lang = strtolower($lang_parts[0]);
        
        // Check if this language is in our configured languages
        $configured_langs = ssm_get_configured_languages();
        if (isset($configured_langs[$detected_lang])) {
            return $detected_lang;
        }
    }
    
    // Get default language from configured languages
    $default_lang = get_option('ssm_default_language', 'en');
    $configured_langs = ssm_get_configured_languages();
    
    // If default language is in configured languages, use it
    if (isset($configured_langs[$default_lang])) {
        return $default_lang;
    }
    
    // Fallback to first configured language or 'en'
    if (!empty($configured_langs)) {
        $keys = array_keys($configured_langs);
        return $keys[0];
    }
    
    return 'en';
}

/**
 * Get all available languages (from plugin or manual config)
 */
function ssm_get_all_languages() {
    // If multilingual plugin is active, use it
    if (ssm_is_multilingual_active()) {
        if (function_exists('icl_get_languages')) {
            $wpml_langs = icl_get_languages('skip_missing=0');
            $languages = [];
            foreach ($wpml_langs as $lang) {
                $languages[$lang['code']] = $lang['native_name'];
            }
            return $languages;
        }
        
        if (function_exists('pll_languages_list')) {
            $pll_langs = pll_languages_list(['fields' => []]);
            $languages = [];
            foreach ($pll_langs as $lang) {
                $languages[$lang->slug] = $lang->name;
            }
            return $languages;
        }
    }
    
    // Otherwise use manually configured languages
    return ssm_get_configured_languages();
}

include_once SSM_PLUGIN_PATH . 'admin-settings.php';

// Add Settings link to plugin list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ssm_add_plugin_action_links');
function ssm_add_plugin_action_links($links) {
    // Add Settings link (appears for both active and inactive plugins)
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=ssm-settings')) . '">' . esc_html__('Settings', 'simple-scheduled-maintenance') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Get language-specific maintenance message
 */
function ssm_get_maintenance_message($key, $default, $lang = null) {
    if ($lang === null) {
        $lang = ssm_get_current_language();
    }
    
    // Try language-specific first
    $option_key = "ssm_{$key}_{$lang}";
    $value = get_option($option_key);
    
    if ($value) {
        // Remove any slashes that might have been added
        return stripslashes($value);
    }
    
    // Fallback to default (also check if default exists in options)
    $default_option = get_option("ssm_{$key}");
    if ($default_option) {
        return stripslashes($default_option);
    }
    
    return $default;
}

/**
 * Function to show the maintenance page
 * Optimized: Early returns to prevent unnecessary processing when disabled or ended
 * 
 * IMPORTANT: This function is called via WordPress hooks on every page request.
 * However, it exits immediately (with only 1 database query) when:
 * - Maintenance mode is disabled
 * - Maintenance window has ended (cached check)
 * 
 * NO background checking occurs - only on-demand checks when pages are requested.
 */
function ssm_show_maintenance_page() {
    // Preview mode (admin/editor only): allow previewing the maintenance page even when the time window isn't active.
    // Requirement: preview should only be available when maintenance mode is enabled.
    // IMPORTANT:
    // - Preview is ONLY allowed for logged-in admins/editors
    // - Preview should NOT send emails or set any "active/ended" transients
    // - Preview should NOT return 503 (so it doesn't pollute monitoring / logs)
    $preview_mode = isset($_GET['ssm_preview']) && $_GET['ssm_preview'] == '1';
    
    // CRITICAL: Check if maintenance mode is enabled FIRST.
    // Only 1 database query: get_option('ssm_enabled')
    $enabled = get_option('ssm_enabled');
    
    $can_preview = $enabled && $preview_mode && is_user_logged_in() && (current_user_can('administrator') || current_user_can('editor'));
    
    if (!$enabled) {
        return; // Exit immediately - maintenance mode disabled (no preview allowed)
    }
    
    // CRITICAL: Check if maintenance window has ended (cached check) - unless previewing.
    // Once window ends, this transient is cached for 24 hours - no repeated checks.
    $window_ended = get_transient('ssm_window_ended');
    if ($window_ended === 'yes' && !$can_preview) {
        return; // Exit immediately - window ended
    }
    
    // Skip for admin pages and AJAX requests
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }
    
    // Skip for login page
    if (isset($GLOBALS['pagenow']) && in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
        return;
    }
    
    // IMPORTANT: Allow admins and editors to bypass maintenance.
    // However, if they add ?ssm_preview=1 to the URL, they can preview the maintenance page.
    if (is_user_logged_in() && (current_user_can('administrator') || current_user_can('editor'))) {
        if (!$preview_mode) {
            return; // Admins/editors bypass maintenance unless preview mode
        }
    } else {
        // Non-admins are never allowed to preview.
        if ($preview_mode) {
            return;
        }
    }
    
    // Get cached active state first (avoids unnecessary date parsing if already checked).
    // When active, we store a transient with an expiry equal to the end timestamp,
    // so most requests during an active window can skip all DateTime parsing.
    $was_active = $can_preview ? false : get_transient('ssm_maintenance_active');
    
    // OPTIMIZATION: Fast-path for active window.
    $is_active = false;
    if (!$can_preview && $was_active) {
        if (is_array($was_active) && isset($was_active['end_ts'])) {
            // Safety: ensure end_ts hasn't passed (should be enforced by transient expiry).
            if (time() < (int) $was_active['end_ts']) {
                $is_active = true;
            } else {
                delete_transient('ssm_maintenance_active');
            }
        } else {
            // Back-compat (older transient value was boolean true)
            $is_active = true;
        }
    }
    
    // If not active via fast-path, compute status (date parsing).
    if (!$is_active && $window_ended !== 'yes') {
        // Only call ssm_should_show_maintenance() if window hasn't ended
        // This function now also checks window_ended internally for double safety
        $is_active = $can_preview ? false : ssm_should_show_maintenance();
    }
    
    // Check if maintenance window has ended (not just inactive, but actually ended)
    if (!$is_active && !$can_preview) {
        // Only check dates if window_ended transient doesn't exist (optimization)
        // This prevents redundant date parsing when we already know window ended
        if ($window_ended !== 'yes') {
            $start = get_option('ssm_start_time');
            $end = get_option('ssm_end_time');
            
            // Quick check: if we have dates, see if end time has passed
            if ($start && $end) {
                try {
                    $timezone = get_option('ssm_timezone', get_option('timezone_string', 'UTC'));
                    $tz = new DateTimeZone($timezone);
                    $current_time = new DateTime('now', $tz);
                    $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz);
                    if (!$end_dt) {
                        $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
                    }
                    
                    // If end time has passed, cache this result to skip future checks
                    if ($end_dt && $current_time > $end_dt) {
                        // Set transient for 24 hours - maintenance window has ended
                        set_transient('ssm_window_ended', 'yes', 24 * HOUR_IN_SECONDS);
                        
                        // Send end notification if needed (only if was previously active)
                        if ($was_active) {
                            ssm_send_maintenance_end_email();
                            delete_transient('ssm_maintenance_active');
                        }
                        
                        return; // Window has ended - exit immediately
                    }
                } catch (Exception $e) {
                    // Silent fail - continue with normal check
                }
            }
        }
        
        // Not active and not ended (hasn't started yet or no dates)
        return;
    }
    
    // Clear the "window ended" transient if we're active (new window started)
    // Do not touch this transient in preview mode.
    if (!$can_preview) {
        delete_transient('ssm_window_ended');
    }
    
    // Send email notification when maintenance becomes active
    if (!$can_preview && !$was_active) {
        ssm_send_maintenance_start_email();
        // Set transient to track active state (expires when maintenance ends)
        $end = get_option('ssm_end_time');
        if ($end) {
            try {
                $timezone = get_option('ssm_timezone', get_option('timezone_string', 'UTC'));
                $tz = new DateTimeZone($timezone);
                $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz);
                if (!$end_dt) {
                    $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
                }
                if ($end_dt) {
                    $expiry = $end_dt->getTimestamp() - time();
                    if ($expiry > 0) {
                        set_transient('ssm_maintenance_active', ['end_ts' => $end_dt->getTimestamp()], $expiry);
                    }
                }
            } catch (Exception $e) {
                // Silent fail
            }
        }
    }

    // Get options
    $timezone       = get_option('ssm_timezone', get_option('timezone_string', 'UTC'));
    $image          = get_option('ssm_image', '');
    if (empty($image)) {
        $image = SSM_PLUGIN_URL . '404.svg'; // Fallback to default local image
    }
    $show_image     = get_option('ssm_show_image', 1);
    $show_countdown = get_option('ssm_show_countdown', 1);
    $start          = get_option('ssm_start_time');
    $end            = get_option('ssm_end_time');
    
    // Get current language
    $current_lang = ssm_get_current_language();
    
    // Get language-specific messages with fallback
    $heading = ssm_get_maintenance_message('heading', 'Site Under Maintenance', $current_lang);
    // $desc    = ssm_get_maintenance_message('description', 'We\'re working hard to improve the user experience. Stay tuned!', $current_lang);
    // $countdown_text = ssm_get_maintenance_message('countdown_text', 'We\'ll be back in:', $current_lang);
    $desc    = ssm_get_maintenance_message('description', '', $current_lang);
    $countdown_text = ssm_get_maintenance_message('countdown_text', '', $current_lang);
    
    // Get timezone and dates for countdown
    try {
        $tz = new DateTimeZone($timezone);
        
        // Get current time in the specified timezone
        $current_time = new DateTime('now', $tz);
        
        // Parse the datetime-local format (stored as Y-m-d\TH:i)
        $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start, $tz);
        $end_dt   = DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz);
        
        // Try alternative formats if first attempt failed
        if (!$start_dt) {
            $start_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $start), $tz);
        }
        if (!$start_dt) {
            $start_dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $start, $tz);
        }
        if (!$start_dt) {
            $start_dt = DateTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $start), $tz);
        }
        
        if (!$end_dt) {
            $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
        }
        if (!$end_dt) {
            $end_dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $end, $tz);
        }
        if (!$end_dt) {
            $end_dt = DateTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $end), $tz);
        }
        
        // If dates are missing or can't be parsed:
        // - In normal mode: bail out (can't determine window)
        // - In preview mode: disable countdown safely and still render the page
        if (!$start_dt || !$end_dt) {
            if (!$can_preview) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SSM: Could not parse dates in maintenance page. Start: ' . $start . ', End: ' . $end);
            }
                return;
            }
            // Preview mode: render without countdown (template requires $countdown_end if countdown is on)
            $show_countdown = 0;
        }
        
        // Decide whether to render page:
        // - Normal mode: only during active window
        // - Preview mode: always render (admin/editor only)
        $should_render = $can_preview;
        if (!$can_preview && $start_dt && $end_dt) {
            // All times are in the same timezone, so comparison should work correctly
            $should_render = ($current_time >= $start_dt && $current_time <= $end_dt);
        }
        
        if ($should_render) {
        // Set page title
        add_filter('pre_get_document_title', function () use ($heading) {
            return esc_html($heading) . ' | Maintenance Mode';
        });

        // Process description with rich text support (wpautop handles paragraphs, wp_kses_post allows safe HTML)
        $desc_formatted = wpautop(wp_kses_post($desc));
        
        // Get HTML language attribute
        $html_lang = $current_lang;
        if (function_exists('pll_current_language')) {
            $pll_lang = pll_current_language('slug');
            if ($pll_lang) {
                $html_lang = $pll_lang;
            }
        }

        // Calculate countdown to end time (in seconds)
        if ($show_countdown && $end_dt) {
            $countdown_end = $end_dt->getTimestamp();
            $countdown_seconds = $countdown_end - time();
            // In preview mode (or if end is in the past), don't run a negative countdown loop.
            if ($countdown_seconds <= 0) {
                $show_countdown = 0;
                $countdown_end = time();
            }
        } else {
            // Safe defaults (template will not reference $countdown_end if countdown is off)
            $countdown_end = time();
            $countdown_seconds = 0;
        }

        // Clear any output buffers to prevent conflicts
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send proper headers
        if (!headers_sent()) {
            if ($can_preview) {
                status_header(200);
                header('X-Robots-Tag: noindex, nofollow', true);
            } else {
                status_header(503);
            }
            nocache_headers();
            header('Content-Type: text/html; charset=utf-8');
        }
        
        // Render the maintenance page as a minimal response for performance.
        // Skipping wp_head/wp_footer prevents themes/plugins from enqueueing large assets.
        $ssm_skip_wp_head = true;
        
        // Load template file
        $template_path = SSM_PLUGIN_PATH . 'template.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback if template doesn't exist
            echo '<!DOCTYPE html><html><head><title>Maintenance Mode</title></head><body><h1>Site Under Maintenance</h1><p>Please check back soon.</p></body></html>';
        }
        
        // Exit immediately
        exit;
        }
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SSM DEBUG Timezone Error: ' . $e->getMessage());
            error_log('SSM DEBUG Stack: ' . $e->getTraceAsString());
        }
        return;
    }
}
// Hook early to catch all requests before theme loads
// Using priority 1 to run before most other plugins
// NOTE: Hooks must be registered (WordPress requirement), but they exit immediately
// when maintenance mode is disabled or window has ended - minimal overhead (1 query/transient check)
add_action('template_redirect', 'ssm_show_maintenance_page', 1);
