<?php
/**
 * Plugin Name: NRFC Person Directory
 * Description: Adds a "Person" custom post type with fields: name, phone number, email, and photo, plus a CSV bulk import tool.
 * Version: 1.0.0
 * Author: NRFC Automation
 * License: GPL2+
 * Text Domain: nrfc-person-directory
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Fallback: load the main class directly if Composer autoload isn't present
if (!class_exists('PersonDirectory\\PersonDirectory')) {
    require_once __DIR__ . '/src/PersonDirectory.php';
}

use PersonDirectory\PersonDirectory;

// Bootstrap the plugin
$plugin = new PersonDirectory();

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array($plugin, 'activate'));
register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));

