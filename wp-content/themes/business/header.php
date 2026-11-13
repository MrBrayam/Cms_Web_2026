<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="bz-header">
	<div class="bz-container bz-header-inner">
		<a class="bz-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		<button class="bz-nav-toggle" type="button" onclick="document.getElementById('bz-nav-wrap').classList.toggle('is-open')">Men&uacute;</button>
		<div id="bz-nav-wrap" class="bz-nav-wrap">
			<nav class="bz-nav" aria-label="Men&uacute; principal">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'fallback_cb'    => 'wp_page_menu',
					)
				);
				?>
			</nav>
			<a class="bz-btn" href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>">Cont&aacute;ctanos</a>
		</div>
	</div>
</header>
<main>
