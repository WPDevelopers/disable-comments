<?php ?>
<!DOCTYPE html><html <?php language_attributes(); ?>><head><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php while ( have_posts() ) : the_post(); ?>
	<article><h1><?php the_title(); ?></h1><?php the_content(); ?></article>
	<?php comments_template(); ?>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body></html>
