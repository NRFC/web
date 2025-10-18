<?php

namespace RugbyPlayerCards;

class RugbyPlayerCards
{
    public function __construct()
    {
        add_action('init', array($this, 'registerPlayerPostType'));
        add_action('manage_rugby_player_posts_custom_column', array($this, 'renderAdminColumns'), 10, 2);
        add_action('pre_get_posts', array($this, 'handleDobSorting'));
        add_action('add_meta_boxes', array($this, 'addRfuidMetaBox'));
        add_action('add_meta_boxes', array($this, 'addPlayerMetaBoxes'));
        add_action('save_post_rugby_player', array($this, 'savePlayerMeta'));
        add_action('init', array($this, 'addPlayerRewriteRule'));
        add_action('admin_menu', array($this, 'addImportMenuItem'));
        add_action('admin_post_rugby_player_import', array($this, 'handleImportRequest'));
        add_filter('query_vars', array($this, 'addQueryVars'));
        // @phpstan-ignore return.void
        add_action('template_include', array($this, 'playerCardTemplate'));
        add_action(
            'wp_enqueue_scripts',
            function () {
                if (get_query_var('academy_players_page')) {
                    wp_enqueue_style(
                        'rugby-player-cards',
                        plugin_dir_url(__FILE__) . 'assets/admin.css',
                        [],
                        '1.0'
                    );
                }
            }
        );
        add_action(
            'init',
            function () {
                add_rewrite_rule('^academy/players/?$', 'index.php?academy_players_page=1', 'top');
            }
        );
        add_action(
            'template_redirect',
            function () {
                if (get_query_var('academy_players_page')) {
                    include plugin_dir_path(__FILE__) . 'players-page.php';
                    exit;
                }
            }
        );
        add_filter('manage_rugby_player_posts_columns', array($this, 'addAdminColumns'));
        add_filter('manage_edit-rugby_player_sortable_columns', array($this, 'makeDobSortable'));
        add_filter(
            'query_vars',
            function ($vars) {
                $vars[] = 'academy_players_page';
                $vars[] = 'school_year';
                return $vars;
            }
        );
        add_filter(
            'upload_size_limit',
            function ($size) {
                if (current_user_can('manage_options')) {
                    return 2 * 1024 * 1024; // 2MB
                }
                return $size;
            }
        );
        add_filter(
            'upload_mimes',
            function ($mimes) {
                $mimes['json'] = 'application/json';
                return $mimes;
            }
        );
    }

    public function activate(): void
    {
        $this->registerPlayerPostType();
        $this->createCardsTable();
        $this->addPlayerRewriteRule();
        flush_rewrite_rules();
    }

    public function registerPlayerPostType(): void
    {
        register_post_type(
            'rugby_player',
            [
            'labels' => [
                'name' => 'Rugby Players',
                'singular_name' => 'Rugby Player'
            ],
            'public' => true,
            'has_archive' => false,
            'supports' => ['title', 'thumbnail', 'editor'],
            'menu_icon' => 'dashicons-groups'
            ]
        );
    }

    public function createCardsTable(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rugby_player_cards';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                player_id mediumint(9) NOT NULL,
                card_date date NOT NULL,
                strengths text NOT NULL,
                work_ons text NOT NULL,
                positions varchar(255) NOT NULL,
                PRIMARY KEY (id),
                KEY player_id (player_id)
            ) $charset_collate;";

            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function addAdminColumns($columns): array
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['dob'] = 'Age';
                $new_columns['rfuid'] = 'RFID';
            }
        }
        return $new_columns;
    }

    public function addImportMenuItem(): void
    {
        add_submenu_page(
            'edit.php?post_type=rugby_player',
            'Import Players',
            'Import Data',
            'manage_options',
            'rugby-player-import',
            array($this, 'renderImportPage')
        );
    }

    public function addRfuidMetaBox(): void
    {
        add_meta_box(
            'player_identification',  // Changed from 'player_rfuid'
            'Player Identification',
            array($this, 'renderPlayerMetaBox'),
            'rugby_player',
            'normal',
            'default'
        );
    }

    public function addPlayerRewriteRule(): void
    {
        add_rewrite_rule('^players/([0-9]+)/?$', 'index.php?player_id=$matches[1]', 'top');
    }

    public function addQueryVars($vars): array
    {
        $vars[] = 'player_id';
        return $vars;
    }

    public function addPlayerMetaBoxes(): void
    {
        add_meta_box(
            'player_cards',
            'Player Development Cards',
            array($this, 'renderCardsMetaBox'),
            'rugby_player',
            'normal',
            'default'
        );
    }

    public function makeDobSortable($columns): array
    {
        $columns['dob'] = 'dob';
        return $columns;
    }

    public function handleDobSorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($orderby = $query->get('orderby')) {
            if ($orderby === 'dob') {
                $query->set('meta_key', '_player_dob');
                $query->set('orderby', 'meta_value');
            }
        }
    }

    public function handleImportRequest(): void
    {
        check_admin_referer('rugby_player_import');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['idps_json']) || $_FILES['idps_json']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        // Verify file type
        $file_info = wp_check_filetype($_FILES['idps_json']['name']);
        if ($file_info['ext'] !== 'json') {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        // Get file contents
        $json_content = file_get_contents($_FILES['idps_json']['tmp_name']);
        $players = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        $overwrite = isset($_POST['overwrite_existing']) && $_POST['overwrite_existing'] === '1';
        $import_result = $this->processPlayerImport($players, $overwrite);

        wp_redirect(
            admin_url(
                'edit.php?post_type=rugby_player&page=rugby-player-import&import=' .
                ($import_result ? 'success' : 'error')
            )
        );
        exit;
    }

    public function processPlayerImport($players, $overwrite = true): bool
    {
        $success_count = 0;

        foreach ($players as $player_id => $player_data) {
            // Get most recent evaluation date
            $latest_date = array_keys($player_data)[0];
            $evaluation = $player_data[$latest_date];

            $dob = array_key_exists('dob', $evaluation) ? sanitize_text_field($evaluation['dob']) : null;

            // Prepare player data
            $player_post = array(
                'post_title'   => sanitize_text_field($evaluation['name']),
                'post_type'    => 'rugby_player',
                'post_status'  => 'publish',
                'meta_input'   => array(
                    '_player_rfuid' => sanitize_text_field($player_id),
                    '_player_dob' => $dob,
                )
            );

            // Check if player exists
            $existing = get_posts(
                array(
                'post_type' => 'rugby_player',
                'meta_key' => '_player_rfuid',
                'meta_value' => $player_id,
                'posts_per_page' => 1
                )
            );

            // Skip if exists and not overwriting
            if ($existing && !$overwrite) {
                continue;
            }

            // Insert/update player
            if ($existing) {
                $player_post['ID'] = $existing[0]->ID;
                $post_id = wp_update_post($player_post);
            } else {
                $post_id = wp_insert_post($player_post);
            }

            // @phpstan-ignore function.impossibleType
            if ($post_id && !is_wp_error($post_id)) {
                // Save positions
                if (!empty($evaluation['positions'])) {
                    update_post_meta($post_id, '_primary_position', sanitize_text_field($evaluation['positions'][0]));
                    update_post_meta(
                        $post_id,
                        '_player_positions',
                        array_map(
                            'sanitize_text_field',
                            $evaluation['positions']
                        )
                    );
                }

                // Import evaluation as a card
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'rugby_player_cards',
                    array(
                    'player_id' => $post_id,
                    'card_date' => sanitize_text_field($latest_date),
                    'strengths' => sanitize_textarea_field($evaluation['strengths']),
                    'work_ons' => !empty($evaluation['areas_for_development']) ?
                        implode("\n", array_map('sanitize_text_field', $evaluation['areas_for_development'])) : '',
                    'positions' => !empty($evaluation['positions']) ?
                        implode(', ', array_map('sanitize_text_field', $evaluation['positions'])) : ''
                    )
                );

                $success_count++;
            }
        }

        return $success_count > 0;
    }

    public function importPlayerData(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $json_file = plugin_dir_path(__FILE__) . 'data/idps.json';

        if (!file_exists($json_file)) {
            wp_die('JSON file not found');
        }

        $json_data = file_get_contents($json_file);
        $players = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die('Invalid JSON data');
        }

        foreach ($players as $player_id => $player_data) {
            // Get most recent evaluation date
            $latest_date = array_keys($player_data)[0];
            $evaluation = $player_data[$latest_date];

            // Create/update player
            $player_post = array(
                'post_title' => $evaluation['name'],
                'post_type' => 'rugby_player',
                'post_status' => 'publish',
                'meta_input' => array(
                    '_player_rfuid' => $player_id,
                    '_player_dob' => ''
                )
            );

            // Insert or update player
            $existing = get_posts(
                array(
                'post_type' => 'rugby_player',
                'meta_key' => '_player_rfuid',
                'meta_value' => $player_id,
                'posts_per_page' => 1
                )
            );

            if ($existing) {
                $player_post['ID'] = $existing[0]->ID;
                $post_id = wp_update_post($player_post);
            } else {
                $post_id = wp_insert_post($player_post);
            }

            // @phpstan-ignore function.impossibleType
            if ($post_id && !is_wp_error($post_id)) {
                // Save positions (for admin display)
                update_post_meta($post_id, '_primary_position', $evaluation['positions'][0]);
                update_post_meta($post_id, '_player_positions', $evaluation['positions']);

                // Import evaluation as a card
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'rugby_player_cards',
                    array(
                    'player_id' => $post_id,
                    'card_date' => $latest_date,
                    'strengths' => $evaluation['strengths'],
                    'work_ons' => implode("\n", $evaluation['areas_for_development']),
                    'positions' => implode(', ', $evaluation['positions'])
                    )
                );
            }
        }

        wp_redirect(admin_url('edit.php?post_type=rugby_player&import=success'));
        exit;
    }

    public function savePlayerMeta($post_id): void
    {
        if (
            !isset($_POST['player_cards_nonce'])
            || !wp_verify_nonce(
                $_POST['player_cards_nonce'],
                'save_player_cards'
            )
        ) {
            return;
        }

        if (isset($_POST['add_new_card'])) {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'rugby_player_cards',
                [
                'player_id' => $post_id,
                'card_date' => sanitize_text_field($_POST['new_card_date']),
                'strengths' => sanitize_textarea_field($_POST['new_card_strengths']),
                'work_ons' => sanitize_textarea_field($_POST['new_card_work_ons']),
                'positions' => isset($_POST['new_card_positions']) ?
                    implode(', ', array_map('sanitize_text_field', $_POST['new_card_positions'])) : ''
                ]
            );
        }

        if (isset($_POST['player_rfuid_nonce']) && wp_verify_nonce($_POST['player_rfuid_nonce'], 'save_player_rfuid')) {
            if (isset($_POST['player_rfuid'])) {
                update_post_meta($post_id, '_player_rfuid', sanitize_text_field($_POST['player_rfuid']));
            } else {
                delete_post_meta($post_id, '_player_rfuid');
            }
        }

        if (isset($_POST['player_dob'])) {
            update_post_meta($post_id, '_player_dob', sanitize_text_field($_POST['player_dob']));
        } else {
            delete_post_meta($post_id, '_player_dob');
        }
    }



    public function playerCardTemplate($template): string
    {
        if (get_query_var('player_id')) {
            return plugin_dir_path(__FILE__) . 'templates/player-card.php';
        }
        return $template;
    }

    public function renderAdminColumns($column, $post_id): void
    {
        static $seen = [];

        $key = $column . '-' . $post_id;

        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        if ($column === 'dob') {
            if ($dob = get_post_meta($post_id, '_player_dob', true)) {
                $age = date_diff(date_create($dob), date_create('today'))->y;
                echo esc_html($age . ' years');
            } else {
                echo '—';
            }
        }

        if ($column === 'rfuid') {
            echo esc_html(get_post_meta($post_id, '_player_rfuid', true) ?: '—');
        }
    }

    public function renderCardsMetaBox($post): void
    {
        wp_nonce_field('save_player_cards', 'player_cards_nonce');
        global $wpdb;

        $cards = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rugby_player_cards
             WHERE player_id = %d ORDER BY card_date DESC",
                $post->ID
            )
        );

        echo '<div class="new-card-form" style="padding:20px;background:#f5f5f5;margin-bottom:20px;">';
        echo '<h3>Add New Card</h3>';
        echo '<div style="margin-bottom:15px;">';
        echo '<label style="display:block;margin-bottom:5px;">';
        echo '<strong>Date:</strong>';
        echo '</label>';
        echo '<input type="date" name="new_card_date" value="' .
            date('Y-m-d') . '" style="width:100%;padding:8px;"></div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label style="display:block;margin-bottom:5px;">';
        echo '<strong>Strengths:</strong>';
        echo '</label>';
        echo '<textarea name="new_card_strengths" rows="3" style="width:100%;padding:8px;"></textarea></div>';

        echo '<div style="margin-bottom:15px;">';
        echo '<label style="display:block;margin-bottom:5px;">';
        echo '<strong>Areas to Work On:</strong></label>';
        echo '<textarea name="new_card_work_ons" rows="3" style="width:100%;padding:8px;">';
        echo '</textarea></div>';

        $all_positions = [
                'Prop', 'Hooker', 'Lock', 'Flanker', 'Number 8', 'Scrum-half',
            'Fly-half', 'Centre', 'Winger', 'Fullback'
        ];
        echo '<div style="margin-bottom:15px;">';
        echo '<label style="display:block;margin-bottom:5px;">';
        echo '<strong>Positions:</strong>';
        echo '</label>';
        foreach ($all_positions as $pos) {
            echo '<label style="margin-right:10px;">';
            echo '<input type="checkbox" name="new_card_positions[]" value="' . esc_attr($pos) . '"> ' . esc_html($pos);
            echo '</label>';
        }
        echo '</div>';

        echo '<button type="submit" name="add_new_card" class="button button-primary">Add Card</button>';
        echo '</div>';

        echo '<div class="card-history">';
        echo '<h3>Card History</h3>';
        if ($cards) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Date</th><th>Positions</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($cards as $card) {
                echo '<tr>';
                echo '<td>' . date('M j, Y', strtotime($card->card_date)) . '</td>';
                echo '<td>' . esc_html($card->positions) . '</td>';
                echo '<td><a href="#" class="view-card" data-card-id="' . $card->id . '">View</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No cards recorded yet.</p>';
        }
        echo '</div>';
    }

    public function renderPlayerMetaBox($post): void
    {

        wp_nonce_field('save_player_identification', 'player_identification_nonce');
        $rfuid = get_post_meta($post->ID, '_player_rfuid', true);
        $dob = get_post_meta($post->ID, '_player_dob', true);

        echo '<label for="player_rfuid" style="display:block;margin-bottom:5px;">RFID Tag ID:</label>';
        echo '<input type="text" name="player_rfuid" id="player_rfuid" value="' .
            esc_attr($rfuid) . '" style="width:100%;margin-bottom:15px;">';

        echo '<label for="player_dob" style="display:block;margin-bottom:5px;">Date of Birth:</label>';
        echo '<input type="date" name="player_dob" id="player_dob" value="' . esc_attr($dob) . '" style="width:100%">';
        echo '<p class="description">Format: YYYY-MM-DD</p>';
    }
    public function renderImportPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Import Player Data</h1>

            <?php if (isset($_GET['import'])) : ?>
                <div class="notice notice-<?php echo $_GET['import'] === 'success' ? 'success' : 'error'; ?>">
                    <p>
                        <?php
                            echo $_GET['import'] === 'success' ? 'Import completed successfully!' : 'Import failed!';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:600px;">
                <h2>Upload JSON File</h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="rugby_player_import">
                    <?php wp_nonce_field('rugby_player_import'); ?>

                    <p>
                        <label for="idps_json">Select IDPS JSON file:</label><br>
                        <input type="file" name="idps_json" id="idps_json" accept=".json" required>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="overwrite_existing" value="1" checked>
                            Overwrite existing player data
                        </label>
                    </p>

                    <button type="submit" class="button button-primary">Upload & Import</button>
                </form>
            </div>
        </div>
        <?php
    }
}