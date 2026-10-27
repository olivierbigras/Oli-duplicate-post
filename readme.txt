=== Oli Duplicate Post ===
Contributors: bigrat95
Tags: duplicate, clone, copy, post, page
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Clone posts, pages, and custom post types with Clone, New Draft, and Rewrite & Republish.

== Description ==

Oli Duplicate Post duplicates posts, pages, and custom post types. Use Clone for a same-list copy, New Draft to open an editable draft, or Rewrite & Republish to edit a copy and merge it back into the live original.

Author: Olivier Bigras
Author URI: https://github.com/olivierbigras
Contact: oli@olivierbigras.com

= Features =

* **Three duplication modes**
  * **Clone** — create a copy and stay on the list table
  * **New Draft** — create a draft and open it for editing
  * **Rewrite & Republish** — edit a copy of a published post and merge it back on publish, including scheduled republish

* **Access points**
  * Row actions in the post list
  * Bulk Clone, New Draft, and Rewrite & Republish
  * Admin bar (admin and front-end singular views)
  * Classic editor meta box
  * Block editor sidebar panel and Options menu

* **What you can copy**
  * Title (with prefix and suffix), date, status, slug, excerpt, content
  * Featured image, template, format, author, password, menu order
  * Attachments, child posts, and comments
  * Post meta and taxonomies, with exclusion lists

* **Permissions**
  * Enable specific post types
  * Restrict duplication to selected user roles

* **WooCommerce**
  * Products can be enabled like any other post type
  * Sales and rating stats are not copied
  * SKUs are made unique
  * Variations are copied as published children, then the parent is synced

* **Developer API**
  * Template tag: `oli_dp_duplicate_post()`
  * Actions and filters for titles, meta, taxonomies, permissions, and merge data

= Rewrite & Republish =

1. Click **Rewrite & Republish** on a published post.
2. Edit the working copy. The original stays live.
3. Publish the copy to replace the original immediately, or schedule it. The original permalink and publish date are kept.
4. The working copy is then removed.

= Template tag =

`
<?php
$new_post_id = oli_dp_duplicate_post( $post_id, 'clone' );
?>
`

Parameters:

* `$post_id` (int) — post to duplicate
* `$mode` (string) — `clone`, `new_draft`, or `rewrite_republish`
* `$args` (array) — optional settings override

= Hooks =

**Actions**

* `oli_dp_before_duplicate`
* `oli_dp_post_duplicated`
* `oli_dp_after_copy_meta`
* `oli_dp_after_copy_taxonomies`
* `oli_dp_before_rewrite_merge`
* `oli_dp_rewrite_republish_merged`

**Filters**

* `oli_dp_pre_duplicate_post_data`
* `oli_dp_duplicate_title`
* `oli_dp_excluded_meta_keys`
* `oli_dp_excluded_taxonomies`
* `oli_dp_copied_meta_value`
* `oli_dp_can_duplicate`
* `oli_dp_enabled_post_types`
* `oli_dp_allowed_roles`
* `oli_dp_rewrite_merge_post_data`
* `oli_dp_woocommerce_unique_sku`

== Installation ==

1. Upload the `oli-duplicate-post` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins screen.
3. Open Settings → Duplicate Post to choose what to copy, which roles are allowed, and which post types are enabled.
4. Duplicate items from the list table, admin bar, classic editor, or block editor.

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes. Enable any public post type, including products, on the settings screen.

= Does it work with the block editor? =

Yes. The plugin adds a sidebar panel and Options menu items in the block editor, and a meta box in the classic editor.

= Can I exclude meta fields or taxonomies? =

Yes. Settings accept comma-separated meta keys (wildcards such as `_private_*` are supported) and taxonomy names.

= What is the difference between Clone and New Draft? =

Clone creates a copy and returns you to the list table. New Draft always creates a draft and opens it in the editor.

= How does Rewrite & Republish scheduling work? =

Scheduling the working copy leaves the original live. The merge runs only when that copy actually publishes.

= Can I restrict who can duplicate? =

Yes. Choose the allowed roles and post types on the settings screen.

= How do I get support? =

Email oli@olivierbigras.com or open an issue at https://github.com/olivierbigras/Oli-duplicate-post

== Screenshots ==

1. Settings screen, part 1: choose which elements are copied when duplicating a post.
2. Posts list with Duplicate now, Duplicate and edit, and Rewrite and republish row actions.
3. Compare Changes screen showing the rewritten version side by side with the original.
4. Settings screen, part 2: title prefix and suffix, excluded meta and taxonomies, post types, roles and UI locations.

== External services ==

This plugin does not call any external service. Clone, New Draft, and Rewrite & Republish run entirely on your site. No visitor or editor data is sent to a third party.

== Changelog ==

= 1.0.9 - 2026-10-26 =
* Uninstall now clears the scheduled Rewrite & Republish cleanup events.

= 1.0.8 - 2026-10-26 =
* Regenerated translation template (POT).

= 1.0.7 =
* Readme: added Screenshots section.

= 1.0.6 =
* Merged rewrite copies stay draft for any non-draft status (private, pending, publish, future), including REST
* Admin cleanup skips the postmeta query when the pending-delete cache is empty
* Gutenberg meta-box-loader redirects to the original after Rewrite & Republish instead of the deleted copy

= 1.0.5 =
* Merged rewrite copies cannot be published again (REST, editor, or status transition) while `_oli_dp_pending_delete` is set
* Pending-delete copy IDs are cached in a transient and refreshed when a copy is marked or deleted
* `pre_get_posts` exclusion applies only to front-end, REST, and sitemap queries

= 1.0.4 =
* Merged rewrite copies are hidden immediately, excluded from queries and sitemaps, and cleaned up after 5 minutes even when WP-Cron is disabled
* Block editor shows a persistent rewrite notice, a pre-publish overwrite warning, and a Republish button
* Posts list marks rewrite copies as “Rewrite of: title”
* Row actions, settings labels, and admin-bar tooltips are clearer

= 1.0.3 =
* Gutenberg Rewrite & Republish no longer 500s: the copy is deleted after the meta-box-loader request or 60 seconds later
* Cancelling a schedule and publishing now clears `_oli_dp_scheduled_gmt` so the copy merges

= 1.0.2 =
* Rewrite & Republish merges after save and deletes the copy after the REST/editor/cron response
* Variation copies keep their original status
* WooCommerce lookup rows are written through the product CRUD API
* Uninstall no longer deletes shared `_dp_original` meta
* Scheduled republish compares GMT timestamps so a +60s schedule is not merged early

= 1.0.1 =
* Product variations are published and synced so the copy can be purchased
* Title prefix and suffix keep leading and trailing spaces
* Attachment copies are real attachments that share the original file
* Invalid duplicate URLs return HTTP 403
* Admin bar Duplicate This is limited to single edit screens and singular views
* Rewrite & Republish permanently deletes the working copy
* Copies store `_dp_original` and link back to the original
* Bulk actions include Rewrite & Republish
* Products are not enabled by default

= 1.0.0 =
* WordPress.org-ready release: no load_plugin_textdomain(), GPLv2 or later, unique oli_dp_ prefix
* Featured image is now a copy setting
* Meta is copied independently of content
* Rewrite & Republish waits until publish for scheduled copies
* Clone returns to the list table; New Draft opens the editor
* WooCommerce products get unique SKUs, no sales stats, and copied variations
* Admin notices stay on plugin-related screens
* Developer hooks for meta, taxonomies, permissions, and merge data
* PHPUnit unit and integration tests plus wp-env config
* Plugin Check and PHPCS WordPress-Extra in CI

= 0.1.2 =
* Moved block editor and Quick Edit JavaScript into enqueued files
* Moved admin CSS into an enqueued stylesheet

= 0.1.1 =
* Comparison screen, block editor menu items, Quick Edit, scheduled republish UI

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 1.0.9 =
Uninstall now clears the scheduled Rewrite & Republish cleanup events.

= 1.0.8 =
Regenerated translation template (POT).

= 1.0.7 =
Readme: added Screenshots section.

= 1.0.6 =
Keeps already-merged rewrite copies as draft, skips empty admin cleanup queries, and sends the block-editor meta-box save back to the original.

= 1.0.5 =
Stops republishing already-merged rewrite copies, caches pending-delete IDs, and limits query exclusion to the public site, REST, and sitemaps.

= 1.0.4 =
Hides merged rewrite copies immediately, adds block-editor rewrite notices, and marks copies in the posts list.

= 1.0.3 =
Fixes the Gutenberg 500 after Rewrite & Republish and merging after a cancelled schedule.

= 1.0.2 =
Fixes REST/editor republish notices, preserves private variation status, writes WooCommerce lookup rows, and leaves Yoast `_dp_original` meta on uninstall.

= 1.0.1 =
Fixes purchasable variable-product copies, title spacing, attachment copies, 403s on forged URLs, and admin-bar list-screen links.

= 1.0.0 =
Adds featured-image settings, scheduled Rewrite & Republish, WooCommerce handling, and WordPress.org compliance fixes.

== Privacy ==

Oli Duplicate Post does not collect, store, or transmit personal data. Duplication stays on your site.
