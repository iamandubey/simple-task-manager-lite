(function() {
    var cfg = window.neuratmAdminConfig || {};

    var taskDialog = document.getElementById('stm-task-dialog');
    var openTaskBtn = document.getElementById('stm-open-task-dialog');
    var closeTaskBtn = document.getElementById('stm-close-task-dialog');
    if (taskDialog && openTaskBtn) {
        openTaskBtn.addEventListener('click', function() {
            if (taskDialog.showModal) { taskDialog.showModal(); }
        });
    }
    if (taskDialog && closeTaskBtn) {
        closeTaskBtn.addEventListener('click', function() { taskDialog.close(); });
    }

    var leaderDialog = document.getElementById('stm-leaderboard-dialog');
    var openLeaderBtn = document.getElementById('stm-open-leaderboard-dialog');
    var closeLeaderBtn = document.getElementById('stm-close-leaderboard-dialog');
    if (leaderDialog && openLeaderBtn) {
        openLeaderBtn.addEventListener('click', function() {
            if (leaderDialog.showModal) { leaderDialog.showModal(); }
        });
    }
    if (leaderDialog && closeLeaderBtn) {
        closeLeaderBtn.addEventListener('click', function() { leaderDialog.close(); });
    }

    if (cfg.openTaskDialogOnLoad && taskDialog && taskDialog.showModal) {
        taskDialog.showModal();
    }

    var dialog = document.getElementById('stm-view-dialog');
    if (dialog) {
        var buttons = document.querySelectorAll('.stm-view-btn');
        var closeBtn = document.getElementById('stm-close-dialog');

        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('stm-view-title').textContent = btn.getAttribute('data-title') || '';
                document.getElementById('stm-view-description').textContent = btn.getAttribute('data-description') || '';
                document.getElementById('stm-view-assignedby').textContent = btn.getAttribute('data-assignedby') || '';
                var assignedToAdminEl = document.getElementById('stm-view-assignedto');
                if (assignedToAdminEl) {
                    assignedToAdminEl.textContent = btn.getAttribute('data-assignedto') || '';
                }
                document.getElementById('stm-view-priority').textContent = btn.getAttribute('data-priority') || '';
                document.getElementById('stm-view-status').textContent = btn.getAttribute('data-status') || '';
                document.getElementById('stm-view-due').textContent = btn.getAttribute('data-due') || '';
                document.getElementById('stm-view-reward').textContent = btn.getAttribute('data-reward') || '0';

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dialog.close();
            });
        }
    }

    if (cfg.autoRefreshEnabled) {
        var ms = parseInt(cfg.autoRefreshMs, 10);
        var onlyVisible = !!cfg.autoRefreshOnlyVisible;
        if (!ms || ms < 10000) {
            ms = 60000;
        }

        function refreshUrl() {
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('stm_refresh', String(Date.now()));
                return u.toString();
            } catch (e) {
                var href = window.location.href;
                var cleaned = href.replace(/([?&])stm_refresh=[^&]*/g, '$1').replace(/[?&]$/, '');
                var sep = cleaned.indexOf('?') === -1 ? '?' : '&';
                return cleaned + sep + 'stm_refresh=' + Date.now();
            }
        }

        window.setInterval(function() {
            if (onlyVisible && document.hidden) {
                return;
            }
            window.location.href = refreshUrl();
        }, ms);
    }
})();
