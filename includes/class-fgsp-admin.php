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
            'manage_options',
            'fgsp-generator',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets($hook)
    {
        global $post;
        $is_generator_page = strpos($hook, 'fgsp-generator') !== false;
        $is_editor = ($hook === 'post.php' || $hook === 'post-new.php') && isset($post);
        $is_supported_post_type = $is_editor && in_array($post->post_type, array('sp_table', 'sp_tournament'));

        if (!$is_generator_page && !$is_supported_post_type) {
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
     * Render the main admin page.
     */
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

        include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/admin-page.php';
    }
}
