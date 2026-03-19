<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX
 * Description: Renames uploaded files. Compatible with WPForms 1.10.0+
 * Version: 1.0.1
 * Author: Wembassy
 * Formula: [TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf
 */

if (!defined('ABSPATH')) exit;

// Store team name early
add_action('wpforms_process_before', 'wembassy_capture_team', 10, 2);

function wembassy_capture_team($entry, $form_data) {
    global $wembassy_team_name;
    
    $wembassy_team_name = current_time('Y-m-d_H-i-s');
    
    if (isset($entry['fields']['2'])) {
        $val = $entry['fields']['2'];
        if (is_string($val) && !empty($val)) {
            $wembassy_team_name = sanitize_file_name($val);
        }
    }
}

// Process after entry is saved
add_action('wpforms_process_after', 'wembassy_rename_files', 10, 3);

function wembassy_rename_files($fields, $entry, $form_data) {
    global $wembassy_team_name;
    
    if (empty($wembassy_team_name)) {
        $wembassy_team_name = current_time('Y-m-d_H-i-s');
    }
    
    $team = sanitize_file_name($wembassy_team_name);
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = sanitize_file_name(wembassy_get_abbrev($form_title));
    
    $upload_dir = wp_upload_dir();
    
    foreach ($fields as $field_id => $field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $files = isset($field['value']) ? $field['value'] : array();
        if (!is_array($files)) {
            $files = array($files);
        }
        
        $new_files = array();
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                $new_files[] = $file_url;
                continue;
            }
            
            $filename = basename($file_url);
            $file_path = false;
            
            // Check multiple locations
            $paths = array(
                $upload_dir['path'] . '/' . $filename,
                $upload_dir['basedir'] . '/' . $filename,
                $upload_dir['basedir'] . '/' . current_time('Y') . '/' . current_time('m') . '/' . $filename,
            );
            
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $file_path = $path;
                    break;
                }
            }
            
            if (!$file_path) {
                $new_files[] = $file_url;
                continue;
            }
            
            // Get extension
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            if (empty($ext)) $ext = 'pdf';
            
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
            
            // Rename
            if (rename($file_path, $new_path)) {
                // Build new URL
                $new_url = str_replace($filename, $new_name, $file_url);
                $new_files[] = $new_url;
                
                // Delete old
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            } else {
                $new_files[] = $file_url;
            }
        }
        
        // Update field value in entry meta
        if (function_exists('wpforms') && wpforms()->entry) {
            wpforms()->entry->update_meta($entry['entry_id'], $field_id, $new_files);
        }
    }
}

function wembassy_get_abbrev($title) {
    $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $title);
    $abbrev = '';
    
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    
    return substr($abbrev, 0, 5) ?: 'KXE';
}
