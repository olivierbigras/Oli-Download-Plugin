=== Oli Download Plugin ===
Contributors: bigrat95
Donate link: https://olivierbigras.com/
Tags: download, plugins, themes, zip, backup
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download installed plugins and themes as ZIP files, export users/posts/comments as CSV, and upload multiple plugins from the WordPress admin.

== Description ==

Oli Download Plugin adds a **Download** link on each row of the Plugins screen. Clicking it builds a ZIP of that installed plugin folder (or single-file plugin) and streams it to your browser. The same workflow is available for themes on Appearance → Themes (including the theme details overlay) and on the network Themes table.

The plugin is written independently. It is not a fork of "Download Plugin" and does not copy that plugin's code.

`Network: true` is intentional. On Multisite the plugin is network-only: activate it from Network Admin so downloads stay on the network Plugins / Themes screens with network capabilities.

= What you can download =

* Installed plugins (directory or single-file) as ZIP, including a bulk Download action
* Installed themes, including child themes (the child folder only), from each theme card and the details overlay
* Several plugin ZIPs uploaded and installed at once
* Must-use plugins and drop-ins, when you enable those options
* Users, posts, pages, custom post types, comments, and terms as CSV (row and bulk Download; administrators only)
* The same ZIP actions from Multisite network admin

= What is never included =

* `wp-content/uploads` and other media files
* The WordPress database as a dump
* User password hashes
* Comment author emails and IP addresses (CSV exports)
* Arbitrary paths outside the validated plugin / theme / mu-plugin / drop-in lists
* Symbolic links (including broken links and links that point outside the source or `open_basedir`)

= Security =

* Plugin downloads require `activate_plugins` on single-site installs, and `manage_network_plugins` on Multisite
* Theme downloads require `switch_themes` or `edit_themes` on single-site installs, and `manage_network_themes` on Multisite
* The settings page uses the same capabilities as the plugin download actions (`activate_plugins` / `manage_network_plugins`). Theme downloads use the theme capabilities above; a user who can only switch themes cannot change MU-plugin or drop-in settings
* Every download link carries a nonce
* Slugs must match `[A-Za-z0-9._-]` and are then validated against `get_plugins()` / `wp_get_themes()` (and scanned MU / drop-in names); path traversal is rejected
* CSV exports require `manage_options` (`manage_network` on Multisite). Comment CSVs omit author email and IP even for administrators
* There are no public front-end endpoints, no external HTTP calls, and no tracking
* Temporary ZIPs are written with `get_temp_dir()` / `wp_tempnam()` under a random `oli-dlp-` name (the pretty `slug-version.zip` name is used only in the download header). If the system temp directory is not writable, files fall back to `uploads/oli-download-plugin-tmp/`
* `.htaccess` in the uploads fallback folder is ignored by Nginx (including Kinsta). Prefer a writable system temp directory so archives never land under the web root
* Temp files are deleted after streaming, on `wp_die()` failure paths, by a shutdown function (`ignore_user_abort( true )`), and by a daily cron sweep

= ZIP details =

* ZipArchive is used when available, with PclZip as a fallback
* Default exclusions: `.git`, `node_modules`, `.DS_Store`, `.env`, `.svn`, `.hg`, `.idea`, `.vscode`, `*.log`, `*.sql` (plus optional settings lines; filterable with `oli_dlp_excluded_patterns`; globs use `fnmatch`)
* Filenames use `slug-version.zip`
* Large plugins raise the admin memory limit and allow up to 300 seconds when `set_time_limit()` is permitted

= Filters =

`oli_dlp_excluded_patterns` — array of path-segment names or glob patterns to omit from generated archives. Custom lines from the Download screen are merged in first.

== External services ==

This plugin does not contact external services. ZIP archives and CSV exports are built on your WordPress server and streamed to the signed-in administrator who requested them. There is no telemetry, no license check, no CDN, and no remote font, map, captcha, spreadsheet, or payment integration.

The plugin never sends plugin files, theme files, CSV rows, options, emails, IP addresses, or other site data to a remote host. In particular it does not call olivierbigras.com, PayPal, Google (including Fonts and reCAPTCHA), Cloudflare Turnstile, Carto, Leaflet, Google Sheets, or Adobe Typekit.

Temporary ZIP files stay on the server (`get_temp_dir()` or `uploads/oli-download-plugin-tmp/`) until they are downloaded and deleted.

= Optional links a person may open =

These are ordinary hyperlinks. The plugin does not request them in the background. A click is initiated by the visitor's browser and sends only what the browser sends for any page visit (typically the destination URL and standard request headers). No WordPress site data is appended.

* Author site / Donate link: [https://olivierbigras.com/](https://olivierbigras.com/) — public author page. The destination does not publish a separate Terms of Use or Privacy Policy page; the homepage is the landing page.
* Source repository (Plugins screen meta link): [https://github.com/olivierbigras/Oli-Download-Plugin](https://github.com/olivierbigras/Oli-Download-Plugin) — [GitHub Terms of Service](https://docs.github.com/site-policy/github-terms/github-terms-of-service) — [GitHub Privacy Statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement)

== Installation ==

1. Upload the `oli-download-plugin` folder to `/wp-content/plugins/`, or install the ZIP from Plugins → Add New → Upload.
2. Activate the plugin (Network Activate on Multisite; `Network: true` is intentional).
3. Open Plugins and click **Download** under a plugin name.
4. Optional: open the **Download** menu to allow MU-plugin and drop-in downloads, edit ZIP exclusions, or upload several plugin ZIPs.

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

= Does this plugin call external services? =

No. All ZIP and CSV work runs on your server. See **External services** for the optional author and GitHub links (opened only if someone clicks them).

== Changelog ==

= 0.1.4 =
* Document the External services policy: no remote APIs, CDNs, or telemetry; optional author/GitHub links are user-initiated only.
* Move the Add Plugins “Upload Multiple Plugins” spacing into an enqueued stylesheet (no inline CSS).

= 0.1.3 =
* CSV exports require manage_options / manage_network; formula injection is neutralized; comment CSV omits author email and IP.
* Theme card buttons work on WordPress 7.0; fputcsv() passes $escape; open_basedir warnings stay silent.
* After a multi-plugin upload, offer Activate all (activate_plugins + nonce).

= 0.1.2 =
* Fix theme overlay freeze and encoded nonce URLs.
* Ignore open_basedir-escaping symlinks without fatals.
* Empty bulk Download redirects with a notice.
* Custom exclusions, multi-plugin upload, CSV downloads, Download menu.

= 0.1.1 =
* Skip symbolic links (broken or escaping the source) when building ZIPs; catch addFile failures.
* Create temp ZIPs outside the web root with a random name; delete them on shutdown, abort, and wp_die.
* Raise memory/time limits for large plugins and check ZipArchive::close().
* Default exclusions: .env, .svn, .hg, .idea, .vscode, *.log, *.sql (glob-aware).
* Document slug format, Network: true, capabilities, and Nginx temp-file note.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.4 =
Documents that the plugin does not contact external services. No download behavior change.

= 0.1.3 =
CSV exports are limited to administrators, theme buttons work on WP 7.0, and bulk upload can activate plugins.

= 0.1.2 =
Fixes the Themes screen freeze and open_basedir fatals; adds CSV downloads and multi-plugin upload.

= 0.1.1 =
Hardens ZIP packing against symbolic links and writes temp archives outside the web root.

= 0.1.0 =
Initial release.

== Screenshots ==

1. A Download link on each plugin row, plus a bulk Download action.
