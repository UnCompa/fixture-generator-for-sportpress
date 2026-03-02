<?php
/**
 * AJAX handlers for Fixture Generator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Ajax
{
    /**
     * @var FGSP_Generator
     */
    protected $generator;

    /**
     * Constructor.
     */
    public function __construct($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get groups associated with a tournament.
     */
    public function get_tournament_groups()
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
            $team_ids = is_array($team_ids) ? $team_ids : array();

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
                'has_fixtures' => !empty(FGSP_Helpers::get_existing_events($group->ID)),
                'is_played' => FGSP_Helpers::is_group_played($group->ID)
            );
        }

        $leagues = get_the_terms($tournament_id, 'sp_league');
        $league_ids = ($leagues && !is_wp_error($leagues)) ? wp_list_pluck($leagues, 'term_id') : array();

        wp_send_json_success(array('groups' => $response, 'leagues' => $league_ids));
    }

    /**
     * Get teams eligible for a tournament based on league and season.
     */
    public function get_eligible_teams()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        if (!$tournament_id) {
            wp_send_json_error('Invalid tournament ID');
        }

        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');

        $args = array(
            'post_type' => 'sp_team',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        $tax_query = array('relation' => 'AND');
        if ($leagues && !is_wp_error($leagues)) {
            $tax_query[] = array('taxonomy' => 'sp_league', 'field' => 'term_id', 'terms' => wp_list_pluck($leagues, 'term_id'));
        }
        if ($seasons && !is_wp_error($seasons)) {
            $tax_query[] = array('taxonomy' => 'sp_season', 'field' => 'term_id', 'terms' => wp_list_pluck($seasons, 'term_id'));
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        $teams = get_posts($args);
        $response = array();
        foreach ($teams as $team) {
            $response[] = array('id' => $team->ID, 'name' => $team->post_title);
        }

        wp_send_json_success($response);
    }

    /**
     * Create a new tournament group.
     */
    public function create_tournament_group()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $team_ids = isset($_POST['team_ids']) ? array_map('intval', $_POST['team_ids']) : array();

        if (!$tournament_id || !$name) {
            wp_send_json_error('Missing required data.');
        }

        $result = $this->generator->create_group($tournament_id, $name, $team_ids);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('table_id' => $result));
    }

    /**
     * Generate fixtures.
     */
    public function generate_fixtures()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
        $confirm_overwrite = isset($_POST['confirm_overwrite']) ? (bool) $_POST['confirm_overwrite'] : false;

        // Validations
        if (FGSP_Helpers::is_group_played($table_id)) {
            wp_send_json_error(__('Este grupo ya tiene partidos jugados. La regeneración de fixtures está bloqueada.', 'fixture-generator-for-sportpress'));
        }

        $existing_events = FGSP_Helpers::get_existing_events($table_id);
        if (!empty($existing_events) && !$confirm_overwrite) {
            wp_send_json_success(array(
                'status' => 'confirmation_required',
                'message' => __('Ya existe un fixture generado para este grupo. ¿Deseas regenerar?', 'fixture-generator-for-sportpress')
            ));
        }

        if ($confirm_overwrite) {
            FGSP_Helpers::delete_existing_fixtures($table_id);
        }

        // Prepare parameters for Generator
        $params = array(
            'tournament_id' => isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0,
            'table_id' => $table_id,
            'algorithm' => isset($_POST['algorithm']) ? sanitize_text_field($_POST['algorithm']) : 'round-robin',
            'start_date' => isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d'),
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '18:00',
            'interval' => isset($_POST['interval']) ? intval($_POST['interval']) : 7,
            'balance_home' => isset($_POST['balance_home']) ? (bool) $_POST['balance_home'] : false,
            'round_prefix' => isset($_POST['round_prefix']) ? sanitize_text_field($_POST['round_prefix']) : 'Jornada',
            'exclude_dates' => !empty($_POST['exclude_dates']) ? array_map('trim', explode(',', $_POST['exclude_dates'])) : array(),
            'shuffle_teams' => isset($_POST['shuffle_teams']) ? (bool) $_POST['shuffle_teams'] : false,
            'allowed_days' => isset($_POST['allowed_days']) ? (array) $_POST['allowed_days'] : array(),
            'rotate_times' => isset($_POST['rotate_times']) ? sanitize_text_field($_POST['rotate_times']) : '',
            'assign_venue' => isset($_POST['assign_venue']) ? (bool) $_POST['assign_venue'] : false,
        );

        $result = $this->generator->generate($params);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Get existing events for a group to display in modal.
     */
    public function get_group_events()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
        if (!$table_id) {
            wp_send_json_error('Invalid table ID');
        }

        $event_ids = FGSP_Helpers::get_existing_events($table_id);

        $response = array();
        foreach ($event_ids as $event_id) {
            $event = get_post($event_id);
            if (!$event)
                continue;

            $response[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'date' => get_the_time(get_option('date_format') . ' ' . get_option('time_format'), $event),
                'status' => $event->post_status,
                'edit_link' => get_edit_post_link($event->ID)
            );
        }

        wp_send_json_success($response);
    }
}
