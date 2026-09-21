<?php

namespace NRFCMatchReports;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Match Reports Class
 */
class MatchReports
{
    const CPT = 'match_report';

    // Meta keys
    const META_FIXTURE_ID    = '_match_report_fixture_id';
    const META_SCORE_FOR      = '_match_report_score_for';
    const META_SCORE_AGAINST  = '_match_report_score_against';
    const META_GALLERY        = '_match_report_gallery';

    const NONCE_METABOX       = 'match_report_metabox_nonce';

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_match_report_meta']);

        // Add custom columns in admin list
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // Enqueue admin scripts for gallery management
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Enqueue front-end scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        // Display gallery on front-end
        add_filter('the_content', [$this, 'display_gallery_in_content']);

        // Add "Create Match Report" column to Fixtures
        add_filter('manage_fixture_posts_columns', [$this, 'add_fixture_admin_columns']);
        add_action('manage_fixture_posts_custom_column', [$this, 'render_fixture_admin_columns'], 10, 2);

        // Register Gutenberg block
        add_action('init', [$this, 'register_match_reports_block']);

        // Register Widget
        add_action('widgets_init', [$this, 'register_match_reports_widget']);
    }

    public function activate(): void
    {
        $this->register_post_type();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Register Match Report Custom Post Type
     */
    public function register_post_type()
    {
        $labels = [
            'name'               => _x('Match Reports', 'post type general name', 'nrfc-match-reports'),
            'singular_name'      => _x('Match Report', 'post type singular name', 'nrfc-match-reports'),
            'menu_name'          => _x('Match Reports', 'admin menu', 'nrfc-match-reports'),
            'name_admin_bar'     => _x('Match Report', 'add new on admin bar', 'nrfc-match-reports'),
            'add_new'            => _x('Add New', 'match report', 'nrfc-match-reports'),
            'add_new_item'       => __('Add New Match Report', 'nrfc-match-reports'),
            'new_item'           => __('New Match Report', 'nrfc-match-reports'),
            'edit_item'          => __('Edit Match Report', 'nrfc-match-reports'),
            'view_item'          => __('View Match Report', 'nrfc-match-reports'),
            'all_items'          => __('All Match Reports', 'nrfc-match-reports'),
            'search_items'       => __('Search Match Reports', 'nrfc-match-reports'),
            'parent_item_colon'  => __('Parent Match Reports:', 'nrfc-match-reports'),
            'not_found'          => __('No match reports found.', 'nrfc-match-reports'),
            'not_found_in_trash' => __('No match reports found in Trash.', 'nrfc-match-reports'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'match-report'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-media-document',
        ];

        register_post_type(self::CPT, $args);
    }

    /**
     * Register Metaboxes
     */
    public function register_metaboxes()
    {
        add_meta_box(
            'match_report_details',
            __('Match Report Details', 'nrfc-match-reports'),
            [$this, 'render_details_metabox'],
            self::CPT,
            'side',
            'high'
        );

        add_meta_box(
            'match_report_gallery',
            __('Gallery Images', 'nrfc-match-reports'),
            [$this, 'render_gallery_metabox'],
            self::CPT,
            'side',
            'low'
        );
    }

    /**
     * Render Details Metabox
     */
    public function render_details_metabox($post)
    {
        wp_nonce_field('save_match_report_details', self::NONCE_METABOX);

        $fixture_id = get_post_meta($post->ID, self::META_FIXTURE_ID, true);
        if (!$fixture_id && isset($_GET['fixture_id'])) {
            $fixture_id = sanitize_text_field($_GET['fixture_id']);
        }
        $score_for = get_post_meta($post->ID, self::META_SCORE_FOR, true);
        $score_against = get_post_meta($post->ID, self::META_SCORE_AGAINST, true);

        // Get all fixtures for the dropdown
        $fixtures = get_posts([
            'post_type' => 'fixture',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_fixture_date',
            'order' => 'DESC'
        ]);

        ?>
        <div class="match-report-metabox">
            <p>
                <label for="match_report_fixture_id"><?php _e('Linked Fixture:', 'nrfc-match-reports'); ?></label><br>
                <select name="match_report_fixture_id" id="match_report_fixture_id" class="widefat" style="width: 100%;">
                    <option value=""><?php _e('-- Select Fixture --', 'nrfc-match-reports'); ?></option>
                    <?php foreach ($fixtures as $fixture): 
                        $date = get_post_meta($fixture->ID, '_fixture_date', true);
                        ?>
                        <option value="<?php echo esc_attr($fixture->ID); ?>" <?php selected($fixture_id, $fixture->ID); ?>>
                            <?php echo esc_html($fixture->post_title . ' (' . $date . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="match_report_score_for"><?php _e('Score For:', 'nrfc-match-reports'); ?></label><br>
                <input type="number" name="match_report_score_for" id="match_report_score_for" value="<?php echo esc_attr($score_for); ?>" min="0" class="small-text">
            </p>
            <p>
                <label for="match_report_score_against"><?php _e('Score Against:', 'nrfc-match-reports'); ?></label><br>
                <input type="number" name="match_report_score_against" id="match_report_score_against" value="<?php echo esc_attr($score_against); ?>" min="0" class="small-text">
            </p>
        </div>
        <?php
    }

    /**
     * Render Gallery Metabox
     */
    public function render_gallery_metabox($post)
    {
        $gallery_ids = get_post_meta($post->ID, self::META_GALLERY, true);
        $image_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : [];
        ?>
        <div id="match_report_gallery_container">
            <ul id="match_report_gallery_list" style="display: flex; flex-wrap: wrap; list-style: none; padding: 0;">
                <?php foreach ($image_ids as $img_id): 
                    $img_url = wp_get_attachment_image_src($img_id, 'thumbnail');
                    if ($img_url): ?>
                        <li style="margin: 2px; position: relative;" data-id="<?php echo esc_attr($img_id); ?>">
                            <img src="<?php echo esc_url($img_url[0]); ?>" style="width: 50px; height: 50px; object-fit: cover; display: block;">
                            <a href="#" class="remove-image" style="position: absolute; top: 0; right: 0; background: rgba(255,0,0,0.7); color: white; text-decoration: none; padding: 0 4px; line-height: 1; font-size: 12px;">&times;</a>
                        </li>
                    <?php endif;
                endforeach; ?>
            </ul>
            <input type="hidden" name="match_report_gallery" id="match_report_gallery_input" value="<?php echo esc_attr($gallery_ids); ?>">
            <button type="button" class="button" id="match_report_add_gallery_images"><?php _e('Add Gallery Images', 'nrfc-match-reports'); ?></button>
        </div>
        <script>
            jQuery(document).ready(function($) {
                var frame;
                $('#match_report_add_gallery_images').on('click', function(e) {
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({
                        title: 'Select Gallery Images',
                        button: { text: 'Add to gallery' },
                        multiple: true
                    });
                    frame.on('select', function() {
                        var selection = frame.state().get('selection');
                        var ids = $('#match_report_gallery_input').val() ? $('#match_report_gallery_input').val().split(',') : [];
                        selection.map(function(attachment) {
                            attachment = attachment.toJSON();
                            if (ids.indexOf(attachment.id.toString()) === -1) {
                                ids.push(attachment.id);
                                $('#match_report_gallery_list').append(
                                    '<li style="margin: 2px; position: relative;" data-id="' + attachment.id + '">' +
                                    '<img src="' + attachment.sizes.thumbnail.url + '" style="width: 50px; height: 50px; object-fit: cover; display: block;">' +
                                    '<a href="#" class="remove-image" style="position: absolute; top: 0; right: 0; background: rgba(255,0,0,0.7); color: white; text-decoration: none; padding: 0 4px; line-height: 1; font-size: 12px;">&times;</a>' +
                                    '</li>'
                                );
                            }
                        });
                        $('#match_report_gallery_input').val(ids.join(','));
                    });
                    frame.open();
                });

                $('#match_report_gallery_list').on('click', '.remove-image', function(e) {
                    e.preventDefault();
                    var li = $(this).closest('li');
                    var id = li.data('id').toString();
                    li.remove();
                    var ids = $('#match_report_gallery_input').val().split(',');
                    var index = ids.indexOf(id);
                    if (index > -1) {
                        ids.splice(index, 1);
                    }
                    $('#match_report_gallery_input').val(ids.join(','));
                });
            });
        </script>
        <?php
    }

    /**
     * Save Metabox Data
     */
    public function save_match_report_meta($post_id)
    {
        if (!isset($_POST[self::NONCE_METABOX]) || !wp_verify_nonce($_POST[self::NONCE_METABOX], 'save_match_report_details')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['match_report_fixture_id'])) {
            update_post_meta($post_id, self::META_FIXTURE_ID, sanitize_text_field($_POST['match_report_fixture_id']));
        }
        if (isset($_POST['match_report_score_for'])) {
            update_post_meta($post_id, self::META_SCORE_FOR, sanitize_text_field($_POST['match_report_score_for']));
        }
        if (isset($_POST['match_report_score_against'])) {
            update_post_meta($post_id, self::META_SCORE_AGAINST, sanitize_text_field($_POST['match_report_score_against']));
        }
        if (isset($_POST['match_report_gallery'])) {
            update_post_meta($post_id, self::META_GALLERY, sanitize_text_field($_POST['match_report_gallery']));
        }
    }

    /**
     * Add Admin Columns
     */
    public function add_admin_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['fixture'] = __('Fixture', 'nrfc-match-reports');
                $new_columns['score'] = __('Score', 'nrfc-match-reports');
            }
        }
        return $new_columns;
    }

    /**
     * Render Admin Columns
     */
    public function render_admin_columns($column, $post_id)
    {
        switch ($column) {
            case 'fixture':
                $fixture_id = get_post_meta($post_id, self::META_FIXTURE_ID, true);
                if ($fixture_id) {
                    echo esc_html(get_the_title($fixture_id));
                } else {
                    echo '—';
                }
                break;
            case 'score':
                $score_for = get_post_meta($post_id, self::META_SCORE_FOR, true);
                $score_against = get_post_meta($post_id, self::META_SCORE_AGAINST, true);
                echo esc_html($score_for . ' - ' . $score_against);
                break;
        }
    }

    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        global $post_type;
        if (self::CPT === $post_type && in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_media();

            // Enqueue Select2
            wp_enqueue_style('select2', plugins_url('../assets/css/select2.min.css', __FILE__));
            wp_enqueue_script('select2', plugins_url('../assets/js/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);

            // Initialize Select2
            wp_add_inline_script('select2', "
                jQuery(document).ready(function($) {
                    $('#match_report_fixture_id').select2({
                        placeholder: '" . esc_js(__('Select a fixture', 'nrfc-match-reports')) . "',
                        allowClear: true,
                        width: '100%'
                    });
                });
            ");
            
            // Add some custom styles to fix Select2 in WP Admin
            wp_add_inline_style('select2', "
                .select2-container--default .select2-selection--single {
                    height: 30px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                }
                .select2-container--default .select2-selection--single .select2-selection__rendered {
                    line-height: 28px;
                    color: #2c3338;
                }
                .select2-container--default .select2-selection--single .select2-selection__arrow {
                    height: 28px;
                }
            ");
        }
    }

    /**
     * Enqueue Front-end Scripts
     */
    public function enqueue_frontend_scripts()
    {
        if (is_singular(self::CPT)) {
            wp_enqueue_style('nrfc-match-report-slider', plugins_url('../assets/css/slider.css', __FILE__));
            wp_enqueue_script('nrfc-match-report-slider', plugins_url('../assets/js/slider.js', __FILE__), [], '1.0.0', true);
        }
    }

    /**
     * Display gallery in content
     */
    public function display_gallery_in_content($content)
    {
        if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $gallery_ids = get_post_meta($post_id, self::META_GALLERY, true);
        
        if (empty($gallery_ids)) {
            return $content;
        }

        $image_ids = explode(',', $gallery_ids);
        if (empty($image_ids)) {
            return $content;
        }

        ob_start();
        ?>
        <div class="nrfc-match-report-slider">
            <div class="slider-wrapper">
                <?php foreach ($image_ids as $img_id): 
                    $img_url = wp_get_attachment_image_src($img_id, 'large');
                    if ($img_url): ?>
                        <div class="slider-slide">
                            <img src="<?php echo esc_url($img_url[0]); ?>" alt="">
                        </div>
                    <?php endif;
                endforeach; ?>
            </div>
        </div>
        <?php
        $gallery_html = ob_get_clean();

        return $content . $gallery_html;
    }

    /**
     * Add Admin Column to Fixtures
     */
    public function add_fixture_admin_columns($columns)
    {
        $columns['match_report'] = __('Match Report', 'nrfc-match-reports');
        return $columns;
    }

    /**
     * Render Admin Column in Fixtures
     */
    public function render_fixture_admin_columns($column, $post_id)
    {
        if ($column === 'match_report') {
            $report = get_posts([
                'post_type' => self::CPT,
                'meta_query' => [
                    [
                        'key' => self::META_FIXTURE_ID,
                        'value' => $post_id,
                    ]
                ],
                'posts_per_page' => 1
            ]);

            if ($report) {
                $edit_link = get_edit_post_link($report[0]->ID);
                echo '<a href="' . esc_url($edit_link) . '">' . __('Edit Report', 'nrfc-match-reports') . '</a>';
            } else {
                $add_link = admin_url('post-new.php?post_type=' . self::CPT . '&fixture_id=' . $post_id);
                echo '<a href="' . esc_url($add_link) . '">' . __('Add Report', 'nrfc-match-reports') . '</a>';
            }
        }
    }

    /**
     * Register the Gutenberg block for displaying latest match reports
     */
    public function register_match_reports_block()
    {
        $handle = 'nrfc-match-reports-block';
        $src = plugins_url('../assets/js/match-reports-block.js', __FILE__);

        wp_register_script(
            $handle,
            $src,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor'],
            '1.0.0',
            true
        );

        register_block_type('nrfc-match-reports/latest-reports', [
            'editor_script'   => $handle,
            'editor_style'    => 'nrfc-match-reports-block-styles',
            'render_callback' => [$this, 'render_match_reports_block'],
            'attributes'      => [
                'count' => [
                    'type'    => 'number',
                    'default' => 5,
                ],
            ],
        ]);

        wp_register_style(
            'nrfc-match-reports-block-styles',
            plugins_url('../assets/css/match-reports-block.css', __FILE__),
            [],
            '1.0.0'
        );
    }

    /**
     * Server-side render callback for the latest match reports block
     */
    public function render_match_reports_block($attributes)
    {
        if (!is_admin()) {
            wp_enqueue_style('nrfc-match-reports-block-styles');
        }

        $count = isset($attributes['count']) ? (int)$attributes['count'] : 5;

        $query_args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            return '<p>' . esc_html__('No match reports found.', 'nrfc-match-reports') . '</p>';
        }

        ob_start();
        $is_vantage = function_exists('vantage_get_query_variables');
        
        if ($is_vantage) : ?>
            <div class="vantage-carousel-wrapper">
                <?php if (is_admin()) : ?>
                    <div class="vantage-carousel-admin-controls" style="background: #eee; padding: 5px; margin-bottom: 10px; display: flex; justify-content: flex-end; gap: 10px; border: 1px solid #ccc;">
                        <span style="flex-grow: 1; font-weight: bold;"><?php esc_html_e('Carousel Preview', 'nrfc-match-reports'); ?></span>
                        <button type="button" class="button button-small"><?php esc_html_e('Prev', 'nrfc-match-reports'); ?></button>
                        <button type="button" class="button button-small"><?php esc_html_e('Next', 'nrfc-match-reports'); ?></button>
                    </div>
                <?php endif; ?>
                <ul class="vantage-carousel" data-query="<?php echo esc_attr(json_encode($query_args)); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                    <?php while ($query->have_posts()) : $query->the_post(); 
                        $img = wp_get_attachment_image_src(get_post_thumbnail_id(), 'vantage-carousel');
                        ?>
                        <li class="carousel-entry">
                            <div class="thumbnail">
                                <?php if ($img) : ?>
                                    <a href="<?php the_permalink(); ?>" style="background-image: url(<?php echo esc_url($img[0]); ?>)"></a>
                                <?php else : ?>
                                    <a href="<?php the_permalink(); ?>" class="default-thumbnail"><span class="vantage-overlay"></span></a>
                                <?php endif; ?>
                            </div>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                </ul>
            </div>
        <?php else : ?>
            <div class="nrfc-latest-match-reports">
                <ul>
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <li>
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="report-thumbnail">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <a href="<?php the_permalink(); ?>" class="report-title"><?php the_title(); ?></a>
                        </li>
                    <?php endwhile; wp_reset_postdata(); ?>
                </ul>
            </div>
        <?php endif;
        return ob_get_clean();
    }

    /**
     * Register the match reports widget
     */
    public function register_match_reports_widget(): void
    {
        if (class_exists('WP_Widget')) {
            register_widget(__NAMESPACE__ . '\\LatestMatchReportsWidget');
        }
    }
}

/**
 * Latest Match Reports Widget
 */
class LatestMatchReportsWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'latest_match_reports_widget',
            __('Latest Match Reports (NRFC)', 'nrfc-match-reports'),
            ['description' => __('Display latest match reports.', 'nrfc-match-reports')]
        );
    }

    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $count = isset($instance['count']) ? (int)$instance['count'] : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>"><?php _e('Number of reports to show:', 'nrfc-match-reports'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('count')); ?>" name="<?php echo esc_attr($this->get_field_name('count')); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($count); ?>" size="3">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['count'] = !empty($new_instance['count']) ? absint($new_instance['count']) : 5;
        return $instance;
    }

    public function widget($args, $instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : '';

        echo $args['before_widget'];
        
        if (!empty($title)) {
            $is_vantage = function_exists('vantage_get_query_variables');
            
            $title_html = apply_filters('widget_title', $title);
            
            if ($is_vantage) {
                echo $args['before_title'];
                echo '<span class="vantage-carousel-title"><span class="vantage-carousel-title-text">' . $title_html . '</span>';
                echo '<a href="#" class="next" title="' . esc_attr(__('Next', 'vantage')) . '"><span class="vantage-icon-arrow-right"></span></a>';
                echo '<a href="#" class="previous" title="' . esc_attr(__('Previous', 'vantage')) . '"><span class="vantage-icon-arrow-left"></span></a>';
                echo '</span>';
                echo $args['after_title'];
            } else {
                echo $args['before_title'] . $title_html . $args['after_title'];
            }
        }

        $reports = new MatchReports();
        echo $reports->render_match_reports_block([
            'count' => $instance['count'] ?? 5,
        ]);

        echo $args['after_widget'];
    }
}
