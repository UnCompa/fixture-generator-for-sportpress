<?php
/**
 * Template for the Quick Fixture Generator meta box.
 * 
 * @var int $count
 * @var int $tournament_id
 * @var bool $is_played
 * @var bool $has_fixtures
 * @var int $post_id
 */
?>
<div class="fgsp-meta-box-content">
    <p><strong>
            <?php echo $count; ?>
        </strong>
        <?php _e('teams detected.', 'fixture-generator-for-sportpress'); ?>
    </p>

    <?php if ($is_played): ?>
        <div class="notice notice-error inline" style="margin-bottom: 10px; display: block;">
            <p>
                <?php _e('Bloqueado: Este grupo ya tiene resultados.', 'fixture-generator-for-sportpress'); ?>
            </p>
        </div>
    <?php elseif ($has_fixtures): ?>
        <div class="notice notice-info inline" style="margin-bottom: 10px; display: block;">
            <p>
                <?php _e('Ya hay un fixture. Se pedirá confirmación para regenerar.', 'fixture-generator-for-sportpress'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($count < 2): ?>
        <div class="notice notice-warning inline">
            <p>
                <?php _e('Need at least 2 teams.', 'fixture-generator-for-sportpress'); ?>
            </p>
        </div>
    <?php else: ?>
        <button type="button" id="fgsp-open-modal" class="button button-primary fgsp-btn-premium-small" <?php disabled($is_played); ?>>
            <span class="dashicons dashicons-randomize"></span>
            <?php _e('Generate Fixtures', 'fixture-generator-for-sportpress'); ?>
        </button>
        <p style="text-align: center; margin-top: 10px;">
            <a href="<?php echo admin_url('admin.php?page=fgsp-generator&sp_table=' . $post_id); ?>"
                style="font-size: 11px;">
                <?php _e('Use advanced generator', 'fixture-generator-for-sportpress'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

<!-- Hidden Modal for Quick Generation -->
<div id="fgsp-quick-modal" class="fgsp-modal" style="display:none;">
    <div class="fgsp-modal-content">
        <div class="fgsp-modal-header">
            <h3>
                <?php _e('Quick Fixture Generation', 'fixture-generator-for-sportpress'); ?>
            </h3>
            <span class="fgsp-close-modal">&times;</span>
        </div>
        <div class="fgsp-modal-body">
            <div class="fgsp-config-section">
                <input type="hidden" id="fgsp-modal-tournament-id" value="<?php echo esc_attr($tournament_id); ?>">
                <input type="hidden" id="fgsp-modal-table-id" value="<?php echo esc_attr($post_id); ?>">

                <div class="fgsp-field">
                    <label>
                        <?php _e('Algorithm', 'fixture-generator-for-sportpress'); ?>
                    </label>
                    <select id="fgsp-modal-algorithm" class="fgsp-algorithm-select" style="width: 100%;">
                        <option value="round-robin">
                            <?php _e('Round Robin (Ida y Vuelta)', 'fixture-generator-for-sportpress'); ?>
                        </option>
                        <option value="single-round-robin">
                            <?php _e('Round Robin (Solo Ida)', 'fixture-generator-for-sportpress'); ?>
                        </option>
                        <option value="random">
                            <?php _e('Random Matchmaking', 'fixture-generator-for-sportpress'); ?>
                        </option>
                        <option value="playoffs-single">
                            <?php _e('Playoffs - Single Elimination (Top 4/8)', 'fixture-generator-for-sportpress'); ?>
                        </option>
                    </select>
                </div>

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <div class="fgsp-field" style="flex:1;">
                        <label>
                            <?php _e('Start Date', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="date" id="fgsp-modal-date" value="<?php echo date('Y-m-d'); ?>"
                            style="width: 100%;">
                    </div>
                    <div class="fgsp-field" style="flex:1;">
                        <label>
                            <?php _e('Time', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="time" id="fgsp-modal-time" value="18:00" style="width: 100%;">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <div class="fgsp-field" style="flex:1;">
                        <label>
                            <?php _e('Interval (Days)', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="number" id="fgsp-modal-interval" value="7" min="1" style="width: 100%;">
                    </div>
                    <div class="fgsp-field" style="flex:1; padding-top:20px;">
                        <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" id="fgsp-modal-balance-home" checked>
                            <?php _e('Balance Localía', 'fixture-generator-for-sportpress'); ?>
                        </label>
                    </div>
                </div>

                <!-- Advanced Settings Toggle -->
                <div class="fgsp-modal-advanced-toggle"
                    style="margin-top:15px; border-top: 1px solid #eee; padding-top: 10px;">
                    <button type="button" class="button-link fgsp-modal-toggle-adv"
                        style="padding:0; font-size:11px; text-decoration:none;">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Advanced Settings (Calendar / Venue)', 'fixture-generator-for-sportpress'); ?>
                    </button>
                </div>

                <div id="fgsp-modal-advanced-fields"
                    style="display:none; margin-top:10px; border-top:1px dashed #eee; padding-top:10px;">
                    <label style="font-weight:600; display:block; margin-bottom:5px; font-size:12px;">
                        <?php _e('Allowed Days', 'fixture-generator-for-sportpress'); ?>
                    </label>
                    <div
                        style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; background:#f9f9f9; padding:8px; border-radius:4px;">
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="1">
                            <span>M</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="2">
                            <span>T</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="3">
                            <span>W</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="4">
                            <span>T</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="5">
                            <span>F</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="6" checked>
                            <span>S</span></label>
                        <label style="font-size:11px;"><input type="checkbox" class="fgsp-modal-day" value="0" checked>
                            <span>S</span></label>
                    </div>

                    <div class="fgsp-field">
                        <label style="font-weight:600; display:block; margin-bottom:5px; font-size:12px;">
                            <?php _e('Rotate Times (comma separated)', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="text" id="fgsp-modal-rotate-times" value="18:00"
                            style="width:100%; font-size:12px;" placeholder="18:00, 20:00">
                    </div>

                    <div class="fgsp-field" style="margin-top: 10px;">
                        <label style="font-weight:600; display:block; margin-bottom:5px; font-size:12px;">
                            <?php _e('Round Name Prefix', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="text" id="fgsp-modal-round-prefix" value="Jornada"
                            style="width:100%; font-size:12px;" placeholder="Jornada">
                    </div>

                    <div class="fgsp-field" style="margin-top: 10px;">
                        <label style="font-weight:600; display:block; margin-bottom:5px; font-size:12px;">
                            <?php _e('Exclude Specific Dates (YYYY-MM-DD, comma separated)', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <input type="text" id="fgsp-modal-exclude-dates" value="" style="width:100%; font-size:12px;"
                            placeholder="2026-12-25, 2027-01-01">
                    </div>

                    <div class="fgsp-field" style="margin-top: 10px;">
                        <label
                            style="font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" id="fgsp-modal-shuffle-teams">
                            <?php _e('Shuffle Teams before generation', 'fixture-generator-for-sportpress'); ?>
                        </label>
                    </div>

                    <div class="fgsp-field" style="margin-top:10px;">
                        <label
                            style="font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" id="fgsp-modal-assign-venue" checked>
                            <?php _e('Auto-assign Venue', 'fixture-generator-for-sportpress'); ?>
                        </label>
                        <span style="font-size: 10px; color: #777; display: block; margin-left: 20px;">
                            <?php _e("Uses Home Team's primary venue.", 'fixture-generator-for-sportpress'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div id="fgsp-modal-progress" class="fgsp-progress-container" style="display:none; margin-top:20px;">
                <div class="fgsp-progress-bar">
                    <div class="fgsp-progress-fill" style="width: 100%;"></div>
                </div>
                <p class="fgsp-progress-text">
                    <?php _e('Generating...', 'fixture-generator-for-sportpress'); ?>
                </p>
            </div>
        </div>
        <div class="fgsp-modal-footer">
            <button type="button" id="fgsp-modal-cancel" class="button">
                <?php _e('Cancel', 'fixture-generator-for-sportpress'); ?>
            </button>
            <button type="button" id="fgsp-modal-submit" class="button button-primary">
                <?php _e('Generate Now', 'fixture-generator-for-sportpress'); ?>
            </button>
        </div>
    </div>
</div>