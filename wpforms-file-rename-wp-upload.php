<?php
/**
 * Plugin Name: WPForms File Rename - WordPress Upload
 * Description: Uses WordPress upload filter - works with any file upload
 * Version: 1.0.0-wp-upload
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

/**
 * Capture team name when WPForms processes
 */
add_action('wpforms_process', 'wembassy_capture_team_wp', 10, 3);

function wembassy_capture_team_wp($fields, $entry, $form_data) {
    // Store team name in a transient
    $team = current_time('Y-m-d_H-i-s');
    
    if (isset($_POST['wpforms']['fields']['2'])) {
        $val = $_POST['wpforms']['fields']['2'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    
    set_transient('wembassy_team_' . get_current_user_id(), $team, 60);
}

/**
 * Filter file uploads via WordPress
 */
add_filter('wp_handle_upload_prefilter', 'wembassy_filter_upload');

function wembassy_filter_upload($file) {
    // Check if we have a team name stored (meaning this is from WPForms)
    $team = get_transient('wembassy_team_' . get_current_user_id());
    
    if (!$team) {
        return $file;
    }
    
    // Check if this looks like a WPForms upload
    $is_wpforms = false;
    if (isset($_POST['wpforms']) || isset($_POST['wpforms_id'])) {
        $is_wpforms = true;
    }
    
    if (!$is_wpforms) {
        return $file;
    }
    
    // Get extension
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Build new filename
    $new_name = $team . '_FORM_KidneyXEmpower_Submission.' . $ext;
    
    // Update the file name
    $file['name'] = $new_name;
    
    return $file;
}

// Clean up transient after upload completes
add_filter('wp_handle_upload', 'wembassy_after_upload', 10, 2);

function wembassy_after_upload($upload, $context) {
    delete_transient('wembassy_team_' . get_current_user_id());
    return $upload;
}

// Admin notice
add_action('admin_notices', 'wembassy_upload_notice');
function wembassy_upload_notice() {
    echo '<div class="notice notice-info"><p>WPForms File Rename (WordPress Upload) is active</p></div>';
}
