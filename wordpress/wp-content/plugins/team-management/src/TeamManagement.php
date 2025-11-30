<?php

namespace TeamManagement;

if (!defined('ABSPATH')) {
    exit;
}

class TeamManagement
{
    public const CPT = 'team';
    public const META_GALLERY = '_team_gallery_ids'; // stores array of attachment IDs
    public const NONCE_METABOX = 'team_gallery_metabox_nonce';

    public function __construct()
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerMeta']);

        // Metabox for media library/gallery
        add_action('add_meta_boxes', [$this, 'registerMetaboxes']);
        add_action('save_post_' . self::CPT, [$this, 'saveTeamMeta']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function activate(): void
    {
        $this->registerPostType();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => __('Teams', 'team-management'),
            'singular_name' => __('Team', 'team-management'),
            'add_new' => __('Add New', 'team-management'),
            'add_new_item' => __('Add New Team', 'team-management'),
            'edit_item' => __('Edit Team', 'team-management'),
            'new_item' => __('New Team', 'team-management'),
            'view_item' => __('View Team', 'team-management'),
            'search_items' => __('Search Teams', 'team-management'),
            'not_found' => __('No teams found', 'team-management'),
            'not_found_in_trash' => __('No teams found in Trash', 'team-management'),
            'all_items' => __('All Teams', 'team-management'),
            'menu_name' => __('Teams', 'team-management'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'thumbnail', 'editor'], // name, featured image, optional description
            'rewrite' => ['slug' => 'teams'],
        ];

        register_post_type(self::CPT, $args);
    }

    public function registerMeta(): void
    {
        // Gallery of attachment IDs
        register_post_meta(self::CPT, self::META_GALLERY, [
            'type' => 'array',
            'single' => true,
            'default' => [],
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_filter(array_map('intval', $value), function ($id) {
                    return $id > 0;
                }));
            },
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'integer' ],
                ],
            ],
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ]);
    }

    public function registerMetaboxes(): void
    {
        add_meta_box(
            'team-gallery-metabox',
            __('Team Media Library', 'team-management'),
            [$this, 'renderGalleryMetabox'],
            self::CPT,
            'normal',
            'default'
        );
    }

    public function renderGalleryMetabox($post): void
    {
        wp_nonce_field(self::NONCE_METABOX, self::NONCE_METABOX);
        $ids = get_post_meta($post->ID, self::META_GALLERY, true);
        if (!is_array($ids)) {
            $ids = [];
        }
        echo '<div id="team-gallery-metabox-root" data-input-name="' . esc_attr(self::META_GALLERY) . '" data-selected="' . esc_attr(wp_json_encode($ids)) . '"></div>';
        // Fallback simple list
        echo '<p class="description">' . esc_html__('Use the "Select Media" button to choose images and files for this team.', 'team-management') . '</p>';
    }

    public function saveTeamMeta($post_id): void
    {
        if (!isset($_POST[self::NONCE_METABOX]) || !wp_verify_nonce($_POST[self::NONCE_METABOX], self::NONCE_METABOX)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST[self::META_GALLERY])) {
            $raw = $_POST[self::META_GALLERY];
            if (is_string($raw)) {
                // Expect comma-separated from the admin JS hidden input
                $ids = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', $raw)))));
            } elseif (is_array($raw)) {
                $ids = array_filter(array_map('intval', $raw));
            } else {
                $ids = [];
            }
            update_post_meta($post_id, self::META_GALLERY, array_values($ids));
        }
    }

    public function enqueueAdminAssets($hook): void
    {
        // Only enqueue on Team edit/add screens
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::CPT) {
            return;
        }

        wp_enqueue_media();

        $handle = 'team-management-admin';
        // Build URL relative to the main plugin file to avoid src/ nesting
        $main_file = dirname(__DIR__) . '/team-management.php';
        $src = plugins_url('assets/admin.js', $main_file);
        wp_enqueue_script($handle, $src, ['jquery'], '1.0.0', true);

        wp_localize_script($handle, 'TeamMgmtL10n', [
            'selectMedia' => __('Select Media', 'team-management'),
            'editSelection' => __('Edit Selection', 'team-management'),
        ]);
    }
}
