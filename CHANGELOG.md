# Changelog

All notable changes to Oli Duplicate Post will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.5] - 2026-10-26

### Changed
- `pre_get_posts` hides merged copies on front-end, REST, and sitemap queries only (not wp-admin lists)

### Fixed
- Publishing a copy that is already merged and still has `_oli_dp_pending_delete` is refused (kept as draft), including REST (`rest_pre_insert_*`, `wp_insert_post_data`, `transition_post_status`)

### Performance
- `get_pending_delete_ids()` is cached in the `oli_dp_pending_delete_ids` transient and invalidated when a copy is marked pending-delete or deleted

## [1.0.4] - 2026-10-26

### Added
- Persistent Gutenberg rewrite notice (`core/notices`), pre-publish overwrite warning, and Republish label
- Posts-list state `Rewrite of: <title>` on the copy and `Rewrite in progress (draft #ID)` on the original
- Opportunistic cleanup of merged copies older than 5 minutes on `admin_init`

### Changed
- Merged rewrite copies are set to draft immediately (no rewrite-hook re-entry) and excluded from queries and sitemaps
- Row actions: Duplicate now, Duplicate and edit, Rewrite and republish
- Settings Title Prefix/Suffix and exclusion fields have `<label for>`
- Admin bar items have explanatory `title` tooltips

### Fixed
- Block editor no longer looks like a second unpublished draft of the original

## [1.0.3] - 2026-10-26

### Fixed
- Gutenberg Rewrite & Republish no longer 500s: the copy stays until the meta-box-loader request, with a 60-second cron fallback
- Cancelling a schedule and publishing now clears `_oli_dp_scheduled_gmt` so the copy merges using the current `post_date_gmt`

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

[1.0.5]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.5
[1.0.4]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.4
[1.0.3]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.3
[1.0.2]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.2
[1.0.1]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.1
[1.0.0]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/1.0.0
[0.1.2]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.2
[0.1.1]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.1
[0.1.0]: https://github.com/olivierbigras/Oli-duplicate-post/releases/tag/0.1.0
