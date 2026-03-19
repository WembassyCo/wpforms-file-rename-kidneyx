<?php
/**
 * Plugin Name: WPForms File Rename - Page Debug Version
 * Description: Debug version that shows messages on page after submission
 * Version: 1.0.0-page-debug
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Store debug messages
add_action('wpforms_process_entry_save', 'wembassy_page_debug_entry_save', 1, 4);

function wembassy_page_debug_entry_save($fields, $entry, $form_id, $form_data) {
    $messages = [];
    $messages[] = '=== WEMBASSY FILE RENAME DEBUG ===';
    $messages[] = 'Form ID: ' . $form_id;
    $messages[] = 'Form Title: ' . (isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'N/A');
    $messages[] = 'Total Fields: ' . count($fields);
    $messages[] = '';
    
    // Check each field
    $file_field_found = false;
    foreach ($fields as $field_id => $field) {
        $type = isset($field['type']) ? $field['type'] : 'unknown';
        $label = isset($field['label']) ? $field['label'] : 'no label';
        $messages[] = 'Field ' . $field_id . ': Type=' . $type . ', Label=' . $label;
        
        if ($type === 'file-upload') {
            $file_field_found = true;
            $messages[] = '*** FILE UPLOAD FOUND ***';
            $messages[] = 'Value: ' . print_r($field['value'], true);
        }
    }
    
    if (!$file_field_found) {
        $messages[] = '';
        $messages[] = 'ERROR: No file upload field found!';
    }
    
    $messages[] = '';
    $messages[] = '=== END DEBUG ===';
    
    // Store messages in transient
    set_transient('wembassy_debug_messages_' . get_current_user_id(), $messages, 60);
}

/**
 * Display debug messages on confirmation
 */
add_filter('wpforms_frontend_confirmation_message', 'wembassy_show_debug_messages', 10, 4);

function wembassy_show_debug_messages($message, $form_data, $fields, $entry_id) {
    $debug_messages = get_transient('wembassy_debug_messages_' . get_current_user_id());
    
    if ($debug_messages) {
        $debug_html = '<div style="background:#f0f0f0; border:2px solid #0073aa; padding:20px; margin:20px 0; font-family:monospace; white-space:pre-wrap;" class="wembassy-debug-output">';
        $debug_html .= '<strong>WPForms File Rename Debug Output:</strong>\n\n';
        $debug_html .= implode("\n", $debug_messages);
        $debug_html .= '</div>';
        
        // Clear the transient
        delete_transient('wembassy_debug_messages_' . get_current_user_id());
        
        return $message . $debug_html;
    }
    
    return $message;
}

/**
 * Also hook into process to attempt rename and show results
 */
add_action('wpforms_process_entry_save', 'wembassy_page_debug_attempt_rename', 20, 4);

function wembassy_page_debug_attempt_rename($fields, $entry, $form_id, $form_data) {
    $messages = get_transient('wembassy_debug_messages_' . get_current_user_id());
    if (!$messages) {
        $messages = [];
    }
    
    $messages[] = '';
    $messages[] = '=== ATTEMPTING FILE RENAME ===';
    
    $upload_dir = wp_upload_dir();
    $messages[] = 'Upload Path: ' . $upload_dir['path'];
    $messages[] = 'Upload URL: ' . $upload_dir['url'];
    $messages[] = '';
    
    // Get form abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_page_debug_get_abbrev($form_title);
    $messages[] = 'Abbreviation: ' . $abbrev;
    
    // Find team name
    $team_name = '';
    foreach ($fields as $field_id => $field) {
        if (isset($field['label']) && stripos($field['label'], 'team') !== false) {
            $team_name = isset($field['value']) ? $field['value'] : '';
            if (is_array($team_name)) {
                $team_name = implode(' ', $team_name);
            }
            $messages[] = 'Team Name found in field ' . $field_id . ': ' . $team_name;
            break;
        }
    }
    
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        $messages[] = 'No Team Name field, using timestamp: ' . $team_name;
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Process files
    $rename_attempted = false;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $rename_attempted = true;
        $files = is_array($field['value']) ? $field['value'] : [$field['value']];
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                $messages[] = 'Empty file URL, skipping';
                continue;
            }
            
            $messages[] = '';
            $messages[] = 'Processing file: ' . $file_url;
            
            // Convert URL to path
            $file_path = str_replace($upload_dir['url'], $upload_dir['path'], $file_url);
            $messages[] = 'Converted to path: ' . $file_path;
            
            // Check if exists
            if (!file_exists($file_path)) {
                $messages[] = 'ERROR: File does not exist at path!';
                
                // Try to find file in alternate locations
                $filename = basename($file_url);
                $messages[] = 'Looking for filename: ' . $filename;
                continue;
            }
            
            $messages[] = 'File exists: YES';
            
            // Get extension
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
            $messages[] = 'Extension: ' . $extension;
            
            // Build new filename
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if (!empty($extension)) {
                $new_filename .= '.' . $extension;
            }
            
            $new_file_path = $upload_dir['path'] . '/' . $new_filename;
            $messages[] = 'New filename will be: ' . $new_filename;
            
            // Attempt rename
            if (rename($file_path, $new_file_path)) {
                $new_url = $upload_dir['url'] . '/' . $new_filename;
                $messages[] = 'RENAME SUCCESS: ' . $new_url;
                
                // Update field
                $field['value'] = [$new_url];
                
                // Delete original
                if (file_exists($file_path)) {
                    unlink($file_path);
                    $messages[] = 'Original file deleted';
                }
            } else {
                $error = error_get_last();
                $messages[] = 'RENAME FAILED: ' . ($error['message'] ?? 'Unknown error');
            }
        }
    }
    
    if (!$rename_attempted) {
        $messages[] = '';
        $messages[] = 'No file upload fields were processed';
    }
    
    $messages[] = '';
    $messages[] = '=== END RENAME ATTEMPT ===';
    
    // Update transient
    set_transient('wembassy_debug_messages_' . get_current_user_id(), $messages, 60);
}

function wembassy_page_debug_get_abbrev($title) {
    $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $clean);
    $abbrev = '';
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    $abbrev = substr($abbrev, 0, 5);
    return empty($abbrev) ? 'KXE' : $abbrev;
}

/**
 * Add admin notice showing plugin is active
 */
add_action('admin_notices', 'wembassy_page_debug_admin_notice');

function wembassy_page_debug_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><strong>WPForms File Rename (Page Debug)</strong> is active. Debug output will appear on form submission confirmation pages.</p>
    </div>
    <?php
}
