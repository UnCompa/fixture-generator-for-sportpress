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
                        <div class="fgsp-config-title">Algorithm</div>
                        <select class="fgsp-algorithm-select" style="width: 100%;">
                            <option value="round-robin">Round Robin (Ida y Vuelta)</option>
                            <option value="single-round-robin">Round Robin (Solo Ida)</option>
                            <option value="random">Emparejamiento Aleatorio</option>
                        </select>
                    </div>
                </div>
            `;
        });

        $groupsContainer.html(html).show();
        $actions.fadeIn(400);
    }
});
