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
		$GLOBALS['dc_outbound'][]        = $url;
		$GLOBALS['dc_outbound_bodies'][] = isset( $args['body'] ) ? $args['body'] : null;
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
	// The stub's siteId makes send_data() store the whole payload under it.
	delete_option( "wpins_{$slug}_dc-smoke-site" );
	delete_option( "wpins_{$slug}_dc-smoke-site_send_failed" );
	delete_option( 'wpins_last_track_time' );
	delete_option( "wpins_deactivation_reason_{$slug}" );
	delete_option( "wpins_deactivation_details_{$slug}" );
	$GLOBALS['dc_outbound']        = array();
	$GLOBALS['dc_outbound_bodies'] = array();
}

$block_notice_before = get_option( 'wpins_block_notice' );

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
echo "\n-- daily reports collect the current account email when available --\n";

dc_reset_tracker_state( $slug );
update_option( 'wpins_allow_tracking', array( $slug => $slug ) );
$tracker->do_tracking();
$sent = (array) end( $GLOBALS['dc_outbound_bodies'] );
dc_check( 'a due daily report includes the current administrator account email', isset( $sent['email'] ) && wp_get_current_user()->user_email === $sent['email'] );

dc_reset_tracker_state( $slug );
wp_set_current_user( 0 );
$tracker->do_tracking();
$sent = (array) end( $GLOBALS['dc_outbound_bodies'] );
dc_check( 'a due report without a logged-in user omits email', count( $GLOBALS['dc_outbound'] ) > 0 && ! isset( $sent['email'] ) );
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------
echo "\n-- the opt-in notice discloses what is sent and where --\n";

dc_reset_tracker_state( $slug );
delete_option( 'wpins_block_notice' );
ob_start();
$tracker->notice();
$notice = strtolower( wp_strip_all_tags( ob_get_clean() ) );

dc_check( 'notice is rendered for an administrator', '' !== trim( $notice ) );
dc_check( 'notice discloses email on daily reports', false !== strpos( $notice, 'daily report can also include' ) );
foreach ( array( 'send.wpinsight.com', 'site url', 'site name', 'email', 'php', 'server software', 'plugins', 'theme', 'text direction', 'deactivat', 'ip address' ) as $needle ) {
	dc_check( "notice mentions \"$needle\"", false !== strpos( $notice, $needle ) );
}

// ---------------------------------------------------------------------------
echo "\n-- deactivation sends only with consent --\n";

dc_reset_tracker_state( $slug );
delete_option( 'wpins_allow_tracking' );
update_option( "wpins_deactivation_reason_{$slug}", 'smoke reason' );
$tracker->deactivate_this_plugin();
dc_check( 'deactivation sends nothing without consent', array() === $GLOBALS['dc_outbound'] );

dc_reset_tracker_state( $slug );
update_option( 'wpins_allow_tracking', array( $slug => $slug ) );
update_option( "wpins_deactivation_reason_{$slug}", 'smoke reason' );
update_option( "wpins_deactivation_details_{$slug}", 'smoke note' );
$tracker->deactivate_this_plugin();
$sent = (array) end( $GLOBALS['dc_outbound_bodies'] );
dc_check( 'deactivation report goes only to https://send.wpinsight.com', count( $GLOBALS['dc_outbound'] ) > 0 && array() === array_diff( $GLOBALS['dc_outbound'], array( DisableComments_Plugin_Tracker::API_URL ) ) );
dc_check( 'deactivation report carries the reason and the note, as disclosed', isset( $sent['deactivation_reason'], $sent['deactivation_details'] ) );
dc_check( 'deactivation report carries the deactivating admin\'s email, as disclosed', isset( $sent['email'] ) && wp_get_current_user()->user_email === $sent['email'] );

// ---------------------------------------------------------------------------
echo "\n-- shipped code has no plain-HTTP remote calls --\n";

$root     = dirname( dirname( __DIR__ ) );
$offences = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( ! preg_match( '/\.(php|js)$/', $path ) || preg_match( '#/(\.git|node_modules|vendor|tests|\.wp-sandbox|wp-sandbox[^/]*)/#', substr( $path, strlen( $root ) ) ) ) {
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
dc_check( 'readme discloses email on daily reports', false !== strpos( $readme, 'A daily report can also include' ) );
$section = (string) preg_replace( '/\n== (?!External services).*$/s', '', $section );
foreach ( array( 'send.wpinsight.com', 'Terms', 'Privacy' ) as $needle ) {
	dc_check( "External services section mentions \"$needle\"", false !== strpos( $section, $needle ) );
}

dc_reset_tracker_state( $slug );
delete_option( 'wpins_allow_tracking' );
if ( false === $block_notice_before ) {
	delete_option( 'wpins_block_notice' );
} else {
	update_option( 'wpins_block_notice', $block_notice_before );
}
dc_smoke_finish( 'External services' );
