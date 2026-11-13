<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function magazine_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'magazine-cover', 720, 450, true );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Principal Revista', 'magazine' ),
			'footer'  => __( 'Menu Pie de Pagina', 'magazine' ),
		)
	);
}
add_action( 'after_setup_theme', 'magazine_theme_setup' );

function magazine_theme_scripts() {
	wp_enqueue_style( 'magazine-style', get_stylesheet_uri(), array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'magazine_theme_scripts' );

function magazine_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Barra Lateral Revista', 'magazine' ),
			'id'            => 'magazine-sidebar',
			'before_widget' => '<div class="mg-sidebar-box">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3>',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'magazine_widgets_init' );
