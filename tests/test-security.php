<?php
/**
 * Security regression tests for the settings AJAX endpoints.
 *
 * @package Disable_Comments
 */

class DisableCommentsSecurityTestCase extends WP_Ajax_UnitTestCase {

	private $plugin;

	public function setUp() {
		parent::setUp();
		$this->plugin = Disable_Comments::get_instance();
		$_GET          = array();
		$_POST         = array();
		$_REQUEST      = array();
		http_response_code( 200 );
	}

	public function tearDown() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		http_response_code( 200 );
		parent::tearDown();
	}

	private function run_ajax( $action ) {
		$this->_last_response = '';

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $exception ) {
			// Expected: wp_send_json_* terminates the AJAX request.
		}

		return json_decode( $this->_last_response, true );
	}

	public function test_bootstrap_registers_protected_ajax_actions() {
		$this->assertNotFalse( has_action( 'wp_ajax_disable_comments_save_settings', array( $this->plugin, 'disable_comments_settings' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_disable_comments_delete_comments', array( $this->plugin, 'delete_comments_settings' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_get_sub_sites', array( $this->plugin, 'get_sub_sites' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_disable_comments_save_settings' ) );
	}

	public function test_save_rejects_invalid_nonce_with_403_error() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = 'invalid';

		$response = $this->run_ajax( 'disable_comments_save_settings' );

		$this->assertSame( 403, http_response_code() );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Nonce verification failed.', $response['data']['message'] );
	}

	public function test_delete_rejects_invalid_nonce_with_403_error() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = 'invalid';

		$response = $this->run_ajax( 'disable_comments_delete_comments' );

		$this->assertSame( 403, http_response_code() );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Nonce verification failed.', $response['data']['message'] );
	}

	public function test_subsite_listing_rejects_invalid_nonce_with_403_error() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['nonce'] = 'invalid';

		$response = $this->run_ajax( 'get_sub_sites' );

		$this->assertSame( 403, http_response_code() );
		$this->assertFalse( $response['success'] );
	}

	public function test_subsite_listing_rejects_single_site_before_multisite_apis_are_called() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site WordPress is required.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['nonce'] = wp_create_nonce( 'disable_comments_save_settings' );

		$response = $this->run_ajax( 'get_sub_sites' );

		$this->assertSame( 400, http_response_code() );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Subsite listing is only available on multisite.', $response['data']['message'] );
	}

	public function test_valid_nonce_does_not_bypass_settings_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['nonce'] = wp_create_nonce( 'disable_comments_save_settings' );

		$response = $this->run_ajax( 'disable_comments_save_settings' );

		$this->assertSame( 403, http_response_code() );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Insufficient permissions.', $response['data']['message'] );
	}

	public function test_role_options_are_json_encoded_then_attribute_escaped() {
		$template = file_get_contents( DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php' );
		$this->assertStringContainsString( 'esc_attr( wp_json_encode( $roles ) )', $template );

		add_role( 'unsafe_role', "Role '><script>alert(1)</script>\"", array( 'read' => true ) );
		$roles     = $this->plugin->get_roles( array( 'unsafe_role' ) );
		$attribute = esc_attr( wp_json_encode( $roles ) );

		$this->assertStringNotContainsString( '<script>', $attribute );
		$this->assertStringNotContainsString( "'>", $attribute );
		$this->assertSame( $roles, json_decode( html_entity_decode( $attribute, ENT_QUOTES, 'UTF-8' ), true ) );
		remove_role( 'unsafe_role' );
	}
}

/**
 * Multisite-only authorization regressions.
 *
 * @group ms-required
 */
class DisableCommentsMultisiteSecurityTestCase extends WP_Ajax_UnitTestCase {

	private $plugin;
	private $original_networkactive;
	private $original_sitewide_settings;

	public function setUp() {
		parent::setUp();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite is required.' );
		}

		$this->plugin                     = Disable_Comments::get_instance();
		$this->original_networkactive     = $this->plugin->networkactive;
		$this->original_sitewide_settings = $this->plugin->sitewide_settings;
		$this->plugin->networkactive      = true;
		$this->plugin->sitewide_settings  = '1';
		$_GET                             = array();
		$_POST                            = array();
		$_REQUEST                         = array();
		http_response_code( 200 );
	}

	public function tearDown() {
		$this->plugin->networkactive     = $this->original_networkactive;
		$this->plugin->sitewide_settings = $this->original_sitewide_settings;
		$_GET                            = array();
		$_POST                           = array();
		$_REQUEST                        = array();
		http_response_code( 200 );
		parent::tearDown();
	}

	private function run_ajax( $action ) {
		$this->_last_response = '';

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $exception ) {
			// Expected: wp_send_json_* terminates the AJAX request.
		}

		return json_decode( $this->_last_response, true );
	}

	public function test_sitewide_lock_requires_network_plugin_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['nonce'] = wp_create_nonce( 'disable_comments_save_settings' );

		$response = $this->run_ajax( 'disable_comments_save_settings' );

		$this->assertSame( 403, http_response_code() );
		$this->assertFalse( $response['success'] );
	}

	public function test_network_delete_skips_target_blog_without_manage_options() {
		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		restore_current_blog();

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_network_plugins' );
		wp_set_current_user( $user_id );

		$_GET['is_network_admin']  = '1';
		$_POST['nonce']            = wp_create_nonce( 'disable_comments_save_settings' );
		$_POST['data']             = 'delete_mode=delete_everywhere&disabled_sites%5Bsite_' . $blog_id . '%5D=1';
		$_REQUEST                  = array_merge( $_GET, $_POST );

		$response = $this->run_ajax( 'disable_comments_delete_comments' );

		$this->assertTrue( $response['success'] );
		switch_to_blog( $blog_id );
		$this->assertNotNull( get_comment( $comment_id ) );
		restore_current_blog();
	}
}
