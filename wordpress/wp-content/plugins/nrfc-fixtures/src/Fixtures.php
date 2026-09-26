<?php

namespace NRFCFixtures;

use DateTime;
use JetBrains\PhpStorm\NoReturn;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Fixtures Class
 */
class Fixtures
{
    const CPT = 'fixture';

    // Taxonomies
    const TAX_TEAM            = 'fixture_team';
    const TAX_OPPOSING_CLUB   = 'opposing_club';
    const TAX_OPPOSING_TEAM   = 'opposing_team';
    const TAX_COMPETITION     = 'competition_type';

    // Meta keys
    const META_DATE           = '_fixture_date';
    const META_KICK_OFF_TIME  = '_fixture_kick_off_time';
    const META_VENUE          = '_fixture_venue';
    const META_NOTES          = '_fixture_notes';

    const NONCE_METABOX       = 'fixture_metabox_nonce';
    const NONCE_IMPORT        = 'fixture_import_nonce';
    const NONCE_SPOND         = 'fixture_spond_nonce';

    const PAGE_SLUG           = 'fixtures';

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_fixture_meta']);

        // Add custom columns in admin list
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . self::CPT . '_sortable_columns', [$this, 'register_sortable_columns']);
        add_action('pre_get_posts', [$this, 'handle_admin_sorting']);

        // Add admin pages
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_init', [$this, 'maybe_handle_spond_export']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

        // Register Gutenberg block
        add_action('init', [$this, 'register_fixtures_block']);

        // Register Widget
        add_action('widgets_init', [$this, 'register_fixtures_widget']);

        // Register Landing Page
        add_action('init', [$this, 'register_landing_page_rewrite']);
        add_filter('template_include', [$this, 'handle_landing_page_template']);
        add_action('template_redirect', [$this, 'handle_public_export_route']);
        add_filter('query_vars', [$this, 'register_query_vars']);

        // Register REST API Route
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Register rewrite rule for the fixtures landing page and export endpoint
     */
    public function register_landing_page_rewrite(): void
    {
        add_rewrite_rule('^fixtures/?$', 'index.php?nrfc_fixtures_page=1', 'top');
        add_rewrite_rule('^fixtures/export/?$', 'index.php?nrfc_fixtures_export=1', 'top');
    }

    /**
     * Register custom query variables
     */
    public function register_query_vars($vars): array
    {
        $vars[] = 'nrfc_fixtures_page';
        $vars[] = 'nrfc_fixtures_export';
        $vars[] = 'teams';
        $vars[] = 'team';
        return $vars;
    }

    /**
     * Register REST API routes for fixtures
     */
    public function register_rest_routes(): void
    {
        register_rest_route('nrfc-fixtures/v1', '/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_export_fixtures'],
            'permission_callback' => '__return_true',
            'args'                => [
                'team' => [
                    'description'       => __('Team name, slug, alias, or ID.', 'nrfc-fixtures'),
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'team_name' => [
                    'description'       => __('Team name, slug, alias, or ID.', 'nrfc-fixtures'),
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * REST API callback for exporting fixtures for a team
     *
     * @param \WP_REST_Request|null $request
     * @return \WP_Error|void
     */
    public function rest_export_fixtures(?\WP_REST_Request $request = null)
    {
        $team_param = '';
        if ($request !== null && method_exists($request, 'get_param')) {
            $team_param = (string) ($request->get_param('team') ?? $request->get_param('team_name') ?? '');
        }

        if ($team_param === '' && isset($_GET['team'])) {
            $team_param = (string) $_GET['team'];
        } elseif ($team_param === '' && isset($_GET['team_name'])) {
            $team_param = (string) $_GET['team_name'];
        }

        $team_param = trim(sanitize_text_field($team_param));
        if ($team_param === '') {
            return new \WP_Error('missing_team', __('A team parameter is required.', 'nrfc-fixtures'), ['status' => 400]);
        }

        $team = $this->resolve_team($team_param);
        if (!$team) {
            return new \WP_Error('team_not_found', sprintf(__('Team "%s" not found.', 'nrfc-fixtures'), $team_param), ['status' => 404]);
        }

        $this->stream_spond_csv($team);
    }

    /**
     * Handle public export route via GET request
     */
    public function handle_public_export_route(): void
    {
        $is_export = get_query_var('nrfc_fixtures_export')
            || (isset($_GET['nrfc_fixtures_export']) && $_GET['nrfc_fixtures_export'])
            || (isset($_GET['export']) && $_GET['export'] === 'spond');

        if (!$is_export) {
            return;
        }

        $team_param = $_GET['team'] ?? $_GET['team_name'] ?? get_query_var('team') ?? get_query_var('teams') ?? '';
        $team_param = trim(sanitize_text_field((string)$team_param));

        if ($team_param === '') {
            status_header(400);
            wp_die(__('A team parameter is required for export.', 'nrfc-fixtures'), __('Bad Request', 'nrfc-fixtures'), ['response' => 400]);
        }

        $team = $this->resolve_team($team_param);
        if (!$team) {
            status_header(404);
            wp_die(sprintf(__('Team "%s" not found.', 'nrfc-fixtures'), esc_html($team_param)), __('Team Not Found', 'nrfc-fixtures'), ['response' => 404]);
        }

        $this->stream_spond_csv($team);
    }

    /**
     * Resolve a team name, slug, alias, or ID to a WP_Term object
     *
     * @param string|int $team_identifier
     * @return \WP_Term|null
     */
    public function resolve_team(string|int $team_identifier): ?\WP_Term
    {
        if (is_int($team_identifier) || ctype_digit(trim((string)$team_identifier))) {
            $term_id = (int)$team_identifier;
            if ($term_id > 0) {
                $term = get_term($term_id, self::TAX_TEAM);
                if ($term instanceof \WP_Term) {
                    return $term;
                }
            }
        }

        $team_str = trim((string)$team_identifier);
        if ($team_str === '') {
            return null;
        }

        // Try exact name match
        $term = get_term_by('name', $team_str, self::TAX_TEAM);
        if ($term instanceof \WP_Term) {
            return $term;
        }

        // Try slug match
        $term = get_term_by('slug', sanitize_title($team_str), self::TAX_TEAM);
        if ($term instanceof \WP_Term) {
            return $term;
        }

        // Try normalised name and its slug
        $normalised = $this->normaliseTeam($team_str);
        if ($normalised !== $team_str) {
            $term = get_term_by('name', $normalised, self::TAX_TEAM);
            if ($term instanceof \WP_Term) {
                return $term;
            }
            $term = get_term_by('slug', sanitize_title($normalised), self::TAX_TEAM);
            if ($term instanceof \WP_Term) {
                return $term;
            }
        }

        // Case-insensitive fallback match against all terms in TAX_TEAM
        $all_teams = get_terms([
            'taxonomy'   => self::TAX_TEAM,
            'hide_empty' => false,
        ]);
        if (!empty($all_teams) && !is_wp_error($all_teams)) {
            foreach ($all_teams as $t) {
                if ($t instanceof \WP_Term) {
                    if (strcasecmp($t->name, $team_str) === 0 || strcasecmp($t->slug, $team_str) === 0) {
                        return $t;
                    }
                    if (strcasecmp($t->name, $normalised) === 0 || strcasecmp($t->slug, $normalised) === 0) {
                        return $t;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get formatted cell content for a fixture: opposing club name and venue in brackets
     *
     * @param int $fixture_id Fixture post ID
     *
     * @return string Formatted cell content
     */
    private function get_fixture_cell_content(int $fixture_id): string
    {
        $opp_club_terms = wp_get_object_terms($fixture_id, self::TAX_OPPOSING_CLUB);
        $opp_club = !empty($opp_club_terms) ? $opp_club_terms[0]->name : '';

        $venue = get_post_meta($fixture_id, self::META_VENUE, true);
        $notes = get_post_meta($fixture_id, self::META_NOTES, true);

        if (!empty($opp_club) && !empty($venue)) {
            $content = '';
            if (in_array(strtolower($venue), ['home', 'away'])) {
                $content = sprintf('%s (%s)', $opp_club, strtoupper(substr($venue, 0, 1)));
            } else {
                $content = sprintf('%s (%s)', $opp_club, $venue);
            }

            if (!empty($notes)) {
                $content .= ' - ' . $notes;
            }

            return $content;
        } elseif (!empty($opp_club)) {
            return !empty($notes) ? sprintf('%s - %s', $opp_club, $notes) : $opp_club;
        } elseif (!empty($venue)) {
            $content = sprintf('(%s)', $venue);
            return !empty($notes) ? sprintf('%s - %s', $content, $notes) : $content;
        } elseif (!empty($notes)) {
            return $notes;
        }

        return '';
    }

    /**
     * Handle the template for the fixtures landing page
     */
    public function handle_landing_page_template($template)
    {
        if (get_query_var('nrfc_fixtures_page') || is_post_type_archive(self::CPT) || is_page(self::PAGE_SLUG)) {
            // Enqueue landing page styles
            add_action('wp_enqueue_scripts', [$this, 'enqueue_landing_page_styles']);
            $this->render_landing_page();
        }
        return $template;
    }

    /**
     * Render the fixtures landing page grid
     */
    #[NoReturn]
    private function render_landing_page(): void {
        if (!is_admin()) {
            $this->enqueue_landing_page_styles();
        }

        get_header();

        $requested_teams = isset($_GET['teams']) ? sanitize_text_field($_GET['teams']) : '';
        if (empty($requested_teams) && isset($_GET['teams_arr']) && is_array($_GET['teams_arr'])) {
            $requested_teams = implode(',', array_map('sanitize_text_field', $_GET['teams_arr']));
        }
        if (empty($requested_teams)) {
            $requested_teams = get_query_var('teams');
        }
        $teams_filter = [];
        if (!empty($requested_teams)) {
            $teams_filter = explode(',', $requested_teams);
        }

        // Get all teams
        $all_available_teams = get_terms([
                'taxonomy'   => self::TAX_TEAM,
                'hide_empty' => false,
        ]);

        $teams = $all_available_teams;
        if (!empty($teams_filter)) {
            $teams = array_filter($teams, function ($team) use ($teams_filter) {
                return in_array($team->slug, $teams_filter) || in_array($team->name, $teams_filter);
            });
        }

        // Query all fixtures
        $args = [
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => self::META_DATE,
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
        ];

        $query = new \WP_Query($args);
        $can_edit = current_user_can('edit_posts');
        $fixtures_by_date = [];
        $fixtures_by_date_raw = [];
        $all_dates = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $date = get_post_meta($id, self::META_DATE, true);
                if (!$date) continue;

                $all_dates[] = $date;

                $fixture_teams = wp_get_object_terms($id, self::TAX_TEAM);
                $cell_content = $this->get_fixture_cell_content($id);

                if ($can_edit) {
                    $edit_link = get_edit_post_link($id);
                    $cell_content = sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($cell_content));
                } else {
                    $cell_content = esc_html($cell_content);
                }

                foreach ($fixture_teams as $team) {
                    $fixtures_by_date[$date][$team->term_id][] = $cell_content;
                    $fixtures_by_date_raw[$date][] = [
                            'team' => $team->name,
                            'cell_content' => $cell_content,
                    ];
                }
            }
            wp_reset_postdata();
        }

        $all_dates = array_unique($all_dates);
        sort($all_dates);

        ?>
        <div class="wrap nrfc-fixtures-grid-container">
            <h2><?php esc_html_e('Fixtures Grid', 'nrfc-fixtures'); ?></h2>

            <div class="nrfc-fixtures-filter">
                <form method="get" action="">
                    <input type="hidden" name="nrfc_fixtures_page" value="1">
                    <div class="nrfc-fixtures-filter-teams">
                        <!-- Senior -->
                        <?php
                        $senior_teams = array_filter($all_available_teams, function($team) {
                            return stripos($team->name, 'Boys') === false &&
                                   stripos($team->name, 'Girls') === false &&
                                   stripos($team->name, 'Mini') === false;
                        }); ?>
                        <div class="nrfc-fixtures-filter-group">
                            <?php foreach ( $senior_teams as $team ) : ?>
                                <label class="nrfc-fixtures-filter-label">
                                    <input type="checkbox" name="teams_arr[]"
                                           value="<?php echo esc_attr( $team->slug ); ?>" <?php checked( in_array( $team->slug, $teams_filter ) || in_array( $team->name, $teams_filter ) ); ?>>
                                    <?php echo esc_html( $team->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!-- Boys -->
                        <?php $boys_teams = array_filter($all_available_teams, function($team) {return stripos($team->name, 'Boys') !== false;}); ?>
                        <div class="nrfc-fixtures-filter-group">
                            <?php foreach ( $boys_teams as $team ) : ?>
                                <label class="nrfc-fixtures-filter-label">
                                    <input type="checkbox" name="teams_arr[]"
                                           value="<?php echo esc_attr( $team->slug ); ?>" <?php checked( in_array( $team->slug, $teams_filter ) || in_array( $team->name, $teams_filter ) ); ?>>
                                    <?php echo esc_html( $team->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!-- Girls -->
                        <?php $girls_teams = array_filter($all_available_teams, function($team) {return stripos($team->name, 'Girls') !== false;}); ?>
                        <div class="nrfc-fixtures-filter-group">
                            <?php foreach ( $girls_teams as $team ) : ?>
                                <label class="nrfc-fixtures-filter-label">
                                    <input type="checkbox" name="teams_arr[]"
                                           value="<?php echo esc_attr( $team->slug ); ?>" <?php checked( in_array( $team->slug, $teams_filter ) || in_array( $team->name, $teams_filter ) ); ?>>
                                    <?php echo esc_html( $team->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!-- Minis -->
                        <?php $mini_teams = array_filter($all_available_teams, function($team) {return stripos($team->name, 'Mini') !== false;}); ?>
                        <div class="nrfc-fixtures-filter-group">
                            <?php foreach ( $mini_teams as $team ) : ?>
                                <label class="nrfc-fixtures-filter-label">
                                    <input type="checkbox" name="teams_arr[]"
                                           value="<?php echo esc_attr( $team->slug ); ?>" <?php checked( in_array( $team->slug, $teams_filter ) || in_array( $team->name, $teams_filter ) ); ?>>
                                    <?php echo esc_html( $team->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="nrfc-filter-submit">
                        <button type="submit"
                                class="button button-primary"><?php esc_html_e( 'Filter', 'nrfc-fixtures' ); ?></button>
                        <a href="<?php echo esc_url( remove_query_arg( [ 'teams', 'teams_arr' ] ) ); ?>"
                           class="button"><?php esc_html_e( 'Clear', 'nrfc-fixtures' ); ?></a>
                    </div>
                </form>
            </div>

            <table class="nrfc-fixtures-grid">
                <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'nrfc-fixtures'); ?></th>
                    <?php foreach ($teams as $team) : ?>
                        <th><?php echo esc_html($team->name); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($all_dates as $date) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html(date_i18n('d M Y', strtotime($date))); ?>
                            <!-- //echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ? -->
                        </td>
                        <?php foreach ($teams as $team) : ?>
                            <td>
                                <?php
                                if (isset($fixtures_by_date[$date][$team->term_id])) {
                                    echo implode(', ', $fixtures_by_date[$date][$team->term_id]);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="nrfc-fixtures-mobile-list">
                <?php foreach ($all_dates as $date) : ?>
                    <?php if (isset($fixtures_by_date_raw[$date])) : ?>
                        <div class="nrfc-fixture-date-block">
                            <h2><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?></h2>
                            <ul>
                                <?php foreach ($fixtures_by_date_raw[$date] as $fixture) : ?>
                                    <?php
                                    // Check if this fixture's team is in the filtered list
                                    $show_fixture = true;
                                    if (!empty($teams_filter)) {
                                        $show_fixture = false;
                                        foreach ($teams as $t) {
                                            if ($t->name === $fixture['team']) {
                                                $show_fixture = true;
                                                break;
                                            }
                                        }
                                    }

                                    if ($show_fixture) :
                                        ?>
                                        <li>
                                            <strong><?php echo esc_html($fixture['team']); ?></strong>:
                                            <?php echo $fixture['cell_content']; ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        get_footer();
        exit;
    }

    /**
     * Plugin activation hook
     */
    public function activate(): void
    {
        $this->register_post_type();
        $this->register_taxonomies();
        $this->seed_taxonomies();
        $this->ensure_fixtures_page();
        flush_rewrite_rules();
    }

    /**
     * Seed taxonomies with sample data
     */
    private function seed_taxonomies(): void
    {
        $data = [
                self::TAX_TEAM => [
                        '1st XV',
                        'Lions',
                        'Women XV',
                        'Boys Senior Academy',
                        'Boys Junior Academy',
                        'Under 15 Boys',
                        'Under 14 Boys',
                        'Under 13 Boys',
                        'Girls Senior Academy',
                        'Girls Junior Academy',
                        'Under 14 Girls',
                        'Under 12 Girls',
                        'Minis',
                ],
                self::TAX_OPPOSING_CLUB => [
                        'Beccles',
                        'Braintree',
                        'Brentwood',
                        'Bury',
                        'Cambridge',
                        'Chelmsford',
                        'Colchester',
                        'Crusaders',
                        'Diss',
                        'Eton Manor',
                        'F&S Barbarians',
                        'Fakenham',
                        'HAC',
                        'Harlow',
                        'Haverhill',
                        'Holt',
                        'Ipswich',
                        'Ipswich YM',
                        'Lakenham Union',
                        'Lowestoft & Yarmouh',
                        'Misley',
                        'Newmarket',
                        'North Walsham',
                        'Phoenixes',
                        'Rochford Hundred',
                        'Shelford',
                        'Southwold',
                        'Stowmarket',
                        'Sudbury',
                        'UEA',
                        'Wanstead',
                        'West Norfolk',
                        'West Walsham',
                        'Wisbech',
                        'Woodbridge',
                        'Woodford',
                        'Wymondham',
                ],
                self::TAX_OPPOSING_TEAM => [
                        '1st XV',
                        '2nd XV',
                        '3rd XV',
                        '4th XV',
                        'Development XV',
                ],
                self::TAX_COMPETITION => [
                        'League',
                        'Cup',
                        'Friendly',
                        'Festival',
                        'Other',
                ],
        ];

        foreach ($data as $taxonomy => $terms) {
            foreach ($terms as $term) {
                if (!term_exists($term, $taxonomy)) {
                    wp_insert_term($term, $taxonomy);
                }
            }
        }
    }

    /**
     * Ensure the fixtures landing page exists
     */
    private function ensure_fixtures_page(): void
    {
        $page = get_page_by_path(self::PAGE_SLUG);
        if (!$page) {
            wp_insert_post([
                'post_title'   => 'Fixtures',
                'post_name'    => self::PAGE_SLUG,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '<!-- This page is automatically populated by the NRFC Fixtures plugin -->',
            ]);
        }
    }


    /**
     * Plugin deactivation hook
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Register Custom Post Type
     */
    public function register_post_type(): void {
        $labels = [
            'name'               => __('Fixtures', 'nrfc-fixtures'),
            'singular_name'      => __('Fixture', 'nrfc-fixtures'),
            'add_new'            => __('Add New', 'nrfc-fixtures'),
            'add_new_item'       => __('Add New Fixture', 'nrfc-fixtures'),
            'edit_item'          => __('Edit Fixture', 'nrfc-fixtures'),
            'new_item'           => __('New Fixture', 'nrfc-fixtures'),
            'view_item'          => __('View Fixture', 'nrfc-fixtures'),
            'search_items'       => __('Search Fixtures', 'nrfc-fixtures'),
            'not_found'          => __('No fixtures found', 'nrfc-fixtures'),
            'not_found_in_trash' => __('No fixtures found in Trash', 'nrfc-fixtures'),
            'all_items'          => __('All Fixtures', 'nrfc-fixtures'),
            'menu_name'          => __('Fixtures', 'nrfc-fixtures'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'show_in_rest'       => false,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title'],
            'rewrite'            => ['slug' => 'fixtures'],
            'taxonomies'         => [self::TAX_TEAM, self::TAX_OPPOSING_CLUB, self::TAX_OPPOSING_TEAM, self::TAX_COMPETITION],
        ];

        register_post_type(self::CPT, $args);
    }

    /**
     * Register Custom Taxonomies
     */
    public function register_taxonomies(): void {
        // Team Taxonomy
        register_taxonomy(self::TAX_TEAM, [self::CPT], [
            'label'             => __('Team', 'nrfc-fixtures'),
            'hierarchical'      => true,
            'show_in_rest'      => false,
            'show_admin_column' => true,
            'meta_box_cb'       => [$this, 'render_single_select_taxonomy_metabox'],
        ]);

        // Opposing Club Taxonomy
        register_taxonomy(self::TAX_OPPOSING_CLUB, [self::CPT], [
            'label'             => __('Opposing Club', 'nrfc-fixtures'),
            'hierarchical'      => true,
            'show_in_rest'      => false,
            'show_admin_column' => true,
            'meta_box_cb'       => [$this, 'render_single_select_taxonomy_metabox'],
        ]);

        // Opposing Team Taxonomy
        register_taxonomy(self::TAX_OPPOSING_TEAM, [self::CPT], [
            'label'             => __('Opposing Team', 'nrfc-fixtures'),
            'hierarchical'      => true,
            'show_in_rest'      => false,
            'show_admin_column' => true,
            'meta_box_cb'       => [$this, 'render_single_select_taxonomy_metabox'],
        ]);

        // Competition Type Taxonomy
        register_taxonomy(self::TAX_COMPETITION, [self::CPT], [
            'label'             => __('Competition', 'nrfc-fixtures'),
            'hierarchical'      => true,
            'show_in_rest'      => false,
            'show_admin_column' => true,
            'meta_box_cb'       => [$this, 'render_single_select_taxonomy_metabox'],
        ]);
    }

    /**
     * Render a single select dropdown for taxonomies
     */
    public function render_single_select_taxonomy_metabox($post, $box): void {
        $taxonomy = $box['args']['taxonomy'];
        $tax      = get_taxonomy($taxonomy);
        $terms    = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $selected = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $current  = !empty($selected) ? $selected[0] : '';

        echo '<div id="taxonomy-' . esc_attr($taxonomy) . '" class="categorydiv">';
        echo '<input type="hidden" name="tax_input[' . esc_attr($taxonomy) . '][]" value="0" />';
        echo '<select name="tax_input[' . esc_attr($taxonomy) . '][]" id="taxonomy-' . esc_attr($taxonomy) . '-select" class="widefat">';
        echo '<option value="0">' . sprintf(esc_html__('Select %s', 'nrfc-fixtures'), $tax->labels->singular_name) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->term_id) . '" ' . selected($current, $term->term_id, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    /**
     * Register Meta Boxes
     */
    public function register_metaboxes(): void {
        add_meta_box(
            'fixture_details_metabox',
            __('Fixture Details', 'nrfc-fixtures'),
            [$this, 'render_fixture_metabox'],
            self::CPT,
            'normal',
            'high'
        );
    }

    /**
     * Render Meta Box
     */
    public function render_fixture_metabox($post): void {
        wp_nonce_field('save_fixture_meta', self::NONCE_METABOX);

        $date          = get_post_meta($post->ID, self::META_DATE, true);
        $kick_off_time = get_post_meta($post->ID, self::META_KICK_OFF_TIME, true);
        $venue         = get_post_meta($post->ID, self::META_VENUE, true);
        $notes         = get_post_meta($post->ID, self::META_NOTES, true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="fixture_date"><?php esc_html_e('Date', 'nrfc-fixtures'); ?></label></th>
                <td><input type="date" name="fixture_date" id="fixture_date" value="<?php echo esc_attr($date); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="fixture_kick_off_time"><?php esc_html_e('Kick Off Time', 'nrfc-fixtures'); ?></label></th>
                <td><input type="time" name="fixture_kick_off_time" id="fixture_kick_off_time" value="<?php echo esc_attr($kick_off_time); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="fixture_venue"><?php esc_html_e('Venue', 'nrfc-fixtures'); ?></label></th>
                <td><input type="text" name="fixture_venue" id="fixture_venue" value="<?php echo esc_attr($venue); ?>" class="regular-text" placeholder="e.g. Home, Away, or specific ground"></td>
            </tr>
            <tr>
                <th><label for="fixture_notes"><?php esc_html_e('Notes', 'nrfc-fixtures'); ?></label></th>
                <td><textarea name="fixture_notes" id="fixture_notes" rows="5" class="large-text"><?php echo esc_textarea($notes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Meta Box Data
     */
    public function save_fixture_meta($post_id): void {
        if (!isset($_POST[self::NONCE_METABOX]) || !wp_verify_nonce($_POST[self::NONCE_METABOX], 'save_fixture_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['fixture_date'])) {
            update_post_meta($post_id, self::META_DATE, sanitize_text_field($_POST['fixture_date']));
        }
        if (isset($_POST['fixture_kick_off_time'])) {
            update_post_meta($post_id, self::META_KICK_OFF_TIME, sanitize_text_field($_POST['fixture_kick_off_time']));
        }
        if (isset($_POST['fixture_venue'])) {
            update_post_meta($post_id, self::META_VENUE, sanitize_text_field($_POST['fixture_venue']));
        }
        if (isset($_POST['fixture_notes'])) {
            update_post_meta($post_id, self::META_NOTES, sanitize_textarea_field($_POST['fixture_notes']));
        }
    }

    /**
     * Register Sortable Columns
     */
    public function register_sortable_columns($columns)
    {
        $columns['fixture_date'] = 'fixture_date';
        $columns['taxonomy-' . self::TAX_TEAM] = 'fixture_team';
        return $columns;
    }

    /**
     * Handle Admin Sorting
     */
    public function handle_admin_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== self::CPT) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'fixture_date') {
            $query->set('meta_key', self::META_DATE);
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'fixture_team') {
            add_filter('posts_clauses', [$this, 'sort_by_team_taxonomy'], 10, 2);
        }
    }

    /**
     * SQL clauses to sort by team taxonomy
     */
    public function sort_by_team_taxonomy($clauses, $query)
    {
        global $wpdb;

        if ($query->get('orderby') === 'fixture_team') {
            $clauses['join'] .= "
                LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id
                LEFT OUTER JOIN {$wpdb->term_taxonomy} ON {$wpdb->term_relationships}.term_taxonomy_id = {$wpdb->term_taxonomy}.term_taxonomy_id
                LEFT OUTER JOIN {$wpdb->terms} ON {$wpdb->term_taxonomy}.term_id = {$wpdb->terms}.term_id
            ";
            $clauses['where'] .= $wpdb->prepare(" AND ({$wpdb->term_taxonomy}.taxonomy = %s OR {$wpdb->term_taxonomy}.taxonomy IS NULL)", self::TAX_TEAM);
            $clauses['groupby'] = "{$wpdb->posts}.ID";
            $clauses['orderby'] = "{$wpdb->terms}.name " . ($query->get('order') === 'ASC' ? 'ASC' : 'DESC');
        }

        return $clauses;
    }

    /**
     * Add Admin Columns
     */
    public function add_admin_columns($columns): array {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['fixture_date'] = __('Date', 'nrfc-fixtures');
                $new_columns['fixture_time'] = __('KO Time', 'nrfc-fixtures');
                $new_columns['fixture_venue'] = __('Venue', 'nrfc-fixtures');
            }
        }
        return $new_columns;
    }

    /**
     * Render Admin Columns
     */
    public function render_admin_columns($column, $post_id): void {
        switch ($column) {
            case 'fixture_date':
                echo esc_html(get_post_meta($post_id, self::META_DATE, true));
                break;
            case 'fixture_time':
                echo esc_html(get_post_meta($post_id, self::META_KICK_OFF_TIME, true));
                break;
            case 'fixture_venue':
                echo esc_html(get_post_meta($post_id, self::META_VENUE, true));
                break;
        }
    }

    /**
     * Register Admin Pages
     */
    public function register_admin_pages(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            __('CSV Import', 'nrfc-fixtures'),
            __('CSV Import', 'nrfc-fixtures'),
            'manage_options',
            'fixture-csv-import',
            [$this, 'render_import_page']
        );

        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            __('Spond Export', 'nrfc-fixtures'),
            __('Spond Export', 'nrfc-fixtures'),
            'manage_options',
            'fixture-spond-export',
            [$this, 'render_spond_export_page']
        );
    }

    /**
     * Render Import Page
     */
    public function render_import_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'nrfc-fixtures'));
        }

        $results = null;
        if (!empty($_POST) && isset($_POST[self::NONCE_IMPORT]) && wp_verify_nonce($_POST[self::NONCE_IMPORT], 'fixture_import_action')) {
            $results = $this->handle_csv_upload();
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Fixture CSV Import', 'nrfc-fixtures') . '</h1>';
        echo '<p>' . esc_html__('Upload a CSV file to import or update fixtures.', 'nrfc-fixtures') . '</p>';

        echo '<h2>CSV Format</h2>';
        echo '<p>' . esc_html__('The first row must be a header row. Duplicate check is performed on Date, Team, and Opposing Club.', 'nrfc-fixtures') . '</p>';
        echo '<ul>';
        echo '<li><code>date</code> (YYYY-MM-DD, ' . esc_html__('required', 'nrfc-fixtures') . ')</li>';
        echo '<li><code>team</code> (' . esc_html__('required', 'nrfc-fixtures') . ')</li>';
        echo '<li><code>opposing_club</code> (' . esc_html__('required', 'nrfc-fixtures') . ')</li>';
        echo '<li><code>opposing_team</code> (optional)</li>';
        echo '<li><code>competition_type</code> (optional)</li>';
        echo '<li><code>kick_off_time</code> (HH:MM, optional)</li>';
        echo '<li><code>venue</code> (optional)</li>';
        echo '<li><code>notes</code> (optional)</li>';
        echo '</ul>';

        if (is_wp_error($results)) {
            echo '<div class="notice notice-error"><p>' . esc_html($results->get_error_message()) . '</p></div>';
        } elseif (is_array($results)) {
            echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('%d created, %d updated, %d errors', 'nrfc-fixtures'), $results['created'], $results['updated'], count($results['errors'])) . '</p></div>';
            if (!empty($results['errors'])) {
                echo '<div class="notice notice-warning"><ul>';
                foreach ($results['errors'] as $err) {
                    echo '<li>' . esc_html($err) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('fixture_import_action', self::NONCE_IMPORT);
        echo '<input type="file" name="fixture_csv" accept=".csv,text/csv" required /> ';
        submit_button(__('Import', 'nrfc-fixtures'), 'primary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render Spond Export Page
     */
    public function render_spond_export_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'nrfc-fixtures'));
        }

        $teams = get_terms([
            'taxonomy'   => self::TAX_TEAM,
            'hide_empty' => false,
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Spond Export', 'nrfc-fixtures') . '</h1>';
        echo '<p>' . esc_html__('Select a team to export fixtures for Spond.', 'nrfc-fixtures') . '</p>';

        echo '<form method="post">';
        wp_nonce_field('fixture_spond_export_action', self::NONCE_SPOND);
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="team_id">' . esc_html__('Select Team', 'nrfc-fixtures') . '</label></th>';
        echo '<td>';
        echo '<select name="team_id" id="team_id" required>';
        echo '<option value="">' . esc_html__('-- Select Team --', 'nrfc-fixtures') . '</option>';
        if (!empty($teams) && !is_wp_error($teams)) {
            foreach ($teams as $team) {
                echo '<option value="' . esc_attr($team->term_id) . '">' . esc_html($team->name) . '</option>';
            }
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button(__('Export to CSV', 'nrfc-fixtures'), 'primary', 'submit', false);
        echo '</form>';

        if (!empty($teams) && !is_wp_error($teams)) {
            echo '<hr style="margin-top: 30px; margin-bottom: 20px;" />';
            echo '<h2>' . esc_html__('Public GET Export Links', 'nrfc-fixtures') . '</h2>';
            echo '<p>' . esc_html__('Below are direct links to export fixtures for each team via GET request:', 'nrfc-fixtures') . '</p>';
            echo '<table class="widefat striped" style="max-width: 900px; margin-top: 10px;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th scope="col">' . esc_html__('Team', 'nrfc-fixtures') . '</th>';
            echo '<th scope="col">' . esc_html__('Export Link', 'nrfc-fixtures') . '</th>';
            echo '<th scope="col">' . esc_html__('REST API Endpoint', 'nrfc-fixtures') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            foreach ($teams as $team) {
                if (!($team instanceof \WP_Term)) {
                    continue;
                }
                $export_url = home_url('/fixtures/export?team=' . rawurlencode($team->slug));
                $rest_url   = function_exists('rest_url') ? rest_url('nrfc-fixtures/v1/export?team=' . rawurlencode($team->slug)) : home_url('/wp-json/nrfc-fixtures/v1/export?team=' . rawurlencode($team->slug));

                echo '<tr>';
                echo '<td><strong>' . esc_html($team->name) . '</strong></td>';
                echo '<td><a href="' . esc_url($export_url) . '" target="_blank">' . esc_html($export_url) . '</a></td>';
                echo '<td><a href="' . esc_url($rest_url) . '" target="_blank">' . esc_html($rest_url) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }

    /**
     * Maybe Handle Spond Export
     */
    public function maybe_handle_spond_export()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'fixture-spond-export') {
            if (!empty($_POST) && isset($_POST[self::NONCE_SPOND]) && wp_verify_nonce($_POST[self::NONCE_SPOND], 'fixture_spond_export_action')) {
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to access this page.', 'nrfc-fixtures'));
                }
                $this->handle_spond_export();
            }
        }
    }

    /**
     * Build a single row for the Spond CSV export
     *
     * @param string $team_name
     * @param string $date_val
     * @param string $kick_off_time
     * @param string $venue
     * @param string $opp_club
     * @param string $opp_team
     * @param string $fixture_title
     * @return array|null
     */
    public function build_spond_row(
        string $team_name,
        string $date_val,
        string $kick_off_time,
        string $venue,
        string $opp_club,
        string $opp_team,
        string $fixture_title
    ): ?array {
        $date_obj = $this->clean_up_date($date_val);
        if (!$date_obj) {
            return null;
        }

        $formatted_date = $date_obj->format('d/m/Y');

        $time_val = trim($kick_off_time);
        if ($time_val === '' || $time_val === '00:00' || $time_val === '00:00:00') {
            $start_time = '11:00';
        } else {
            $time_obj = DateTime::createFromFormat('H:i', $time_val) ?: DateTime::createFromFormat('H:i:s', $time_val);
            if (!$time_obj) {
                $timestamp = strtotime($time_val);
                $start_time = ($timestamp !== false) ? date('H:i', $timestamp) : '11:00';
            } else {
                $start_time = $time_obj->format('H:i');
            }
        }

        $start_dt = DateTime::createFromFormat('H:i', $start_time);
        if ($start_dt) {
            $start_dt->modify('+2 hours');
            $end_time = $start_dt->format('H:i');
        } else {
            $end_time = '13:00';
        }

        $opp_club = trim($opp_club);
        $opp_team = trim($opp_team);
        $opposing_team = trim($opp_club . (!empty($opp_team) ? ' ' . $opp_team : ''));

        $is_away = strtolower(trim($venue)) === 'away';
        if ($is_away) {
            $match_type = 'Away match';
            $home_team  = $opposing_team;
            $away_team  = trim($team_name);
        } else {
            $match_type = 'Home match';
            $home_team  = trim($team_name);
            $away_team  = $opposing_team;
        }

        return [
            $formatted_date,
            $start_time,
            '01:00',
            $formatted_date,
            $end_time,
            $match_type,
            $home_team,
            $away_team,
            $fixture_title,
            '',
        ];
    }

    /**
     * Generate Spond CSV content for a given team
     *
     * @param \WP_Term $team
     * @return string
     */
    public function generate_spond_csv(\WP_Term $team): string
    {
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => self::META_DATE,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => self::TAX_TEAM,
                    'field'    => 'term_id',
                    'terms'    => $team->term_id,
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $fixtures = $query->posts ?? [];

        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Start date',
            'Start time',
            'Meet up',
            'End date',
            'End time',
            'Match type',
            'Home team',
            'Away team',
            'Description',
            'Place',
        ], ',', '"', "\\");

        foreach ($fixtures as $fixture_post) {
            $id = is_object($fixture_post) ? ($fixture_post->ID ?? 0) : (int)$fixture_post;
            if (!$id) {
                continue;
            }

            $date_val      = (string) get_post_meta($id, self::META_DATE, true);
            $kick_off_time = (string) get_post_meta($id, self::META_KICK_OFF_TIME, true);
            $venue         = (string) get_post_meta($id, self::META_VENUE, true);

            $opp_club_terms = wp_get_object_terms($id, self::TAX_OPPOSING_CLUB);
            $opp_club = !empty($opp_club_terms) && !is_wp_error($opp_club_terms) && isset($opp_club_terms[0]->name) ? $opp_club_terms[0]->name : '';

            $opp_team_terms = wp_get_object_terms($id, self::TAX_OPPOSING_TEAM);
            $opp_team = !empty($opp_team_terms) && !is_wp_error($opp_team_terms) && isset($opp_team_terms[0]->name) ? $opp_team_terms[0]->name : '';

            $fixture_title = !empty($fixture_post->post_title) ? $fixture_post->post_title : get_the_title($id);

            $row = $this->build_spond_row(
                $team->name,
                $date_val,
                $kick_off_time,
                $venue,
                $opp_club,
                $opp_team,
                $fixture_title
            );

            if ($row !== null) {
                fputcsv($output, $row, ',', '"', "\\");
            }
        }

        rewind($output);
        $csv_data = stream_get_contents($output);
        fclose($output);

        return $csv_data;
    }

    /**
     * Send headers and stream Spond CSV file
     *
     * @param \WP_Term $team
     */
    public function stream_spond_csv(\WP_Term $team): void
    {
        $filename = sprintf('%s-for-spond.csv', sanitize_title($team->name));

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        echo $this->generate_spond_csv($team);
        exit;
    }

    /**
     * Handle Spond Export
     */
    private function handle_spond_export()
    {
        $team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
        if (!$team_id) {
            return;
        }

        $team = get_term($team_id, self::TAX_TEAM);
        if (!$team || is_wp_error($team) || !($team instanceof \WP_Term)) {
            return;
        }

        $this->stream_spond_csv($team);
    }

    /**
     * Handle CSV Upload and Processing
     */
    private function handle_csv_upload(): \WP_Error|array {
        if (!isset($_FILES['fixture_csv']) || empty($_FILES['fixture_csv']['tmp_name'])) {
            return new \WP_Error('no_file', __('No CSV file uploaded.', 'nrfc-fixtures'));
        }

        $file = $_FILES['fixture_csv'];
        if (!empty($file['error'])) {
            return new \WP_Error('upload_error', sprintf(__('Upload error: %s', 'nrfc-fixtures'), (string)$file['error']));
        }

        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh) {
            return new \WP_Error('open_failed', __('Failed to open uploaded file.', 'nrfc-fixtures'));
        }

        // Parse headers
        $headers = fgetcsv($fh, 0, ',', '"', "\\");
        if (!$headers) {
            fclose($fh);
            return new \WP_Error('bad_csv', __('CSV appears to be empty.', 'nrfc-fixtures'));
        }

        $headers = array_map('trim', $headers);
        $map = [];
        $expected_keys = [
            'date', 'team', 'opposing_club', 'opposing_team',
            'competition_type', 'kick_off_time', 'venue', 'notes'
        ];

        foreach ($headers as $idx => $h) {
            $key = strtolower(str_replace(' ', '_', $h));
            if (in_array($key, $expected_keys)) {
                $map[$key] = $idx;
            }
        }

        // Check required columns
        foreach (['date', 'team', 'opposing_club'] as $req) {
            if (!isset($map[$req])) {
                fclose($fh);
                return new \WP_Error('missing_column', sprintf(__('CSV must include a "%s" column.', 'nrfc-fixtures'), $req));
            }
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        while (($row = fgetcsv($fh, 0, ',', "'", "\\")) !== false) {
            if (count(array_filter($row)) === 0) continue;

            $data = array_map( function ( $idx ) use ( $row ) {
                return isset( $row[ $idx ] ) ? sanitize_text_field( trim( $row[ $idx ] ) ) : '';
            }, $map );

            if (empty($data['date']) && empty($data['team']) && (empty($data['opposing_club']) || empty($data['notes']))) {
                $errors[] = __('Skipped row due to missing required data (Date, Team, or either Opposing Club/Notes). ' . print_r($row, true), 'nrfc-fixtures');
                continue;
            }

            $date = $this->clean_up_date($data['date']);
            if (!$date) {
                $errors[] = __(sprintf('Skipped row due to invalid date format: "%s".', $data['date']), 'nrfc-fixtures');
                continue;
            }

            // Get Term IDs
            $team_id           = $this->get_term($this->normaliseTeam($data['team']), self::TAX_TEAM);
            $opp_club_id       = $this->get_term($data['opposing_club'], self::TAX_OPPOSING_CLUB);
            $opp_team_id       = !empty($data['opposing_team']) ? $this->get_term($data['opposing_team'], self::TAX_OPPOSING_TEAM) : null;
            $comp_type_id      = !empty($data['competition_type']) ? $this->get_term($data['competition_type'], self::TAX_COMPETITION) : null;

            $notes             = $data['notes'] ?? '';

            if ($team_id == 0) {
                $errors[] = __(sprintf('Skipped row due to unrecognised team: %s', $data['team']), 'nrfc-fixtures');
                continue;
            }

            if ($opp_club_id == 0 && empty($notes)) {
                $errors[] = __(sprintf('Skipped row due to unrecognised opposing club : %s and empty notes', $data['opposing_club']), 'nrfc-fixtures');
                continue;
            }

            // Check for duplicate
            $existing_id = $this->find_existing_fixture($data['date'], $team_id, $opp_club_id);

            $post_title = $this->makeFixtureTitle($data['team'], $data['opposing_club'], $notes);

            $post_data = [
                'post_type'   => self::CPT,
                'post_title'  => $post_title,
                'post_status' => 'publish',
            ];

            if ($existing_id) {
                $post_data['ID'] = $existing_id;
                $post_id = wp_update_post($post_data);
                $updated++;
            } else {
                $post_id = wp_insert_post($post_data);
                $created++;
            }

            if (is_wp_error($post_id)) {
                $errors[] = sprintf(__('Error saving fixture: %s', 'nrfc-fixtures'), $post_id->get_error_message());
                continue;
            }

            // Save Meta
            update_post_meta($post_id, self::META_DATE, $date->format('Y-m-d'));
            update_post_meta($post_id, self::META_KICK_OFF_TIME, $data['kick_off_time'] ?? "12:00");
            update_post_meta($post_id, self::META_VENUE, $data['venue'] ?? '');
            update_post_meta($post_id, self::META_NOTES, $notes);

            // Save Taxonomies
            wp_set_object_terms($post_id, [$team_id], self::TAX_TEAM);
            wp_set_object_terms($post_id, [$opp_club_id], self::TAX_OPPOSING_CLUB);
            if ($opp_team_id) {
                wp_set_object_terms($post_id, [$opp_team_id], self::TAX_OPPOSING_TEAM);
            }
            if ($comp_type_id) {
                wp_set_object_terms($post_id, [$comp_type_id], self::TAX_COMPETITION);
            }
        }

        fclose($fh);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    private function clean_up_date(string $raw_date): DateTime|bool {
        // Allow only numbers, hyphens, periods, and forward slashes
        $date_string = preg_replace('/[^0-9\-.\/]/', '', $raw_date);

        // Optional: trim any remaining whitespace or control characters
        $date_string = trim($date_string);

        $date = DateTime::createFromFormat('Y-m-d', $date_string);
        if (!$date) {
            // Try other formats with the sanitized string
            $formats = ['Y-m-d', 'Y/m/d', 'Y.m.d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'm-d-Y', 'm/d/Y', 'm.d.Y', 'Ymd'];

            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $date_string);
                if ($date) {
                    break;
                }
            }
        }

        return $date;
    }

    /**
     * Get term ID or create if not exists
     */
    private function get_term($name, $taxonomy): int {
        $term = term_exists($name, $taxonomy);
        if ($term) {
            return (int)(is_array($term) ? $term['term_id'] : $term);
        }

        return 0;
    }

    /**
     * Register the Gutenberg block for displaying fixtures
     */
    public function register_fixtures_block(): void {
        $handle = 'nrfc-fixtures-block';
        $src = plugin_dir_url(dirname(__FILE__)) . 'assets/fixtures-block.js';

        wp_register_script(
            $handle,
            $src,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor'],
            '1.0.0',
            true
        );

        // Fetch teams for the block settings
        $teams = get_terms([
            'taxonomy'   => self::TAX_TEAM,
            'hide_empty' => false,
        ]);

        wp_localize_script($handle, 'NRFCFixturesBlockData', [
            'teams' => $teams,
        ]);

        register_block_type('nrfc-fixtures/fixtures-list', [
            'editor_script'   => $handle,
            'render_callback' => [$this, 'render_fixtures_block'],
            'attributes'      => [
                'teamId'    => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'startDate' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'endDate'   => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Enqueue styles
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_block_styles']);
        }
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles(): void
    {
        wp_enqueue_style(
            'nrfc-fixtures-admin-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/nrfc-fixtures-admin.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Enqueue landing page styles
     */
    public function enqueue_landing_page_styles(): void
    {
        wp_enqueue_style(
                'nrfc-fixtures-page-styles',
                plugin_dir_url(dirname(__FILE__)) . 'assets/nrfc-fixtures-frontend.css',
                [],
                '1.0.0'
        );
    }

    /**
     * Enqueue block styles
     */
    public function enqueue_block_styles(): void {
        wp_enqueue_style(
            'nrfc-fixtures-block-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/fixtures-block.css',
            [],
            '1.0.0'
        );
    }
    /**
     * Server-side render callback for the fixtures block
     */
    public function render_fixtures_block($attributes): bool|string {
        if (!is_admin() && !wp_style_is('nrfc-fixtures-block-styles', 'enqueued')) {
            $this->enqueue_block_styles();
        }

        $team_id    = !empty($attributes['teamId']) ? (int)$attributes['teamId'] : 0;
        $start_date = !empty($attributes['startDate']) ? sanitize_text_field($attributes['startDate']) : '';
        $end_date   = !empty($attributes['endDate']) ? sanitize_text_field($attributes['endDate']) : '';

        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => self::META_DATE,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ];

        $meta_query = [];
        if ($start_date) {
            $meta_query[] = [
                'key'     => self::META_DATE,
                'value'   => $start_date,
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }
        if ($end_date) {
            $meta_query[] = [
                'key'     => self::META_DATE,
                'value'   => $end_date,
                'compare' => '<=',
                'type'    => 'DATE',
            ];
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        if ($team_id > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy' => self::TAX_TEAM,
                    'field'    => 'term_id',
                    'terms'    => $team_id,
                ],
            ];
        }

        $query = new \WP_Query($args);
        $can_edit = current_user_can('edit_posts');

        if (!$query->have_posts()) {
            return '<p>' . esc_html__('No fixtures found.', 'nrfc-fixtures') . '</p>';
        }

        ob_start();
        ?>
        <div class="nrfc-fixtures-list">
            <table class="nrfc-fixtures-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'nrfc-fixtures'); ?></th>
                        <th><?php esc_html_e('Time', 'nrfc-fixtures'); ?></th>
                        <th><?php esc_html_e('Fixture', 'nrfc-fixtures'); ?></th>
                        <th><?php esc_html_e('Venue', 'nrfc-fixtures'); ?></th>
                        <th><?php esc_html_e('Competition', 'nrfc-fixtures'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()) : $query->the_post();
                        $id = get_the_ID();
                        $date = get_post_meta($id, self::META_DATE, true);
                        $time = get_post_meta($id, self::META_KICK_OFF_TIME, true);
                        $venue = get_post_meta($id, self::META_VENUE, true);
                        $comp = wp_get_object_terms($id, self::TAX_COMPETITION, ['fields' => 'names']);
                        $comp_name = !empty($comp) ? $comp[0] : '';

                        if ($time == '00:00') {
                            $time = '';
                        }

                        // Format date for display
                        $display_date = date_i18n(get_option('date_format'), strtotime($date));

                        $fixture_title = get_the_title();
                        if ($can_edit) {
                            $edit_link = get_edit_post_link($id);
                            $fixture_title = sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($fixture_title));
                        } else {
                            $fixture_title = esc_html($fixture_title);
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($display_date); ?></td>
                            <td><?php echo esc_html($time); ?></td>
                            <td><?php echo $fixture_title; ?></td>
                            <td><?php echo esc_html($venue); ?></td>
                            <td><?php echo esc_html($comp_name); ?></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Register the fixtures widget
     */
    public function register_fixtures_widget(): void
    {
        if (class_exists('WP_Widget')) {
            register_widget(__NAMESPACE__ . '\\FixtureListWidget');
        }
    }

    /**
     * Find existing fixture by date, team, and opposing club
     */
    private function find_existing_fixture($date, $team_id, $opp_club_id): int|\WP_Post|null {
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => self::META_DATE,
                    'value' => $date,
                ],
            ],
            'tax_query'      => [
                'relation' => 'AND',
                [
                    'taxonomy' => self::TAX_TEAM,
                    'field'    => 'term_id',
                    'terms'    => $team_id,
                ],
                [
                    'taxonomy' => self::TAX_OPPOSING_CLUB,
                    'field'    => 'term_id',
                    'terms'    => $opp_club_id,
                ],
            ],
        ];

        $query = new \WP_Query($args);
        return !empty($query->posts) ? $query->posts[0] : null;
    }

    private function normaliseTeam(string $team): string
    {
        return match (strtolower($team)) {
            'u12b'  => 'Under 12 Boys',
            'u13b'  => 'Under 13 Boys',
            'u14b'  => 'Under 14 Boys',
            'u15b'  => 'Under 15 Boys',
            'u16b', 'jba' => 'Boys Junior Academy',
            'u18b', 'sba'   => 'Boys Senior Academy',
            'u12g'  => 'Under 12 Girls',
            'u14g'  => 'Under 14 Girls',
            'u16g'  => 'Girls Junior Academy',
            'u18g'  => 'Girls Senior Academy',
            default => $team,
        };
    }

    private function makeFixtureTitle(string $team, $opposing_club, $notes): string {
        $title = $team;
        if ($opposing_club) {
            $title .= ' vs ' . $opposing_club;
        }
        if ($notes) {
            $title .= ' - ' . str_replace('"', '', $notes);
        }
        return $title;
    }
}

/**
 * Fixture List Widget
 */
class FixtureListWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'fixture_list_widget',
            __('Fixtures (NRFC)', 'nrfc-fixtures'),
            ['description' => __('Display filtered list of fixtures.', 'nrfc-fixtures')]
        );
    }

    public function form($instance): void {
        $title     = $instance['title'] ?? '';
        $team_id   = $instance['teamId'] ?? '';
        $startDate = $instance['startDate'] ?? '';
        $endDate   = $instance['endDate'] ?? '';

        $teams = get_terms([
            'taxonomy'   => Fixtures::TAX_TEAM,
            'hide_empty' => false,
        ]);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('teamId')); ?>"><?php _e('Filter by Team:', 'nrfc-fixtures'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('teamId')); ?>" name="<?php echo esc_attr($this->get_field_name('teamId')); ?>">
                <option value=""><?php _e('All Teams', 'nrfc-fixtures'); ?></option>
                <?php foreach ($teams as $team) : ?>
                    <option value="<?php echo esc_attr($team->term_id); ?>" <?php selected($team_id, $team->term_id); ?>>
                        <?php echo esc_html($team->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('startDate')); ?>"><?php _e('Start Date (YYYY-MM-DD):', 'nrfc-fixtures'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('startDate')); ?>" name="<?php echo esc_attr($this->get_field_name('startDate')); ?>" type="date" value="<?php echo esc_attr($startDate); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('endDate')); ?>"><?php _e('End Date (YYYY-MM-DD):', 'nrfc-fixtures'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('endDate')); ?>" name="<?php echo esc_attr($this->get_field_name('endDate')); ?>" type="date" value="<?php echo esc_attr($endDate); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance): array {
        $instance = [];
        $instance['title']     = sanitize_text_field($new_instance['title'] ?? '');
        $instance['teamId']    = sanitize_text_field($new_instance['teamId'] ?? '');
        $instance['startDate'] = sanitize_text_field($new_instance['startDate'] ?? '');
        $instance['endDate']   = sanitize_text_field($new_instance['endDate'] ?? '');
        return $instance;
    }

    public function widget($args, $instance): void {
        $title = !empty($instance['title']) ? $instance['title'] : '';

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        $fixtures = new Fixtures();
        echo $fixtures->render_fixtures_block([
            'teamId'    => $instance['teamId'] ?? '',
            'startDate' => $instance['startDate'] ?? '',
            'endDate'   => $instance['endDate'] ?? '',
        ]);

        echo $args['after_widget'];
    }
}
