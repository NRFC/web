<?php

/*
Plugin Name: NRFC Sponsor Management
Description: Manages sponsors with custom fields for name, logo, URL, and type
Version: 1.0
Author: NRFC
Text Domain: nrfc-sponsor-management
*/

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Bootstrap the plugin
use SponsorManagement\SponsorManagement;

// Create plugin instance
$plugin = new SponsorManagement();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array($plugin, 'activate'));
register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));
