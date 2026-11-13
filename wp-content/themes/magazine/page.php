<?php get_header(); ?>

<div class="mg-article-box">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1 class="mg-single-title"><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="mg-single-cover"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>
			<div class="mg-content"><?php the_content(); ?></div>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
