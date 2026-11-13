<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="mb-header">
	<div class="mb-container mb-header-inner">
		<div>
			<a class="mb-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p class="mb-tagline"><?php bloginfo( 'description' ); ?></p>
			<?php endif; ?>
		</div>
		<button class="mb-nav-toggle" type="button" aria-expanded="false" onclick="document.getElementById('mb-nav').classList.toggle('is-open')">Men&uacute;</button>
		<nav id="mb-nav" class="mb-nav" aria-label="Men&uacute; principal">
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
<main class="mb-main">
	<div class="mb-container">
