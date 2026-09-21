<?php
/**
 * Plugin Name: NRFC Fixtures
 * Description: Adds a "Fixture" custom post type to manage rugby matches.
 * Version: 1.0.0
 * Author: NRFC Automation
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Basic PSR-4 Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'NRFCFixtures\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use NRFCFixtures\Fixtures;

// Bootstrap the plugin
$fixtures_plugin = new Fixtures();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array($fixtures_plugin, 'activate'));
register_deactivation_hook(__FILE__, array($fixtures_plugin, 'deactivate'));
