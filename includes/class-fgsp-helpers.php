<?php
/**
 * Utility helper methods for Fixture Generator for SportsPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Helpers
{
    /**
     * Check if a group (table) has any played matches.
     *
     * @param int $table_id The table ID.
     * @return bool True if matches were played, false otherwise.
     */
    public static function is_group_played($table_id)
    {
        $events = self::get_existing_events($table_id);
        if (empty($events)) {
            return false;
        }

        foreach ($events as $event_id) {
            $results = get_post_meta($event_id, 'sp_results', true);
            if (!empty($results) && is_array($results)) {
                foreach ($results as $tid => $stats) {
                    if ($tid == 0)
                        continue;
                    if (isset($stats['outcome']) && !empty($stats['outcome'])) {
                        return true;
                    }
                    if (isset($stats['goals']) && $stats['goals'] !== '') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get IDs of existing events associated with a table.
     *
     * @param int $table_id The table ID.
     * @return array Array of event IDs.
     */
    public static function get_existing_events($table_id)
    {
        return get_posts(array(
            'post_type' => 'sp_event',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'sp_table',
                    'value' => $table_id,
                ),
            ),
            'fields' => 'ids',
        ));
    }

    /**
     * Delete existing fixtures for a table.
     *
     * @param int $table_id The table ID.
     */
    public static function delete_existing_fixtures($table_id)
    {
        $events = self::get_existing_events($table_id);
        foreach ($events as $event_id) {
            wp_delete_post($event_id, true); // Bypass trash
        }
    }

    /**
     * Get IDs of all players assigned to a team.
     *
     * @param int $team_id The team ID.
     * @return array Array of player IDs.
     */
    public static function get_team_players_ids($team_id)
    {
        return get_posts(array(
            'post_type' => 'sp_player',
            'posts_per_page' => -1,
            'meta_key' => 'sp_team',
            'meta_value' => $team_id,
            'fields' => 'ids',
        ));
    }

    /**
     * Get current results for an event.
     * 
     * @param int $event_id
     * @return array
     */
    public static function get_event_results($event_id)
    {
        $results = get_post_meta($event_id, 'sp_results', true);
        return is_array($results) ? $results : array();
    }
}
