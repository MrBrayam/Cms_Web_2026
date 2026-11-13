<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function portfolio_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'portfolio-card', 720, 540, true );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Principal Portafolio', 'portfolio' ),
			'footer'  => __( 'Menu Pie de Pagina', 'portfolio' ),
		)
	);
}
add_action( 'after_setup_theme', 'portfolio_theme_setup' );

function portfolio_theme_scripts() {
	wp_enqueue_style( 'portfolio-style', get_stylesheet_uri(), array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'portfolio_theme_scripts' );
