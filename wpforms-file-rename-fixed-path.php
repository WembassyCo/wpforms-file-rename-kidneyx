<?php
/**
 * Plugin Name: WPForms File Rename - Fixed Path Version
 * Description: Fixed version that correctly handles WPForms file paths
 * Version: 1.0.1-path-fix
 * Author: Wembassy
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug hook to test before anything else
add_action('wpforms_process_entry_save', 'wembassy_test_file_rename', 10, 4);

function wembassy_test_file_rename($fields, $entry, $form_id, $form_data) {
    // Store messages for display
    $messages = [];
    $messages[] = '=== WEMBASSY FILE RENAME (FIXED) ===';
    $messages[] = 'Form ID: ' . $form_id;
    $messages[] = 'Form Title: ' . (isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'N/A');
    $messages[] = '';
    
    // Get form abbreviation
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_get_abbrev($form_title);
    $messages[] = 'Abbreviation: ' . $abbrev;
    
    // Find Team Name using multiple methods
    $team_name = '';
    
    // Method 1: Look for field name containing 'team'
    foreach ($fields as $field_id => $field) {
        // Check field name/id
        if (stripos($field_id, 'team') !== false) {
            $team_name = isset($field['value']) ? $field['value'] : '';
            if (is_array($team_name)) $team_name = implode(' ', $team_name);
            $messages[] = 'Team found by field ID ' . $field_id . ': ' . $team_name;
            break;
        }
        // Check field value for common team names
        if (isset($field['value'])) {
            $val = is_array($field['value']) ? implode(' ', $field['value']) : $field['value'];
            if (strlen($val) > 2 && strlen($val) < 50 && preg_match('/^[A-Za-z0-9\s]+$/', $val)) {
                // Could be a team name - check if it looks like one
                if (preg_match('/team|group|company|org/i', $val)) {
                    $team_name = $val;
                    $messages[] = 'Team found by pattern in field ' . $field_id . ': ' . $team_name;
                    break;
                }
            }
        }
    }
    
    // Fallback to timestamp
    if (empty($team_name)) {
        $team_name = current_time('Y-m-d_H-i-s');
        $messages[] = 'Using timestamp: ' . $team_name;
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Process file upload fields
    $files_processed = 0;
    
    foreach ($fields as $field_id => &$field) {
        if (!isset($field['type']) || $field['type'] !== 'file-upload') {
            continue;
        }
        
        $messages[] = '';
        $messages[] = '--- Processing field ' . $field_id . ' ---';
        
        $files = is_array($field['value']) ? $field['value'] : [$field['value']];
        $new_files = [];
        
        foreach ($files as $file_url) {
            if (empty($file_url)) {
                $messages[] = 'Empty file URL - skipping';
                continue;
            }
            
            $messages[] = 'File URL: ' . $file_url;
            
            // Get just the filename from URL
            $filename = basename($file_url);
            $messages[] = 'Filename: ' . $filename;
            
            // Determine the correct file path
            // WPForms can store in multiple locations
            $possible_paths = [
                // Standard WP uploads
                WP_CONTENT_DIR . '/uploads/' . $filename,
                WP_CONTENT_DIR . '/uploads/2026/03/' . $filename,
                WP_CONTENT_DIR . '/uploads/' . current_time('Y') . '/' . current_time('m') . '/' . $filename,
                // WPForms specific
                WP_CONTENT_DIR . '/uploads/wpforms/' . $filename,
                // Direct from URL parsing
                str_replace(content_url(), WP_CONTENT_DIR, $file_url),
                // ABSPATH based
                ABSPATH . str_replace(site_url(), '', $file_url),
            ];
            
            $file_path = null;
            foreach ($possible_paths as $path) {
                // Remove double slashes
                $path = str_replace('//', '/', $path);
                if (file_exists($path)) {
                    $file_path = $path;
                    $messages[] = 'Found file at: ' . $path;
                    break;
                }
            }
            
            if (!$file_path) {
                $messages[] = 'ERROR: File not found in any expected location!';
                $messages[] = 'Checked paths:';
                foreach ($possible_paths as $path) {
                    $messages[] = '  - ' . str_replace('//', '/', $path);
                }
                $new_files[] = $file_url;
                continue;
            }
            
            // Get file info
            $file_info = pathinfo($file_path);
            $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
            $upload_dir = wp_upload_dir();
            
            // Build new filename
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if (!empty($extension)) {
                $new_filename .= '.' . $extension;
            }
            
            // Ensure unique
            $counter = 1;
            $new_file_path = $upload_dir['path'] . '/' . $new_filename;
            while (file_exists($new_file_path)) {
                $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter;
                if (!empty($extension)) {
                    $new_filename .= '.' . $extension;
                }
                $new_file_path = $upload_dir['path'] . '/' . $new_filename;
                $counter++;
            }
            
            $messages[] = 'Will rename to: ' . $new_filename;
            
            // Attempt rename
            if (rename($file_path, $new_file_path)) {
                $new_files[] = $upload_dir['url'] . '/' . $new_filename;
                $files_processed++;
                $messages[] = 'SUCCESS: File renamed!';
                
                // Delete original
                if (file_exists($file_path) && $file_path !== $new_file_path) {
                    unlink($file_path);
                    $messages[] = 'Original file deleted';
                }
            } else {
                $error = error_get_last();
                $messages[] = 'FAILED: ' . ($error['message'] ?? 'Unknown error');
                $new_files[] = $file_url;
            }
        }
        
        // Update field value
        $field['value'] = $new_files;
    }
    
    $messages[] = '';
    $messages[] = '=== SUMMARY ===';
    $messages[] = 'Files processed: ' . $files_processed;
    $messages[] = '=== END ===';
    
    // Store for display
    set_transient('wembassy_file_rename_debug_' . get_current_user_id(), $messages, 60);
}

function wembassy_get_abbrev($title) {
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

// Display on confirmation
add_filter('wpforms_frontend_confirmation_message', 'wembassy_show_file_rename_results', 10, 4);

function wembassy_show_file_rename_results($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_file_rename_debug_' . get_current_user_id());
    if ($debug) {
        $html = '<div style="background:#f9f9f9; border:3px solid #2271b1; padding:15px; margin:20px 0; font-family:monospace; font-size:12px; white-space:pre-wrap;">';
        $html .= '<strong style="font-size:14px;">File Rename Debug:</strong>\n\n';
        $html .= implode("\n", $debug);
        $html .= '</div>';
        delete_transient('wembassy_file_rename_debug_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
