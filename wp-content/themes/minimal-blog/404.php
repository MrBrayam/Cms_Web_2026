<?php get_header(); ?>

<section class="mb-post">
	<h1 class="mb-single-title">404 &mdash; P&aacute;gina no encontrada</h1>
	<p>El contenido que buscas no existe o fue movido.</p>
	<?php get_search_form(); ?>
	<p><a class="mb-read-more" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Volver al inicio</a></p>
</section>

<?php get_footer(); ?>
