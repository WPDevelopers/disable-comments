<?php
/**
 * Tools page.
 *
 * @package Disable_Comments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
<h1><?php _e( 'Delete Comments', 'disable-comments' ); ?></h1>
<?php

global $wpdb;
$comments_count = $wpdb->get_var( "SELECT count(comment_id) from $wpdb->comments" );
if ( $comments_count <= 0 ) {
	?>
<p><strong><?php _e( 'No comments available for deletion.', 'disable-comments' ); ?></strong></p>
</div>
	<?php
	return;
}

$publictypeargs = array( 'public' => true );
$privatetypeargs = array( 'public' => false );
if ( $this->networkactive ) {
	$typeargs['_builtin'] = true;   // stick to known types for network.
}
$publictypes = get_post_types( $publictypeargs, 'objects' );
$privatetypes = get_post_types( $privatetypeargs, 'objects' );
$types = array_merge( $publictypes, $privatetypes );
foreach ( array_keys( $types ) as $type ) {
	if ( ! in_array( $type, $this->modified_types ) && ! post_type_supports( $type, 'comments' ) ) {   // the type doesn't support comments anyway.
		unset( $types[ $type ] );
	}
}
$commenttypes = array();
$commenttypes_query = $wpdb->get_results( "SELECT DISTINCT comment_type FROM $wpdb->comments", ARRAY_A );
if ( ! empty( $commenttypes_query ) && is_array( $commenttypes_query ) ) {
	foreach ( $commenttypes_query as $entry ) {
		$value = $entry['comment_type'];
		if ( '' === $value ) {
			$commenttypes['default'] = __( 'Default (no type)', 'disable-comments' );
		} else {
			$commenttypes[$value] = ucwords( str_replace( '_', ' ', $value ) ) . ' (' . $value . ')';
		}
	}
}

if ( isset( $_POST['delete'] ) && isset( $_POST['delete_mode'] ) ) {
	check_admin_referer( 'delete-comments-admin' );

	if ( $_POST['delete_mode'] == 'delete_everywhere' ) {
		if ( $wpdb->query( "TRUNCATE $wpdb->commentmeta" ) != false ) {
			if ( $wpdb->query( "TRUNCATE $wpdb->comments" ) != false ) {
				$wpdb->query( "UPDATE $wpdb->posts SET comment_count = 0" );
				$wpdb->query( "OPTIMIZE TABLE $wpdb->commentmeta" );
				$wpdb->query( "OPTIMIZE TABLE $wpdb->comments" );
				echo "<p style='color:green'><strong>" . __( 'All comments have been deleted.', 'disable-comments' ) . '</strong></p>';
			} else {
				echo "<p style='color:red'><strong>" . __( 'Internal error occured. Please try again later.', 'disable-comments' ) . '</strong></p>';
			}
		} else {
			echo "<p style='color:red'><strong>" . __( 'Internal error occured. Please try again later.', 'disable-comments' ) . '</strong></p>';
		}
	} elseif ( $_POST['delete_mode'] == 'selected_delete_types' ) {
		$delete_post_types = empty( $_POST['delete_types'] ) ? array() : (array) $_POST['delete_types'];
		$delete_post_types = array_intersect( $delete_post_types, array_keys( $types ) );

		// Extra custom post types.
		if ( $this->networkactive && ! empty( $_POST['delete_extra_post_types'] ) ) {
			$delete_extra_post_types = array_filter( array_map( 'sanitize_key', explode( ',', $_POST['delete_extra_post_types'] ) ) );
			$delete_extra_post_types = array_diff( $delete_extra_post_types, array_keys( $types ) );    // Make sure we don't double up builtins.
			$delete_post_types       = array_merge( $delete_post_types, $delete_extra_post_types );
		}

		if ( ! empty( $delete_post_types ) ) {
			// Loop through post_types and remove comments/meta and set posts comment_count to 0.
			foreach ( $delete_post_types as $delete_post_type ) {
				$wpdb->query( "DELETE cmeta FROM $wpdb->commentmeta cmeta INNER JOIN $wpdb->comments comments ON cmeta.comment_id=comments.comment_ID INNER JOIN $wpdb->posts posts ON comments.comment_post_ID=posts.ID WHERE posts.post_type = '$delete_post_type'" );
				$wpdb->query( "DELETE comments FROM $wpdb->comments comments INNER JOIN $wpdb->posts posts ON comments.comment_post_ID=posts.ID WHERE posts.post_type = '$delete_post_type'" );
				$wpdb->query( "UPDATE $wpdb->posts SET comment_count = 0 WHERE post_author != 0 AND post_type = '$delete_post_type'" );

				$post_type_object = get_post_type_object( $delete_post_type );
				$post_type_label  = $post_type_object ? $post_type_object->labels->name : $delete_post_type;
				echo "<p style='color:green'><strong>" . sprintf( __( 'All comments have been deleted for %s.', 'disable-comments' ), $post_type_label ) . '</strong></p>';
			}

			$wpdb->query( "OPTIMIZE TABLE $wpdb->commentmeta" );
			$wpdb->query( "OPTIMIZE TABLE $wpdb->comments" );

			echo "<h4 style='color:green'><strong>" . __( 'Comment Deletion Complete', 'disable-comments' ) . '</strong></h4>';
		}
	} elseif ( $_POST['delete_mode'] == 'selected_delete_comment_types' ) {
		$delete_comment_types = empty( $_POST['delete_comment_types'] ) ? array() : (array) $_POST['delete_comment_types'];
		$delete_comment_types = array_intersect( $delete_comment_types, array_keys( $commenttypes ) );

		if ( ! empty( $delete_comment_types ) ) {
			// Loop through comment_types and remove comments/meta and set posts comment_count to 0.
			foreach ( $delete_comment_types as $delete_comment_type ) {
				$wpdb->query( "DELETE cmeta FROM $wpdb->commentmeta cmeta INNER JOIN $wpdb->comments comments ON cmeta.comment_id=comments.comment_ID WHERE comments.comment_type = '$delete_comment_type'" );
				$wpdb->query( "DELETE comments FROM $wpdb->comments comments  WHERE comments.comment_type = '$delete_comment_type'" );
				
				echo "<p style='color:green'><strong>" . sprintf( __( 'All comments have been deleted for %s.', 'disable-comments' ), $commenttypes[$delete_comment_type] ) . '</strong></p>';
			}

			// Update comment_count on post_types
			foreach( $types as $key => $value ) {
				$comment_count = $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM $wpdb->comments comments INNER JOIN $wpdb->posts posts ON comments.comment_post_ID=posts.ID WHERE posts.post_type = '$key'" );
				$wpdb->query( "UPDATE $wpdb->posts SET comment_count = $comment_count WHERE post_author != 0 AND post_type = '$key'" );
			}

			$wpdb->query( "OPTIMIZE TABLE $wpdb->commentmeta" );
			$wpdb->query( "OPTIMIZE TABLE $wpdb->comments" );

			echo "<h4 style='color:green'><strong>" . __( 'Comment Deletion Complete', 'disable-comments' ) . '</strong></h4>';
		}		
	}

	$comments_count = $wpdb->get_var( "SELECT count(comment_id) from $wpdb->comments" );
	if ( $comments_count <= 0 ) {
		?>
		<p><strong><?php _e( 'No comments available for deletion.', 'disable-comments' ); ?></strong></p>
		</div>
		<?php
		return;
	}
}
?>
<form action="" method="post" id="delete-comments">
<ul>
<li><label for="delete_everywhere"><input type="radio" id="delete_everywhere" name="delete_mode" value="delete_everywhere" <?php checked( $this->options['remove_everywhere'] ); ?> /> <strong><?php _e( 'Everywhere', 'disable-comments' ); ?></strong>: <?php _e( 'Delete all comments in WordPress.', 'disable-comments' ); ?></label>
	<p class="indent"><?php printf( __( '%s: This function and will affect your entire site. Use it only if you want to delete comments <em>everywhere</em>.', 'disable-comments' ), '<strong style="color: #900">' . __( 'Warning', 'disable-comments' ) . '</strong>' ); ?></p>
</li>
<li><label for="selected_delete_types"><input type="radio" id="selected_delete_types" name="delete_mode" value="selected_delete_types" <?php checked( ! $this->options['remove_everywhere'] ); ?> /> <strong><?php _e( 'For certain post types', 'disable-comments' ); ?></strong>:</label>
	<p></p>
	<ul class="indent" id="listofdeletetypes">
		<?php
		foreach ( $types as $k => $v ) {
			echo "<li><label for='post-type-$k'><input type='checkbox' name='delete_types[]' value='$k' " . checked( in_array( $k, $this->options['disabled_post_types'] ), true, false ) . " id='post-type-$k'> {$v->labels->name}</label></li>";}
		?>
	</ul>
	<?php if ( $this->networkactive ) : ?>
	<p class="indent" id="extradeletetypes"><?php _e( 'Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments' ); ?>
	<br /><label><?php _e( 'Custom post types:', 'disable-comments' ); ?> <input type="text" name="delete_extra_post_types" size="30" value="<?php echo implode( ', ', (array) $this->options['extra_post_types'] ); ?>" /></label></p>
	<?php endif; ?>
	<p class="indent"><?php printf( __( '%s: Deleting comments by post type will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backup.', 'disable-comments' ), '<strong style="color: #900">' . __( 'Warning', 'disable-comments' ) . '</strong>' ); ?></p>
</li>
<?php if ( ! empty( $commenttypes ) ) : ?>
<li><label for="selected_delete_comment_types"><input type="radio" id="selected_delete_comment_types" name="delete_mode" value="selected_delete_comment_types" /> <strong><?php _e( 'For certain comment types', 'disable-comments' ); ?></strong>:</label>
	<p></p>
	<ul class="indent" id="listofdeletecommenttypes">
		<?php
		foreach ( $commenttypes as $k => $v ) {
			echo "<li><label for='comment-type-$k'><input type='checkbox' name='delete_comment_types[]' value='$k' id='comment-type-$k'> {$v}</label></li>";}
		?>
	</ul>
	<p class="indent"><?php printf( __( '%s: Deleting comments by comment type will remove existing comment entries of the selected comment type(s) in the database and cannot be reverted without a database backup.', 'disable-comments' ), '<strong style="color: #900">' . __( 'Warning', 'disable-comments' ) . '</strong>' ); ?></p>
</li>
<?php endif; ?>
</ul>

<?php wp_nonce_field( 'delete-comments-admin' ); ?>
<h4><?php _e( 'Total Comments:', 'disable-comments' ); ?> <?php echo $comments_count; ?></h4>
<p class="submit"><input class="button-primary" type="submit" name="delete" value="<?php _e( 'Delete Comments', 'disable-comments' ); ?>"></p>
</form>
</div>
<script>
jQuery(document).ready(function($){
	function delete_comments_uihelper(){
		var toggle_pt_bits = $("#listofdeletetypes, #extradeletetypes");
		var toggle_ct_bits = $("#listofdeletecommenttypes");
		if( $("#delete_everywhere").is(":checked") ) {
			toggle_pt_bits.css("color", "#888").find(":input").attr("disabled", true );
			toggle_ct_bits.css("color", "#888").find(":input").attr("disabled", true );
		} else {
			if( $("#selected_delete_types").is(":checked") ) {
				toggle_pt_bits.css("color", "#000").find(":input").attr("disabled", false );
				toggle_ct_bits.css("color", "#888").find(":input").attr("disabled", true );
			} else {
				toggle_ct_bits.css("color", "#000").find(":input").attr("disabled", false );
				toggle_pt_bits.css("color", "#888").find(":input").attr("disabled", true );
			}
		}
	}

	$("#delete-comments :input").change(function(){
		delete_comments_uihelper();
	});

	delete_comments_uihelper();
});
</script>
