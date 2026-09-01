<?php
/**
 * Deliberately wrong: draws the comment area without ever asking whether
 * comments are open, and without calling comments_template().
 */
?>
<!DOCTYPE html><html <?php language_attributes(); ?>><head><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php while ( have_posts() ) : the_post(); ?>
	<article><h1><?php the_title(); ?></h1><?php the_content(); ?></article>
<?php endwhile; ?>

<div id="comments">
	<form id="commentform" action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>" method="post">
		<textarea name="comment"></textarea>
		<input type="submit" value="Post Comment">
	</form>
</div>

<?php wp_footer(); ?>
</body></html>
