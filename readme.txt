=== Neura Task Manager ===
Contributors: amandubey
Tags: tasks, task manager, admin, productivity, rewards
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Task management for WordPress admin with assignments, statuses, and reward points.

== Description ==
Neura Task Manager provides a task dashboard in WordPress admin.

Features:
- Create, edit, delete tasks
- Assign tasks to users
- Task status workflow (To Do, In Progress, Done)
- Priority and due date support
- Reward points and leaderboard
- Role-based access controls
- Frontend dashboard shortcode

== External services ==
This plugin can connect to Slack APIs for task assignment integrations when Slack settings are enabled by the site administrator.

Slack API endpoints used:
- https://slack.com/api/users.list
- https://slack.com/api/chat.postMessage
- https://slack.com/api/conversations.open

Data sent to Slack may include task title, assignee mapping IDs, due date, priority, and reward points, only when Slack-related features are used.

Slack service terms:
- https://slack.com/terms-of-service
Slack privacy policy:
- https://slack.com/privacy-policy

== Installation ==
1. Upload the `simple-task-manager-lite` folder to `/wp-content/plugins/`.
2. Activate **Neura Task Manager** from the Plugins page.
3. Open **Task Manager** from the WordPress admin menu.

== Changelog ==
= 1.0.0 =
* Initial public release for WordPress.org.
* Renamed plugin branding to Neura Task Manager.
