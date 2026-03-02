/* FB CAPI & Pixel Manager — Admin JS */
/* globals fbCapiAdmin */

// ── Tab switching ─────────────────────────────────────────────────────────────

function fbcTab(id, el) {
    document.querySelectorAll('.fbc-panel').forEach(function (p) {
        p.classList.remove('active');
    });
    document.querySelectorAll('.fbc-tab').forEach(function (t) {
        t.classList.remove('active');
    });
    document.getElementById('panel-' + id).classList.add('active');
    el.classList.add('active');
}

// ── Log refresh (AJAX) ────────────────────────────────────────────────────────

function fbcRefreshLogs() {
    var btn       = document.getElementById('fbc-refresh-btn');
    var icon      = document.getElementById('fbc-refresh-icon');
    var container = document.getElementById('fbc-logs-container');

    if (!btn || !container) return;

    btn.disabled       = true;
    btn.style.opacity  = '0.7';
    icon.style.transform = 'rotate(360deg)';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', fbCapiAdmin.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        btn.disabled      = false;
        btn.style.opacity = '1';
        setTimeout(function () { icon.style.transform = 'rotate(0deg)'; }, 300);

        if (xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && resp.data.html) {
                    container.innerHTML = resp.data.html;
                    // Re-attach filter listener after DOM replacement.
                    fbcBindFilter();
                }
            } catch (e) {
                console.error('[FB CAPI] Erreur parsing AJAX:', e);
            }
        }
    };

    xhr.send('action=fb_capi_refresh_logs&_ajax_nonce=' + fbCapiAdmin.nonce);
}

// ── Log filter ────────────────────────────────────────────────────────────────

function fbcFilterLogs(value) {
    var rows = document.querySelectorAll('#fbc-logs-table tbody tr');
    rows.forEach(function (row) {
        var ev = row.getAttribute('data-event');
        var st = row.getAttribute('data-status');
        if (value === 'all') {
            row.style.display = '';
        } else if (value === 'error') {
            row.style.display = (st !== 'success') ? '' : 'none';
        } else {
            row.style.display = (ev === value) ? '' : 'none';
        }
    });
}

function fbcBindFilter() {
    var filter = document.getElementById('fbc-log-filter');
    if (filter) {
        filter.addEventListener('change', function () {
            fbcFilterLogs(this.value);
        });
    }
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    fbcBindFilter();
    fbcRefreshLogs();
});
