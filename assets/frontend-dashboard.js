(function() {
    var cfg = window.neuratmFrontendConfig || {};

    var taskDialog = document.getElementById('stmf-task-dialog');
    var openTaskBtn = document.getElementById('stmf-open-task-dialog');
    var closeTaskBtn = document.getElementById('stmf-task-close');
    if (taskDialog && openTaskBtn) {
        openTaskBtn.addEventListener('click', function() {
            if (taskDialog.showModal) { taskDialog.showModal(); }
        });
    }
    if (taskDialog && closeTaskBtn) {
        closeTaskBtn.addEventListener('click', function() { taskDialog.close(); });
    }

    var leaderDialog = document.getElementById('stmf-leader-dialog');
    var openLeaderBtn = document.getElementById('stmf-open-leaderboard-dialog');
    var closeLeaderBtn = document.getElementById('stmf-leader-close');
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

    var dialog = document.getElementById('stmf-view-dialog');
    if (dialog) {
        var closeBtn = document.getElementById('stmf-v-close');
        var btns = document.querySelectorAll('.stmf-view-btn');
        var showDetailsText = cfg.showDetailsText || 'Show details';
        var hideDetailsText = cfg.hideDetailsText || 'Hide details';

        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('stmf-v-title').textContent = btn.getAttribute('data-title') || '';
                document.getElementById('stmf-v-desc').textContent = btn.getAttribute('data-description') || '';
                if (dialog.showModal) { dialog.showModal(); }
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function() { dialog.close(); });
        }

        var cardsWrap = document.querySelector('.task-cards');
        if (cardsWrap) {
            cardsWrap.addEventListener('click', function(e) {
                var toggleBtn = e.target.closest('.stm-task-toggle');
                if (!toggleBtn) {
                    return;
                }
                var card = toggleBtn.closest('.task-card');
                if (!card) {
                    return;
                }
                var isOpen = card.classList.toggle('is-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggleBtn.textContent = isOpen ? hideDetailsText : showDetailsText;
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
