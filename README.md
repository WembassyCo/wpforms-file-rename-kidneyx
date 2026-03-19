# WPForms File Rename - KidneyX

WordPress plugin that renames uploaded files in WPForms using the `wpforms_process_entry_save` action hook.

## How It Works

When a form is submitted, this plugin:

1. **Captures form data** via `wpforms_process_entry_save` hook
2. **Finds Team Name** from a field labeled "team" (or uses timestamp as fallback)
3. **Generates abbreviation** from the form title (e.g., "Application Form" → "AF")
4. **Renames files** to pattern: `[TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf`
5. **Updates entry data** with the new file URL
6. **Deletes original files** after successful rename
7. **Logs all actions** to WordPress error log

## Filename Formula

```
[TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf
```

- **TeamName**: Value from field with "team" in the label (or timestamp if not found)
- **Abbrev**: First letter of each word in form title (max 5 chars)
- **Extension**: Preserved from original file

### Examples

| Form Title | Team Field Value | Result Filename |
|------------|------------------|-----------------|
| Application Form | Team Alpha | `TeamAlpha_AF_KidneyXEmpower_Submission.pdf` |
| Submission Portal | Beta Squad | `BetaSquad_SP_KidneyXEmpower_Submission.pdf` |
| Contact Form | _(empty)_ | `2025-03-19_09-30-15_CF_KidneyXEmpower_Submission.pdf` |

## Requirements

- WordPress 5.0+
- WPForms plugin

## Installation

1. Upload to `/wp-content/plugins/wpforms-file-rename-kidneyx/`
2. Activate in WordPress admin
3. Submit a test form and check error logs

## Debugging

All actions are logged to WordPress error log with prefix `WEMBASSY FILE RENAME:`

View logs via:
- WP Engine: `/wp-content/uploads/sites/*/wp-logs/`
- Debug Bar plugin
- Or add to `wp-config.php`: `define('WP_DEBUG_LOG', true);`

## Hook Used

- `wpforms_process_entry_save` - Fires after entry is saved, before notifications are sent
- Documentation: https://wpforms.com/developers/wpforms_process_entry_save/

## Author

Wembassy
