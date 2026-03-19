<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX
 * Description: Renames uploaded files and updates the entry
 * Version: 1.0.4
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

add_action('wpforms_process_complete', 'wembassy_kidneyx_rename', 10, 4);

function wembassy_kidneyx_rename($fields, $entry, $form_data, $entry_id) {
    if (empty($entry_id)) return;
    
    // Store messages
    $output = array();
    $output[] = '=== FILE RENAME START ===';
    $output[] = 'Entry: ' . $entry_id;
    
    // Get team name
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']) && isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    $output[] = 'Team: ' . $team;
    
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
    $abbrev = substr($abbrev, 0, 5);
    if (empty($abbrev)) $abbrev = 'FORM';
    
    $team = sanitize_file_name($team);
    $abbrev = sanitize_file_name($abbrev);
    $upload_dir = wp_upload_dir();
    
    $output[] = 'Upload dir: ' . $upload_dir['basedir'];
    
    // Track updated fields
    $updated_fields = array();
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        if (empty($field['value'])) {
            continue;
        }
        
        $file_url = $field['value'];
        if (is_array($file_url)) {
            $file_url = $file_url[0];
        }
        if (empty($file_url)) continue;
        
        $filename = basename($file_url);
        $output[] = '';
        $output[] = 'Field ' . $field_id . ' file: ' . $filename;
        
        // Find file
        $file_path = false;
        $url_path = parse_url($file_url, PHP_URL_PATH);
        if ($url_path && preg_match('/uploads\/([0-9]{4})\/([0-9]{2})\//', $url_path, $matches)) {
            $test = $upload_dir['basedir'] . '/' . $matches[1] . '/' . $matches[2] . '/' . $filename;
            if (file_exists($test)) $file_path = $test;
        }
        
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
        
        if (!$file_path) {
            $output[] = 'ERROR: File not found!';
            continue;
        }
        
        $output[] = 'Found at: ' . $file_path;
        
        // Rename
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) ?: 'pdf';
        $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission.' . $ext;
        $new_path = dirname($file_path) . '/' . $new_name;
        
        $counter = 1;
        while (file_exists($new_path)) {
            $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter . '.' . $ext;
            $new_path = dirname($file_path) . '/' . $new_name;
            $counter++;
        }
        
        $output[] = 'Renaming to: ' . $new_name;
        
        if (rename($file_path, $new_path)) {
            $dir = dirname($file_path);
            $url_base = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dir);
            $new_url = $url_base . '/' . $new_name;
            $output[] = 'SUCCESS: ' . $new_url;
            
            // Store for update
            $updated_fields[$field_id] = $new_url;
            
            // Clean up old file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        } else {
            $output[] = 'FAILED rename';
        }
    }
    
    // Update entry in database
    if (!empty($updated_fields)) {
        $output[] = '';
        $output[] = 'Updating entry...';
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpforms_entries';
        
        // Get current entry fields
        $row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM $table WHERE entry_id = %d", $entry_id), ARRAY_A);
        
        if ($row && !empty($row['fields'])) {
            $entry_fields = json_decode($row['fields'], true);
            
            if (is_array($entry_fields)) {
                foreach ($updated_fields as $fid => $url) {
                    if (isset($entry_fields[$fid])) {
                        $entry_fields[$fid]['value'] = $url;
                        $entry_fields[$fid]['file'] = $url;
                        $entry_fields[$fid]['file_original'] = basename($url);
                        $output[] = "Updated field $fid to: $url";
                    }
                }
                
                // Update the database
                $result = $wpdb->update(
                    $table,
                    array('fields' => wp_json_encode($entry_fields)),
                    array('entry_id' => $entry_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $output[] = "Entry updated: $entry_id";
                } else {
                    $output[] = "Entry update failed";
                }
            }
        } else {
            $output[] = "Could not retrieve entry fields";
        }
    }
    
    $output[] = '=== END ===';
    
    set_transient('wembassy_kidneyx_' . get_current_user_id(), $output, 60);
}

// Show debug on confirmation
add_filter('wpforms_frontend_confirmation_message', 'wembassy_kidneyx_display', 10, 4);

function wembassy_kidneyx_display($message, $form_data, $fields, $entry_id) {
    $data = get_transient('wembassy_kidneyx_' . get_current_user_id());
    if ($data) {
        $html = '<div style="background:#f0f0f0;border:2px solid #0073aa;padding:15px;margin:20px 0;font-family:monospace;font-size:11px;white-space:pre-wrap;">';
        $html .= implode('\n', $data);
        $html .= '</div>';
        delete_transient('wembassy_kidneyx_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
