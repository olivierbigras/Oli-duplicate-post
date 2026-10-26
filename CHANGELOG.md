# Changelog

All notable changes to Oli Duplicate Post will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-10-26

### Changed
- Rewrite & Republish merges on `wp_after_insert_post` and deletes the copy after REST, editor, and cron finish
- Copied variations keep the original variation status
- Uninstall leaves `_dp_original` meta (shared with Yoast Duplicate Post)

### Fixed
- Republish no longer deletes the copy mid-save (REST `id` stays valid; 0 notices on PHP 8.4)
- WooCommerce lookup rows are written with `$product->save()` / the variation data store
- Scheduled republish compares UTC GMT timestamps so a +60s REST schedule does not merge immediately

## [1.0.1] - 2026-10-26

### Added
- `_dp_original` meta on every copy, with an Original list-table column and edit-screen link
- Bulk Rewrite & Republish alongside Clone and New Draft
- Tests for published product variations, title prefix/suffix spaces, and real attachment copies

### Changed
- Default enabled post types are `post` and `page` only (products stay opt-in)
- Rewrite & Republish permanently deletes the working copy after merge
- Title prefix and suffix keep leading and trailing spaces when settings are saved

### Fixed
- Duplicated product variations are published, the parent is synced, and `wc_product_meta_lookup` is updated
- Attachment copies are real `attachment` posts that share the original file and metadata
- Forged or invalid duplicate action URLs return HTTP 403
- Admin bar "Duplicate This" no longer appears on list screens for a random post

## [1.0.0] - 2026-10-26

### Added
- Featured image as an explicit copy setting
- `Oli_DP_Settings::get_settings()` so new keys merge with saved options
- WooCommerce product handling: unique SKUs, skip sales/rating meta, copy variations
- Developer filters and actions for titles, meta, taxonomies, permissions, and rewrite merge
- Comparison-screen nonce, admin-post duplicate URLs, uninstall cleanup
- PHPUnit unit tests and Rewrite & Republish integration tests
- `wp-env.json` for a local WordPress environment
- Official Plugin Check GitHub Action
- GPLv2 or later `LICENSE` file
- Installable ZIP build script under `artifacts/`

### Changed
- Clone returns to the list table; New Draft and Rewrite & Republish open the editor
- Rewrite copies keep the original title (no prefix/suffix)
- Scheduled republish no longer merges until the copy actually publishes
- Original permalink and publish date are preserved on merge
- Admin assets and notices are limited to plugin-related screens
- PHPCS standard is WordPress-Extra
- Author URI is https://github.com/olivierbigras; Plugin URI omitted
- Contact email documented as oli@olivierbigras.com

### Fixed
- Meta is copied even when content copying is disabled
- Deactivation no longer deletes in-progress rewrite relationships
- Product variations are not double-copied when children are also enabled
- `load_plugin_textdomain()` removed for WordPress.org translation loading

### Security
- Capability and nonce checks on every duplicate, bulk, compare, and merge action
- No external HTTP calls or tracking

## [0.1.2] - 2026-10-21

### Changed
- Extracted the block editor sidebar/Options-menu script to `assets/js/block-editor.js`
- Extracted the Quick Edit "Copy to New Draft" script to `assets/js/quick-edit.js`
- Moved admin and comparison styles to `assets/css/admin.css`

### Fixed
- Escape comparison featured-image markup with `wp_kses_post`
- Require `current_user_can` on comparison and duplicate admin actions

## [0.1.1] - 2026-10-19

### Added
- Side-by-side comparison screen for Rewrite & Republish
- Block editor Options menu items
- Quick edit "Copy to new draft" button
- Scheduled republish UI

## [0.1.0] - 2026-10-19

### Added
- Initial release of Oli Duplicate Post

[1.0.2]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.2
[1.0.1]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.1
[1.0.0]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.0
[0.1.2]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.2
[0.1.1]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.1
[0.1.0]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.0
