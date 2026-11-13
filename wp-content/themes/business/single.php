<?php get_header(); ?>

<div class="bz-container">
	<div class="bz-page-wrap">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="bz-meta">
					<span><?php echo esc_html( get_the_date() ); ?></span>
					&middot; <span>Por <?php the_author(); ?></span>
					&middot; <span><?php the_category( ', ' ); ?></span>
				</div>
				<?php if ( has_post_thumbnail() ) : ?>
					<div style="margin:1.25rem 0;border-radius:8px;overflow:hidden;"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>
				<div><?php the_content(); ?></div>
				<?php if ( get_the_tags() ) : ?>
					<p style="margin-top:1.5rem;"><strong>Etiquetas:</strong> <?php the_tags( '', ', ' ); ?></p>
				<?php endif; ?>
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
