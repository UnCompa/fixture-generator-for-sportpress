<?php
/**
 * Admin page template for Fixture Generator
 */
?>
<div class="wrap fgsp-admin-wrap">
    <div class="fgsp-header">
        <h1><span class="dashicons dashicons-calendar-alt"></span>
            <?php _e('Fixture Generator for SportsPress', 'fixture-generator-for-sportpress'); ?>
        </h1>
        <p class="description">
            <?php _e('Generate professional schedules for your tournaments with ease.', 'fixture-generator-for-sportpress'); ?>
        </p>
    </div>

    <div class="fgsp-main-card">
        <div class="fgsp-card-header">
            <h2>
                <?php _e('1. Select Tournament', 'fixture-generator-for-sportpress'); ?>
            </h2>
        </div>
        <div class="fgsp-card-body">
            <div class="fgsp-form-group">
                <label for="fgsp-tournament-selector">
                    <?php _e('Tournament', 'fixture-generator-for-sportpress'); ?>
                </label>
                <select id="fgsp-tournament-selector" class="fgsp-select-2"
                    data-preselected-tournament="<?php echo esc_attr($preselected_tournament); ?>"
                    data-preselected-table="<?php echo esc_attr($preselected_table); ?>">
                    <option value="">
                        <?php _e('-- Choose a Tournament --', 'fixture-generator-for-sportpress'); ?>
                    </option>
                    <?php foreach ($tournaments as $tournament): ?>
                        <option value="<?php echo esc_attr($tournament->ID); ?>" <?php selected($preselected_tournament, $tournament->ID); ?>>
                            <?php echo esc_html($tournament->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div id="fgsp-groups-container" class="fgsp-groups-grid" style="display: none;">
        <!-- Groups will be loaded here via AJAX -->
    </div>

    <!-- Create Group UI for Main Admin Page -->
    <div id="fgsp-create-group-container" class="fgsp-main-card"
        style="display: none; border-top: 4px solid var(--fgsp-secondary);">
        <div class="fgsp-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin:0;"><?php _e('Add New Group', 'fixture-generator-for-sportpress'); ?></h2>
            <button type="button" class="fgsp-close-creation-form button-link"
                style="color:#e74c3c; text-decoration:none;"><span class="dashicons dashicons-no-alt"></span>
                Cancel</button>
        </div>
        <div class="fgsp-card-body">
            <div class="fgsp-form-group">
                <label><?php _e('Group Name', 'fixture-generator-for-sportpress'); ?></label>
                <input type="text" id="fgsp-main-new-group-name" placeholder="Ex: Grupo A, Fase 1..."
                    class="regular-text" style="width:100%; max-width:400px; display:block;">
            </div>
            <div class="fgsp-form-group">
                <label><?php _e('Select Teams (Filtered by League)', 'fixture-generator-for-sportpress'); ?></label>
                <div id="fgsp-main-team-selector" class="fgsp-team-selector-grid"
                    style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:15px; background:#f9f9f9; border-radius:8px; display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                    <!-- Teams loaded here -->
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="button" id="fgsp-main-create-group-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Create Group & Assign', 'fixture-generator-for-sportpress'); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="fgsp-global-actions" class="fgsp-footer-actions" style="display: none;">
        <div style="display: flex; gap: 15px; width: 100%; justify-content: center; margin-bottom: 20px;">
            <button id="fgsp-show-create-form" class="button button-secondary button-large">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Create New Group', 'fixture-generator-for-sportpress'); ?>
            </button>
            <button id="fgsp-generate-all" class="button button-primary button-large fgsp-btn-premium"
                style="margin: 0;">
                <span class="dashicons dashicons-randomize"></span>
                <?php _e('Generate All Fixtures', 'fixture-generator-for-sportpress'); ?>
            </button>
            <button id="fgsp-open-playoff-modal" class="button button-secondary button-large"
                style="background: #2c3e50; border-color: #2c3e50; color: white;">
                <span class="dashicons dashicons-networking"></span>
                <?php _e('Generate Playoff Bracket', 'fixture-generator-for-sportpress'); ?>
            </button>
            <button id="fgsp-create-calendar" class="button button-secondary button-large"
                style="background: #27ae60; border-color: #27ae60; color: white;">
                <span class="dashicons dashicons-calendar"></span>
                <?php _e('Generate Calendar', 'fixture-generator-for-sportpress'); ?>
            </button>
        </div>

        <div id="fgsp-loader" class="fgsp-overlay" style="display: none;">
            <div class="fgsp-spinner"></div>
        </div>
    </div>

    <!-- Event Viewer Modal -->
    <div id="fgsp-event-viewer-modal" class="fgsp-modal" style="display:none;">
        <div class="fgsp-modal-content" style="max-width: 750px;">
            <div class="fgsp-modal-header" style="background: var(--fgsp-secondary);">
                <h3 style="color:white; margin:0;"><span class="dashicons dashicons-soccer"></span>
                    <?php _e('Generated Events', 'fixture-generator-for-sportpress'); ?></h3>
                <span class="fgsp-close-event-modal fgsp-close-modal">&times;</span>
            </div>
            <div class="fgsp-modal-body" style="max-height: 500px; overflow-y: auto;">
                <div id="fgsp-event-list-container">
                    <p class="text-center"><?php _e('Loading events...', 'fixture-generator-for-sportpress'); ?></p>
                </div>
            </div>
            <div class="fgsp-modal-footer">
                <button type="button" id="fgsp-save-results-btn" class="button button-primary"
                    style="display:none;"><?php _e('Save Results', 'fixture-generator-for-sportpress'); ?></button>
                <button type="button"
                    class="button fgsp-close-event-modal"><?php _e('Close', 'fixture-generator-for-sportpress'); ?></button>
            </div>
        </div>
    </div>

    <!-- Promotion Modal -->
    <div id="fgsp-promotion-modal" class="fgsp-modal" style="display:none;">
        <div class="fgsp-modal-content" style="max-width: 800px;">
            <div class="fgsp-modal-header">
                <h3><?php _e('Promote Teams to Playoffs', 'fixture-generator-for-sportpress'); ?></h3>
                <span class="fgsp-close-promotion-modal">&times;</span>
            </div>
            <div class="fgsp-modal-body">
                <div id="fgsp-promotion-content">
                    <p><?php _e('Select a group to see standings and promote teams.', 'fixture-generator-for-sportpress'); ?>
                    </p>
                </div>
            </div>
            <div class="fgsp-modal-footer">
                <button type="button" id="fgsp-submit-promotion-btn" class="button button-primary"
                    style="display:none;"><?php _e('Confirm Promotion', 'fixture-generator-for-sportpress'); ?></button>
                <button type="button"
                    class="button fgsp-close-promotion-modal"><?php _e('Cancel', 'fixture-generator-for-sportpress'); ?></button>
            </div>
        </div>
    </div>

    <!-- Playoff Generator Modal -->
    <div id="fgsp-playoff-modal" class="fgsp-modal" style="display:none;">
        <div class="fgsp-modal-content" style="max-width: 500px;">
            <div class="fgsp-modal-header" style="background: #2c3e50;">
                <h3 style="color:white; margin:0;"><span class="dashicons dashicons-networking"></span>
                    <?php _e('Build Playoff Bracket', 'fixture-generator-for-sportpress'); ?></h3>
                <span class="fgsp-close-playoff-modal fgsp-close-modal">&times;</span>
            </div>
            <div class="fgsp-modal-body">
                <div class="fgsp-field" style="margin-bottom: 20px;">
                    <label><?php _e('Bracket Format', 'fixture-generator-for-sportpress'); ?></label>
                    <select id="fgsp-playoff-format" style="width:100%;">
                        <option value="4">
                            <?php _e('Semi-finals & Final (4 teams)', 'fixture-generator-for-sportpress'); ?></option>
                        <option value="8">
                            <?php _e('Quarter-finals to Final (8 teams)', 'fixture-generator-for-sportpress'); ?>
                        </option>
                        <option value="16">
                            <?php _e('Round of 16 to Final (16 teams)', 'fixture-generator-for-sportpress'); ?></option>
                    </select>
                </div>
                <div class="fgsp-field" style="margin-bottom: 20px;">
                    <label><?php _e('Match Format', 'fixture-generator-for-sportpress'); ?></label>
                    <select id="fgsp-playoff-legs" style="width:100%;">
                        <option value="1">
                            <?php _e('Single Leg (Un solo partido)', 'fixture-generator-for-sportpress'); ?></option>
                        <option value="2"><?php _e('Two Legs (Ida y Vuelta)', 'fixture-generator-for-sportpress'); ?>
                        </option>
                    </select>
                </div>
                <div class="fgsp-alert fgsp-alert-warning"
                    style="background: #fff3cd; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba;">
                    <p style="margin:0; font-size: 13px; color: #856404;">
                        <span class="dashicons dashicons-info" style="font-size: 18px; vertical-align: middle;"></span>
                        <?php _e('This will create the necessary events in your tournament and can be visualized in SportsPress brackets.', 'fixture-generator-for-sportpress'); ?>
                    </p>
                </div>
            </div>
            <div class="fgsp-modal-footer">
                <button type="button" id="fgsp-generate-playoffs-btn" class="button button-primary">
                    <?php _e('Create Events', 'fixture-generator-for-sportpress'); ?>
                </button>
                <button type="button"
                    class="button fgsp-close-playoff-modal"><?php _e('Cancel', 'fixture-generator-for-sportpress'); ?></button>
            </div>
        </div>
    </div>
</div>