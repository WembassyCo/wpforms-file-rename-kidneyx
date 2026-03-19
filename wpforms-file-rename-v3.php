<?php
/**
 * Plugin Name: WPForms File Rename - Using Correct WordPress Paths
 * Description: Uses WordPress functions to get correct file paths
 * Version: 1.0.2-wp-paths
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('wpforms_process_entry_save', 'wembassy_file_rename_v3', 10, 4);

function wembassy_file_rename_v3($fields, $entry, $form_id, $form_data) {
    $messages = [];
    $messages[] = '=== WEMBASSY FILE RENAME v3 ===';
    $messages[] = 'Form ID: ' . $form_id;
    $messages[] = '';
    
    // Get abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_v3_get_abbrev($form_title);
    $messages[] = 'Form Title: ' . $form_title;
    $messages[] = 'Abbreviation: ' . $abbrev;
    
    // Find team name
    $team_name = '';
    foreach ($fields as $field_id => $field) {
        // Look in field value for team name
        if (isset($field['value']) && !is_array($field['value'])) {
            $val = trim($field['value']);
            // Check if this looks like a team name (not email, not URL)
            if (strlen($val) > 2 && strlen($val) < 50 && !strpos($val, '@') && !strpos($val, 'http')) {
                // Field 2 is commonly the team name in this form
                if ($field_id == '2') {
                    $team_name = $val;
                    $messages[] = 'Team Name (field 2): ' . $team_name;
                    break;
                }
            }
        }
    }
    
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        $messages[] = 'Using timestamp: ' . $team_name;
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Process file uploads
    $files_renamed = 0;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $messages[] = '';
        $messages[] = '--- Field ' . $field_id . ' ---';
        
        $files = is_array($field['value']) ? $field['value'] : [$field['value']];
        $new_files = [];
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                $messages[] = 'Empty URL - skipping';
                continue;
            }
            
            $messages[] = 'URL: ' . $file_url;
            
            // Use WordPress to convert URL to path
            $file_path = wembassy_v3_url_to_path($file_url);
            
            if (!$file_path) {
                $messages[] = 'ERROR: Could not convert URL to path';
                $new_files[] = $file_url;
                continue;
            }
            
            $messages[] = 'Converted path: ' . $file_path;
            
            // Check if file exists
            if (!file_exists($file_path)) {
                $messages[] = 'ERROR: File does not exist!';
                
                // Try alternative: maybe it's a temp file
                $upload_dir = wp_upload_dir();
                $filename = basename($file_url);
                $alt_path = $upload_dir['basedir'] . '/' . $filename;
                
                if (file_exists($alt_path)) {
                    $messages[] = 'Found at alternative path: ' . $alt_path;
                    $file_path = $alt_path;
                } else {
                    $new_files[] = $file_url;
                    continue;
                }
            }
            
            $messages[] = 'File exists: YES';
            
            // Get extension
            $file_info = wp_check_filetype($file_path);
            $extension = isset($file_info['ext']) ? $file_info['ext'] : '';
            $messages[] = 'Extension: ' . $extension;
            
            // Build new filename
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if (!empty($extension)) {
                $new_filename .= '.' . $extension;
            }
            
            // Get destination directory from original file
            $dir = dirname($file_path);
            $new_file_path = $dir . '/' . $new_filename;
            
            // Ensure unique
            $counter = 1;
            while (file_exists($new_file_path)) {
                $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter;
                if (!empty($extension)) {
                    $new_filename .= '.' . $extension;
                }
                $new_file_path = $dir . '/' . $new_filename;
                $counter++;
            }
            
            $messages[] = 'New filename: ' . $new_filename;
            
            // Rename
            if (rename($file_path, $new_file_path)) {
                // Convert back to URL
                $new_url = wembassy_v3_path_to_url($new_file_path);
                $new_files[] = $new_url;
                $files_renamed++;
                
                $messages[] = 'SUCCESS: Renamed to ' . $new_url;
                
                // Delete original (rename would have moved it, but just in case)
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            } else {
                $error = error_get_last();
                $messages[] = 'FAILED: ' . ($error['message'] ?? 'Unknown');
                $new_files[] = $file_url;
            }
        }
        
        // Update field
        $field['value'] = $new_files;
    }
    
    $messages[] = '';
    $messages[] = '=== SUMMARY ===';
    $messages[] = 'Files renamed: ' . $files_renamed;
    $messages[] = '=== END ===';
    
    set_transient('wembassy_v3_debug_' . get_current_user_id(), $messages, 60);
}

/**
 * Convert URL to path using WordPress
 */
function wembassy_v3_url_to_path($url) {
    // Remove protocol for comparison
    $url_no_protocol = preg_replace('#^https?://#', '', $url);
    $site_url_no_protocol = preg_replace('#^https?://#', '', site_url());
    
    // Check if URL starts with site URL
    if (strpos($url_no_protocol, $site_url_no_protocol) === 0) {
        // URL is on this site
        $relative_path = substr($url_no_protocol, strlen($site_url_no_protocol));
        $relative_path = ltrim($relative_path, '/');
        return ABSPATH . $relative_path;
    }
    
    // Try content URL
    $content_url_no_protocol = preg_replace('#^https?://#', '', content_url());
    if (strpos($url_no_protocol, $content_url_no_protocol) === 0) {
        $relative_path = substr($url_no_protocol, strlen($content_url_no_protocol));
        $relative_path = ltrim($relative_path, '/');
        return WP_CONTENT_DIR . '/' . $relative_path;
    }
    
    // Try upload URL
    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['baseurl'])) {
        $upload_url_no_protocol = preg_replace('#^https?://#', '', $upload_dir['baseurl']);
        if (strpos($url_no_protocol, $upload_url_no_protocol) === 0) {
            $relative_path = substr($url_no_protocol, strlen($upload_url_no_protocol));
            $relative_path = ltrim($relative_path, '/');
            return $upload_dir['basedir'] . '/' . $relative_path;
        }
    }
    
    // Fallback: try to guess from URL structure
    $upload_dir = wp_upload_dir();
    if (strpos($url, '/wp-content/uploads/') !== false) {
        $parts = explode('/wp-content/uploads/', $url);
        if (isset($parts[1])) {
            return $upload_dir['basedir'] . '/' . $parts[1];
        }
    }
    
    return false;
}

/**
 * Convert path to URL using WordPress
 */
function wembassy_v3_path_to_url($path) {
    $upload_dir = wp_upload_dir();
    
    // Check if in uploads
    if (strpos($path, $upload_dir['basedir']) === 0) {
        $relative = substr($path, strlen($upload_dir['basedir']));
        return $upload_dir['baseurl'] . $relative;
    }
    
    // Check if in content
    if (strpos($path, WP_CONTENT_DIR) === 0) {
        $relative = substr($path, strlen(WP_CONTENT_DIR));
        return content_url() . $relative;
    }
    
    // Check if in ABSPATH
    if (strpos($path, ABSPATH) === 0) {
        $relative = substr($path, strlen(ABSPATH));
        return site_url('/') . $relative;
    }
    
    return $path;
}

function wembassy_v3_get_abbrev($title) {
    $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $clean);
    $abbrev = '';
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($abbrev, 0, 5) ?: 'KXE';
}

// Display debug
add_filter('wpforms_frontend_confirmation_message', 'wembassy_v3_show_debug', 10, 4);
function wembassy_v3_show_debug($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_v3_debug_' . get_current_user_id());
    if ($debug) {
        $html = '<div style="background:#f9f9f9; border:3px solid #2271b1; padding:15px; margin:20px 0; font-family:monospace; font-size:12px; white-space:pre-wrap;">';
        $html .= '<strong>File Rename v3:</strong>\n\n';
        $html .= implode("\n", $debug);
        $html .= '</div>';
        delete_transient('wembassy_v3_debug_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
