<?php

namespace SponsorManagement;

class SponsorManagement
{
    public function __construct()
    {
        // Register the sponsor post type
        add_action('init', array($this, 'registerSponsorPostType'));
        
        // Add meta boxes for custom fields
        add_action('add_meta_boxes', array($this, 'addSponsorMetaBoxes'));
        
        // Save post meta
        add_action('save_post_sponsor', array($this, 'saveSponsorMeta'));
        
        // Add admin columns
        add_filter('manage_sponsor_posts_columns', array($this, 'addAdminColumns'));
        add_action('manage_sponsor_posts_custom_column', array($this, 'renderAdminColumns'), 10, 2);
        
        // Add CSV import submenu in admin
        add_action('admin_menu', array($this, 'registerImportSubmenu'));
    }

    /**
     * Activate the plugin
     */
    public function activate(): void
    {
        $this->registerSponsorPostType();
        flush_rewrite_rules();
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Register the CSV import submenu under Sponsors
     */
    public function registerImportSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=sponsor',
            'Import Sponsors',
            'Import from CSV',
            'edit_posts',
            'sponsor-import',
            array($this, 'renderImportPage')
        );
    }

    /**
     * Render the CSV import admin page
     */
    public function renderImportPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $results = null;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sponsor_import_nonce'])) {
            if (!wp_verify_nonce($_POST['sponsor_import_nonce'], 'sponsor_import')) {
                $errors[] = 'Security check failed.';
            } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Please upload a valid CSV file.';
            } else {
                $file = $_FILES['csv_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $errors[] = 'Only .csv files are allowed.';
                } else {
                    $results = $this->processCsvFile($file['tmp_name']);
                    if (is_wp_error($results)) {
                        $errors[] = $results->get_error_message();
                        $results = null;
                    }
                }
            }
        }

        echo '<div class="wrap">';
        echo '<h1>Import Sponsors from CSV</h1>';

        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $errors)) . '</p></div>';
        }

        if ($results) {
            echo '<div class="notice notice-success"><p>Import completed. ' . intval($results['created']) . ' created, ' . intval($results['updated']) . ' updated, ' . intval($results['skipped']) . ' skipped.</p></div>';
            if (!empty($results['messages'])) {
                echo '<ul style="list-style:disc;margin-left:20px;">';
                foreach ($results['messages'] as $msg) {
                    echo '<li>' . esc_html($msg) . '</li>';
                }
                echo '</ul>';
            }
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('sponsor_import', 'sponsor_import_nonce');
        echo '<p><input type="file" name="csv_file" accept=".csv" required></p>';
        echo '<p><label><input type="checkbox" name="update_existing" value="1" checked> Update existing sponsors when a match is found</label></p>';
        echo '<p><button type="submit" class="button button-primary">Run Import</button></p>';
        echo '</form>';

        echo '<h2>CSV format</h2>';
        echo '<p>Header row required. Supported columns:</p>';
        echo '<ul>';
        echo '<li><code>name</code> (required)</li>';
        echo '<li><code>url</code></li>';
        echo '<li><code>type</code> (club|gold|silver|bronze|associate)</li>';
        echo '<li><code>logo_url</code> (optional, remote image URL to set as featured image)</li>';
        echo '<li><code>status</code> (publish|draft), default publish</li>';
        echo '<li><code>post_id</code> (optional, update the specific sponsor)</li>';
        echo '</ul>';

        echo '<p>Matching logic: if <code>post_id</code> is provided, that post is updated; otherwise we match by exact <code>name</code> (post title). If a match is found and "Update existing" is checked, the record is updated, otherwise it is skipped.</p>';

        echo '<p>Example:</p>';
        echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:10px;">name,url,type,logo_url,status\nAcme Corp,https://acme.test,gold,https://example.com/acme.png,publish</pre>';

        echo '</div>';
    }

    /**
     * Process the uploaded CSV file and import sponsors
     * @param string $tmpPath
     * @return array|\WP_Error
     */
    private function processCsvFile(string $tmpPath)
    {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            return new \WP_Error('csv_open_error', 'Unable to open uploaded CSV.');
        }

        // Read header and detect delimiter
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return new \WP_Error('csv_empty', 'CSV file is empty.');
        }
        $delimiter = $this->detectDelimiter($firstLine);
        // Rewind and use fgetcsv
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return new \WP_Error('csv_header', 'Unable to parse CSV header.');
        }

        // Normalize headers to lowercase
        $headers = array_map(function ($h) { return strtolower(trim($h)); }, $headers);

        $required = ['name'];
        foreach ($required as $req) {
            if (!in_array($req, $headers, true)) {
                fclose($handle);
                return new \WP_Error('csv_missing_header', 'Missing required column: ' . $req);
            }
        }

        $colIndex = array_flip($headers);

        $created = 0; $updated = 0; $skipped = 0;
        $messages = [];
        $rowNum = 1; // already consumed header
        $updateExisting = isset($_POST['update_existing']);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim(implode('', $row)) === '') {
                continue; // skip empty lines
            }

            $data = [
                'post_id'  => isset($colIndex['post_id']) ? trim($row[$colIndex['post_id']]) : '',
                'name'     => trim($row[$colIndex['name']]),
                'url'      => isset($colIndex['url']) ? trim($row[$colIndex['url']]) : '',
                'type'     => isset($colIndex['type']) ? strtolower(trim($row[$colIndex['type']])) : '',
                'logo_url' => isset($colIndex['logo_url']) ? trim($row[$colIndex['logo_url']]) : '',
                'status'   => isset($colIndex['status']) ? strtolower(trim($row[$colIndex['status']])) : 'publish',
            ];

            if ($data['name'] === '') {
                $skipped++;
                $messages[] = "Row {$rowNum}: skipped (missing name).";
                continue;
            }

            // Validate type
            $valid_types = ['club','gold','silver','bronze','associate'];
            if ($data['type'] !== '' && !in_array($data['type'], $valid_types, true)) {
                $skipped++;
                $messages[] = "Row {$rowNum}: skipped (invalid type '" . $data['type'] . "').";
                continue;
            }

            $result = $this->createOrUpdateSponsorFromRow($data, $updateExisting);
            if (is_wp_error($result)) {
                $skipped++;
                $messages[] = "Row {$rowNum}: skipped (" . $result->get_error_message() . ").";
            } else {
                if ($result['action'] === 'created') { $created++; }
                if ($result['action'] === 'updated') { $updated++; }
                $messages[] = "Row {$rowNum}: " . $result['action'] . " (#" . $result['post_id'] . ")";
            }
        }

        fclose($handle);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'messages' => $messages,
        ];
    }

    /**
     * Detect common delimiters from the first line
     */
    private function detectDelimiter(string $line): string
    {
        $c = substr_count($line, ',');
        $s = substr_count($line, ';');
        $t = substr_count($line, "\t");
        if ($t >= $c && $t >= $s) return "\t";
        if ($s > $c) return ';';
        return ',';
    }

    /**
     * Create or update a sponsor based on row data
     * @param array $data
     * @param bool $updateExisting
     * @return array|\WP_Error
     */
    private function createOrUpdateSponsorFromRow(array $data, bool $updateExisting)
    {
        $post_id = 0;

        if (!empty($data['post_id'])) {
            $post = get_post((int)$data['post_id']);
            if ($post && $post->post_type === 'sponsor') {
                $post_id = (int)$post->ID;
            } else {
                return new \WP_Error('invalid_post', 'post_id does not reference a sponsor.');
            }
        } else {
            // Try to find by exact title match
            $existing = get_page_by_title($data['name'], OBJECT, 'sponsor');
            if ($existing) {
                $post_id = (int)$existing->ID;
            }
        }

        $valid_status = in_array($data['status'], ['publish','draft'], true) ? $data['status'] : 'publish';

        if ($post_id && !$updateExisting) {
            return new \WP_Error('exists', 'Existing sponsor found but update_existing is unchecked.');
        }

        if ($post_id) {
            // Update
            $p = [
                'ID' => $post_id,
                'post_title' => $data['name'],
                'post_status' => $valid_status,
                'post_type' => 'sponsor',
            ];
            $updated_id = wp_update_post($p, true);
            if (is_wp_error($updated_id)) {
                return $updated_id;
            }
            $this->updateSponsorMetaAndMedia($updated_id, $data);
            return ['action' => 'updated', 'post_id' => $updated_id];
        }

        // Create new
        $p = [
            'post_title' => $data['name'],
            'post_status' => $valid_status,
            'post_type' => 'sponsor',
        ];
        $new_id = wp_insert_post($p, true);
        if (is_wp_error($new_id)) {
            return $new_id;
        }
        $this->updateSponsorMetaAndMedia($new_id, $data);
        return ['action' => 'created', 'post_id' => $new_id];
    }

    /**
     * Update meta (url, type) and featured image from logo_url
     */
    private function updateSponsorMetaAndMedia(int $post_id, array $data): void
    {
        if (!empty($data['url'])) {
            update_post_meta($post_id, '_sponsor_url', esc_url_raw($data['url']));
        }
        if (!empty($data['type'])) {
            update_post_meta($post_id, '_sponsor_type', sanitize_text_field($data['type']));
        }
        if (!empty($data['logo_url'])) {
            // Ensure media functions are available
            if (!function_exists('media_sideload_image')) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            // Try to sideload image and set as featured
            $attachment_id = media_sideload_image($data['logo_url'], $post_id, $data['name'], 'id');
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, (int)$attachment_id);
            }
        }
    }

    /**
     * Register the sponsor post type
     */
    public function registerSponsorPostType(): void
    {
        register_post_type(
            'sponsor',
            [
                'labels' => [
                    'name' => 'Sponsors',
                    'singular_name' => 'Sponsor',
                    'add_new' => 'Add New Sponsor',
                    'add_new_item' => 'Add New Sponsor',
                    'edit_item' => 'Edit Sponsor',
                    'new_item' => 'New Sponsor',
                    'view_item' => 'View Sponsor',
                    'search_items' => 'Search Sponsors',
                    'not_found' => 'No sponsors found',
                    'not_found_in_trash' => 'No sponsors found in trash'
                ],
                'public' => true,
                'has_archive' => true,
                'supports' => ['title', 'thumbnail'],
                'menu_icon' => 'dashicons-businessman',
                'menu_position' => 5,
                'show_in_rest' => true,
            ]
        );
    }

    /**
     * Add meta boxes for sponsor custom fields
     */
    public function addSponsorMetaBoxes(): void
    {
        add_meta_box(
            'sponsor_details',
            'Sponsor Details',
            array($this, 'renderSponsorMetaBox'),
            'sponsor',
            'normal',
            'high'
        );
    }

    /**
     * Render the sponsor meta box
     */
    public function renderSponsorMetaBox($post): void
    {
        wp_nonce_field('save_sponsor_details', 'sponsor_details_nonce');
        
        // Get existing meta values
        $url = get_post_meta($post->ID, '_sponsor_url', true);
        $type = get_post_meta($post->ID, '_sponsor_type', true);
        
        // Sponsor types
        $sponsor_types = [
            'club' => 'Club',
            'gold' => 'Gold',
            'silver' => 'Silver',
            'bronze' => 'Bronze'
        ];
        
        // URL field
        echo '<div style="margin-bottom:15px;">';
        echo '<label for="sponsor_url" style="display:block;margin-bottom:5px;"><strong>Sponsor URL:</strong></label>';
        echo '<input type="url" name="sponsor_url" id="sponsor_url" value="' . esc_attr($url) . '" style="width:100%;">';
        echo '<p class="description">Enter the website URL for this sponsor</p>';
        echo '</div>';
        
        // Type field
        echo '<div style="margin-bottom:15px;">';
        echo '<label for="sponsor_type" style="display:block;margin-bottom:5px;"><strong>Sponsor Type:</strong></label>';
        echo '<select name="sponsor_type" id="sponsor_type" style="width:100%;">';
        
        foreach ($sponsor_types as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($type, $value, false) . '>' . esc_html($label) . '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Select the type of sponsorship</p>';
        echo '</div>';
        
        // Note about logo
        echo '<div style="margin-bottom:15px;">';
        echo '<p><strong>Logo:</strong> Use the Featured Image section to upload and set the sponsor\'s logo.</p>';
        echo '</div>';
        
        // Note about name
        echo '<div style="margin-bottom:15px;">';
        echo '<p><strong>Name:</strong> Use the title field at the top to set the sponsor\'s name.</p>';
        echo '</div>';
    }

    /**
     * Save the sponsor meta data
     */
    public function saveSponsorMeta($post_id): void
    {
        // Check if nonce is set
        if (!isset($_POST['sponsor_details_nonce']) || !wp_verify_nonce($_POST['sponsor_details_nonce'], 'save_sponsor_details')) {
            return;
        }
        
        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save URL
        if (isset($_POST['sponsor_url'])) {
            update_post_meta($post_id, '_sponsor_url', esc_url_raw($_POST['sponsor_url']));
        }
        
        // Save type
        if (isset($_POST['sponsor_type'])) {
            $valid_types = ['club', 'gold', 'silver', 'bronze'];
            $type = sanitize_text_field($_POST['sponsor_type']);
            
            if (in_array($type, $valid_types)) {
                update_post_meta($post_id, '_sponsor_type', $type);
            }
        }
    }

    /**
     * Add custom columns to the sponsor admin list
     */
    public function addAdminColumns($columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add our columns after the title
            if ($key === 'title') {
                $new_columns['sponsor_type'] = 'Type';
                $new_columns['sponsor_url'] = 'URL';
            }
        }
        
        return $new_columns;
    }

    /**
     * Render the custom column content
     */
    public function renderAdminColumns($column, $post_id): void
    {
        switch ($column) {
            case 'sponsor_type':
                $type = get_post_meta($post_id, '_sponsor_type', true);
                $types = [
                    'club' => 'Club',
                    'gold' => 'Gold',
                    'silver' => 'Silver',
                    'bronze' => 'Bronze'
                ];
                echo isset($types[$type]) ? esc_html($types[$type]) : '—';
                break;
                
            case 'sponsor_url':
                $url = get_post_meta($post_id, '_sponsor_url', true);
                if (!empty($url)) {
                    echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_url($url) . '</a>';
                } else {
                    echo '—';
                }
                break;
        }
    }
}
