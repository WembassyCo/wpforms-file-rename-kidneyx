<?php
/**
 * Plugin Name: WPForms File Rename - Upload Hook
 * Description: Uses the file upload hook directly
 * Version: 1.0.0-upload
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Hook into file upload process
add_filter('wpforms_process_field_entry', 'wembassy_rename_on_upload', 10, 6);

function wembassy_rename_on_upload($entry, $field, $field_data, $form_id, $form_data, $entry_id) {
    // Only process file upload fields
    if ($field['type'] !== 'file-upload') {
        return $entry;
    }
    
    // Get file info
    if (empty($entry['value'])) {
        return $entry;
    }
    
    // Store debug info
    $log = array();
    $log[] = date('Y-m-d H:i:s') . ' - File upload detected';
    
    // Get file URL from entry
    $file_url = $entry['value'];
    $log[] = 'URL: ' . $file_url;
    
    // Build filename
    $team_name = 'Team';
    $abbrev = 'FORM';
    
    // Check POST data for team name
    if (isset($_POST['wpforms']['fields'])) {
        foreach ($_POST['wpforms']['fields'] as $fid => $val) {
            if (is_string($val) && strlen($val) > 0 && strlen($val) < 100) {
                // Could be team field
                if ($fid === '2') {
                    $team_name = sanitize_file_name($val);
                    $log[] = 'Team from field 2: ' . $team_name;
                    break;
                }
            }
        }
    }
    
    // Get extension
    $ext = pathinfo($file_url, PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (empty($ext)) $ext = 'pdf';
    
    // Create new filename
    $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission.' . $ext;
    $log[] = 'New filename: ' . $new_filename;
    
    // Find file in upload directory
    $upload_dir = wp_upload_dir();
    $filename = basename($file_url);
    $source_path = $upload_dir['path'] . '/' . $filename;
    
    $log[] = 'Looking for: ' . $source_path;
    
    // If not in current month, try basedir
    if (!file_exists($source_path)) {
        $source_path = $upload_dir['basedir'] . '/' . $filename;
        $log[] = 'Try basedir: ' . $source_path;
    }
    
    // Try year/month
    if (!file_exists($source_path)) {
        $source_path = $upload_dir['basedir'] . '/2026/03/' . $filename;
        $log[] = 'Try Y/M: ' . $source_path;
    }
    
    // Write log
    file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', implode("\n", $log) . "\n---\n", FILE_APPEND);
    
    // Check if file exists
    if (!file_exists($source_path)) {
        file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', "ERROR: File not found at " . $source_path . "\n---\n", FILE_APPEND);
        return $entry;
    }
    
    // Check for duplicates
    $counter = 1;
    $new_path = $upload_dir['path'] . '/' . $new_filename;
    while (file_exists($new_path)) {
        $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter . '.' . $ext;
        $new_path = $upload_dir['path'] . '/' . $new_filename;
        $counter++;
    }
    
    // Rename file
    if (rename($source_path, $new_path)) {
        file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', "SUCCESS: Renamed to " . $new_path . "\n---\n", FILE_APPEND);
        
        // Update entry with new URL
        $entry['value'] = $upload_dir['url'] . '/' . $new_filename;
    } else {
        file_put_contents(WP_CONTENT_DIR . '/wembassy-rename.log', "FAILED: Could not rename\n---\n", FILE_APPEND);
    }
    
    return $entry;
}

// Admin notice
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>WPForms File Rename (Upload Hook) is active</p></div>';
});
