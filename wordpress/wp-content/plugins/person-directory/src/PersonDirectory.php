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
            'normal',
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
        $headers = fgetcsv($fh);
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

        while (($row = fgetcsv($fh)) !== false) {
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
