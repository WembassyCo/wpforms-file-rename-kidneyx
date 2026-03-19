<?php
/**
 * Plugin Name: WPForms File Rename - DEBUG Version
 * Description: Debug version to diagnose why file rename isn't working
 * Version: 1.0.0-debug
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug: Log all form submissions
 */
add_action('wpforms_process_entry_save', 'wembassy_debug_entry_save', 1, 4);

function wembassy_debug_entry_save($fields, $entry, $form_id, $form_data) {
    error_log('=== WEMBASSY DEBUG ===');
    error_log('Form ID: ' . $form_id);
    error_log('Form Title: ' . (isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'N/A'));
    error_log('Total Fields: ' . count($fields));
    
    // Log all field types and IDs
    foreach ($fields as $field_id => $field) {
        $type = isset($field['type']) ? $field['type'] : 'unknown';
        $label = isset($field['label']) ? $field['label'] : 'no label';
        error_log('Field ID ' . $field_id . ' - Type: ' . $type . ' - Label: ' . $label);
        
        if ($type === 'file-upload') {
            error_log('FILE UPLOAD FIELD FOUND: ' . $field_id);
            error_log('Raw value: ' . print_r($field['value'], true));
        }
    }
    
    error_log('=== END DEBUG ===');
}

/**
 * Main rename function with enhanced debugging
 */
add_action('wpforms_process_entry_save', 'wembassy_rename_with_debug', 10, 4);

function wembassy_rename_with_debug($fields, $entry, $form_id, $form_data) {
    error_log('WEMBASSY RENAME: Starting for form ' . $form_id);
    
    $upload_dir = wp_upload_dir();
    error_log('WEMBASSY RENAME: Upload path: ' . $upload_dir['path']);
    error_log('WEMBASSY RENAME: Upload URL: ' . $upload_dir['url']);
    
    // Get form title for abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_debug_get_abbrev($form_title);
    error_log('WEMBASSY RENAME: Form title: ' . $form_title . ' -> Abbrev: ' . $abbrev);
    
    // Find Team Name
    $team_name = wembassy_debug_find_team($fields);
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        error_log('WEMBASSY RENAME: Using timestamp: ' . $team_name);
    } else {
        error_log('WEMBASSY RENAME: Team name found: ' . $team_name);
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Process file fields
    $file_fields_found = false;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $file_fields_found = true;
        error_log('WEMBASSY RENAME: Processing file field ' . $field_id);
        
        $files = is_array($field['value']) ? $field['value'] : [$field['value']];
        error_log('WEMBASSY RENAME: Files to process: ' . print_r($files, true));
        
        $new_files = [];
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                error_log('WEMBASSY RENAME: Empty file URL, skipping');
                continue;
            }
            
            error_log('WEMBASSY RENAME: Processing URL: ' . $file_url);
            
            // Convert URL to path
            $file_path = str_replace($upload_dir['url'], $upload_dir['path'], $file_url);
            error_log('WEMBASSY RENAME: Converted to path: ' . $file_path);
            
            // Check if file exists
            if (!file_exists($file_path)) {
                error_log('WEMBASSY RENAME: FILE NOT FOUND: ' . $file_path);
                $new_files[] = $file_url;
                continue;
            }
            
            error_log('WEMBASSY RENAME: File exists: ' . $file_path);
            
            // Get extension
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
            error_log('WEMBASSY RENAME: Extension: ' . $extension);
            
            // Build new filename
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if (!empty($extension)) {
                $new_filename .= '.' . $extension;
            }
            
            // Ensure unique
            $new_file_path = $upload_dir['path'] . '/' . $new_filename;
            $counter = 1;
            while (file_exists($new_file_path)) {
                $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter;
                if (!empty($extension)) {
                    $new_filename .= '.' . $extension;
                }
                $new_file_path = $upload_dir['path'] . '/' . $new_filename;
                $counter++;
            }
            
            error_log('WEMBASSY RENAME: Attempting rename to: ' . $new_file_path);
            
            // Try rename
            if (rename($file_path, $new_file_path)) {
                $new_url = $upload_dir['url'] . '/' . $new_filename;
                $new_files[] = $new_url;
                error_log('WEMBASSY RENAME: SUCCESS! New URL: ' . $new_url);
                
                // Delete original
                if (file_exists($file_path)) {
                    unlink($file_path);
                    error_log('WEMBASSY RENAME: Original deleted');
                }
            } else {
                error_log('WEMBASSY RENAME: FAILED to rename!');
                error_log('WEMBASSY RENAME: Error: ' . error_get_last()['message']);
                $new_files[] = $file_url;
            }
        }
        
        // Update the field
        $field['value'] = $new_files;
        error_log('WEMBASSY RENAME: Field ' . $field_id . ' updated');
    }
    
    if (!$file_fields_found) {
        error_log('WEMBASSY RENAME: No file upload fields found in this form!');
    }
    
    error_log('WEMBASSY RENAME: Completed');
}

function wembassy_debug_find_team($fields) {
    foreach ($fields as $field_id => $field) {
        $label = isset($field['label']) ? strtolower($field['label']) : '';
        error_log('WEMBASSY DEBUG: Checking field ' . $field_id . ' label: ' . $label);
        
        if (strpos($label, 'team') !== false) {
            $value = isset($field['value']) ? $field['value'] : '';
            $result = is_array($value) ? implode(' ', $value) : $value;
            error_log('WEMBASSY DEBUG: Team field found! Value: ' . $result);
            return $result;
        }
    }
    error_log('WEMBASSY DEBUG: No team field found');
    return '';
}

function wembassy_debug_get_abbrev($title) {
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
