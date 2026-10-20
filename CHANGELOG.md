# Changelog

All notable changes to Oli Download Plugin are documented here.

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
