<?php
/**
 * Plugin Name: WPForms File Rename - Simple Debug
 * Description: Bare minimum to test hooks work
 * Version: 1.0.0-test
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Very simple test - just log that hook fired
add_action('wpforms_process_complete', 'wembassy_simple_test', 10, 4);

function wembassy_simple_test($fields, $entry, $form_data, $entry_id) {
    // Write to a custom log file
    $log_file = WP_CONTENT_DIR . '/wembassy-test.log';
    $log = fopen($log_file, 'a');
    if ($log) {
        fwrite($log, date('Y-m-d H:i:s') . " - Hook fired for entry: $entry_id\n");
        fwrite($log, "Fields count: " . count($fields) . "\n");
        
        // Check for file fields
        foreach ($fields as $fid => $field) {
            if (isset($field['type']) && $field['type'] === 'file-upload') {
                fwrite($log, "File field $fid found\n");
                fwrite($log, "Value: " . print_r($field['value'], true) . "\n");
            }
        }
        
        fwrite($log, "---\n");
        fclose($log);
    }
}

// Display on confirmation page
add_filter('wpforms_frontend_confirmation_message', 'wembassy_show_log_location', 10, 4);

function wembassy_show_log_location($message, $form_data, $fields, $entry_id) {
    return $message . '<p style="background:#ffffcc;padding:10px;margin:10px 0;">✓ File rename hooks executed. Check log file at: ' . WP_CONTENT_DIR . '/wembassy-test.log</p>';
}
