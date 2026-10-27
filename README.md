# Oli Duplicate Post

A plugin to clone posts, pages, and custom post types with Clone, New Draft, and Rewrite & Republish.

**Author:** Olivier Bigras
**Author URI:** https://github.com/olivierbigras
**Contact:** oli@olivierbigras.com

## Features

- **Clone** — copy an item and stay on the list table
- **New Draft** — open a draft copy in the editor
- **Rewrite & Republish** — edit a copy of a published item and merge it back on publish or at a scheduled time
- Row actions, bulk actions, admin bar, classic editor meta box, and block editor panel
- Settings for title, date, status, slug, excerpt, content, featured image, template, format, author, password, attachments, children, comments, and menu order
- Title prefix/suffix, excluded meta keys, excluded taxonomies, roles, and post types
- WooCommerce products: unique SKUs, no sales/rating stats, copied variations
- Template tag `oli_dp_duplicate_post()` and developer hooks

## Requirements

- WordPress 6.5 or higher
- PHP 7.4 or higher

## Installation

1. Upload `oli-duplicate-post` to `/wp-content/plugins/`
2. Activate the plugin
3. Open Settings → Duplicate Post

## Testing

Unit tests (no WordPress install):

```bash
composer install
composer test
```

WordPress integration tests (requires the WP test suite):

```bash
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
composer test:all
```

Local site with wp-env:

```bash
npx @wordpress/env start
```

PHPCS (WordPress-Extra) and smoke tests:

```bash
composer phpcs
composer smoke
```

Build an installable ZIP:

```bash
bash bin/build-zip.sh
```

The ZIP is written to `artifacts/oli-duplicate-post.zip`.

## License

GPLv2 or later. See [LICENSE](LICENSE).

## Support

Email oli@olivierbigras.com or open an issue on GitHub.
