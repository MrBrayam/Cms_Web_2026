<?php get_header(); ?>

<section class="pf-intro">
	<?php if ( is_archive() ) : ?>
		<h1><?php the_archive_title(); ?></h1>
	<?php elseif ( is_search() ) : ?>
		<h1>Resultados: &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
	<?php else : ?>
		<h1>Proyectos &amp; Publicaciones</h1>
		<p><?php echo esc_html( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : 'Explora nuestra galeria de proyectos destacados. Pasa el cursor sobre cada tarjeta para ver el detalle.' ); ?></p>
	<?php endif; ?>
</section>

<?php
$categories = get_categories( array( 'hide_empty' => true ) );
if ( ! empty( $categories ) && ! is_archive() ) :
	?>
	<div class="pf-filters" role="group" aria-label="Filtrar proyectos por categor&iacute;a">
		<button type="button" class="pf-filter-btn is-active" data-filter="all">Todos</button>
		<?php foreach ( $categories as $cat ) : ?>
			<button type="button" class="pf-filter-btn" data-filter="<?php echo esc_attr( $cat->slug ); ?>">
				<?php echo esc_html( $cat->name ); ?>
			</button>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( have_posts() ) : ?>
	<div class="pf-grid" id="pf-grid">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$post_cats = get_the_category();
			$slugs     = array();
			$first_cat = 'Proyecto';
			if ( ! empty( $post_cats ) ) {
				$first_cat = $post_cats[0]->name;
				foreach ( $post_cats as $c ) {
					$slugs[] = $c->slug;
				}
			}
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'pf-card' ); ?> data-cats="<?php echo esc_attr( implode( ' ', $slugs ) ); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'portfolio-card', array( 'class' => 'pf-card-img' ) ); ?>
				<?php else : ?>
					<div class="pf-card-fallback"><?php the_title(); ?></div>
				<?php endif; ?>

				<div class="pf-card-ribbon"><?php the_title(); ?></div>

				<div class="pf-overlay">
					<span class="pf-card-cat"><?php echo esc_html( $first_cat ); ?></span>
					<h2 class="pf-card-title"><?php the_title(); ?></h2>
					<p class="pf-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></p>
					<a class="pf-card-link" href="<?php the_permalink(); ?>">Ver proyecto &rarr;</a>
				</div>
			</article>
		<?php endwhile; ?>
	</div>

	<div class="pf-pagination">
		<div><?php previous_posts_link( '&larr; Anteriores' ); ?></div>
		<div><?php next_posts_link( 'Siguientes &rarr;' ); ?></div>
	</div>
<?php else : ?>
	<p>No hay proyectos publicados todav&iacute;a.</p>
<?php endif; ?>

<script>
(function () {
	var buttons = document.querySelectorAll('.pf-filter-btn');
	var cards = document.querySelectorAll('#pf-grid .pf-card');
	if (!buttons.length || !cards.length) return;
	buttons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			buttons.forEach(function (b) { b.classList.remove('is-active'); });
			btn.classList.add('is-active');
			var filter = btn.getAttribute('data-filter');
			cards.forEach(function (card) {
				var cats = (card.getAttribute('data-cats') || '').split(' ');
				if (filter === 'all' || cats.indexOf(filter) !== -1) {
					card.classList.remove('is-hidden');
				} else {
					card.classList.add('is-hidden');
				}
			});
		});
	});
})();
</script>

<?php get_footer(); ?>
