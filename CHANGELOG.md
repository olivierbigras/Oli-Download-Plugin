# Changelog

All notable changes to Oli Download Plugin are documented here.

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
