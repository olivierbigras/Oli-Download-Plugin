# Changelog

All notable changes to Oli Download Plugin are documented here.

## 0.1.4 — 2026-10-21

### Documentation

- Added an **External services** section to `readme.txt`. The plugin does not call remote APIs, CDNs, fonts, maps, captchas, spreadsheets, or payment processors. Optional author (`olivierbigras.com`) and GitHub links are opened only when a person clicks them; GitHub terms and privacy URLs are listed.

### Changed

- The Add Plugins “Upload Multiple Plugins” spacing uses an enqueued stylesheet instead of an inline `style` attribute.

## 0.1.3 — 2026-10-20

### Security

- CSV exports (users, posts, pages, comments, terms — row and bulk) now require `manage_options` on single site and `manage_network` on Multisite. Editors can no longer download drafts, comment emails, or terms with a valid nonce.
- CSV cells that start with `=`, `+`, `-`, `@`, a tab, or CR are prefixed with an apostrophe to block spreadsheet formula injection.
- Comment CSV exports never include the author’s email or IP, including for administrators.

### Fixed

- Theme card Download buttons use `$theme.find('.theme-actions')` so they appear on WordPress 7.0, where the actions bar is nested. The overlay observer still pauses during inject.
- `fputcsv()` is called with an explicit `$escape` argument (empty string on PHP 7.4+).
- Remaining `open_basedir` warnings on escaping symlinks are swallowed by a local error handler that still forwards every other error. Paths outside `open_basedir` are skipped before `realpath()` / `is_file()` / `is_dir()`.

### Added

- After a multi-plugin upload, an **Activate all** form is offered (`activate_plugins` / `manage_network_plugins`, nonce `oli_dlp_activate_plugins`).

## 0.1.2 — 2026-10-20

### Fixed

- Theme details overlay no longer freezes `themes.php`. The MutationObserver is paused while injecting the button, the button is added only once, and download URLs are built with `esc_url_raw()` so `&amp;` cannot break the nonce.
- A Download button is prepended on each theme card (`button-primary`), matching the original plugin’s placement.
- Symbolic links pointing outside PHP `open_basedir` (for example `/etc/hostname`) are detected with `is_link()` first and skipped. `realpath()`, `file_exists()`, `is_file()`, and `is_dir()` are guarded so an `open_basedir` warning cannot become a fatal 500.
- Bulk Download with no plugins selected redirects to `plugins.php` with an admin notice instead of a 400 page.

### Added

- Custom ZIP exclusion patterns on the Download settings screen (one per line, sanitized), in addition to `oli_dlp_excluded_patterns`.
- Deactivation and uninstall now delete leftover `oli-dlp-*` files from `get_temp_dir()`.
- Upload Multiple Plugins from Add Plugins and the Download screen.
- CSV Download row and bulk actions for users, posts, pages, custom post types, comments, and terms (no password hashes).
- Top-level Download admin menu (`dashicons-media-archive`).

## 0.1.1 — 2026-10-20

### Security

- ZIP packing no longer follows symbolic links. Broken links and links that leave the plugin directory are skipped, and `addFile()` is wrapped so a bad path cannot fatal the request.
- The same containment rules apply to the PclZip fallback callback.
- Temporary archives are created outside the web root via `get_temp_dir()` / `wp_tempnam()` under a random `oli-dlp-` name. The pretty `slug-version.zip` name is used only in `Content-Disposition`. If the system temp directory is not writable, the plugin falls back to `uploads/oli-download-plugin-tmp/` (note: `.htaccess` is ignored by Nginx, including Kinsta).
- A `register_shutdown_function` plus `ignore_user_abort( true )` always deletes the temp file, including when the request exits through `wp_die()`.

### Changed

- Default exclusions now include `.env`, `.svn`, `.hg`, `.idea`, `.vscode`, `*.log`, and `*.sql`, with glob matching via `fnmatch()`.
- Large archives call `wp_raise_memory_limit( 'admin' )` and a 300-second `set_time_limit()` when permitted. `ZipArchive::close()` is checked and surfaces a translated error on failure.
- CI runs on PHP 7.4, 8.2, 8.3, and 8.4, plus a Plugin Check job.

### Documentation

- Documented that download slugs must match `[A-Za-z0-9._-]`, that `Network: true` is intentional, and that the settings page uses the same capabilities as plugin download actions (theme downloads use theme capabilities).

## 0.1.0 — 2026-10-20

### Added

- Download link on each Plugins screen row (single site and Multisite network admin).
- Bulk download of selected plugins as one ZIP.
- Theme download from Appearance → Themes (details overlay) and the network Themes table, including child themes.
- Optional must-use plugin and drop-in downloads (disabled by default).
- ZipArchive with PclZip fallback, `slug-version.zip` filenames.
- Temp files in `uploads/oli-download-plugin-tmp/` protected by `index.php` and `.htaccess`, deleted after streaming and by a daily cron.
- Filterable exclusions for `.git`, `node_modules`, and `.DS_Store`.
- French translation, `.pot` catalog, unit tests, and GitHub Actions CI.
