<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX Final
 * Description: Final version with fixed entry update
 * Version: 1.0.5
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Store the new URLs before output buffer clears
$GLOBALS['wembassy_new_urls'] = array();
$GLOBALS['wembassy_entry_id'] = 0;

add_action('wpforms_process_complete', 'wembassy_final_rename', 10, 4);

function wembassy_final_rename($fields, $entry, $form_data, $entry_id) {
    if (empty($entry_id)) return;
    
    $GLOBALS['wembassy_entry_id'] = $entry_id;
    
    // Team name
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']) && isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    
    // Abbreviation
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
        
        $file_url = is_array($field['value']) ? $field['value'][0] : $field['value'];
        if (empty($file_url)) continue;
        
        $filename = basename($file_url);
        
        // Find file
        $file_path = false;
        if (preg_match('/uploads\/([0-9]{4})\/([0-9]{2})\//', parse_url($file_url, PHP_URL_PATH), $matches)) {
            $test = $upload_dir['basedir'] . '/' . $matches[1] . '/' . $matches[2] . '/' . $filename;
            if (file_exists($test)) $file_path = $test;
        }
        
        if (!$file_path) {
            foreach (array($upload_dir['path'], $upload_dir['basedir']) as $dir) {
                if (file_exists($dir . '/' . $filename)) {
                    $file_path = $dir . '/' . $filename;
                    break;
                }
            }
        }
        
        if (!$file_path) continue;
        
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
        
        if (rename($file_path, $new_path)) {
            $dir = dirname($file_path);
            $url_base = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dir);
            $new_url = $url_base . '/' . $new_name;
            
            // Store for later update
            $GLOBALS['wembassy_new_urls'][$field_id] = $new_url;
            
            // Also update fields array for this request
            $field['value'] = is_array($field['value']) ? array($new_url) : $new_url;
            $field['file'] = $new_url;
            $field['file_original'] = $new_name;
            
            // Delete old
            if (file_exists($file_path)) unlink($file_path);
        }
    }
    
    // Update database immediately
    wembassy_update_entry_now($entry_id);
}

function wembassy_update_entry_now($entry_id) {
    $new_urls = $GLOBALS['wembassy_new_urls'];
    if (empty($new_urls)) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    
    // Get entry
    $row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM $table WHERE entry_id = %d", $entry_id), ARRAY_A);
    
    if (!$row || empty($row['fields'])) return;
    
    $entry_fields = json_decode($row['fields'], true);
    if (!is_array($entry_fields)) return;
    
    // Update each field
    foreach ($new_urls as $fid => $url) {
        if (isset($entry_fields[$fid])) {
            $entry_fields[$fid]['value'] = $url;
            $entry_fields[$fid]['file'] = $url;
            $entry_fields[$fid]['file_original'] = basename($url);
        }
    }
    
    // Update
    $wpdb->update(
        $table,
        array('fields' => json_encode($entry_fields)),
        array('entry_id' => $entry_id),
        array('%s'),
        array('%d')
    );
}

// Also try updating via WPForms API if available
add_action('wpforms_process_complete', 'wembassy_update_via_wpforms', 20, 4);

function wembassy_update_via_wpforms($fields, $entry, $form_data, $entry_id) {
    if (empty($GLOBALS['wembassy_new_urls'])) return;
    
    // Try using WPForms entry class
    if (function_exists('wpforms') && wpforms()->entry) {
        $entry_obj = wpforms()->entry;
        $entry_data = $entry_obj->get($entry_id);
        
        if ($entry_data) {
            // Update the entry fields
            $updated_fields = array();
            foreach ($entry_data->fields as $fid => $fdata) {
                if (isset($GLOBALS['wembassy_new_urls'][$fid])) {
                    $updated_fields[$fid] = $GLOBALS['wembassy_new_urls'][$fid];
                }
            }
            
            if (!empty($updated_fields)) {
                $entry_obj->update_meta($entry_id, 'fields', $updated_fields);
            }
        }
    }
}

// Show results
add_filter('wpforms_frontend_confirmation_message', 'wembassy_final_display', 10, 4);

function wembassy_final_display($message, $form_data, $fields, $entry_id) {
    $new_urls = $GLOBALS['wembassy_new_urls'] ?? array();
    
    if (empty($new_urls)) {
        return $message . '<div style="background:#fff3cd;padding:10px;margin:10px 0;">No files renamed</div>';
    }
    
    $html = '<div style="background:#d4edda;border:1px solid #c3e6cb;padding:15px;margin:20px 0;font-family:monospace;font-size:12px;">';
    $html .= '<strong>File Rename Results:</strong><br>';
    foreach ($new_urls as $fid => $url) {
        $html .= "Field $fid: $url<br>";
    }
    $html .= '</div>';
    
    return $message . $html;
}
