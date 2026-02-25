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
}

// Inicializar el plugin
function init()
{
    return FGSP_Plugin::get_instance();
}

// Ejecutar inicialización inmediatamente
init();
