<?php
/**
 * Plugin Name: WPForms File Rename v4 - After Complete Hook
 * Description: Uses wpforms_process_complete when files are definitely saved
 * Version: 1.0.4-complete-hook
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Use wpforms_process_complete - fires after everything is saved
add_action('wpforms_process_complete', 'wembassy_file_rename_v4', 10, 4);

function wembassy_file_rename_v4($fields, $entry, $form_data, $entry_id) {
    if (empty($entry_id)) return;
    
    $messages = [];
    $messages[] = '=== WEMBASSY FILE RENAME v4 (Complete Hook) ===';
    $messages[] = 'Entry ID: ' . $entry_id;
    $messages[] = 'Form ID: ' . ($form_data['id'] ?? 'unknown');
    
    // Get form title
    $form_title = isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : 'Form';
    $abbrev = wembassy_v4_get_abbrev($form_title);
    $messages[] = 'Form: ' . $form_title . ' -> Abbrev: ' . $abbrev;
    
    // Find team - WPForms stores values differently in entry
    $team_name = wembassy_v4_find_team_in_entry($entry_id);
    
    if (empty($team_name)) {
        // Try from submitted data
        if (isset($_POST['wpforms']['fields']['2'])) {
            $team_name = sanitize_file_name($_POST['wpforms']['fields']['2']);
            $messages[] = 'Team from POST field 2: ' . $team_name;
        } else {
            $team_name = current_time('Y-m-d_H-i-s');
            $messages[] = 'Using timestamp: ' . $team_name;
        }
    } else {
        $messages[] = 'Team from entry: ' . $team_name;
    }
    
    $team_name = sanitize_file_name($team_name);
    $abbrev = sanitize_file_name($abbrev);
    
    // Get file fields from entry meta
    $file_fields = wembassy_v4_get_file_fields_from_entry($entry_id);
    $messages[] = 'File fields found: ' . count($file_fields);
    
    $upload_dir = wp_upload_dir();
    $files_renamed = 0;
    
    foreach ($file_fields as $field_id => $file_urls) {
        $messages[] = '';
        $messages[] = '--- Field ' . $field_id . ' ---';
        
        if (is_string($file_urls)) {
            $file_urls = [$file_urls];
        }
        
        $new_files = [];
        
        foreach ($file_urls as $file_url) {
            if (empty($file_url)) {
                $messages[] = 'Empty URL';
                continue;
            }
            
            $messages[] = 'Processing: ' . $file_url;
            
            // Extract path from URL
            $parsed = parse_url($file_url);
            if (!isset($parsed['path'])) {
                $messages[] = 'ERROR: Cannot parse URL';
                $new_files[] = $file_url;
                continue;
            }
            
            // Build server path
            $relative_path = ltrim($parsed['path'], '/');
            
            // Try different document root possibilities
            $possible_roots = [
                ABSPATH,
                $_SERVER['DOCUMENT_ROOT'] . '/',
                trailingslashit($_SERVER['DOCUMENT_ROOT']),
            ];
            
            // Also try finding from uploads structure
            if (strpos($relative_path, 'wp-content/uploads/') !== false) {
                $parts = explode('wp-content/uploads/', $relative_path);
                if (isset($parts[1])) {
                    $possible_roots[] = $upload_dir['basedir'] . '/';
                    $relative_path = $parts[1];
                }
            }
            
            $file_path = null;
            foreach ($possible_roots as $root) {
                $test_path = $root . $relative_path;
                $test_path = str_replace('//', '/', $test_path);
                
                if (file_exists($test_path)) {
                    $file_path = $test_path;
                    $messages[] = 'Found at: ' . $test_path;
                    break;
                }
            }
            
            if (!$file_path) {
                // Last resort - check upload dir directly
                $filename = basename($relative_path);
                $possible_paths = [
                    $upload_dir['path'] . '/' . $filename,
                    $upload_dir['basedir'] . '/' . $filename,
                    $upload_dir['basedir'] . '/2026/03/' . $filename,
                ];
                
                foreach ($possible_paths as $test_path) {
                    if (file_exists($test_path)) {
                        $file_path = $test_path;
                        $messages[] = 'Found at upload path: ' . $test_path;
                        break;
                    }
                }
            }
            
            if (!$file_path) {
                $messages[] = 'ERROR: File not found anywhere!';
                $new_files[] = $file_url;
                continue;
            }
            
            // Get extension and create new filename
            $file_info = wp_check_filetype($file_path);
            $extension = $file_info['ext'] ?? '';
            
            $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission';
            if ($extension) $new_filename .= '.' . $extension;
            
            // Get destination directory
            $dir = dirname($file_path);
            $new_file_path = $dir . '/' . $new_filename;
            
            // Ensure unique
            $counter = 1;
            while (file_exists($new_file_path)) {
                $new_filename = $team_name . '_' . $abbrev . '_KidneyXEmpower_Submission_' . $counter;
                if ($extension) $new_filename .= '.' . $extension;
                $new_file_path = $dir . '/' . $new_filename;
                $counter++;
            }
            
            $messages[] = 'Renaming to: ' . $new_filename;
            
            // Rename file
            if (rename($file_path, $new_file_path)) {
                // Build new URL
                $new_url = str_replace(basename($file_url), $new_filename, $file_url);
                $new_files[] = $new_url;
                $files_renamed++;
                
                $messages[] = 'SUCCESS: ' . $new_url;
                
                // Update entry meta
                wembassy_v4_update_entry_meta($entry_id, $field_id, $new_url, $file_url);
                $messages[] = 'Entry meta updated';
            } else {
                $error = error_get_last();
                $messages[] = 'FAILED: ' . ($error['message'] ?? 'Unknown error');
                $new_files[] = $file_url;
            }
        }
    }
    
    $messages[] = '';
    $messages[] = '=== SUMMARY ===';
    $messages[] = 'Files renamed: ' . $files_renamed;
    $messages[] = '=== END ===';
    
    set_transient('wembassy_v4_debug_' . get_current_user_id(), $messages, 60);
}

// Also hook into process_entry_save to log field data
add_action('wpforms_process_entry_save', 'wembassy_v4_capture_team', 10, 4);
function wembassy_v4_capture_team($fields, $entry, $form_id, $form_data) {
    // Store team name for later use
    if (isset($_POST['wpforms']['fields']['2'])) {
        set_transient('wembassy_team_' . session_id(), sanitize_file_name($_POST['wpforms']['fields']['2']), 300);
    }
}

function wembassy_v4_find_team_in_entry($entry_id) {
    // Try from transient
    $team = get_transient('wembassy_team_' . session_id());
    if ($team) {
        delete_transient('wembassy_team_' . session_id());
        return $team;
    }
    
    // Try from entry meta
    if (function_exists('wpforms') && wpforms()->entry) {
        $entry = wpforms()->entry->get($entry_id);
        if ($entry && isset($entry->fields['2'])) {
            return sanitize_file_name($entry->fields['2']->value);
        }
    }
    
    return '';
}

function wembassy_v4_get_file_fields_from_entry($entry_id) {
    $file_fields = [];
    
    if (!function_exists('wpforms') || !wpforms()->entry) {
        return $file_fields;
    }
    
    $entry = wpforms()->entry->get($entry_id);
    if (!$entry || !isset($entry->fields)) {
        return $file_fields;
    }
    
    foreach ($entry->fields as $field_id => $field) {
        if ($field->type === 'file-upload' && !empty($field->value)) {
            $file_fields[$field_id] = $field->value;
        }
    }
    
    return $file_fields;
}

function wembassy_v4_update_entry_meta($entry_id, $field_id, $new_url, $old_url) {
    if (!function_exists('wpforms') || !wpforms()->entry) {
        return;
    }
    
    // Update the entry meta with new URL
    wpforms()->entry->update_meta($entry_id, $field_id, $new_url);
}

function wembassy_v4_get_abbrev($title) {
    $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
    $words = explode(' ', $clean);
    $abbrev = '';
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $abbrev .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($abbrev, 0, 5) ?: 'KXE';
}

// Display results
add_filter('wpforms_frontend_confirmation_message', 'wembassy_v4_show_results', 10, 4);
function wembassy_v4_show_results($message, $form_data, $fields, $entry_id) {
    $debug = get_transient('wembassy_v4_debug_' . get_current_user_id());
    if ($debug) {
        $html = '<div style="background:#f9f9f9; border:3px solid #2271b1; padding:15px; margin:20px 0; font-family:monospace; font-size:12px; white-space:pre-wrap;">';
        $html .= '<strong>File Rename v4 (Complete Hook):</strong>\n\n';
        $html .= implode("\n", $debug);
        $html .= '</div>';
        delete_transient('wembassy_v4_debug_' . get_current_user_id());
        return $message . $html;
    }
    return $message;
}
