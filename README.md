# IEEE RAS Website Feedback

A visual feedback tool for WordPress that allows selected users to click any element on a page and submit feedback with comments. Features browser-inspector-like element selection, threaded replies, and status management.

## Features

### Visual Feedback Tool
- **Element Selection** - Hover over any page element to highlight it (like browser DevTools inspector)
- **Click to Comment** - Click an element to open a feedback form
- **Position Tracking** - Records click position, element selector, and viewport dimensions
- **Page Context** - Automatically tracks which page the feedback is about

### User Access Control
- **Selective Access** - Enable specific WordPress users to use the feedback tool
- **Guest Access** - Share a special URL parameter for external reviewers
- **Token-Based Auth** - Regeneratable tokens for guest access security

### Email Notifications
- **Live Emails** - Instant notifications for new feedback, replies, and status changes
- **Daily Digest** - Consolidated email sent at 3:00 PM EST
- **Per-User Preferences** - Each user can choose their notification mode

### Admin Dashboard
- **Feedback Log** - View all feedback with status indicators, filters, and search
- **Reply System** - Threaded conversation on each feedback item
- **Status Workflow** - Mark feedback as Unresolved → Pending → Resolved
- **User Management** - Enable/disable users and configure notification preferences

### Frontend Features
- **Floating Feedback Button** - Always accessible feedback trigger
- **Feedback Counter Badge** - Shows unresolved feedback count per page
- **Feedback Drawer** - View all feedback for current page without leaving

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Installation

1. Download the plugin ZIP from GitHub releases
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Go to Feedback menu in admin to configure user access

## Configuration

### Enable Users
1. Navigate to Feedback → User Access tab
2. Search for WordPress users
3. Click to enable them for feedback access
4. Set notification preferences for each user

### Guest Access
1. Navigate to Feedback → Settings tab
2. Enable "Guest Access"
3. Share the generated URL with external reviewers
4. URL format: `yoursite.com/?ras_wf_guest=TOKEN`

### Notification Preferences
For each enabled user, choose:
- **Live emails** - Immediate notifications
- **Daily digest** - Summary email at 3:00 PM EST
- **Off** - No email notifications

## Usage

### For Feedback Submitters
1. Visit any page on the site (must be logged in or have guest token)
2. Click the floating feedback button (bottom-right)
3. Hover over elements to see highlighting
4. Click an element to select it
5. Enter your feedback comment
6. Submit!

### For Administrators
1. Go to Feedback menu in admin
2. Review feedback items in the Feedback Log
3. Change status: Unresolved → Pending → Resolved
4. Add replies to communicate with feedback submitters
5. Delete feedback when no longer needed

## Hooks & Filters

### Actions
```php
// Fired when new feedback is submitted
do_action('ras_wf_feedback_submitted', $feedback_id, $feedback_data);

// Fired when feedback status changes
do_action('ras_wf_status_changed', $feedback_id, $old_status, $new_status);

// Fired when reply is added
do_action('ras_wf_reply_added', $feedback_id, $reply_id, $reply_data);
```

### Filters
```php
// Modify enabled user IDs
$users = apply_filters('ras_wf_enabled_users', $user_ids);

// Modify notification recipients
$recipients = apply_filters('ras_wf_notification_recipients', $user_ids, $notification_type);

// Modify email content
$content = apply_filters('ras_wf_email_content', $content, $type, $feedback_id);
```

## Database Tables

The plugin creates a custom table for replies:
- `{prefix}_ras_wf_replies` - Stores threaded replies on feedback items
- `{prefix}_ras_wf_email_queue` - Stores queued notifications for daily digest

## Updates

This plugin supports automatic updates via [Git Updater](https://git-updater.com/). Updates are fetched from GitHub releases.

## Changelog

### 1.1.0
- Added email notification system
- Added per-user notification preferences (live/digest/off)
- Added daily digest scheduling at 3:00 PM EST
- Added notification triggers for new feedback, replies, and status changes
- Added Git Updater support for automatic updates

### 1.0.0
- Initial release
- Visual element selection tool
- Feedback submission with comments
- Admin dashboard with status workflow
- Reply system for threaded conversations
- User access control with guest URL support
- Frontend drawer for page-specific feedback

## License

GPL v2 or later

## Credits

Developed for IEEE Robotics and Automation Society (RAS) by TAROS Web Services.
