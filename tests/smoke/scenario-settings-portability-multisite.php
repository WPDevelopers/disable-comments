<?php
/**
 * Smoke scenario: settings export/import against network storage, over WP-CLI.
 *
 * Regression cover for the Codex review on PR #22.
 *
 * On a network-wide install the constructor does not always leave
 * $this->options holding what is stored: when the current blog is not among
 * the network's disabled_sites and the request is not a network admin one, it
 * substitutes a blank, everything-enabled config. Under WP-CLI that condition
 * is always met, because is_network_admin_ajax_context() has no GET parameter
 * to read there.
 *
 * Export then wrote that blank out as if it were the network's settings, and
 * import took it as the baseline for every field the incoming file omits.
 *
 * Verified before the fix, on this sandbox with subsite 2 excluded: importing
 * a file carrying one cosmetic flag reported a single-row diff —
 * show_existing_comments false → true — and silently set remove_everywhere
 * back to false, emptied both post-type lists, and wiped disabled_sites.
 * Comments back on across the network, reported as a one-line change.
 *
 * The two operations run as real `wp` subprocesses rather than in-process
 * calls, because the bug lives in how the plugin constructs itself on a
 * request against that subsite. Driving the methods from an instance built on
 * the network's main site would construct past it and prove nothing.
 *
 * Requires a multisite sandbox with the plugin network-activated — see
 * bin/smoke-test-multisite.sh. Skips cleanly on single site.
 *
 * Run: bin/smoke-test-multisite.sh
 *
 * @package Disable_Comments
 */

require __DIR__ . '/helpers.php';

if ( ! is_multisite() ) {
	echo "  SKIP  not a multisite install\n";
	echo "\nsettings-portability-multisite: skipped\n";
	return;
}

$dc = Disable_Comments::get_instance();
dc_check( 'plugin is network active', (bool) $dc->networkactive );

$site_ids = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
dc_check( 'the network has a subsite to run this from', count( $site_ids ) > 1 );

$subsite = (int) $site_ids[1];
$url     = get_site_url( $subsite );

$restore_options  = get_site_option( 'disable_comments_options' );
$restore_sitewide = get_site_option( 'disable_comments_sitewide_settings' );

/**
 * Read the network option as it is on disk right now.
 *
 * The subprocesses below write it, but this process has its own object cache
 * and will happily keep serving the value it read before they ran. Without the
 * flush every "this survived the import" assertion passes on a stale read and
 * tests nothing — which is exactly what the first draft of this file did.
 */
function dc_ms_stored() {
	wp_cache_flush();

	return (array) get_site_option( 'disable_comments_options' );
}

/**
 * Run one `wp` subprocess against the excluded subsite.
 */
function dc_ms_cli( $command, $url ) {
	return WP_CLI::runcommand(
		$command . ' --url=' . escapeshellarg( $url ),
		array(
			'launch'     => true,
			'return'     => 'all',
			'exit_error' => false,
		)
	);
}

// A real network configuration, with this subsite explicitly not covered —
// which is exactly the case the constructor blanks $this->options for.
$network_settings = array(
	'remove_everywhere'        => true,
	'disabled_post_types'      => array( 'post', 'page' ),
	'extra_post_types'         => array( 'book' ),
	'remove_xmlrpc_comments'   => 1,
	'remove_rest_API_comments' => 1,
	'disabled_sites'           => array( 'all' => false, 'site_1' => true, "site_$subsite" => false ),
	'settings_saved'           => true,
	'db_version'               => 8,
);

update_site_option( 'disable_comments_options', $network_settings );
update_site_option( 'disable_comments_sitewide_settings', false );

// ---------------------------------------------------------------------------
echo "\n-- the subsite really does construct with a blanked config --\n";

// Without this the rest of the scenario could pass on a build where the
// blanking never happens, and quietly stop testing anything.
$probe = dc_ms_cli( "eval 'echo empty( Disable_Comments::get_instance()->options[\"remove_everywhere\"] ) ? \"blank\" : \"loaded\";'", $url );
dc_check( 'the effective config is blanked there, so this tests something', 'blank' === trim( $probe->stdout ) );

// ---------------------------------------------------------------------------
echo "\n-- export names the network's settings, not the blanked view --\n";

$run = dc_ms_cli( 'disable-comments export', $url );
dc_check( 'export exits cleanly', 0 === $run->return_code );

$export = json_decode( $run->stdout, true );
dc_check( 'export is valid JSON', is_array( $export ) && isset( $export['settings'] ) );

$s = isset( $export['settings'] ) ? $export['settings'] : array();

dc_check( 'export carries remove_everywhere', ! empty( $s['remove_everywhere'] ) );
dc_check( 'export carries the disabled post types', array( 'post', 'page' ) === array_values( (array) $s['disabled_post_types'] ) );
dc_check( 'export carries the extra post types', array( 'book' ) === array_values( (array) $s['extra_post_types'] ) );
dc_check( 'export carries remove_xmlrpc_comments', 1 === (int) $s['remove_xmlrpc_comments'] );

// ---------------------------------------------------------------------------
echo "\n-- a partial import leaves everything the file omits alone --\n";

$file = get_temp_dir() . 'dc-smoke-partial-' . uniqid() . '.json';
file_put_contents(
	$file,
	wp_json_encode(
		array(
			'schema_version' => 1,
			'settings'       => array( 'show_existing_comments' => true ),
		)
	)
);

$dry = dc_ms_cli( 'disable-comments import --dry-run --file=' . escapeshellarg( $file ), $url );
dc_check( 'the dry run exits cleanly', 0 === $dry->return_code );
dc_check( 'the dry run names the field the file carries', false !== strpos( $dry->stdout, 'show_existing_comments' ) );
dc_check( 'and claims no other field is changing', false === strpos( $dry->stdout, 'remove_everywhere' ) );
$unchanged = dc_ms_stored();
dc_check( 'and writes nothing', ! empty( $unchanged['remove_everywhere'] ) && empty( $unchanged['show_existing_comments'] ) );

$run = dc_ms_cli( 'disable-comments import --file=' . escapeshellarg( $file ), $url );
dc_check( 'the import exits cleanly', 0 === $run->return_code );
dc_check( 'the import reports the field the file carries', false !== strpos( $run->stdout, 'show_existing_comments' ) );

unlink( $file );

$after = dc_ms_stored();

dc_check( 'remove_everywhere survived the import', ! empty( $after['remove_everywhere'] ) );
dc_check( 'the disabled post types survived', array( 'post', 'page' ) === array_values( (array) $after['disabled_post_types'] ) );
dc_check( 'the extra post types survived', array( 'book' ) === array_values( (array) $after['extra_post_types'] ) );
dc_check( 'remove_rest_API_comments survived', 1 === (int) $after['remove_rest_API_comments'] );
dc_check( 'the per-site exclusions survived', isset( $after['disabled_sites']["site_$subsite"] ) && false === $after['disabled_sites']["site_$subsite"] );
dc_check( 'and the field the file did carry was written', ! empty( $after['show_existing_comments'] ) );

update_site_option( 'disable_comments_options', $restore_options );
update_site_option( 'disable_comments_sitewide_settings', $restore_sitewide );

dc_smoke_finish( 'settings-portability-multisite' );
