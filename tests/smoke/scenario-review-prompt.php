<?php
/**
 * The review prompt.
 *
 * Read the history before changing anything here. Two 1-star reviews in May
 * 2025 were specifically about promotion appearing in the post editor and
 * around the admin ("This is not ok", "That is a hard no from me"). Both were
 * revised to 5 stars after it was pulled. We recovered once; we do not get to
 * make the same mistake twice.
 *
 * So most of this file asserts the prompt does NOT appear. Every condition is
 * a reason to stay silent, and each one is tested independently — a prompt
 * that is correctly suppressed for the wrong reason is one refactor away from
 * appearing everywhere.
 *
 * @package Disable_Comments
 */

require_once __DIR__ . '/helpers.php';

$dc = Disable_Comments::get_instance();

wp_set_current_user( 1 );

/**
 * Pretend we are on a given admin screen.
 *
 * should_show_review_prompt() asks get_current_screen(), so the screen has to
 * be real rather than mocked.
 *
 * @param string $id Screen id.
 */
function dc_set_screen( $id ) {
	set_current_screen( 'dashboard' );
	$screen     = get_current_screen();
	$screen->id = $id;
	$GLOBALS['current_screen'] = $screen;
}

function dc_reset_prompt_state( $dc ) {
	delete_user_meta( get_current_user_id(), Disable_Comments::REVIEW_DISMISSED_META );
	delete_option( Disable_Comments::REVIEW_TRIGGER_OPTION );
}

// ---------------------------------------------------------------------------
echo "\n-- nothing has happened yet --\n";

dc_reset_prompt_state( $dc );
dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );

dc_check( 'no prompt before any successful action', false === $dc->should_show_review_prompt() );

// Activation is not an achievement. The user has done nothing yet and has no
// basis for an opinion.
$dc->record_review_trigger( 0 );
dc_check( 'recording a zero-comment delete triggers nothing', false === $dc->should_show_review_prompt() );

// ---------------------------------------------------------------------------
echo "\n-- after a real bulk delete, on our own screen --\n";

$dc->record_review_trigger( 512 );

dc_check( 'the prompt is offered on the settings screen', true === $dc->should_show_review_prompt() );

dc_set_screen( 'tools_page_disable_comments_tools' );
dc_check( 'and on the delete-comments screen', true === $dc->should_show_review_prompt() );

// Network admin screens carry a -network suffix. Without them the super admin
// who just cleared comments across a whole network is the one person we never
// ask.
dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG . '-network' );
dc_check( 'and on the network settings screen', true === $dc->should_show_review_prompt() );

dc_set_screen( 'settings_page_disable_comments_tools-network' );
dc_check( 'and on the network delete screen', true === $dc->should_show_review_prompt() );

// A -network suffix must not smuggle in a screen that is not ours.
dc_set_screen( 'plugins-network' );
dc_check( 'but not on the network plugins screen', false === $dc->should_show_review_prompt() );

// ---------------------------------------------------------------------------
echo "\n-- never anywhere else --\n";

$forbidden = array(
	'post'                => 'the post editor',
	'page'                => 'the page editor',
	'dashboard'           => 'the dashboard',
	'plugins'             => 'the plugins screen',
	'edit-post'           => 'the posts list',
	'options-discussion'  => 'the discussion settings screen',
	'upload'              => 'the media library',
	'edit-comments'       => 'the comments screen',
);

foreach ( $forbidden as $screen_id => $label ) {
	dc_set_screen( $screen_id );
	dc_check( "no prompt on {$label}", false === $dc->should_show_review_prompt() );
}

// ---------------------------------------------------------------------------
echo "\n-- the rendered markup only appears where it should --\n";

dc_set_screen( 'post' );
ob_start();
$dc->review_prompt();
$html = ob_get_clean();
dc_check( 'review_prompt() renders nothing in the post editor', '' === trim( $html ) );

dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );
ob_start();
$dc->review_prompt();
$html = ob_get_clean();

dc_check( 'it renders on our own screen', false !== strpos( $html, 'disable_comments_review_prompt' ) );
dc_check( 'it names what actually happened', false !== strpos( $html, '512' ) );
dc_check( 'it offers a permanent dismissal', false !== strpos( $html, 'data-dc-review="dismiss"' ) );
dc_check( 'it is dismissible by WordPress too', false !== strpos( $html, 'is-dismissible' ) );
dc_check( 'the review link opens WordPress.org', false !== strpos( $html, 'wordpress.org/support/plugin/disable-comments/reviews' ) );
dc_check( 'the outbound link is rel-protected', false !== strpos( $html, 'rel="noopener noreferrer"' ) );

// ---------------------------------------------------------------------------
echo "\n-- dismissed means dismissed --\n";

update_user_meta( get_current_user_id(), Disable_Comments::REVIEW_DISMISSED_META, time() );

dc_check( 'a dismissed user is never asked again', false === $dc->should_show_review_prompt() );

// Not even after another successful delete.
$dc->record_review_trigger( 9000 );
dc_check( 'not even after another bulk delete', false === $dc->should_show_review_prompt() );

ob_start();
$dc->review_prompt();
$html = ob_get_clean();
dc_check( 'and nothing renders', '' === trim( $html ) );

// ---------------------------------------------------------------------------
echo "\n-- dismissal survives a plugin update --\n";

// Version-stamped storage would reset here. User meta does not.
update_option( 'disable_comment_version', '0.0.1' );
$dc->check_upgrades();

dc_check( 'still dismissed after check_upgrades()', false === $dc->should_show_review_prompt() );
dc_check(
	'the dismissal is stored per user, not per site',
	'' !== (string) get_user_meta( get_current_user_id(), Disable_Comments::REVIEW_DISMISSED_META, true )
);

// A different user has not been asked yet, so they still can be.
$other = wp_insert_user(
	array(
		'user_login' => 'dc-smoke-' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'administrator',
	)
);
if ( !is_wp_error( $other ) ) {
	wp_set_current_user( $other );
	dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );

	dc_check( "another admin's dismissal does not silence this one", true === $dc->should_show_review_prompt() );

	wp_set_current_user( 1 );
}

// ---------------------------------------------------------------------------
echo "\n-- capability and the kill switch --\n";

dc_reset_prompt_state( $dc );
$dc->record_review_trigger( 12 );
dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );

dc_check( 'an admin is offered the prompt', true === $dc->should_show_review_prompt() );

$subscriber = wp_insert_user(
	array(
		'user_login' => 'dc-smoke-sub-' . uniqid(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
if ( !is_wp_error( $subscriber ) ) {
	wp_set_current_user( $subscriber );
	dc_check( 'a subscriber never is', false === $dc->should_show_review_prompt() );
	wp_set_current_user( 1 );
}

add_filter( 'disable_comments_show_review_prompt', '__return_false' );
dc_check( 'the filter suppresses it entirely', false === $dc->should_show_review_prompt() );
remove_filter( 'disable_comments_show_review_prompt', '__return_false' );

// ---------------------------------------------------------------------------
echo "\n-- a real delete arms it --\n";

dc_reset_prompt_state( $dc );

$post_id = dc_make_test_post( 'post' );
for ( $i = 0; $i < 3; $i++ ) {
	wp_insert_comment(
		array(
			'comment_post_ID'  => $post_id,
			'comment_content'  => 'dc smoke ' . uniqid(),
			'comment_approved' => '1',
			'comment_author'   => 'DC Smoke',
		)
	);
}

dc_check( 'not armed before the delete', false === $dc->should_show_review_prompt() );

$dc->delete_comments_settings(
	array(
		'delete'       => true,
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);

$trigger = get_option( Disable_Comments::REVIEW_TRIGGER_OPTION );

dc_check( 'the delete armed the prompt', !empty( $trigger['at'] ) );
dc_check( 'and recorded how many it removed', 3 === (int) $trigger['deleted'] );
dc_check( 'so the prompt is now offered', true === $dc->should_show_review_prompt() );

// Allowlisting the -network screen ids achieves nothing on its own: network
// admin pages fire network_admin_notices, not admin_notices.
//
// Registration lives behind is_admin(), which is false under WP-CLI — so the
// hooks have to be driven through the real registration path with an admin
// screen in place, not just asserted on a bare CLI request. is_admin() reads
// $GLOBALS['current_screen']->in_admin(), which dc_set_screen() provides.
dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );
$dc->init_wploaded_filters();

dc_check(
	'the prompt is registered on the network notice hook',
	false !== has_action( 'network_admin_notices', array( $dc, 'review_prompt' ) )
);
dc_check(
	'and still on the single-site one',
	false !== has_action( 'admin_notices', array( $dc, 'review_prompt' ) )
);

// A delete that removes nothing must not arm it.
dc_reset_prompt_state( $dc );
$dc->delete_comments_settings(
	array(
		'delete'       => true,
		'delete_mode'  => 'selected_delete_types',
		'delete_types' => array( 'post' ),
	)
);

dc_check( 'a delete that removed nothing does not arm it', false === $dc->should_show_review_prompt() );

// ---------------------------------------------------------------------------
echo "\n-- the dismiss handler goes wherever the notice goes --\n";

// The notice renders on every screen asserted above, but its dismiss handler
// used to ship only inside the settings-page bundle, which is enqueued by hook
// suffix. Anywhere the two disagreed, "No thanks", the click-through and the
// notice's own X would all appear to work and the prompt would come back on
// the next page load.
//
// The hook suffix passed below is deliberately not one the settings bundle
// answers to: what is being asserted is that the dismiss script follows the
// NOTICE, not the screen the bundle happens to load on.
dc_reset_prompt_state( $dc );
$dc->record_review_trigger( 42 );

foreach ( array(
	'settings_page_' . DC_PLUGIN_SLUG,
	'tools_page_disable_comments_tools',
	'settings_page_' . DC_PLUGIN_SLUG . '-network',
	'settings_page_disable_comments_tools-network',
) as $prompt_screen ) {
	wp_dequeue_script( 'disable-comments-review-prompt' );
	dc_set_screen( $prompt_screen );
	$dc->settings_page_assets( 'not-the-settings-page-hook' );

	dc_check(
		"the dismiss script loads on {$prompt_screen}",
		wp_script_is( 'disable-comments-review-prompt', 'enqueued' )
	);
}

// It only depends on jQuery, so it can load on screens the settings bundle
// never touches. Depending on that bundle would drag select2, sweetalert2 and
// the pagination library onto a screen that needs none of them.
$registered = wp_scripts()->query( 'disable-comments-review-prompt', 'registered' );
dc_check(
	'and carries no dependency beyond jQuery',
	$registered && array( 'jquery' ) === $registered->deps
);

// The other half of "follows the notice": no notice, no script.
wp_dequeue_script( 'disable-comments-review-prompt' );
dc_set_screen( 'dashboard' );
$dc->settings_page_assets( 'index.php' );

dc_check(
	'but not on a screen where no prompt renders',
	false === wp_script_is( 'disable-comments-review-prompt', 'enqueued' )
);

// Nor once the user has said no, on our own screen.
wp_dequeue_script( 'disable-comments-review-prompt' );
dc_set_screen( 'settings_page_' . DC_PLUGIN_SLUG );
update_user_meta( get_current_user_id(), Disable_Comments::REVIEW_DISMISSED_META, time() );
$dc->settings_page_assets( 'not-the-settings-page-hook' );

dc_check(
	'nor for a user who already dismissed it',
	false === wp_script_is( 'disable-comments-review-prompt', 'enqueued' )
);

dc_smoke_finish( 'review-prompt' );
