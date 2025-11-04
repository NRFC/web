<?php


/**
 * Get club sponsors for display in the footer
 *
 * @return array Array of sponsor posts
 */
function nrfc_get_gold_sponsors(): array {
	$args = array(
		'post_type' => 'sponsor',
		'posts_per_page' => -1,
		'meta_query' => array(
			array(
				'key' => '_sponsor_type',
				'value' => 'club',
				'compare' => '='
			)
		)
	);

	$sponsors = new WP_Query($args);

	return $sponsors->posts;
}

add_action( 'after_setup_theme', 'add_custom_image_sizes' );

function add_custom_image_sizes() {
	add_image_size( 'sponsor-footer', 9999, 50 );
	add_image_size( 'sponsor', 9999, 150 );
}

function enqueue_sponsors_styles() {
	// Enqueue sponsors CSS
	wp_enqueue_style(
		'sponsors-css',
		get_template_directory_uri() . '/../nrfc/sponsors.css',
		array(),
		'1.0.0',
		'all'
	);
}

add_action( 'wp_enqueue_scripts', 'enqueue_sponsors_styles' );
