=== Simple Task Management Lite ===
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
Simple Task Management adds a modern task dashboard in WordPress admin.

Features:
- Create, edit, delete tasks
- Assign tasks to users
- Reward points for task completion
- Per-task reward points
- Reward settings and leaderboard UI
- Status workflow (To Do, In Progress, Done)
- Priority levels (Low, Medium, High)
- Due date support
- Improved dashboard UI (sidebar, cards, badges, filters)
- Role-based access control for task page
- Role-based access control for plugin settings page
- Visibility mode (all tasks or created/assigned tasks)

Lite excludes:
- Slack integration
- Task Data Tools (export/reset)
- Overdue approval workflow
- Custom dashboard title
- Guest login style controls
- Auto-refresh controls
- Tasks-per-page control
- Leaderboard reset-all action

== Installation ==
1. Upload the `simple-task-manager-lite` folder to `/wp-content/plugins/`.
2. Activate **Simple Task Management Lite** from the Plugins page.
3. Open **Task Manager** from the WordPress admin menu.
4. Configure access and reward settings from **Task Manager > Settings**.

== Changelog ==
= 1.0.0 =
* Version reset for WordPress.org submission baseline.

= 1.8.23 =
* Added new setting: `Employees See Own Tasks Only`.
* When enabled, non admin/editor users only see tasks assigned to them or created by them.

= 1.8.22 =
* Added Settings -> Task Data Tools:
* Reset all tasks action.
* Download task export in CSV, Excel (.xls), and PDF-ready HTML table format.

= 1.8.21 =
* Changed Slack assignee resolution to strict Slack-based mapping only.
* `@username` now resolves via Slack API and then maps through Slack User ID mapping.
* Removed WordPress username fallback from Slack assignment flow.

= 1.8.20 =
* Fixed Slack username assignment for handles with leading digits/symbols (example: `@61satyendra`).
* Added stronger normalized/partial matching fallback for assignee names.

= 1.8.19 =
* Improved Slack name matching tolerance for assignee input (normalizes spaces, dots, underscores and supports prefix/compact matches).
* Better support for assigning tasks using plain names like `@Satyendra`.

= 1.8.18 =
* Added Slack `@username` resolver via Slack API lookup (when bot token has required scope), then mapped to WordPress user via Slack User Map.
* Improves task assignment from Slack commands without typing Slack member IDs.

= 1.8.17 =
* Fixed Slack assignee parser to accept `@U...` format directly in slash commands.

= 1.8.16 =
* Improved Slack assignee resolution for commands (supports `@username`, login, display name, first name, email, and Slack mention/user ID mapping).
* Improved Slack command channel validation and error message clarity.
* Added explicit Slack response when WordPress task insert fails (shows DB error hint).

= 1.8.15 =
* Added new setting: `Logout Redirect URL` under Frontend Dashboard settings.
* Users can now be redirected to a custom URL after logout.

= 1.8.14 =
* Added modern right-side SVG icon styling on top metric cards (To Do, In Progress, Completed, My Reward Points).
* Applied the same icon-card treatment consistently across frontend and backend dashboards.

= 1.8.12 =
* Added status-specific action icons for task flow:
* `Start` icon for To Do tasks
* `Complete` icon for In Progress tasks
* `Reopen` icon for Done tasks

= 1.8.11 =
* Employee mobile filter layout refined: `All Status` and `Apply` stay aligned in one row.
* Mobile task card header/toggle alignment improved for cleaner inline presentation.

= 1.8.10 =
* Fixed employee frontend mobile filter alignment: Search full width, Status + Apply aligned and visible.
* Improved mobile card header alignment so task title and Show details are not visually broken.

= 1.8.9 =
* Fixed frontend mobile filter responsiveness for employees (Search, Status, Employee, Apply now stack correctly).
* Restored collapsible mobile task cards: details open only when tapping Show details.

= 1.8.8 =
* Strong mobile layout stabilization for frontend/backend dashboards (no clipping/overflow, full-width cards/filters/actions).
* Restored fully open readable card details behavior across roles on mobile.

= 1.8.7 =
* Restored and stabilized pre-regression dashboard layout behavior for frontend/backend responsive views.
* Fixed mobile filter/control stacking (Search, Status, Employee, Apply) and removed clipped task detail controls.
* UI-only update; no feature logic changed.

= 1.8.6 =
* Fixed frontend mobile layout regressions (filters, cards, overflow, and clipped detail controls).
* Improved employee frontend responsiveness and restored full task details visibility on mobile cards.

= 1.8.5 =
* Improved frontend employee dashboard responsiveness on mobile.
* Aligned frontend task visuals closer to backend (priority badges and cleaner card/action layout).

= 1.8.4 =
* Improved Slack DM delivery with fallback method when conversations.open is restricted.
* Updated Slack settings notes to include required scopes (`chat:write`, `im:write`).

= 1.8.3 =
* Added new Slack option to send direct DM notification to assigned user via bot chat.
* Assignment notifications can now be sent to channel and/or direct message.

= 1.8.2 =
* Fixed Slack assignee parsing for mention format like `<@U12345|name>` so slash-command assignment works reliably.

= 1.8.1 =
* Bundled Slack integration guide files with plugin package (PDF + text).
* Added guide links inside Settings -> Slack Integration.

= 1.8.0 =
* Added Slack integration settings (enable, signing secret, bot token, channel, toggles).
* Added Slack slash command endpoint for creating tasks from Slack channel assignment format.
* Added WordPress user to Slack user ID mapping in settings.
* Added automatic Slack notification when tasks are assigned from WordPress and Slack command.

= 1.7.3 =
* Added new "Tasks Per Page" setting (min 1, max 100).
* Frontend and backend pagination now use the configured page size.

= 1.7.2 =
* Added task pagination (10 per page) for frontend and backend task lists.
* Pagination works with filters and applies to all users.

= 1.7.1 =
* Added guest login card customization settings:
* Guest login title/subtitle text
* Login button text
* Guest note text
* Login button style controls (gradient start/end color, text color, border radius)

= 1.7.0 =
* Added configurable Frontend URL settings for multi-site/custom-domain usage:
* Tasks Home URL
* Login Page URL
* Login Redirect Parameter
* Updated login button, Tasks Home links, and forced redirect logic to use these settings.

= 1.6.9 =
* Added frontend footer credit: "Design By - Aman Dubey" with minimal opacity and darker hover effect.
* Refined frontend/backend task table border styling to prevent boxy nested-cell appearance.

= 1.6.8 =
* Added custom Dashboard Title setting (used on both frontend and backend dashboards).
* Improved mobile header alignment so Tasks Home, Refresh, and user points stay on one line.

= 1.6.7 =
* Added default-enabled "Force Tasks Page Only" setting to keep selected frontend roles on `/tasks` after login.
* Added dashboard lock for forced frontend roles to prevent access to wp-admin until permissions are changed.
* Updated plugin author branding to Aman Dubey and plugin URL to amandubey.com.

= 1.6.6 =
* Updated table action buttons to icon-only style with hover tooltips (View/Edit/Status/Delete) for a cleaner modern UI.

= 1.6.5 =
* Improved responsive top-bar alignment so Tasks Home, Refresh and user points align cleanly on mobile.
* Added more spacing under dashboard title and refined table visuals (shadow, rounded header, improved hover/row rhythm).
* Improved frontend filter row layout for cases with/without employee filter.

= 1.6.4 =
* Redesigned dashboard layout for better focus: Active Tasks table now full width.
* Moved Add New Task and Leaderboard into popup dialogs via quick-action buttons.
* Improved responsive behavior and kept edit flow accessible by auto-opening task popup when editing.

= 1.6.3 =
* Improved frontend/backend filter row alignment so Search, Status, Employee and Apply stay on one line on desktop.
* Refreshed task table UI with cleaner header, row spacing, zebra contrast and hover states.

= 1.6.2 =
* Added frontend employee filter support for admin/editor when enabled in settings.
* Changed task filter action button text from "Search" to "Apply".
* Truncated table description text for compact view while keeping full description in View popup.

= 1.6.1 =
* Added admin employee-name task filter in Active Tasks.
* Added setting toggle to enable/disable the admin employee task filter.

= 1.6.0 =
* Fixed double-login flow by respecting requested login redirect target.
* Forced secure frontend login URL (`https`) for task dashboard sign-in.
* Added no-cache headers/flag on frontend dashboard shortcode to avoid stale guest cache after login.

= 1.5.9 =
* Added mobile task list optimizations: collapsible task cards and "Load More Tasks" pagination (10 at a time).

= 1.5.8 =
* Added advanced auto-refresh conditions in Settings:
* Enable per frontend/backend dashboard.
* Restrict by user type (all, admin/editor only, non admin/editor only).
* Option to refresh only when browser tab is active.

= 1.5.7 =
* Improved frontend mobile task card metadata layout with boxed tiles for better readability.

= 1.5.6 =
* Improved frontend mobile task card UI to prevent action button overlap.
* Updated frontend action icons to Dashicons (same icon set as backend buttons).

= 1.5.5 =
* Fixed frontend Start/Complete task action permissions for allowed dashboard users.
* Updated frontend View popup to show only task title and "Task To Do" content.

= 1.5.4 =
* Fixed auto-refresh reliability on frontend and backend dashboards (interval refresh with compatibility fallback).

= 1.5.3 =
* Restricted task creation to admin/editor only (users can no longer add tasks).
* Users now see only "Assigned By" in Active Tasks; "Assigned To" remains for admin/editor.
* Renamed "Open Tasks URL" button text to "Tasks Home".

= 1.5.2 =
* Added leaderboard management tools in Settings: reset all points, remove user from leaderboard, and manually update user points.

= 1.5.1 =
* Added optional auto-refresh settings (enable + interval seconds) for frontend and backend dashboards.

= 1.5.0 =
- Fixed frontend status update reliability.
- Added/updated overdue reason submission and review workflow behavior.
- Improved frontend task actions and popup behavior parity.
- Renamed display labels to `Assigned By` where requested.

= 1.3.0 =
- Added reward points system for task completion.
- Added per-task reward points field.
- Added rewards settings (enable/disable, points by priority, award mode).
- Added leaderboard UI and current user points display.
- Added rewards log table and user points meta tracking.
- Refreshed task dashboard UI inspired by modern layout.

= 1.2.0 =
- Added `Assign To` user field for tasks.
- Added assigned user column in task list.
- Improved task dashboard UI and task status actions.
- Added status filtering.
- Updated visibility mode to include assigned tasks.
- Added DB migration for `assigned_to` column.

= 1.1.0 =
- Added role-based task access settings.
- Added role-based settings access controls.
- Added task visibility and default task behavior settings.
- Added plugin upgrade routine and option cleanup on uninstall.

= 1.0.0 =
- Initial release.
