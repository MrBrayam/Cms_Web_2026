<?php get_header(); ?>

<div class="mg-single-layout">
	<div class="mg-article-box">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php
				$cats = get_the_category();
				if ( ! empty( $cats ) ) :
					?>
					<a class="mg-badge" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
						<?php echo esc_html( $cats[0]->name ); ?>
					</a>
				<?php endif; ?>
				<h1 class="mg-single-title"><?php the_title(); ?></h1>
				<div class="mg-meta" style="border-top:none;padding-top:0;margin-bottom:1rem;">
					<span>Por <?php the_author(); ?></span>
					<span><?php echo esc_html( get_the_date() ); ?></span>
				</div>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="mg-single-cover"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>

				<div class="mg-content"><?php the_content(); ?></div>

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

	<aside>
		<?php if ( is_active_sidebar( 'magazine-sidebar' ) ) : ?>
			<?php dynamic_sidebar( 'magazine-sidebar' ); ?>
		<?php else : ?>
			<div class="mg-sidebar-box">
				<h3>Categor&iacute;as</h3>
				<ul>
					<?php wp_list_categories( array( 'title_li' => '', 'show_count' => true ) ); ?>
				</ul>
			</div>
			<div class="mg-sidebar-box">
				<h3>&Uacute;ltimas publicaciones</h3>
				<ul>
					<?php
					$recent = wp_get_recent_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
					foreach ( $recent as $item ) :
						?>
						<li><a href="<?php echo esc_url( get_permalink( $item['ID'] ) ); ?>"><?php echo esc_html( $item['post_title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</aside>
</div>

<?php get_footer(); ?>
