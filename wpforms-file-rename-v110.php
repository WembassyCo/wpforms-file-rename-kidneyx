<?php
/**
 * Plugin Name: WPForms File Rename - v1.10 Compatible
 * Description: Compatible with WPForms 1.10.0
 * Version: 1.0.0-v110
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

/**
 * WPForms 1.10 uses wpforms_process_before to modify data before save
 * and wpforms_process_after after save
 */

// Store info before processing
add_action('wpforms_process_before', 'wembassy_v110_before', 10, 2);

function wembassy_v110_before($entry, $form_data) {
    // Store team name
    $team = current_time('Y-m-d_H-i-s');
    
    if (isset($entry['fields']['2'])) {
        $val = $entry['fields']['2'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    
    // Store in transient for use after upload
    set_transient('wembassy_team_v110', $team, 300);
    
    // Log start
    file_put_contents(
        WP_CONTENT_DIR . '/wembassy-v110.log',
        date('Y-m-d H:i:s') . " BEFORE - Team: $team\n",
        FILE_APPEND
    );
}

// Process after entry is saved
add_action('wpforms_process_after', 'wembassy_v110_after', 10, 3);

function wembassy_v110_after($fields, $entry, $form_data) {
    $team = get_transient('wembassy_team_v110');
    if (!$team) {
        $team = current_time('Y-m-d_H-i-s');
    }
    delete_transient('wembassy_team_v110');
    
    $log = array();
    $log[] = date('Y-m-d H:i:s') . " AFTER - Entry saved";
    $log[] = "Team: $team";
    
    // Check for file uploads in fields
    foreach ($fields as $field_id => $field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $log[] = "File field found: $field_id";
        
        // Get file info
        $file_urls = isset($field['value']) ? $field['value'] : array();
        if (!is_array($file_urls)) {
            $file_urls = array($file_urls);
        }
        
        foreach ($file_urls as $url) {
            $log[] = "File URL: $url";
            
            if (empty($url)) continue;
            
            // Find the file
            $upload_dir = wp_upload_dir();
            $filename = basename($url);
            
            $paths = array(
                $upload_dir['path'] . '/' . $filename,
                $upload_dir['basedir'] . '/' . $filename,
                $upload_dir['basedir'] . '/2026/03/' . $filename,
            );
            
            $found = false;
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $log[] = "Found at: $path";
                    $found = true;
                    
                    // Get extension
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'pdf';
                    
                    // New name
                    $new_name = $team . '_FORM_KidneyXEmpower_Submission.' . $ext;
                    $new_path = dirname($path) . '/' . $new_name;
                    
                    // Ensure unique
                    $counter = 1;
                    while (file_exists($new_path)) {
                        $new_name = $team . '_FORM_KidneyXEmpower_Submission_' . $counter . '.' . $ext;
                        $new_path = dirname($path) . '/' . $new_name;
                        $counter++;
                    }
                    
                    // Rename
                    if (rename($path, $new_path)) {
                        $log[] = "SUCCESS: Renamed to $new_name";
                    } else {
                        $log[] = "FAILED to rename";
                    }
                    
                    break;
                }
            }
            
            if (!$found) {
                $log[] = "File not found at any location";
            }
        }
    }
    
    $log[] = "---";
    file_put_contents(WP_CONTENT_DIR . '/wembassy-v110.log', implode("\n", $log) . "\n", FILE_APPEND);
}

// Admin notice
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>WPForms File Rename v1.10 active. Log: ' . WP_CONTENT_DIR . '/wembassy-v110.log</p></div>';
});
