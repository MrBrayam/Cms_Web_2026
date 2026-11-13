<?php get_header(); ?>

<?php if ( is_home() && ! is_paged() ) : ?>
	<section class="bz-hero">
		<div class="bz-container">
			<div class="bz-hero-inner">
				<span class="bz-hero-eyebrow"><?php bloginfo( 'name' ); ?> &middot; Corporativo</span>
				<h1><?php echo esc_html( get_theme_mod( 'business_hero_title', 'Soluciones Digitales y Gestion de Contenido' ) ); ?></h1>
				<p><?php echo esc_html( get_theme_mod( 'business_hero_subtitle', 'Plataforma corporativa escalable con gestion editorial, galeria multimedia, analiticas y comunicacion directa.' ) ); ?></p>
				<div class="bz-hero-actions">
					<a class="bz-btn" href="#bz-blog">Explorar publicaciones</a>
					<a class="bz-btn bz-btn-outline" href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>">Solicitar informaci&oacute;n</a>
				</div>
			</div>
		</div>
	</section>

	<section class="bz-section" style="background:#ffffff;border-bottom:1px solid #e2e8f0;">
		<div class="bz-container">
			<div class="bz-section-header">
				<h2>Pilares de Nuestra Plataforma</h2>
				<p>Arquitectura modular lista para equipos editoriales y sitios institucionales.</p>
			</div>
			<div class="bz-grid-3">
				<div class="bz-card">
					<div class="bz-service-icon">01</div>
					<h3>Gesti&oacute;n Editorial &amp; Roles</h3>
					<p>Flujo organizado entre administradores, editores y autores con categor&iacute;as, etiquetas y moderaci&oacute;n.</p>
				</div>
				<div class="bz-card">
					<div class="bz-service-icon">02</div>
					<h3>SEO &amp; Anal&iacute;ticas</h3>
					<p>Meta etiquetas optimizadas, sitemap XML autom&aacute;tico y estad&iacute;sticas de visitas en tiempo real.</p>
				</div>
				<div class="bz-card">
					<div class="bz-service-icon">03</div>
					<h3>Multimedia &amp; Contacto</h3>
					<p>Galer&iacute;as organizadas por &aacute;lbumes, difusi&oacute;n en redes sociales y formularios personalizables.</p>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<section id="bz-blog" class="bz-section">
	<div class="bz-container">
		<div class="bz-section-header">
			<?php if ( is_archive() ) : ?>
				<h2><?php the_archive_title(); ?></h2>
			<?php elseif ( is_search() ) : ?>
				<h2>Resultados para: &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h2>
			<?php else : ?>
				<h2>&Uacute;ltimas Publicaciones</h2>
				<p>Novedades, art&iacute;culos t&eacute;cnicos y comunicados institucionales.</p>
			<?php endif; ?>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="bz-grid-3">
				<?php while ( have_posts() ) : the_post(); ?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'bz-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="bz-post-cover" href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'business-card' ); ?>
							</a>
						<?php endif; ?>
						<div class="bz-meta">
							<span><?php echo esc_html( get_the_date() ); ?></span>
							<span>&middot;</span>
							<span><?php the_author(); ?></span>
						</div>
						<h3><a href="<?php the_permalink(); ?>" style="color:#0b3b60;"><?php the_title(); ?></a></h3>
						<p style="color:#475569;flex:1;"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
						<a href="<?php the_permalink(); ?>" style="font-weight:600;">Leer m&aacute;s &rarr;</a>
					</article>
				<?php endwhile; ?>
			</div>

			<div class="bz-pagination">
				<div><?php previous_posts_link( '&larr; Anteriores' ); ?></div>
				<div><?php next_posts_link( 'Siguientes &rarr;' ); ?></div>
			</div>
		<?php else : ?>
			<p style="text-align:center;">No hay art&iacute;culos disponibles.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
