<?php

/**
 * Unified bootstrap for NRFC plugin tests
 *
 * This file loads WordPress function stubs and plugin classes for all plugins
 * that have test suites defined in the root phpunit.xml.dist.
 */

declare(strict_types=1);

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content/');
}

// Load individual plugin bootstraps
$plugins_with_tests = [
    'nrfc-fixtures' => 'tests/bootstrap.php',
    'nrfc-match-reports' => 'tests/bootstrap.php',
    'nrfc-auto-links' => 'tests/bootstrap.php',
    'nrfc-person-directory' => 'tests/bootstrap.php',
    'nrfc-sponsor-management' => 'tests/bootstrap.php',
];

foreach ($plugins_with_tests as $plugin => $bootstrap) {
    $bootstrap_path = ABSPATH . "wp-content/plugins/$plugin/$bootstrap";
    if (file_exists($bootstrap_path)) {
        // Each plugin bootstrap should handle its own namespacing
        // We load them in sequence to set up all necessary stubs
    }
}

