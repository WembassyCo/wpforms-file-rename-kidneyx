<?php
/**
 * Plugin Name: WPForms File Rename - KidneyX Fixed
 * Description: Renames uploaded files and updates entry properly
 * Version: 1.0.1-fixed
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

add_action('wpforms_process_complete', 'wembassy_kidneyx_rename_fixed', 10, 4);

function wembassy_kidneyx_rename_fixed($fields, $entry, $form_data, $entry_id) {
    if (empty($entry_id)) return;
    
    // Store debug messages
    $debug = array();
    $debug[] = '=== RENAME START ===';
    $debug[] = 'Entry: ' . $entry_id;
    
    // Get team name
    $team = current_time('Y-m-d_H-i-s');
    if (isset($fields['2']) && isset($fields['2']['value'])) {
        $val = $fields['2']['value'];
        if (is_string($val) && !empty($val)) {
            $team = sanitize_file_name($val);
        }
    }
    $debug[] = 'Team: ' . $team;
    
    // Get abbrev
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
    $debug[] = 'Abbrev: ' . $abbrev;
    
    $team = sanitize_file_name($team);
    $abbrev = sanitize_file_name($abbrev);
    
    $upload_dir = wp_upload_dir();
    
    // Process each file field
    $fields_updated = false;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
        if (empty($field['value'])) continue;
        
        $debug[] = '';
        $debug[] = 'Field ' . $field_id;
        
        $file_url = $field['value'];
        if (is_array($file_url)) {
            $urls = $file_url;
            $file_url = $urls[0];
        }
        
        if (empty($file_url)) continue;
        
        $filename = basename($file_url);
        $debug[] = 'Original: ' . $filename;
        
        // Find file
        $file_path = false;
        
        // Parse year/month from URL
        $url_path = parse_url($file_url, PHP_URL_PATH);
        if ($url_path && preg_match('/uploads\/([0-9]{4})\/([0-9]{2})\//', $url_path, $matches)) {
            $test = $upload_dir['basedir'] . '/' . $matches[1] . '/' . $matches[2] . '/' . $filename;
            if (file_exists($test)) {
                $file_path = $test;
            }
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
        
        if (!$file_path) {
            $debug[] = 'ERROR: File not found!';
            continue;
        }
        
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
        
        $debug[] = 'New name: ' . $new_name;
        
        // Rename
        if (rename($file_path, $new_path)) {
            $debug[] = 'SUCCESS: File renamed';
            
            // Build new URL
            $dir = dirname($file_path);
            $url_base = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dir);
            $new_url = $url_base . '/' . $new_name;
            
            // Update field value
            $field['value'] = is_array($field['value']) ? array($new_url) : $new_url;
            $debug[] = 'Updated field value to: ' . $new_url;
            $fields_updated = true;
            
            // Update database using WPForms API
            global $wpdb;
            if (is_callable(array('WPForms', 'instance'))) {
                $wpforms = WPForms::instance();
                if (isset($wpforms->entry) && method_exists($wpforms->entry, 'update')) {
                    $wpforms->entry->update(
                        $entry_id,
                        array(
                            'fields'        => $fields,
                            'metas'         => isset($entry['metas']) ? $entry['metas'] : array(),
                            'fields_update' => true,
                        )
                    );
                    $debug[] = 'Entry updated via WPForms API';
                } else {
                    // Fallback to direct DB update
                    $table = $wpdb->prefix . 'wpforms_entries';
                    $wpdb->update(
                        $table,
                        array('fields' => wp_json_encode($fields)),
                        array('entry_id' => $entry_id),
                        array('%s'),
                        array('%d')
                    );
                    $debug[] = 'Entry updated via database';
                }
            } else {
                // Direct DB update
                $table = $wpdb->prefix . 'wpforms_entries';
                $wpdb->update(
                    $table,
                    array('fields' => wp_json_encode($fields)),
                    array('entry_id' => $entry_id),
                    array('%s'),
                    array('%d')
                );
                $debug[] = 'Entry updated via database (fallback)';
            }
            
            // Delete original file if still exists
            if (file_exists($file_path) && $file_path !== $new_path) {
                unlink($file_path);
            }
        } else {
            $error = error_get_last();
            $debug[] = 'ERROR: Rename failed - ' . ($error ? $error['message'] : 'unknown');
        }
    }
    
    $debug[] = '';
    $debug[] = '=== END ===';
    
    set_transient('wembassy_kidneyx_debug_' . get_current_user_id(), $debug, 60);
}

add_filter('wpforms_frontend_confirmation_message', 'wembassy_kidneyx_show_debug', 10, 4);

function wembassy_kidneyx_show_debug($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_kidneyx_debug_' . get_current_user_id());
    if ($debug) {
        $html = '<div style="background:#f0f0f0;border:2px solid #0073aa;padding:15px;margin:20px 0;font-family:monospace;font-size:11px;white-space:pre-wrap;">';
        $html .= '<strong>File Rename Debug:</strong>\n\n';
        $html .= implode('\n', $debug);
        $html .= '</div>';
        delete_transient('wembassy_kidneyx_debug_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
