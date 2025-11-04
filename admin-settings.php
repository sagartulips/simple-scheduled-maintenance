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
    // If multilingual plugin is active, use it
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
        echo "<div class='updated'><p><strong>Image removed successfully.</strong></p></div>";
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
        echo "<div class='updated'><p><strong>Language configuration reset. Please configure languages again.</strong></p></div>";
    }
    
    // Check if plugin was just activated
    $just_activated = get_option('ssm_plugin_just_activated', false);
    if ($just_activated) {
        delete_option('ssm_plugin_just_activated');
    }
    
    $languages_configured = get_option('ssm_languages_configured', false);
    
    // Handle language configuration mode
    if (isset($_POST['ssm_set_language_mode'])) {
        check_admin_referer('ssm_language_mode_action', 'ssm_language_mode_nonce');
        
        $mode = sanitize_text_field($_POST['ssm_language_mode']);
        update_option('ssm_language_mode', $mode);
        
        if ($mode === 'automatic') {
            // Auto-detect from WPML/Polylang
            update_option('ssm_languages_configured', true);
        } elseif ($mode === 'manual') {
            // Will be configured in next step
            update_option('ssm_languages_configured', false);
        }
        
        echo "<div class='updated'><p><strong>Language mode set.</strong></p></div>";
    }
    
    // Handle manual language configuration
    if (isset($_POST['ssm_configure_languages']) && !$is_multilingual) {
        check_admin_referer('ssm_configure_languages_action', 'ssm_configure_languages_nonce');
        
        $languages = [];
        $default_lang = sanitize_text_field($_POST['ssm_default_language']);
        
        if (isset($_POST['ssm_languages']) && is_array($_POST['ssm_languages'])) {
            foreach ($_POST['ssm_languages'] as $lang_data) {
                if (!empty($lang_data['code']) && !empty($lang_data['name'])) {
                    $code = sanitize_key($lang_data['code']);
                    $name = sanitize_text_field($lang_data['name']);
                    $languages[$code] = $name;
                }
            }
        }
        
        // If no languages configured, add default English
        if (empty($languages)) {
            $languages['en'] = 'English';
            $default_lang = 'en';
        }
        
        update_option('ssm_configured_languages', $languages);
        update_option('ssm_default_language', $default_lang);
        update_option('ssm_languages_configured', true);
        
        echo "<div class='updated'><p><strong>Languages configured.</strong></p></div>";
    }
    
    // Handle main settings save
    if (isset($_POST['ssm_save'])) {
        check_admin_referer('ssm_save_action', 'ssm_save_nonce');

        update_option('ssm_enabled', isset($_POST['ssm_enabled']) ? 1 : 0);
        update_option('ssm_start_time', sanitize_text_field($_POST['ssm_start_time']));
        update_option('ssm_end_time', sanitize_text_field($_POST['ssm_end_time']));
        update_option('ssm_timezone', sanitize_text_field($_POST['ssm_timezone']));
        update_option('ssm_show_countdown', isset($_POST['ssm_show_countdown']) ? 1 : 0);
        
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

        echo "<div class='updated'><p><strong>Settings saved.</strong></p></div>";
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
    $languages       = ssm_get_available_languages();
    $default_lang    = get_option('ssm_default_language', 'en');
    $configured_langs = ssm_get_configured_languages();
    $language_mode   = get_option('ssm_language_mode', $is_multilingual ? 'automatic' : 'manual');
    
    // Check if languages need to be configured
    $needs_config = !$languages_configured && (!$is_multilingual || $language_mode === 'manual');

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
?>
<div class="wrap">
    <h1>Simple Scheduled Maintenance</h1>
    
    <?php if ($needs_config || $just_activated) : ?>
        <!-- Language Configuration Modal -->
        <div id="ssm-language-modal" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
                <h2 style="margin-top: 0;"><?php echo $just_activated ? 'Welcome! Language Configuration' : 'Reconfigure Languages'; ?></h2>
                <p>Choose how to configure languages for your maintenance messages:</p>
                
                <form method="post" id="ssm-language-mode-form">
                    <?php wp_nonce_field('ssm_language_mode_action', 'ssm_language_mode_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th>
                                <label>
                                    <input type="radio" name="ssm_language_mode" value="automatic" <?php checked($is_multilingual); ?> <?php echo !$is_multilingual ? 'disabled' : ''; ?>>
                                    <strong>Automatic Detection</strong>
                                </label>
                            </th>
                            <td>
                                <?php if ($is_multilingual) : ?>
                                    <p>Languages will be automatically detected from your multilingual plugin (<?php echo function_exists('icl_get_languages') ? 'WPML' : 'Polylang'; ?>).</p>
                                <?php else : ?>
                                    <p style="color: #999;">No multilingual plugin detected. Please use manual configuration.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <input type="radio" name="ssm_language_mode" value="manual" <?php checked(!$is_multilingual || $language_mode === 'manual'); ?>>
                                    <strong>Manual Configuration</strong>
                                </label>
                            </th>
                            <td>
                                <p>Manually configure languages with codes and names.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="ssm_set_language_mode" class="button button-primary" value="Continue">
                    </p>
                </form>
            </div>
        </div>
        
        <?php if ($language_mode === 'manual') : ?>
            <!-- Manual Language Configuration -->
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
                                    <?php foreach ($configured_langs as $code => $name) : ?>
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
                        <?php foreach ($configured_langs as $code => $name) : ?>
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
    <?php endif; ?>
    
    <?php if (!$needs_config) : ?>
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper" style="margin: 20px 0;">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general">General Settings</a>
            <a href="#default-lang" class="nav-tab" data-tab="default-lang">Default Language (<?php echo esc_html(strtoupper($default_lang)); ?>)</a>
            <?php foreach ($languages as $lang_code => $lang_name) : ?>
                <?php if ($lang_code !== $default_lang) : ?>
                    <a href="#lang-<?php echo esc_attr($lang_code); ?>" class="nav-tab" data-tab="lang-<?php echo esc_attr($lang_code); ?>"><?php echo esc_html($lang_name); ?> (<?php echo esc_html(strtoupper($lang_code)); ?>)</a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="#debug" class="nav-tab" data-tab="debug">Debug Info</a>
        </nav>
        
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
                            <input type="datetime-local" id="ssm_start_time" name="ssm_start_time" value="<?php echo esc_attr($start); ?>" required>
                            <p class="description">Enter date and time in the timezone selected below.</p>
                        </td>
            </tr>
            <tr>
                <th><label for="ssm_end_time">End Date/Time</label></th>
                        <td>
                            <input type="datetime-local" id="ssm_end_time" name="ssm_end_time" value="<?php echo esc_attr($end); ?>" required>
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
                                    $status_html = '<span style="color:green; font-weight: bold;">Ended</span> - Ended ' . human_time_diff($end_dt->getTimestamp(), $now->getTimestamp()) . ' ago';
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
                    <?php if ($enabled && $start && $end) : ?>
                    <tr>
                        <th>Preview Maintenance Page</th>
                        <td>
                            <?php
                            $home_url = home_url();
                            $preview_url = add_query_arg('ssm_preview', '1', $home_url);
                            ?>
                            <a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button button-secondary">
                                View Maintenance Page (Opens in new tab)
                            </a>
                            <p class="description">
                                <strong>Note:</strong> As an admin, you bypass the maintenance page by default. Click this link to preview what regular visitors will see.
                                Regular users (not logged in) will automatically see the maintenance page when it's active.
                            </p>
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
                <button type="button" id="ssm-reconfigure-btn" class="button">Reconfigure Languages</button>
            </p>
    </form>

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
            <h2 style="color: #dc3232;">⚠️ Remove All Plugin Data (Danger Zone)</h2>
            <p><strong>Warning:</strong> This will permanently delete all maintenance settings, language configurations, and messages. This action cannot be undone.</p>
            <form method="post" onsubmit="return confirm('Are you absolutely sure you want to delete ALL plugin data? This cannot be undone!');">
                <?php wp_nonce_field('ssm_remove_data_action', 'ssm_remove_data_nonce'); ?>
                <label>
                    <input type="checkbox" name="ssm_confirm_remove" value="1" required>
                    I understand this will delete all plugin data permanently
                </label>
                <p class="submit">
                    <input type="submit" name="ssm_remove_data" class="button button-secondary" value="Remove All Data" style="background: #dc3232; color: white; border-color: #dc3232;">
                </p>
            </form>
        </div>
        
        <!-- Toggle for Data Removal Section -->
        <div style="margin-top: 20px;">
            <button type="button" id="show-data-removal" class="button" style="color: #dc3232;">Show Data Removal Options</button>
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
                                echo '<span style="color:green;">Ended</span> - Ended ' . human_time_diff($end_dt->getTimestamp(), $now->getTimestamp()) . ' ago';
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
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
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
    
    // Reconfigure button handler
    $('#ssm-reconfigure-btn').on('click', function() {
        if (confirm('This will reset your language configuration. Continue?')) {
            window.location.href = '?page=ssm-settings&reconfigure=1';
        }
    });
    
    // Show/hide data removal section
    $('#show-data-removal').on('click', function() {
        var section = $('#data-removal-section');
        if (section.is(':visible')) {
            section.slideUp();
            $(this).text('Show Data Removal Options');
        } else {
            section.slideDown();
            $(this).text('Hide Data Removal Options');
        }
    });
    
    // Language configuration JavaScript
    var addLanguageBtn = $('#add-language');
    var languagesContainer = $('#languages-container');
    
    if (addLanguageBtn.length && languagesContainer.length) {
        addLanguageBtn.on('click', function() {
            var row = $('<div class="language-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">' +
                       '<input type="text" name="ssm_languages[][code]" placeholder="Code (e.g., en, sv)" style="width: 120px;" required>' +
                       '<input type="text" name="ssm_languages[][name]" placeholder="Language Name (e.g., English)" style="flex: 1;" required>' +
                       '<button type="button" class="button remove-language" style="color: #dc3232;">Remove</button>' +
                       '</div>');
            languagesContainer.append(row);
            
            row.find('.remove-language').on('click', function() {
                if (languagesContainer.children().length > 1) {
                    row.remove();
                } else {
                    alert('You must have at least one language configured.');
                }
            });
        });
        
        // Add remove functionality to existing rows
        languagesContainer.on('click', '.remove-language', function() {
            if (languagesContainer.children().length > 1) {
                $(this).closest('.language-row').remove();
            } else {
                alert('You must have at least one language configured.');
            }
        });
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
            // Auto-refresh when countdown ends (with 1 second delay)
            setTimeout(function() {
                location.reload();
            }, 1000);
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
            // Auto-refresh when countdown ends (with 1 second delay)
            setTimeout(function() {
                location.reload();
            }, 1000);
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
