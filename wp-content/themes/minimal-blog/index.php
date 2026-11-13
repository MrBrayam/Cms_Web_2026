<?php get_header(); ?>

<?php if ( is_archive() ) : ?>
	<div class="mb-archive-header">
		<h1><?php the_archive_title(); ?></h1>
		<?php the_archive_description( '<p>', '</p>' ); ?>
	</div>
<?php elseif ( is_search() ) : ?>
	<div class="mb-archive-header">
		<h1>Resultados para: &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
	</div>
<?php endif; ?>

<?php if ( have_posts() ) : ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'mb-post' ); ?>>
			<div class="mb-meta">
				<?php
				$cats = get_the_category();
				if ( ! empty( $cats ) ) :
					?>
					<a class="mb-cat-badge" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
						<?php echo esc_html( $cats[0]->name ); ?>
					</a>
				<?php endif; ?>
				<span><?php echo esc_html( get_the_date() ); ?></span>
				<span>&middot;</span>
				<span><?php the_author(); ?></span>
				<span>&middot;</span>
				<span><?php echo esc_html( minimal_blog_reading_time() ); ?></span>
			</div>

			<h2 class="mb-post-title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>

			<?php if ( has_post_thumbnail() ) : ?>
				<a class="mb-featured-img" href="<?php the_permalink(); ?>">
					<?php the_post_thumbnail( 'large' ); ?>
				</a>
			<?php endif; ?>

			<div class="mb-excerpt">
				<?php the_excerpt(); ?>
			</div>

			<a class="mb-read-more" href="<?php the_permalink(); ?>">Leer art&iacute;culo &rarr;</a>
		</article>
	<?php endwhile; ?>

	<div class="mb-pagination">
		<div><?php previous_posts_link( '&larr; Entradas recientes' ); ?></div>
		<div><?php next_posts_link( 'Entradas anteriores &rarr;' ); ?></div>
	</div>
<?php else : ?>
	<p>No se encontraron art&iacute;culos para mostrar.</p>
<?php endif; ?>

<?php get_footer(); ?>
