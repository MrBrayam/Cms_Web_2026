<?php get_header(); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<div class="mb-meta">
			<?php
			$cats = get_the_category();
			if ( ! empty( $cats ) ) :
				foreach ( $cats as $cat ) :
					?>
					<a class="mb-cat-badge" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
						<?php echo esc_html( $cat->name ); ?>
					</a>
					<?php
				endforeach;
			endif;
			?>
			<span><?php echo esc_html( get_the_date() ); ?></span>
			<span>&middot;</span>
			<span>Por <?php the_author(); ?></span>
			<span>&middot;</span>
			<span><?php echo esc_html( minimal_blog_reading_time() ); ?></span>
		</div>

		<h1 class="mb-single-title"><?php the_title(); ?></h1>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="mb-featured-img">
				<?php the_post_thumbnail( 'large' ); ?>
			</div>
		<?php endif; ?>

		<div class="mb-content">
			<?php the_content(); ?>
		</div>

		<?php
		$tags = get_the_tags();
		if ( $tags ) :
			?>
			<div class="mb-tags">
				<?php foreach ( $tags as $tag ) : ?>
					<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>
<?php endwhile; ?>

<?php get_footer(); ?>
