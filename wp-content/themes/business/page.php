<?php get_header(); ?>

<div class="bz-container">
	<div class="bz-page-wrap">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<?php if ( has_post_thumbnail() ) : ?>
					<div style="margin:1.25rem 0;border-radius:8px;overflow:hidden;"><?php the_post_thumbnail( 'large' ); ?></div>
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
</div>

<?php get_footer(); ?>
