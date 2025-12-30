<?php
function ssm_admin_menu() {
    add_options_page('Simple Maintenance', 'Maintenance Mode', 'manage_options', 'ssm-settings', 'ssm_settings_page');
}
add_action('admin_menu', 'ssm_admin_menu');

// AJAX handler for removing image
add_action('wp_ajax_ssm_remove_image', 'ssm_ajax_remove_image');
function ssm_ajax_remove_image() {
    check_ajax_referer('ssm_save_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }
    
    update_option('ssm_image', '');
    if (function_exists('ssm_clear_cache')) {
        ssm_clear_cache();
    }
    
    wp_send_json_success(['message' => 'Image removed successfully']);
}

/**
 * Get available languages (from plugin or manual config)
 */
function ssm_get_available_languages() {
    // Check if language mode is set to manual - if so, only use configured languages
    $language_mode = get_option('ssm_language_mode', '');
    if ($language_mode === 'manual') {
        // Manual mode: only use configured languages
        return ssm_get_configured_languages();
    }
    
    // If multilingual plugin is active and mode is automatic or not set, use it
    if (function_exists('ssm_is_multilingual_active') && ssm_is_multilingual_active()) {
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

// Admin settings page
function ssm_settings_page() {
    $is_multilingual = function_exists('ssm_is_multilingual_active') && ssm_is_multilingual_active();
    
    // Handle cache clearing request
    if (isset($_POST['ssm_clear_cache'])) {
        check_admin_referer('ssm_clear_cache_action', 'ssm_clear_cache_nonce');
        
        if (function_exists('ssm_clear_cache')) {
            ssm_clear_cache();
            echo "<div class='updated'><p><strong>Plugin cache cleared successfully.</strong></p></div>";
        } else {
            echo "<div class='error'><p><strong>Error: Cache clearing function not available.</strong></p></div>";
        }
    }
    
    // Handle image removal (fallback for non-AJAX - redirect method)
    if (isset($_POST['ssm_remove_image']) && !isset($_POST['ssm_save'])) {
        check_admin_referer('ssm_save_action', 'ssm_save_nonce');
        update_option('ssm_image', '');
        if (function_exists('ssm_clear_cache')) {
            ssm_clear_cache();
        }
        // Redirect to avoid form resubmission and show updated state
        wp_safe_redirect(add_query_arg(['page' => 'ssm-settings', 'image_removed' => '1'], admin_url('options-general.php')));
        exit;
    }
    
    // Show success message if image was removed via redirect
    if (isset($_GET['image_removed']) && $_GET['image_removed'] == '1') {
        $current_time = current_time('mysql');
        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
        echo "<div class='updated notice is-dismissible' id='ssm-image-removed'><p><strong>Image removed successfully.</strong> <span style='color: #666; font-size: 0.9em;'>(Removed at: " . esc_html($formatted_time) . ")</span></p></div>";
    }
    
    // Handle data removal request (separate, more protected)
    if (isset($_POST['ssm_remove_data']) && isset($_POST['ssm_confirm_remove'])) {
        check_admin_referer('ssm_remove_data_action', 'ssm_remove_data_nonce');
        
        // Remove all plugin options
        delete_option('ssm_enabled');
        delete_option('ssm_start_time');
        delete_option('ssm_end_time');
        delete_option('ssm_timezone');
        delete_option('ssm_heading');
        delete_option('ssm_description');
        delete_option('ssm_image');
        delete_option('ssm_show_image');
        delete_option('ssm_show_countdown');
        delete_option('ssm_configured_languages');
        delete_option('ssm_default_language');
        delete_option('ssm_language_mode');
        delete_option('ssm_languages_configured');
        delete_option('ssm_plugin_just_activated');
        delete_option('ssm_plugin_deactivated');
        delete_option('ssm_plugin_version');
        
        // Remove language-specific options
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ssm_%'");
        
        // Clear cache
        if (function_exists('ssm_clear_cache')) {
            ssm_clear_cache();
        }
        
        echo "<div class='updated'><p><strong>All plugin data has been removed.</strong></p></div>";
    }
    
    // Handle reconfigure request
    if (isset($_GET['reconfigure'])) {
        delete_option('ssm_languages_configured');
        delete_option('ssm_language_mode');
        // Don't delete configured_languages yet - keep them for manual mode
        // Only reset if user explicitly chooses to reconfigure
    }
    
    // Check if plugin was just activated
    $just_activated = get_option('ssm_plugin_just_activated', false);
    if ($just_activated) {
        delete_option('ssm_plugin_just_activated');
    }
    
    $languages_configured = get_option('ssm_languages_configured', false);
    $language_mode = get_option('ssm_language_mode', '');
    $configured_langs = get_option('ssm_configured_languages', []);
    
    // For non-multilingual sites, automatically set up English and skip configuration
    // BUT only if:
    // 1. Not in reconfigure mode (reconfigure shouldn't show for single-language sites anyway)
    // 2. Not in language_mode_set mode (user clicked Continue and wants manual config)
    // 3. Languages are not configured yet (auto-configure on first load)
    if (!$is_multilingual && !isset($_GET['reconfigure']) && !isset($_GET['language_mode_set'])) {
        // Auto-configure if languages are not configured or empty
        if (!$languages_configured || empty($configured_langs) || empty($language_mode)) {
            update_option('ssm_configured_languages', ['en' => 'English']);
            update_option('ssm_default_language', 'en');
            update_option('ssm_language_mode', 'manual');
            update_option('ssm_languages_configured', true);
            // Reload variables after setting options
            $languages_configured = true;
            $language_mode = 'manual';
            $configured_langs = ['en' => 'English'];
        }
    }
    
    // Also handle reconfigure URL for single-language sites - just ensure config is set
    if (!$is_multilingual && isset($_GET['reconfigure'])) {
        // Ensure English is configured even if reconfigure is clicked
        if (!$languages_configured || empty($configured_langs) || empty($language_mode)) {
            update_option('ssm_configured_languages', ['en' => 'English']);
            update_option('ssm_default_language', 'en');
            update_option('ssm_language_mode', 'manual');
            update_option('ssm_languages_configured', true);
            // Reload variables after setting options
            $languages_configured = true;
            $language_mode = 'manual';
            $configured_langs = ['en' => 'English'];
        }
        // Remove reconfigure parameter from URL for single-language sites
        // (they don't need language reconfiguration)
        wp_safe_redirect(add_query_arg(['page' => 'ssm-settings'], admin_url('options-general.php')));
        exit;
    }
    
    // Handle language configuration mode
    if (isset($_POST['ssm_set_language_mode'])) {
        check_admin_referer('ssm_language_mode_action', 'ssm_language_mode_nonce');
        
        $mode = sanitize_text_field($_POST['ssm_language_mode']);
        update_option('ssm_language_mode', $mode);
        
        if ($mode === 'automatic') {
            // Auto-detect from WPML/Polylang
            update_option('ssm_languages_configured', true);
            // Set transient for success message
            set_transient('ssm_language_mode_set_success', 'automatic', 30);
            // Redirect to clean URL
            wp_safe_redirect(add_query_arg(['page' => 'ssm-settings'], admin_url('options-general.php')));
            exit;
        } elseif ($mode === 'manual') {
            // Will be configured in next step
            update_option('ssm_languages_configured', false);
            
            // Clear old automatically detected languages when switching to manual
            // Get old configured languages before clearing
            $old_languages = get_option('ssm_configured_languages', []);
            
            // For multi-language sites, get all languages from automatic detection
            $is_multilingual = function_exists('ssm_is_multilingual_active') && ssm_is_multilingual_active();
            if ($is_multilingual) {
                // Get all automatically detected languages
                $auto_languages = [];
                if (function_exists('icl_get_languages')) {
                    $wpml_langs = icl_get_languages('skip_missing=0');
                    foreach ($wpml_langs as $lang) {
                        $auto_languages[$lang['code']] = $lang['native_name'];
                    }
                } elseif (function_exists('pll_languages_list')) {
                    $pll_langs = pll_languages_list(['fields' => []]);
                    foreach ($pll_langs as $lang) {
                        $auto_languages[$lang->slug] = $lang->name;
                    }
                }
                
                // Clear language-specific options for all automatically detected languages
                foreach ($auto_languages as $old_code => $old_name) {
                    delete_option("ssm_heading_{$old_code}");
                    delete_option("ssm_description_{$old_code}");
                    delete_option("ssm_countdown_text_{$old_code}");
                }
            } else {
                // For single-language sites, clear old configured languages
                foreach ($old_languages as $old_code => $old_name) {
                    delete_option("ssm_heading_{$old_code}");
                    delete_option("ssm_description_{$old_code}");
                    delete_option("ssm_countdown_text_{$old_code}");
                }
            }
            
            // Clear the configured languages array - will be set fresh when user configures manually
            delete_option('ssm_configured_languages');
            
            // Clear cache to ensure fresh data
            wp_cache_flush();
            
            // Ensure at least English is configured for manual mode
            $existing_langs = get_option('ssm_configured_languages', []);
            if (empty($existing_langs)) {
                update_option('ssm_configured_languages', ['en' => 'English']);
                update_option('ssm_default_language', 'en');
            }
            
            // IMPORTANT: Clear any output buffers before redirect
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Redirect to close modal and show manual config form
            // Build clean URL from scratch with only the parameters we want
            $redirect_url = admin_url('options-general.php?page=ssm-settings&language_mode_set=1');
            
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    // Show success message if language mode was just set (check transient first for automatic mode)
    $auto_mode_success = get_transient('ssm_language_mode_set_success');
    if ($auto_mode_success === 'automatic') {
        delete_transient('ssm_language_mode_set_success');
        $current_time = current_time('mysql');
        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
        echo "<div class='updated notice is-dismissible' id='ssm-mode-saved'><p><strong>Language mode set. Languages configured automatically.</strong> <span style='color: #666; font-size: 0.9em;'>(Saved at: " . esc_html($formatted_time) . ")</span></p></div>";
    } elseif (isset($_GET['language_mode_set']) && $_GET['language_mode_set'] == '1') {
        $current_mode = get_option('ssm_language_mode', '');
        if ($current_mode === 'manual') {
            $current_time = current_time('mysql');
            $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
            echo "<div class='updated notice is-dismissible' id='ssm-mode-saved'><p><strong>Language mode set. Please configure languages below.</strong> <span style='color: #666; font-size: 0.9em;'>(Saved at: " . esc_html($formatted_time) . ")</span></p></div>";
        }
    }
    
    // Handle manual language configuration (works for both multilingual and non-multilingual)
    if (isset($_POST['ssm_configure_languages'])) {
        check_admin_referer('ssm_configure_languages_action', 'ssm_configure_languages_nonce');
        
        $languages = [];
        $default_lang = isset($_POST['ssm_default_language']) ? sanitize_text_field($_POST['ssm_default_language']) : 'en';
        
        // Process languages array - handle WordPress form structure
        // WordPress processes ssm_languages[][code] and ssm_languages[][name] as separate indexed arrays
        // Result: [0]=>{code:en}, [1]=>{name:English}, [2]=>{code:sv}, [3]=>{name:Swedish}
        if (isset($_POST['ssm_languages']) && is_array($_POST['ssm_languages'])) {
            $current_code = '';
            $current_name = '';
            
            foreach ($_POST['ssm_languages'] as $index => $lang_data) {
                if (is_array($lang_data)) {
                    // Check if this array has a 'code' key
                    if (isset($lang_data['code']) && !empty($lang_data['code'])) {
                        // If we have a previous code/name pair, save it first
                        if (!empty($current_code) && !empty($current_name)) {
                            // Trim and clean code (remove all spaces)
                            $code = trim($current_code);
                            $code = str_replace(' ', '', $code);
                            $code = sanitize_key($code);
                            $name = trim($current_name);
                            $name = sanitize_text_field($name);
                            $languages[$code] = $name;
                        }
                        // Start new pair with code - trim whitespace
                        $current_code = trim($lang_data['code']);
                        // Remove any spaces from code (language codes should be continuous)
                        $current_code = str_replace(' ', '', $current_code);
                        $current_name = ''; // Reset name
                    }
                    // Check if this array has a 'name' key
                    elseif (isset($lang_data['name']) && !empty($lang_data['name'])) {
                        $current_name = trim($lang_data['name']);
                        
                        // If we have both code and name now, save the pair
                        if (!empty($current_code) && !empty($current_name)) {
                            // Trim and clean code (remove all spaces)
                            $code = trim($current_code);
                            $code = str_replace(' ', '', $code);
                            $code = sanitize_key($code);
                            $name = trim($current_name);
                            $name = sanitize_text_field($name);
                            $languages[$code] = $name;
                            // Reset for next pair
                            $current_code = '';
                            $current_name = '';
                        }
                    }
                }
            }
            
            // Save any remaining pair at the end
            if (!empty($current_code) && !empty($current_name)) {
                $code = sanitize_key($current_code);
                $name = sanitize_text_field($current_name);
                $languages[$code] = $name;
            }
        }
        
        // If no languages configured, add default English
        if (empty($languages)) {
            $languages['en'] = 'English';
            $default_lang = 'en';
        }
        
        // Ensure default language is in the languages array
        if (!isset($languages[$default_lang]) && !empty($languages)) {
            // If default language is not in list, use first language as default
            $default_lang = array_key_first($languages);
        }
        
        // Get old languages before updating
        $old_languages = get_option('ssm_configured_languages', []);
        
        // Update configured languages
        $saved_langs = update_option('ssm_configured_languages', $languages);
        update_option('ssm_default_language', $default_lang);
        update_option('ssm_languages_configured', true);
        
        // Clear language-specific options for languages that are no longer in the list
        foreach ($old_languages as $old_code => $old_name) {
            if (!isset($languages[$old_code])) {
                // This language was removed, delete its options
                delete_option("ssm_heading_{$old_code}");
                delete_option("ssm_description_{$old_code}");
                delete_option("ssm_countdown_text_{$old_code}");
            }
        }
        
        // Clear cache aggressively to ensure fresh data
        if (function_exists('ssm_clear_cache')) {
            ssm_clear_cache();
        }
        // Clear specific option caches
        wp_cache_delete('ssm_configured_languages', 'options');
        wp_cache_delete('ssm_default_language', 'options');
        wp_cache_delete('ssm_languages_configured', 'options');
        wp_cache_delete('ssm_language_mode', 'options');
        wp_cache_flush();
        
        // IMPORTANT: Clear output buffers before redirect
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Redirect to avoid showing empty page
        wp_safe_redirect(add_query_arg(['page' => 'ssm-settings', 'languages_configured' => '1'], admin_url('options-general.php')));
        exit;
    }
    
    // Show success message if languages were just configured
    if (isset($_GET['languages_configured']) && $_GET['languages_configured'] == '1') {
        $current_time = current_time('mysql');
        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
        echo "<div class='updated notice is-dismissible' id='ssm-languages-saved'><p><strong>Languages configured successfully.</strong> <span style='color: #666; font-size: 0.9em;'>(Saved at: " . esc_html($formatted_time) . ")</span></p></div>";
    }
    
    // Handle plugin reset
    if (isset($_POST['ssm_reset']) && isset($_POST['ssm_reset_confirm']) && $_POST['ssm_reset_confirm'] === 'yes') {
        check_admin_referer('ssm_reset_action', 'ssm_reset_nonce');
        
        // Get image URL before deleting options (to delete the file)
        $image_url = get_option('ssm_image', '');
        
        // Delete ALL plugin options from database using comprehensive SQL query
        // This is more reliable than individual delete_option() calls
        // and catches ALL plugin-related options including:
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
        // Clear specific option caches
        wp_cache_delete('ssm_enabled', 'options');
        wp_cache_delete('ssm_start_time', 'options');
        wp_cache_delete('ssm_end_time', 'options');
        wp_cache_delete('ssm_timezone', 'options');
        wp_cache_delete('ssm_heading', 'options');
        wp_cache_delete('ssm_description', 'options');
        wp_cache_delete('ssm_image', 'options');
        wp_cache_delete('ssm_show_image', 'options');
        wp_cache_delete('ssm_show_countdown', 'options');
        wp_cache_delete('ssm_countdown_text', 'options');
        wp_cache_delete('ssm_configured_languages', 'options');
        wp_cache_delete('ssm_default_language', 'options');
        wp_cache_delete('ssm_language_mode', 'options');
        wp_cache_delete('ssm_languages_configured', 'options');
        wp_cache_delete('ssm_plugin_just_activated', 'options');
        wp_cache_delete('ssm_plugin_version', 'options');
        wp_cache_delete('ssm_plugin_deactivated', 'options');
        
        // Flush all cache to ensure everything is cleared
        wp_cache_flush();
        
        // Redirect with success message
        wp_safe_redirect(add_query_arg(['page' => 'ssm-settings', 'reset_success' => '1'], admin_url('options-general.php')));
        exit;
    }
    
    // Show success message if plugin was reset
    if (isset($_GET['reset_success']) && $_GET['reset_success'] == '1') {
        $current_time = current_time('mysql');
        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
        echo "<div class='updated notice is-dismissible' id='ssm-reset-success'><p><strong>Plugin data has been reset successfully. All settings and configurations have been removed.</strong> <span style='color: #666; font-size: 0.9em;'>(Reset at: " . esc_html($formatted_time) . ")</span></p></div>";
    }
    
    // Handle main settings save
    if (isset($_POST['ssm_save'])) {
        check_admin_referer('ssm_save_action', 'ssm_save_nonce');

        update_option('ssm_enabled', isset($_POST['ssm_enabled']) ? 1 : 0);
        update_option('ssm_start_time', sanitize_text_field($_POST['ssm_start_time']));
        update_option('ssm_end_time', sanitize_text_field($_POST['ssm_end_time']));
        update_option('ssm_timezone', sanitize_text_field($_POST['ssm_timezone']));
        
        // Clear "window ended" transient when settings are saved (new window may be configured)
        delete_transient('ssm_window_ended');
        update_option('ssm_show_countdown', isset($_POST['ssm_show_countdown']) ? 1 : 0);
        
        // Save email notification settings
        update_option('ssm_email_notifications', isset($_POST['ssm_email_notifications']) ? 1 : 0);
        update_option('ssm_email_addresses', sanitize_text_field($_POST['ssm_email_addresses'] ?? ''));
        update_option('ssm_email_notify_end', isset($_POST['ssm_email_notify_end']) ? 1 : 0);
        update_option('ssm_email_subject_start', sanitize_text_field($_POST['ssm_email_subject_start'] ?? ''));
        update_option('ssm_email_message_start', wp_kses_post($_POST['ssm_email_message_start'] ?? ''));
        update_option('ssm_email_subject_end', sanitize_text_field($_POST['ssm_email_subject_end'] ?? ''));
        update_option('ssm_email_message_end', wp_kses_post($_POST['ssm_email_message_end'] ?? ''));
        
        // Save default/fallback messages
        // Remove slashes if magic quotes are enabled
        $heading_value = isset($_POST['ssm_heading']) ? stripslashes($_POST['ssm_heading']) : '';
        $description_value = isset($_POST['ssm_description']) ? stripslashes($_POST['ssm_description']) : '';
        $countdown_value = isset($_POST['ssm_countdown_text']) ? stripslashes($_POST['ssm_countdown_text']) : 'We\'ll be back in:';
        
        update_option('ssm_heading', sanitize_text_field($heading_value));
        update_option('ssm_description', wp_kses_post($description_value));
        update_option('ssm_countdown_text', sanitize_text_field($countdown_value));
        
        // Save language-specific messages
        $languages = ssm_get_available_languages();
        
        foreach ($languages as $lang_code => $lang_name) {
            if (isset($_POST["ssm_heading_{$lang_code}"])) {
                $lang_heading = stripslashes($_POST["ssm_heading_{$lang_code}"]);
                update_option("ssm_heading_{$lang_code}", sanitize_text_field($lang_heading));
            }
            if (isset($_POST["ssm_description_{$lang_code}"])) {
                $lang_desc = stripslashes($_POST["ssm_description_{$lang_code}"]);
                update_option("ssm_description_{$lang_code}", wp_kses_post($lang_desc));
            }
            if (isset($_POST["ssm_countdown_text_{$lang_code}"])) {
                $lang_countdown = stripslashes($_POST["ssm_countdown_text_{$lang_code}"]);
                update_option("ssm_countdown_text_{$lang_code}", sanitize_text_field($lang_countdown));
            }
        }
        
        // Handle image upload
        if (!empty($_FILES['ssm_image']['tmp_name'])) {
            $upload = wp_handle_upload($_FILES['ssm_image'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                update_option('ssm_image', esc_url_raw($upload['url']));
            }
        }
        
        // Handle show image option
        update_option('ssm_show_image', isset($_POST['ssm_show_image']) ? 1 : 0);

        // Show success message with timestamp
        $current_time = current_time('mysql');
        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($current_time));
        echo "<div class='updated notice is-dismissible' id='ssm-settings-saved'><p><strong>Settings saved successfully.</strong> <span style='color: #666; font-size: 0.9em;'>(Saved at: " . esc_html($formatted_time) . ")</span></p></div>";
    }

    $enabled         = get_option('ssm_enabled');
    // Get raw values and remove any slashes that might have been added
    $heading_raw     = get_option('ssm_heading', 'Site Under Maintenance');
    $heading         = esc_attr(stripslashes($heading_raw));
    $description_raw = get_option('ssm_description', 'We\'ll be back shortly.');
    $description     = stripslashes($description_raw);
    $countdown_raw   = get_option('ssm_countdown_text', 'We\'ll be back in:');
    $countdown_text  = esc_attr(stripslashes($countdown_raw));
    $start           = get_option('ssm_start_time');
    $end             = get_option('ssm_end_time');
    $timezone        = get_option('ssm_timezone') ?: get_option('timezone_string') ?: 'Europe/Stockholm';
    $image           = get_option('ssm_image', '');
    if (empty($image)) {
        $image = (defined('SSM_PLUGIN_URL') ? SSM_PLUGIN_URL : plugin_dir_url(__FILE__)) . '404.svg'; // Fallback to default local image
    }
    $show_image      = get_option('ssm_show_image', 1);
    $show_countdown  = get_option('ssm_show_countdown', 1);
    $email_notifications = get_option('ssm_email_notifications', 0);
    $email_addresses = get_option('ssm_email_addresses', get_option('admin_email', ''));
    $email_notify_end = get_option('ssm_email_notify_end', 0);
    $email_subject_start = stripslashes(get_option('ssm_email_subject_start', ''));
    $email_message_start = stripslashes(get_option('ssm_email_message_start', ''));
    $email_subject_end = stripslashes(get_option('ssm_email_subject_end', ''));
    $email_message_end = stripslashes(get_option('ssm_email_message_end', ''));
    
    // If languages were just configured, reload everything fresh FIRST
    if (isset($_GET['languages_configured']) && $_GET['languages_configured'] == '1') {
        // Force clear all caches
        wp_cache_delete('ssm_configured_languages', 'options');
        wp_cache_delete('ssm_language_mode', 'options');
        wp_cache_delete('ssm_languages_configured', 'options');
        wp_cache_delete('ssm_default_language', 'options');
        wp_cache_flush();
    }
    
    // Reload language_mode first to ensure correct language source
    $language_mode   = get_option('ssm_language_mode', '');
    // Reload languages_configured to ensure we have the latest after auto-config
    $languages_configured = get_option('ssm_languages_configured', false);
    
    // Get languages - use updated values after auto-configuration
    // IMPORTANT: Clear cache before getting languages to ensure fresh data
    if (!isset($_GET['languages_configured'])) {
        wp_cache_delete('ssm_configured_languages', 'options');
    }
    $languages       = ssm_get_available_languages();
    $default_lang    = get_option('ssm_default_language', 'en');
    // Reload configured_langs to ensure we have the latest after auto-config
    $configured_langs = ssm_get_configured_languages();
    
    
    // If on reconfigure, ensure we have at least English for manual mode
    if (isset($_GET['reconfigure']) && empty($configured_langs)) {
        $configured_langs = ['en' => 'English'];
        update_option('ssm_configured_languages', $configured_langs);
        update_option('ssm_default_language', 'en');
    }
    
    // After Continue is clicked with manual mode, update variables for display
    if (isset($_GET['language_mode_set'])) {
        // Ensure language_mode is set to manual (from the form submission)
        $language_mode = get_option('ssm_language_mode', '');
        if ($language_mode === 'manual') {
            $languages_configured = false; // Force showing config form
            // Reset configured languages to ensure clean state for manual config
            $configured_langs = get_option('ssm_configured_languages', []);
            if (empty($configured_langs)) {
                $configured_langs = ['en' => 'English'];
                update_option('ssm_configured_languages', $configured_langs);
            }
            // Update languages array to reflect the reset
            $languages = $configured_langs;
            // Ensure default lang is set
            if (!isset($configured_langs[$default_lang]) && !empty($configured_langs)) {
                $default_lang = array_key_first($configured_langs);
                update_option('ssm_default_language', $default_lang);
            }
        }
    }
    
    // Check if languages need to be configured
    // For single-language sites, allow manual config even if languages_configured is true
    // This allows users to add more languages manually
    $needs_config = !$languages_configured && (!$is_multilingual || $language_mode === 'manual');
    
    // If on reconfigure page and manual mode selected, ensure config form shows
    // OR if language_mode_set is in URL (user clicked Continue in modal), show form
    if (isset($_GET['language_mode_set']) && $language_mode === 'manual') {
        $needs_config = true;
        $languages_configured = false; // Force showing config form
    }
    
    // Determine if manual config form should show (defined early for use in show_main_tabs)
    // Show if: 
    // 1. needs config and manual mode, OR 
    // 2. language_mode_set is in URL and mode is manual (user wants to configure manually)
    // NOTE: For single-language sites, we don't show manual config - they just use English
    $show_manual_config = false;
    if ($is_multilingual) {
        // Only show manual config for multilingual sites
        $show_manual_config = ($needs_config && $language_mode === 'manual') || 
                             (isset($_GET['language_mode_set']) && $language_mode === 'manual');
    }

    $timezones = timezone_identifiers_list();

    // Validate and sanitize timezone
    if (!in_array($timezone, $timezones)) {
        $timezone = 'Europe/Stockholm';
    }

    // Safe DateTimeZone instantiation
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('Europe/Stockholm');
        $timezone = 'Europe/Stockholm';
    }
    
    // Get current time in the selected timezone
    $now = new DateTime('now', $tz);
    $start_dt = $start ? DateTime::createFromFormat('Y-m-d\TH:i', $start, $tz) : null;
    $end_dt   = $end ? DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz) : null;

    // If parsing failed, try alternative format
    if ($start && !$start_dt) {
        $start_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $start), $tz);
    }
    if ($end && !$end_dt) {
        $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
    }
    
    // Get default language name
    $default_lang_name = isset($languages[$default_lang]) ? $languages[$default_lang] : 'English';
    
    // Only show modal if:
    // 1. On reconfigure AND language_mode_set is NOT in URL (means Continue not clicked yet)
    // 2. OR multilingual site that needs initial configuration
    // NOTE: For single-language sites, we don't show modal - just auto-configure English
    $show_modal = false;
    if (isset($_GET['reconfigure']) && !isset($_GET['language_mode_set'])) {
        // Only show modal on reconfigure for MULTILINGUAL sites
        // Single-language sites don't need language configuration
        if ($is_multilingual) {
            $show_modal = true;
        }
    } elseif ($is_multilingual && !$languages_configured && empty($language_mode) && !isset($_GET['language_mode_set']) && !isset($_GET['reconfigure'])) {
        // Show modal for multilingual sites that need initial configuration
        $show_modal = true;
    }
?>
<div class="wrap">
    <h1>Simple Scheduled Maintenance</h1>
    
    <?php if ($show_modal) : ?>
        <!-- Language Configuration Modal -->
        <div id="ssm-language-modal" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
                <h2 style="margin-top: 0;"><?php echo $just_activated ? 'Welcome! Language Configuration' : 'Reconfigure Languages'; ?></h2>
                <p>Choose how to configure languages for your maintenance messages:</p>
                
                <form method="post" id="ssm-language-mode-form" action="">
                    <?php wp_nonce_field('ssm_language_mode_action', 'ssm_language_mode_nonce'); ?>
                    
                    <table class="form-table">
                        <?php if ($is_multilingual) : ?>
                        <tr>
                            <th>
                                <label>
                                    <input type="radio" name="ssm_language_mode" value="automatic" <?php checked($language_mode === 'automatic' || (empty($language_mode) && $is_multilingual)); ?>>
                                    <strong>Automatic Detection</strong>
                                </label>
                            </th>
                            <td>
                                <p>Languages will be automatically detected from your multilingual plugin (<?php echo function_exists('icl_get_languages') ? 'WPML' : 'Polylang'; ?>).</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>
                                <label>
                                    <input type="radio" name="ssm_language_mode" value="manual" <?php checked(!$is_multilingual || $language_mode === 'manual' || (empty($language_mode) && !$is_multilingual)); ?>>
                                    <strong>Manual Configuration</strong>
                                </label>
                            </th>
                            <td>
                                <p><?php echo $is_multilingual ? 'Manually configure languages with codes and names.' : 'Configure languages manually (default: English).'; ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="ssm_set_language_mode" class="button button-primary" value="Continue">
                    </p>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <?php 
    // Show manual config form (already defined above)
    if ($show_manual_config) : 
    ?>
        <!-- Manual Language Configuration (shown after Continue is clicked) -->
        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <h2>Configure Languages</h2>
            <p>Add languages with their codes (e.g., 'en', 'sv', 'no', 'de', 'fi') and names (e.g., 'English', 'Swedish').</p>
            
            <form method="post" id="language-config-form">
                <?php wp_nonce_field('ssm_configure_languages_action', 'ssm_configure_languages_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="ssm_default_language">Default Language</label></th>
                        <td>
                            <select id="ssm_default_language" name="ssm_default_language">
                                <?php 
                                // Ensure at least English is available
                                $langs_to_show = !empty($configured_langs) ? $configured_langs : ['en' => 'English'];
                                foreach ($langs_to_show as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($default_lang, $code); ?>>
                                        <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">This language will be used as fallback if current language is not detected.</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Languages</h3>
                <div id="languages-container">
                    <?php 
                    // Ensure at least one language (English) is shown
                    $langs_to_display = !empty($configured_langs) ? $configured_langs : ['en' => 'English'];
                    foreach ($langs_to_display as $code => $name) : ?>
                        <div class="language-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                            <input type="text" 
                                   name="ssm_languages[][code]" 
                                   value="<?php echo esc_attr($code); ?>" 
                                   placeholder="Code (e.g., en, sv)" 
                                   style="width: 120px;"
                                   required>
                            <input type="text" 
                                   name="ssm_languages[][name]" 
                                   value="<?php echo esc_attr($name); ?>" 
                                   placeholder="Language Name (e.g., English)" 
                                   style="flex: 1;"
                                   required>
                            <button type="button" class="button remove-language" style="color: #dc3232;">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-language" class="button" style="margin-top: 10px;">+ Add Language</button>
                
                <p class="submit">
                    <input type="submit" name="ssm_configure_languages" class="button button-primary" value="Save Language Configuration">
                </p>
            </form>
        </div>
    <?php endif; ?>
    
    <?php 
    // Only show main tabs if:
    // 1. Languages are configured AND
    // 2. Not in reconfigure mode AND
    // 3. Not in language_mode_set mode AND
    // 4. Not showing manual config form
    $show_main_tabs = $languages_configured && !isset($_GET['reconfigure']) && !isset($_GET['language_mode_set']) && !$show_manual_config;
    if ($show_main_tabs) : 
    ?>
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper" style="margin: 20px 0;">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general">General Settings</a>
            <a href="#default-lang" class="nav-tab" data-tab="default-lang">Default Language (<?php echo esc_html(strtoupper($default_lang)); ?>)</a>
            <?php foreach ($languages as $lang_code => $lang_name) : ?>
                <?php if ($lang_code !== $default_lang) : ?>
                    <a href="#lang-<?php echo esc_attr($lang_code); ?>" class="nav-tab" data-tab="lang-<?php echo esc_attr($lang_code); ?>"><?php echo esc_html($lang_name); ?> (<?php echo esc_html(strtoupper($lang_code)); ?>)</a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="#email" class="nav-tab" data-tab="email">Email Notifications</a>
            <a href="#debug" class="nav-tab" data-tab="debug">Debug Info</a>
        </nav>
        
        <?php
        // Check if maintenance is currently active
        $is_active = false;
        $status_message = '';
        $status_class = 'notice-info';
        
        if ($enabled && $start && $end) {
            try {
                $timezone = get_option('ssm_timezone') ?: get_option('timezone_string') ?: 'UTC';
                $tz = new DateTimeZone($timezone);
                $current_time = new DateTime('now', $tz);
                
                // Try to parse dates
                $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start, $tz);
                if (!$start_dt) {
                    $start_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $start), $tz);
                }
                
                $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end, $tz);
                if (!$end_dt) {
                    $end_dt = DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $end), $tz);
                }
                
                if ($start_dt && $end_dt) {
                    if ($current_time >= $start_dt && $current_time <= $end_dt) {
                        $is_active = true;
                        $status_class = 'notice-error';
                        $time_remaining = $current_time->diff($end_dt);
                        $status_message = '<strong>⚠️ MAINTENANCE MODE IS CURRENTLY ACTIVE</strong><br>';
                        $status_message .= 'Your site is currently showing a 503 maintenance page to all visitors (except admins/editors).<br>';
                        $status_message .= 'Ends: ' . esc_html($end_dt->format('Y-m-d H:i:s T')) . ' (' . esc_html($time_remaining->format('%h hours %i minutes')) . ' remaining)';
                    } elseif ($current_time < $start_dt) {
                        $time_until = $current_time->diff($start_dt);
                        $status_message = '<strong>Maintenance Mode Scheduled</strong><br>';
                        $status_message .= 'Will start: ' . esc_html($start_dt->format('Y-m-d H:i:s T')) . ' (in ' . esc_html($time_until->format('%d days %h hours %i minutes')) . ')<br>';
                        $status_message .= 'Will end: ' . esc_html($end_dt->format('Y-m-d H:i:s T'));
                    } else {
                        $status_message = '<strong>Maintenance Window Has Passed</strong><br>';
                        $status_message .= 'The scheduled maintenance period has ended. Uncheck "Enable Maintenance Mode" to prevent accidental activation.';
                        $status_class = 'notice-warning';
                    }
                }
            } catch (Exception $e) {
                // Silent fail - don't show status if dates can't be parsed
            }
        } elseif ($enabled) {
            $status_message = '<strong>⚠️ Maintenance Mode Enabled But Not Configured</strong><br>';
            $status_message .= 'Please set Start Date/Time and End Date/Time below.';
            $status_class = 'notice-warning';
        } else {
            $status_message = '<strong>Maintenance Mode is Disabled</strong><br>';
            $status_message .= 'Your site is accessible normally. Enable and configure schedule below to activate maintenance mode.';
        }

            // (debug output removed)

        ?>
        
        <?php if ($status_message): ?>
        <div class="notice <?php echo esc_attr($status_class); ?> is-dismissible" style="margin: 20px 0; padding: 12px;">
            <p><?php echo $status_message; ?></p>
        </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" id="ssm-main-form">
        <?php wp_nonce_field('ssm_save_action', 'ssm_save_nonce'); ?>
            
            <!-- General Settings Tab -->
            <div id="tab-general" class="ssm-tab-content" style="display: block;">
                <h2>General Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="ssm_enabled">Enable Maintenance Mode</label></th>
                <td><input type="checkbox" id="ssm_enabled" name="ssm_enabled" <?php checked($enabled); ?>></td>
            </tr>
            <tr>
                <th><label for="ssm_start_time">Start Date/Time</label></th>
                        <td>
                            <input type="datetime-local" id="ssm_start_time" name="ssm_start_time" value="<?php echo !empty($start) ? esc_attr($start) : ''; ?>"<?php echo !empty($start) ? ' required' : ''; ?>>
                            <p class="description">Enter date and time in the timezone selected below.</p>
                        </td>
            </tr>
            <tr>
                <th><label for="ssm_end_time">End Date/Time</label></th>
                        <td>
                            <input type="datetime-local" id="ssm_end_time" name="ssm_end_time" value="<?php echo !empty($end) ? esc_attr($end) : ''; ?>"<?php echo !empty($end) ? ' required' : ''; ?>>
                            <p class="description">Enter date and time in the timezone selected below.</p>
                        </td>
            </tr>
            <tr>
                <th><label for="ssm_timezone">Time Zone</label></th>
                <td>
                    <select id="ssm_timezone" name="ssm_timezone">
                                <?php foreach ($timezones as $tz_option) : ?>
                                    <option value="<?php echo esc_attr($tz_option); ?>" <?php selected($timezone, $tz_option); ?>>
                                        <?php echo esc_html($tz_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                            <div style="margin: 10px 0; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; border-radius: 4px;">
                                <strong>Current Date/Time (<?php echo esc_html($timezone); ?>):</strong>
                                <div id="current-time-display" style="font-size: 16px; font-weight: bold; color: #2271b1; margin-top: 5px;">
                                    <?php echo esc_html($now->format('Y-m-d H:i:s T')); ?>
                                </div>
                            </div>
                            <p class="description"><strong>Important:</strong> Enter start/end times in this timezone. The system will automatically convert to match.</p>
                        </td>
                    </tr>
                    <?php if ($start_dt && $end_dt) : ?>
                    <tr>
                        <th>Maintenance Status</th>
                        <td>
                            <div id="maintenance-status-display" style="margin: 10px 0;">
                                <?php
                                $status_html = '';
                                $countdown_seconds = 0;
                                if (!$enabled) {
                                    $status_html = '<span style="color:gray; font-weight: bold;">Disabled</span>';
                                } elseif ($now < $start_dt) {
                                    $countdown_seconds = $start_dt->getTimestamp() - time();
                                    $status_html = '<span style="color:blue; font-weight: bold;">Scheduled (Not Started)</span> - Starts in: <span id="countdown-to-start"></span>';
                                } elseif ($now > $end_dt) {
                                    $status_html = '<span style="color:green; font-weight: bold;">Ended</span> - Ended ' . human_time_diff($end_dt->getTimestamp(), time()) . ' ago';
                                } else {
                                    $countdown_seconds = $end_dt->getTimestamp() - time();
                                    $status_html = '<span style="color:red; font-weight: bold;">ACTIVE NOW</span> - Ends in: <span id="countdown-to-end"></span>';
                                }
                                echo $status_html;
                                ?>
                            </div>
                            <?php if ($countdown_seconds > 0) : ?>
                            <div id="countdown-timer-general" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; display: none;">
                                <div style="font-weight: bold; margin-bottom: 10px;">Countdown Timer:</div>
                                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                                    <div style="text-align: center;">
                                        <div id="countdown-days" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                        <div style="font-size: 12px; color: #666;">Days</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div id="countdown-hours" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                        <div style="font-size: 12px; color: #666;">Hours</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div id="countdown-minutes" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                        <div style="font-size: 12px; color: #666;">Minutes</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div id="countdown-seconds" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                        <div style="font-size: 12px; color: #666;">Seconds</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="ssm_show_image">Show Maintenance Image</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="ssm_show_image" name="ssm_show_image" value="1" <?php checked($show_image, 1); ?>>
                                Enable maintenance image display
                            </label>
                            <p class="description">Check to show the maintenance image on the maintenance page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ssm_image">Maintenance Image</label></th>
                        <td>
                            <input type="file" id="ssm_image" name="ssm_image" accept="image/*">
                            <?php 
                            $default_image = defined('SSM_PLUGIN_URL') ? SSM_PLUGIN_URL . '404.svg' : plugin_dir_url(__FILE__) . '404.svg';
                            $has_custom_image = !empty($image) && $image !== $default_image && $image !== '';
                            ?>
                            <?php if ($has_custom_image) : ?>
                                <p id="ssm-image-container">
                                    <img src="<?php echo esc_url($image); ?>" style="max-width: 200px; margin-top: 10px; display: block;" alt="Current image" id="ssm-current-image">
                                    <button type="button" id="ssm-remove-image-btn" class="button button-secondary" style="margin-top: 10px;">
                                        Remove Image
                                    </button>
                                </p>
                            <?php elseif (empty($image) || $image === $default_image) : ?>
                                <p>
                                    <img src="<?php echo esc_url($default_image); ?>" style="max-width: 200px; margin-top: 10px; display: block; opacity: 0.6;" alt="Default image">
                                    <span class="description" style="display: block; margin-top: 5px;">Default image (upload a new image to replace)</span>
                                </p>
                            <?php endif; ?>
                            <p class="description">Upload a custom maintenance image. This image will be used for all languages. Recommended size: 180x180px or larger.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ssm_show_countdown">Show Countdown</label></th>
                        <td>
                            <input type="checkbox" id="ssm_show_countdown" name="ssm_show_countdown" value="1" <?php checked($show_countdown, 1); ?>>
                            <p class="description">Display countdown timer on maintenance page.</p>
                        </td>
                    </tr>
                    <?php if ($enabled) : ?>
                    <tr>
                        <th>Preview Maintenance Page</th>
                        <td>
                            <?php
                            $home_url = home_url();
                            $preview_url = add_query_arg('ssm_preview', '1', $home_url);
                            ?>
                            <a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button button-secondary">
                                Preview Maintenance Page (Opens in new tab)
                            </a>
                            <p class="description">
                                <strong>Admin preview:</strong> This link will show the maintenance page to you even if the maintenance window is not currently active.
                                Visitors will still only see it during the scheduled window.
                            </p>
                            <?php if (empty($start) || empty($end)) : ?>
                                <p class="description" style="color:#b32d2e;">
                                    <strong>Note:</strong> Start/End time is not set yet. Preview will show the page, but countdown may be hidden until you set an End time.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Default Language Tab -->
            <div id="tab-default-lang" class="ssm-tab-content" style="display: none;">
                <h2>Default Language (<?php echo esc_html(strtoupper($default_lang)); ?>) - <?php echo esc_html($default_lang_name); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ssm_heading">Heading</label></th>
                        <td><input type="text" id="ssm_heading" name="ssm_heading" value="<?php echo $heading; ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="ssm_description">Description</label></th>
                        <td>
                            <?php
                            wp_editor($description, 'ssm_description', [
                                'textarea_name' => 'ssm_description',
                                'textarea_rows' => 10,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,alignjustify,link,unlink,forecolor,backcolor,removeformat,undo,redo',
                                    'toolbar2' => 'formatselect,fontselect,fontsizeselect',
                                    'toolbar3' => '',
                                ],
                                'quicktags' => true,
                            ]);
                            ?>
                            <p class="description">Rich text editor with support for formatting, alignment, images, and HTML.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ssm_countdown_text">Countdown Text</label></th>
                        <td>
                            <input type="text" id="ssm_countdown_text" name="ssm_countdown_text" value="<?php echo esc_attr($countdown_text); ?>" class="regular-text">
                            <p class="description">Text displayed above the countdown timer (e.g., "We'll be back in:", "Vi är tillbaka om:", etc.)</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Language-Specific Tabs -->
            <?php foreach ($languages as $lang_code => $lang_name) : ?>
                <?php if ($lang_code !== $default_lang) : ?>
                    <div id="tab-lang-<?php echo esc_attr($lang_code); ?>" class="ssm-tab-content" style="display: none;">
                        <h2><?php echo esc_html($lang_name); ?> (<?php echo esc_html(strtoupper($lang_code)); ?>)</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="ssm_heading_<?php echo esc_attr($lang_code); ?>">Heading</label></th>
                                <td>
                                    <input type="text" 
                                           id="ssm_heading_<?php echo esc_attr($lang_code); ?>" 
                                           name="ssm_heading_<?php echo esc_attr($lang_code); ?>" 
                                           value="<?php echo esc_attr(stripslashes(get_option("ssm_heading_{$lang_code}", ''))); ?>" 
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr($heading); ?>">
                                    <p class="description">Leave empty to use default heading.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ssm_description_<?php echo esc_attr($lang_code); ?>">Description</label></th>
                                <td>
                                    <?php
                                    $lang_desc_raw = get_option("ssm_description_{$lang_code}", '');
                                    $lang_desc = stripslashes($lang_desc_raw);
                                    wp_editor($lang_desc, "ssm_description_{$lang_code}", [
                                        'textarea_name' => "ssm_description_{$lang_code}",
                                        'textarea_rows' => 10,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'tinymce' => [
                                            'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,alignjustify,link,unlink,forecolor,backcolor,removeformat,undo,redo',
                                            'toolbar2' => 'formatselect,fontselect,fontsizeselect',
                                            'toolbar3' => '',
                                        ],
                                        'quicktags' => true,
                                    ]);
                                    ?>
                                    <p class="description">Leave empty to use default description. Rich text editor with support for formatting, alignment, images, and HTML.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ssm_countdown_text_<?php echo esc_attr($lang_code); ?>">Countdown Text</label></th>
                                <td>
                                    <input type="text" 
                                           id="ssm_countdown_text_<?php echo esc_attr($lang_code); ?>" 
                                           name="ssm_countdown_text_<?php echo esc_attr($lang_code); ?>" 
                                           value="<?php echo esc_attr(stripslashes(get_option("ssm_countdown_text_{$lang_code}", ''))); ?>" 
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr($countdown_text); ?>">
                                    <p class="description">Leave empty to use default countdown text.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <p class="submit" style="margin-top: 20px;">
                <?php submit_button('Save Settings', 'primary', 'ssm_save', false); ?>
                <?php // Only show Reconfigure Languages button for multilingual sites ?>
                <?php if ($is_multilingual): ?>
                    <button type="button" id="ssm-reconfigure-btn" class="button">Reconfigure Languages</button>
                <?php endif; ?>
            </p>
    </form>

        <!-- Reconfigure Languages Confirmation Modal -->
        <div id="ssm-reconfigure-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top: 0; color: #dc3232;">Reconfigure Languages</h2>
                <p style="font-size: 16px; line-height: 1.6; margin-bottom: 25px;">This will reset your language configuration. All language settings will be cleared and you'll need to reconfigure them. Continue?</p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="ssm-reconfigure-cancel" class="button">Cancel</button>
                    <button type="button" id="ssm-reconfigure-ok" class="button button-primary" style="background: #dc3232; border-color: #dc3232;">OK</button>
                </div>
            </div>
        </div>

        <!-- Clear Cache Section -->
        <div class="card" style="margin-top: 30px; border-left: 4px solid #2271b1;">
            <h2 style="color: #2271b1;">Clear Plugin Cache</h2>
            <p>Clear the plugin's cached configuration data. This will refresh the maintenance settings without deleting any configuration. Safe to use anytime.</p>
            <form method="post">
                <?php wp_nonce_field('ssm_clear_cache_action', 'ssm_clear_cache_nonce'); ?>
                <p class="submit">
                    <input type="submit" name="ssm_clear_cache" class="button button-secondary" value="Clear Cache" style="background: #2271b1; color: white; border-color: #2271b1;">
                </p>
            </form>
        </div>
        
        <!-- Data Removal Section (Hidden by default, can be shown via toggle) -->
        <div class="card" style="margin-top: 30px; border-left: 4px solid #dc3232; display: none;" id="data-removal-section">
            <h2 style="color: #dc3232;">⚠️ Reset Plugin Data (Danger Zone)</h2>
            <p><strong>Warning:</strong> This will permanently delete all maintenance settings, language configurations, uploaded images, and messages from the database. This action cannot be undone.</p>
            <p><strong>What will be deleted:</strong></p>
            <ul style="margin-left: 20px;">
                <li>All maintenance mode settings (enabled/disabled, start/end times, timezone)</li>
                <li>All language configurations (automatic/manual mode, configured languages)</li>
                <li>All maintenance messages (headings, descriptions, countdown text) for all languages</li>
                <li>Uploaded maintenance image file</li>
                <li>All plugin-specific options and transients</li>
            </ul>
            <form method="post" id="ssm-reset-form">
                <?php wp_nonce_field('ssm_reset_action', 'ssm_reset_nonce'); ?>
                <label>
                    <input type="checkbox" name="ssm_reset_confirm" value="yes" required>
                    I understand this will delete all plugin data permanently
                </label>
                <p class="submit">
                    <button type="button" id="ssm-reset-btn" class="button button-secondary" style="background: #dc3232; color: white; border-color: #dc3232;">Reset All Plugin Data</button>
                </p>
            </form>
        </div>
        
        <!-- Reset Plugin Data Confirmation Modal -->
        <div id="ssm-reset-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top: 0; color: #dc3232;">⚠️ Reset All Plugin Data</h2>
                <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;"><strong>Are you absolutely sure you want to reset ALL plugin data?</strong></p>
                <p style="font-size: 14px; line-height: 1.6; color: #666; margin-bottom: 25px;">This action will permanently delete:</p>
                <ul style="margin-left: 20px; margin-bottom: 25px; color: #666;">
                    <li>All maintenance mode settings</li>
                    <li>All language configurations</li>
                    <li>All maintenance messages for all languages</li>
                    <li>Uploaded maintenance image file</li>
                    <li>All plugin-specific options and transients</li>
                </ul>
                <p style="font-size: 14px; line-height: 1.6; color: #dc3232; font-weight: bold; margin-bottom: 25px;">⚠️ This action cannot be undone!</p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="ssm-reset-cancel" class="button">Cancel</button>
                    <button type="button" id="ssm-reset-confirm" class="button button-primary" style="background: #dc3232; border-color: #dc3232;">Yes, Delete All Data</button>
                </div>
            </div>
        </div>
        
        <!-- Toggle for Reset Section -->
        <div style="margin-top: 20px;">
            <button type="button" id="show-data-removal" class="button" style="color: #dc3232;">Show Reset Options</button>
        </div>
        
        <!-- Email Notifications Tab -->
        <div id="tab-email" class="ssm-tab-content" style="display: none;">
            <h2>Email Notifications</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ssm_email_notifications">Enable Email Notifications</label></th>
                    <td>
                        <input type="checkbox" id="ssm_email_notifications" name="ssm_email_notifications" <?php checked($email_notifications); ?>>
                        <p class="description">Receive email alerts when maintenance mode starts and ends using WordPress native wp_mail() function.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssm_email_addresses">Email Addresses</label></th>
                    <td>
                        <input type="text" id="ssm_email_addresses" name="ssm_email_addresses" value="<?php echo esc_attr($email_addresses); ?>" class="regular-text" placeholder="admin@example.com, team@example.com">
                        <p class="description">Comma-separated list of email addresses to notify. Defaults to admin email if empty.</p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><h3 style="margin: 20px 0 10px 0;">Maintenance Start Email</h3></th>
                </tr>
                <tr>
                    <th><label for="ssm_email_subject_start">Email Subject</label></th>
                    <td>
                        <input type="text" id="ssm_email_subject_start" name="ssm_email_subject_start" value="<?php echo esc_attr($email_subject_start); ?>" class="regular-text" placeholder="[Site Name] Maintenance Mode Activated">
                        <p class="description">Subject line for maintenance start notification. Leave empty to use default.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssm_email_message_start">Email Message</label></th>
                    <td>
                        <?php
                        wp_editor($email_message_start, 'ssm_email_message_start', [
                            'textarea_name' => 'ssm_email_message_start',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => true,
                            'tinymce' => false,
                            'quicktags' => true,
                        ]);
                        ?>
                        <p class="description">Message body for maintenance start notification. Leave empty to use default. Available placeholders: {site_name}, {site_url}, {start_time}, {end_time}, {duration}, {timezone}</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssm_email_notify_end">Notify When Maintenance Ends</label></th>
                    <td>
                        <input type="checkbox" id="ssm_email_notify_end" name="ssm_email_notify_end" <?php checked($email_notify_end); ?>>
                        <p class="description">Send an email notification when maintenance mode completes.</p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><h3 style="margin: 20px 0 10px 0;">Maintenance End Email</h3></th>
                </tr>
                <tr>
                    <th><label for="ssm_email_subject_end">Email Subject</label></th>
                    <td>
                        <input type="text" id="ssm_email_subject_end" name="ssm_email_subject_end" value="<?php echo esc_attr($email_subject_end); ?>" class="regular-text" placeholder="[Site Name] Maintenance Mode Completed">
                        <p class="description">Subject line for maintenance end notification. Leave empty to use default.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssm_email_message_end">Email Message</label></th>
                    <td>
                        <?php
                        wp_editor($email_message_end, 'ssm_email_message_end', [
                            'textarea_name' => 'ssm_email_message_end',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => true,
                            'tinymce' => false,
                            'quicktags' => true,
                        ]);
                        ?>
                        <p class="description">Message body for maintenance end notification. Leave empty to use default. Available placeholders: {site_name}, {site_url}</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Debug Info Tab -->
        <div id="tab-debug" class="ssm-tab-content" style="display: none;">
            <h2>Maintenance Timing Debug Info</h2>
    <table class="form-table">
        <tr>
                    <th>Current Time (<?php echo esc_html($timezone); ?>)</th>
                    <td><span id="current-server-time-debug"><?php echo esc_html($now->format('Y-m-d H:i:s T')); ?></span></td>
        </tr>
        <tr>
            <th>Start Date/Time</th>
            <td><?php echo $start_dt ? esc_html($start_dt->format('Y-m-d H:i:s T')) : 'Not set'; ?></td>
        </tr>
        <tr>
            <th>End Date/Time</th>
            <td><?php echo $end_dt ? esc_html($end_dt->format('Y-m-d H:i:s T')) : 'Not set'; ?></td>
        </tr>
                <tr>
                    <th>Maintenance Status</th>
            <td>
                        <div id="maintenance-status-debug">
                <?php
                            $debug_countdown_seconds = 0;
                    if (!$enabled) {
                        echo '<span style="color:gray;">Disabled</span>';
                            } elseif (!$start_dt || !$end_dt) {
                                echo '<span style="color:orange;">Dates not set</span>';
                    } elseif ($now < $start_dt) {
                                $debug_countdown_seconds = $start_dt->getTimestamp() - time();
                                echo '<span style="color:blue;">Scheduled (Not Started)</span> - Starts in: <span id="countdown-to-start-debug"></span>';
                    } elseif ($now > $end_dt) {
                                echo '<span style="color:green;">Ended</span> - Ended ' . human_time_diff($end_dt->getTimestamp(), time()) . ' ago';
                            } else {
                                $debug_countdown_seconds = $end_dt->getTimestamp() - time();
                                echo '<span style="color:red; font-weight: bold;">ACTIVE NOW</span> - Ends in: <span id="countdown-to-end-debug"></span>';
                            }
                            ?>
                        </div>
                        <?php if ($debug_countdown_seconds > 0) : ?>
                        <div id="countdown-timer-debug" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #2271b1;">
                            <div style="font-weight: bold; margin-bottom: 10px; color: #2271b1;">Countdown Timer:</div>
                            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                                <div style="text-align: center;">
                                    <div id="countdown-days-debug" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                    <div style="font-size: 12px; color: #666;">Days</div>
                                </div>
                                <div style="text-align: center;">
                                    <div id="countdown-hours-debug" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                    <div style="font-size: 12px; color: #666;">Hours</div>
                                </div>
                                <div style="text-align: center;">
                                    <div id="countdown-minutes-debug" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                    <div style="font-size: 12px; color: #666;">Minutes</div>
                                </div>
                                <div style="text-align: center;">
                                    <div id="countdown-seconds-debug" style="font-size: 24px; font-weight: bold; color: #2271b1;">0</div>
                                    <div style="font-size: 12px; color: #666;">Seconds</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Server Timezone</th>
                    <td><?php echo esc_html(date_default_timezone_get()); ?></td>
                </tr>
                <tr>
                    <th>Multilingual Plugin</th>
                    <td>
                        <?php 
                            if ($is_multilingual) {
                                echo '<span style="color:green;">✓ Active</span>';
                                if (function_exists('icl_get_languages')) {
                                    echo ' (WPML)';
                                } elseif (function_exists('pll_languages_list')) {
                                    echo ' (Polylang)';
                                }
                            } else {
                                echo '<span style="color:gray;">Not detected - Using manual configuration</span>';
                            }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Available Languages</th>
                    <td>
                        <?php 
                            if (!empty($languages)) {
                                $lang_list = [];
                                foreach ($languages as $code => $name) {
                                    $lang_list[] = esc_html($name) . ' (' . esc_html($code) . ')';
                                }
                                echo implode(', ', $lang_list);
                    } else {
                                echo 'None configured';
                    }
                ?>
            </td>
        </tr>
        <tr>
                    <th>Default Language</th>
                    <td><?php echo esc_html($default_lang); ?></td>
        </tr>
        <tr>
                    <th>Detected Current Language</th>
                    <td><?php echo esc_html(function_exists('ssm_get_current_language') ? ssm_get_current_language() : 'Not available'); ?></td>
        </tr>
    </table>
        </div>
    <?php elseif (!$show_modal && !$show_manual_config && !$show_main_tabs): ?>
        <!-- Fallback message if nothing else shows (shouldn't happen, but prevents blank page) -->
        <!-- Only show for multilingual sites that need configuration -->
        <?php if ($is_multilingual): ?>
            <div class="notice notice-info" style="margin: 20px 0;">
                <p><strong>Please configure your languages.</strong></p>
                <p>If you see this message, there may be a configuration issue. Please try <a href="<?php echo esc_url(add_query_arg(['page' => 'ssm-settings', 'reconfigure' => '1'], admin_url('options-general.php'))); ?>">reconfiguring languages</a>.</p>
            </div>
        <?php else: ?>
            <!-- For single-language sites, this shouldn't appear - auto-config should handle it -->
            <div class="notice notice-warning" style="margin: 20px 0;">
                <p><strong>Configuration issue detected.</strong></p>
                <p>Please refresh the page or contact support if this message persists.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Prevent datetime-local inputs from defaulting to today when empty
    // Dynamically toggle required attribute based on maintenance mode checkbox
    function toggleDateFieldRequirements() {
        var maintenanceEnabled = $('#ssm_enabled').is(':checked');
        if (maintenanceEnabled) {
            // Only require if maintenance is enabled AND field has a value
            $('#ssm_start_time, #ssm_end_time').each(function() {
                if ($(this).val()) {
                    $(this).attr('required', 'required');
                } else {
                    $(this).removeAttr('required');
                }
            });
        } else {
            // Remove required when maintenance is disabled
            $('#ssm_start_time, #ssm_end_time').removeAttr('required');
        }
    }
    
    // Toggle on checkbox change
    $('#ssm_enabled').on('change', toggleDateFieldRequirements);
    
    // Initialize on page load
    toggleDateFieldRequirements();
    
    // Prevent datetime-local inputs from auto-filling today's date when empty
    $('#ssm_start_time, #ssm_end_time').on('focus', function() {
        var $field = $(this);
        var originalValue = $field.data('original-value') || '';
        if (!originalValue) {
            $field.data('original-value', $field.val() || '');
        }
    }).on('blur', function() {
        var $field = $(this);
        // If field was empty before focus and is still empty (or was cleared), keep it empty
        var originalValue = $field.data('original-value');
        if (!originalValue && !$field.val()) {
            $field.val('').attr('value', '');
        }
    }).on('change', function() {
        // If user clears the field, ensure it's truly empty
        if (!$(this).val()) {
            $(this).val('').attr('value', '');
            $(this).removeAttr('required');
        } else {
            // If maintenance is enabled, make it required
            if ($('#ssm_enabled').is(':checked')) {
                $(this).attr('required', 'required');
            }
        }
    });
    
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show/hide content
        $('.ssm-tab-content').hide();
        $('#tab-' + tab).show();
    });
    
    // Fix HTML5 validation for hidden required fields
    // When form is submitted, remove 'required' from fields in hidden tabs
    $('#ssm-main-form').on('submit', function(e) {
        // Find all hidden tab content
        $('.ssm-tab-content:hidden').find('input[required], select[required], textarea[required]').each(function() {
            // Remove required attribute from hidden fields
            $(this).removeAttr('required');
        });
        
        // Also handle fields that might be in containers with display:none
        $('#ssm-main-form').find('input[required], select[required], textarea[required]').each(function() {
            var $field = $(this);
            // Check if field or any parent is hidden
            if (!$field.is(':visible') || $field.closest(':hidden').length > 0) {
                $field.removeAttr('required');
            }
        });
        
        // Only require date fields if maintenance mode is enabled
        var maintenanceEnabled = $('#ssm_enabled').is(':checked');
        if (!maintenanceEnabled) {
            $('#ssm_start_time, #ssm_end_time').removeAttr('required');
        } else {
            // If enabled, ensure dates are provided
            var startTime = $('#ssm_start_time').val();
            var endTime = $('#ssm_end_time').val();
            if (!startTime || !endTime) {
                e.preventDefault();
                alert('Please provide both Start Date/Time and End Date/Time when maintenance mode is enabled.');
                $('#ssm_start_time, #ssm_end_time').attr('required', 'required');
                return false;
            }
        }
    });
    
    // Remove image button handler (AJAX)
    $('#ssm-remove-image-btn').on('click', function() {
        if (!confirm('Are you sure you want to remove this image?')) {
            return;
        }
        
        var $btn = $(this);
        var $container = $('#ssm-image-container');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Removing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ssm_remove_image',
                nonce: '<?php echo wp_create_nonce('ssm_save_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $container.html('<span class="description" style="display: block; margin-top: 10px;">Image removed. Default image will be used.</span>');
                    // Show success message
                    $('.wrap h1').after('<div class="notice notice-success is-dismissible"><p><strong>' + response.data.message + '</strong></p></div>');
                    // Auto-dismiss after 3 seconds
                    setTimeout(function() {
                        $('.notice.is-dismissible').fadeOut();
                    }, 3000);
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to remove image'));
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('Error: Failed to remove image');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Reconfigure button handler - show modal instead of confirm
    $('#ssm-reconfigure-btn').on('click', function() {
        $('#ssm-reconfigure-modal').show();
    });
    
    // Reconfigure modal handlers
    $('#ssm-reconfigure-cancel').on('click', function() {
        $('#ssm-reconfigure-modal').hide();
    });
    
    $('#ssm-reconfigure-ok').on('click', function() {
        window.location.href = '?page=ssm-settings&reconfigure=1';
    });
    
    // Close modal when clicking outside
    $('#ssm-reconfigure-modal').on('click', function(e) {
        if ($(e.target).attr('id') === 'ssm-reconfigure-modal') {
            $(this).hide();
        }
    });
    
    // Auto-dismiss success messages after 5 seconds
    $('.notice.is-dismissible[id^="ssm-"]').each(function() {
        var $notice = $(this);
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000); // 5 seconds
    });
    
    // Also handle WordPress dismiss button
    $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Reset button handler - show modal instead of confirm
    $('#ssm-reset-btn').on('click', function() {
        // Check if checkbox is checked
        var checkbox = $('#ssm-reset-form input[name="ssm_reset_confirm"]');
        if (!checkbox.is(':checked')) {
            alert('Please check the confirmation checkbox first.');
            return;
        }
        $('#ssm-reset-modal').show();
    });
    
    // Reset modal handlers
    $('#ssm-reset-cancel').on('click', function() {
        $('#ssm-reset-modal').hide();
    });
    
    $('#ssm-reset-confirm').on('click', function() {
        // Submit the form
        $('#ssm-reset-form').submit();
    });
    
    // Close modal when clicking outside
    $('#ssm-reset-modal').on('click', function(e) {
        if ($(e.target).attr('id') === 'ssm-reset-modal') {
            $(this).hide();
        }
    });
    
    // Show/hide reset section
    $('#show-data-removal').on('click', function() {
        var section = $('#data-removal-section');
        if (section.is(':visible')) {
            section.slideUp();
            $(this).text('Show Reset Options');
        } else {
            section.slideDown();
            $(this).text('Hide Reset Options');
        }
    });
    
    // Language configuration JavaScript
    var addLanguageBtn = $('#add-language');
    var languagesContainer = $('#languages-container');
    
    if (addLanguageBtn.length && languagesContainer.length) {
        // Function to update default language dropdown
        function updateDefaultLanguageDropdown() {
            var defaultLangSelect = $('#ssm_default_language');
            if (defaultLangSelect.length) {
                var currentValue = defaultLangSelect.val();
                var options = [];
                
                languagesContainer.find('.language-row').each(function() {
                    var code = $(this).find('input[name*="[code]"]').val();
                    var name = $(this).find('input[name*="[name]"]').val();
                    // Trim whitespace from code and name
                    if (code) code = code.trim().replace(/\s+/g, '');
                    if (name) name = name.trim();
                    if (code && name) {
                        options.push('<option value="' + code + '">' + name + ' (' + code.toUpperCase() + ')</option>');
                    }
                });
                
                defaultLangSelect.html(options.join(''));
                
                // Restore selection if it still exists
                if (defaultLangSelect.find('option[value="' + currentValue + '"]').length) {
                    defaultLangSelect.val(currentValue);
                } else if (options.length > 0) {
                    // Select first option if current value doesn't exist
                    defaultLangSelect.val(defaultLangSelect.find('option:first').val());
                }
            }
        }
        
        addLanguageBtn.on('click', function() {
            var row = $('<div class="language-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">' +
                       '<input type="text" name="ssm_languages[][code]" placeholder="Code (e.g., en, sv)" style="width: 120px;" required>' +
                       '<input type="text" name="ssm_languages[][name]" placeholder="Language Name (e.g., English)" style="flex: 1;" required>' +
                       '<button type="button" class="button remove-language" style="color: #dc3232;">Remove</button>' +
                       '</div>');
            languagesContainer.append(row);
            
            // Auto-trim language code on blur
            row.find('input[name*="[code]"]').on('blur', function() {
                var $codeInput = $(this);
                var code = $codeInput.val();
                if (code) {
                    // Trim and remove all spaces
                    code = code.trim().replace(/\s+/g, '');
                    $codeInput.val(code);
                }
                updateDefaultLanguageDropdown();
            });
            
            // Auto-trim language name on blur
            row.find('input[name*="[name]"]').on('blur', function() {
                var $nameInput = $(this);
                var name = $nameInput.val();
                if (name) {
                    name = name.trim();
                    $nameInput.val(name);
                }
                updateDefaultLanguageDropdown();
            });
            
            // Update dropdown when inputs change
            row.find('input').on('input change', function() {
                updateDefaultLanguageDropdown();
            });
            
            updateDefaultLanguageDropdown();
            
            row.find('.remove-language').on('click', function() {
                if (languagesContainer.children().length > 1) {
                    row.remove();
                    updateDefaultLanguageDropdown();
                } else {
                    alert('You must have at least one language configured.');
                }
            });
        });
        
        // Auto-trim language codes on blur for existing inputs
        languagesContainer.on('blur', 'input[name*="[code]"]', function() {
            var $codeInput = $(this);
            var code = $codeInput.val();
            if (code) {
                // Trim and remove all spaces
                code = code.trim().replace(/\s+/g, '');
                $codeInput.val(code);
            }
            updateDefaultLanguageDropdown();
        });
        
        // Auto-trim language names on blur for existing inputs
        languagesContainer.on('blur', 'input[name*="[name]"]', function() {
            var $nameInput = $(this);
            var name = $nameInput.val();
            if (name) {
                name = name.trim();
                $nameInput.val(name);
            }
            updateDefaultLanguageDropdown();
        });
        
        // Update dropdown when existing language inputs change
        languagesContainer.on('input change', 'input[name*="[code]"], input[name*="[name]"]', function() {
            updateDefaultLanguageDropdown();
        });
        
        // Add remove functionality to existing rows
        languagesContainer.on('click', '.remove-language', function() {
            if (languagesContainer.children().length > 1) {
                $(this).closest('.language-row').remove();
                updateDefaultLanguageDropdown();
            } else {
                alert('You must have at least one language configured.');
            }
        });
        
        // Initial dropdown update
        updateDefaultLanguageDropdown();
    }
    
    // Server time update for General Settings
    function updateServerTime() {
        var timezone = "<?php echo esc_js($timezone); ?>";
        var now = new Date();
        
        var timeString = now.toLocaleString('en-GB', { 
            timeZone: timezone, 
            hour12: false,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        // Update General Settings time display
        var generalTimeElement = $('#current-time-display');
        if (generalTimeElement.length) {
            generalTimeElement.text(timeString + ' (' + timezone + ')');
        }
        
        // Update Debug tab time
        var debugTimeElement = $('#current-server-time-debug');
        if (debugTimeElement.length) {
            debugTimeElement.text(timeString + ' (' + timezone + ')');
        }
    }
    
    // Update timezone when changed
    $('#ssm_timezone').on('change', function() {
        updateServerTime();
    });

    setInterval(updateServerTime, 1000);
    updateServerTime();
    
    // Countdown timer for General Settings
    <?php if ($start_dt && $end_dt && isset($countdown_seconds) && $countdown_seconds > 0) : ?>
    var countdownTarget = <?php echo ($now < $start_dt ? $start_dt->getTimestamp() : $end_dt->getTimestamp()); ?>;
    var isCountingToStart = <?php echo ($now < $start_dt ? 'true' : 'false'); ?>;
    
    function updateCountdownGeneral() {
        var now = Math.floor(Date.now() / 1000);
        var remaining = countdownTarget - now;
        
        if (remaining <= 0) {
            $('#countdown-timer-general').hide();
            if (isCountingToStart) {
                $('#countdown-to-start').text('0 seconds');
            } else {
                $('#countdown-to-end').text('0 seconds');
            }
            // Auto-refresh ONCE when countdown ends (avoid infinite reload loops)
            try {
                var reloadKey = 'ssm_countdown_reload_general_' + countdownTarget + '_' + (isCountingToStart ? 'start' : 'end');
                if (!sessionStorage.getItem(reloadKey)) {
                    sessionStorage.setItem(reloadKey, '1');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            } catch (e) {
                // Fallback: if sessionStorage is unavailable, do not auto-reload to avoid loops
            }
            return;
        }
        
        var days = Math.floor(remaining / 86400);
        var hours = Math.floor((remaining % 86400) / 3600);
        var minutes = Math.floor((remaining % 3600) / 60);
        var seconds = remaining % 60;
        
        $('#countdown-days').text(days);
        $('#countdown-hours').text(hours);
        $('#countdown-minutes').text(minutes);
        $('#countdown-seconds').text(seconds);
        
        var timeString = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
        if (isCountingToStart) {
            $('#countdown-to-start').text(timeString);
        } else {
            $('#countdown-to-end').text(timeString);
        }
        
        $('#countdown-timer-general').show();
    }
    
    updateCountdownGeneral();
    setInterval(updateCountdownGeneral, 1000);
    <?php endif; ?>
    
    // Countdown timer for Debug tab
    <?php if ($start_dt && $end_dt && isset($debug_countdown_seconds) && $debug_countdown_seconds > 0) : ?>
    var countdownTargetDebug = <?php echo ($now < $start_dt ? $start_dt->getTimestamp() : $end_dt->getTimestamp()); ?>;
    var isCountingToStartDebug = <?php echo ($now < $start_dt ? 'true' : 'false'); ?>;
    
    function updateCountdownDebug() {
        var now = Math.floor(Date.now() / 1000);
        var remaining = countdownTargetDebug - now;
        
        if (remaining <= 0) {
            $('#countdown-timer-debug').hide();
            if (isCountingToStartDebug) {
                $('#countdown-to-start-debug').text('0 seconds');
            } else {
                $('#countdown-to-end-debug').text('0 seconds');
            }
            // Auto-refresh ONCE when countdown ends (avoid infinite reload loops)
            try {
                var reloadKey = 'ssm_countdown_reload_debug_' + countdownTargetDebug + '_' + (isCountingToStartDebug ? 'start' : 'end');
                if (!sessionStorage.getItem(reloadKey)) {
                    sessionStorage.setItem(reloadKey, '1');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            } catch (e) {
                // Fallback: if sessionStorage is unavailable, do not auto-reload to avoid loops
            }
            return;
        }
        
        var days = Math.floor(remaining / 86400);
        var hours = Math.floor((remaining % 86400) / 3600);
        var minutes = Math.floor((remaining % 3600) / 60);
        var seconds = remaining % 60;
        
        $('#countdown-days-debug').text(days);
        $('#countdown-hours-debug').text(hours);
        $('#countdown-minutes-debug').text(minutes);
        $('#countdown-seconds-debug').text(seconds);
        
        var timeString = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
        if (isCountingToStartDebug) {
            $('#countdown-to-start-debug').text(timeString);
        } else {
            $('#countdown-to-end-debug').text(timeString);
        }
    }
    
    updateCountdownDebug();
    setInterval(updateCountdownDebug, 1000);
    <?php endif; ?>
});
</script>
<?php
}
