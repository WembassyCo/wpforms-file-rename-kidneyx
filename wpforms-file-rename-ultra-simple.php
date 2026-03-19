<?php
/**
 * Plugin Name: WPForms File Rename - Ultra Simple
 * Description: Minimal version for testing
 * Version: 1.0.0-ultra
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rename files on form submission
 */
function wembassy_simple_rename($fields, $entry, $form_data) {
    // Get team name from field 2
    $team = 'Unknown';
    if (isset($_POST['wpforms']['fields']['2'])) {
        $team_raw = $_POST['wpforms']['fields']['2'];
        if (is_string($team_raw)) {
            $team = sanitize_file_name($team_raw);
        }
    }
    
    // Get abbreviation from form title
    $title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $title);
    $abbrev = '';
    foreach ($words as $w) {
        $w = trim($w);
        if (!empty($w)) {
            $abbrev .= strtoupper(substr($w, 0, 1));
        }
    }
    $abbrev = substr($abbrev, 0, 5);
    if (empty($abbrev)) {
        $abbrev = 'FORM';
    }
    
    // Process file fields
    $upload_dir = wp_upload_dir();
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        // Skip if no file
        if (empty($field['value'])) {
            continue;
        }
        
        $file_url = $field['value'];
        $filename = basename($file_url);
        
        // Find the file
        $file_path = false;
        
        // Possible locations
        $candidates = array(
            $upload_dir['path'] . '/' . $filename,
            $upload_dir['basedir'] . '/' . $filename,
            $upload_dir['basedir'] . '/2026/03/' . $filename,
            $upload_dir['basedir'] . '/' . date('Y') . '/' . date('m') . '/' . $filename,
        );
        
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $file_path = $candidate;
                break;
            }
        }
        
        if (!$file_path) {
            continue;
        }
        
        // Get extension
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'pdf';
        }
        
        // New filename
        $new_name = $team . '_' . $abbrev . '_KidneyX_Submission.' . $ext;
        $new_path = dirname($file_path) . '/' . $new_name;
        
        // Counter for duplicates
        $counter = 1;
        while (file_exists($new_path)) {
            $new_name = $team . '_' . $abbrev . '_KidneyX_Submission_' . $counter . '.' . $ext;
            $new_path = dirname($file_path) . '/' . $new_name;
            $counter++;
        }
        
        // Rename
        if (rename($file_path, $new_path)) {
            // Update URL
            $field['value'] = str_replace($filename, $new_name, $file_url);
            
            // Delete original
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
}

// Hook into entry saving
add_action('wpforms_process_entry_save', 'wembassy_simple_rename', 10, 3);
