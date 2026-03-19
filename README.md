# WPForms File Rename - KidneyX

WordPress plugin that renames uploaded files in WPForms using WPForms 1.10+ compatible hooks.

## How It Works

When a form is submitted, this plugin:

1. **Captures team name early** via `wpforms_process_before` hook
2. **Finds Team Name** from field ID 2 (or uses timestamp as fallback)
3. **Generates abbreviation** from the form title (e.g., "Submission Portal" → "SP")
4. **Renames files** to pattern: `[TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf`
5. **Updates entry data** with the new file URLs
6. **Deletes original files** after successful rename

## Filename Formula

```
[TeamName]_[Abbrev]_KidneyXEmpower_Submission.pdf
```

- **TeamName**: Value from field ID 2 (or timestamp if not found)
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
- WPForms plugin 1.10.0+

## Installation

1. Upload `wpforms-file-rename-kidneyx.php` to `/wp-content/plugins/`
2. Activate in WordPress admin
3. Submit a test form with file upload

## Hooks Used

- `wpforms_process_before` - Captures team name from form data
- `wpforms_process_after` - Renames files after entry is fully saved

## Troubleshooting

If files aren't being renamed:

1. Check that the form has a field with ID 2 (team name)
2. Verify file upload field is properly configured
3. Check WordPress error logs for messages

## Author

Wembassy
