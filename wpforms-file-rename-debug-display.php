<?php
/**
 * Plugin Name: WPForms File Rename - Debug Display
 * Description: Shows debug output on confirmation page
 * Version: 1.0.0-debug-display
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Store debug messages
function wembassy_store_msg($msg) {
    static $messages = array();
    $messages[] = $msg;
    // Also store in transient in case page redirects
    $existing = get_transient('wembassy_display_debug') ?: array();
    $existing[] = $msg;
    set_transient('wembassy_display_debug', $existing, 30);
}

add_action('wpforms_process_before', 'wembassy_display_before', 1, 2);

function wembassy_display_before($entry, $form_data) {
    wembassy_store_msg('=== FORM START ===');
    wembassy_store_msg('Form ID: ' . (isset($form_data['id']) ? $form_data['id'] : 'unknown'));
    
    if (isset($_POST['wpforms']['fields']['2'])) {
        wembassy_store_msg('Team field 2: ' . $_POST['wpforms']['fields']['2']);
    } else {
        wembassy_store_msg('Team field 2: NOT FOUND');
    }
    
    // Store team for later
    $team = current_time('Y-m-d_H-i-s');
    if (isset($_POST['wpforms']['fields']['2'])) {
        $team_raw = $_POST['wpforms']['fields']['2'];
        if (is_string($team_raw) && !empty($team_raw)) {
            $team = sanitize_file_name($team_raw);
        }
    }
    set_transient('wembassy_display_team', $team, 60);
    wembassy_store_msg('Team set to: ' . $team);
}

// Intercept uploads
add_filter('wp_handle_upload_prefilter', 'wembassy_display_prefilter', 1);

function wembassy_display_prefilter($file) {
    wembassy_store_msg('');
    wembassy_store_msg('--- UPLOAD START ---');
    wembassy_store_msg('Original name: ' . $file['name']);
    wembassy_store_msg('Temp file: ' . $file['tmp_name']);
    wembassy_store_msg('Size: ' . $file['size']);
    
    $team = get_transient('wembassy_display_team');
    wembassy_store_msg('Team from transient: ' . ($team ?: 'NOT FOUND'));
    
    if ($team) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = $team . '_FORM_KidneyX_Submission.' . $ext;
        wembassy_store_msg('Will rename to: ' . $new_name);
        
        $file['name'] = $new_name;
        wembassy_store_msg('*** NAME CHANGED ***');
    } else {
        wembassy_store_msg('*** NO TEAM - NOT RENAMING ***');
    }
    
    return $file;
}

add_filter('wp_handle_upload', 'wembassy_display_after_upload', 1, 2);

function wembassy_display_after_upload($upload, $context) {
    wembassy_store_msg('');
    wembassy_store_msg('--- UPLOAD COMPLETE ---');
    if (is_array($upload)) {
        wembassy_store_msg('File: ' . $upload['file']);
        wembassy_store_msg('URL: ' . $upload['url']);
        wembassy_store_msg('Type: ' . $upload['type']);
    } else {
        wembassy_store_msg('Upload failed or returned: ' . print_r($upload, true));
    }
    return $upload;
}

add_action('wpforms_process_complete', 'wembassy_display_complete', 999, 4);

function wembassy_display_complete($fields, $entry, $form_data, $entry_id) {
    wembassy_store_msg('');
    wembassy_store_msg('=== WPForms Complete ===');
    wembassy_store_msg('Entry ID: ' . $entry_id);
    
    foreach ($fields as $field_id => $field) {
        if ($field['type'] === 'file-upload') {
            wembassy_store_msg('File field ' . $field_id . ' final value: ' . $field['value']);
        }
    }
    
    wembassy_store_msg('=== END ===');
}

// Display on confirmation page
add_filter('wpforms_frontend_confirmation_message', 'wembassy_show_debug_results', 10, 4);

function wembassy_show_debug_results($message, $form_data, $fields, $entry_id) {
    // Get stored messages
    $debug = get_transient('wembassy_display_debug');
    
    if (!$debug) {
        $debug = array('No debug messages found');
    }
    
    // Build display
    $html = '<div style="background:#f0f0f0; border:3px solid #d63638; padding:15px; margin:20px 0; font-family:monospace; font-size:12px;">';
    $html .= '<strong style="color:#d63638; font-size:14px;">Debug Output:</strong><br><br>';
    $html .= nl2br(esc_html(implode("\n", $debug)));
    $html .= '</div>';
    
    // Clear transient
    delete_transient('wembassy_display_debug');
    delete_transient('wembassy_display_team');
    
    return $message . $html;
}

// Admin notice
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p><strong>WPForms Debug:</strong> Debug output will appear on form confirmation page</p></div>';
});
