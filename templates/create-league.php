<?php
/**
 * Template for creating a league table.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fgsp-admin-wrapper">
    <h1 class="wp-heading-inline">
        <?php _e('Crear Nueva Liga (Estructura Completa)', 'fixture-generator-for-sportpress'); ?>
    </h1>
    <hr class="wp-header-end">

    <div class="fgsp-card"
        style="max-width: 800px; margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 30px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0, 0, 0, .04);">
        <form id="fgsp-create-league-form">
            <div class="fgsp-form-section">
                <h3><?php _e('Información de la Competencia', 'fixture-generator-for-sportpress'); ?></h3>
                <div class="fgsp-form-row" style="margin-bottom: 25px;">
                    <label for="league_name" style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <?php _e('Nombre de la Liga / Torneo', 'fixture-generator-for-sportpress'); ?>
                    </label>
                    <input type="text" name="league_name" id="league_name" class="regular-text"
                        style="width: 100%; border-radius: 6px; padding: 12px; border: 1px solid #8c8f94;"
                        placeholder="<?php _e('Ej: Liga de Verano 2026', 'fixture-generator-for-sportpress'); ?>"
                        required>
                    <p class="description">
                        <?php _e('Este nombre se usará para el Torneo, la Tabla y los términos de competencia.', 'fixture-generator-for-sportpress'); ?>
                    </p>
                </div>
            </div>

            <div class="fgsp-form-section" style="margin-top: 30px;">
                <h3 style="margin-bottom: 10px;">
                    <?php _e('Equipos Participantes', 'fixture-generator-for-sportpress'); ?></h3>
                <p class="description" style="margin-bottom: 15px;">
                    <?php _e('Selecciona todos los equipos que formarán parte de esta liga.', 'fixture-generator-for-sportpress'); ?>
                </p>

                <div class="fgsp-teams-selector"
                    style="max-height: 350px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <div style="margin-bottom: 12px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                        <label style="font-weight: bold; cursor: pointer;">
                            <input type="checkbox" id="fgsp-select-all-teams">
                            <?php _e('Seleccionar Todos los Equipos', 'fixture-generator-for-sportpress'); ?>
                        </label>
                    </div>
                    <div class="fgsp-team-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <?php foreach ($teams as $team): ?>
                            <label class="fgsp-team-item"
                                style="display: flex; align-items: center; gap: 8px; padding: 6px; cursor: pointer; border-radius: 3px;">
                                <input type="checkbox" name="team_ids[]" value="<?php echo $team->ID; ?>"
                                    class="fgsp-team-checkbox">
                                <span class="fgsp-team-name"><?php echo esc_html($team->post_title); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="fgsp-form-section"
                style="margin-top: 35px; border-top: 1px solid #eee; padding-top: 20px; text-align: right;">
                <button type="submit" class="button button-primary button-large" id="fgsp-btn-submit-league"
                    style="height: 46px; padding: 0 30px; font-size: 15px;">
                    <?php _e('Crear e Ir a la Tabla', 'fixture-generator-for-sportpress'); ?>
                </button>
            </div>
        </form>
    </div>

    <div id="fgsp-league-result-message" style="margin-top: 25px; max-width: 800px;"></div>
</div>

<script>
    jQuery(document).ready(function ($) {
        // Hover effect for team items
        $('.fgsp-team-item').hover(
            function () { $(this).css('background', '#f0f6fb'); },
            function () { $(this).css('background', 'transparent'); }
        );

        // Select all teams
        $('#fgsp-select-all-teams').on('change', function () {
            $('.fgsp-team-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Form submission
        $('#fgsp-create-league-form').on('submit', function (e) {
            e.preventDefault();

            const $btn = $('#fgsp-btn-submit-league');
            const $msg = $('#fgsp-league-result-message');

            const teamsSelected = $('.fgsp-team-checkbox:checked').length;
            if (teamsSelected === 0) {
                alert('<?php _e('Por favor, selecciona al menos un equipo.', 'fixture-generator-for-sportpress'); ?>');
                return;
            }

            $btn.prop('disabled', true).text('<?php _e('Generando Estructura...', 'fixture-generator-for-sportpress'); ?>');

            const formData = $(this).serialize();
            const data = {
                action: 'fgsp_create_league_full',
                nonce: fgspData.nonce,
                data: formData
            };

            $.post(fgspData.ajaxUrl, data, function (response) {
                if (response.success) {
                    $msg.html('<div class="notice notice-success is-dismissible" style="display: block; border-left-color: #68b231;"><p><strong>' + response.data.message + '</strong></p></div>');
                    // Redirect after a short delay
                    setTimeout(function () {
                        window.location.href = response.data.redirect_url;
                    }, 1200);
                } else {
                    $msg.html('<div class="notice notice-error" style="display: block;"><p>' + response.data + '</p></div>');
                    $btn.prop('disabled', false).text('<?php _e('Crear e Ir a la Tabla', 'fixture-generator-for-sportpress'); ?>');
                }
            });
        });
    });
</script>

<style>
    .fgsp-team-item:hover {
        background: #f0f6fb;
    }

    .fgsp-card {
        border: 1px solid #ccd0d4;
    }

    .fgsp-form-section h3 {
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        color: #1d2327;
    }
</style>