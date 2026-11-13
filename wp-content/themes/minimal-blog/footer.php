	</div>
</main>
<footer class="mb-footer">
	<div class="mb-container mb-footer-inner">
		<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &mdash; Tema Minimal Blog</span>
		<nav aria-label="Men&uacute; secundario">
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
