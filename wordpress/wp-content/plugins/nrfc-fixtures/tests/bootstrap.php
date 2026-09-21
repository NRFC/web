<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../../../');
}

if (!class_exists('WP_Widget')) {
    class WP_Widget
    {
        public function __construct(...$args)
        {
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Fixtures.php';

