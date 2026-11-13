<?php get_header(); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<h1 class="mb-single-title"><?php the_title(); ?></h1>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="mb-featured-img">
				<?php the_post_thumbnail( 'large' ); ?>
			</div>
		<?php endif; ?>

		<div class="mb-content">
			<?php the_content(); ?>
		</div>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>
<?php endwhile; ?>

<?php get_footer(); ?>
