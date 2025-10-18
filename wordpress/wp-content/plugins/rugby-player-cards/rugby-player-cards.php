<?php

/*
Plugin Name: Rugby Player Cards
Description: Tracks player development with multiple dated cards
Version: 1.0
Author: Toby Batch
*/

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap the plugin
use RugbyPlayerCards\RugbyPlayerCards;

$plugin = new RugbyPlayerCards();

register_activation_hook(__FILE__, array($plugin, 'activate'));
register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));