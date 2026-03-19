<?php
/**
 * Plugin Name: WPForms File Rename - WordPress Upload Handler
 * Description: Hooks into WordPress upload process - should work with all WPForms versions
 * Version: 1.0.0-wordpress
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

/**
 * Store team name when form is submitted
 */
add_action('wpforms_process', 'wembassy_capture_team_name', 10, 3);

function wembassy_capture_team_name($fields, $entry, $form_data) {
    // Store team name in a global for later use
    global $wembassy_team_name;
    
    $wembassy_team_name = current_time('Y-m-d_H-i-s'); // Default
    
    // Try to get from field 2
    if (isset($_POST['wpforms']['fields']['2'])) {
        $team = sanitize_file_name($_POST['wpforms']['fields']['2']);
        if (!empty($team)) {
            $wembassy_team_name = $team;
        }
    }
}

/**
 * Filter uploaded files - WordPress core hook
 * This runs for ALL uploads, not just WPForms
 */
add_filter('wp_handle_upload_prefilter', 'wembassy_filter_upload_name');

function wembassy_filter_upload_name($file) {
    global $wembassy_team_name;
    
    // Only process if we have a team name stored (meaning we're in a WPForms submission)
    if (empty($wembassy_team_name)) {
        return $file;
    }
    
    // Get file extension
    $info = pathinfo($file['name']);
    $ext = isset($info['extension']) ? strtolower($info['extension']) : 'pdf';
    
    // Build new filename
    $new_name = $wembassy_team_name . '_FORM_KidneyXEmpower_Submission.' . $ext;
    
    // Check if this is from WPForms (check tmp_name for clues)
    if (strpos($file['tmp_name'], 'wpforms') !== false || isset($_POST['wpforms'])) {
        $log = "Renaming upload: " . $file['name'] . " -> " . $new_name . "\n";
        file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', $log, FILE_APPEND);
        
        $file['name'] = $new_name;
    }
    
    return $file;
}

/**
 * Alternative: Hook into WordPress upload
 */
add_filter('wp_handle_upload', 'wembassy_after_upload', 10, 2);

function wembassy_after_upload($upload, $context = 'upload') {
    global $wembassy_team_name;
    
    if (empty($wembassy_team_name)) {
        return $upload;
    }
    
    // Only process WPForms submissions
    if (isset($_POST['wpforms']) || strpos($upload['file'], 'wpforms') !== false) {
        file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', "Upload complete: " . $upload['file'] . "\n", FILE_APPEND);
    }
    
    return $upload;
}

// Admin notice
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>WPForms File Rename (WordPress Hook) active. Log: ' . WP_CONTENT_DIR . '/wembassy-rename.log</p></div>';
});
