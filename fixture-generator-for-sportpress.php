<?php
/**
 * Plugin Name: Fixture Generator for SportsPress
 * Description: Automatically generates fixtures for SportsPress tournaments and league tables.
 * Version: 1.0.0
 * Author: Brandon
 * Text Domain: fixture-generator-for-sportpress
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Plugin
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Meta box for individual groups
        add_action('add_meta_boxes', array($this, 'add_meta_box'));

        // AJAX handlers
        add_action('wp_ajax_fgsp_get_tournament_groups', array($this, 'ajax_get_tournament_groups'));
        add_action('wp_ajax_fgsp_generate_fixtures', array($this, 'ajax_generate_fixtures'));
        add_action('wp_ajax_fgsp_create_tournament_group', array($this, 'ajax_create_tournament_group'));
    }

    public static function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fgsp_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            table_id bigint(20) NOT NULL,
            tournament_id bigint(20) NOT NULL,
            algorithm varchar(50) NOT NULL,
            event_count int(11) NOT NULL,
            event_ids longtext NOT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY table_id (table_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_meta_box()
    {
        add_meta_box(
            'fgsp-individual-generator',
            __('Quick Fixture Generator', 'fixture-generator-for-sportpress'),
            array($this, 'render_meta_box'),
            'sp_table',
            'side',
            'high'
        );

        add_meta_box(
            'fgsp-generation-history',
            __('Fixture Generation History', 'fixture-generator-for-sportpress'),
            array($this, 'render_history_meta_box'),
            array('sp_table', 'sp_tournament'),
            'normal',
            'low'
        );

        add_meta_box(
            'fgsp-tournament-groups-manager',
            __('Fixture Groups Manager', 'fixture-generator-for-sportpress'),
            array($this, 'render_tournament_groups_meta_box'),
            'sp_tournament',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        $team_ids = get_post_meta($post->ID, 'sp_teams', true);
        $count = is_array($team_ids) ? count(array_filter(array_keys($team_ids))) : 0;
        $tournament_id = get_post_meta($post->ID, 'sp_tournament', true);
        ?>
        <div class="fgsp-meta-box-content">
            <p><strong><?php echo $count; ?></strong> <?php _e('teams detected.', 'fixture-generator-for-sportpress'); ?></p>
            <?php if ($count < 2): ?>
                <div class="notice notice-warning inline">
                    <p><?php _e('Need at least 2 teams.', 'fixture-generator-for-sportpress'); ?></p>
                </div>
            <?php else: ?>
                <button type="button" id="fgsp-open-modal" class="button button-primary fgsp-btn-premium-small">
                    <span class="dashicons dashicons-randomize"></span>
                    <?php _e('Generate Fixtures', 'fixture-generator-for-sportpress'); ?>
                </button>
                <p style="text-align: center; margin-top: 10px;">
                    <a href="<?php echo admin_url('admin.php?page=fgsp-generator&sp_table=' . $post->ID); ?>"
                        style="font-size: 11px;">
                        <?php _e('Use advanced generator', 'fixture-generator-for-sportpress'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Hidden Modal for Quick Generation -->
        <div id="fgsp-quick-modal" class="fgsp-modal" style="display:none;">
            <div class="fgsp-modal-content">
                <div class="fgsp-modal-header">
                    <h3><?php _e('Quick Fixture Generation', 'fixture-generator-for-sportpress'); ?></h3>
                    <span class="fgsp-close-modal">&times;</span>
                </div>
                <div class="fgsp-modal-body">
                    <div class="fgsp-config-section">
                        <input type="hidden" id="fgsp-modal-tournament-id" value="<?php echo esc_attr($tournament_id); ?>">
                        <input type="hidden" id="fgsp-modal-table-id" value="<?php echo esc_attr($post->ID); ?>">

                        <div class="fgsp-field">
                            <label><?php _e('Algorithm', 'fixture-generator-for-sportpress'); ?></label>
                            <select id="fgsp-modal-algorithm" class="fgsp-algorithm-select" style="width: 100%;">
                                <option value="round-robin">
                                    <?php _e('Round Robin (Ida y Vuelta)', 'fixture-generator-for-sportpress'); ?>
                                </option>
                                <option value="single-round-robin">
                                    <?php _e('Round Robin (Solo Ida)', 'fixture-generator-for-sportpress'); ?>
                                </option>
                                <option value="random"><?php _e('Random Matchmaking', 'fixture-generator-for-sportpress'); ?>
                                </option>
                            </select>
                        </div>

                        <div style="display:flex; gap:10px; margin-top:15px;">
                            <div class="fgsp-field" style="flex:1;">
                                <label><?php _e('Start Date', 'fixture-generator-for-sportpress'); ?></label>
                                <input type="date" id="fgsp-modal-date" value="<?php echo date('Y-m-d'); ?>"
                                    style="width: 100%;">
                            </div>
                            <div class="fgsp-field" style="flex:1;">
                                <label><?php _e('Time', 'fixture-generator-for-sportpress'); ?></label>
                                <input type="time" id="fgsp-modal-time" value="18:00" style="width: 100%;">
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; margin-top:15px; align-items:center;">
                            <div class="fgsp-field" style="flex:1;">
                                <label><?php _e('Interval (Days)', 'fixture-generator-for-sportpress'); ?></label>
                                <input type="number" id="fgsp-modal-interval" value="7" min="1" style="width: 100%;">
                            </div>
                            <div class="fgsp-field" style="flex:1; padding-top:20px;">
                                <label style="cursor:pointer;">
                                    <input type="checkbox" id="fgsp-modal-balance" checked>
                                    <?php _e('Balance Home/Away', 'fixture-generator-for-sportpress'); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="fgsp-modal-progress" class="fgsp-progress-container" style="display:none; margin-top:20px;">
                        <div class="fgsp-progress-bar">
                            <div class="fgsp-progress-fill" style="width: 100%;"></div>
                        </div>
                        <p class="fgsp-progress-text"><?php _e('Generating...', 'fixture-generator-for-sportpress'); ?></p>
                    </div>
                </div>
                <div class="fgsp-modal-footer">
                    <button type="button" id="fgsp-modal-cancel"
                        class="button"><?php _e('Cancel', 'fixture-generator-for-sportpress'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_tournament_groups_meta_box($post)
    {
        // Get tournament leagues
        $leagues = get_the_terms($post->ID, 'sp_league');
        $league_ids = array();

        if ($leagues && !is_wp_error($leagues)) {
            $league_ids = wp_list_pluck($leagues, 'term_id');
        }

        $args = array(
            'post_type' => 'sp_team',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        // Filter by league if set
        if (!empty($league_ids)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'sp_league',
                    'field' => 'term_id',
                    'terms' => $league_ids,
                ),
            );
        }

        $teams = get_posts($args);

        // Get currently associated groups (sp_table posts)
        $tables = get_posts(array(
            'post_type' => 'sp_table',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'sp_tournament',
                    'value' => $post->ID
                )
            )
        ));
        ?>
        <div class="fgsp-tournament-groups-ui">
            <div id="fgsp-new-group-form"
                style="background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e2e4e7; margin-bottom:20px;">
                <h4 style="margin-top:0;"><?php _e('Create New Group', 'fixture-generator-for-sportpress'); ?></h4>
                <div class="fgsp-field" style="margin-bottom:10px;">
                    <label
                        style="display:block; margin-bottom:5px; font-weight:600;"><?php _e('Group Name', 'fixture-generator-for-sportpress'); ?></label>
                    <input type="text" id="fgsp-new-group-name" placeholder="Ex: Grupo A, Fase 1..." style="width:100%;">
                </div>
                <div class="fgsp-field">
                    <label
                        style="display:block; margin-bottom:5px; font-weight:600;"><?php _e('Select Teams', 'fixture-generator-for-sportpress'); ?></label>
                    <div class="fgsp-team-selector-grid"
                        style="max-height:150px; overflow-y:auto; border:1px solid #ddd; padding:10px; background:#fff; border-radius:4px; display:grid; grid-template-columns: repeat(2, 1fr); gap: 5px;">
                        <?php foreach ($teams as $team): ?>
                            <label style="font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                                <input type="checkbox" name="fgsp_teams[]" value="<?php echo $team->ID; ?>">
                                <?php echo esc_html($team->post_title); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="fgsp-tournament-id" value="<?php echo $post->ID; ?>">
                <button type="button" id="fgsp-create-group-btn" class="button button-primary"
                    style="margin-top:15px; width:100%;">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align:middle; line-height:1.5;"></span>
                    <?php _e('Create Group & Assign', 'fixture-generator-for-sportpress'); ?>
                </button>
            </div>

            <div id="fgsp-existing-groups">
                <h4 style="border-bottom:1px solid #eee; padding-bottom:5px;">
                    <?php _e('Associated Groups', 'fixture-generator-for-sportpress'); ?>
                </h4>
                <?php if (empty($tables)): ?>
                    <p id="fgsp-no-groups-msg" style="font-style:italic; color:#777;">
                        <?php _e('No groups created for this tournament yet.', 'fixture-generator-for-sportpress'); ?>
                    </p>
                <?php endif; ?>
                <div class="fgsp-groups-list">
                    <?php foreach ($tables as $table):
                        $table_teams = get_post_meta($table->ID, 'sp_teams', true);
                        $team_count = is_array($table_teams) ? count(array_filter(array_keys($table_teams))) : 0;
                        ?>
                        <div class="fgsp-group-item"
                            style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #eee;">
                            <span><strong><?php echo esc_html($table->post_title); ?></strong> (<?php echo $team_count; ?>
                                teams)</span>
                            <a href="<?php echo get_edit_post_link($table->ID); ?>" class="button button-small"
                                target="_blank"><?php _e('Edit', 'fixture-generator-for-sportpress'); ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_history_meta_box($post)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fgsp_logs';

        // Fail-safe for table existence
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            self::activate();
        }

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE (table_id = %d OR tournament_id = %d) ORDER BY generated_at DESC LIMIT 10",
            $post->ID,
            $post->ID
        ));

        if (empty($logs)) {
            echo '<p>' . __('No generation history found for this group.', 'fixture-generator-for-sportpress') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'fixture-generator-for-sportpress'); ?></th>
                    <th><?php _e('Algorithm', 'fixture-generator-for-sportpress'); ?></th>
                    <th><?php _e('Events', 'fixture-generator-for-sportpress'); ?></th>
                    <th><?php _e('Actions', 'fixture-generator-for-sportpress'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->generated_at)); ?>
                        </td>
                        <td><code
                                style="background:#eee; padding:2px 5px; border-radius:3px;"><?php echo esc_html($log->algorithm); ?></code>
                        </td>
                        <td><strong><?php echo intval($log->event_count); ?></strong></td>
                        <td>
                            <?php
                            $event_ids = json_decode($log->event_ids, true);
                            if (is_array($event_ids)):
                                $event_links = array();
                                foreach (array_slice($event_ids, 0, 3) as $eid) {
                                    $event_links[] = '<a href="' . get_edit_post_link($eid) . '" target="_blank">#' . $eid . '</a>';
                                }
                                echo implode(', ', $event_links);
                                if (count($event_ids) > 3)
                                    echo '...';
                            endif;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function add_menu()
    {
        add_submenu_page(
            'edit.php?post_type=sp_event',
            __('Fixture Generator', 'fixture-generator-for-sportpress'),
            __('Fixture Generator', 'fixture-generator-for-sportpress'),
            'manage_options',
            'fgsp-generator',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_assets($hook)
    {
        global $post;
        $is_generator_page = strpos($hook, 'fgsp-generator') !== false;
        $is_editor = ($hook === 'post.php' || $hook === 'post-new.php') && isset($post);
        $is_supported_post_type = $is_editor && in_array($post->post_type, array('sp_table', 'sp_tournament'));

        if (!$is_generator_page && !$is_supported_post_type) {
            return;
        }

        wp_enqueue_style('fgsp-admin-css', plugins_url('assets/css/admin.css', __FILE__), array(), '1.0.0');
        wp_enqueue_script('fgsp-admin-js', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0.0', true);

        wp_localize_script('fgsp-admin-js', 'fgspData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fgsp_nonce'),
        ));
    }

    public function render_admin_page()
    {
        $tournaments = get_posts(array(
            'post_type' => 'sp_tournament',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));

        $preselected_table = isset($_GET['sp_table']) ? intval($_GET['sp_table']) : 0;
        $preselected_tournament = 0;

        if ($preselected_table) {
            $preselected_tournament = get_post_meta($preselected_table, 'sp_tournament', true);
        }

        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }

    public function ajax_get_tournament_groups()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;

        if (!$tournament_id) {
            wp_send_json_error('Invalid tournament ID');
        }

        $groups = get_posts(array(
            'post_type' => 'sp_table',
            'meta_query' => array(
                array(
                    'key' => 'sp_tournament',
                    'value' => $tournament_id,
                ),
            ),
            'posts_per_page' => -1,
        ));

        $response = array();
        foreach ($groups as $group) {
            $team_ids = get_post_meta($group->ID, 'sp_teams', true);
            if (!is_array($team_ids)) {
                $team_ids = array();
            }

            $teams = array();
            foreach (array_keys($team_ids) as $team_id) {
                if (!$team_id)
                    continue;
                $teams[] = array(
                    'id' => $team_id,
                    'name' => get_the_title($team_id),
                );
            }

            $response[] = array(
                'id' => $group->ID,
                'title' => $group->post_title,
                'teams' => $teams,
            );
        }

        wp_send_json_success($response);
    }

    public function ajax_generate_fixtures()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
        $algorithm = isset($_POST['algorithm']) ? sanitize_text_field($_POST['algorithm']) : 'round-robin';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d');
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '18:00';
        $interval = isset($_POST['interval']) ? intval($_POST['interval']) : 7;
        $balance_home = isset($_POST['balance_home']) ? (bool) $_POST['balance_home'] : false;

        $dt_string = $start_date . ' ' . $start_time;

        error_log("FGSP: Starting generation - Tournament: $tournament_id, Table: $table_id, Algorithm: $algorithm");

        if (!$tournament_id || !$table_id) {
            wp_send_json_error('Missing parameters');
        }

        // Get teams
        $team_ids_meta = get_post_meta($table_id, 'sp_teams', true);
        if (!is_array($team_ids_meta)) {
            error_log("FGSP Error: No teams metadata found for Table: $table_id");
            wp_send_json_error('No teams found in this group');
        }
        $team_ids = array_keys($team_ids_meta);
        $team_ids = array_filter($team_ids);

        error_log("FGSP: Found " . count($team_ids) . " teams: " . implode(', ', $team_ids));

        if (count($team_ids) < 2) {
            wp_send_json_error('At least 2 teams required');
        }

        // Get Taxonomies (League and Season) from existing events or tournament
        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');

        $league_id = ($leagues && !is_wp_error($leagues)) ? $leagues[0]->term_id : 0;
        $season_id = ($seasons && !is_wp_error($seasons)) ? $seasons[0]->term_id : 0;

        error_log("FGSP: League: $league_id, Season: $season_id");

        $rounds = array();
        if (strpos($algorithm, 'round-robin') !== false) {
            $rounds = $this->generate_round_robin_schedule($team_ids, $balance_home);

            if ($algorithm === 'round-robin' || $algorithm === 'reverse-round-robin') {
                $second_half = array();
                foreach ($rounds as $matches) {
                    $swapped = array();
                    foreach ($matches as $match) {
                        $swapped[] = array($match[1], $match[0]);
                    }
                    $second_half[] = $swapped;
                }

                if ($algorithm === 'reverse-round-robin') {
                    $rounds = array_merge($second_half, $rounds);
                } else {
                    $rounds = array_merge($rounds, $second_half);
                }
            }
        } elseif ($algorithm === 'knockout') {
            $rounds = $this->generate_knockout_schedule($team_ids);
        } else {
            // Random
            shuffle($team_ids);
            $matches = array();
            for ($i = 0; $i < count($team_ids); $i += 2) {
                if (isset($team_ids[$i + 1])) {
                    $matches[] = array($team_ids[$i], $team_ids[$i + 1]);
                }
            }
            $rounds[] = $matches;
        }

        error_log("FGSP: Generated " . count($rounds) . " rounds");

        $created_count = 0;
        $created_event_ids = array();
        $current_timestamp = strtotime($dt_string);

        foreach ($rounds as $r_idx => $matches) {
            $round_num = $r_idx + 1;
            $event_date = date('Y-m-d H:i:s', $current_timestamp);

            foreach ($matches as $match) {
                $home_id = $match[0];
                $away_id = $match[1];

                $event_title = get_the_title($home_id) . ' vs ' . get_the_title($away_id);

                $event_id = wp_insert_post(array(
                    'post_title' => $event_title,
                    'post_type' => 'sp_event',
                    'post_status' => 'publish',
                    'post_date' => $event_date,
                ));

                if ($event_id) {
                    error_log("FGSP: Created Event $event_id: $event_title (Round $round_num, Date: $event_date)");
                    update_post_meta($event_id, 'sp_team', $home_id);
                    add_post_meta($event_id, 'sp_team', $away_id);
                    update_post_meta($event_id, 'sp_tournament', $tournament_id);
                    update_post_meta($event_id, 'sp_table', $table_id);
                    update_post_meta($event_id, 'sp_day', $round_num);
                    update_post_meta($event_id, 'sp_format', 'league');

                    if ($league_id)
                        wp_set_object_terms($event_id, intval($league_id), 'sp_league');
                    if ($season_id)
                        wp_set_object_terms($event_id, intval($season_id), 'sp_season');

                    $created_count++;
                    $created_event_ids[] = $event_id;
                }
            }
            $current_timestamp += ($interval * DAY_IN_SECONDS);
        }

        // Log to database
        if ($created_count > 0) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'fgsp_logs',
                array(
                    'table_id' => $table_id,
                    'tournament_id' => $tournament_id,
                    'algorithm' => $algorithm,
                    'event_count' => $created_count,
                    'event_ids' => json_encode($created_event_ids),
                    'generated_at' => current_time('mysql')
                )
            );
        }

        wp_send_json_success(array('count' => $created_count));
    }

    public function ajax_create_tournament_group()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        $group_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $team_ids = isset($_POST['team_ids']) ? array_map('intval', $_POST['team_ids']) : array();

        if (!$tournament_id || !$group_name) {
            wp_send_json_error('Missing required data.');
        }

        // Create the sp_table post
        $table_id = wp_insert_post(array(
            'post_title' => $group_name,
            'post_type' => 'sp_table',
            'post_status' => 'publish',
        ));

        if (!$table_id || is_wp_error($table_id)) {
            wp_send_json_error('Failed to create group.');
        }

        // Standard SportsPress setup for tables (Matching ID 443)
        update_post_meta($table_id, 'sp_mode', 'team');
        update_post_meta($table_id, 'sp_format', 'standings');
        update_post_meta($table_id, 'sp_select', 'manual');
        update_post_meta($table_id, 'sp_orderby', 'default');
        update_post_meta($table_id, 'sp_order', 'ASC');

        // Default columns mapping
        $columns = array('p', 'w', 'd', 'l', 'f', 'a', 'gd', 'pts');
        update_post_meta($table_id, 'sp_columns', $columns);

        // Link to tournament
        update_post_meta($table_id, 'sp_tournament', $tournament_id);

        // Assign teams with full stats structure
        $teams_meta = array();
        delete_post_meta($table_id, 'sp_team'); // Clear defaults if any
        add_post_meta($table_id, 'sp_team', '0'); // SportsPress padding/offset

        foreach ($team_ids as $tid) {
            $teams_meta[$tid] = array(
                'name' => '',
                'p' => '',
                'w' => '',
                'd' => '',
                'l' => '',
                'f' => '',
                'a' => '',
                'gd' => '',
                'pts' => ''
            );
            add_post_meta($table_id, 'sp_team', $tid);
        }
        update_post_meta($table_id, 'sp_teams', $teams_meta);

        // Inherit Taxonomies from Tournament
        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');

        if ($leagues && !is_wp_error($leagues)) {
            wp_set_object_terms($table_id, intval($leagues[0]->term_id), 'sp_league');
        }
        if ($seasons && !is_wp_error($seasons)) {
            wp_set_object_terms($table_id, intval($seasons[0]->term_id), 'sp_season');
        }

        wp_send_json_success(array('table_id' => $table_id));
    }

    private function generate_round_robin_schedule($teams, $balance = true)
    {
        if (count($teams) % 2 != 0) {
            $teams[] = null; // bye
        }
        $n = count($teams);
        $rounds = array();
        for ($r = 0; $r < $n - 1; $r++) {
            $round_matches = array();
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $teams[$i];
                $away = $teams[$n - 1 - $i];
                if ($home !== null && $away !== null) {
                    // Balancing home/away
                    if ($balance && ($i === 0 ? ($r % 2 === 0) : (($i + $r) % 2 === 0))) {
                        $round_matches[] = array($away, $home);
                    } else {
                        $round_matches[] = array($home, $away);
                    }
                }
            }
            $rounds[] = $round_matches;

            // Rotate
            $last = array_pop($teams);
            array_splice($teams, 1, 0, array($last));
        }
        return $rounds;
    }

    private function generate_knockout_schedule($teams)
    {
        shuffle($teams);
        $matches = array();
        for ($i = 0; $i < count($teams); $i += 2) {
            if (isset($teams[$i + 1])) {
                $matches[] = array($teams[$i], $teams[$i + 1]);
            }
        }
        return array($matches); // Simple single round for knockout for now
    }
}

// Inicializar el plugin
function init()
{
    return FGSP_Plugin::get_instance();
}

// Ejecutar inicialización inmediatamente
init();
register_activation_hook(__FILE__, array('FGSP_Plugin', 'activate'));
