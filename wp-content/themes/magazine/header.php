<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="mg-topbar">
	<div class="mg-container mg-topbar-inner">
		<span>Edici&oacute;n Digital &middot; <?php echo esc_html( date_i18n( 'l, j F Y' ) ); ?></span>
		<span>Revista &amp; BlogCMS</span>
	</div>
</div>
<header class="mg-header">
	<div class="mg-container">
		<div class="mg-masthead">
			<div>
				<a class="mg-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<?php if ( get_bloginfo( 'description' ) ) : ?>
					<p class="mg-tagline"><?php bloginfo( 'description' ); ?></p>
				<?php endif; ?>
			</div>
			<form role="search" method="get" class="mg-search-mini" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" placeholder="Buscar art&iacute;culos..." value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
				<button type="submit">Buscar</button>
			</form>
		</div>
		<div class="mg-nav-bar">
			<button class="mg-nav-toggle" type="button" onclick="document.getElementById('mg-nav').classList.toggle('is-open')">Men&uacute;</button>
			<nav id="mg-nav" class="mg-nav" aria-label="Men&uacute; principal">
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
	</div>
</header>
<main class="mg-main">
	<div class="mg-container">
