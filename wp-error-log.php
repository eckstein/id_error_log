<?php
require_once plugin_dir_path(__FILE__) . 'id-config-transformer.php';
/*
Plugin Name: WP Error Log Viewer
Description: View and manage WordPress and PHP errors in the admin area
Version: 1.0
Author: Zac Eckstein, iD Tech
*/

// Add admin menu item
add_action('admin_menu', 'wp_error_log_menu');

function wp_error_log_menu() {
    add_menu_page('WP Error Log', 'Error Log', 'manage_options', 'wp-error-log', 'wp_error_log_page', 'dashicons-warning', 80);
}

// Create the error log page
function wp_error_log_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $error_log_file = WP_CONTENT_DIR . '/debug.log';

    echo '<div class="wrap wp-error-log-wrap">';
    echo '<h1>WordPress Debug Log</h1>';

    if (file_exists($error_log_file) && is_readable($error_log_file)) {
        echo '<div id="error-log-container">';
        echo '<textarea id="error-log-content" readonly></textarea>';
        echo '<div class="wp-error-log-actions">';
        echo '<button id="refresh-log" class="button">Refresh Log</button>';
        echo '<button id="clear-log" class="button button-primary">Clear Debug Log</button>';
        echo '</div>'; // Close .wp-error-log-actions
        echo '</div>'; // Close #error-log-container
    } else {
        echo '<p>Debug log file not found or not accessible. Make sure WP_DEBUG_LOG is set to true in your wp-config.php file.</p>';
    }

    echo '</div>'; // Close .wrap

    // Enqueue styles and scripts
    wp_enqueue_style('wp-error-log-styles', plugins_url('wp-error-log-styles.css', __FILE__));
    wp_enqueue_script('wp-error-log-scripts', plugins_url('wp-error-log-scripts.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('wp-error-log-scripts', 'wpErrorLog', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_error_log_nonce'),
    ));
}

// Optionally, add a custom error handler
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    $error_message = date('[Y-m-d H:i:s]') . " PHP Error: $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, ini_get('error_log'));
    return false;
}

set_error_handler('custom_error_handler');

// Add AJAX actions
add_action('wp_ajax_get_error_log', 'wp_error_log_get_content');
add_action('wp_ajax_clear_error_log', 'wp_error_log_clear');

function wp_error_log_get_content() {
    check_ajax_referer('wp_error_log_nonce', 'nonce');
    $error_log_file = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($error_log_file)) {
        wp_send_json_error(array('message' => 'Debug log file does not exist.'));
    }
    
    if (!is_readable($error_log_file)) {
        wp_send_json_error(array('message' => 'Debug log file is not readable.'));
    }
    
    $log_content = file_get_contents($error_log_file);
    
    if ($log_content === false) {
        wp_send_json_error(array('message' => 'Failed to read debug log file.'));
    }
    
    wp_send_json_success(array('content' => $log_content ?: 'Log file is empty.'));
}

function wp_error_log_clear() {
    check_ajax_referer('wp_error_log_nonce', 'nonce');
    $error_log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($error_log_file) && is_writable($error_log_file)) {
        file_put_contents($error_log_file, '');
        wp_send_json_success(array('message' => 'Debug log cleared successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Debug log file not found or not accessible.'));
    }
}

// Add settings page
add_action('admin_menu', 'wp_error_log_settings_menu');

function wp_error_log_settings_menu() {
    add_submenu_page('wp-error-log', 'Debug Settings', 'Debug Settings', 'manage_options', 'wp-error-log-settings', 'wp_error_log_settings_page');
}

// Add this function to create the debug.log file if it doesn't exist
function wp_error_log_ensure_log_file() {
    $error_log_file = WP_CONTENT_DIR . '/debug.log';
    if (!file_exists($error_log_file)) {
        $result = @file_put_contents($error_log_file, '');
        if ($result === false) {
            add_settings_error('wp_error_log_settings', 'log_file_create_failed', 'Failed to create debug.log file. Please check permissions for the wp-content directory.', 'error');
            return false;
        }
    }
    return true;
}

// Update the settings page function
function wp_error_log_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Ensure debug.log file exists
    wp_error_log_ensure_log_file();

    $config_transformer = new WP_Config_Transformer();

    if (isset($_POST['wp_error_log_settings_nonce']) && wp_verify_nonce($_POST['wp_error_log_settings_nonce'], 'wp_error_log_settings')) {
        $wp_debug = isset($_POST['wp_debug']) ? 'true' : 'false';
        $wp_debug_log = isset($_POST['wp_debug_log']) ? 'true' : 'false';
        $wp_debug_display = isset($_POST['wp_debug_display']) ? 'true' : 'false';

        try {
            $config_transformer->update('constant', 'WP_DEBUG', $wp_debug, array('raw' => true));
            $config_transformer->update('constant', 'WP_DEBUG_LOG', $wp_debug_log, array('raw' => true));
            $config_transformer->update('constant', 'WP_DEBUG_DISPLAY', $wp_debug_display, array('raw' => true));
            add_settings_error('wp_error_log_settings', 'settings_updated', 'Debug settings updated successfully.', 'success');
        } catch (Exception $e) {
            add_settings_error('wp_error_log_settings', 'update_failed', 'Failed to update wp-config.php: ' . $e->getMessage(), 'error');
        }
    }

    // Get current values
    try {
        $wp_debug = $config_transformer->exists('constant', 'WP_DEBUG') ? $config_transformer->get_value('constant', 'WP_DEBUG') : 'false';
        $wp_debug_log = $config_transformer->exists('constant', 'WP_DEBUG_LOG') ? $config_transformer->get_value('constant', 'WP_DEBUG_LOG') : 'false';
        $wp_debug_display = $config_transformer->exists('constant', 'WP_DEBUG_DISPLAY') ? $config_transformer->get_value('constant', 'WP_DEBUG_DISPLAY') : 'false';
    } catch (Exception $e) {
        add_settings_error('wp_error_log_settings', 'read_failed', 'Failed to read wp-config.php: ' . $e->getMessage(), 'error');
        $wp_debug = $wp_debug_log = $wp_debug_display = 'false';
    }

    ?>
    <div class="wrap wp-error-log-wrap wp-error-log-settings">
        <h1>Debug Settings</h1>
        <?php settings_errors('wp_error_log_settings'); ?>
        <form method="post">
            <?php wp_nonce_field('wp_error_log_settings', 'wp_error_log_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wp_debug">Enable WP_DEBUG</label></th>
                    <td><input type="checkbox" id="wp_debug" name="wp_debug" <?php checked($wp_debug, 'true'); ?>></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_debug_log">Enable WP_DEBUG_LOG</label></th>
                    <td><input type="checkbox" id="wp_debug_log" name="wp_debug_log" <?php checked($wp_debug_log, 'true'); ?>></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp_debug_display">Enable WP_DEBUG_DISPLAY</label></th>
                    <td><input type="checkbox" id="wp_debug_display" name="wp_debug_display" <?php checked($wp_debug_display, 'true'); ?>></td>
                </tr>
            </table>
            <?php submit_button('Save Changes'); ?>
        </form>
    </div>
    <?php
}
