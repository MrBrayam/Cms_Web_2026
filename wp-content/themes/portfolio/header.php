<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="pf-header">
	<div class="pf-container pf-header-inner">
		<a class="pf-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php bloginfo( 'name' ); ?><span>.</span>
		</a>
		<button class="pf-nav-toggle" type="button" onclick="document.getElementById('pf-nav').classList.toggle('is-open')">Men&uacute;</button>
		<nav id="pf-nav" class="pf-nav" aria-label="Men&uacute; principal">
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
	</div>
</header>
<main class="pf-main">
	<div class="pf-container">
