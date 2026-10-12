<?php
/**
 * External services disclosure (WordPress.org Guidelines 6 and 7, issue #164).
 *
 * The opt-in usage tracker is the only code path that contacts a third party.
 * This scenario intercepts every outbound request it makes and holds it to
 * what the opt-in notice and the readme promise: nothing before consent, and
 * after consent only send.wpinsight.com, over HTTPS. It used to look up the
 * requester's IP at ip-api.com over plain HTTP, which neither disclosed.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc      = Disable_Comments::get_instance();
$tracker = $dc->tracker;
$slug    = 'disable-comments';

dc_check( 'usage tracker is loaded', $tracker instanceof DisableComments_Plugin_Tracker );
if ( ! $tracker instanceof DisableComments_Plugin_Tracker ) {
	dc_smoke_finish( 'External services' );
	return;
}

$GLOBALS['dc_outbound'] = array();
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$GLOBALS['dc_outbound'][] = $url;
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'siteId' => 'dc-smoke-site' ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	},
	10,
	3
);

/**
 * Forget any earlier send so the tracker takes its first-contact path, which
 * is where the IP lookup used to happen.
 *
 * @param string $slug Tracker plugin slug.
 */
function dc_reset_tracker_state( $slug ) {
	delete_option( "wpins_{$slug}_site_id" );
	delete_option( "wpins_{$slug}_original_url" );
	delete_option( 'wpins_last_track_time' );
	delete_option( 'wpins_block_notice' );
	$GLOBALS['dc_outbound'] = array();
}

// A routable address, so the old guard against 127.0.0.1 would not skip it.
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------
echo "\n-- before opt-in --\n";

dc_reset_tracker_state( $slug );
delete_option( 'wpins_allow_tracking' );
$tracker->do_tracking( true );
dc_check( 'nothing is sent without consent', array() === $GLOBALS['dc_outbound'] );

// ---------------------------------------------------------------------------
echo "\n-- after opt-in, first send --\n";

dc_reset_tracker_state( $slug );
update_option( 'wpins_allow_tracking', array( $slug => $slug ) );
$tracker->do_tracking( true );

$hosts = array_values( array_unique( array_map( 'wp_parse_url', $GLOBALS['dc_outbound'], array_fill( 0, count( $GLOBALS['dc_outbound'] ), PHP_URL_HOST ) ) ) );
dc_check( 'the tracker sends its report', count( $GLOBALS['dc_outbound'] ) > 0 );
dc_check( 'only send.wpinsight.com is contacted (got: ' . implode( ', ', $hosts ) . ')', array( 'send.wpinsight.com' ) === $hosts );
dc_check( 'no IP lookup at ip-api.com', false === strpos( implode( ' ', $GLOBALS['dc_outbound'] ), 'ip-api.com' ) );
foreach ( $GLOBALS['dc_outbound'] as $url ) {
	dc_check( "request is HTTPS: $url", 0 === strpos( $url, 'https://' ) );
}

// ---------------------------------------------------------------------------
echo "\n-- the opt-in notice discloses what is sent and where --\n";

dc_reset_tracker_state( $slug );
ob_start();
$tracker->notice();
$notice = strtolower( wp_strip_all_tags( ob_get_clean() ) );

dc_check( 'notice is rendered for an administrator', '' !== trim( $notice ) );
foreach ( array( 'send.wpinsight.com', 'site url', 'site name', 'email', 'php', 'server software', 'plugins', 'theme', 'deactivat' ) as $needle ) {
	dc_check( "notice mentions \"$needle\"", false !== strpos( $notice, $needle ) );
}
dc_check( 'notice no longer promises a third-party IP lookup', false === strpos( $notice, 'ip-api' ) );

// ---------------------------------------------------------------------------
echo "\n-- shipped code has no plain-HTTP remote calls --\n";

$root     = dirname( dirname( __DIR__ ) );
$offences = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( ! preg_match( '/\.(php|js)$/', $path ) || preg_match( '#/(\.git|node_modules|vendor|tests)/#', substr( $path, strlen( $root ) ) ) ) {
		continue;
	}
	$code = file_get_contents( $path );
	if ( preg_match( '/(wp_remote_\w+|wp_safe_remote_\w+|fetch)\s*\(\s*[\'"]http:\/\//', $code ) || false !== strpos( $code, 'ip-api.com' ) ) {
		$offences[] = substr( $path, strlen( $root ) + 1 );
	}
}
dc_check( 'no http:// remote call or ip-api.com reference (found: ' . implode( ', ', $offences ) . ')', array() === $offences );

// ---------------------------------------------------------------------------
echo "\n-- readme discloses the service --\n";

$readme = file_get_contents( $root . '/readme.txt' );
dc_check( 'readme has an External services section', false !== strpos( $readme, '== External services ==' ) );
$section = (string) substr( $readme, (int) strpos( $readme, '== External services ==' ) );
$section = (string) preg_replace( '/\n== (?!External services).*$/s', '', $section );
foreach ( array( 'send.wpinsight.com', 'Terms', 'Privacy' ) as $needle ) {
	dc_check( "External services section mentions \"$needle\"", false !== strpos( $section, $needle ) );
}

dc_reset_tracker_state( $slug );
delete_option( 'wpins_allow_tracking' );
dc_smoke_finish( 'External services' );
