<?php
/**
 * Smoke tests for Oli Duplicate Post plugin.
 *
 * Usage: php tests/smoke-test.php
 *
 * @package Oli_Duplicate_Post
 */

/**
 * Print a passing test line.
 *
 * @param string $message Message to print.
 */
function oli_dp_test_pass( $message ) {
	echo "\033[0;32m✓\033[0m " . $message . "\n";
}

/**
 * Print a failing test line.
 *
 * @param string $message Message to print.
 */
function oli_dp_test_fail( $message ) {
	echo "\033[0;31m✗\033[0m " . $message . "\n";
}

/**
 * Print a section header.
 *
 * @param string $message Section title.
 */
function oli_dp_test_section( $message ) {
	echo "\n\033[1;36m" . $message . "\033[0m\n";
	echo str_repeat( '=', 60 ) . "\n";
}

/**
 * Recursively collect PHP files, skipping vendor.
 *
 * @param string $dir Directory to scan.
 * @return array
 */
function oli_dp_collect_php_files( $dir ) {
	$files  = array();
	$skip   = array( 'vendor', 'node_modules', '.git', 'artifacts' );
	$handle = opendir( $dir );

	if ( false === $handle ) {
		return $files;
	}

	while ( false !== ( $entry = readdir( $handle ) ) ) {
		if ( '.' === $entry || '..' === $entry || in_array( $entry, $skip, true ) ) {
			continue;
		}

		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, oli_dp_collect_php_files( $path ) );
		} elseif ( '.php' === substr( $entry, -4 ) ) {
			$files[] = $path;
		}
	}

	closedir( $handle );
	return $files;
}

$total_tests  = 0;
$passed_tests = 0;
$plugin_root  = dirname( __DIR__ );

oli_dp_test_section( 'Oli Duplicate Post - Smoke Tests' );

oli_dp_test_section( 'File Structure Tests' );
++$total_tests;
if ( file_exists( $plugin_root . '/oli-duplicate-post.php' ) ) {
	oli_dp_test_pass( 'Main plugin file exists' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Main plugin file not found' );
}

$required_dirs = array( 'includes', 'admin', 'assets', 'assets/js', 'assets/css', 'languages', 'tests', 'tests/phpunit', '.github/workflows' );
foreach ( $required_dirs as $dir ) {
	++$total_tests;
	if ( is_dir( $plugin_root . '/' . $dir ) ) {
		oli_dp_test_pass( "Directory exists: {$dir}" );
		++$passed_tests;
	} else {
		oli_dp_test_fail( "Directory missing: {$dir}" );
	}
}

$required_files = array(
	'includes/class-oli-dp-utils.php',
	'includes/class-oli-dp-permissions.php',
	'includes/class-oli-dp-settings.php',
	'includes/class-oli-dp-duplicator.php',
	'includes/class-oli-dp-woocommerce.php',
	'includes/class-oli-dp-rewrite-republish.php',
	'includes/class-oli-dp-comparison.php',
	'includes/class-oli-dp-hooks.php',
	'includes/class-oli-dp-admin.php',
	'includes/class-oli-dp-quick-edit.php',
	'admin/class-oli-dp-post-list.php',
	'admin/class-oli-dp-bulk-actions.php',
	'admin/class-oli-dp-admin-bar.php',
	'admin/class-oli-dp-meta-box.php',
	'assets/js/block-editor.js',
	'assets/js/quick-edit.js',
	'assets/css/admin.css',
	'uninstall.php',
	'LICENSE',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
	'wp-env.json',
	'composer.json',
);

foreach ( $required_files as $file ) {
	++$total_tests;
	if ( file_exists( $plugin_root . '/' . $file ) ) {
		oli_dp_test_pass( "File exists: {$file}" );
		++$passed_tests;
	} else {
		oli_dp_test_fail( "File missing: {$file}" );
	}
}

oli_dp_test_section( 'WordPress.org Compliance Tests' );
++$total_tests;
$readme_path    = $plugin_root . '/readme.txt';
$readme_content = '';
if ( file_exists( $readme_path ) ) {
	oli_dp_test_pass( 'readme.txt exists' );
	++$passed_tests;

	$readme_content   = file_get_contents( $readme_path );
	$required_headers = array(
		'Contributors:',
		'Tags:',
		'Requires at least:',
		'Tested up to:',
		'Requires PHP:',
		'Stable tag:',
		'License:',
	);

	foreach ( $required_headers as $header ) {
		++$total_tests;
		if ( false !== strpos( $readme_content, $header ) ) {
			oli_dp_test_pass( "readme.txt contains: {$header}" );
			++$passed_tests;
		} else {
			oli_dp_test_fail( "readme.txt missing: {$header}" );
		}
	}

	++$total_tests;
	if ( false !== strpos( $readme_content, 'Contributors: bigrat95' ) ) {
		oli_dp_test_pass( 'readme.txt Contributors uses wordpress.org username bigrat95' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'readme.txt Contributors should remain bigrat95' );
	}

	++$total_tests;
	if ( false !== strpos( $readme_content, 'oli@olivierbigras.com' ) ) {
		oli_dp_test_pass( 'readme.txt includes contact email' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'readme.txt missing contact email' );
	}

	++$total_tests;
	if ( false !== strpos( $readme_content, 'github.com/olivierbigras' ) && false === strpos( $readme_content, 'github.com/bigrat95' ) ) {
		oli_dp_test_pass( 'readme.txt GitHub URL uses olivierbigras owner' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'readme.txt GitHub URL should use github.com/olivierbigras' );
	}

	++$total_tests;
	if ( false !== strpos( $readme_content, '== External services ==' ) ) {
		oli_dp_test_pass( 'readme.txt has External services section' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'readme.txt missing External services section' );
	}
} else {
	oli_dp_test_fail( 'readme.txt not found' );
}

oli_dp_test_section( 'Code Standards Tests' );
$main_file  = file_get_contents( $plugin_root . '/oli-duplicate-post.php' );
$plugin_php = '';
foreach ( oli_dp_collect_php_files( $plugin_root ) as $php_path ) {
	if ( false !== strpos( $php_path, '/tests/' ) ) {
		continue;
	}
	$plugin_php .= file_get_contents( $php_path ) . "\n";
}

++$total_tests;
if ( preg_match( '/oli_dp_/', $plugin_php ) && preg_match( '/Oli_DP_/', $plugin_php ) ) {
	oli_dp_test_pass( 'Proper prefixing used (oli_dp_ and Oli_DP_)' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Prefix issues found' );
}

++$total_tests;
if ( preg_match( '/if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)/', $main_file ) ) {
	oli_dp_test_pass( 'ABSPATH security check present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'ABSPATH security check missing' );
}

++$total_tests;
if ( preg_match( '/Text Domain:\s*oli-duplicate-post/', $main_file ) ) {
	oli_dp_test_pass( 'Correct text domain declared' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Text domain issue' );
}

++$total_tests;
if ( preg_match( '/Author URI:\s*https:\/\/github.com\/olivierbigras/', $main_file ) ) {
	oli_dp_test_pass( 'Author URI is github.com/olivierbigras' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Author URI should be https://github.com/olivierbigras' );
}

++$total_tests;
if ( ! preg_match( '/^\s*\*\s*Plugin URI:/m', $main_file ) ) {
	oli_dp_test_pass( 'Plugin URI omitted from plugin header' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Plugin URI should be omitted' );
}

++$total_tests;
if ( ! preg_match( '/load_plugin_textdomain\s*\(/', $plugin_php ) ) {
	oli_dp_test_pass( 'Does not call load_plugin_textdomain()' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'load_plugin_textdomain() must not be called' );
}

++$total_tests;
$po_files = array_merge(
	glob( $plugin_root . '/languages/*.po' ) ?: array(),
	glob( $plugin_root . '/languages/*.mo' ) ?: array()
);
if ( empty( $po_files ) ) {
	oli_dp_test_pass( 'No bundled .po or .mo files' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Bundled translation binaries are not allowed' );
}

++$total_tests;
$version_in_main   = preg_match( '/Version:\s*([\d.]+)/', $main_file, $main_version );
$version_in_readme = preg_match( '/Stable tag:\s*([\d.]+)/', $readme_content, $readme_version );
if ( $version_in_main && $version_in_readme && $main_version[1] === $readme_version[1] ) {
	oli_dp_test_pass( "Version consistency: {$main_version[1]}" );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Version mismatch between plugin file and readme.txt' );
}

oli_dp_test_section( 'PHP Syntax Tests' );
$php_files = glob( $plugin_root . '/*.php' );
$php_files = array_merge( $php_files, glob( $plugin_root . '/includes/*.php' ) );
$php_files = array_merge( $php_files, glob( $plugin_root . '/admin/*.php' ) );

foreach ( $php_files as $php_file ) {
	++$total_tests;
	$output     = array();
	$return_var = 0;
	exec( 'php -l ' . escapeshellarg( $php_file ) . ' 2>&1', $output, $return_var );

	if ( 0 === $return_var ) {
		oli_dp_test_pass( 'Valid syntax: ' . basename( $php_file ) );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'Syntax error in: ' . basename( $php_file ) );
		echo '  ' . implode( "\n  ", $output ) . "\n";
	}
}

oli_dp_test_section( 'WordPress Standards Tests' );

++$total_tests;
if ( preg_match( '/wp_insert_post/', $plugin_php ) ) {
	oli_dp_test_pass( 'Uses wp_insert_post() for post creation' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'wp_insert_post() not found' );
}

++$total_tests;
if ( preg_match( '/sanitize_|esc_html|esc_attr|esc_url/', $plugin_php ) ) {
	oli_dp_test_pass( 'Uses sanitization/escaping functions' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'No sanitization functions found' );
}

++$total_tests;
if ( preg_match( '/wp_verify_nonce|wp_create_nonce|wp_nonce_url/', $plugin_php ) ) {
	oli_dp_test_pass( 'Uses nonce verification' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Nonce functions not found' );
}

++$total_tests;
if ( preg_match( '/copy_featured_image/', $plugin_php ) ) {
	oli_dp_test_pass( 'Featured image copy setting present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Featured image copy setting missing' );
}

++$total_tests;
if ( preg_match( '/future/', $plugin_php ) && preg_match( '/merge_to_original/', $plugin_php ) ) {
	oli_dp_test_pass( 'Scheduled republish path is present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Scheduled republish path missing' );
}

oli_dp_test_section( 'Asset Enqueue Tests' );

$plugin_js = '';
$js_files  = glob( $plugin_root . '/assets/js/*.js' );
if ( is_array( $js_files ) ) {
	foreach ( $js_files as $js_file ) {
		$plugin_js .= file_get_contents( $js_file ) . "\n";
	}
}

++$total_tests;
if ( false === strpos( $plugin_php, '<script' ) && false === strpos( $plugin_php, '<style' ) ) {
	oli_dp_test_pass( 'No inline <script> or <style> tags in plugin PHP' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Inline <script> or <style> tags still present in plugin PHP' );
}

++$total_tests;
if ( false === strpos( $plugin_php, 'wp_add_inline_script' ) && false === strpos( $plugin_php, 'wp_add_inline_style' ) ) {
	oli_dp_test_pass( 'No wp_add_inline_script/style usage' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'wp_add_inline_script or wp_add_inline_style still used' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'wp_enqueue_script' ) && false !== strpos( $plugin_php, 'wp_localize_script' ) && false !== strpos( $plugin_php, 'wp_enqueue_style' ) ) {
	oli_dp_test_pass( 'Scripts and styles are enqueued and localized' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Missing wp_enqueue_script, wp_localize_script, or wp_enqueue_style' );
}

oli_dp_test_section( 'Feature Parity Tests' );

++$total_tests;
if ( false !== strpos( $plugin_php, 'oli-dp-compare' ) ) {
	oli_dp_test_pass( 'Rewrite & Republish comparison screen is registered' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Comparison screen missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_js, 'PluginMoreMenuItem' ) ) {
	oli_dp_test_pass( 'Block editor toolbar/menu items present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Block editor toolbar items missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_js, 'createWarningNotice' ) && false !== strpos( $plugin_js, 'isDismissible: false' ) ) {
	oli_dp_test_pass( 'Block editor rewrite notice is non-dismissible' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Block editor rewrite notice missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_js, 'PluginPrePublishPanel' ) ) {
	oli_dp_test_pass( 'Block editor pre-publish overwrite warning present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Block editor pre-publish panel missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'Copy to New Draft' ) || false !== strpos( $plugin_js, 'Copy to New Draft' ) ) {
	oli_dp_test_pass( 'Quick edit / Copy to New Draft link present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Copy to New Draft link missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'oli_dp_duplicated' ) ) {
	oli_dp_test_pass( 'Success notices after duplication actions present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Duplication success notices missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'Republish Schedule' ) ) {
	oli_dp_test_pass( 'Scheduled republish UI present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Scheduled republish UI missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'Oli_DP_WooCommerce' ) ) {
	oli_dp_test_pass( 'WooCommerce handling class present' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'WooCommerce handling missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'WC_Product_Variable::sync' ) && false !== strpos( $plugin_php, 'wc_product_meta_lookup' ) ) {
	oli_dp_test_pass( 'Variable products sync and update lookup tables' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Variable product sync/lookup missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, "sanitize_title_affix" ) ) {
	oli_dp_test_pass( 'Title prefix/suffix sanitization preserves spaces' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Title affix sanitizer missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'wp_insert_attachment' ) ) {
	oli_dp_test_pass( 'Attachment copies use wp_insert_attachment()' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Attachment copies do not create real attachments' );
}

++$total_tests;
if ( substr_count( $plugin_php, "'response' => 403" ) >= 1 ) {
	oli_dp_test_pass( 'Invalid action URLs return HTTP 403' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'wp_die 403 response missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, "pagenow ) && 'post.php' === \$pagenow" ) || false !== strpos( $plugin_php, "'post.php' === \$pagenow" ) ) {
	oli_dp_test_pass( 'Admin bar is limited to single edit screens' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Admin bar still resolves posts on list screens' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'wp_delete_post' ) && false === strpos( $plugin_php, 'wp_trash_post' ) && false !== strpos( $plugin_php, 'wp_after_insert_post' ) ) {
	oli_dp_test_pass( 'Rewrite copies are deleted after save completes' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Rewrite merge is not deferred until after save' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'oli_dp_delete_rewrite_copy' ) && false !== strpos( $plugin_php, 'meta-box-loader' ) ) {
	oli_dp_test_pass( 'Rewrite copy deletion waits for Gutenberg meta-box-loader' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Gutenberg meta-box-loader delete path missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'hide_merged_copy' ) && false !== strpos( $plugin_php, 'exclude_merged_copies' ) && false !== strpos( $plugin_php, 'cleanup_stale_merged_copies' ) ) {
	oli_dp_test_pass( 'Merged copies are hidden, excluded, and cleaned up after 5 minutes' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Merged-copy hide/exclude/cleanup path missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'rest_pre_insert_' ) && false !== strpos( $plugin_php, 'prevent_pending_delete_publish' ) && false !== strpos( $plugin_php, 'is_pending_delete_copy' ) ) {
	oli_dp_test_pass( 'Merged copies cannot be republished via REST or status transition' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Pending-delete republish guard missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'oli_dp_pending_delete_ids' ) && false !== strpos( $plugin_php, 'invalidate_pending_delete_ids_cache' ) ) {
	oli_dp_test_pass( 'Pending-delete IDs are cached in a transient' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Pending-delete ID transient cache missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'query_should_exclude_merged_copies' ) && false !== strpos( $plugin_php, 'is_sitemap_query' ) ) {
	oli_dp_test_pass( 'pre_get_posts exclusion is limited to front-end, REST, and sitemaps' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'pre_get_posts exclusion scope missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'Rewrite of:' ) && false !== strpos( $plugin_php, 'display_post_states' ) ) {
	oli_dp_test_pass( 'Rewrite copies have a posts-list state' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Rewrite copy post state missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'for="oli_dp_title_prefix"' ) && false !== strpos( $plugin_php, 'id="oli_dp_' ) ) {
	oli_dp_test_pass( 'Settings text fields have label for attributes' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Settings label for attributes missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'Duplicate now' ) && false !== strpos( $plugin_php, 'Creates a copy immediately, without opening it' ) ) {
	oli_dp_test_pass( 'Row actions and admin-bar tooltips use clarified labels' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Clarified row-action or admin-bar labels missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, 'gmt_mysql_is_future' ) ) {
	oli_dp_test_pass( 'Scheduled republish compares GMT timestamps' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'GMT schedule comparison helper missing' );
}

++$total_tests;
$uninstall_php = file_get_contents( $plugin_root . '/uninstall.php' );
if ( false === strpos( $uninstall_php, "'_dp_original'" ) && false !== strpos( $uninstall_php, '_oli_dp_original_post' ) ) {
	oli_dp_test_pass( 'Uninstall leaves shared _dp_original meta' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Uninstall still deletes shared _dp_original meta' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, "'_dp_original'" ) ) {
	oli_dp_test_pass( 'Copies store _dp_original meta' );
	++$passed_tests;
} else {
	oli_dp_test_fail( '_dp_original meta is missing' );
}

++$total_tests;
if ( false !== strpos( $plugin_php, "'oli_dp_rewrite_republish'" ) ) {
	oli_dp_test_pass( 'Bulk actions include Rewrite & Republish' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Bulk Rewrite & Republish action missing' );
}

oli_dp_test_section( 'CI/CD Tests' );
++$total_tests;
$workflow_file = $plugin_root . '/.github/workflows/php-checks.yml';
if ( file_exists( $workflow_file ) ) {
	oli_dp_test_pass( 'GitHub Actions workflow exists' );
	++$passed_tests;

	$workflow_content = file_get_contents( $workflow_file );

	++$total_tests;
	if ( false !== strpos( $workflow_content, 'php -l' ) ) {
		oli_dp_test_pass( 'Workflow includes syntax checking' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'Workflow missing syntax check' );
	}

	++$total_tests;
	if ( false !== strpos( $workflow_content, 'phpcs' ) && false !== strpos( $workflow_content, 'WordPress-Extra' ) ) {
		oli_dp_test_pass( 'Workflow includes PHPCS WordPress-Extra' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'Workflow missing PHPCS WordPress-Extra' );
	}

	++$total_tests;
	if ( false !== strpos( $workflow_content, 'plugin-check-action' ) ) {
		oli_dp_test_pass( 'Workflow includes Plugin Check action' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'Workflow missing Plugin Check action' );
	}

	++$total_tests;
	if ( false !== strpos( $workflow_content, 'phpunit' ) ) {
		oli_dp_test_pass( 'Workflow includes PHPUnit' );
		++$passed_tests;
	} else {
		oli_dp_test_fail( 'Workflow missing PHPUnit' );
	}
} else {
	oli_dp_test_fail( 'GitHub Actions workflow not found' );
}

oli_dp_test_section( 'Documentation Tests' );
++$total_tests;
if ( file_exists( $plugin_root . '/CHANGELOG.md' ) ) {
	oli_dp_test_pass( 'CHANGELOG.md exists' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'CHANGELOG.md not found' );
}

++$total_tests;
if ( file_exists( $plugin_root . '/README.md' ) ) {
	oli_dp_test_pass( 'README.md exists' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'README.md not found' );
}

++$total_tests;
$changelog = file_get_contents( $plugin_root . '/CHANGELOG.md' );
$readme_md = file_get_contents( $plugin_root . '/README.md' );
if ( false === strpos( $changelog . $readme_md, 'github.com/bigrat95' ) && false !== strpos( $changelog . $readme_md, 'github.com/olivierbigras' ) ) {
	oli_dp_test_pass( 'Docs use github.com/olivierbigras URLs' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'Docs still contain github.com/bigrat95 or missing olivierbigras GitHub URL' );
}

++$total_tests;
$phpcs_config = file_get_contents( $plugin_root . '/phpcs.xml.dist' );
if ( false !== strpos( $phpcs_config, 'WordPress-Extra' ) ) {
	oli_dp_test_pass( 'phpcs.xml.dist uses WordPress-Extra' );
	++$passed_tests;
} else {
	oli_dp_test_fail( 'phpcs.xml.dist must use WordPress-Extra' );
}

oli_dp_test_section( 'Test Summary' );

$pass_rate = ( $passed_tests / $total_tests ) * 100;

echo "\n";
echo "Total Tests: {$total_tests}\n";
echo "Passed: {$passed_tests}\n";
echo 'Failed: ' . ( $total_tests - $passed_tests ) . "\n";
printf( "Pass Rate: %.1f%%\n", $pass_rate );
echo "\n";

if ( $passed_tests === $total_tests ) {
	echo "\033[1;32m✓ All tests passed!\033[0m\n\n";
	exit( 0 );
}

echo "\033[1;31m✗ Smoke tests failed.\033[0m\n\n";
exit( 1 );
