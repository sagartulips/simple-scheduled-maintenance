<?php
/**
 * Plugin Name: Simple Scheduled Maintenance
 * Plugin URI: https://github.com/sagartulips/simple-scheduled-maintenance
 * Description: A powerful WordPress plugin for scheduled maintenance mode with multi-language support, countdown timer, and flexible configuration. Automatically detects WPML/Polylang or allows manual language setup.
 * Version: 2.3
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
 * @version 2.3
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
define('SSM_VERSION', '2.3');

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
 */
function ssm_plugin_uninstall() {
    // Check if user wants to remove data (this would be handled via AJAX before uninstall)
    // For now, we'll keep the data unless explicitly deleted
    // The uninstall process in WordPress doesn't allow user interaction,
    // so we'll add a settings page option to allow data removal
}

/**
 * Clear plugin-specific cache only (not site-wide cache)
 */
function ssm_clear_cache() {
    // Clear plugin-specific transients
    delete_transient('ssm_maintenance_status');
    
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
 * Check if maintenance should be active (better error handling)
 */
function ssm_should_show_maintenance() {
    $enabled = get_option('ssm_enabled');
    
    if (!$enabled) {
        error_log('SSM DEBUG: Maintenance is disabled');
        return false;
    }
    
    $start = get_option('ssm_start_time');
    $end = get_option('ssm_end_time');
    
    if (!$start || !$end) {
        error_log('SSM DEBUG: Start or end time not set. Start: ' . ($start ?: 'empty') . ', End: ' . ($end ?: 'empty'));
        return false;
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
            error_log('SSM DEBUG: Invalid date format. Start: ' . $start . ', End: ' . $end);
            error_log('SSM DEBUG: Start parsed: ' . ($start_dt ? $start_dt->format('Y-m-d H:i:s T') : 'FAILED'));
            error_log('SSM DEBUG: End parsed: ' . ($end_dt ? $end_dt->format('Y-m-d H:i:s T') : 'FAILED'));
            return false;
        }
        
        // Compare times in the same timezone
        $is_active = ($current_time >= $start_dt && $current_time <= $end_dt);
        
        // Always log for debugging when maintenance should be active
        error_log('SSM DEBUG: Current time: ' . $current_time->format('Y-m-d H:i:s T'));
        error_log('SSM DEBUG: Start time: ' . $start_dt->format('Y-m-d H:i:s T'));
        error_log('SSM DEBUG: End time: ' . $end_dt->format('Y-m-d H:i:s T'));
        error_log('SSM DEBUG: Current >= Start: ' . ($current_time >= $start_dt ? 'YES' : 'NO'));
        error_log('SSM DEBUG: Current <= End: ' . ($current_time <= $end_dt ? 'YES' : 'NO'));
        error_log('SSM DEBUG: Is Active: ' . ($is_active ? 'YES' : 'NO'));
        
        if (!$is_active) {
            if ($current_time < $start_dt) {
                $diff = $current_time->diff($start_dt);
                error_log('SSM DEBUG: Not started yet. Time until start: ' . $diff->format('%h hours %i minutes %s seconds'));
            } elseif ($current_time > $end_dt) {
                $diff = $end_dt->diff($current_time);
                error_log('SSM DEBUG: Already ended. Time since end: ' . $diff->format('%h hours %i minutes %s seconds'));
            }
        }
        
        return $is_active;
        
    } catch (Exception $e) {
        error_log('SSM DEBUG Error: ' . $e->getMessage());
        error_log('SSM DEBUG Stack: ' . $e->getTraceAsString());
        return false;
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
 */
function ssm_show_maintenance_page() {
    // Skip for admin pages and AJAX requests
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        error_log('SSM DEBUG: Skipping - Admin or AJAX request');
        return;
    }
    
    // Skip for login page
    if (isset($GLOBALS['pagenow']) && in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
        error_log('SSM DEBUG: Skipping - Login page');
        return;
    }
    
    // IMPORTANT: Allow admins and editors to bypass maintenance
    // They should always be able to access the site normally
    // However, if they add ?ssm_preview=1 to the URL, they can preview the maintenance page
    $preview_mode = isset($_GET['ssm_preview']) && $_GET['ssm_preview'] == '1';
    
    if (is_user_logged_in() && (current_user_can('administrator') || current_user_can('editor'))) {
        if (!$preview_mode) {
            error_log('SSM DEBUG: Skipping - User is admin/editor (bypassing maintenance)');
            return;
        } else {
            error_log('SSM DEBUG: Admin/editor preview mode enabled');
        }
    }

    error_log('SSM DEBUG: Checking if maintenance should be shown...');
    
    // Use improved check function for better error handling
    if (!ssm_should_show_maintenance()) {
        error_log('SSM DEBUG: Maintenance check returned false, not showing page');
        return;
    }

    error_log('SSM DEBUG: Maintenance should be shown! Displaying maintenance page...');

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
    $desc    = ssm_get_maintenance_message('description', 'We\'re working hard to improve the user experience. Stay tuned!', $current_lang);
    $countdown_text = ssm_get_maintenance_message('countdown_text', 'We\'ll be back in:', $current_lang);
    
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
        
        if (!$start_dt || !$end_dt) {
            error_log('SSM: Could not parse dates in maintenance page. Start: ' . $start . ', End: ' . $end);
            return;
        }
        
        // Double-check we're in maintenance window (should already be checked, but safety first)
        // All times are in the same timezone, so comparison should work correctly
        if ($current_time >= $start_dt && $current_time <= $end_dt) {
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
        $countdown_end = $end_dt->getTimestamp();
        $countdown_seconds = $countdown_end - time();

        // Output maintenance page
        error_log('SSM DEBUG: Starting to output maintenance page HTML...');
        
        // Clear any output buffers to prevent conflicts
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send proper headers
        if (!headers_sent()) {
            status_header(503);
            nocache_headers();
            header('Content-Type: text/html; charset=utf-8');
        }
        
        // Load template file
        $template_path = SSM_PLUGIN_PATH . 'template.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback if template doesn't exist
            error_log('SSM ERROR: Template file not found at: ' . $template_path);
            echo '<!DOCTYPE html><html><head><title>Maintenance Mode</title></head><body><h1>Site Under Maintenance</h1><p>Please check back soon.</p></body></html>';
        }
        
        error_log('SSM DEBUG: Maintenance page HTML output complete, calling exit...');
        
        // Exit immediately
        exit;
        }
        
    } catch (Exception $e) {
        error_log('SSM DEBUG Timezone Error: ' . $e->getMessage());
        error_log('SSM DEBUG Stack: ' . $e->getTraceAsString());
        return;
    }
}
// Hook early to catch all requests before theme loads
// Using priority 1 to run before most other plugins
add_action('template_redirect', 'ssm_show_maintenance_page', 1);

// Also try hooking into init as a backup (runs earlier)
add_action('init', function() {
    // Only run on frontend, not admin
    if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
        // Check if maintenance should be active
        if (function_exists('ssm_should_show_maintenance') && ssm_should_show_maintenance()) {
            // If user is not admin/editor, show maintenance page
            if (!is_user_logged_in() || !(current_user_can('administrator') || current_user_can('editor'))) {
                error_log('SSM DEBUG: Init hook triggered, maintenance should be active');
            }
        }
    }
}, 1);
