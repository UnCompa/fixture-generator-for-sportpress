<?php
/**
 * Plugin activator logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Activator
{
    /**
     * Activation logic.
     */
    public static function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fgsp_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            table_id bigint(20) NOT NULL,
            tournament_id bigint(20) NOT NULL,
            algorithm varchar(50) NOT NULL,
            event_count int(11) NOT NULL,
            event_ids longtext NOT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY table_id (table_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
