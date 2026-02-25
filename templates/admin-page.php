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

    <div id="fgsp-global-actions" class="fgsp-footer-actions" style="display: none;">
        <button id="fgsp-generate-all" class="button button-primary button-large fgsp-btn-premium">
            <span class="dashicons dashicons-randomize"></span>
            <?php _e('Generate All Fixtures', 'fixture-generator-for-sportpress'); ?>
        </button>
    </div>

    <div id="fgsp-loader" class="fgsp-overlay" style="display: none;">
        <div class="fgsp-spinner"></div>
    </div>
</div>