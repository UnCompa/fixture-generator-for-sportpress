<?php
/**
 * Template for the Fixture Groups Manager meta box.
 * 
 * @var array $teams
 * @var array $tables
 * @var int $post_id
 */
?>
<div class="fgsp-tournament-groups-ui">
    <div id="fgsp-new-group-form"
        style="background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e2e4e7; margin-bottom:20px;">
        <h4 style="margin-top:0;">
            <?php _e('Create New Group', 'fixture-generator-for-sportpress'); ?>
        </h4>
        <div class="fgsp-field" style="margin-bottom:10px;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">
                <?php _e('Group Name', 'fixture-generator-for-sportpress'); ?>
            </label>
            <input type="text" id="fgsp-new-group-name" placeholder="Ex: Grupo A, Fase 1..." style="width:100%;">
        </div>
        <div class="fgsp-field">
            <label style="display:block; margin-bottom:5px; font-weight:600;">
                <?php _e('Select Teams', 'fixture-generator-for-sportpress'); ?>
            </label>
            <div class="fgsp-team-selector-grid"
                style="max-height:150px; overflow-y:auto; border:1px solid #ddd; padding:10px; background:#fff; border-radius:4px; display:grid; grid-template-columns: repeat(2, 1fr); gap: 5px;">
                <?php foreach ($teams as $team): ?>
                    <label style="font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                        <input type="checkbox" name="fgsp_teams[]" value="<?php echo $team->ID; ?>">
                        <?php echo esc_html($team->post_title); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <input type="hidden" id="fgsp-tournament-id" value="<?php echo $post_id; ?>">
        <button type="button" id="fgsp-create-group-btn" class="button button-primary"
            style="margin-top:15px; width:100%;">
            <span class="dashicons dashicons-plus-alt" style="vertical-align:middle; line-height:1;"></span>
            <?php _e('Create Group & Assign', 'fixture-generator-for-sportpress'); ?>
        </button>
    </div>

    <div id="fgsp-existing-groups">
        <h4 style="border-bottom:1px solid #eee; padding-bottom:5px;">
            <?php _e('Associated Groups', 'fixture-generator-for-sportpress'); ?>
        </h4>
        <?php if (empty($tables)): ?>
            <p id="fgsp-no-groups-msg" style="font-style:italic; color:#777;">
                <?php _e('No groups created for this tournament yet.', 'fixture-generator-for-sportpress'); ?>
            </p>
        <?php endif; ?>
        <div class="fgsp-groups-list">
            <?php foreach ($tables as $table):
                $table_teams = get_post_meta($table->ID, 'sp_teams', true);
                $team_count = is_array($table_teams) ? count(array_filter(array_keys($table_teams))) : 0;
                // Retrieve tournament id for data attributes (to prefill modal later)
                $tournament_of_table = get_post_meta($table->ID, 'sp_tournament', true);
                ?>
                <div class="fgsp-group-item"
                    style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #eee;"
                    data-group-id="<?php echo $table->ID; ?>"
                    data-tournament-id="<?php echo esc_attr($tournament_of_table); ?>">
                    <span><strong>
                            <?php echo esc_html($table->post_title); ?>
                        </strong> (
                        <?php echo $team_count; ?>
                        teams)
                    </span>
                    <a href="<?php echo get_edit_post_link($table->ID); ?>" class="button button-small" target="_blank">
                        <?php _e('Edit', 'fixture-generator-for-sportpress'); ?>
                    </a>
                    <button type="button" class="button button-small fgsp-generate-fixtures"
                        data-group-id="<?php echo $table->ID; ?>"
                        data-tournament-id="<?php echo esc_attr($tournament_of_table); ?>"
                        title="Generate Fixtures for this group">
                        <span class="dashicons dashicons-media-spreadsheet"
                            style="vertical-align:middle; margin-right:2px;"></span>
                        <?php _e('Generate Fixtures', 'fixture-generator-for-sportpress'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// Re-use the Quick Fixture Generator modal markup on the Groups admin page
include plugin_dir_path(dirname(__FILE__, 1)) . 'templates/meta-box-quick.php';
?>