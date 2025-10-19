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