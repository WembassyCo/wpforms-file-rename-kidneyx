<?php
/**
 * Plugin Name: WPForms Entry URL Fixer
 * Description: Admin tool to update WPForms entry URLs after file rename
 * Version: 1.0.2
 * Author: Wembassy
 */

if (!defined('ABSPATH')) exit;

// Add admin menu
add_action('admin_menu', 'wembassy_entry_fixer_menu');

function wembassy_entry_fixer_menu() {
    add_management_page(
        'WPForms Entry Fixer',
        'WPForms Entry Fixer',
        'manage_options',
        'wpforms-entry-fixer',
        'wembassy_entry_fixer_page'
    );
}

// Admin page
function wembassy_entry_fixer_page() {
    global $wpdb;
    
    $message = '';
    $debug_output = '';
    
    // Handle direct update
    if (isset($_POST['update_entry']) && isset($_POST['entry_id'])) {
        check_admin_referer('wembassy_fix_entry');
        $entry_id = intval($_POST['entry_id']);
        $field_id = sanitize_text_field($_POST['field_id']);
        $new_url = esc_url_raw($_POST['new_url']);
        
        if ($entry_id && $field_id && $new_url) {
            $result = wembassy_manual_update_entry($entry_id, $field_id, $new_url);
            if ($result) {
                $message = '<div class="notice notice-success"><p>✅ Entry #' . $entry_id . ' updated!</p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>❌ Update failed. Check debug below.</p></div>';
            }
        }
    }
    
    // Show entry details if requested
    if (isset($_GET['view_entry'])) {
        $entry_id = intval($_GET['view_entry']);
        $debug_output = wembassy_debug_entry($entry_id);
    }
    
    // Get entries with file uploads
    $table = $wpdb->prefix . 'wpforms_entries';
    $entries = $wpdb->get_results("SELECT entry_id, form_id, fields, date FROM $table ORDER BY entry_id DESC LIMIT 50");
    
    $upload_dir = wp_upload_dir();
    ?>
    
    <div class="wrap">
        <h1>WPForms Entry URL Fixer</h1>
        
        <?php echo $message; ?>
        
        <div class="notice notice-info">
            <p><strong>Instructions:</strong> Files are being renamed correctly, but the entry database still shows the old URL. 
            Find the entry you want to fix, click "View & Fix", then paste the new file URL and click Update.</p>
        </div>
        
        <?php if ($debug_output): ?>
        <div style="background:#f0f0f0;padding:15px;margin:20px 0;border:2px solid #0073aa;">
            <h3>Entry Debug Information</h3>
            <?php echo $debug_output; ?>
            <p><a href="<?php echo admin_url('tools.php?page=wpforms-entry-fixer'); ?>" class="button">← Back to List</a></p>
        </div>
        <?php endif; ?>
        
        <h2>Entries with File Uploads</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Entry ID</th>
                    <th>Form ID</th>
                    <th>Date</th>
                    <th>Field ID</th>
                    <th>Current URL</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): 
                    $fields = json_decode($entry->fields, true);
                    if (!is_array($fields)) continue;
                    
                    foreach ($fields as $fid => $field):
                        if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
                        if (empty($field['value'])) continue;
                        
                        $url = is_array($field['value']) ? $field['value'][0] : $field['value'];
                        $filename = basename($url);
                        
                        // Check if file exists
                        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
                        $exists = file_exists($file_path);
                        
                        // Find renamed files
                        $dir = dirname($file_path);
                        $renamed_files = array();
                        if (is_dir($dir)) {
                            $pattern = $dir . '/*KidneyX*.pdf';
                            $files = glob($pattern);
                            foreach ($files as $file) {
                                $renamed_files[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file);
                            }
                        }
                        
                        // Also check media library
                        $attachment_id = wembassy_get_attachment_by_url($url);
                ?>
                <tr>
                    <td><?php echo $entry->entry_id; ?></td>
                    <td><?php echo $entry->form_id; ?></td>
                    <td><?php echo $entry->date; ?></td>
                    <td><?php echo $fid; ?></td>
                    <td>
                        <code style="font-size:10px;word-break:break-all;display:block;max-width:350px;">
                            <?php echo esc_html($url); ?>
                        </code>
                        <?php if ($attachment_id): ?>
                            <br><small>Media ID: <?php echo $attachment_id; ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($exists): ?>
                            <span style="color:green;">✅ File exists</span>
                        <?php else: ?>
                            <span style="color:red;">❌ Not found</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('tools.php?page=wpforms-entry-fixer&view_entry=' . $entry->entry_id); ?>" 
                           class="button button-small">
                            View & Fix
                        </a>
                        <?php if (!$exists && !empty($renamed_files)): ?>
                            <br><small style="color:orange;">Found <?php echo count($renamed_files); ?> renamed file(s)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function wembassy_debug_entry($entry_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    
    $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE entry_id = %d", $entry_id), ARRAY_A);
    
    if (!$entry) {
        return '<p style="color:red;">Entry not found!</p>';
    }
    
    $fields = json_decode($entry['fields'], true);
    $upload_dir = wp_upload_dir();
    
    $output = '<h4>Entry #' . $entry_id . '</h4>';
    $output .= '<pre style="background:#fff;padding:10px;overflow:auto;max-height:400px;">';
    $output .= "Form ID: " . $entry['form_id'] . "\n";
    $output .= "Date: " . $entry['date'] . "\n\n";
    
    foreach ($fields as $fid => $field) {
        if ($field['type'] === 'file-upload' && !empty($field['value'])) {
            $url = is_array($field['value']) ? $field['value'][0] : $field['value'];
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            
            $output .= "=== Field $fid ===\n";
            $output .= "Type: " . $field['type'] . "\n";
            $output .= "Current URL: $url\n";
            $output .= "File path: $file_path\n";
            $output .= "Exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n\n";
            
            // Look for renamed files
            $dir = dirname($file_path);
            if (is_dir($dir)) {
                $files = glob($dir . '/*.pdf');
                if (!empty($files)) {
                    $output .= "PDF files in same directory:\n";
                    foreach ($files as $f) {
                        $f_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $f);
                        $output .= "  - " . basename($f) . "\n";
                        $output .= "    URL: $f_url\n";
                    }
                }
            }
            $output .= "\n";
            
            // Update form
            $output .= '<form method="post" style="background:#e5f5fa;padding:15px;margin:15px 0;">';
            $output .= wp_nonce_field('wembassy_fix_entry', '_wpnonce', true, false);
            $output .= '<input type="hidden" name="entry_id" value="' . $entry_id . '">';
            $output .= '<input type="hidden" name="field_id" value="' . $fid . '">';
            $output .= '<p><strong>Update Field ' . $fid . ' URL:</strong></p>';
            $output .= '<p><input type="url" name="new_url" style="width:100%;" placeholder="Paste new file URL here" required></p>';
            $output .= '<p><input type="submit" name="update_entry" class="button button-primary" value="Update Entry URL"></p>';
            $output .= '</form>';
        }
    }
    
    $output .= '</pre>';
    
    // Show raw fields data
    $output .= '<h4>Raw Fields Data (JSON):</h4>';
    $output .= '<pre style="background:#fff;padding:10px;overflow:auto;max-height:300px;font-size:10px;">';
    $output .= esc_html(json_encode($fields, JSON_PRETTY_PRINT));
    $output .= '</pre>';
    
    return $output;
}

function wembassy_manual_update_entry($entry_id, $field_id, $new_url) {
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    
    error_log("Entry Fixer: Starting update for entry $entry_id, field $field_id");
    
    // Get entry
    $row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM $table WHERE entry_id = %d", $entry_id), ARRAY_A);
    
    if (!$row || empty($row['fields'])) {
        error_log("Entry Fixer: Entry $entry_id not found or has no fields");
        return false;
    }
    
    $fields = json_decode($row['fields'], true);
    if (!is_array($fields)) {
        error_log("Entry Fixer: Could not decode fields JSON");
        return false;
    }
    
    if (!isset($fields[$field_id])) {
        error_log("Entry Fixer: Field $field_id not found in entry");
        return false;
    }
    
    // Store old for logging
    $old_val = $fields[$field_id]['value'];
    $new_filename = basename($new_url);
    error_log("Entry Fixer: Old value: " . print_r($old_val, true));
    
    // Update main value
    $fields[$field_id]['value'] = $new_url;
    
    // Update value_raw array if it exists (this is what admin displays)
    if (isset($fields[$field_id]['value_raw']) && is_array($fields[$field_id]['value_raw'])) {
        foreach ($fields[$field_id]['value_raw'] as $idx => $file_data) {
            if (isset($file_data['value'])) {
                $fields[$field_id]['value_raw'][$idx]['value'] = $new_url;
            }
            if (isset($file_data['file'])) {
                $fields[$field_id]['value_raw'][$idx]['file'] = $new_filename;
            }
            if (isset($file_data['file_original'])) {
                $fields[$field_id]['value_raw'][$idx]['file_original'] = $new_filename;
            }
            if (isset($file_data['file_user_name'])) {
                $fields[$field_id]['value_raw'][$idx]['file_user_name'] = $new_filename;
            }
            if (isset($file_data['name'])) {
                $fields[$field_id]['value_raw'][$idx]['name'] = $new_filename;
            }
        }
    }
    
    // Update other field keys
    $fields[$field_id]['file'] = $new_url;
    $fields[$field_id]['file_original'] = $new_filename;
    $fields[$field_id]['file_user_name'] = $new_filename;
    $fields[$field_id]['name'] = $new_filename;
    
    // Encode
    $new_json = wp_json_encode($fields);
    error_log("Entry Fixer: New JSON length: " . strlen($new_json));
    
    // Update
    $result = $wpdb->update(
        $table,
        array('fields' => $new_json),
        array('entry_id' => $entry_id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        error_log("Entry Fixer: DB update failed: " . $wpdb->last_error);
        return false;
    }
    
    error_log("Entry Fixer: SUCCESS - Updated entry $entry_id");
    return true;
}

function wembassy_get_attachment_by_url($url) {
    global $wpdb;
    $file = basename($url);
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid LIKE '%%%s';", $file));
    if (!empty($attachment)) {
        return $attachment[0];
    }
    return false;
}
