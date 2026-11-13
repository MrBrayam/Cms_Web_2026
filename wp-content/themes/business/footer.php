</main>
<footer class="bz-footer">
	<div class="bz-container">
		<div class="bz-footer-grid">
			<div>
				<h4><?php bloginfo( 'name' ); ?></h4>
				<p><?php echo esc_html( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : 'Portal corporativo y gestor de contenidos BlogCMS.' ); ?></p>
			</div>
			<div>
				<h4>Navegaci&oacute;n</h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'fallback_cb'    => 'wp_page_menu',
					)
				);
				?>
			</div>
			<div>
				<h4>Categor&iacute;as</h4>
				<ul>
					<?php wp_list_categories( array( 'title_li' => '', 'number' => 5 ) ); ?>
				</ul>
			</div>
		</div>
		<div class="bz-footer-bottom">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &mdash; Tema Business Corporativo</span>
			<span>UNSM &middot; Ingenier&iacute;a Web</span>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
