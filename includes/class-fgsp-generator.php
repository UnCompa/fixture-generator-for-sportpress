<?php
/**
 * Core fixture generator service.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'class-fgsp-algorithms.php';
require_once plugin_dir_path(__FILE__) . 'class-fgsp-helpers.php';

class FGSP_Generator
{
    /**
     * Generate fixtures based on the provided parameters.
     *
     * @param array $params Generation parameters (tournament_id, table_id, algorithm, etc.)
     * @return array|WP_Error Array of results or WP_Error object.
     */
    public function generate($params)
    {
        $defaults = array(
            'tournament_id' => 0,
            'table_id' => 0,
            'algorithm' => 'round-robin',
            'start_date' => date('Y-m-d'),
            'start_time' => '18:00',
            'interval' => 7,
            'balance_home' => false,
            'round_prefix' => 'Jornada',
            'exclude_dates' => array(),
            'shuffle_teams' => false,
            'allowed_days' => array(),
            'rotate_times' => '',
            'assign_venue' => false
        );

        $params = wp_parse_args($params, $defaults);
        $tournament_id = intval($params['tournament_id']);
        $table_id = intval($params['table_id']);

        error_log("FGSP: Starting generation - Tournament: $tournament_id, Table: $table_id, Algorithm: {$params['algorithm']}");

        if (!$tournament_id || !$table_id) {
            return new WP_Error('missing_params', 'Missing tournament or group ID');
        }

        // Get tournament title for suffix if requested
        $tournament_title = get_the_title($tournament_id);

        // Get teams
        $team_ids_meta = get_post_meta($table_id, 'sp_teams', true);
        if (!is_array($team_ids_meta)) {
            error_log("FGSP Error: No teams metadata found for Table: $table_id");
            return new WP_Error('no_teams', 'No teams found in this group');
        }

        $team_ids = array_keys($team_ids_meta);
        $team_ids = array_filter($team_ids);

        if ($params['shuffle_teams']) {
            shuffle($team_ids);
        }

        if (count($team_ids) < 2) {
            return new WP_Error('too_few_teams', 'At least 2 teams required');
        }

        // Get Taxonomies (League and Season) from tournament
        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');
        $league_id = ($leagues && !is_wp_error($leagues)) ? $leagues[0]->term_id : 0;
        $season_id = ($seasons && !is_wp_error($seasons)) ? $seasons[0]->term_id : 0;

        // Pre-fetch all players for each team
        $players_by_team = array();
        foreach ($team_ids as $team_id) {
            $players_by_team[$team_id] = FGSP_Helpers::get_team_players_ids($team_id);
        }

        // Determine scheduling rounds
        $rounds = array();
        $algorithm = $params['algorithm'];

        if (strpos($algorithm, 'round-robin') !== false) {
            $rounds = FGSP_Algorithms::generate_round_robin($team_ids, $params['balance_home']);

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
        } elseif ($algorithm === 'playoffs-single') {
            // Sort teams by points
            uasort($team_ids_meta, function ($a, $b) {
                $pts_a = isset($a['pts']) ? intval($a['pts']) : 0;
                $pts_b = isset($b['pts']) ? intval($b['pts']) : 0;
                if ($pts_a == $pts_b) {
                    $gd_a = isset($a['gd']) ? intval($a['gd']) : 0;
                    $gd_b = isset($b['gd']) ? intval($b['gd']) : 0;
                    return $gd_b - $gd_a;
                }
                return $pts_b - $pts_a;
            });
            $sorted_team_ids = array_keys($team_ids_meta);

            // Limit to power of 2
            $count = count($sorted_team_ids);
            if ($count >= 16)
                $limit = 16;
            elseif ($count >= 8)
                $limit = 8;
            elseif ($count >= 4)
                $limit = 4;
            else
                $limit = 2;

            $playoff_teams = array_slice($sorted_team_ids, 0, $limit);
            $rounds = FGSP_Algorithms::generate_playoff($playoff_teams);
        } elseif ($algorithm === 'knockout') {
            $rounds = FGSP_Algorithms::generate_knockout($team_ids);
        } else {
            // Random Matchmaking
            shuffle($team_ids);
            $matches = array();
            for ($i = 0; $i < count($team_ids); $i += 2) {
                if (isset($team_ids[$i + 1])) {
                    $matches[] = array($team_ids[$i], $team_ids[$i + 1]);
                }
            }
            $rounds[] = $matches;
        }

        // Preparation for date assignment
        $times_list = array_map('trim', explode(',', $params['rotate_times']));
        if (empty($times_list) || empty($params['rotate_times'])) {
            $times_list = array($params['start_time']);
        }

        $created_count = 0;
        $created_event_ids = array();
        $current_timestamp = strtotime($params['start_date'] . ' ' . $times_list[0]);

        // Create events for each round
        foreach ($rounds as $r_idx => $matches) {
            $round_num = $r_idx + 1;

            // Advance date (if not first round)
            if ($r_idx > 0) {
                $current_timestamp = strtotime(date('Y-m-d H:i:s', $current_timestamp) . " + {$params['interval']} days");
            }

            // Ensure valid date
            $is_valid_date = false;
            while (!$is_valid_date) {
                $date_to_check = date('Y-m-d', $current_timestamp);
                $day_of_week = date('w', $current_timestamp);

                if (!empty($params['exclude_dates']) && in_array($date_to_check, $params['exclude_dates'])) {
                    $current_timestamp = strtotime(date('Y-m-d H:i:s', $current_timestamp) . " + 1 day");
                    continue;
                }

                if (!empty($params['allowed_days']) && !in_array($day_of_week, $params['allowed_days'])) {
                    $current_timestamp = strtotime(date('Y-m-d H:i:s', $current_timestamp) . " + 1 day");
                    continue;
                }

                $is_valid_date = true;
            }

            $current_date_base = date('Y-m-d', $current_timestamp);

            foreach ($matches as $m_idx => $match) {
                $home_id = $match[0];
                $away_id = $match[1];

                $this_match_time = $times_list[$m_idx % count($times_list)];
                $event_datetime = $current_date_base . ' ' . $this_match_time;

                $event_id = wp_insert_post(array(
                    'post_title' => get_the_title($home_id) . ' vs ' . get_the_title($away_id) . ' (' . $tournament_title . ')',
                    'post_type' => 'sp_event',
                    'post_status' => 'future',
                    'post_date' => $event_datetime,
                ));

                if ($event_id) {
                    $this->update_event_meta($event_id, $home_id, $away_id, $tournament_id, $table_id, $round_num, $players_by_team);

                    if ($params['assign_venue']) {
                        $this->assign_event_venue($event_id, $home_id);
                    }

                    if ($league_id)
                        wp_set_object_terms($event_id, intval($league_id), 'sp_league');
                    if ($season_id)
                        wp_set_object_terms($event_id, intval($season_id), 'sp_season');

                    $created_count++;
                    $created_event_ids[] = $event_id;
                }
            }
        }

        // Logging
        if ($created_count > 0) {
            $this->log_generation($table_id, $tournament_id, $algorithm, $created_count, $created_event_ids);
        }

        return array('count' => $created_count, 'event_ids' => $created_event_ids);
    }

    /**
     * Create a new group (table) and assign teams.
     */
    public function create_group($tournament_id, $group_name, $team_ids)
    {
        $tournament_title = get_the_title($tournament_id);

        // Ensure we don't double suffix if name already contains it
        if (strpos($group_name, "($tournament_title)") === false) {
            $group_name .= ' (' . $tournament_title . ')';
        }

        $table_id = wp_insert_post(array(
            'post_title' => $group_name,
            'post_type' => 'sp_table',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ));

        if (!$table_id || is_wp_error($table_id)) {
            return new WP_Error('create_failed', 'Failed to create group');
        }

        update_post_meta($table_id, 'sp_mode', 'team');
        update_post_meta($table_id, 'sp_format', 'standings');
        update_post_meta($table_id, 'sp_select', 'manual');
        update_post_meta($table_id, 'sp_orderby', 'default');
        update_post_meta($table_id, 'sp_order', 'ASC');
        update_post_meta($table_id, 'sp_columns', array('p', 'w', 'd', 'l', 'f', 'a', 'gd', 'pts'));
        update_post_meta($table_id, 'sp_tournament', $tournament_id);

        $teams_meta = array();
        delete_post_meta($table_id, 'sp_team');
        add_post_meta($table_id, 'sp_team', '0');

        foreach ($team_ids as $tid) {
            $teams_meta[$tid] = array('name' => '', 'p' => '', 'w' => '', 'd' => '', 'l' => '', 'f' => '', 'a' => '', 'gd' => '', 'pts' => '');
            add_post_meta($table_id, 'sp_team', $tid);
        }
        update_post_meta($table_id, 'sp_teams', $teams_meta);

        // Taxonomies
        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');
        if ($leagues && !is_wp_error($leagues))
            wp_set_object_terms($table_id, intval($leagues[0]->term_id), 'sp_league');
        if ($seasons && !is_wp_error($seasons))
            wp_set_object_terms($table_id, intval($seasons[0]->term_id), 'sp_season');

        return $table_id;
    }

    protected function update_event_meta($event_id, $home_id, $away_id, $tournament_id, $table_id, $round_num, $players_by_team)
    {
        update_post_meta($event_id, 'sp_team', $home_id);
        add_post_meta($event_id, 'sp_team', $away_id);
        update_post_meta($event_id, 'sp_tournament', $tournament_id);
        update_post_meta($event_id, 'sp_table', $table_id);
        update_post_meta($event_id, 'sp_day', $round_num);
        update_post_meta($event_id, 'sp_format', 'league');
        update_post_meta($event_id, 'sp_mode', 'team');
        update_post_meta($event_id, 'sp_status', 'ok');

        // Players
        add_post_meta($event_id, 'sp_player', 0); // Home separator
        if (isset($players_by_team[$home_id])) {
            foreach ($players_by_team[$home_id] as $player_id) {
                add_post_meta($event_id, 'sp_player', $player_id);
            }
        }
        add_post_meta($event_id, 'sp_player', 0); // Away separator
        if (isset($players_by_team[$away_id])) {
            foreach ($players_by_team[$away_id] as $player_id) {
                add_post_meta($event_id, 'sp_player', $player_id);
            }
        }
    }

    protected function assign_event_venue($event_id, $team_id)
    {
        $venue_ids = get_the_terms($team_id, 'sp_venue');
        if ($venue_ids && !is_wp_error($venue_ids)) {
            $venue_id = $venue_ids[0]->term_id;
            wp_set_object_terms($event_id, intval($venue_id), 'sp_venue');
            update_post_meta($event_id, 'sp_venue', $venue_id);
        }
    }

    public function generate_playoffs($params)
    {
        $tournament_id = intval($params['tournament_id']);
        $format = intval($params['format']);
        $legs = intval($params['legs']);

        $tournament_title = get_the_title($tournament_id);

        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');
        $league_id = ($leagues && !is_wp_error($leagues)) ? $leagues[0]->term_id : 0;
        $season_id = ($seasons && !is_wp_error($seasons)) ? $seasons[0]->term_id : 0;

        $created_count = 0;
        $bracket_event_ids = array();

        // Define rounds based on format
        $rounds_definitions = array();
        if ($format >= 16)
            $rounds_definitions[] = array('title' => 'Octavos de Final', 'matches' => 8);
        if ($format >= 8)
            $rounds_definitions[] = array('title' => 'Cuartos de Final', 'matches' => 4);
        if ($format >= 4)
            $rounds_definitions[] = array('title' => 'Semifinal', 'matches' => 2);

        $rounds_definitions[] = array('title' => 'Gran Final', 'matches' => 1);

        foreach ($rounds_definitions as $round) {
            for ($i = 1; $i <= $round['matches']; $i++) {
                $titles = array();
                $suffix = ($round['matches'] > 1) ? " " . $i : "";

                if ($legs == 2 && $round['title'] !== 'Gran Final') {
                    $titles[] = $round['title'] . $suffix . " (Ida)";
                    $titles[] = $round['title'] . $suffix . " (Vuelta)";
                } else {
                    $titles[] = $round['title'] . $suffix;
                }

                $last_event_id = 0;
                foreach ($titles as $title) {
                    $event_id = wp_insert_post(array(
                        'post_title' => $title . ' (' . $tournament_title . ')',
                        'post_type' => 'sp_event',
                        'post_status' => 'future'
                    ));

                    if ($event_id) {
                        update_post_meta($event_id, 'sp_tournament', $tournament_id);
                        update_post_meta($event_id, 'sp_format', 'league');
                        update_post_meta($event_id, 'sp_mode', 'team');

                        if ($league_id)
                            wp_set_object_terms($event_id, intval($league_id), 'sp_league');
                        if ($season_id)
                            wp_set_object_terms($event_id, intval($season_id), 'sp_season');

                        $created_count++;
                        $last_event_id = $event_id;
                    }
                }
                // We add the last event of the pair (the "Vuelta" or the single leg) to the bracket slot
                $bracket_event_ids[] = $last_event_id;
            }
        }

        // Integration with SportsPress Integrated Bracket
        $tournament_format = get_post_meta($tournament_id, 'sp_format', true);
        if ($tournament_format === 'bracket') {
            $num_rounds = count($rounds_definitions);
            update_post_meta($tournament_id, 'sp_rounds', $num_rounds);

            // Re-order labels
            $labels = array();
            foreach ($rounds_definitions as $rd) {
                $labels[] = $rd['title'];
            }
            update_post_meta($tournament_id, 'sp_labels', $labels);

            // Construct sp_events meta (serialized array of slots)
            $sp_events_array = array();
            foreach ($bracket_event_ids as $idx => $eid) {
                $sp_events_array[$idx] = array(
                    'teams' => array('0', '0'),
                    'id' => (string) $eid,
                    'hidden' => '0',
                    'date' => ''
                );
            }
            update_post_meta($tournament_id, 'sp_events', $sp_events_array);

            // Update individual sp_event meta keys (SportsPress uses multiple keys with the same name)
            delete_post_meta($tournament_id, 'sp_event');
            foreach ($bracket_event_ids as $eid) {
                add_post_meta($tournament_id, 'sp_event', $eid);
            }
        }

        return array('count' => $created_count, 'message' => sprintf('Se han generado %d eventos para las eliminatorias del torneo y se ha configurado el Bracket.', $created_count));
    }

    /**
     * Create or retrieve a SportsPress calendar for the tournament.
     */
    public function create_tournament_calendar($tournament_id, $format = 'blocks')
    {
        $tournament_title = get_the_title($tournament_id);
        $calendar_title = 'Calendario - ' . $tournament_title;

        // Check if exists
        $existing = get_page_by_title($calendar_title, OBJECT, 'sp_calendar');
        if ($existing) {
            return $existing->ID;
        }

        // Create new
        $calendar_id = wp_insert_post(array(
            'post_title'  => $calendar_title,
            'post_type'   => 'sp_calendar',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ));

        if (!$calendar_id || is_wp_error($calendar_id)) {
            return false;
        }

        // Config basic meta
        update_post_meta($calendar_id, 'sp_format', $format);
        update_post_meta($calendar_id, 'sp_status', 'any');
        update_post_meta($calendar_id, 'sp_event_format', 'all');
        update_post_meta($calendar_id, 'sp_orderby', 'date');
        update_post_meta($calendar_id, 'sp_order', 'ASC');

        // Default columns
        $columns = array('event', 'time', 'league', 'season', 'venue', 'day');
        update_post_meta($calendar_id, 'sp_columns', $columns);

        // Link with tournament taxonomies
        $leagues = get_the_terms($tournament_id, 'sp_league');
        $seasons = get_the_terms($tournament_id, 'sp_season');

        if ($leagues && !is_wp_error($leagues)) {
            wp_set_object_terms($calendar_id, intval($leagues[0]->term_id), 'sp_league');
        }
        if ($seasons && !is_wp_error($seasons)) {
            wp_set_object_terms($calendar_id, intval($seasons[0]->term_id), 'sp_season');
        }

        // Specifically link the tournament ID in meta if wanted (SportsPress often uses it)
        update_post_meta($calendar_id, 'sp_tournament', $tournament_id);

        return $calendar_id;
    }

    protected function log_generation($table_id, $tournament_id, $algorithm, $count, $ids)
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'fgsp_logs',
            array(
                'table_id' => $table_id,
                'tournament_id' => $tournament_id,
                'algorithm' => $algorithm,
                'event_count' => $count,
                'event_ids' => json_encode($ids),
                'generated_at' => current_time('mysql')
            )
        );
    }
}
