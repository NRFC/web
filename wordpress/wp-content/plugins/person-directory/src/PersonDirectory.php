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

    /**
     * Server-side render callback for the people block
     */
    public function render_people_block($attributes, $content)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $entries = isset($attributes['entries']) && is_array($attributes['entries']) ? $attributes['entries'] : [];
        if (empty($entries)) {
            return '';
        }

        // Preserve order based on entries array
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

        // Build output
        ob_start();
        $class = 'person-directory-people';
        if (!empty($attributes['className'])) {
            $class .= ' ' . sanitize_html_class($attributes['className']);
        }
        echo '<div class="' . esc_attr($class) . '">';
        echo '<ul class="person-directory-list" style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:16px;">';
        foreach ($posts as $p) {
            $label = $labelsById[$p->ID] ?? '';
            $title = get_the_title($p);
            $permalink = get_permalink($p);
            $phone = get_post_meta($p->ID, self::META_PHONE, true);
            $email = get_post_meta($p->ID, self::META_EMAIL, true);
            $thumb = get_the_post_thumbnail($p->ID, 'thumbnail', ['class' => 'person-photo', 'loading' => 'lazy', 'alt' => esc_attr($title)]);
            if (!$thumb) {
                $placeholder = trailingslashit(get_stylesheet_directory_uri()) . '../nrfc/images/person-placeholder.png';
                $thumb = '<img src="' . esc_url($placeholder) . '" class="person-photo" loading="lazy" alt="' . esc_attr($title) . '" height="150" width="150" style="border-radius:5%;object-fit:cover;display:block;" />';
            }
            echo '<li class="person-directory-item" style="border:1px solid #ddd;border-radius:8px;padding:12px;box-sizing:border-box;display:flex;gap:10px;align-items:flex-start;min-width:240px;flex:1 1 280px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.06);">';
            echo '<a href="' . esc_url($permalink) . '" class="person-photo-link">' . $thumb . '</a>';
            echo '<div class="person-info">';
            echo '<div class="person-name"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></div>';
            if ($label !== '') {
                echo '<div class="person-label">' . esc_html($label) . '</div>';
            }
            if ($phone) {
                echo '<div class="person-phone">' . esc_html($phone) . '</div>';
            }
            if ($email) {
                echo '<div class="person-email"><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></div>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
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
        ?>
        <style>
            .person-directory-admin-cards .person-trump-card{
                cursor: pointer;
                transition: border-color .15s ease, box-shadow .15s ease;
            }
            .person-directory-admin-cards .person-trump-card.is-selected{
                border-color: #2271b1 !important; /* WP primary */
                box-shadow: 0 0 0 2px rgba(34, 113, 177, .15);
            }
        </style>
        <div class="person-directory-admin-cards" data-ids-field="<?php echo $field_id('ids'); ?>" data-labels-field="<?php echo $field_id('labels'); ?>" style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;">
            <?php if (!empty($persons)): ?>
                <?php foreach ($persons as $p): ?>
                    <?php
                        $title_p = get_the_title($p);
                        $thumb   = get_the_post_thumbnail($p->ID, 'thumbnail', ['style' => 'display:block;width:100%;height:auto;border-radius:6px;']);
                        $phone   = get_post_meta($p->ID, PersonDirectory::META_PHONE, true);
                        $email   = get_post_meta($p->ID, PersonDirectory::META_EMAIL, true);
                    ?>
                    <div class="person-trump-card" data-person-id="<?php echo (int)$p->ID; ?>" style="width:160px;border:1px solid #ddd;border-radius:8px;padding:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.06);">
                        <div class="person-trump-photo" style="margin-bottom:6px;">
                            <?php echo $thumb ? $thumb : '<div style="width:100%;height:120px;background:#f6f7f7;border-radius:6px;"></div>'; ?>
                        </div>
	                    <div class="person-trump-title" style="font-weight:600;font-size:13px;line-height:1.3;margin-bottom:4px;">
		                    <?php echo esc_html($title_p); ?>
	                    </div>
	                    <div class="person-trump-role" style="margin-top:6px;">
		                    <label style="display:block;font-size:11px;color:#555;margin-bottom:2px;">
			                    <?php echo esc_html__('Role/Position', 'person-directory'); ?>
		                    </label>
		                    <input type="text" class="widefat" placeholder="<?php echo esc_attr__('Role/Position', 'person-directory'); ?>" style="width:100%;box-sizing:border-box;font-size:12px;padding:4px 6px;" />
	                    </div>
                        <div class="person-trump-meta" style="font-size:12px;color:#555;">
                            <div><?php echo esc_html__('ID', 'person-directory'); ?>: <code><?php echo (int)$p->ID; ?></code></div>
                            <?php if (!empty($phone)): ?>
                                <div><?php echo esc_html($phone); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($email)): ?>
                                <div><?php echo esc_html($email); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="margin:12px 0;"><?php echo esc_html__('No persons found yet. Add some People to the directory to see them here.', 'person-directory'); ?></p>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            var container = document.currentScript && document.currentScript.previousElementSibling;
            if(!container || !container.classList.contains('person-directory-admin-cards')){
                container = document.querySelector('.person-directory-admin-cards');
            }
            if(!container) return;

            var idsFieldId = container.getAttribute('data-ids-field');
            var labelsFieldId = container.getAttribute('data-labels-field');
            var idsInput = idsFieldId ? document.getElementById(idsFieldId) : null;
            var labelsInput = labelsFieldId ? document.getElementById(labelsFieldId) : null;
            if(!idsInput || !labelsInput){
                document.addEventListener('DOMContentLoaded', function(){
                    idsInput = idsFieldId ? document.getElementById(idsFieldId) : null;
                    labelsInput = labelsFieldId ? document.getElementById(labelsFieldId) : null;
                    if(!idsInput || !labelsInput) return; // give up silently
                    setup();
                });
            } else {
                setup();
            }

            function setup(){
            function parseIds(){
                var raw = (idsInput.value || '').trim();
                if(!raw) return [];
                return raw.split(',').map(function(s){ return s.trim(); }).filter(function(s){ return s !== ''; });
            }
            function parseLabels(){
                var raw = (labelsInput.value || '').trim();
                if(!raw) return [];
                return raw.split('|');
            }
            function write(idsArr, labelsArr){
                idsInput.value = idsArr.join(',');
                labelsInput.value = labelsArr.join('|');
            }
            function syncLengths(idsArr, labelsArr){
                // Ensure labels array matches ids length
                if(labelsArr.length < idsArr.length){
                    while(labelsArr.length < idsArr.length) labelsArr.push('');
                } else if(labelsArr.length > idsArr.length){
                    labelsArr.length = idsArr.length;
                }
            }
            function indexOfId(idsArr, id){
                id = String(id);
                for(var i=0;i<idsArr.length;i++) if(String(idsArr[i]) === id) return i;
                return -1;
            }
            function addId(id, label){
                var idsArr = parseIds();
                var labelsArr = parseLabels();
                var idx = indexOfId(idsArr, id);
                if(idx === -1){
                    idsArr.push(String(id));
                    syncLengths(idsArr, labelsArr);
                    labelsArr[idsArr.length - 1] = label || '';
                    write(idsArr, labelsArr);
                } else {
                    // Update label at existing index
                    syncLengths(idsArr, labelsArr);
                    labelsArr[idx] = label || '';
                    write(idsArr, labelsArr);
                }
            }
            function removeId(id){
                var idsArr = parseIds();
                var labelsArr = parseLabels();
                var idx = indexOfId(idsArr, id);
                if(idx !== -1){
                    idsArr.splice(idx, 1);
                    labelsArr.splice(idx, 1);
                    write(idsArr, labelsArr);
                }
            }
            function updateLabel(id, label){
                var idsArr = parseIds();
                var labelsArr = parseLabels();
                var idx = indexOfId(idsArr, id);
                if(idx !== -1){
                    syncLengths(idsArr, labelsArr);
                    labelsArr[idx] = label || '';
                    write(idsArr, labelsArr);
                }
            }
            function labelForId(id){
                var idsArr = parseIds();
                var labelsArr = parseLabels();
                var idx = indexOfId(idsArr, id);
                if(idx !== -1){
                    return labelsArr[idx] || '';
                }
                return '';
            }

            // Toggle selection via clicking the card (excluding interactive controls)
            container.addEventListener('click', function(e){
                if (e.target.closest('input, textarea, select, label, a, button')) return;
                var card = e.target.closest('.person-trump-card');
                if(!card) return;
                var id = card.getAttribute('data-person-id');
                var roleInput = card.querySelector('.person-trump-role input[type="text"]');
                var roleVal = roleInput ? roleInput.value : '';
                var willSelect = !card.classList.contains('is-selected');
                if(willSelect){
                    card.classList.add('is-selected');
                    addId(id, roleVal);
                } else {
                    card.classList.remove('is-selected');
                    removeId(id);
                }
            });

            // Keep labels in sync when typing inside selected cards
            container.addEventListener('input', function(e){
                if(e.target && e.target.matches('.person-trump-role input[type="text"]')){
                    var card = e.target.closest('.person-trump-card');
                    if(!card) return;
                    if(!card.classList.contains('is-selected')) return;
                    var id = card.getAttribute('data-person-id');
                    updateLabel(id, e.target.value || '');
                }
            });

            // Initialize selection states from existing ids/labels
            var cards = container.querySelectorAll('.person-trump-card');
            cards.forEach(function(card){
                var id = card.getAttribute('data-person-id');
                var lbl = labelForId(id);
                if(indexOfId(parseIds(), id) !== -1){
                    card.classList.add('is-selected');
                }
                var input = card.querySelector('.person-trump-role input[type="text"]');
                if(input && lbl) input.value = lbl;
            });
            }
        })();
        </script>
        <p>
            <label for="<?php echo $field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $field_id('title'); ?>" name="<?php echo $field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $field_id('ids'); ?>"><?php _e('Person IDs (comma-separated):', 'person-directory'); ?></label>
            <input class="widefat" id="<?php echo $field_id('ids'); ?>" name="<?php echo $field_name('ids'); ?>" type="text" value="<?php echo esc_attr($ids); ?>">
            <small><?php _e('Example: 12,45,78', 'person-directory'); ?></small>
        </p>
        <p>
            <label for="<?php echo $field_id('labels'); ?>"><?php _e('Labels (pipe-separated, positionally matched):', 'person-directory'); ?></label>
            <input class="widefat" id="<?php echo $field_id('labels'); ?>" name="<?php echo $field_name('labels'); ?>" type="text" value="<?php echo esc_attr($labels); ?>">
            <small><?php _e('Example: Captain|Coach|Manager', 'person-directory'); ?></small>
        </p>
        <p>
            <em><?php _e('Tip: For a better selection UI, use the Gutenberg block on pages. This widget is a simple classic widget.', 'person-directory'); ?></em>
        </p>
        <?php
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
