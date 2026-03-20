<?php
/**
 * Plugin Name: WPForms Entry URL Fixer
 * Description: Admin tool to update WPForms entry URLs after file rename
 * Version: 1.0.1
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
                $message = '<div class="notice notice-success"><p>✅ Entry #' . $entry_id . ' updated successfully! New URL: ' . esc_html($new_url) . '</p></div>';
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
        
        <div class="notice notice-warning">
            <p><strong>How this works:</strong> The entry shows the OLD URL, but the file has been renamed. This tool finds the renamed file and updates the entry to point to it.</p>
        </div>
        
        <h2>Recent Entries with File Uploads</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Entry ID</th>
                    <th>Form ID</th>
                    <th>Date</th>
                    <th>Field ID</th>
                    <th>Current Entry URL</th>
                    <th>File Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $upload_dir = wp_upload_dir();
                foreach ($entries as $entry): 
                    $fields = json_decode($entry->fields, true);
                    if (!is_array($fields)) continue;
                    
                    foreach ($fields as $fid => $field):
                        if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
                        if (empty($field['value'])) continue;
                        
                        $url = is_array($field['value']) ? $field['value'][0] : $field['value'];
                        $filename = basename($url);
                        
                        // Check if file exists at stored URL
                        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
                        $exists = file_exists($file_path);
                        
                        // Look for renamed file (even if current exists, check for renamed version)
                        $renamed_url = '';
                        $renamed_exists = false;
                        $dir = dirname($file_path);
                        
                        if (is_dir($dir)) {
                            // Look for files matching the KidneyX pattern
                            $pattern = $dir . '/*KidneyXEmpower_Submission*.pdf';
                            $files = glob($pattern);
                            
                            if (!empty($files)) {
                                foreach ($files as $file) {
                                    $test_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file);
                                    // If this is different from current URL, it's the renamed one
                                    if ($test_url !== $url) {
                                        $renamed_url = $test_url;
                                        $renamed_exists = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Show row if file doesn't exist OR if renamed file found
                        $needs_fix = !$exists || ($renamed_exists && $renamed_url !== $url);
                ?>
                <tr>
                    <td><?php echo $entry->entry_id; ?></td>
                    <td><?php echo $entry->form_id; ?></td>
                    <td><?php echo $entry->date; ?></td>
                    <td><?php echo $fid; ?></td>
                    <td>
                        <code style="font-size:10px;word-break:break-all;display:block;max-width:300px;"><?php echo esc_html($url); ?></code>
                    </td>
                    <td>
                        <?php if ($exists): ?>
                            <span style="color:green;">✅ File exists at URL</span>
                        <?php else: ?>
                            <span style="color:red;">❌ File NOT found at URL</span>
                        <?php endif; ?>
                        <?php if ($renamed_exists && $renamed_url !== $url): ?>
                            <br><span style="color:orange;">📝 Renamed file found:</span>
                            <br><code style="font-size:10px;"><?php echo basename($renamed_url); ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($renamed_exists && $renamed_url !== $url): ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('wembassy_fix_entry'); ?>
                                <input type="hidden" name="entry_id" value="<?php echo $entry->entry_id; ?>">
                                <input type="hidden" name="field_id" value="<?php echo $fid; ?>">
                                <input type="hidden" name="new_url" value="<?php echo esc_attr($renamed_url); ?>">
                                <button type="submit" name="fix_entry" class="button button-primary">
                                    Update Entry URL
                                </button>
                            </form>
                        <?php elseif (!$exists && !$renamed_exists): ?>
                            <span style="color:red;">No renamed file found</span>
                        <?php else: ?>
                            <span style="color:green;">No fix needed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
        
        <h2 style="margin-top:30px;">Quick Fix: Form 88, Field 8</h2>
        
        <p>Since you mentioned Form ID 88 and Field ID 8, use this quick form:</p>
        
        <form method="post">
            <?php wp_nonce_field('wembassy_fix_entry'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="quick_entry_id">Entry ID</label></th>
                    <td><input type="number" name="entry_id" id="quick_entry_id" required></td>
                </tr>
                <tr>
                    <th>Field ID</th>
                    <td><input type="text" name="field_id" value="8" readonly> (locked to 8)</td>
                </tr>
                <tr>
                    <th><label for="quick_new_url">New File URL</label></th>
                    <td>
                        <input type="url" name="new_url" id="quick_new_url" style="width:100%;" placeholder="https://kidneyxempodev.wpenginepowered.com/wp-content/uploads/2026/03/TeamName_ABBR_KidneyXEmpower_Submission.pdf" required>
                        <p class="description">Paste the full URL of the renamed file here</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Update Entry URL', 'primary', 'fix_entry'); ?>
        </form>
        
        <h2 style="margin-top:30px;">Bulk Fix All Entries</h2>
        
        <p>This will scan ALL entries and fix any that have renamed files:</p>
        
        <form method="get">
            <input type="hidden" name="page" value="wpforms-entry-fixer">
            <input type="hidden" name="bulk_fix" value="1">
            <?php submit_button('Run Bulk Fix', 'secondary'); ?>
        </form>
        
        <?php if (isset($_GET['bulk_fix'])): 
            $results = wembassy_bulk_fix_all_entries();
        ?>
            <div class="notice notice-info">
                <p><strong>Bulk Fix Results:</strong></p>
                <ul>
                    <li>Entries checked: <?php echo $results['checked']; ?></li>
                    <li>Entries fixed: <?php echo $results['fixed']; ?></li>
                    <li>Errors: <?php echo $results['errors']; ?></li>
                </ul>
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
        error_log("Entry Fixer: Could not find entry $entry_id");
        return false;
    }
    
    $fields = json_decode($row['fields'], true);
    if (!is_array($fields)) {
        error_log("Entry Fixer: Could not decode fields for entry $entry_id");
        return false;
    }
    
    if (!isset($fields[$field_id])) {
        error_log("Entry Fixer: Field $field_id not found in entry $entry_id");
        return false;
    }
    
    // Store old URL for logging
    $old_url = is_array($fields[$field_id]['value']) ? $fields[$field_id]['value'][0] : $fields[$field_id]['value'];
    
    // Update the field
    $fields[$field_id]['value'] = $new_url;
    $fields[$field_id]['file'] = $new_url;
    $fields[$field_id]['file_original'] = basename($new_url);
    
    // Update database
    $result = $wpdb->update(
        $table,
        array('fields' => wp_json_encode($fields)),
        array('entry_id' => $entry_id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        error_log("Entry Fixer: Database update failed for entry $entry_id: " . $wpdb->last_error);
        return false;
    }
    
    error_log("Entry Fixer: Successfully updated entry $entry_id, field $field_id from $old_url to $new_url");
    return true;
}

// Bulk fix all entries
function wembassy_bulk_fix_all_entries() {
    global $wpdb;
    $table = $wpdb->prefix . 'wpforms_entries';
    $upload_dir = wp_upload_dir();
    
    $results = array('checked' => 0, 'fixed' => 0, 'errors' => 0);
    
    // Get all entries
    $entries = $wpdb->get_results("SELECT entry_id, fields FROM $table ORDER BY entry_id DESC");
    
    foreach ($entries as $entry) {
        $fields = json_decode($entry->fields, true);
        if (!is_array($fields)) continue;
        
        $updated = false;
        
        foreach ($fields as $fid => $field) {
            if (!isset($field['type']) || $field['type'] !== 'file-upload') continue;
            if (empty($field['value'])) continue;
            
            $results['checked']++;
            
            $url = is_array($field['value']) ? $field['value'][0] : $field['value'];
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            
            // Skip if file exists
            if (file_exists($file_path)) continue;
            
            // Look for renamed file
            $dir = dirname($file_path);
            if (!is_dir($dir)) continue;
            
            $pattern = $dir . '/*KidneyXEmpower_Submission*.pdf';
            $files = glob($pattern);
            
            if (!empty($files)) {
                // Use the first matching file
                $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $files[0]);
                
                // Only update if different
                if ($new_url !== $url) {
                    $fields[$fid]['value'] = $new_url;
                    $fields[$fid]['file'] = $new_url;
                    $fields[$fid]['file_original'] = basename($new_url);
                    $updated = true;
                }
            }
        }
        
        if ($updated) {
            $result = $wpdb->update(
                $table,
                array('fields' => wp_json_encode($fields)),
                array('entry_id' => $entry->entry_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $results['fixed']++;
            } else {
                $results['errors']++;
            }
        }
    }
    
    return $results;
}
