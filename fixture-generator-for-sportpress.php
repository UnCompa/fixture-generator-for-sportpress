<?php
/**
 * Plugin Name: Fixture Generator for SportsPress
 * Description: Automatically generates fixtures for SportsPress tournaments and league tables.
 * Version: 1.1.0
 * Author: UnCompa
 * Text Domain: fixture-generator-for-sportpress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class FGSP_Plugin
{
    /** @var FGSP_Plugin */
    private static $instance = null;

    /** @var FGSP_Admin */
    public $admin;

    /** @var FGSP_Meta_Boxes */
    public $meta_boxes;

    /** @var FGSP_Ajax */
    public $ajax;

    /** @var FGSP_Generator */
    public $generator;

    /**
     * Singleton instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
    }

    /**
     * Load all required files.
     */
    private function load_dependencies()
    {
        $path = plugin_dir_path(__FILE__) . 'includes/';
        require_once $path . 'class-fgsp-activator.php';
        require_once $path . 'class-fgsp-helpers.php';
        require_once $path . 'class-fgsp-algorithms.php';
        require_once $path . 'class-fgsp-generator.php';
        require_once $path . 'class-fgsp-admin.php';
        require_once $path . 'class-fgsp-meta-boxes.php';
        require_once $path . 'class-fgsp-ajax.php';
    }

    /**
     * Initialize components.
     */
    private function init_components()
    {
        $this->generator = new FGSP_Generator();
        $this->admin = new FGSP_Admin();
        $this->meta_boxes = new FGSP_Meta_Boxes();
        $this->ajax = new FGSP_Ajax($this->generator);
    }

    /**
     * Register admin hooks.
     */
    private function define_admin_hooks()
    {
        add_action('admin_menu', array($this->admin, 'add_menu'), 99);
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_assets'));
        add_action('add_meta_boxes', array($this->meta_boxes, 'add_meta_boxes'));
    }

    /**
     * Register AJAX hooks.
     */
    private function define_ajax_hooks()
    {
        $handlers = array(
            'get_tournament_groups',
            'generate_fixtures',
            'create_tournament_group',
            'get_eligible_teams',
            'get_group_events',
            'save_quick_results',
            'get_group_standings',
            'submit_promotions',
        );

        foreach ($handlers as $handler) {
            add_action('wp_ajax_fgsp_' . $handler, array($this->ajax, $handler));
        }
    }
}

/**
 * Initialize the plugin.
 */
function fgsp_init()
{
    return FGSP_Plugin::get_instance();
}

// Global initialization
fgsp_init();

// Activation hook
register_activation_hook(__FILE__, array('FGSP_Activator', 'activate'));
