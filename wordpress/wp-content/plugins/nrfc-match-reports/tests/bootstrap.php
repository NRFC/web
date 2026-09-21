<?php

declare(strict_types=1);

namespace {
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
}

namespace NRFCMatchReports {
    final class TestWpState
    {
        public static bool $nonce_valid = true;
        public static bool $can_edit_post = true;

        /** @var array<int, array{post_id:int|string, meta_key:string, meta_value:string}> */
        public static array $updated_meta = [];

        public static function reset(): void
        {
            self::$nonce_valid = true;
            self::$can_edit_post = true;
            self::$updated_meta = [];
        }
    }

    function wp_verify_nonce($nonce, $action): bool
    {
        return TestWpState::$nonce_valid;
    }

    function current_user_can($capability, $post_id = null): bool
    {
        return TestWpState::$can_edit_post;
    }

    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }

    function update_post_meta($post_id, string $meta_key, $meta_value): bool
    {
        TestWpState::$updated_meta[] = [
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'meta_value' => (string) $meta_value,
        ];

        return true;
    }

    function absint($value): int
    {
        return abs((int) $value);
    }

    function __(string $text, ?string $domain = null): string
    {
        return $text;
    }
}

namespace {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/src/MatchReports.php';
}

