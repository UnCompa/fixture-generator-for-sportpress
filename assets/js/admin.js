jQuery(document).ready(function($) {
    const $selector = $('#fgsp-tournament-selector');
    const $groupsContainer = $('#fgsp-groups-container');
    const $loader = $('#fgsp-loader');
    const $actions = $('#fgsp-global-actions');

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

    function renderGroups(groups, preselectedTable = 0) {
        if (!groups.length) {
            $groupsContainer.html('<div class="fgsp-main-card"><p>No groups found for this tournament.</p></div>').show();
            $actions.hide();
            return;
        }

        let html = '';
        groups.forEach(group => {
            const teamCount = group.teams.length;
            const isIncomplete = teamCount < 2;
            const isHighlighted = (preselectedTable && parseInt(preselectedTable) === parseInt(group.id));
            
            html += `
                <div class="fgsp-group-card ${isIncomplete ? 'incomplete' : ''} ${isHighlighted ? 'highlighted' : ''}" id="group-${group.id}">
                    <div class="fgsp-group-header">
                        <h3>${group.title}</h3>
                        <span class="fgsp-team-count">${teamCount} Teams</span>
                    </div>
                    
                    ${isIncomplete ? `
                        <div class="fgsp-alert fgsp-alert-warning">
                            <span class="dashicons dashicons-warning"></span> At least 2 teams required to generate fixtures.
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
                    </div>
                </div>
            `;
        });

        $groupsContainer.html(html).show();
        $actions.fadeIn(400);
    }

    $(document).on('click', '#fgsp-generate-all', async function() {
        const $btn = $(this);
        const $progressContainer = $('.fgsp-progress-container');
        const $progressFill = $('.fgsp-progress-fill');
        const $progressText = $('.fgsp-progress-text');
        const tournamentId = $selector.val();
        
        const $groupCards = $('.fgsp-group-card:not(.incomplete)');
        const totalGroups = $groupCards.length;

        if (totalGroups === 0) {
            alert('No groups with enough teams to generate fixtures.');
            return;
        }

        if (!confirm(`Are you sure you want to generate all fixtures for ${totalGroups} groups?`)) {
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

            console.log(`FGSP: Generating fixtures for group ${tableId}...`);
            try {
                const response = await $.ajax({
                    url: fgspData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fgsp_generate_fixtures',
                        tournament_id: tournamentId,
                        table_id: tableId,
                        algorithm: algorithm,
                        start_date: startDate,
                        start_time: startTime,
                        interval: interval,
                        balance_home: balanceHome,
                        nonce: fgspData.nonce
                    }
                });

                if (response.success) {
                    completed++;
                    console.log(`FGSP: Success for group ${tableId}. Created ${response.data.count} events.`);
                    const percent = Math.round((completed / totalGroups) * 100);
                    $progressFill.css('width', percent + '%');
                    $progressText.text(`${percent}% (${completed}/${totalGroups} groups completed)`);
                } else {
                    console.error(`FGSP Error in group ${tableId}:`, response.data);
                }
            } catch (err) {
                console.error(`Request failed for group ${tableId}:`, err);
            }
        }

        $btn.prop('disabled', false).removeClass('updating');
        alert(`Finished! Generated fixtures for ${completed} groups.`);
        
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

        $modalSubmit.on('click', async function() {
            const tournamentId = $('#fgsp-modal-tournament-id').val();
            const tableId = $('#fgsp-modal-table-id').val();
            const algorithm = $('#fgsp-modal-algorithm').val();
            const startDate = $('#fgsp-modal-date').val();
            const startTime = $('#fgsp-modal-time').val();
            const interval = $('#fgsp-modal-interval').val();
            const balanceHome = $('#fgsp-modal-balance').is(':checked') ? 1 : 0;

            if (!tournamentId) {
                alert('Tournament ID not found. Please link this table to a tournament first.');
                return;
            }

            $modalSubmit.prop('disabled', true).text('Generating...');
            $modalProgress.slideDown();

            try {
                const response = await $.ajax({
                    url: fgspData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fgsp_generate_fixtures',
                        tournament_id: tournamentId,
                        table_id: tableId,
                        algorithm: algorithm,
                        start_date: startDate,
                        start_time: startTime,
                        interval: interval,
                        balance_home: balanceHome,
                        nonce: fgspData.nonce
                    }
                });

                if (response.success) {
                    alert(`Success! Generated ${response.data.count} events.`);
                    location.reload(); // Reload to see results in SportsPress calendars
                } else {
                    alert('Error: ' + response.data);
                }
            } catch (err) {
                console.error('Modal Request Failed:', err);
                alert('Request failed. Check console for details.');
            } finally {
                $modalSubmit.prop('disabled', false).text('Generate Now');
            }
        });
    }

    /**
     * Tournament Groups Manager Logic
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

            if (!name) {
                alert('Please enter a group name.');
                return;
            }

            if (selectedTeams.length === 0) {
                alert('Please select at least one team.');
                return;
            }

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
                console.error('Group Creation Failed:', err);
                alert('Request failed. Check console.');
            } finally {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="vertical-align:middle; line-height:1.5;"></span> Create Group & Assign');
            }
        });
    }
});
