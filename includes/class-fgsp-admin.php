<?php
/**
 * Admin logic for menu and assets.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Admin
{
    /**
     * Add admin menus.
     */
    public function add_menu()
    {
        add_submenu_page(
            'edit.php?post_type=sp_event',
            __('Fixture Generator', 'fixture-generator-for-sportpress'),
            __('Fixture Generator', 'fixture-generator-for-sportpress'),
            'edit_sp_events',
            'fgsp-generator',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'edit.php?post_type=sp_event',
            __('Crear Liga', 'fixture-generator-for-sportpress'),
            __('Crear Liga', 'fixture-generator-for-sportpress'),
            'edit_sp_events',
            'fgsp-create-league',
            array($this, 'render_create_league_page')
        );

        add_filter('post_row_actions', array($this, 'add_league_row_actions'), 10, 2);
    }

    /**
     * Add "Crear Liga" action to sp_table list.
     */
    public function add_league_row_actions($actions, $post)
    {
        if ($post->post_type !== 'sp_table') {
            return $actions;
        }

        $actions['create_league_wizard'] = sprintf(
            '<a href="%s" style="color: #68b231; font-weight: bold;">%s</a>',
            admin_url('edit.php?post_type=sp_event&page=fgsp-create-league'),
            __('Crear Estructura Completa', 'fixture-generator-for-sportpress')
        );

        return $actions;
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets($hook)
    {
        global $post;
        $is_generator_page = strpos($hook, 'fgsp-generator') !== false;
        $is_create_league_page = strpos($hook, 'fgsp-create-league') !== false;
        $is_editor = ($hook === 'post.php' || $hook === 'post-new.php') && isset($post);
        $is_supported_post_type = $is_editor && in_array($post->post_type, array('sp_table', 'sp_tournament'));

        if (!$is_generator_page && !$is_create_league_page && !$is_supported_post_type) {
            return;
        }

        wp_enqueue_style('fgsp-admin-css', plugins_url('assets/css/admin.css', dirname(__FILE__, 1)), array(), '1.0.0');
        wp_enqueue_script('fgsp-admin-js', plugins_url('assets/js/admin.js', dirname(__FILE__, 1)), array('jquery'), '1.0.0', true);

        wp_localize_script('fgsp-admin-js', 'fgspData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fgsp_nonce'),
        ));
    }

    /**
     * Render the Create League page.
     */
    public function render_create_league_page()
    {
        $leagues = get_terms(array(
            'taxonomy' => 'sp_league',
            'hide_empty' => false,
        ));

        $seasons = get_terms(array(
            'taxonomy' => 'sp_season',
            'hide_empty' => false,
        ));

        $teams = get_posts(array(
            'post_type' => 'sp_team',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ));

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/create-league.php';
    }

    /**
     * Render the main admin page.
     */
    public function render_admin_page()
    {
        $tournaments = get_posts(array(
            'post_type' => 'sp_tournament',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'suppress_filters' => false,
        ));

        $preselected_table = isset($_GET['sp_table']) ? intval($_GET['sp_table']) : 0;
        $preselected_tournament = 0;

        if ($preselected_table) {
            $preselected_tournament = get_post_meta($preselected_table, 'sp_tournament', true);
        }

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/admin-page.php';
    }
}
