jQuery(document).ready(function($) {
    const $selector = $('#fgsp-tournament-selector');
    const $groupsContainer = $('#fgsp-groups-container');
    const $loader = $('#fgsp-loader');
    const $actions = $('#fgsp-global-actions');
    
    let currentKnockoutEvents = [];

    $selector.on('change', function() {
        const tournamentId = $(this).val();
        
        if (!tournamentId) {
            $groupsContainer.hide().html('');
            $actions.hide();
            return;
        }

        const preselectedTable = $selector.data('preselected-table');
        loadGroups(tournamentId, preselectedTable);
    });

    // Check for pre-selected values on load
    if ($selector.val()) {
        $selector.trigger('change');
    }

    function loadGroups(tournamentId, preselectedTable = 0) {
        $loader.fadeIn(200);

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_get_tournament_groups',
                tournament_id: tournamentId,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentKnockoutEvents = response.data.knockout_events || [];
                    renderGroups(response.data, preselectedTable);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $loader.fadeOut(200);
            }
        });
    }

    function renderGroups(data, preselectedTable = 0) {
        const groups = data.groups || [];
        
        if (!groups.length) {
            $groupsContainer.html('<div class="fgsp-main-card"><p>No groups found for this tournament. Use the button below to create the first one!</p></div>').show();
            $actions.fadeIn(400);
            return;
        }

        let html = '';
        groups.forEach(group => {
            const teamCount = group.teams.length;
            const isIncomplete = teamCount < 2;
            const isHighlighted = (preselectedTable && parseInt(preselectedTable) === parseInt(group.id));
            
            html += `
                <div class="fgsp-group-card ${isIncomplete ? 'incomplete' : ''} ${isHighlighted ? 'highlighted' : ''} ${group.is_played ? 'is-locked' : ''}" id="group-${group.id}">
                    <div class="fgsp-group-header">
                        <h3><a href="post.php?post=${group.id}&action=edit" title="Edit League Table">${group.title}</a></h3>
                        <div class="fgsp-group-actions">
                            ${group.has_fixtures ? `
                                <button type="button" class="fgsp-btn-icon fgsp-view-events" data-table-id="${group.id}" title="View & Edit Results">
                                    <span class="dashicons dashicons-soccer"></span>
                                </button>
                                <button type="button" class="fgsp-btn-icon fgsp-promote-btn" data-table-id="${group.id}" title="Promote to Playoffs">
                                    <span class="dashicons dashicons-external"></span>
                                </button>
                            ` : ''}
                            <span class="fgsp-team-count">${teamCount} Teams</span>
                        </div>
                    </div>
                    
                    ${isIncomplete ? `
                        <div class="fgsp-alert fgsp-alert-warning">
                            <span class="dashicons dashicons-warning"></span> At least 2 teams required to generate fixtures.
                        </div>
                    ` : ''}

                    ${group.is_played ? `
                        <div class="fgsp-alert fgsp-alert-error" style="background:#fde8e8; color:#9b1c1c; border-color:#f8b4b4; padding: 10px; border-radius: 4px; margin-bottom: 10px; border: 1px solid;">
                            <span class="dashicons dashicons-lock"></span> Bloqueado: Este grupo ya tiene resultados.
                        </div>
                    ` : group.has_fixtures ? `
                        <div class="fgsp-alert fgsp-alert-info" style="background:#e1effe; color:#1e429f; border-color:#bcdefa; padding: 10px; border-radius: 4px; margin-bottom: 10px; border: 1px solid;">
                            <span class="dashicons dashicons-info"></span> Ya existen fixtures generados.
                        </div>
                    ` : ''}


                    <div class="fgsp-config-title">Linked Teams</div>
                    <ul class="fgsp-team-list">
                        ${group.teams.map(team => `
                            <li class="fgsp-team-item">
                                <span class="dashicons dashicons-groups"></span>
                                ${team.name}
                            </li>
                        `).join('')}
                    </ul>

                    <div class="fgsp-config-section">
                        <div class="fgsp-config-row">
                            <div class="fgsp-field">
                                <div class="fgsp-config-title">Algorithm</div>
                                <select class="fgsp-algorithm-select" style="width: 100%;">
                                    <option value="round-robin">Round Robin (Ida y Vuelta)</option>
                                    <option value="single-round-robin">Round Robin (Solo Ida)</option>
                                    <option value="reverse-round-robin">Round Robin (Vuelta e Ida)</option>
                                    <option value="random">Emparejamiento Aleatorio</option>
                                    <option value="knockout">Knockout (Eliminación Directa)</option>
                                    <option value="playoffs-single">Playoffs (Top 4/8 - Seeded)</option>
                                </select>
                            </div>
                        </div>
                        <div class="fgsp-config-row" style="margin-top: 15px; display: flex; gap: 10px;">
                            <div class="fgsp-field" style="flex: 1.5;">
                                <div class="fgsp-config-title">Start Date</div>
                                <input type="date" class="fgsp-start-date" value="${new Date().toISOString().split('T')[0]}" style="width: 100%;">
                            </div>
                            <div class="fgsp-field" style="flex: 1;">
                                <div class="fgsp-config-title">Time</div>
                                <input type="time" class="fgsp-start-time" value="18:00" style="width: 100%;">
                            </div>
                        </div>
                        <div class="fgsp-config-row" style="margin-top: 15px; display: flex; gap: 10px;">
                            <div class="fgsp-field" style="flex: 1;">
                                <div class="fgsp-config-title">Interval (Days)</div>
                                <input type="number" class="fgsp-interval" value="7" min="1" max="365" style="width: 100%;">
                            </div>
                            <div class="fgsp-field" style="flex: 2; align-self: end; padding-bottom: 5px;">
                                <label style="font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" class="fgsp-balance-home" checked> Balance Localía
                                </label>
                            </div>
                        </div>

                        <!-- Advanced Settings -->
                        <div class="fgsp-advanced-toggle-wrapper" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                            <button type="button" class="button-link fgsp-toggle-advanced" style="text-decoration: none; font-size: 11px; padding: 0;">
                                <span class="dashicons dashicons-arrow-down-alt2"></span> Advanced Settings (Calendar / Venue)
                            </button>
                        </div>
                        <div class="fgsp-advanced-settings" style="display: none; padding-top: 10px; border-top: 1px dashed #eee; margin-top: 5px;">
                            <div class="fgsp-config-title">Allowed Days</div>
                            <div class="fgsp-days-selector" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; background: #f9f9f9; padding: 8px; border-radius: 4px;">
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="1"> <span>M</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="2"> <span>T</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="3"> <span>W</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="4"> <span>T</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="5"> <span>F</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="6" checked> <span>S</span></label>
                                <label style="font-size:11px;"><input type="checkbox" class="fgsp-day" value="0" checked> <span>S</span></label>
                            </div>
                            
                            <div class="fgsp-field">
                                <div class="fgsp-config-title">Rotate Times (comma separated)</div>
                                <input type="text" class="fgsp-rotate-times" placeholder="18:00, 20:00" style="width: 100%; font-size: 12px;" value="18:00">
                            </div>

                            <div class="fgsp-field" style="margin-top: 10px;">
                                <div class="fgsp-config-title">Round Name Prefix</div>
                                <input type="text" class="fgsp-round-prefix" placeholder="Jornada" style="width: 100%; font-size: 12px;" value="Jornada">
                            </div>

                            <div class="fgsp-field" style="margin-top: 10px;">
                                <div class="fgsp-config-title">Exclude Specific Dates (YYYY-MM-DD, comma separated)</div>
                                <input type="text" class="fgsp-exclude-dates" placeholder="2026-12-25, 2027-01-01" style="width: 100%; font-size: 12px;">
                            </div>

                            <div class="fgsp-field" style="margin-top: 10px;">
                                <label style="font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" class="fgsp-shuffle-teams"> Shuffle Teams
                                </label>
                            </div>

                            <div class="fgsp-field" style="margin-top: 10px;">
                                <label style="font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" class="fgsp-assign-venue" checked> Auto-assign Venue
                                </label>
                            </div>
                        </div>

                        <button type="button" class="button button-primary fgsp-generate-individual" ${group.is_played || isIncomplete ? 'disabled' : ''}>
                             <span class="dashicons dashicons-randomize"></span> ${group.has_fixtures ? 'Regenerate This Group' : 'Generate This Group'}
                        </button>
                    </div>
                </div>
            `;
        });

        $groupsContainer.html(html).show();
        $actions.fadeIn(400);
    }

    $(document).on('click', '.fgsp-toggle-advanced', function() {
        const $btn = $(this);
        const $target = $btn.closest('.fgsp-group-card').find('.fgsp-advanced-settings');
        const $icon = $btn.find('.dashicons');

        if ($target.is(':visible')) {
            $target.slideUp(200);
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
            $target.slideDown(200);
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }
    });

    /**
     * Individual Group Generation
     */
    $(document).on('click', '.fgsp-generate-individual', function() {
        const $card = $(this).closest('.fgsp-group-card');
        const tableId = $card.attr('id').replace('group-', '');
        generateFixturesForGroup(tableId, $card);
    });

    async function generateFixturesForGroup(tableId, $card, confirmOverwrite = 0) {
        const tournamentId = $selector.val();
        $loader.fadeIn();

        try {
            const requestData = {
                action: 'fgsp_generate_fixtures',
                tournament_id: tournamentId,
                table_id: tableId,
                algorithm: $card.find('.fgsp-algorithm-select').val(),
                start_date: $card.find('.fgsp-start-date').val(),
                start_time: $card.find('.fgsp-start-time').val(),
                interval: $card.find('.fgsp-interval').val(),
                balance_home: $card.find('.fgsp-balance-home').is(':checked') ? 1 : 0,
                allowed_days: $card.find('.fgsp-day:checked').map(function() { return $(this).val(); }).get(),
                rotate_times: $card.find('.fgsp-rotate-times').val(),
                assign_venue: $card.find('.fgsp-assign-venue').is(':checked') ? 1 : 0,
                round_prefix: $card.find('.fgsp-round-prefix').val(),
                exclude_dates: $card.find('.fgsp-exclude-dates').val(),
                shuffle_teams: $card.find('.fgsp-shuffle-teams').is(':checked') ? 1 : 0,
                confirm_overwrite: confirmOverwrite,
                nonce: fgspData.nonce
            };

            const response = await $.ajax({
                url: fgspData.ajaxUrl,
                type: 'POST',
                data: requestData
            });

            if (response.success && response.data.status === 'confirmation_required') {
                $loader.fadeOut();
                if (confirm(response.data.message)) {
                    generateFixturesForGroup(tableId, $card, 1);
                }
                return;
            }

            if (response.success) {
                alert(`Success! Created ${response.data.count} events.`);
                loadGroups(tournamentId); // Refresh UI
            } else {
                alert('Error: ' + response.data);
            }
        } catch (err) {
            console.error('Individual Generation Failed:', err);
            alert('Request failed.');
        } finally {
            $loader.fadeOut();
        }
    }

    /**
     * View Events & Edit Results Modal Logic
     */
    const $eventModal = $('#fgsp-event-viewer-modal');
    const $eventContainer = $('#fgsp-event-list-container');
    const $saveResultsBtn = $('#fgsp-save-results-btn');

    $(document).on('click', '.fgsp-view-events', function() {
        const tableId = $(this).data('table-id');
        $eventContainer.html('<p class="text-center"><span class="dashicons dashicons-update spin"></span> Loading events & results...</p>');
        $eventModal.fadeIn(300).css('display', 'flex');
        $saveResultsBtn.hide();

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_get_group_events',
                table_id: tableId,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.length === 0) {
                        $eventContainer.html('<p>No events found.</p>');
                    } else {
                        let html = `
                            <table class="fgsp-event-table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th style="text-align:center;">Home</th>
                                        <th style="text-align:center;">Score</th>
                                        <th style="text-align:center;">Away</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                        
                        response.data.forEach(event => {
                            html += `
                                <tr class="fgsp-result-row" data-event-id="${event.id}">
                                    <td><small>${event.title}</small></td>
                                    <td style="text-align:right;"><strong>${event.home_name}</strong></td>
                                    <td style="text-align:center; min-width:100px;">
                                        <input type="number" class="fgsp-score-home" value="${event.home_goals}" style="width:40px; text-align:center; padding:2px;"> 
                                        - 
                                        <input type="number" class="fgsp-score-away" value="${event.away_goals}" style="width:40px; text-align:center; padding:2px;">
                                    </td>
                                    <td><strong>${event.away_name}</strong></td>
                                    <td><small>${event.date}</small></td>
                                    <td><a href="${event.edit_link}" class="button button-small" target="_blank"><span class="dashicons dashicons-edit"></span></a></td>
                                </tr>`;
                        });
                        html += '</tbody></table>';
                        $eventContainer.html(html);
                        $saveResultsBtn.show();
                    }
                } else {
                    $eventContainer.html('<p>Error: ' + response.data + '</p>');
                }
            }
        });
    });

    $saveResultsBtn.on('click', function() {
        const $btn = $(this);
        const results = {};
        
        $('.fgsp-result-row').each(function() {
            const $row = $(this);
            const id = $row.data('event-id');
            const home = $row.find('.fgsp-score-home').val();
            const away = $row.find('.fgsp-score-away').val();
            
            if (home !== '' || away !== '') {
                results[id] = { home: home, away: away };
            }
        });

        if (Object.keys(results).length === 0) {
            alert('No se han ingresado marcadores nuevos.');
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_save_quick_results',
                results: results,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $eventModal.fadeOut(200);
                    // Refresh groups to toggle locks if needed
                    const tournamentId = $selector.val();
                    loadGroups(tournamentId);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Guardar Resultados');
            }
        });
    });

    $('.fgsp-close-event-modal').on('click', function() {
        $eventModal.fadeOut(200);
    });

    /**
     * Promotion Logic
     */
    const $promotionModal = $('#fgsp-promotion-modal');
    const $promotionContent = $('#fgsp-promotion-content');
    const $submitPromotionBtn = $('#fgsp-submit-promotion-btn');

    $(document).on('click', '.fgsp-promote-btn', function() {
        const tableId = $(this).data('table-id');
        $promotionContent.html('<p class="text-center"><span class="dashicons dashicons-update spin"></span> Fetching standings...</p>');
        $promotionModal.fadeIn(300).css('display', 'flex');
        $submitPromotionBtn.hide();

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_get_group_standings',
                table_id: tableId,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const standings = response.data;
                    if (standings.length === 0) {
                        $promotionContent.html('<p>No teams found in this group.</p>');
                    } else {
                        let html = `
                            <div style="margin-bottom:20px;">
                                <p>Asigne los equipos mejor clasificados a los eventos de eliminatoria correspondientes.</p>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th width="50">Pos</th>
                                            <th>Team</th>
                                            <th>Points</th>
                                            <th>Promote To</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        
                        standings.forEach(team => {
                            html += `
                                <tr class="fgsp-promotion-row" data-team-id="${team.id}">
                                    <td><strong>${team.pos}</strong></td>
                                    <td>${team.name}</td>
                                    <td>${team.pts} pts (${team.p} PJ)</td>
                                    <td>
                                        <select class="fgsp-promo-event" style="width:70%;">
                                            <option value="">-- No promover --</option>
                                            ${currentKnockoutEvents.map(event => `
                                                <option value="${event.id}">[Event] ${event.title}</option>
                                            `).join('')}
                                        </select>
                                        <select class="fgsp-promo-side" style="width:25%;">
                                            <option value="home">Local</option>
                                            <option value="away">Visita</option>
                                        </select>
                                    </td>
                                </tr>`;
                        });
                        html += '</tbody></table></div>';
                        $promotionContent.html(html);
                        $submitPromotionBtn.show();
                    }
                } else {
                    $promotionContent.html('<p>Error: ' + response.data + '</p>');
                }
            }
        });
    });

    $submitPromotionBtn.on('click', function() {
        const $btn = $(this);
        const promotions = {}; // event_id => {home: id, away: id}
        
        $('.fgsp-promotion-row').each(function() {
            const $row = $(this);
            const teamId = $row.data('team-id');
            const eventId = $row.find('.fgsp-promo-event').val();
            const side = $row.find('.fgsp-promo-side').val();
            
            if (eventId) {
                if (!promotions[eventId]) promotions[eventId] = {};
                promotions[eventId][side] = teamId;
            }
        });

        if (Object.keys(promotions).length === 0) {
            alert('Por favor seleccione al menos un evento de destino.');
            return;
        }

        $btn.prop('disabled', true).text('Promoviendo...');

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_submit_promotions',
                promotions: promotions,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $promotionModal.fadeOut(200);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text('Confirmar Promoción');
            }
        });
    });

    $('.fgsp-close-promotion-modal').on('click', function() {
        $promotionModal.fadeOut(200);
    });

    /**
     * Bulk Generation Global Action (already added above)
     */
    $(document).on('click', '#fgsp-generate-all', async function() {
        const $btn = $(this);
        const $progressContainer = $('.fgsp-progress-container');
        const $progressFill = $('.fgsp-progress-fill');
        const $progressText = $('.fgsp-progress-text');
        const tournamentId = $selector.val();
        
        const $groupCards = $('.fgsp-group-card:not(.incomplete):not(.is-locked)');
        const totalGroups = $groupCards.length;

        if (totalGroups === 0) {
            alert('No unlocked groups ready for fixture generation.');
            return;
        }

        if (!confirm(`Are you sure you want to generate/regenerate fixtures for ${totalGroups} unlocked groups?`)) {
            return;
        }

        $btn.prop('disabled', true).addClass('updating');
        $progressContainer.fadeIn();
        
        let completed = 0;

        for (let i = 0; i < $groupCards.length; i++) {
            const $card = $($groupCards[i]);
            const tableId = $card.attr('id').replace('group-', '');
            const algorithm = $card.find('.fgsp-algorithm-select').val();
            const startDate = $card.find('.fgsp-start-date').val();
            const startTime = $card.find('.fgsp-start-time').val();
            const interval = $card.find('.fgsp-interval').val();
            const balanceHome = $card.find('.fgsp-balance-home').is(':checked') ? 1 : 0;

            try {
                const requestData = {
                    action: 'fgsp_generate_fixtures',
                    tournament_id: tournamentId,
                    table_id: tableId,
                    algorithm: algorithm,
                    start_date: startDate,
                    start_time: startTime,
                    interval: interval,
                    balance_home: balanceHome,
                    allowed_days: $card.find('.fgsp-day:checked').map(function() { return $(this).val(); }).get(),
                    rotate_times: $card.find('.fgsp-rotate-times').val(),
                    assign_venue: $card.find('.fgsp-assign-venue').is(':checked') ? 1 : 0,
                    round_prefix: $card.find('.fgsp-round-prefix').val(),
                    exclude_dates: $card.find('.fgsp-exclude-dates').val(),
                    shuffle_teams: $card.find('.fgsp-shuffle-teams').is(':checked') ? 1 : 0,
                    nonce: fgspData.nonce
                };

                // Check for existing fixtures individually to avoid stopping the whole process
                requestData.confirm_overwrite = $card.find('.fgsp-alert-info').length > 0 ? 1 : 0;

                const response = await $.ajax({
                    url: fgspData.ajaxUrl,
                    type: 'POST',
                    data: requestData
                });

                if (response.success) {
                    completed++;
                    const percent = Math.round((completed / totalGroups) * 100);
                    $progressFill.css('width', percent + '%');
                    $progressText.text(`${percent}% (${completed}/${totalGroups} groups completed)`);
                }
            } catch (err) {
                console.error(`Request failed for group ${tableId}:`, err);
            }
        }

        $btn.prop('disabled', false).removeClass('updating');
        alert(`Finished! Generated fixtures for ${completed} groups.`);
        loadGroups(tournamentId); // Refresh final state
        
        setTimeout(() => {
            $progressContainer.fadeOut();
            $progressFill.css('width', '0%');
        }, 3000);
    });

    /**
     * Modal Logic for Quick Generation (sp_table edit page)
     */
    const $quickModal = $('#fgsp-quick-modal');
    if ($quickModal.length) {
        const $modalProgress = $('#fgsp-modal-progress');
        const $modalSubmit = $('#fgsp-modal-submit');

        $('#fgsp-open-modal').on('click', function() {
            $quickModal.fadeIn(300).css('display', 'flex');
        });

        $('.fgsp-close-modal, #fgsp-modal-cancel').on('click', function() {
            if (!$modalSubmit.prop('disabled')) {
                $quickModal.fadeOut(200);
            }
        });

        $(document).on('click', '.fgsp-modal-toggle-adv', function() {
            const $btn = $(this);
            const $target = $('#fgsp-modal-advanced-fields');
            const $icon = $btn.find('.dashicons');
            $target.slideToggle(200);
            if ($icon.hasClass('dashicons-arrow-down-alt2')) {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            } else {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        });

        $modalSubmit.on('click', async function() {
            const tournamentId = $('#fgsp-modal-tournament-id').val();
            const tableId = $('#fgsp-modal-table-id').val();
            const algorithm = $('#fgsp-modal-algorithm').val();
            const startDate = $('#fgsp-modal-date').val();
            const startTime = $('#fgsp-modal-time').val();
            const interval = $('#fgsp-modal-interval').val();

            if (!tournamentId) {
                alert('Tournament ID not found. Please link this table to a tournament first.');
                return;
            }

            $modalSubmit.prop('disabled', true).text('Generating...');
            $modalProgress.slideDown();

            try {
                const requestData = {
                    action: 'fgsp_generate_fixtures',
                    tournament_id: tournamentId,
                    table_id: tableId,
                    algorithm: algorithm,
                    start_date: startDate,
                    start_time: startTime,
                    interval: interval,
                    balance_home: $('#fgsp-modal-balance-home').is(':checked') ? 1 : 0,
                    allowed_days: $('.fgsp-modal-day:checked').map(function() { return $(this).val(); }).get(),
                    rotate_times: $('#fgsp-modal-rotate-times').val(),
                    assign_venue: $('#fgsp-modal-assign-venue').is(':checked') ? 1 : 0,
                    round_prefix: $('#fgsp-modal-round-prefix').val(),
                    exclude_dates: $('#fgsp-modal-exclude-dates').val(),
                    shuffle_teams: $('#fgsp-modal-shuffle-teams').is(':checked') ? 1 : 0,
                    nonce: fgspData.nonce
                };

                let response = await $.ajax({
                    url: fgspData.ajaxUrl,
                    type: 'POST',
                    data: requestData
                });

                if (response.success && response.data.status === 'confirmation_required') {
                    if (confirm(response.data.message)) {
                        requestData.confirm_overwrite = 1;
                        response = await $.ajax({
                            url: fgspData.ajaxUrl,
                            type: 'POST',
                            data: requestData
                        });
                    } else {
                        $modalSubmit.prop('disabled', false).text('Generate Now');
                        $modalProgress.slideUp();
                        return;
                    }
                }

                if (response.success) {
                    alert(`Success! Generated ${response.data.count} events.`);
                    location.reload(); 
                } else {
                    alert('Error: ' + response.data);
                }
            } catch (err) {
                console.error('Modal Request Failed:', err);
                alert('Request failed.');
            } finally {
                $modalSubmit.prop('disabled', false).text('Generate Now');
            }
        });
    }

    /**
     * Tournament Groups Manager Logic (Tournament Page)
     */
    const $createGroupBtn = $('#fgsp-create-group-btn');
    if ($createGroupBtn.length) {
        $createGroupBtn.on('click', async function() {
            const $btn = $(this);
            const name = $('#fgsp-new-group-name').val();
            const tournamentId = $('#fgsp-tournament-id').val();
            const selectedTeams = [];
            
            $('input[name="fgsp_teams[]"]:checked').each(function() {
                selectedTeams.push($(this).val());
            });

            if (!name) { alert('Please enter a group name.'); return; }
            if (selectedTeams.length === 0) { alert('Please select at least one team.'); return; }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creating...');

            try {
                const response = await $.ajax({
                    url: fgspData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fgsp_create_tournament_group',
                        tournament_id: tournamentId,
                        name: name,
                        team_ids: selectedTeams,
                        nonce: fgspData.nonce
                    }
                });

                if (response.success) {
                    alert('Group created successfully!');
                    location.reload(); 
                } else {
                    alert('Error: ' + response.data);
                }
            } catch (err) {
                alert('Request failed.');
            } finally {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="vertical-align:middle; line-height:1.5;"></span> Create Group & Assign');
            }
        });
    }

    /**
     * Main Generator Page - Group Creation Logic
     */
    const $showCreateFormBtn = $('#fgsp-show-create-form');
    const $creationContainer = $('#fgsp-create-group-container');
    const $teamSelector = $('#fgsp-main-team-selector');

    $showCreateFormBtn.on('click', function() {
        const tournamentId = $selector.val();
        if(!tournamentId) return;

        $groupsContainer.hide();
        $actions.hide();
        $creationContainer.fadeIn();

        $teamSelector.html('<p><span class="dashicons dashicons-update spin"></span> Loading eligible teams...</p>');
        
        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_get_eligible_teams',
                tournament_id: tournamentId,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(team => {
                            html += `
                                <label style="display:flex; align-items:center; gap:8px; padding:8px; background:#fff; border-radius:4px; border:1px solid #eee; cursor:pointer;">
                                    <input type="checkbox" name="fgsp_main_teams[]" value="${team.id}">
                                    <span style="font-size:13px; font-weight:500;">${team.name}</span>
                                </label>`;
                        });
                    } else {
                        html = '<p>No teams found for the tournament league.</p>';
                    }
                    $teamSelector.html(html);
                } else {
                    $teamSelector.html('<p>Error loading teams.</p>');
                }
            }
        });
    });

    $('.fgsp-close-creation-form').on('click', function() {
        $creationContainer.hide();
        $groupsContainer.show();
        $actions.show();
    });

    $('#fgsp-main-create-group-btn').on('click', async function() {
        const $btn = $(this);
        const name = $('#fgsp-main-new-group-name').val();
        const tournamentId = $selector.val();
        const selectedTeams = [];
        
        $('input[name="fgsp_main_teams[]"]:checked').each(function() {
            selectedTeams.push($(this).val());
        });

        if (!name) { alert('Please enter a group name.'); return; }
        if (selectedTeams.length === 0) { alert('Please select teams.'); return; }

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creating...');

        try {
            const response = await $.ajax({
                url: fgspData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fgsp_create_tournament_group',
                    tournament_id: tournamentId,
                    name: name,
                    team_ids: selectedTeams,
                    nonce: fgspData.nonce
                }
            });

            if (response.success) {
                alert('Group created successfully!');
                $creationContainer.hide();
                $selector.trigger('change'); 
                $('#fgsp-main-new-group-name').val('');
            } else {
                alert('Error: ' + response.data);
            }
        } catch (err) {
            alert('Request failed.');
        } finally {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Create Group & Assign');
        }
    });

    /**
     * Playoff Generator Logic
     */
    const $playoffModal = $('#fgsp-playoff-modal');
    
    $('#fgsp-open-playoff-modal').on('click', function() {
        $playoffModal.fadeIn(300).css('display', 'flex');
    });

    $('.fgsp-close-playoff-modal').on('click', function() {
        $playoffModal.fadeOut(200);
    });

    $('#fgsp-generate-playoffs-btn').on('click', function() {
        const $btn = $(this);
        const tournamentId = $selector.val();
        
        if (!tournamentId) {
            alert('Please select a tournament first.');
            return;
        }

        const format = $('#fgsp-playoff-format').val();
        const legs = $('#fgsp-playoff-legs').val();

        if (!confirm('This will create new knockout events for your tournament. Continue?')) {
            return;
        }

        $btn.prop('disabled', true).text('Generating...');
        $loader.fadeIn();

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_generate_playoffs',
                tournament_id: tournamentId,
                format: format,
                legs: legs,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $playoffModal.fadeOut(200);
                    loadGroups(tournamentId); // Refresh UI
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Create Events');
                $loader.fadeOut();
            }
        });
    });

    /**
     * Calendar Creation Logic
     */
    $('#fgsp-create-calendar').on('click', function() {
        const tournamentId = $selector.val();
        const format = $('#fgsp-calendar-format').val() || 'blocks';
        
        if (!tournamentId) {
            alert('Please select a tournament first.');
            return;
        }

        $loader.fadeIn();

        $.ajax({
            url: fgspData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fgsp_create_tournament_calendar',
                tournament_id: tournamentId,
                format: format,
                nonce: fgspData.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (confirm(response.data.message + ' ¿Deseas ir a configurarlo?')) {
                        window.open(response.data.edit_link, '_blank');
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $loader.fadeOut();
            }
        });
    });

});
