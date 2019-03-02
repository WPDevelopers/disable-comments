<?php
use PHPUnit_Framework_TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class RemoveEveryWhereTestCase extends WP_UnitTestCase {

    function setUp() {
        parent::setUp();
		update_option( 'disable_comments_options', array(
			'db_version' => Disable_Comments::DB_VERSION,
			'remove_everywhere' => true,
			'disabled_post_types' => array( 'post', 'page', 'attachment' )
		) );
		$this->plugin_instance = new Disable_Comments();
    }

    function tearDown()
    {
        Monkey::tearDown();
        parent::tearDown();
    }

	function test_init_hooks_added() {
		$this->assertEquals( 10,  has_action( 'widgets_init', array( $this->plugin_instance, 'disable_rc_widget' ) ) );
		$this->assertEquals( 10,  has_action( 'wp_headers', array( $this->plugin_instance, 'filter_wp_headers' ) ) );
		$this->assertEquals( 9,  has_action( 'template_redirect', array( $this->plugin_instance, 'filter_query' ) ) );
		$this->assertEquals( 10,  has_action( 'template_redirect', array( $this->plugin_instance, 'filter_admin_bar' ) ) );
		$this->assertEquals( 10,  has_action( 'admin_init', array( $this->plugin_instance, 'filter_admin_bar' ) ) );
		$this->assertEquals( 10,  has_action( 'plugins_loaded', array( $this->plugin_instance, 'register_text_domain' ) ) );
		$this->assertEquals( 10,  has_action( 'wp_loaded', array( $this->plugin_instance, 'init_wploaded_filters' ) ) );
	}

	function test_wp_loaded_actions() {
		$this->plugin_instance->init_wploaded_filters();
		$this->assertEquals( 20,  has_action( 'comments_array', array( $this->plugin_instance, 'filter_existing_comments' ) ) );
		$this->assertEquals( 20,  has_action( 'comments_open', array( $this->plugin_instance, 'filter_comment_status' ) ) );
		$this->assertEquals( 20,  has_action( 'pings_open', array( $this->plugin_instance, 'filter_comment_status' ) ) );
		$this->assertEquals( 20,  has_action( 'get_comments_number', array( $this->plugin_instance, 'filter_comments_number' ) ) );

		// Check that comment suport has been removed from all the post types
		$this->assertFalse( post_type_supports( 'post', 'comments' ) );
		$this->assertFalse( post_type_supports( 'page', 'comments' ) );
		$this->assertFalse( post_type_supports( 'attachment', 'comments' ) );
	}

    function test_wp_loaded_admin_actions() {
        Functions::when( 'is_admin' )->justReturn(true);
		$this->plugin_instance->init_wploaded_filters();

        $this->assertEquals( 10,  has_action( 'admin_print_styles-index.php', array( $this->plugin_instance, 'admin_css' ) ) );
        $this->assertEquals( 10,  has_action( 'admin_print_styles-profile.php', array( $this->plugin_instance, 'admin_css' ) ) );
	}

    function test_widget_init_actions() {
        $this->plugin_instance->disable_rc_widget();
        $this->assertEquals( 10,  has_filter( 'show_recent_comments_widget_style', '__return_false' ) );
    }

	function test_comment_template_filter() {
		Functions::when( 'is_singular' )->justReturn(true);
		Functions::when( 'wp_deregister_script' )->justReturn(true);
		$this->plugin_instance->check_comment_template();
		# Check that this action was removed
		$this->assertFalse( has_action( 'wp_head', 'feed_links_extra' ) );
	}

	function test_filter_headers() {
		$input = array( 'X-Pingback' => 'http://example.com' );
		$output = $this->plugin_instance->filter_wp_headers($input);
		$this->assertEmpty($output);
	}

	function test_comment_feed_403() {
		Functions::when( 'is_comment_feed' )->justReturn(true);
		Functions::expect( 'wp_die' )->once();
		$this->plugin_instance->filter_query();
	}

	function test_admin_bar_filter() {
		Functions::when( 'is_admin_bar_showing' )->justReturn(true);
		add_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		$this->plugin_instance->filter_admin_bar();
		$this->assertFalse( has_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu' ) );
	}

	function test_filter_existing_comments() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_existing_comments( array( 'comment1', 'comment2', 'comment3' ), $post_id );
		$this->assertEmpty($output);
	}

	function test_filter_comment_status() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_comment_status( 'open', $post_id );
		$this->assertFalse($output);
	}

	function test_filter_comments_number() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_comments_number( 5, $post_id );
		$this->assertEquals(0, $output);
	}
}


class RemoveIndividualTestCase extends WP_UnitTestCase {

    function setUp() {
        parent::setUp();
		$this->reset_post_types();
		update_option( 'disable_comments_options', array(
			'db_version' => Disable_Comments::DB_VERSION,
			'remove_everywhere' => false,
			'disabled_post_types' => array('post')
		) );
		$this->plugin_instance = new Disable_Comments();
    }

	function test_init_hooks_added() {
		$this->assertEquals( 10,  has_action( 'plugins_loaded', array( $this->plugin_instance, 'register_text_domain' ) ) );
		$this->assertEquals( 10,  has_action( 'wp_loaded', array( $this->plugin_instance, 'init_wploaded_filters' ) ) );
	}

	function test_wp_loaded_actions() {
		$this->plugin_instance->init_wploaded_filters();
		$this->assertEquals( 20,  has_action( 'comments_array', array( $this->plugin_instance, 'filter_existing_comments' ) ) );
		$this->assertEquals( 20,  has_action( 'comments_open', array( $this->plugin_instance, 'filter_comment_status' ) ) );
		$this->assertEquals( 20,  has_action( 'pings_open', array( $this->plugin_instance, 'filter_comment_status' ) ) );
		$this->assertEquals( 20,  has_action( 'get_comments_number', array( $this->plugin_instance, 'filter_comments_number' ) ) );

		// Check that comment suport has been removed only from posts
		$this->assertFalse( post_type_supports( 'post', 'comments' ) );
		$this->assertTrue( post_type_supports( 'page', 'comments' ) );
	}

	function test_filter_existing_comments_on_disabled_type() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_existing_comments( array( 'comment1', 'comment2', 'comment3' ), $post_id );
		$this->assertEmpty($output);
	}

	function test_filter_existing_comments_on_enabled_type() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$output = $this->plugin_instance->filter_existing_comments( array( 'comment1', 'comment2', 'comment3' ), $post_id );
		$this->assertEquals(array( 'comment1', 'comment2', 'comment3' ), $output);
	}

	function test_filter_comment_status_on_disabled_type() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_comment_status( 'open', $post_id );
		$this->assertFalse( $output );
	}

	function test_filter_comment_status_on_enabled_type() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$output = $this->plugin_instance->filter_comment_status( 'open', $post_id );
		$this->assertEquals( 'open', $output );
	}

	function test_filter_comments_number_on_disabled_type() {
		$post_id = $this->factory->post->create();
		$output = $this->plugin_instance->filter_comments_number( 5, $post_id );
		$this->assertEquals(0, $output);
	}

	function test_filter_comments_number_on_enabled_type() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$output = $this->plugin_instance->filter_comments_number( 5, $post_id );
		$this->assertEquals(5, $output);
	}
}
