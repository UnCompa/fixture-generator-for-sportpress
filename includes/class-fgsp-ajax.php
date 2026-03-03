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
     * @var FGSP_Promotion
     */
    protected $promotion;

    /**
     * Constructor.
     */
    public function __construct($generator)
    {
        $this->generator = $generator;
        if (class_exists('FGSP_Promotion')) {
            $this->promotion = new FGSP_Promotion();
        }
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

        // Get Knockout Events for this tournament (events not linked to a table)
        // Actually SportsPress might link bracket events to the tournament but not a table
        $knockout_events = get_posts(array(
            'post_type' => 'sp_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'sp_tournament',
                    'value' => $tournament_id,
                ),
                array(
                    'key' => 'sp_table',
                    'compare' => 'NOT EXISTS' // Usually bracket events don't belong to a group table
                )
            )
        ));

        $ko_response = array();
        foreach ($knockout_events as $event) {
            $teams = get_post_meta($event->ID, 'sp_team', false);
            $ko_response[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'home_id' => isset($teams[0]) ? $teams[0] : 0,
                'away_id' => isset($teams[1]) ? $teams[1] : 0
            );
        }

        $leagues = get_the_terms($tournament_id, 'sp_league');
        $league_ids = ($leagues && !is_wp_error($leagues)) ? wp_list_pluck($leagues, 'term_id') : array();

        wp_send_json_success(array(
            'groups' => $response,
            'leagues' => $league_ids,
            'knockout_events' => $ko_response
        ));
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
     * Get existing events for a group to display in modal with results.
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

            $teams = get_post_meta($event->ID, 'sp_team', false);
            $results = FGSP_Helpers::get_event_results($event->ID);

            $home_id = isset($teams[0]) ? $teams[0] : 0;
            $away_id = isset($teams[1]) ? $teams[1] : 0;

            $response[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'date' => get_the_time(get_option('date_format') . ' ' . get_option('time_format'), $event),
                'status' => $event->post_status,
                'edit_link' => get_edit_post_link($event->ID),
                'home_id' => $home_id,
                'away_id' => $away_id,
                'home_name' => $home_id ? get_the_title($home_id) : '-',
                'away_name' => $away_id ? get_the_title($away_id) : '-',
                'home_goals' => (isset($results[$home_id]) && isset($results[$home_id]['goals'])) ? $results[$home_id]['goals'] : '',
                'away_goals' => (isset($results[$away_id]) && isset($results[$away_id]['goals'])) ? $results[$away_id]['goals'] : '',
            );
        }

        wp_send_json_success($response);
    }

    /**
     * Save quick results from the modal.
     */
    public function save_quick_results()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $results_data = isset($_POST['results']) ? $_POST['results'] : array();
        if (empty($results_data)) {
            wp_send_json_error('No data to save');
        }

        $count = 0;
        foreach ($results_data as $event_id => $scores) {
            $event_id = intval($event_id);

            $teams = get_post_meta($event_id, 'sp_team', false);
            if (count($teams) < 2)
                continue;

            $home_id = $teams[0];
            $away_id = $teams[1];

            $home_goals = isset($scores['home']) ? $scores['home'] : '';
            $away_goals = isset($scores['away']) ? $scores['away'] : '';

            // If both are empty, we might not want to save a result, but if one is set, we do.
            if ($home_goals === '' && $away_goals === '')
                continue;

            // Determine outcome
            $outcome = array();
            if ($home_goals !== '' && $away_goals !== '') {
                $h = intval($home_goals);
                $a = intval($away_goals);
                if ($h > $a) {
                    $outcome = array($home_id => array('win'), $away_id => array('loss'));
                } elseif ($h < $a) {
                    $outcome = array($home_id => array('loss'), $away_id => array('win'));
                } else {
                    $outcome = array($home_id => array('draw'), $away_id => array('draw'));
                }
            }

            $sp_results = array(
                $home_id => array('goals' => $home_goals, 'outcome' => isset($outcome[$home_id]) ? $outcome[$home_id] : array()),
                $away_id => array('goals' => $away_goals, 'outcome' => isset($outcome[$away_id]) ? $outcome[$away_id] : array())
            );

            update_post_meta($event_id, 'sp_results', $sp_results);

            // Mark as publish if results are entered
            wp_update_post(array(
                'ID' => $event_id,
                'post_status' => 'publish'
            ));

            $count++;
        }

        wp_send_json_success(array('message' => sprintf('Se han guardado %d partidos con éxito.', $count)));
    }

    /**
     * Get standings for a group.
     */
    public function get_group_standings()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;

        if (!$table_id || !$this->promotion) {
            wp_send_json_error('Invalid table ID or Promotion class missing');
        }

        $standings = $this->promotion->get_standings($table_id);
        wp_send_json_success($standings);
    }

    /**
     * Submit promotions to events.
     */
    public function submit_promotions()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $promotions = isset($_POST['promotions']) ? $_POST['promotions'] : array();
        if (empty($promotions) || !$this->promotion) {
            wp_send_json_error('No promotions data provided');
        }

        $count = $this->promotion->promote_to_events($promotions);
        wp_send_json_success(array('message' => sprintf('Se han promovido equipos a %d eventos.', $count)));
    }

    /**
     * Generate playoff events.
     */
    public function generate_playoffs()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $params = array(
            'tournament_id' => isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0,
            'format' => isset($_POST['format']) ? intval($_POST['format']) : 4,
            'legs' => isset($_POST['legs']) ? intval($_POST['legs']) : 1,
        );

        if (!$params['tournament_id']) {
            wp_send_json_error('Invalid tournament ID');
        }

        $result = $this->generator->generate_playoffs($params);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Create a calendar for the tournament.
     */
    public function create_tournament_calendar()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        if (!$tournament_id) {
            wp_send_json_error('Invalid tournament ID');
        }

        $calendar_id = $this->generator->create_tournament_calendar($tournament_id);

        if (!$calendar_id) {
            wp_send_json_error('Failed to create calendar');
        }

        wp_send_json_success(array(
            'calendar_id' => $calendar_id,
            'edit_link' => get_edit_post_link($calendar_id, 'raw'),
            'message' => __('Calendario generado con éxito.', 'fixture-generator-for-sportpress')
        ));
    }

    /**
     * Create a full league setup (Tournament, League Table, Taxonomies) from a single name.
     */
    public function create_league_full()
    {
        check_ajax_referer('fgsp_nonce', 'nonce');

        $raw_data = isset($_POST['data']) ? $_POST['data'] : '';
        parse_str($raw_data, $form_data);

        $league_name = isset($form_data['league_name']) ? sanitize_text_field($form_data['league_name']) : '';
        $team_ids = isset($form_data['team_ids']) ? array_map('intval', (array) $form_data['team_ids']) : array();

        if (empty($league_name)) {
            wp_send_json_error(__('Por favor ingresa un nombre para la liga.', 'fixture-generator-for-sportpress'));
        }

        error_log("FGSP: Creando liga completa: " . $league_name);

        // 1. Create main Competition post (sp_tournament)
        $tournament_id = wp_insert_post(array(
            'post_title' => $league_name,
            'post_type' => 'sp_tournament',
            'post_status' => 'publish'
        ));

        if (is_wp_error($tournament_id)) {
            wp_send_json_error(__('Error al crear el torneo: ', 'fixture-generator-for-sportpress') . $tournament_id->get_error_message());
        }

        // 2. Auto-manage Taxonomies (League and Season)
        // Competition Taxonomy
        $league_term = get_term_by('name', $league_name, 'sp_league');
        if (!$league_term) {
            $league_term = wp_insert_term($league_name, 'sp_league');
        }
        $league_term_id = (!is_wp_error($league_term)) ? (is_array($league_term) ? $league_term['term_id'] : $league_term->term_id) : 0;

        // Season Taxonomy
        $current_year = date('Y');
        $season_name = sprintf(__('Temporada %s', 'fixture-generator-for-sportpress'), $current_year);
        $season_term = get_term_by('name', $season_name, 'sp_season');
        if (!$season_term) {
            $season_term = wp_insert_term($season_name, 'sp_season');
        }
        $season_term_id = (!is_wp_error($season_term)) ? (is_array($season_term) ? $season_term['term_id'] : $season_term->term_id) : 0;

        // Assign terms to tournament
        if ($league_term_id)
            wp_set_object_terms($tournament_id, intval($league_term_id), 'sp_league');
        if ($season_term_id)
            wp_set_object_terms($tournament_id, intval($season_term_id), 'sp_season');

        // 3. Create Standing Table (sp_table) and assign teams
        $table_id = $this->generator->create_group($tournament_id, $league_name, $team_ids);

        if (is_wp_error($table_id)) {
            wp_send_json_error($table_id->get_error_message());
        }

        // 4. Link teams AND their players to the new competition taxonomies
        foreach ($team_ids as $tid) {
            if ($league_term_id)
                wp_set_object_terms($tid, intval($league_term_id), 'sp_league', true);
            if ($season_term_id)
                wp_set_object_terms($tid, intval($season_term_id), 'sp_season', true);

            // Get players for this team and link them too
            $player_ids = FGSP_Helpers::get_team_players_ids($tid);
            if (!empty($player_ids)) {
                foreach ($player_ids as $pid) {
                    if ($league_term_id)
                        wp_set_object_terms($pid, intval($league_term_id), 'sp_league', true);
                    if ($season_term_id)
                        wp_set_object_terms($pid, intval($season_term_id), 'sp_season', true);
                }
            }
        }

        // 5. Update redirect link to be cleaner
        $redirect_url = admin_url('post.php?post=' . $table_id . '&action=edit');

        wp_send_json_success(array(
            'message' => __('¡Liga y Torneo creados exitosamente! Redirigiendo a la tabla...', 'fixture-generator-for-sportpress'),
            'redirect_url' => $redirect_url
        ));
    }
}
