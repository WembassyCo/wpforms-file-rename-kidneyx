<?php
/**
 * Plugin Name: WPForms Entry URL Fixer
 * Description: Admin tool to update WPForms entry URLs after file rename
 * Version: 1.0.0
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
    $updated = false;
    
    // Handle fix action
    if (isset($_POST['fix_entry']) && isset($_POST['entry_id']) && isset($_POST['field_id'])) {
        check_admin_referer('wembassy_fix_entry');
        $entry_id = intval($_POST['entry_id']);
        $field_id = sanitize_text_field($_POST['field_id']);
        $new_url = isset($_POST['new_url']) ? esc_url_raw($_POST['new_url']) : '';
        
        if ($entry_id && $new_url) {
            $result = wembassy_fix_entry_url($entry_id, $field_id, $new_url);
            if ($result) {
                $message = '<div class="notice notice-success"><p>✅ Entry #' . $entry_id . ' updated successfully!</p></div>';
                $updated = true;
            } else {
                $message = '<div class="notice notice-error"><p>❌ Failed to update entry #' . $entry_id . '</p></div>';
            }
        }
    }
    
    // Get recent entries with file uploads
    $table = $wpdb->prefix . 'wpforms_entries';
    $entries = $wpdb->get_results("SELECT entry_id, form_id, fields, date FROM $table ORDER BY entry_id DESC LIMIT 20");
    
    ?>
    <div class="wrap">
        <h1>WPForms Entry URL Fixer</h1>
        
        <?php echo $message; ?>
        
        <p>This tool helps fix entry URLs after files have been renamed. Look for entries where the file URL doesn't match the actual filename.</p>
        
        <h2>Recent Entries with File Uploads</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Entry ID</th>
                    <th>Form ID</th>
                    <th>Date</th>
                    <th>File Field</th>
                    <th>Current URL</th>
                    <th>Action</th>
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
                        
                        // Check if file exists with current name
                        $upload_dir = wp_upload_dir();
                        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
                        $exists = file_exists($file_path);
                        
                        // Try to find renamed file
                        $renamed_url = '';
                        if (!$exists && preg_match('/kidneyxempodev.*\.pdf$/i', $url)) {
                            // Look for renamed files in same directory
                            $dir = dirname($file_path);
                            if (is_dir($dir)) {
                                $files = glob($dir . '/*KidneyXEmpower_Submission*.pdf');
                                if (!empty($files)) {
                                    $renamed_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $files[0]);
                                }
                            }
                        }
                ?>
                <tr>
                    <td><?php echo $entry->entry_id; ?></td>
                    <td><?php echo $entry->form_id; ?></td>
                    <td><?php echo $entry->date; ?></td>
                    <td>Field <?php echo $fid; ?></td>
                    <td>
                        <code style="font-size:11px;word-break:break-all;"><?php echo esc_html($url); ?></code><br>
                        <?php if ($exists): ?>
                            <span style="color:green;">✅ File exists</span>
                        <?php else: ?>
                            <span style="color:red;">❌ File not found</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$exists && $renamed_url): ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('wembassy_fix_entry'); ?>
                                <input type="hidden" name="entry_id" value="<?php echo $entry->entry_id; ?>">
                                <input type="hidden" name="field_id" value="<?php echo $fid; ?>">
                                <input type="hidden" name="new_url" value="<?php echo esc_attr($renamed_url); ?>">
                                <button type="submit" name="fix_entry" class="button button-primary">
                                    Fix URL
                                </button>
                            </form>
                            <br><small>New: <code><?php echo basename($renamed_url); ?></code></small>
                        <?php elseif ($exists): ?>
                            <span class="button disabled" style="opacity:0.5;">No fix needed</span>
                        <?php else: ?>
                            <span style="color:orange;">Manual fix required</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
        
        <h2 style="margin-top:30px;">Manual URL Fix</h2>
        <form method="post">
            <?php wp_nonce_field('wembassy_fix_entry'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="manual_entry_id">Entry ID</label></th>
                    <td><input type="number" name="entry_id" id="manual_entry_id" required></td>
                </tr>
                <tr>
                    <th><label for="manual_field_id">Field ID</label></th>
                    <td><input type="text" name="field_id" id="manual_field_id" placeholder="e.g., 5" required></td>
                </tr>
                <tr>
                    <th><label for="manual_new_url">New File URL</label></th>
                    <td><input type="url" name="new_url" id="manual_new_url" style="width:100%;" placeholder="https://.../TeamName_ABBR_KidneyXEmpower_Submission.pdf" required></td>
                </tr>
            </table>
            <?php submit_button('Update Entry URL', 'primary', 'fix_entry'); ?>
        </form>
        
        <h2 style="margin-top:30px;">Bulk Scan for Renamed Files</h2>
        <p>Click to scan upload directories and match renamed files to entries:</p>
        <form method="get">
            <input type="hidden" name="page" value="wpforms-entry-fixer">
            <input type="hidden" name="bulk_scan" value="1">
            <?php submit_button('Run Bulk Scan', 'secondary'); ?>
        </form>
        
        <?php if (isset($_GET['bulk_scan'])): 
            $fixed = wembassy_bulk_fix_entries();
        ?>
            <div class="notice notice-info">
                <p>Bulk scan complete. Fixed <?php echo $fixed; ?> entries.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Fix entry URL
function wembassy_fix_entry_url($entry_id, $field_id, $new_url) {
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    
    // Get current entry
    $row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM $table WHERE entry_id = %d", $entry_id), ARRAY_A);
    
    if (!$row || empty($row['fields'])) {
        return false;
    }
    
    $fields = json_decode($row['fields'], true);
    if (!is_array($fields) || !isset($fields[$field_id])) {
        return false;
    }
    
    // Update the field
    $fields[$field_id]['value'] = $new_url;
    $fields[$field_id]['file'] = $new_url;
    $fields[$field_id]['file_original'] = basename($new_url);
    
    // Update database
    $result = $wpdb->update(
        $table,
        array('fields' => json_encode($fields)),
        array('entry_id' => $entry_id),
        array('%s'),
        array('%d')
    );
    
    return $result !== false;
}

// Bulk scan and fix
function wembassy_bulk_fix_entries() {
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    $upload_dir = wp_upload_dir();
    $fixed = 0;
    
    // Get all entries with file uploads
    $entries = $wpdb->get_results("SELECT entry_id, fields FROM $table");
    
    foreach ($entries as $entry) {
        $fields = json_decode($entry->fields, true);
        if (!is_array($fields)) continue;
        
        $updated = false;
        
        foreach ($fields as $fid => $field) {
            if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
            if (empty($field['value'])) continue;
            
            $url = is_array($field['value']) ? $field['value'][0] : $field['value'];
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            
            if (file_exists($file_path)) continue; // File exists, skip
            
            // Look for renamed file
            $dir = dirname($file_path);
            if (!is_dir($dir)) continue;
            
            $pattern = $dir . '/*KidneyXEmpower_Submission*.pdf';
            $files = glob($pattern);
            
            if (!empty($files)) {
                $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $files[0]);
                $fields[$fid]['value'] = $new_url;
                $fields[$fid]['file'] = $new_url;
                $fields[$fid]['file_original'] = basename($new_url);
                $updated = true;
            }
        }
        
        if ($updated) {
            $wpdb->update(
                $table,
                array('fields' => json_encode($fields)),
                array('entry_id' => $entry->entry_id),
                array('%s'),
                array('%d')
            );
            $fixed++;
        }
    }
    
    return $fixed;
}
