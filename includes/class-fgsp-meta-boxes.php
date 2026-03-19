<?php
/**
 * Meta box registration and rendering logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Meta_Boxes
{
    /**
     * Register meta boxes.
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'fgsp-individual-generator',
            __('Quick Fixture Generator', 'fixture-generator-for-sportpress'),
            array($this, 'render_quick_generator'),
            'sp_table',
            'side',
            'high'
        );

        add_meta_box(
            'fgsp-generation-history',
            __('Fixture Generation History', 'fixture-generator-for-sportpress'),
            array($this, 'render_history'),
            array('sp_table', 'sp_tournament'),
            'normal',
            'low'
        );

        add_meta_box(
            'fgsp-tournament-groups-manager',
            __('Fixture Groups Manager', 'fixture-generator-for-sportpress'),
            array($this, 'render_groups_manager'),
            'sp_tournament',
            'normal',
            'high'
        );

        add_meta_box(
            'fgsp-associated-events',
            __('Associated Events', 'fixture-generator-for-sportpress'),
            array($this, 'render_associated_events'),
            array('sp_tournament', 'sp_table'),
            'normal',
            'default'
        );
    }

    /**
     * Render the Quick Generator meta box.
     */
    public function render_quick_generator($post)
    {
        $post_id = $post->ID;
        $team_ids = get_post_meta($post_id, 'sp_teams', true);
        $count = is_array($team_ids) ? count(array_filter(array_keys($team_ids))) : 0;
        $tournament_id = get_post_meta($post_id, 'sp_tournament', true);

        $is_played = FGSP_Helpers::is_group_played($post_id);
        $has_fixtures = !empty(FGSP_Helpers::get_existing_events($post_id));

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/meta-box-quick.php';
    }

    /**
     * Render the Groups Manager meta box.
     */
    public function render_groups_manager($post)
    {
        $post_id = $post->ID;
        $user = wp_get_current_user();
        $is_manager = in_array('tournament_manager', (array) $user->roles) && !in_array('administrator', (array) $user->roles);

        // Get tournament leagues
        $leagues = get_the_terms($post_id, 'sp_league');
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

        if ($is_manager) {
            $args['suppress_filters'] = false;
        }

        if (!empty($league_ids)) {
            $args['tax_query'] = array(
                array('taxonomy' => 'sp_league', 'field' => 'term_id', 'terms' => $league_ids),
            );
        }

        $teams = get_posts($args);

        // Get associated groups (tables)
        $table_args = array(
            'post_type' => 'sp_table',
            'posts_per_page' => -1,
            'meta_query' => array(
                array('key' => 'sp_tournament', 'value' => $post_id)
            )
        );

        if ($is_manager) {
            $table_args['suppress_filters'] = false;
        }

        $tables = get_posts($table_args);

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/meta-box-groups.php';
    }

    /**
     * Render the Associated Events meta box.
     */
    public function render_associated_events($post)
    {
        $user = wp_get_current_user();
        $is_manager = in_array('tournament_manager', (array) $user->roles) && !in_array('administrator', (array) $user->roles);
        $meta_key = ($post->post_type === 'sp_table') ? 'sp_table' : 'sp_tournament';

        $event_args = array(
            'post_type' => 'sp_event',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => $meta_key,
                    'value' => $post->ID
                )
            ),
            'orderby' => 'post_date',
            'order' => 'DESC'
        );

        if ($is_manager) {
            $event_args['suppress_filters'] = false;
        }

        $events = get_posts($event_args);
        
        // Final sanity check: if no events found but we restricted by author,
        // it might be because the events were created by someone else (unlikely for a manager's tourney)

        if (empty($events)) {
            echo '<p>' . __('No events found.', 'fixture-generator-for-sportpress') . '</p>';
            return;
        }

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/meta-box-events.php';
    }

    /**
     * Render the Generation History meta box.
     */
    public function render_history($post)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fgsp_logs';

        // Fail-safe for table existence (legacy check)
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            FGSP_Activator::activate();
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

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/meta-box-history.php';
    }
}
