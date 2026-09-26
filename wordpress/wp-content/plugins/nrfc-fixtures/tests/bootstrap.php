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

if (!class_exists('WP_Term')) {
    class WP_Term
    {
        public int $term_id;
        public string $name;
        public string $slug;
        public string $taxonomy;

        public function __construct(int $term_id = 0, string $name = '', string $slug = '', string $taxonomy = '')
        {
            $this->term_id = $term_id;
            $this->name = $name;
            $this->slug = $slug;
            $this->taxonomy = $taxonomy;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public string $code;
        public string $message;
        public mixed $data;

        public function __construct(string $code = '', string $message = '', mixed $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];

        public function __construct(array $params = [])
        {
            $this->params = $params;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }
    }
}

class FixturesTestState
{
    /** @var array<int, WP_Term> */
    public static array $terms = [];

    /** @var array<int, object> */
    public static array $query_posts = [];

    /** @var array<int, array<string, mixed>> */
    public static array $post_meta = [];

    /** @var array<int, array<string, array<WP_Term>>> */
    public static array $object_terms = [];

    /** @var array<string, mixed> */
    public static array $query_vars = [];

    /** @var array<int, array{namespace: string, route: string, args: array}> */
    public static array $registered_rest_routes = [];

    /** @var array<int, array{regex: string, query: string, after: string}> */
    public static array $rewrite_rules = [];

    public static ?int $last_status_header = null;
    public static ?string $last_die_message = null;

    public static function reset(): void
    {
        self::$terms = [];
        self::$query_posts = [];
        self::$post_meta = [];
        self::$object_terms = [];
        self::$query_vars = [];
        self::$registered_rest_routes = [];
        self::$rewrite_rules = [];
        self::$last_status_header = null;
        self::$last_die_message = null;
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        public array $posts = [];

        public function __construct(array $args = [])
        {
            $this->posts = FixturesTestState::$query_posts;
        }

        public function have_posts(): bool
        {
            return !empty($this->posts);
        }

        public function the_post(): void
        {
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^a-z0-9_\-\s]/', '', $title) ?? '';
        return preg_replace('/[\s_]+/', '-', $title) ?? '';
    }
}

if (!function_exists('get_term')) {
    function get_term(int $term_id, string $taxonomy = ''): ?WP_Term
    {
        foreach (FixturesTestState::$terms as $term) {
            if ($term->term_id === $term_id && ($taxonomy === '' || $term->taxonomy === $taxonomy)) {
                return $term;
            }
        }
        return null;
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by(string $field, string|int $value, string $taxonomy = ''): ?WP_Term
    {
        foreach (FixturesTestState::$terms as $term) {
            if ($taxonomy !== '' && $term->taxonomy !== $taxonomy) {
                continue;
            }
            if ($field === 'name' && $term->name === (string)$value) {
                return $term;
            }
            if ($field === 'slug' && $term->slug === (string)$value) {
                return $term;
            }
            if ($field === 'id' && $term->term_id === (int)$value) {
                return $term;
            }
        }
        return null;
    }
}

if (!function_exists('get_terms')) {
    function get_terms(array $args = []): array
    {
        $taxonomy = $args['taxonomy'] ?? '';
        $results = [];
        foreach (FixturesTestState::$terms as $term) {
            if ($taxonomy === '' || $term->taxonomy === $taxonomy) {
                $results[] = $term;
            }
        }
        return $results;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
    {
        return FixturesTestState::$post_meta[$post_id][$key] ?? ($single ? '' : []);
    }
}

if (!function_exists('wp_get_object_terms')) {
    function wp_get_object_terms(int $post_id, string $taxonomy, array $args = []): array
    {
        return FixturesTestState::$object_terms[$post_id][$taxonomy] ?? [];
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(int|object $post = 0): string
    {
        if (is_object($post) && isset($post->post_title)) {
            return $post->post_title;
        }
        $id = is_int($post) ? $post : 0;
        foreach (FixturesTestState::$query_posts as $p) {
            if (isset($p->ID) && $p->ID === $id) {
                return $p->post_title ?? '';
            }
        }
        return '';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        FixturesTestState::$registered_rest_routes[] = [
            'namespace' => $namespace,
            'route'     => $route,
            'args'      => $args,
        ];
        return true;
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(string $regex, string $query, string $after = 'bottom'): void
    {
        FixturesTestState::$rewrite_rules[] = [
            'regex' => $regex,
            'query' => $query,
            'after' => $after,
        ];
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var(string $var, mixed $default = ''): mixed
    {
        return FixturesTestState::$query_vars[$var] ?? $default;
    }
}

if (!function_exists('status_header')) {
    function status_header(int $code): void
    {
        FixturesTestState::$last_status_header = $code;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void
    {
        FixturesTestState::$last_die_message = $message;
        throw new \RuntimeException("wp_die: {$message}");
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string
    {
        return 'http://example.org' . ($path !== '' && !str_starts_with($path, '/') ? '/' : '') . $path;
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = '', string $scheme = 'rest'): string
    {
        return 'http://example.org/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        return true;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string|int $action = -1, string $name = '_wpnonce', bool $referer = true, bool $echo = true): string
    {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="test_nonce" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('submit_button')) {
    function submit_button(?string $text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, mixed $other_attributes = null): void
    {
        echo '<input type="submit" name="' . esc_attr($name) . '" value="' . esc_attr($text ?? 'Submit') . '" class="button button-' . esc_attr($type) . '" />';
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Fixtures.php';

