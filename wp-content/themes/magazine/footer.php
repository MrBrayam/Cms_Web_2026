	</div>
</main>
<footer class="mg-footer">
	<div class="mg-container mg-footer-inner">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &mdash; Tema Magazine (3 Columnas)</span>
		<nav aria-label="Men&uacute; pie">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
