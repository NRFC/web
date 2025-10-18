<!DOCTYPE html>
<html <?php language_attributes(); ?> lang="enGB">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php wp_title(); ?></title>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header>
  <div class="site-branding">
    <h1><a href="<?php echo esc_url(home_url('/')); ?>">Norwich Rugby Club</a></h1>
  </div>
  <?php get_template_part('template-parts/sponsors', 'frontpage'); ?>
  <nav>
    <?php wp_nav_menu(array('theme_location' => 'primary')); ?>
  </nav>
</header>
