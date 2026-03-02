<?php
/**
 * Handles promotion logic from group stages to knockout rounds.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Promotion
{
    /**
     * Get sorted standings for a group (sp_table).
     * 
     * @param int $table_id
     * @return array
     */
    public function get_standings($table_id)
    {
        if (class_exists('SP_Table')) {
            $table = new SP_Table($table_id);
            $data = $table->data();

            $standings = array();
            $pos = 1;

            // SportsPress data() usually returns teams sorted if configured
            foreach ($data as $team_id => $stats) {
                if ($team_id == 0)
                    continue;

                $standings[] = array(
                    'pos' => $pos++,
                    'id' => $team_id,
                    'name' => get_the_title($team_id),
                    'pts' => isset($stats['pts']) ? $stats['pts'] : 0,
                    'p' => isset($stats['p']) ? $stats['p'] : 0,
                    'w' => isset($stats['w']) ? $stats['w'] : 0,
                    'd' => isset($stats['d']) ? $stats['d'] : 0,
                    'l' => isset($stats['l']) ? $stats['l'] : 0,
                    'f' => isset($stats['f']) ? $stats['f'] : 0,
                    'a' => isset($stats['a']) ? $stats['a'] : 0,
                    'gd' => isset($stats['gd']) ? $stats['gd'] : 0,
                );
            }
            return $standings;
        }

        // Fallback if SP_Table is not available or hasn't calculated data
        $team_ids = get_post_meta($table_id, 'sp_teams', true);
        $team_ids = is_array($team_ids) ? array_keys($team_ids) : array();

        $standings = array();
        $pos = 1;
        foreach ($team_ids as $team_id) {
            $standings[] = array(
                'pos' => $pos++,
                'id' => $team_id,
                'name' => get_the_title($team_id),
                'pts' => 0,
                'p' => 0
            );
        }
        return $standings;
    }

    /**
     * Promote teams to specific events (knockout bracket).
     * 
     * @param array $promotions Array of [event_id => [home_team_id, away_team_id]]
     * @return int Number of events updated.
     */
    public function promote_to_events($promotions)
    {
        $count = 0;
        foreach ($promotions as $event_id => $teams) {
            $event_id = intval($event_id);

            // 1. Update the Event post metadata
            delete_post_meta($event_id, 'sp_team');
            $home_team = isset($teams['home']) ? intval($teams['home']) : 0;
            $away_team = isset($teams['away']) ? intval($teams['away']) : 0;

            if ($home_team) {
                add_post_meta($event_id, 'sp_team', $home_team);
            }
            if ($away_team) {
                add_post_meta($event_id, 'sp_team', $away_team);
            }

            // 2. Sync with SportsPress Integrated Bracket (Tournament Metadata)
            $tournament_id = get_post_meta($event_id, 'sp_tournament', true);
            if ($tournament_id) {
                $bracket_data = get_post_meta($tournament_id, 'sp_events', true);
                if (is_array($bracket_data)) {
                    $synced = false;
                    foreach ($bracket_data as &$slot) {
                        // In SP, slot['id'] is stored as string
                        if (isset($slot['id']) && (int) $slot['id'] === $event_id) {
                            $slot['teams'] = array(
                                $home_team ? (string) $home_team : '0',
                                $away_team ? (string) $away_team : '0'
                            );
                            $synced = true;
                        }
                    }
                    if ($synced) {
                        update_post_meta($tournament_id, 'sp_events', $bracket_data);
                    }
                }
            }

            $count++;
        }
        return $count;
    }
}
