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
