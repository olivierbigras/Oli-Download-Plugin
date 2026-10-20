=== Oli Download Plugin ===
Contributors: bigrat95
Donate link: https://olivierbigras.com/
Tags: download, plugins, themes, zip, backup
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download installed plugins, themes, and child themes as ZIP files from the WordPress admin.

== Description ==

Oli Download Plugin adds a **Download** link on each row of the Plugins screen. Clicking it builds a ZIP of that installed plugin folder (or single-file plugin) and streams it to your browser. The same workflow is available for themes on Appearance → Themes (including the theme details overlay) and on the network Themes table.

The plugin is written independently. It is not a fork of "Download Plugin" and does not copy that plugin's code.

`Network: true` is intentional. On Multisite the plugin is network-only: activate it from Network Admin so downloads stay on the network Plugins / Themes screens with network capabilities.

= What you can download =

* Installed plugins (directory or single-file)
* Installed themes, including child themes (the child folder only)
* Several plugins at once as one ZIP via the Plugins screen bulk action
* Must-use plugins and drop-ins, when you enable those options
* The same actions from Multisite network admin

= What is never included =

* `wp-content/uploads` and other media
* The WordPress database
* Posts, users, comments, or custom post types
* Arbitrary paths outside the validated plugin / theme / mu-plugin / drop-in lists
* Symbolic links (including broken links and links that point outside the source)

= Security =

* Plugin downloads require `activate_plugins` on single-site installs, and `manage_network_plugins` on Multisite
* Theme downloads require `switch_themes` or `edit_themes` on single-site installs, and `manage_network_themes` on Multisite
* The settings page uses the same capabilities as the plugin download actions (`activate_plugins` / `manage_network_plugins`). Theme downloads use the theme capabilities above; a user who can only switch themes cannot change MU-plugin or drop-in settings
* Every download link carries a nonce
* Slugs must match `[A-Za-z0-9._-]` and are then validated against `get_plugins()` / `wp_get_themes()` (and scanned MU / drop-in names); path traversal is rejected
* There are no public front-end endpoints, no external HTTP calls, and no tracking
* Temporary ZIPs are written with `get_temp_dir()` / `wp_tempnam()` under a random `oli-dlp-` name (the pretty `slug-version.zip` name is used only in the download header). If the system temp directory is not writable, files fall back to `uploads/oli-download-plugin-tmp/`
* `.htaccess` in the uploads fallback folder is ignored by Nginx (including Kinsta). Prefer a writable system temp directory so archives never land under the web root
* Temp files are deleted after streaming, on `wp_die()` failure paths, by a shutdown function (`ignore_user_abort( true )`), and by a daily cron sweep

= ZIP details =

* ZipArchive is used when available, with PclZip as a fallback
* Default exclusions: `.git`, `node_modules`, `.DS_Store`, `.env`, `.svn`, `.hg`, `.idea`, `.vscode`, `*.log`, `*.sql` (filterable with `oli_dlp_excluded_patterns`; globs use `fnmatch`)
* Filenames use `slug-version.zip`
* Large plugins raise the admin memory limit and allow up to 300 seconds when `set_time_limit()` is permitted

= Filters =

`oli_dlp_excluded_patterns` — array of path-segment names or glob patterns to omit from generated archives.

== Installation ==

1. Upload the `oli-download-plugin` folder to `/wp-content/plugins/`, or install the ZIP from Plugins → Add New → Upload.
2. Activate the plugin (Network Activate on Multisite; `Network: true` is intentional).
3. Open Plugins and click **Download** under a plugin name.
4. Optional: Settings → Oli Download Plugin (or Network Settings on Multisite) to allow MU-plugin and drop-in downloads.

== Frequently Asked Questions ==

= Can I download the uploads folder? =

No. This plugin only archives plugins, themes, and optionally must-use plugins and drop-ins.

= Do child themes include the parent? =

No. A child theme ZIP contains only the child folder. Download the parent theme separately if you need it.

= Will the ZIP reinstall in WordPress? =

Yes. Directory plugins and themes are stored with their folder as the ZIP root (`slug/…`). Single-file plugins are stored as the PHP file at the ZIP root.

= Who can download on Multisite? =

Plugin ZIPs require `manage_network_plugins`. Theme ZIPs require `manage_network_themes`. Use Network Admin → Plugins / Themes.

= Why is Network: true set? =

So the plugin is network-only on Multisite. Downloads and settings belong in Network Admin, not on individual sites.

= Which capability does the settings page use? =

The same as plugin downloads: `activate_plugins` on single site, `manage_network_plugins` on Multisite. Theme downloads use `switch_themes` / `edit_themes` (or `manage_network_themes`). Settings only control MU-plugins and drop-ins, so they stay aligned with the plugin actions.

= What slug format is accepted? =

Download slugs must match `[A-Za-z0-9._-]`. Paths, `..`, and other characters are rejected before the slug is matched against installed plugins or themes.

= Are temporary ZIPs public on Nginx / Kinsta? =

`.htaccess` deny rules do not apply on Nginx. The plugin writes archives to `get_temp_dir()` with a random name whenever that directory is writable. The uploads fallback is a last resort; keep PHP's temp directory writable so those files stay outside the web root.

== Changelog ==

= 0.1.1 =
* Skip symbolic links (broken or escaping the source) when building ZIPs; catch addFile failures.
* Create temp ZIPs outside the web root with a random name; delete them on shutdown, abort, and wp_die.
* Raise memory/time limits for large plugins and check ZipArchive::close().
* Default exclusions: .env, .svn, .hg, .idea, .vscode, *.log, *.sql (glob-aware).
* Document slug format, Network: true, capabilities, and Nginx temp-file note.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.1 =
Hardens ZIP packing against symbolic links and writes temp archives outside the web root.

= 0.1.0 =
Initial release.

== Screenshots ==

1. A Download link on each plugin row, plus a bulk Download action.
