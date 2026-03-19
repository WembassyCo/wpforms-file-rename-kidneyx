<?php
/**
 * Plugin Name: WPForms File Rename - Debug Final
 * Description: Full debug version to diagnose file path issues
 * Version: 1.0.0-debug-final
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Create log file
$log_file = WP_CONTENT_DIR . '/wembassy-final-debug.log';

function wembassy_log($msg) {
    global $log_file;
    $line = date('Y-m-d H:i:s') . ' - ' . $msg . "\n";
    file_put_contents($log_file, $line, FILE_APPEND);
}

// Clear log on activation
register_activation_hook(__FILE__, function() {
    global $log_file;
    file_put_contents($log_file, "Debug log started\n", FILE_APPEND);
});

// Log when WPForms starts processing
add_action('wpforms_process_before', 'wembassy_debug_before', 1, 2);

function wembassy_debug_before($entry, $form_data) {
    wembassy_log('=== FORM SUBMISSION STARTED ===');
    wembassy_log('Form ID: ' . (isset($form_data['id']) ? $form_data['id'] : 'unknown'));
    wembassy_log('Post data fields: ' . print_r($_POST['wpforms']['fields'] ?? 'not set', true));
    
    // Store team name
    $team = current_time('Y-m-d_H-i-s');
    if (isset($_POST['wpforms']['fields']['2'])) {
        $team_raw = $_POST['wpforms']['fields']['2'];
        wembassy_log('Field 2 raw value: ' . var_export($team_raw, true));
        if (is_string($team_raw) && !empty($team_raw)) {
            $team = sanitize_file_name($team_raw);
        }
    }
    wembassy_log('Team name set to: ' . $team);
    
    // Also store abbreviation
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
    $abbrev = substr($abbrev, 0, 5) ?: 'SP';
    
    wembassy_log('Form title: ' . $form_title);
    wembassy_log('Abbrev: ' . $abbrev);
    
    set_transient('wembassy_debug_team', $team, 120);
    set_transient('wembassy_debug_abbrev', $abbrev, 120);
    set_transient('wembassy_debug_submitting', true, 120);
}

// Check file uploads via WordPress filter
add_filter('wp_handle_upload_prefilter', 'wembassy_debug_prefilter', 1);

function wembassy_debug_prefilter($file) {
    $is_wpforms = get_transient('wembassy_debug_submitting');
    
    wembassy_log('');
    wembassy_log('--- WordPress Upload Started ---');
    wembassy_log('File name: ' . $file['name']);
    wembassy_log('File size: ' . $file['size']);
    wembassy_log('Temp name: ' . $file['tmp_name']);
    wembassy_log('Is WPForms submission: ' . ($is_wpforms ? 'YES' : 'NO'));
    
    if ($is_wpforms) {
        $team = get_transient('wembassy_debug_team') ?: 'Unknown';
        $abbrev = get_transient('wembassy_debug_abbrev') ?: 'FORM';
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = $team . '_' . $abbrev . '_KidneyX_Submission.' . $ext;
        
        wembassy_log('Would rename to: ' . $new_name);
        
        // Actually rename
        $file['name'] = $new_name;
        wembassy_log('File name changed to: ' . $file['name']);
    }
    
    return $file;
}

// Log after upload completes
add_filter('wp_handle_upload', 'wembassy_debug_after_upload', 10, 2);

function wembassy_debug_after_upload($upload, $context) {
    wembassy_log('');
    wembassy_log('--- WordPress Upload Completed ---');
    wembassy_log('Context: ' . $context);
    wembassy_log('File: ' . print_r($upload, true));
    
    return $upload;
}

// Log WPForms complete
add_action('wpforms_process_complete', 'wembassy_debug_complete', 999, 4);

function wembassy_debug_complete($fields, $entry, $form_data, $entry_id) {
    wembassy_log('');
    wembassy_log('=== WPForms Process Complete ===');
    wembassy_log('Entry ID: ' . $entry_id);
    
    foreach ($fields as $field_id => $field) {
        if ($field['type'] === 'file-upload') {
            wembassy_log('File field ' . $field_id . ' value: ' . print_r($field['value'], true));
        }
    }
    
    delete_transient('wembassy_debug_submitting');
    wembassy_log('=== FORM SUBMISSION COMPLETE ===');
}

// Show admin notice with log location
add_action('admin_notices', 'wembassy_debug_notice');

function wembassy_debug_notice() {
    global $log_file;
    $exists = file_exists($log_file) ? 'exists' : 'not created yet';
    echo '<div class="notice notice-info"><p><strong>WPForms Debug:</strong> Log file: ' . $log_file . ' (' . $exists . ')</p></div>';
}
