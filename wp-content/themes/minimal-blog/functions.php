<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function minimal_blog_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 200, 'flex-width' => true ) );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Principal', 'minimal-blog' ),
			'footer'  => __( 'Menu Pie de Pagina', 'minimal-blog' ),
		)
	);
}
add_action( 'after_setup_theme', 'minimal_blog_setup' );

function minimal_blog_scripts() {
	wp_enqueue_style( 'minimal-blog-style', get_stylesheet_uri(), array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'minimal_blog_scripts' );

function minimal_blog_reading_time( $post_id = null ) {
	$content = get_post_field( 'post_content', $post_id ? $post_id : get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$minutes = max( 1, (int) ceil( $words / 200 ) );
	return $minutes . ' min de lectura';
}
