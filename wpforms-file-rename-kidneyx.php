<?php
/**
 * Plugin Name: WPForms File Rename - Minimal
 * Description: Minimal working version for WPForms
 * Version: 1.0.0-minimal
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

add_action('wpforms_process_complete', 'wembassy_minimal_complete', 10, 4);

function wembassy_minimal_complete($fields, $entry, $form_data, $entry_id) {
    // Store messages
    $output = array();
    $output[] = '=== START ===';
    $output[] = 'Entry: ' . $entry_id;
    
    // Get team
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']) && isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    $output[] = 'Team: ' . $team;
    
    // Get abbrev from field 1 - first letter of each word, max 5 chars
    $abbrev = 'FORM';
    if (isset($fields['1']) && isset($fields['1']['value'])) {
        $val = $fields['1']['value'];
        if (is_string($val) && !empty($val)) {
            // Clean and split by spaces
            $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $val);
            $words = explode(' ', $clean);
            $letters = '';
            foreach ($words as $word) {
                $word = trim($word);
                if (!empty($word)) {
                    $letters .= strtoupper(substr($word, 0, 1));
                }
            }
            $abbrev = substr($letters, 0, 5);
            if (empty($abbrev)) {
                $abbrev = 'FORM';
            }
        }
    }
    $output[] = 'Abbrev: ' . $abbrev;
    
    $team = sanitize_file_name($team);
    // Note: $abbrev is already clean (just letters), no need to sanitize
    
    // Get upload dir
    $upload_dir = wp_upload_dir();
    $output[] = 'Upload path: ' . $upload_dir['path'];
    $output[] = 'Upload URL: ' . $upload_dir['url'];
    
    // Process fields
    $found_files = 0;
    $renamed_files = 0;
    
    foreach ($fields as $field_id => $field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        if (empty($field['value'])) {
            continue;
        }
        
        $found_files++;
        $output[] = '';
        $output[] = 'Field ' . $field_id;
        $output[] = 'Value: ' . print_r($field['value'], true);
        
        $file_url = $field['value'];
        if (is_array($file_url)) {
            $file_url = $file_url[0];
        }
        
        if (empty($file_url)) {
            continue;
        }
        
        $filename = basename($file_url);
        $output[] = 'Filename: ' . $filename;
        
        // Try to find file
        $file_path = false;
        
        // Parse year/month from URL
        $url_path = parse_url($file_url, PHP_URL_PATH);
        if ($url_path) {
            // Try to get year/month from URL
            if (preg_match('/uploads\/([0-9]{4})\/([0-9]{2})\//', $url_path, $matches)) {
                $ym = $matches[1] . '/' . $matches[2];
                $test = $upload_dir['basedir'] . '/' . $ym . '/' . $filename;
                if (file_exists($test)) {
                    $file_path = $test;
                }
            }
        }
        
        // Try other locations
        if (!$file_path) {
            $tests = array(
                $upload_dir['path'] . '/' . $filename,
                $upload_dir['basedir'] . '/' . $filename,
                $upload_dir['basedir'] . '/2026/03/' . $filename,
            );
            foreach ($tests as $test) {
                if (file_exists($test)) {
                    $file_path = $test;
                    break;
                }
            }
        }
        
        if (!$file_path) {
            $output[] = 'File NOT FOUND';
            continue;
        }
        
        $output[] = 'Found: ' . $file_path;
        
        // Get extension
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'pdf';
        }
        $output[] = 'Extension: ' . $ext;
        
        // New name
        $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission.' . $ext;
        $new_path = dirname($file_path) . '/' . $new_name;
        
        $counter = 1;
        while (file_exists($new_path)) {
            $new_name = $team . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter . '.' . $ext;
            $new_path = dirname($file_path) . '/' . $new_name;
            $counter++;
        }
        
        $output[] = 'Rename to: ' . $new_name;
        
        // Rename
        if (rename($file_path, $new_path)) {
            $output[] = 'SUCCESS';
            $renamed_files++;
            
            // Get new URL
            $dir = dirname($file_path);
            $url_base = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dir);
            $new_url = $url_base . '/' . $new_name;
            $output[] = 'New URL: ' . $new_url;
            
            // Update entry via global (hacky but may work)
            global $wpdb;
            if (isset($wpdb) && $entry_id) {
                $table = $wpdb->prefix . 'wpforms_entries';
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE entry_id = %d", $entry_id));
                if ($row && $row->fields) {
                    $entry_fields = json_decode($row->fields, true);
                    if ($entry_fields && isset($entry_fields[$field_id])) {
                        $new_filename = basename($new_url);
                        
                        // Update main value
                        $entry_fields[$field_id]['value'] = $new_url;
                        
                        // Update value_raw array if it exists (this is what admin displays)
                        if (isset($entry_fields[$field_id]['value_raw']) && is_array($entry_fields[$field_id]['value_raw'])) {
                            foreach ($entry_fields[$field_id]['value_raw'] as $idx => $file_data) {
                                if (isset($file_data['value'])) {
                                    $entry_fields[$field_id]['value_raw'][$idx]['value'] = $new_url;
                                }
                                if (isset($file_data['file'])) {
                                    $entry_fields[$field_id]['value_raw'][$idx]['file'] = $new_filename;
                                }
                                if (isset($file_data['file_original'])) {
                                    $entry_fields[$field_id]['value_raw'][$idx]['file_original'] = $new_filename;
                                }
                                if (isset($file_data['file_user_name'])) {
                                    $entry_fields[$field_id]['value_raw'][$idx]['file_user_name'] = $new_filename;
                                }
                                if (isset($file_data['name'])) {
                                    $entry_fields[$field_id]['value_raw'][$idx]['name'] = $new_filename;
                                }
                            }
                        }
                        
                        // Update other field keys
                        $entry_fields[$field_id]['file'] = $new_url;
                        $entry_fields[$field_id]['file_original'] = $new_filename;
                        $entry_fields[$field_id]['file_user_name'] = $new_filename;
                        $entry_fields[$field_id]['name'] = $new_filename;
                        
                        $wpdb->update($table, array('fields' => wp_json_encode($entry_fields)), array('entry_id' => $entry_id));
                        $output[] = 'DB updated (value + value_raw)';
                    }
                }
            }
        } else {
            $error = error_get_last();
            $output[] = 'FAILED: ' . ($error ? $error['message'] : 'unknown');
        }
    }
    
    $output[] = '';
    $output[] = '=== END ===';
    $output[] = 'Files found: ' . $found_files;
    $output[] = 'Files renamed: ' . $renamed_files;
    
    // Store for display
    set_transient('wembassy_minimal_' . get_current_user_id(), $output, 60);
}

// Debug output disabled - remove this filter to enable debugging
// add_filter('wpforms_frontend_confirmation_message', 'wembassy_minimal_show', 10, 4);

function wembassy_minimal_show($message, $form_data, $fields, $entry_id) {
    // Disabled - no debug output on form submission
    return $message;
}
