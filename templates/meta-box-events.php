<?php
/**
 * Template for the Associated Events meta box.
 * 
 * @var array $events
 */
?>
<div class="fgsp-events-list-wrapper" style="max-height: 400px; overflow-y: auto;">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 35%;">
                    <?php _e('Event', 'fixture-generator-for-sportpress'); ?>
                </th>
                <th>
                    <?php _e('Group / Table', 'fixture-generator-for-sportpress'); ?>
                </th>
                <th>
                    <?php _e('Date', 'fixture-generator-for-sportpress'); ?>
                </th>
                <th>
                    <?php _e('Status', 'fixture-generator-for-sportpress'); ?>
                </th>
                <th style="width:60px;">
                    <?php _e('Actions', 'fixture-generator-for-sportpress'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event):
                $table_id = get_post_meta($event->ID, 'sp_table', true);
                $table_title = $table_id ? get_the_title($table_id) : '-';
                ?>
                <tr>
                    <td><strong>
                            <?php echo esc_html($event->post_title); ?>
                        </strong></td>
                    <td>
                        <?php echo esc_html($table_title); ?>
                    </td>
                    <td>
                        <?php echo get_the_time(get_option('date_format') . ' ' . get_option('time_format'), $event); ?>
                    </td>
                    <td><span class="status-<?php echo esc_attr($event->post_status); ?>"
                            style="padding: 2px 6px; border-radius: 4px; background: #eee; font-size: 10px; text-transform: uppercase; font-weight: bold;">
                            <?php echo esc_html($event->post_status); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo get_edit_post_link($event->ID); ?>" class="button button-small" target="_blank">
                            <?php _e('Edit', 'fixture-generator-for-sportpress'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>