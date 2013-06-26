<?php
/*
Plugin Name: Disable Comments
Plugin URI: http://wordpress.org/extend/plugins/disable-comments/
Description: Allows administrators to globally disable comments on their site. Comments can be disabled according to post type.
Version: 0.9.2
Author: Samir Shah
Author URI: http://rayofsolaris.net/
License: GPL2
Text Domain: disable-comments
Domain Path: /languages/
*/

if( !defined( 'ABSPATH' ) )
	exit;

class Disable_Comments {
	const db_version = 5;
	private $options;
	private $networkactive;
	private $modified_types = array();

	function __construct() {
		// are we network activated?
		$this->networkactive = ( is_multisite() && array_key_exists( plugin_basename( __FILE__ ), get_site_option( 'active_sitewide_plugins' ) ) );
		
		// load options
		$this->options = $this->networkactive ? get_site_option( 'disable_comments_options', array() ) : get_option( 'disable_comments_options', array() );
		
		// load language files
		load_plugin_textdomain( 'disable-comments', false, dirname( plugin_basename( __FILE__ ) ) .  '/languages' );
                
		// If it looks like first run, check compat
		if ( empty( $this->options ) && version_compare( $GLOBALS['wp_version'], '3.2', '<' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			deactivate_plugins( __FILE__ );
			if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' ) )
				exit( sprintf( __( 'Disable Comments requires WordPress version %s or greater.', 'disable-comments' ), '3.2' ) );
		}
		
		$old_ver = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;
		if( $old_ver < self::db_version ) {
			if( $old_ver < 2 ) {
				// upgrade options from version 0.2.1 or earlier to 0.3
				$this->options['disabled_post_types'] = get_option( 'disable_comments_post_types', array() );
				delete_option( 'disable_comments_post_types' );
			}
			if( $old_ver < 5 ) {
				// simple is beautiful - remove multiple settings in favour of one
				$this->options['remove_everywhere'] = isset( $this->options['remove_admin_menu_comments'] ) ? $this->options['remove_admin_menu_comments'] : false;
				foreach( array( 'remove_admin_menu_comments', 'remove_admin_bar_comments', 'remove_recent_comments', 'remove_discussion', 'remove_rc_widget' ) as $v )
					unset( $this->options[$v] );
			}

			foreach( array( 'remove_everywhere', 'permanent' ) as $v )
				if( !isset( $this->options[$v] ) )
					$this->options[$v] = false;

			$this->options['db_version'] = self::db_version;
			$this->update_options();
		}
		
		// these need to happen now
		if( $this->options['remove_everywhere'] ) {
			add_action( 'widgets_init', array( $this, 'disable_rc_widget' ) );
			add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
			add_action( 'template_redirect', array( $this, 'filter_query' ), 9 );	// before redirect_canonical
			
			// Admin bar filtering has to happen here since WP 3.6
			add_action( 'template_redirect', array( $this, 'filter_admin_bar' ) );
			add_action( 'admin_init', array( $this, 'filter_admin_bar' ) );
		}
                
		// these can happen later
		add_action( 'wp_loaded', array( $this, 'setup_filters' ) );	
	}
	
	private function update_options() {
		if( $this->networkactive )
			update_site_option( 'disable_comments_options', $this->options );
		else
			update_option( 'disable_comments_options', $this->options );
	}
	
	function setup_filters(){
		if( !empty( $this->options['disabled_post_types'] ) ) {
			foreach( $this->options['disabled_post_types'] as $type ) {
				// we need to know what native support was for later
				if( post_type_supports( $type, 'comments' ) ) {
					$this->modified_types[] = $type;
					remove_post_type_support( $type, 'comments' );
					remove_post_type_support( $type, 'trackbacks' );
				}
			}
			add_filter( 'comments_open', array( $this, 'filter_comment_status' ), 20, 2 );
			add_filter( 'pings_open', array( $this, 'filter_comment_status' ), 20, 2 );
		}
		elseif( is_admin() ) {
			add_action( 'all_admin_notices', array( $this, 'setup_notice' ) );
		}

		// Filters for the admin only
		if( is_admin() ) {
			if( $this->networkactive ) {
				add_action( 'network_admin_menu', array( $this, 'settings_menu' ) );
			}
			else {
				add_action( 'admin_menu', array( $this, 'settings_menu' ) );
				register_deactivation_hook( __FILE__, array( $this, 'single_site_deactivate' ) );
			}

			add_action( 'admin_print_footer_scripts', array( $this, 'discussion_notice' ) );
			add_filter( 'plugin_row_meta', array( $this, 'set_plugin_meta' ), 10, 2 );
			
			// if only certain types are disabled, remember the original post status
			if( !( $this->persistent_mode_allowed() && $this->options['permanent'] ) && !$this->options['remove_everywhere'] ) {
				add_action( 'edit_form_advanced', array( $this, 'edit_form_inputs' ) );
				add_action( 'edit_page_form', array( $this, 'edit_form_inputs' ) );
			}
			
			if( $this->options['remove_everywhere'] ) {
				add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 9999 );	// do this as late as possible
				add_action( 'admin_head', array( $this, 'hide_discussion_rightnow' ) );
				add_action( 'wp_dashboard_setup', array( $this, 'filter_dashboard' ) );
				add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
			}
		}
	}
	
	function filter_wp_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
	
	function filter_query() {
		if( is_comment_feed() ) {
			if( isset( $_GET['feed'] ) ) {
				wp_redirect( remove_query_arg( 'feed' ), 301 );
				exit;
			}

			set_query_var( 'feed', '' );	// redirect_canonical will do the rest
			redirect_canonical();
		}
	}
	
	function filter_admin_bar() {
		if( is_admin_bar_showing() ) {
			// Remove comments links from admin bar
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 50 );	// WP<3.3
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );	// WP 3.3
			if( $this->networkactive )
				add_action( 'admin_bar_menu', array( $this, 'remove_network_comment_links' ), 500 );
		}
	}
	
	function remove_network_comment_links( $wp_admin_bar ) {
		foreach( (array) $wp_admin_bar->user->blogs as $blog )
			$wp_admin_bar->remove_menu( 'blog-' . $blog->userblog_id . '-c' );
	}
	
	function edit_form_inputs() {
		global $post;
		// Without a dicussion meta box, comment_status will be set to closed on new/updated posts
		if( in_array( $post->post_type, $this->modified_types ) ) {
			echo '<input type="hidden" name="comment_status" value="' . $post->comment_status . '" /><input type="hidden" name="ping_status" value="' . $post->ping_status . '" />';
		}
	}
	
	function discussion_notice(){
		if( get_current_screen()->id == 'options-discussion' && !empty( $this->options['disabled_post_types'] ) ) {
			$names = array();
			foreach( $this->options['disabled_post_types'] as $type )
				$names[$type] = get_post_type_object( $type )->labels->name;
?>
<script>
jQuery(document).ready(function($){
	$(".wrap h2").first().after( <?php echo json_encode( '<div style="color: #900"><p>' . sprintf( __( 'Note: The <em>Disable Comments</em> plugin is currently active, and comments are completely disabled on: %s. Many of the settings below will not be applicable for those post types.', 'disable-comments' ), implode( __( ', ' ), $names ) ) . '</p></div>' );?> );
});
</script>
<?php
		}
	}
	
	function setup_notice(){
		if( strpos( get_current_screen()->id, 'settings_page_disable_comments_settings' ) === 0 )
			return;
		$hascaps = $this->networkactive ? is_network_admin() && current_user_can( 'manage_network_plugins' ) : current_user_can( 'manage_options' );
		$url = $this->networkactive ? network_admin_url( 'settings.php?page=disable_comments_settings' ) : admin_url( 'options-general.php?page=disable_comments_settings' );
		$url = esc_url( $url );
		if( $hascaps )
			echo '<div class="updated fade"><p>' . sprintf( __( 'The <em>Disable Comments</em> plugin is active, but isn\'t configured to do anything yet. Visit the <a href="%s">configuration page</a> to choose which post types to disable comments on.', 'disable-comments'), $url ) . '</p></div>';
	}
	
	function filter_admin_menu(){
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}
	
	function filter_dashboard(){
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}
	
	function hide_discussion_rightnow(){
		if( 'dashboard' == get_current_screen()->id )
			add_action( 'admin_print_footer_scripts', array( $this, 'discussion_js' ) );
	}
	
	function discussion_js(){
		// getting hold of the discussion box is tricky. The table_discussion class is used for other things in multisite
		echo '<script> jQuery(document).ready(function($){ $("#dashboard_right_now .table_discussion").has(\'a[href="edit-comments.php"]\').first().hide(); }); </script>';
	}
	
	function filter_comment_status( $open, $post_id ) {
		$post = get_post( $post_id );
		return ( $this->options['remove_everywhere'] || in_array( $post->post_type, $this->options['disabled_post_types'] ) ) ? false : $open;
	}
	
	function disable_rc_widget() {
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}
	
	function set_plugin_meta( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			return array_merge(
				$links,
				array( '<a href="https://github.com/solarissmoke/disable-comments">GitHub-Repo</a>' )
			);
		}
		return $links;
	}

	function settings_menu() {
		$title = __( 'Disable Comments', 'disable-comments' );
		if( $this->networkactive )
			add_submenu_page( 'settings.php', $title, $title, 'manage_network_plugins', 'disable_comments_settings', array( $this, 'settings_page' ) );
		else
			add_submenu_page( 'options-general.php', $title, $title, 'manage_options', 'disable_comments_settings', array( $this, 'settings_page' ) );
	}
	
	function settings_page() {
		$typeargs = array( 'public' => true );
		if( $this->networkactive )
			$typeargs['_builtin'] = true;	// stick to known types for network
		$types = get_post_types( $typeargs, 'objects' );
		foreach( array_keys( $types ) as $type ) {
			if( ! in_array( $type, $this->modified_types ) && ! post_type_supports( $type, 'comments' ) )	// the type doesn't support comments anyway
				unset( $types[$type] );
		}

		$persistent_allowed = $this->persistent_mode_allowed();
		
		if ( isset( $_POST['submit'] ) ) {
			$this->options['remove_everywhere'] = ( $_POST['mode'] == 'remove_everywhere' );
			
			if( $this->options['remove_everywhere'] )
				$disabled_post_types = array_keys( $types );
			else
				$disabled_post_types =  empty( $_POST['disabled_types'] ) ? array() : (array) $_POST['disabled_types'];

			$disabled_post_types = array_intersect( $disabled_post_types, array_keys( $types ) );
			
			// entering permanent mode, or post types have changed
			if( $persistent_allowed && !empty( $_POST['permanent'] ) && ( !$this->options['permanent'] || $disabled_post_types != $this->options['disabled_post_types'] ) )
				$this->enter_permanent_mode();
			
			$this->options['disabled_post_types'] = $disabled_post_types;
			$this->options['permanent'] = $persistent_allowed && isset( $_POST['permanent'] );
			
			$this->update_options();
			$cache_message = WP_CACHE ? ' <strong>' . __( 'If a caching/performance plugin is active, please invalidate its cache to ensure that changes are reflected immediately.' ) . '</strong>' : '';
			echo '<div id="message" class="updated"><p>' . __( 'Options updated. Changes to the Admin Menu and Admin Bar will not appear until you leave or reload this page.', 'disable-comments' ) . $cache_message . '</p></div>';
		}	
	?>
	<style> .indent {padding-left: 2em} </style>
	<div class="wrap">
	<?php screen_icon( 'plugins' ); ?>
	<h2><?php _e( 'Disable Comments', 'disable-comments') ?></h2>
	<?php 
	if( $this->networkactive ) 
		echo '<div class="updated"><p>' . __( '<em>Disable Comments</em> is Network Activated. The settings below will affect <strong>all sites</strong> in this network.', 'disable-comments') . '</p></div>';
	if( WP_CACHE )
		echo '<div class="updated"><p>' . __( "It seems that a caching/performance plugin is active on this site. Please manually invalidate that plugin's cache after making any changes to the settings below.", 'disable-comments') . '</p></div>';
	?>
	<form action="" method="post" id="disable-comments">
	<ul>
	<li><label for="remove_everywhere"><input type="radio" id="remove_everywhere" name="mode" value="remove_everywhere" <?php checked( $this->options['remove_everywhere'] );?> /> <strong><?php _e( 'Everywhere', 'disable-comments') ?></strong>: <?php _e( 'Disable all comment-related controls and settings in WordPress.', 'disable-comments') ?></label>
		<p class="indent"><?php printf( __( '%s: This option is global and will affect your entire site. Use it only if you want to disable comments <em>everywhere</em>. A complete description of what this option does is <a href="%s" target="_blank">available here</a>.', 'disable-comments' ), '<strong style="color: #900">' . __('Warning', 'disable-comments') . '</strong>', 'http://wordpress.org/extend/plugins/disable-comments/other_notes/' ); ?></p>
	</li>
	<li><label for="selected_types"><input type="radio" id="selected_types" name="mode" value="selected_types" <?php checked( ! $this->options['remove_everywhere'] );?> /> <strong><?php _e( 'On certain post types', 'disable-comments') ?></strong></label>:
		<p></p>
		<ul class="indent" id="listoftypes">
			<?php foreach( $types as $k => $v ) echo "<li><label for='post-type-$k'><input type='checkbox' name='disabled_types[]' value='$k' ". checked( in_array( $k, $this->options['disabled_post_types'] ), true, false ) ." id='post-type-$k'> {$v->labels->name}</label></li>";?>
		</ul>
		<p class="indent"><?php _e( 'Disabling comments will also disable trackbacks and pingbacks. All comment-related fields will also be hidden from the edit/quick-edit screens of the affected posts. These settings cannot be overridden for individual posts.', 'disable-comments') ?></p>
	</li>
	</ul>
	<h3><?php _e( 'Other options', 'disable-comments') ?></h3>
	<ul>
		<li>
		<?php
		if( $persistent_allowed ) {
			echo '<label for="permanent"><input type="checkbox" name="permanent" id="permanent" '. checked( $this->options['permanent'], true, false ) . '> <strong>' . __( 'Use persistent mode', 'disable-comments') . '</strong></label>';
			echo '<p class="indent">' . sprintf( __( '%s: <strong>This will make persistent changes to your database &mdash; comments will remain closed even if you later disable the plugin!</strong> You should not use it if you only want to disable comments temporarily. Please <a href="%s" target="_blank">read the FAQ</a> before selecting this option.', 'disable-comments'), '<strong style="color: #900">' . __('Warning', 'disable-comments') . '</strong>', 'http://wordpress.org/extend/plugins/disable-comments/faq/' ) . '</p>';
			if( $this->networkactive )
				echo '<p class="indent">' . sprintf( __( '%s: Entering persistent mode on large multi-site networks requires a large number of database queries and can take a while. Use with caution!', 'disable-comments'), '<strong>' . __('Warning', 'disable-comments') . '</strong>' ) . '</p>';
		}
		else {
			printf( __( 'Persistent mode has been manually disabled. See the <a href="%s" target="_blank">FAQ</a> for more information.', 'disable-comments' ), 'http://wordpress.org/extend/plugins/disable-comments/faq/' );
		}
		?>
		</li>
	</ul>
	<p class="submit"><input class="button-primary" type="submit" name="submit" value="<?php _e( 'Save Changes') ?>"></p>
	</form>
	</div>
	<script>
	jQuery(document).ready(function($){
		function disable_comments_uihelper(){
			if( $("#remove_everywhere").is(":checked") )
				$("#listoftypes").css("color", "#888").find(":input").attr("disabled", true );
			else
				$("#listoftypes").css("color", "#000").find(":input").attr("disabled", false );
		}
		
		$("#disable-comments :input").change(function(){
			$("#message").slideUp();
			disable_comments_uihelper();
		});
		
		disable_comments_uihelper();
		
		$("#permanent").change( function() {
			if( $(this).is(":checked") && ! confirm(<?php echo json_encode( sprintf( __( '%s: Selecting this option will make persistent changes to your database. Are you sure you want to enable it?', 'disable-comments'), __( 'Warning', 'disable-comments' ) ) );?>) )
				$(this).attr("checked", false );
		});
	});
	</script>
<?php
	}
	
	private function enter_permanent_mode() {
		$types = $this->options['disabled_post_types'];
		if( empty( $types ) )
			return;
			
		global $wpdb;

		if( $this->networkactive ) {
			// NOTE: this can be slow on large networks!
			$blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND deleted = '0'", $wpdb->siteid ) );
	
			foreach ( $blogs as $id ) {
				switch_to_blog( $id );
				$this->close_comments_in_db( $types );
				restore_current_blog();
			}
		}
		else {
			$this->close_comments_in_db( $types );
		}
	}
	
	private function close_comments_in_db( $types ){
		global $wpdb;
		$bits = implode( ', ', array_pad( array(), count( $types ), '%s' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE `$wpdb->posts` SET `comment_status` = 'closed', ping_status = 'closed' WHERE `post_type` IN ( $bits )", $types ) );
	}
	
	private function persistent_mode_allowed() {
		return apply_filters( 'disable_comments_allow_persistent_mode', true );
	}

	function single_site_deactivate() {
		// for single sites, delete the options upon deactivation, not uninstall
		delete_option( 'disable_comments_options' );
	}
}

new Disable_Comments();
