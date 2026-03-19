<?php
/**
 * Plugin Name: WPForms File Rename - Field Filter
 * Description: Uses wpforms_field filter on file uploads
 * Version: 1.0.0-field
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Store team name from form submission
add_action('wpforms_process_before', 'wembassy_field_store_team', 10, 2);

function wembassy_field_store_team($entry, $form_data) {
    $team = current_time('Y-m-d_H-i-s');
    
    if (isset($entry['fields']['2'])) {
        $val = $entry['fields']['2'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    
    set_transient('wembassy_team_field', $team, 120);
}

// Process file uploads after entry is saved
add_action('wpforms_process_complete', 'wembassy_field_complete', 10, 4);

function wembassy_field_complete($fields, $entry, $form_data, $entry_id) {
    global $wpdb;
    
    if (empty($entry_id)) return;
    
    $team = get_transient('wembassy_team_field');
    if (!$team) {
        $team = current_time('Y-m-d_H-i-s');
    }
    delete_transient('wembassy_team_field');
    
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
    
    $team = sanitize_file_name($team);
    $abbrev = sanitize_file_name($abbrev);
    
    // Get upload directory
    $upload_dir = wp_upload_dir();
    
    foreach ($fields as $field_id => $field) {
        if ($field['type'] !== 'file-upload') continue;
        if (empty($field['value'])) continue;
        
        $old_url = $field['value'];
        $filename = basename($old_url);
        
        // Try to find the file
        $file_path = false;
        $checked_paths = array();
        
        // Build possible paths based on year/month in filename or URL
        $possible_dirs = array(
            $upload_dir['path'] . '/',
            $upload_dir['basedir'] . '/',
            $upload_dir['basedir'] . '/2026/03/',
            $upload_dir['basedir'] . '/' . date('Y') . '/' . date('m') . '/',
        );
        
        foreach ($possible_dirs as $dir) {
            $test = $dir . $filename;
            $checked_paths[] = $test;
            if (file_exists($test)) {
                $file_path = $test;
                break;
            }
        }
        
        if (!$file_path) {
            continue;
        }
        
        // Get extension
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        if (empty($ext)) $ext = 'pdf';
        
        // Create new filename
        $new_name = $team . '_' . $abbrev . '_KidneyX_Submission.' . $ext;
        $new_path = dirname($file_path) . '/' . $new_name;
        
        // Ensure unique
        $counter = 1;
        while (file_exists($new_path)) {
            $new_name = $team . '_' . $abbrev . '_KidneyX_Submission_' . $counter . '.' . $ext;
            $new_path = dirname($file_path) . '/' . $new_name;
            $counter++;
        }
        
        // Rename the file
        if (rename($file_path, $new_path)) {
            // Build new URL
            $new_url = str_replace($filename, $new_name, $old_url);
            
            // Update the entry in database
            $table_name = $wpdb->prefix . 'wpforms_entries';
            $fields_col = $wpdb->get_var("SELECT fields FROM $table_name WHERE entry_id = $entry_id");
            
            if ($fields_col) {
                $entry_fields = json_decode($fields_col, true);
                if (isset($entry_fields[$field_id])) {
                    $entry_fields[$field_id]['value'] = $new_url;
                    $entry_fields[$field_id]['file'] = $new_url;
                    $entry_fields[$field_id]['file_original'] = $new_name;
                    
                    $wpdb->update(
                        $table_name,
                        array('fields' => json_encode($entry_fields)),
                        array('entry_id' => $entry_id)
                    );
                }
            }
            
            // Delete old file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
}
