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
    public const META_AGE_GRADE = '_team_age_grade'; // stores string: senior|youth|minis
    public const META_GENDER = '_team_gender'; // stores string: male|female|mixed
    public const META_PEOPLE = '_team_people_ids'; // stores array of person post IDs

    // Allowed values for age grade
    private const AGE_GRADES = ['senior', 'u18', 'u18', 'u16', 'u15', 'u14', 'u13', 'u12', 'minis'];
    // Allowed values for gender
    private const GENDERS = ['male', 'female', 'mixed'];

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

        // People (PersonDirectory CPT) selection as array of IDs
        register_post_meta(self::CPT, self::META_PEOPLE, [
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
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ]);

        // Age grade string meta
        register_post_meta(self::CPT, self::META_AGE_GRADE, [
            'type' => 'string',
            'single' => true,
            'default' => 'senior',
            'sanitize_callback' => function ($value) {
                $value = is_string($value) ? strtolower(trim($value)) : '';
                return in_array($value, self::AGE_GRADES, true) ? $value : 'senior';
            },
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                    'enum' => self::AGE_GRADES,
                ],
            ],
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ]);

        // Gender string meta
        register_post_meta(self::CPT, self::META_GENDER, [
            'type' => 'string',
            'single' => true,
            'default' => 'mixed',
            'sanitize_callback' => function ($value) {
                $value = is_string($value) ? strtolower(trim($value)) : '';
                return in_array($value, self::GENDERS, true) ? $value : 'mixed';
            },
            'show_in_rest' => [
                'schema' => [
                    'type' => 'string',
                    'enum' => self::GENDERS,
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

        add_meta_box(
            'team-people-metabox',
            __('Team People', 'team-management'),
            [$this, 'renderPeopleMetabox'],
            self::CPT,
            'normal',
            'default'
        );

        add_meta_box(
            'team-age-grade-metabox',
            __('Age Grade', 'team-management'),
            [$this, 'renderAgeGradeMetabox'],
            self::CPT,
            'side',
            'default'
        );

        add_meta_box(
            'team-gender-metabox',
            __('Gender', 'team-management'),
            [$this, 'renderGenderMetabox'],
            self::CPT,
            'side',
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

    public function renderPeopleMetabox($post): void
    {
        // Reuse the same nonce
        wp_nonce_field(self::NONCE_METABOX, self::NONCE_METABOX);
        $ids = get_post_meta($post->ID, self::META_PEOPLE, true);
        if (!is_array($ids)) {
            $ids = [];
        }
        echo '<div id="team-people-metabox-root" data-input-name="' . esc_attr(self::META_PEOPLE) . '" data-selected="' . esc_attr(wp_json_encode($ids)) . '"></div>';
        echo '<p class="description">' . esc_html__('Search and add people to this team. Drag to reorder; click × to remove.', 'team-management') . '</p>';        
    }

    public function renderAgeGradeMetabox($post): void
    {
        // Reuse the same nonce to keep saving logic simple
        wp_nonce_field(self::NONCE_METABOX, self::NONCE_METABOX);
        $current = get_post_meta($post->ID, self::META_AGE_GRADE, true);
        if (!is_string($current) || !in_array($current, self::AGE_GRADES, true)) {
            $current = 'senior';
        }

        $options = [
            'senior' => __('Senior', 'team-management'),
            'u18'  => __('Under 18', 'team-management'),
            'u16'  => __('Under 16', 'team-management'),
            'u15'  => __('Under 15', 'team-management'),
            'u14'  => __('Under 14', 'team-management'),
            'u13'  => __('Under 13', 'team-management'),
            'u12'  => __('under 12', 'team-management'),
            'minis'  => __('Minis', 'team-management'),
        ];

        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . esc_html__('Age Grade', 'team-management') . '</legend>';
        foreach ($options as $value => $label) {
            $id = 'team-age-grade-' . esc_attr($value);
            echo '<p style="margin: 0 0 6px;">';
            echo '<label for="' . $id . '">';
            echo '<input type="radio" name="' . esc_attr(self::META_AGE_GRADE) . '" id="' . $id . '" value="' . esc_attr($value) . '" ' . checked($current, $value, false) . ' /> ' . esc_html($label);
            echo '</label>';
            echo '</p>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Select the age grade for this team.', 'team-management') . '</p>';
    }

    public function renderGenderMetabox($post): void
    {
        // Reuse the same nonce as other metaboxes
        wp_nonce_field(self::NONCE_METABOX, self::NONCE_METABOX);
        $current = get_post_meta($post->ID, self::META_GENDER, true);
        if (!is_string($current) || !in_array($current, self::GENDERS, true)) {
            $current = 'mixed';
        }

        $options = [
            'male' => __('Male', 'team-management'),
            'female' => __('Female', 'team-management'),
            'mixed' => __('Mixed', 'team-management'),
        ];

        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . esc_html__('Gender', 'team-management') . '</legend>';
        foreach ($options as $value => $label) {
            $id = 'team-gender-' . esc_attr($value);
            echo '<p style="margin: 0 0 6px;">';
            echo '<label for="' . $id . '">';
            echo '<input type="radio" name="' . esc_attr(self::META_GENDER) . '" id="' . $id . '" value="' . esc_attr($value) . '" ' . checked($current, $value, false) . ' /> ' . esc_html($label);
            echo '</label>';
            echo '</p>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Select the gender for this team.', 'team-management') . '</p>';
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

        if (isset($_POST[self::META_PEOPLE])) {
            $raw = $_POST[self::META_PEOPLE];
            if (is_string($raw)) {
                $ids = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', $raw)))));
            } elseif (is_array($raw)) {
                $ids = array_filter(array_map('intval', $raw));
            } else {
                $ids = [];
            }
            update_post_meta($post_id, self::META_PEOPLE, array_values($ids));
        }

        if (isset($_POST[self::META_AGE_GRADE])) {
            $value = is_string($_POST[self::META_AGE_GRADE]) ? strtolower(trim(wp_unslash($_POST[self::META_AGE_GRADE]))) : '';
            if (!in_array($value, self::AGE_GRADES, true)) {
                $value = 'senior';
            }
            update_post_meta($post_id, self::META_AGE_GRADE, $value);
        }

        if (isset($_POST[self::META_GENDER])) {
            $value = is_string($_POST[self::META_GENDER]) ? strtolower(trim(wp_unslash($_POST[self::META_GENDER]))) : '';
            if (!in_array($value, self::GENDERS, true)) {
                $value = 'mixed';
            }
            update_post_meta($post_id, self::META_GENDER, $value);
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
        // Ensure wpApiSettings is available for REST calls
        wp_enqueue_script('wp-api');
        wp_enqueue_script($handle, $src, ['jquery', 'wp-api'], '1.0.0', true);

        wp_localize_script($handle, 'TeamMgmtL10n', [
            'selectMedia' => __('Select Media', 'team-management'),
            'editSelection' => __('Edit Selection', 'team-management'),
            'people' => [
                'searchPlaceholder' => __('Search people…', 'team-management'),
                'add' => __('Add', 'team-management'),
                'noResults' => __('No people found.', 'team-management'),
            ],
        ]);
    }
}
