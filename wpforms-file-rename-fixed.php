<?php
/**
 * Plugin Name: WPForms File Rename - Fixed
 * Description: Works with WPForms file upload system
 * Version: 1.0.0-fixed
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

/**
 * Hook into entry AFTER it's saved and files are uploaded
 */
function wembassy_fixed_entry_save($fields, $entry, $form_id, $form_data) {
    // Store messages for display
    static $messages = array();
    $messages[] = '=== ENTRY SAVE HOOK ===';
    $messages[] = 'Entry ID: ' . ($entry ? $entry['entry_id'] : 'none');
    $messages[] = 'Form ID: ' . $form_id;
    
    // Get team name
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    $messages[] = 'Team: ' . $team;
    
    // Get abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $form_title);
    $words = explode(' ', $title);
    $abbrev = '';
    foreach ($words as $w) {
        $w = trim($w);
        if (!empty($w)) {
            $abbrev .= strtoupper(substr($w, 0, 1));
        }
    }
    $abbrev = substr($abbrev, 0, 5) ?: 'FORM';
    $messages[] = 'Abbrev: ' . $abbrev;
    
    $team = sanitize_file_name($team);
    $abbrev = sanitize_file_name($abbrev);
    
    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    
    $messages[] = 'Upload path: ' . $upload_dir['path'];
    $messages[] = 'Upload URL: ' . $upload_dir['url'];
    
    // Process each field
    $files_found = 0;
    $files_renamed = 0;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $files_found++;
        $messages[] = '';
        $messages[] = '--- Field ' . $field_id . ' ---';
        
        $file_value = isset($field['value']) ? $field['value'] : '';
        $messages[] = 'File value: ' . print_r($file_value, true);
        
        if (empty($file_value)) {
            $messages[] = 'Empty file, skipping';
            continue;
        }
        
        // Parse file URL - could be array or string
        $file_urls = is_array($file_value) ? $file_value : array($file_value);
        $new_files = array();
        
        foreach ($file_urls as $file_url) {
            if (empty($file_url)) {
                $new_files[] = $file_url;
                continue;
            }
            
            $filename = basename($file_url);
            $messages[] = 'Filename: ' . $filename;
            
            // Find the file - try multiple paths
            $file_path = false;
            $tested_paths = array();
            
            // Parse year/month from URL or use current
            $url_parts = parse_url($file_url);
            $url_path = isset($url_parts['path']) ? $url_parts['path'] : '';
            
            // Try to extract year/month from URL path
            $year_month = '';
            if (preg_match('/uploads\/(\d{4})\/(\d{2})\//', $url_path, $matches)) {
                $year_month = $matches[1] . '/' . $matches[2] . '/';
            }
            
            $possible_paths = array(
                $upload_dir['basedir'] . '/' . $year_month . $filename,
                $upload_dir['basedir'] . '/2026/03/' . $filename,
                $upload_dir['basedir'] . '/' . date('Y') . '/' . date('m') . '/' . $filename,
                $upload_dir['basedir'] . '/' . $filename,
                $upload_dir['path'] . '/' . $filename,
            );
            
            foreach ($possible_paths as $path) {
                $messages[] = 'Checking: ' . $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND');
                $tested_paths[] = $path;
                if (file_exists($path)) {
                    $file_path = $path;
                    break;
                }
            }
            
            if (!$file_path) {
                $messages[] = 'ERROR: File not found at any location!';
                $new_files[] = $file_url;
                continue;
            }
            
            $messages[] = 'Found at: ' . $file_path;
            
            // Get extension
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if (empty($ext)) $ext = 'pdf';
            $messages[] = 'Extension: ' . $ext;
            
            // Build new filename
            $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission.' . $ext;
            $new_path = dirname($file_path) . '/' . $new_name;
            
            // Ensure unique
            $counter = 1;
            while (file_exists($new_path)) {
                $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter . '.' . $ext;
                $new_path = dirname($file_path) . '/' . $new_name;
                $counter++;
            }
            
            $messages[] = 'Will rename to: ' . $new_name;
            
            // Rename
            if (rename($file_path, $new_path)) {
                $messages[] = 'SUCCESS: File renamed!';
                $files_renamed++;
                
                // Build new URL
                $new_url = str_replace($filename, $new_name, $file_url);
                $new_files[] = $new_url;
                
                // Delete old if still exists
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            } else {
                $error = error_get_last();
                $messages[] = 'FAILED: ' . ($error ? $error['message'] : 'Unknown error');
                $new_files[] = $file_url;
            }
        }
        
        // Update field value
        $field['value'] = is_array($file_value) ? $new_files : $new_files[0];
        $messages[] = 'Updated field value';
    }
    
    $messages[] = '';
    $messages[] = '=== SUMMARY ===';
    $messages[] = 'File fields found: ' . $files_found;
    $messages[] = 'Files renamed: ' . $files_renamed;
    $messages[] = '=== END ===';
    
    // Store messages
    set_transient('wembassy_fixed_debug_' . get_current_user_id(), $messages, 60);
}

// Hook: This fires after entry is saved but WPForms uses it BEFORE notification
add_action('wpforms_process_entry_save', 'wembassy_fixed_entry_save', 10, 4);

// Display on confirmation
add_filter('wpforms_frontend_confirmation_message', 'wembassy_fixed_show', 10, 4);

function wembassy_fixed_show($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_fixed_debug_' . get_current_user_id());
    if ($debug) {
        $html = '<div style="background:#f9f9f9; border:3px solid #2271b1; padding:15px; margin:20px 0; font-family:monospace; font-size:11px; white-space:pre-wrap;">';
        $html .= '<strong>File Rename Debug:</strong>\n\n';
        $html .= implode("\n", $debug);
        $html .= '</div>';
        delete_transient('wembassy_fixed_debug_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
