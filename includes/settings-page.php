<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$typeargs = array( 'public' => true );
if ( $this->networkactive ) {
	$typeargs['_builtin'] = true;   // stick to known types for network
}
$types = get_post_types( $typeargs, 'objects' );
foreach ( array_keys( $types ) as $type ) {
	if ( ! in_array( $type, $this->modified_types ) && ! post_type_supports( $type, 'comments' ) ) {   // the type doesn't support comments anyway
		unset( $types[ $type ] );
	}
}

if ( isset( $_POST['submit'] ) ) {
	check_admin_referer( 'disable-comments-admin' );
	$this->options['remove_everywhere'] = ( $_POST['mode'] == 'remove_everywhere' );

	if ( $this->options['remove_everywhere'] ) {
		$disabled_post_types = array_keys( $types );
	} else {
		$disabled_post_types = empty( $_POST['disabled_types'] ) ? array() : (array) $_POST['disabled_types'];
	}

	$disabled_post_types = array_intersect( $disabled_post_types, array_keys( $types ) );

	$this->options['disabled_post_types'] = $disabled_post_types;

	// Extra custom post types
	if ( $this->networkactive && ! empty( $_POST['extra_post_types'] ) ) {
		$extra_post_types                  = array_filter( array_map( 'sanitize_key', explode( ',', $_POST['extra_post_types'] ) ) );
		$this->options['extra_post_types'] = array_diff( $extra_post_types, array_keys( $types ) ); // Make sure we don't double up builtins
	}

	$this->update_options();
	$cache_message = WP_CACHE ? ' <strong>' . __( 'If a caching/performance plugin is active, please invalidate its cache to ensure that changes are reflected immediately.' ) . '</strong>' : '';
	echo '<div id="message" class="updated"><p>' . __( 'Options updated. Changes to the Admin Menu and Admin Bar will not appear until you leave or reload this page.', 'disable-comments' ) . $cache_message . '</p></div>';
}
?>
<style> .indent {padding-left: 2em} </style>
<div class="wrap">
<h1><?php _ex( 'Disable Comments', 'settings page title', 'disable-comments' ); ?></h1>
<?php
if ( $this->networkactive ) {
	echo '<div class="updated"><p>' . __( '<em>Disable Comments</em> is Network Activated. The settings below will affect <strong>all sites</strong> in this network.', 'disable-comments' ) . '</p></div>';
}
if ( WP_CACHE ) {
	echo '<div class="updated"><p>' . __( "It seems that a caching/performance plugin is active on this site. Please manually invalidate that plugin's cache after making any changes to the settings below.", 'disable-comments' ) . '</p></div>';
}
?>
<form action="" method="post" id="disable-comments">
<ul>
<li><label for="remove_everywhere"><input type="radio" id="remove_everywhere" name="mode" value="remove_everywhere" <?php checked( $this->options['remove_everywhere'] ); ?> /> <strong><?php _e( 'Everywhere', 'disable-comments' ); ?></strong>: <?php _e( 'Disable all comment-related controls and settings in WordPress.', 'disable-comments' ); ?></label>
	<p class="indent"><?php printf( __( '%1$s: This option is global and will affect your entire site. Use it only if you want to disable comments <em>everywhere</em>. A complete description of what this option does is <a href="%2$s" target="_blank">available here</a>.', 'disable-comments' ), '<strong style="color: #900">' . __( 'Warning', 'disable-comments' ) . '</strong>', 'https://wordpress.org/plugins/disable-comments/other_notes/' ); ?></p>
</li>
<li><label for="selected_types"><input type="radio" id="selected_types" name="mode" value="selected_types" <?php checked( ! $this->options['remove_everywhere'] ); ?> /> <strong><?php _e( 'On certain post types', 'disable-comments' ); ?></strong>:</label>
	<p></p>
	<ul class="indent" id="listoftypes">
		<?php
		foreach ( $types as $k => $v ) {
			echo "<li><label for='post-type-$k'><input type='checkbox' name='disabled_types[]' value='$k' " . checked( in_array( $k, $this->options['disabled_post_types'] ), true, false ) . " id='post-type-$k'> {$v->labels->name}</label></li>";}
		?>
	</ul>
	<?php if ( $this->networkactive ) : ?>
	<p class="indent" id="extratypes"><?php _e( 'Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments' ); ?>
	<br /><label><?php _e( 'Custom post types:', 'disable-comments' ); ?> <input type="text" name="extra_post_types" size="30" value="<?php echo implode( ', ', (array) $this->options['extra_post_types'] ); ?>" /></label></p>
	<?php endif; ?>
	<p class="indent"><?php _e( 'Disabling comments will also disable trackbacks and pingbacks. All comment-related fields will also be hidden from the edit/quick-edit screens of the affected posts. These settings cannot be overridden for individual posts.', 'disable-comments' ); ?></p>
</li>
</ul>

<?php wp_nonce_field( 'disable-comments-admin' ); ?>
<p class="submit"><input class="button-primary" type="submit" name="submit" value="<?php _e( 'Save Changes', 'disable-comments' ); ?>"></p>
</form>
</div>
<script>
jQuery(document).ready(function($){
	function disable_comments_uihelper(){
		var indiv_bits = $("#listoftypes, #extratypes");
		if( $("#remove_everywhere").is(":checked") )
			indiv_bits.css("color", "#888").find(":input").attr("disabled", true );
		else
			indiv_bits.css("color", "#000").find(":input").attr("disabled", false );
	}

	$("#disable-comments :input").change(function(){
		$("#message").slideUp();
		disable_comments_uihelper();
	});

	disable_comments_uihelper();
});
</script>
