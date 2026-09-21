<?php

namespace PersonDirectory;

if (!defined('ABSPATH')) {
    exit;
}

class PersonDirectory
{
    const CPT = 'person';
    const META_PHONE = '_person_phone';
    const META_EMAIL = '_person_email';
    const NONCE_METABOX = 'person_metabox_nonce';
    const NONCE_IMPORT = 'person_import_nonce';
    const BLOCK_NAME = 'person-directory/people';

    const IMAGE_WIDTH = 192;
    const IMAGE_HEIGHT = 224;

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);

        // Metabox
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_person_meta']);

        // Admin list table columns
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // CSV Import admin page
        add_action('admin_menu', [$this, 'register_admin_pages']);

        // Gutenberg block (widget)
        add_action('init', [$this, 'register_people_block']);

        // Classic widget to select people with per-instance labels
        add_action('widgets_init', [$this, 'register_people_widget']);
    }

    /**
     * Plugin activation hook
     */
    public function activate(): void
    {
        $this->register_post_type();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function register_post_type()
    {
        $labels = [
            'name' => __('Persons', 'person-directory'),
            'singular_name' => __('Person', 'person-directory'),
            'add_new' => __('Add New', 'person-directory'),
            'add_new_item' => __('Add New Person', 'person-directory'),
            'edit_item' => __('Edit Person', 'person-directory'),
            'new_item' => __('New Person', 'person-directory'),
            'view_item' => __('View Person', 'person-directory'),
            'search_items' => __('Search Persons', 'person-directory'),
            'not_found' => __('No persons found', 'person-directory'),
            'not_found_in_trash' => __('No persons found in Trash', 'person-directory'),
            'all_items' => __('All Persons', 'person-directory'),
            'menu_name' => __('Persons', 'person-directory'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'thumbnail', 'editor'], // editor optional for bio/notes
            'rewrite' => ['slug' => 'persons'],
        ];

        register_post_type(self::CPT, $args);
    }

    public function register_meta()
    {
        register_post_meta(self::CPT, self::META_PHONE, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => [$this, 'sanitize_phone'],
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ]);
        register_post_meta(self::CPT, self::META_EMAIL, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_email',
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ]);
    }

    public function sanitize_phone($value)
    {
        $value = wp_strip_all_tags($value);
        // Keep digits, plus, spaces, hyphens, parentheses
        $value = preg_replace('/[^0-9+\-() ]+/', '', $value);
        return trim($value);
    }

    public function register_metaboxes()
    {
        add_meta_box(
            'person_details_metabox',
            __('Person Details', 'person-directory'),
            [$this, 'render_person_metabox'],
            self::CPT,
            'side',
            'default'
        );
    }

    public function render_person_metabox($post)
    {
        wp_nonce_field('save_person_meta', self::NONCE_METABOX);
        $phone = get_post_meta($post->ID, self::META_PHONE, true);
        $email = get_post_meta($post->ID, self::META_EMAIL, true);
        echo '<p><label for="person_phone"><strong>' . esc_html__('Phone', 'person-directory') . '</strong></label><br/>';
        echo '<input name="person_phone" id="person_phone" type="text" class="widefat" value="' . esc_attr($phone) . '" /></p>';
        echo '<p><label for="person_email"><strong>' . esc_html__('Email', 'person-directory') . '</strong></label><br/>';
        echo '<input name="person_email" id="person_email" type="email" class="widefat" value="' . esc_attr($email) . '" /></p>';
        echo '<p>' . esc_html__('Use the featured image box to set a photo.', 'person-directory') . '</p>';
    }

    public function save_person_meta($post_id)
    {
        if (!isset($_POST[self::NONCE_METABOX]) || !wp_verify_nonce($_POST[self::NONCE_METABOX], 'save_person_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $phone = isset($_POST['person_phone']) ? $this->sanitize_phone(wp_unslash($_POST['person_phone'])) : '';
        $email = isset($_POST['person_email']) ? sanitize_email(wp_unslash($_POST['person_email'])) : '';
        update_post_meta($post_id, self::META_PHONE, $phone);
        update_post_meta($post_id, self::META_EMAIL, $email);
    }

    public function add_admin_columns($columns)
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['person_phone'] = __('Phone', 'person-directory');
                $new['person_email'] = __('Email', 'person-directory');
            }
        }
        return $new;
    }

    public function render_admin_columns($column, $post_id)
    {
        if ($column === 'person_phone') {
            echo esc_html(get_post_meta($post_id, self::META_PHONE, true));
        } elseif ($column === 'person_email') {
            $email = get_post_meta($post_id, self::META_EMAIL, true);
            if ($email) {
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            }
        }
    }

    public function register_admin_pages()
    {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            __('CSV Import', 'person-directory'),
            __('CSV Import', 'person-directory'),
            'manage_options',
            'person-csv-import',
            [$this, 'render_import_page']
        );
    }

    /**
     * Register the Gutenberg block that acts as a "widget" to place people on pages with per-instance labels
     */
    public function register_people_block()
    {
        // Register editor script (no build step; plain ES5-ish using WP globals)
        $handle = 'person-directory-people-block';
        $src = plugin_dir_url(dirname(__FILE__)) . 'assets/people-block.js';
        wp_register_script(
            $handle,
            $src,
            ['wp-blocks','wp-element','wp-components','wp-i18n','wp-data','wp-api-fetch','wp-block-editor'],
            '1.0.0',
            true
        );

        // Localize some defaults
        wp_localize_script($handle, 'PersonDirectoryBlockData', [
            'cpt' => self::CPT,
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        // Register block type with server-side render
        register_block_type('person-directory/people', [
            'api_version' => 2,
            'editor_script' => $handle,
            'render_callback' => [$this, 'render_people_block'],
            'attributes' => [
                // Array of { id: number, label: string }
                'entries' => [
                    'type' => 'array',
                    'default' => [],
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [ 'type' => 'integer' ],
                            'label' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
                'className' => [ 'type' => 'string' ],
            ],
            'supports' => [
                'html' => false,
            ],
        ]);

        // Optional shortcode for classic editor
        add_shortcode('person_directory_people', function ($atts) {
            $atts = shortcode_atts([
                'ids' => '', // comma-separated IDs
                'labels' => '', // pipe-separated labels matching order
            ], $atts, 'person_directory_people');
            $ids = array_filter(array_map('absint', explode(',', (string)$atts['ids'])));
            $labels = array_map('sanitize_text_field', explode('|', (string)$atts['labels']));
            $entries = [];
            foreach ($ids as $idx => $id) {
                $entries[] = [ 'id' => $id, 'label' => $labels[$idx] ?? '' ];
            }
            return $this->render_people_block(['entries' => $entries], '');
        });
    }

    public function enqueue_block_styles()
    {
        // Check if we're on frontend and not in admin
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // Enqueue the CSS file if not already enqueued
        if (!wp_style_is('person-directory-block-styles', 'enqueued')) {
            $css_url = plugin_dir_url(dirname(__FILE__)) . 'assets/view.css';

            wp_enqueue_style(
                    'person-directory-block-styles',
                    $css_url,
                    [],
                    '1.0.0'
            );

            // Mark as enqueued to prevent duplicate enqueuing
            wp_style_add_data('person-directory-block-styles', 'enqueued', true);
        }
    }

    /**
     * Server-side render callback for the people block
     */
    public function render_people_block($attributes, $content)
    {
        $this->enqueue_block_styles();

        $attributes = is_array($attributes) ? $attributes : [];
        $entries = isset($attributes['entries']) && is_array($attributes['entries']) ? $attributes['entries'] : [];

        if (empty($entries)) {
            return '';
        }

        // Prepare entries with post data
        $ids = [];
        $labelsById = [];

        foreach ($entries as $e) {
            $id = isset($e['id']) ? intval($e['id']) : 0;
            if ($id > 0) {
                $ids[] = $id;
                $labelsById[$id] = isset($e['label']) ? sanitize_text_field($e['label']) : '';
            }
        }

        if (empty($ids)) {
            return '';
        }

        // Fetch posts in one query
        $posts = get_posts([
                'post_type' => self::CPT,
                'post__in' => $ids,
                'orderby' => 'post__in',
                'numberposts' => -1,
                'suppress_filters' => false,
        ]);

        if (empty($posts)) {
            return '';
        }

        // Prepare data for template
        $template_data = [
                'entries' => [],
                'attributes' => $attributes,
                'plugin' => $this,
        ];

        $image_size = ['width' => self::IMAGE_WIDTH, 'height' => self::IMAGE_HEIGHT];

        foreach ( $posts as $post ) {
            if (has_post_thumbnail($post->ID)) {
                $image_src = get_the_post_thumbnail_url($post->ID, array($image_size['width'], $image_size['width']));
            } else {
                $image_src = trailingslashit(get_stylesheet_directory_uri()) . '../nrfc/images/person-placeholder.png';
            }

            $template_data['entries'][] = [
                    'name'  => $post->post_title,
                    'phone' => get_post_meta( $post->ID, PersonDirectory::META_PHONE, true ),
                    'email' => get_post_meta( $post->ID, PersonDirectory::META_EMAIL, true ),
                    'label' => $labelsById[ $post->ID ] ?? '',
                    'image_attrs' => [
                        'src' => esc_url($image_src),
                        'width' => $image_size['width'],
                        'height' => $image_size['height'],
                        'title' => esc_attr($post->post_title),
                        'alt' => esc_attr('Photo of ' . $post->post_title),
                    ],
            ];
        }

        ob_start();

        $template_path = $this->get_template_path('people-block.php');
        if (file_exists($template_path)) {
            $data = $template_data;
            include $template_path;
        } else {
            echo '<div class="notice notice-error">Template file not found</div>';
        }

        return ob_get_clean();
    }

    /**
     * Get the path to a template file
     */
    public function get_template_path($template_name)
    {
        // Check plugin templates directory first
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/' . $template_name;

        // Allow theme override (new folder first, legacy folder for backward compatibility)
        $theme_template = get_stylesheet_directory() . '/nrfc-person-directory/' . $template_name;
        $legacy_theme_template = get_stylesheet_directory() . '/person-directory/' . $template_name;

        // Use theme template if it exists, otherwise use plugin template
        if (file_exists($theme_template)) {
            return $theme_template;
        }

        if (file_exists($legacy_theme_template)) {
            return $legacy_theme_template;
        }

        return file_exists($plugin_template) ? $plugin_template : false;
    }

    public function render_import_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'person-directory'));
        }

        $results = null;
        if (!empty($_POST) && isset($_POST[self::NONCE_IMPORT]) && wp_verify_nonce($_POST[self::NONCE_IMPORT], 'person_import_action')) {
            $results = $this->handle_csv_upload();
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Person CSV Import', 'person-directory') . '</h1>';
        echo '<p>' . esc_html__('Upload a CSV file with the columns: name, phone, email, photo (URL).', 'person-directory') . '</p>';

	    echo '<h2>CSV format</h2>';
	    echo '<p>Header row required. Supported columns:</p>';
	    echo '<ul>';
	    echo '<li><code>name</code> (required)</li>';
	    echo '<li><code>phone</code> (optional)</li>';
	    echo '<li><code>email</code> (optional)</li>';
	    echo '<li><code>photo</code> (optional, remote image URL to set as featured image)</li>';
	    echo '<li><code>status</code> (publish|draft), default publish</li>';
	    echo '<li><code>post_id</code> (optional, update the specific person)</li>';
	    echo '</ul>';

        if (is_wp_error($results)) {
            echo '<div class="notice notice-error"><p>' . esc_html($results->get_error_message()) . '</p></div>';
        } elseif (is_array($results)) {
            echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('%d created, %d updated, %d images set, %d errors', 'person-directory'), $results['created'], $results['updated'], $results['images'], count($results['errors'])) . '</p></div>';
            if (!empty($results['errors'])) {
                echo '<div class="notice notice-warning"><ul>';
                foreach ($results['errors'] as $err) {
                    echo '<li>' . esc_html($err) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('person_import_action', self::NONCE_IMPORT);
        echo '<input type="file" name="person_csv" accept=".csv,text/csv" required /> ';
        submit_button(__('Import', 'person-directory'));
        echo '</form>';

        echo '<h2>' . esc_html__('Notes', 'person-directory') . '</h2>';
        echo '<ul>';
        echo '<li>' . esc_html__('The person "name" maps to the post title.', 'person-directory') . '</li>';
        echo '<li>' . esc_html__('If a person with the same name exists, they will be updated instead of created.', 'person-directory') . '</li>';
        echo '<li>' . esc_html__('The "photo" should be a publicly accessible image URL. It will be downloaded and set as the featured image.', 'person-directory') . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    public function register_people_widget(): void
    {
        if (class_exists('WP_Widget')) {
            register_widget(__NAMESPACE__ . '\\PersonListWidget');
        }
    }

    private function handle_csv_upload()
    {
        if (!isset($_FILES['person_csv']) || empty($_FILES['person_csv']['tmp_name'])) {
            return new \WP_Error('no_file', __('No CSV file uploaded.', 'person-directory'));
        }

        $file = $_FILES['person_csv'];
        if (!empty($file['error'])) {
            return new \WP_Error('upload_error', sprintf(__('Upload error: %s', 'person-directory'), (string)$file['error']));
        }

        $tmp = $file['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            return new \WP_Error('open_failed', __('Failed to open uploaded file.', 'person-directory'));
        }

        // Parse headers
        $headers = fgetcsv($fh, 0, ',', '"', "\\");
        if (!$headers) {
            fclose($fh);
            return new \WP_Error('bad_csv', __('CSV appears to be empty.', 'person-directory'));
        }
        $headers = array_map('trim', $headers);
        $map = [
            'name' => null,
            'phone' => null,
            'email' => null,
            'photo' => null,
        ];
        foreach ($headers as $idx => $h) {
            $key = strtolower($h);
            if (array_key_exists($key, $map)) {
                $map[$key] = $idx;
            }
        }
        if ($map['name'] === null) {
            fclose($fh);
            return new \WP_Error('missing_name', __('CSV must include a "name" column.', 'person-directory'));
        }

        // Prepare for media handling
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $created = 0; $updated = 0; $images = 0; $errors = [];

        while (($row = fgetcsv($fh, 0, ',', '"', "\\")) !== false) {
            // Skip empty rows
            if (count(array_filter($row, function ($v) { return trim((string)$v) !== ''; })) === 0) {
                continue;
            }

            $name = isset($row[$map['name']]) ? sanitize_text_field($row[$map['name']]) : '';
            if ($name === '') {
                $errors[] = __('Skipped a row with empty name.', 'person-directory');
                continue;
            }

            $phone = ($map['phone'] !== null && isset($row[$map['phone']])) ? $this->sanitize_phone($row[$map['phone']]) : '';
            $email = ($map['email'] !== null && isset($row[$map['email']])) ? sanitize_email($row[$map['email']]) : '';
            $photo = ($map['photo'] !== null && isset($row[$map['photo']])) ? esc_url_raw($row[$map['photo']]) : '';

            // Find existing by exact title match
            $existing = get_page_by_title($name, OBJECT, self::CPT);
            if ($existing) {
                $post_id = $existing->ID;
                $update_post = [
                    'ID' => $post_id,
                    'post_title' => $name,
                    'post_status' => 'publish',
                ];
                wp_update_post($update_post);
                $updated++;
            } else {
                $post_id = wp_insert_post([
                    'post_type' => self::CPT,
                    'post_title' => $name,
                    'post_status' => 'publish',
                ], true);
                if (is_wp_error($post_id)) {
                    $errors[] = sprintf(__('Failed to create "%s": %s', 'person-directory'), $name, $post_id->get_error_message());
                    continue;
                }
                $created++;
            }

            update_post_meta($post_id, self::META_PHONE, $phone);
            update_post_meta($post_id, self::META_EMAIL, $email);

            if ($photo) {
                $attach_id = $this->sideload_image_to_post($photo, $name, $post_id);
                if (is_wp_error($attach_id)) {
                    $errors[] = sprintf(__('Image for "%s" not set: %s', 'person-directory'), $name, $attach_id->get_error_message());
                } elseif ($attach_id) {
                    set_post_thumbnail($post_id, $attach_id);
                    $images++;
                }
            }
        }
        fclose($fh);

        return [
            'created' => $created,
            'updated' => $updated,
            'images' => $images,
            'errors' => $errors,
        ];
    }

    private function sideload_image_to_post($url, $desc, $post_id)
    {
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        // Check for download errors
        $file_check = wp_check_filetype($file_array['name']);
        if (empty($file_check['type'])) {
            @unlink($tmp);
            return new \WP_Error('invalid_image', __('Downloaded file is not a valid image.', 'person-directory'));
        }

        $id = media_handle_sideload($file_array, $post_id, $desc);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        return $id;
    }
}


// Widget to display selected people with per-instance labels (similar to SponsorTypeWidget)
class PersonListWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'person_list_widget',
            __('People (Person Directory)', 'person-directory'),
            ['description' => __('Display selected people with per-page labels.', 'person-directory')]
        );
    }

    public function form($instance)
    {
        $title  = isset($instance['title']) ? $instance['title'] : '';
        $ids    = isset($instance['ids']) ? $instance['ids'] : '';
        $labels = isset($instance['labels']) ? $instance['labels'] : '';

        $field_id = function($key){ return esc_attr($this->get_field_id($key)); };
        $field_name = function($key){ return esc_attr($this->get_field_name($key)); };

        // Fetch all persons to render as cards above the form (helper UI)
        $persons = get_posts([
            'post_type' => PersonDirectory::CPT,
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        //////////////////////////////////////
        $idToLabel = [];
        $idsArr = array_filter(array_map('absint', explode(',', $ids)));
        $labelsArr = array_map('sanitize_text_field', explode('|', $labels));
        foreach ($idsArr as $idx => $id) {
            $idToLabel[$id] = $labelsArr[$idx] ?? '';
        }

        $json = [];
        foreach ($persons as $p) {
            $json[] = [
                'id' => $p->ID,
                'title' => get_the_title($p),
                'selected' => in_array($p->ID, $idsArr),
                'label' => $idToLabel[$p->ID] ?? '',
            ];
        }
        echo '<div id="person-directory-admin-chooser" data-all-people="' . esc_attr(json_encode($json)) . '"></div>';

        //////////////////////////////////////

        ?>
        <style>
            .person-directory-admin-cards .person-trump-card{
                cursor: pointer;
                transition: border-color .15s ease, box-shadow .15s ease;
            }
            .person-directory-admin-cards .person-trump-card.is-selected{
                border-color: #2271b1 !important;
                box-shadow: 0 0 0 2px rgba(34, 113, 177, .15);
                background: #f5f5f5 !important;
            }
        </style>
        <p>
            <label for="<?php echo $field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $field_id('title'); ?>" name="<?php echo $field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p style="display: none;">
            <label for="<?php echo $field_id('ids'); ?>"><?php _e('Person IDs (comma-separated):', 'person-directory'); ?></label>
            <input class="widefat" id="<?php echo $field_id('ids'); ?>" name="<?php echo $field_name('ids'); ?>" type="text" value="<?php echo esc_attr($ids); ?>">
        </p>
        <p style="display: none;">
            <label for="<?php echo $field_id('labels'); ?>"><?php _e('Labels (pipe-separated, positionally matched):', 'person-directory'); ?></label>
            <input class="widefat" id="<?php echo $field_id('labels'); ?>" name="<?php echo $field_name('labels'); ?>" type="text" value="<?php echo esc_attr($labels); ?>">
        </p>
        <p>
            <em><?php _e('Tip: For a better selection UI, use the Gutenberg block on pages. This widget is a simple classic widget.', 'person-directory'); ?></em>
        </p>
        <div class="person-directory-admin-cards" data-ids-field="<?php echo $field_id('ids'); ?>" data-labels-field="<?php echo $field_id('labels'); ?>" style="margin:12px 0;">
            <!-- JS will populate this -->
        </div>
        <?php
        $js_url = plugins_url('assets/admin-chooser.js', dirname(__DIR__) . '/nrfc-person-directory.php');
        echo '<script src="' . esc_url($js_url) . '"></script>';
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');

        // Sanitize IDs
        $ids_raw = isset($new_instance['ids']) ? (string)$new_instance['ids'] : '';
        $ids = array_filter(array_map('absint', preg_split('/\s*,\s*/', $ids_raw)));
        $instance['ids'] = implode(',', $ids);

        // Sanitize labels (allow pipes as separators)
        $labels_raw = isset($new_instance['labels']) ? (string)$new_instance['labels'] : '';
        // Split by pipe, sanitize each, then re-join with single pipe
        $labels = array_map('sanitize_text_field', explode('|', $labels_raw));
        $labels = array_map(function($s){ return trim($s); }, $labels);
        $instance['labels'] = implode('|', $labels);

        return $instance;
    }

    public function widget($args, $instance)
    {
        $title  = isset($instance['title']) ? $instance['title'] : '';
        $ids    = isset($instance['ids']) ? $instance['ids'] : '';
        $labels = isset($instance['labels']) ? $instance['labels'] : '';

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        if (!empty($ids)) {
            // Reuse the existing shortcode which calls the dynamic renderer
            $shortcode = sprintf('[person_directory_people ids="%s" labels="%s"]', esc_attr($ids), esc_attr($labels));
            echo do_shortcode($shortcode);
        } else {
            // No IDs set; render nothing (consistent with block behavior)
        }

        echo $args['after_widget'];
    }
}

