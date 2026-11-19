<?php
/**
 * xSpeed Cache tips: who sees them, and when they go away.
 *
 * Run: bin/wp eval-file tests/smoke/scenario-xspeed-tips.php
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

Disable_Comments::get_instance();

$tips = 'Disable_Comments_XSpeed_Tips';

dc_check( 'the tips class is loaded', class_exists( $tips ) );
if ( ! class_exists( $tips ) ) {
	dc_smoke_finish( 'xspeed-tips' );
	return;
}

/**
 * Capture what render() prints.
 *
 * @param string $tip Tip id.
 * @return string
 */
function dc_xspeed_render( $tip ) {
	ob_start();
	Disable_Comments_XSpeed_Tips::render( $tip );
	return (string) ob_get_clean();
}

wp_set_current_user( 1 );
delete_user_meta( 1, Disable_Comments_XSpeed_Tips::DISMISSED_META );

echo "\n-- an administrator sees every tip --\n";
foreach ( array( 'sidebar', 'save', 'delete', 'footer' ) as $tip ) {
	dc_check( "{$tip} tip is shown", $tips::should_show( $tip ) );
}

$card = dc_xspeed_render( 'sidebar' );
dc_check( 'the sidebar card renders', false !== strpos( $card, 'How fast is your site?' ) );
dc_check( 'links carry the campaign tag', false !== strpos( $card, 'utm_source=disable-comments' ) );
dc_check( 'no cache plugin, no import line', false === strpos( $card, 'import its settings' ) );
dc_check( 'one button only: no "About xSpeed Cache"', 1 === substr_count( $card, 'class="dc-xspeed-btn ' ) && false === strpos( $card, 'About xSpeed Cache' ) );
dc_check( 'the line under it links to xSpeed on WordPress.org', false !== strpos( $card, 'href="https://wordpress.org/plugins/xspeed/"' ) );
dc_check( 'the footer says "Backed by your trusted WPDeveloper team"', false !== strpos( $card, 'Backed by your trusted <strong>WPDeveloper</strong> team' ) );
dc_check( 'the save tip is printed hidden', false !== strpos( dc_xspeed_render( 'save' ), ' hidden>' ) );

echo "\n-- never an admin notice --\n";
dc_check(
	'nothing hooks admin_notices',
	false === has_action( 'admin_notices', array( $tips, 'render' ) )
		&& false === has_action( 'all_admin_notices', array( $tips, 'render' ) )
);

echo "\n-- dismissed means gone --\n";
update_user_meta( 1, Disable_Comments_XSpeed_Tips::DISMISSED_META, array( 'save', 'sidebar' ) );
dc_check( 'a dismissed save tip stays hidden', ! $tips::should_show( 'save' ) );
dc_check( 'a dismissed sidebar card stays hidden', '' === dc_xspeed_render( 'sidebar' ) );
dc_check( 'other tips are unaffected', $tips::should_show( 'delete' ) );
dc_check( 'the footer link is not dismissible', $tips::should_show( 'footer' ) );
delete_user_meta( 1, Disable_Comments_XSpeed_Tips::DISMISSED_META );

echo "\n-- people who cannot manage the site see nothing --\n";
$subscriber = wp_insert_user(
	array(
		'user_login' => 'dc-xspeed-sub-' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
wp_set_current_user( $subscriber );
dc_check( 'a subscriber gets no sidebar card', ! $tips::should_show( 'sidebar' ) );
dc_check( 'a subscriber gets no footer link', ! $tips::should_show( 'footer' ) );
wp_set_current_user( 1 );
wp_delete_user( $subscriber );

echo "\n-- the filter turns everything off --\n";
add_filter( 'disable_comments_show_xspeed_tips', '__return_false' );
foreach ( array( 'sidebar', 'save', 'delete', 'footer' ) as $tip ) {
	dc_check( "{$tip} tip is hidden by the filter", ! $tips::should_show( $tip ) );
}
remove_filter( 'disable_comments_show_xspeed_tips', '__return_false' );

echo "\n-- the import line only names a cache plugin the site really runs --\n";
if ( ! function_exists( 'rocket_clean_domain' ) ) {
	/**
	 * Stand-in for WP Rocket's code being loaded.
	 */
	function rocket_clean_domain() {}
}
dc_check( 'loaded code alone is not enough', '' === $tips::importable_cache_plugin() );
dc_check( 'so the card has no import line', false === strpos( dc_xspeed_render( 'sidebar' ), 'import its settings' ) );

$dc_active_rocket = function ( $plugins ) {
	$plugins   = (array) $plugins;
	$plugins[] = 'wp-rocket/wp-rocket.php';
	return $plugins;
};
add_filter( 'option_active_plugins', $dc_active_rocket );
dc_check( 'active and loaded: WP Rocket is detected', 'WP Rocket' === $tips::importable_cache_plugin() );
dc_check( 'the card names it', false !== strpos( dc_xspeed_render( 'sidebar' ), 'Using <strong>WP Rocket</strong>? xSpeed Cache can import its settings in one click!' ) );
remove_filter( 'option_active_plugins', $dc_active_rocket );

add_filter(
	'option_active_plugins',
	function ( $plugins ) {
		$plugins   = (array) $plugins;
		$plugins[] = 'w3-total-cache/w3-total-cache.php';
		return $plugins;
	}
);
dc_check( 'active but not loaded (W3 Total Cache) is not named', '' === $tips::importable_cache_plugin() );

echo "\n-- \"Scan my site free\" starts an unlisted scan, and only on click --\n";
$GLOBALS['dc_xspeed_http'] = array();
$GLOBALS['dc_xspeed_reply'] = null;
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$GLOBALS['dc_xspeed_http'][] = array( 'url' => $url, 'body' => isset( $args['body'] ) ? $args['body'] : '' );
		return $GLOBALS['dc_xspeed_reply'];
	},
	10,
	3
);

dc_xspeed_render( 'sidebar' );
dc_xspeed_render( 'save' );
dc_check( 'rendering the tips sends nothing', array() === $GLOBALS['dc_xspeed_http'] );

$dc_reply = function ( $body ) {
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( $body ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};

$GLOBALS['dc_xspeed_reply'] = $dc_reply( array( 'status' => 'ok', 'scanId' => 'site-abc', 'reportUrl' => 'https://xspeedcache.com/scan/r/site-abc' ) );
$result = $tips::start_scan();
$sent   = json_decode( (string) $GLOBALS['dc_xspeed_http'][0]['body'], true );
dc_check( 'it calls the xSpeed Scan API', 'https://xspeedcache.com/api/scan' === $GLOBALS['dc_xspeed_http'][0]['url'] );
dc_check( 'it sends only the site address and "unlisted"', array( 'url', 'visibility' ) === array_keys( (array) $sent ) && home_url( '/' ) === $sent['url'] && 'unlisted' === $sent['visibility'] );
dc_check( 'it opens the scan on the experience page', $result['ok'] && 0 === strpos( $result['url'], 'https://xspeedcache.com/experience/?' ) && false !== strpos( $result['url'], 'scan=site-abc' ) && false !== strpos( $result['url'], 'start=1' ) );
$result = $tips::start_scan( 'save' );
dc_check( 'the campaign tag names the tip that was clicked', false !== strpos( $result['url'], 'utm_campaign=tip-save' ) );

$GLOBALS['dc_xspeed_reply'] = $dc_reply( array( 'error' => 'invalid_url', 'message' => 'nope' ) );
$result = $tips::start_scan();
dc_check( 'a local site falls back to the home page', ! $result['ok'] && 0 === strpos( $result['url'], 'https://xspeedcache.com/?' ) );
dc_check( 'and says why', false !== strpos( $result['message'], 'not reachable from the internet' ) );

$GLOBALS['dc_xspeed_reply'] = $dc_reply( array( 'status' => 'ok', 'reportUrl' => 'https://evil.example/scan/r/x' ) );
$result = $tips::start_scan();
dc_check( 'a report link off xspeedcache.com is never opened', ! $result['ok'] && false === strpos( $result['url'], 'evil.example' ) );

$GLOBALS['dc_xspeed_reply'] = $dc_reply( array( 'status' => 'ok', 'scanId' => 'x"><script>', 'reportUrl' => 'https://xspeedcache.com/scan/r/x' ) );
$result = $tips::start_scan();
dc_check( 'a scan id that is not a plain id is never used', ! $result['ok'] && false === strpos( $result['url'], 'script' ) );

$GLOBALS['dc_xspeed_reply'] = new WP_Error( 'http_request_failed', 'timeout' );
$result = $tips::start_scan();
dc_check( 'a network failure falls back to the home page', ! $result['ok'] && 0 === strpos( $result['url'], 'https://xspeedcache.com/?' ) );
remove_all_filters( 'pre_http_request' );

$readme = file_get_contents( dirname( dirname( __DIR__ ) ) . '/readme.txt' );
dc_check( 'the readme discloses xSpeed Scan', false !== strpos( $readme, '`https://xspeedcache.com/api/scan`' ) && false !== strpos( $readme, 'unlisted' ) );

echo "\n-- with xSpeed active, only a status line remains --\n";
define( 'XSPEED_VERSION', 'smoke' );
$card = dc_xspeed_render( 'sidebar' );
dc_check( 'the sidebar shows the status line', false !== strpos( $card, 'xSpeed Cache is active' ) );
dc_check( 'and no promotion', false === strpos( $card, 'Scan my site free' ) );
dc_check( 'no save tip', ! $tips::should_show( 'save' ) );
dc_check( 'no delete tip', ! $tips::should_show( 'delete' ) );
dc_check( 'no footer link', '' === dc_xspeed_render( 'footer' ) );

dc_smoke_finish( 'xspeed-tips' );
