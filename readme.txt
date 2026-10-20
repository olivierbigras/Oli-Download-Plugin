=== Oli Download Plugin ===
Contributors: bigrat95
Donate link: https://olivierbigras.com/
Tags: download, plugins, themes, zip, backup
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download installed plugins, themes, and child themes as ZIP files from the WordPress admin.

== Description ==

Oli Download Plugin adds a **Download** link on each row of the Plugins screen. Clicking it builds a ZIP of that installed plugin folder (or single-file plugin) and streams it to your browser. The same workflow is available for themes on Appearance → Themes (including the theme details overlay) and on the network Themes table.

The plugin is written independently. It is not a fork of "Download Plugin" and does not copy that plugin's code.

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

= Security =

* Plugin downloads require `activate_plugins` on single-site installs, and `manage_network_plugins` on Multisite
* Theme downloads require `switch_themes` or `edit_themes` on single-site installs, and `manage_network_themes` on Multisite
* Every download link carries a nonce
* Slugs are validated against `get_plugins()` / `wp_get_themes()` (and scanned MU / drop-in names); path traversal is rejected
* There are no public front-end endpoints, no external HTTP calls, and no tracking
* Temporary ZIPs live in `uploads/oli-download-plugin-tmp/`, protected with `index.php` and `.htaccess`, deleted immediately after streaming, and swept by a daily cron

= ZIP details =

* ZipArchive is used when available, with PclZip as a fallback
* Default exclusions: `.git`, `node_modules`, `.DS_Store` (filterable with `oli_dlp_excluded_patterns`)
* Filenames use `slug-version.zip`

= Filters =

`oli_dlp_excluded_patterns` — array of path-segment names to omit from generated archives.

== Installation ==

1. Upload the `oli-download-plugin` folder to `/wp-content/plugins/`, or install the ZIP from Plugins → Add New → Upload.
2. Activate the plugin (Network Activate on Multisite).
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

== Changelog ==

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.

== Screenshots ==

1. A Download link on each plugin row, plus a bulk Download action.
