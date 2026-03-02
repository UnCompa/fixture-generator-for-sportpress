<?php
/**
 * Template for the Fixture Generation History meta box.
 * 
 * @var array $logs
 */
?>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Date', 'fixture-generator-for-sportpress'); ?></th>
            <th><?php _e('Algorithm', 'fixture-generator-for-sportpress'); ?></th>
            <th><?php _e('Events', 'fixture-generator-for-sportpress'); ?></th>
            <th><?php _e('Actions', 'fixture-generator-for-sportpress'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->generated_at)); ?>
                </td>
                <td><code
                        style="background:#eee; padding:2px 5px; border-radius:3px;"><?php echo esc_html($log->algorithm); ?></code>
                </td>
                <td><strong><?php echo intval($log->event_count); ?></strong></td>
                <td>
                    <?php
                    $event_ids = json_decode($log->event_ids, true);
                    if (is_array($event_ids)):
                        $event_links = array();
                        foreach (array_slice($event_ids, 0, 3) as $eid) {
                            $event_links[] = '<a href="' . get_edit_post_link($eid) . '" target="_blank">#' . $eid . '</a>';
                        }
                        echo implode(', ', $event_links);
                        if (count($event_ids) > 3)
                            echo '...';
                    endif;
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
