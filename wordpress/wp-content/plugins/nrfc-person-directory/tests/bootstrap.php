<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/../../../../');
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($value): string
        {
            return trim(strip_tags((string) $value));
        }
    }

    if (!function_exists('absint')) {
        function absint($value): int
        {
            return abs((int) $value);
        }
    }

    if (!function_exists('__')) {
        function __(string $text, ?string $domain = null): string
        {
            return $text;
        }
    }

    if (!class_exists('WP_Widget')) {
        class WP_Widget
        {
            public function __construct(...$args)
            {
            }
        }
    }
}

namespace PersonDirectory {
    function wp_strip_all_tags($value): string
    {
        return strip_tags((string) $value);
    }
}

namespace {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/src/PersonDirectory.php';
}


