<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function business_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'business-card', 640, 360, true );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Principal Corporativo', 'business' ),
			'footer'  => __( 'Menu Pie de Pagina', 'business' ),
		)
	);
}
add_action( 'after_setup_theme', 'business_theme_setup' );

function business_theme_scripts() {
	wp_enqueue_style( 'business-style', get_stylesheet_uri(), array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'business_theme_scripts' );

function business_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'business_hero_section',
		array(
			'title'    => __( 'Hero Banner Corporativo', 'business' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_setting( 'business_hero_title', array( 'default' => 'Soluciones Digitales y Gestion de Contenido', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'business_hero_title', array( 'label' => 'Titulo del Hero', 'section' => 'business_hero_section', 'type' => 'text' ) );

	$wp_customize->add_setting( 'business_hero_subtitle', array( 'default' => 'Plataforma corporativa escalable con gestion editorial, galeria multimedia, analiticas y comunicacion directa.', 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control( 'business_hero_subtitle', array( 'label' => 'Subtitulo del Hero', 'section' => 'business_hero_section', 'type' => 'textarea' ) );
}
add_action( 'customize_register', 'business_customize_register' );
