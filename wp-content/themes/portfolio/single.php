<?php get_header(); ?>

<div class="pf-article-wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<div class="pf-article-meta">
				<span><?php echo esc_html( get_the_date() ); ?></span>
				&middot; <span>Por <?php the_author(); ?></span>
				&middot; <span><?php the_category( ', ' ); ?></span>
			</div>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="pf-article-cover"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div><?php the_content(); ?></div>

			<?php if ( get_the_tags() ) : ?>
				<p style="margin-top:1.5rem;color:#94a3b8;"><strong>Etiquetas:</strong> <?php the_tags( '', ', ' ); ?></p>
			<?php endif; ?>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
