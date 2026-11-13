<?php get_header(); ?>

<?php if ( is_archive() ) : ?>
	<h1 class="mg-section-title"><?php the_archive_title(); ?></h1>
<?php elseif ( is_search() ) : ?>
	<h1 class="mg-section-title">B&uacute;squeda: &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
<?php endif; ?>

<?php if ( have_posts() ) : ?>
	<?php
	$post_index = 0;
	$show_lead  = is_home() && ! is_paged();
	?>

	<?php if ( $show_lead ) : ?>
		<?php the_post(); $post_index++; ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'mg-lead' ); ?>>
			<div class="mg-lead-media">
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large' ); ?></a>
				<?php else : ?>
					<div class="mg-card-cover-placeholder">Portada destacada</div>
				<?php endif; ?>
			</div>
			<div class="mg-lead-body">
				<?php
				$cats = get_the_category();
				if ( ! empty( $cats ) ) :
					?>
					<a class="mg-badge" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
						<?php echo esc_html( $cats[0]->name ); ?>
					</a>
				<?php endif; ?>
				<h2 class="mg-lead-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<div class="mg-card-excerpt"><?php the_excerpt(); ?></div>
				<div class="mg-meta">
					<span>Por <?php the_author(); ?></span>
					<span><?php echo esc_html( get_the_date() ); ?></span>
				</div>
			</div>
		</article>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<h2 class="mg-section-title"><?php echo $show_lead ? 'Edici&oacute;n en 3 Columnas' : 'Art&iacute;culos'; ?></h2>
		<div class="mg-grid-3">
			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'mg-card' ); ?>>
					<a class="mg-card-cover" href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'magazine-cover' ); ?>
						<?php else : ?>
							<div class="mg-card-cover-placeholder"><?php bloginfo( 'name' ); ?></div>
						<?php endif; ?>
					</a>
					<div class="mg-card-body">
						<?php
						$cats = get_the_category();
						if ( ! empty( $cats ) ) :
							?>
							<a class="mg-badge" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
								<?php echo esc_html( $cats[0]->name ); ?>
							</a>
						<?php endif; ?>
						<h3 class="mg-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div class="mg-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></div>
						<div class="mg-meta">
							<span><?php the_author(); ?></span>
							<span><?php echo esc_html( get_the_date() ); ?></span>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
	<?php endif; ?>

	<div class="mg-pagination">
		<div><?php previous_posts_link( '&larr; P&aacute;gina anterior' ); ?></div>
		<div><?php next_posts_link( 'Siguiente p&aacute;gina &rarr;' ); ?></div>
	</div>
<?php else : ?>
	<p>No hay publicaciones disponibles.</p>
<?php endif; ?>

<?php get_footer(); ?>
