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
    }

    public function render_meta_box($post)
    {
        $team_ids = get_post_meta($post->ID, 'sp_teams', true);
        $count = is_array($team_ids) ? count(array_filter(array_keys($team_ids))) : 0;
        ?>
        <div class="fgsp-meta-box-content">
            <p><strong><?php echo $count; ?></strong> <?php _e('teams detected.', 'fixture-generator-for-sportpress'); ?></p>
            <?php if ($count < 2): ?>
                <div class="notice notice-warning inline">
                    <p><?php _e('Need at least 2 teams.', 'fixture-generator-for-sportpress'); ?></p>
                </div>
            <?php else: ?>
                <a href="<?php echo admin_url('admin.php?page=fgsp-generator&sp_table=' . $post->ID); ?>"
                    class="button button-primary fgsp-btn-premium-small">
                    <?php _e('Configure & Generate', 'fixture-generator-for-sportpress'); ?>
                </a>
            <?php endif; ?>
        </div>
        <style>
            .fgsp-btn-premium-small {
                background: #2ecc71 !important;
                border: none !important;
                color: white !important;
                text-align: center;
                display: block !important;
                width: 100%;
            }
        </style>
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
        if (strpos($hook, 'fgsp-generator') === false) {
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
        $interval = isset($_POST['interval']) ? intval($_POST['interval']) : 7;

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
        if ($algorithm === 'round-robin' || $algorithm === 'single-round-robin') {
            $rounds = $this->generate_round_robin_schedule($team_ids);

            if ($algorithm === 'round-robin') {
                $second_half = array();
                foreach ($rounds as $matches) {
                    $swapped = array();
                    foreach ($matches as $match) {
                        $swapped[] = array($match[1], $match[0]);
                    }
                    $second_half[] = $swapped;
                }
                $rounds = array_merge($rounds, $second_half);
            }
        } else {
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
        $current_timestamp = strtotime($start_date);

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
                }
            }
            $current_timestamp += ($interval * DAY_IN_SECONDS);
        }

        wp_send_json_success(array('count' => $created_count));
    }

    private function generate_round_robin_schedule($teams)
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
                    $round_matches[] = array($home, $away);
                }
            }
            $rounds[] = $round_matches;

            // Rotate
            $last = array_pop($teams);
            array_splice($teams, 1, 0, array($last));
        }
        return $rounds;
    }
}

// Inicializar el plugin
function init()
{
    return FGSP_Plugin::get_instance();
}

// Ejecutar inicialización inmediatamente
init();
