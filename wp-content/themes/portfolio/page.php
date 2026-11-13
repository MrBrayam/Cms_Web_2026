<?php get_header(); ?>

<div class="pf-article-wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="pf-article-cover"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>
			<div><?php the_content(); ?></div>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
