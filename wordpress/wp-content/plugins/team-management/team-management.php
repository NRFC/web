<?php
/*
Plugin Name: Team Management
Description: Manage sports teams with name, featured image, and a media library gallery.
Author: NRFC Web
Version: 1.0.0
Text Domain: team-management
*/

if (!defined('ABSPATH')) {
    exit;
}

// Simple PSR-4 like autoload for this plugin namespace
spl_autoload_register(function ($class) {
    if (strpos($class, 'TeamManagement\\') !== 0) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('TeamManagement\\', '', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

use TeamManagement\TeamManagement;

$GLOBALS['team_management_instance'] = new TeamManagement();

register_activation_hook(__FILE__, function () {
    $GLOBALS['team_management_instance']->activate();
});

register_deactivation_hook(__FILE__, function () {
    $GLOBALS['team_management_instance']->deactivate();
});
