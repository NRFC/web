<?php

function nrfc_theme_setup()
{
    register_nav_menus(
        array(
        'primary' => __('Primary Menu', 'nrfc')
        )
    );
}
add_action('after_setup_theme', 'nrfc_theme_setup');

function nrfc_enqueue_scripts()
{
    wp_enqueue_style(
        'nrfc-main-style',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
        filemtime(get_template_directory() . '/assets/css/main.css')
    );
}
add_action('wp_enqueue_scripts', 'nrfc_enqueue_scripts');

/**
 * Get gold sponsors for display in the footer
 * 
 * @return array Array of sponsor posts
 */
function nrfc_get_gold_sponsors()
{
    $args = array(
        'post_type' => 'sponsor',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_sponsor_type',
                'value' => 'gold',
                'compare' => '='
            )
        )
    );

    $sponsors = new WP_Query($args);

    return $sponsors->posts;
}
