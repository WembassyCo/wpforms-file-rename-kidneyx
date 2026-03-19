<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX
 * Description: Renames uploaded files to [TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf format
 * Version: 1.0.0
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

/**
 * Rename uploaded files after WPForms submission
 */
add_action('wpforms_process_complete', 'wembassy_kidneyx_rename', 10, 4);

function wembassy_kidneyx_rename($fields, $entry, $form_data, $entry_id) {
    if (empty($entry_id)) return;
    
    // Get team name from field ID 2
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']) && isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    
    // Get abbreviation from form title
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
    
    $upload_dir = wp_upload_dir();
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
        if (empty($field['value'])) continue;
        
        $file_url = $field['value'];
        if (is_array($file_url)) $file_url = $file_url[0];
        if (empty($file_url)) continue;
        
        $filename = basename($file_url);
        
        // Find file - check year/month from URL
        $file_path = false;
        $url_path = parse_url($file_url, PHP_URL_PATH);
        if ($url_path && preg_match('/uploads\/([0-9]{4})\/([0-9]{2})\//', $url_path, $matches)) {
            $test = $upload_dir['basedir'] . '/' . $matches[1] . '/' . $matches[2] . '/' . $filename;
            if (file_exists($test)) $file_path = $test;
        }
        
        // Try other locations
        if (!$file_path) {
            $tests = array(
                $upload_dir['path'] . '/' . $filename,
                $upload_dir['basedir'] . '/' . $filename,
            );
            foreach ($tests as $test) {
                if (file_exists($test)) {
                    $file_path = $test;
                    break;
                }
            }
        }
        
        if (!$file_path) continue;
        
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) ?: 'pdf';
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
            $dir = dirname($file_path);
            $url_base = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dir);
            $new_url = $url_base . '/' . $new_name;
            
            // Update entry in database
            global $wpdb;
            $table = $wpdb->prefix . 'wpforms_entries';
            $row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM $table WHERE entry_id = %d", $entry_id));
            if ($row && $row->fields) {
                $entry_fields = json_decode($row->fields, true);
                if ($entry_fields && isset($entry_fields[$field_id])) {
                    $entry_fields[$field_id]['value'] = $new_url;
                    $wpdb->update($table, array('fields' => json_encode($entry_fields)), array('entry_id' => $entry_id));
                }
            }
        }
    }
}
