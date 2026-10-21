# Oli Download Plugin

WordPress plugin that adds a **Download** link on the Plugins and Themes screens so you can ZIP an installed plugin or theme (including child themes) and save it locally.

- Slug / text domain: `oli-download-plugin`
- Author: [Olivier Bigras](https://olivierbigras.com/)
- wordpress.org contributor: `bigrat95`
- License: GPLv2 or later
- Requires WordPress 6.5+, PHP 7.4+
- Repository: https://github.com/olivierbigras/Oli-Download-Plugin
- `Network: true` is intentional (network-only on Multisite)
- Download slugs must match `[A-Za-z0-9._-]`

See [`readme.txt`](readme.txt) for the WordPress.org listing (including **External services**) and [`CHANGELOG.md`](CHANGELOG.md) for releases.

## Development

```bash
composer install
composer phpcs
composer test
```

GitHub Actions runs `php -l`, PHPCS (WordPress standard), and the unit tests on PHP 7.4 / 8.2 / 8.3 / 8.4, plus Plugin Check.

The free [Download Plugin](https://wordpress.org/plugins/download-plugin/) listing is the UX reference (buttons, labels, downloadable items). This plugin is an independent reimplementation: rebranded, hardened, and without that plugin’s promotional UI. CSV exports require `manage_options` (`manage_network` on Multisite).
