<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX
 * Description: Renames uploaded files using wpforms_process_entry_save hook
 * Version: 1.0.0
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rename uploaded files using wpforms_process_entry_save hook
 * 
 * Formula: [TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf
 * - TeamName: From form field (or timestamp if field not found)
 * - Abbrev: Abbreviation of form title
 * 
 * @param array $fields Form fields
 * @param array $entry Entry data
 * @param int   $form_id Form ID
 * @param array $form_data Form configuration
 * @return void
 */
add_action('wpforms_process_entry_save', 'wembassy_rename_files_on_entry_save', 10, 4);

function wembassy_rename_files_on_entry_save($fields, $entry, $form_id, $form_data) {
    error_log('WEMBASSY FILE RENAME: Starting for form ID ' . $form_id);
    
    // Get form info for abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_get_abbreviation($form_title);
    error_log('WEMBASSY FILE RENAME: Form title "' . $form_title . '" → Abbrev "' . $abbrev . '"');
    
    // Find Team Name field
    $team_name = wembassy_find_team_name_field($fields);
    
    // If no Team Name field, use timestamp
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        error_log('WEMBASSY FILE RENAME: No Team Name field found, using timestamp: ' . $team_name);
    } else {
        error_log('WEMBASSY FILE RENAME: Team Name found: ' . $team_name);
    }
    
    // Sanitize for filename
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Process each file upload field
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        error_log('WEMBASSY FILE RENAME: Processing file upload field ' . $field_id);
        
        $files = is_array($field['value']) ? $field['value'] : [$field['value']];
        $new_files = [];
        $upload_dir = wp_upload_dir();
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                continue;
            }
            
            // Convert URL to path
            $file_path = str_replace($upload_dir['url'], $upload_dir['path'], $file_url);
            
            error_log('WEMBASSY FILE RENAME: Processing file: ' . $file_path);
            
            // Check file exists
            if (!file_exists($file_path)) {
                error_log('WEMBASSY FILE RENAME: File does not exist, skipping: ' . $file_path);
                $new_files[] = $file_url;
                continue;
            }
            
            // Get file extension
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
            
            // Build new filename
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if (!empty($extension)) {
                $new_filename .= '.' . $extension;
            }
            
            // Ensure unique filename
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
            
            // Rename the file
            if (rename($file_path, $new_file_path)) {
                $new_file_url = $upload_dir['url'] . '/' . $new_filename;
                $new_files[] = $new_file_url;
                
                error_log('WEMBASSY FILE RENAME: SUCCESS - Renamed "' . basename($file_path) . '" to "' . $new_filename . '"');
                error_log('WEMBASSY FILE RENAME: New URL: ' . $new_file_url);
                
                // Delete original file if different
                if ($file_path !== $new_file_path && file_exists($file_path)) {
                    unlink($file_path);
                    error_log('WEMBASSY FILE RENAME: Deleted original file: ' . $file_path);
                }
            } else {
                error_log('WEMBASSY FILE RENAME: FAILED - Could not rename "' . $file_path . '" to "' . $new_file_path . '"');
                $new_files[] = $file_url;
            }
        }
        
        // Update field value with new file URLs
        $field['value'] = $new_files;
        error_log('WEMBASSY FILE RENAME: Updated field ' . $field_id . ' value');
    }
    
    error_log('WEMBASSY FILE RENAME: Completed for form ID ' . $form_id);
}

/**
 * Find Team Name field value from form fields
 * 
 * Looks for field with label containing "team" or field ID commonly used for team name
 * 
 * @param array $fields Form fields
 * @return string Team name or empty string if not found
 */
function wembassy_find_team_name_field($fields) {
    foreach ($fields as $field_id => $field) {
        // Check field label
        if (isset($field['label']) && stripos($field['label'], 'team') !== false) {
            $value = isset($field['value']) ? $field['value'] : '';
            return is_array($value) ? implode(' ', $value) : $value;
        }
        
        // Check field name
        if (isset($field['name']) && stripos($field['name'], 'team') !== false) {
            $value = isset($field['value']) ? $field['value'] : '';
            return is_array($value) ? implode(' ', $value) : $value;
        }
    }
    
    return '';
}

/**
 * Generate abbreviation from form title
 * 
 * Takes first letter of each word (up to 5 characters)
 * 
 * @param string $title Form title
 * @return string Abbreviation
 */
function wembassy_get_abbreviation($title) {
    // Remove special characters
    $clean_title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    
    // Split into words
    $words = explode(' ', $clean_title);
    $abbrev = '';
    
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    
    // Limit to 5 characters
    $abbrev = substr($abbrev, 0, 5);
    
    // Fallback if empty
    if (empty($abbrev)) {
        $abbrev = 'KXE';
    }
    
    return $abbrev;
}
