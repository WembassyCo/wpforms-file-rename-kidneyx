<?php
/**
 * Plugin Name: WPForms File Rename v5 - Clean Version
 * Description: Clean version without syntax errors
 * Version: 1.0.5-clean
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main function - runs after form is complete
 */
add_action('wpforms_process_complete', 'wembassy_file_rename_v5', 10, 4);

function wembassy_file_rename_v5($fields, $entry, $form_data, $entry_id) {
    // Only proceed if we have an entry ID
    if (empty($entry_id)) {
        return;
    }
    
    // Store debug messages
    $messages = array();
    $messages[] = '=== WEMBASSY v5 ===';
    $messages[] = 'Entry ID: ' . $entry_id;
    
    // Get form info
    $form_title = 'Form';
    if (isset($form_data['settings']['form_title'])) {
        $form_title = $form_data['settings']['form_title'];
    }
    
    $abbrev = wembassy_v5_get_abbrev($form_title);
    
    // Get team name from POST data
    $team_name = '';
    if (isset($_POST['wpforms']['fields']['2'])) {
        $team_name = sanitize_file_name($_POST['wpforms']['fields']['2']);
        $messages[] = 'Team: ' . $team_name;
    }
    
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        $messages[] = 'Using timestamp';
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Find file upload fields
    $upload_dir = wp_upload_dir();
    $files_renamed = 0;
    
    foreach ($fields as $field_id => $field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $messages[] = 'Field ' . $field_id . ' is file upload';
        
        $files = isset($field['value']) ? $field['value'] : array();
        if (!is_array($files)) {
            $files = array($files);
        }
        
        $new_files = array();
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                continue;
            }
            
            $messages[] = 'URL: ' . $file_url;
            
            // Get filename from URL
            $filename = basename($file_url);
            $messages[] = 'Filename: ' . $filename;
            
            // Try to find file - check multiple locations
            $file_path = false;
            
            // Option 1: Direct path from URL
            $test_path = str_replace(content_url(), WP_CONTENT_DIR, $file_url);
            if (file_exists($test_path)) {
                $file_path = $test_path;
                $messages[] = 'Found at: ' . $test_path;
            }
            
            // Option 2: In uploads directory
            if (!$file_path) {
                $test_path2 = $upload_dir['basedir'] . '/' . $filename;
                if (file_exists($test_path2)) {
                    $file_path = $test_path2;
                    $messages[] = 'Found in uploads: ' . $test_path2;
                }
            }
            
            // Option 3: Year/month subdirectory
            if (!$file_path) {
                $year = current_time('Y');
                $month = current_time('m');
                $test_path3 = $upload_dir['basedir'] . '/' . $year . '/' . $month . '/' . $filename;
                if (file_exists($test_path3)) {
                    $file_path = $test_path3;
                    $messages[] = 'Found in Y/M: ' . $test_path3;
                }
            }
            
            if (!$file_path) {
                $messages[] = 'ERROR: File not found';
                $new_files[] = $file_url;
                continue;
            }
            
            // Build new filename
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : 'pdf';
            
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission.' . $extension;
            
            // Ensure unique
            $dir = dirname($file_path);
            $new_file_path = $dir . '/' . $new_filename;
            $counter = 1;
            while (file_exists($new_file_path)) {
                $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter . '.' . $extension;
                $new_file_path = $dir . '/' . $new_filename;
                $counter++;
            }
            
            // Rename
            if (rename($file_path, $new_file_path)) {
                $new_url = str_replace($file_path, '', $file_url);
                $new_url = $file_url . '/../' . $new_filename;
                $new_url = str_replace('/./', '/', $new_url);
                
                // Better way to build URL
                $base_url = content_url() . '/uploads/' . $year . '/' . $month . '/' . $new_filename;
                if (strpos($file_path, $year . '/' . $month) === false) {
                    $base_url = content_url() . '/uploads/' . $new_filename;
                }
                
                $new_files[] = $base_url;
                $files_renamed++;
                $messages[] = 'RENAME SUCCESS';
                
                // Delete old
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            } else {
                $messages[] = 'RENAME FAILED';
                $new_files[] = $file_url;
            }
        }
    }
    
    $messages[] = 'Files renamed: ' . $files_renamed;
    
    // Store for display
    set_transient('wembassy_v5_debug_' . get_current_user_id(), $messages, 60);
}

function wembassy_v5_get_abbrev($title) {
    $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $title);
    $abbrev = '';
    
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    
    $abbrev = substr($abbrev, 0, 5);
    
    if (empty($abbrev)) {
        $abbrev = 'KXE';
    }
    
    return $abbrev;
}

// Show on confirmation page
add_filter('wpforms_frontend_confirmation_message', 'wembassy_v5_show_debug', 10, 4);

function wembassy_v5_show_debug($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_v5_debug_' . get_current_user_id());
    
    if ($debug) {
        $html = '<div style="background:#f0f0f0;border:2px solid #2271b1;padding:15px;margin:20px 0;font-family:monospace;font-size:12px;white-space:pre-wrap;" class="wembassy-debug">';
        $html .= '<strong>File Rename v5:</strong>\n\n';
        $html .= implode("\n", $debug);
        $html .= '</div>';
        
        delete_transient('wembassy_v5_debug_' . get_current_user_id());
        
        return $message . $html;
    }
    
    return $message;
}
