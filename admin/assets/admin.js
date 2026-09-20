document.addEventListener('DOMContentLoaded', function () {
    const defaultSelect = document.querySelector('select[name="default_language"]');
    if (!defaultSelect) return;
    const syncDefault = () => {
        const value = defaultSelect.value;
        document.querySelectorAll('input[name="additional_languages[]"]').forEach((input) => {
            if (input.value === value) {
                input.checked = false;
                input.closest('.itkt-check-card').style.opacity = '.45';
                input.disabled = true;
            } else {
                input.disabled = false;
                input.closest('.itkt-check-card').style.opacity = '';
            }
        });
    };
    defaultSelect.addEventListener('change', syncDefault);
    syncDefault();
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.itkt-direction-control').forEach(function (control) {
        const auto = control.querySelector('input[type="checkbox"]');
        const select = control.querySelector('select');
        if (!auto || !select) return;
        const sync = function () {
            select.style.opacity = auto.checked ? '.55' : '1';
            select.title = auto.checked ? 'Bei Automatisch wird die Standardrichtung der Sprache verwendet.' : '';
        };
        auto.addEventListener('change', sync);
        sync();
    });
});

// V0.8.2 - resumable String Translation scanner.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ITKT_ADMIN === 'undefined' || !ITKT_ADMIN.ajaxUrl) return;

    const scanForms = Array.from(document.querySelectorAll('form')).filter(function (form) {
        const action = form.querySelector('input[name="action"]');
        return action && action.value === 'itkt_scan_strings';
    });
    if (!scanForms.length && !new URLSearchParams(window.location.search).get('scan_job')) return;

    let panel = null;
    let retryCount = 0;

    function ensurePanel() {
        if (panel) return panel;
        panel = document.createElement('div');
        panel.className = 'itkt-scan-progress';
        panel.innerHTML = '' +
            '<div class="itkt-scan-progress-head">' +
                '<span class="itkt-scan-spinner" aria-hidden="true"></span>' +
                '<div><strong>Texte werden schrittweise gescannt</strong><small>Du kannst diese Seite offen lassen. Große Themes verursachen dadurch keinen Gateway Timeout mehr.</small></div>' +
            '</div>' +
            '<div class="itkt-scan-progress-bar"><span></span></div>' +
            '<div class="itkt-scan-progress-stats">Scan wird vorbereitet …</div>';
        const target = document.querySelector('.itkt-wrap .itkt-topbar');
        if (target && target.parentNode) target.parentNode.insertBefore(panel, target.nextSibling);
        else document.body.appendChild(panel);
        return panel;
    }

    function setProgress(text, done) {
        const el = ensurePanel();
        const stats = el.querySelector('.itkt-scan-progress-stats');
        const bar = el.querySelector('.itkt-scan-progress-bar span');
        if (stats) stats.textContent = text;
        if (done) {
            el.classList.add('is-done');
            if (bar) bar.style.width = '100%';
        } else if (bar) {
            // Unknown total: animate a restrained activity bar instead of displaying a fake percentage.
            const current = parseInt(bar.dataset.step || '8', 10);
            const next = current >= 82 ? 18 : current + 9;
            bar.dataset.step = String(next);
            bar.style.width = next + '%';
        }
    }

    function post(data) {
        const body = new URLSearchParams();
        Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
        return fetch(ITKT_ADMIN.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        });
    }

    function processJob(jobId) {
        post({
            action: 'itkt_scan_strings_batch',
            nonce: ITKT_ADMIN.scanNonce,
            job_id: jobId
        }).then(function (json) {
            if (!json || !json.success) {
                throw new Error(json && json.data && json.data.message ? json.data.message : 'Scan konnte nicht fortgesetzt werden.');
            }
            retryCount = 0;
            const data = json.data || {};
            const stats = data.stats || {};
            const files = Number(stats.files || 0);
            const strings = Number(stats.strings || 0);
            const fresh = Number(stats.new || 0);
            const pending = Number(data.pending_files || 0) + Number(data.pending_dirs || 0);
            setProgress(files + ' Dateien geprüft · ' + strings + ' Textstellen gefunden · ' + fresh + ' neue Strings' + (pending ? ' · Scan läuft …' : ''), !!data.done);
            if (data.done) {
                window.setTimeout(function () { window.location.href = data.redirect || ITKT_ADMIN.stringsUrl; }, 450);
            } else {
                window.setTimeout(function () { processJob(jobId); }, 80);
            }
        }).catch(function (error) {
            retryCount++;
            if (retryCount <= 3) {
                setProgress('Kurze Serverunterbrechung – Scan wird automatisch fortgesetzt (' + retryCount + '/3) …', false);
                window.setTimeout(function () { processJob(jobId); }, 1200 * retryCount);
                return;
            }
            const el = ensurePanel();
            el.classList.add('is-error');
            const stats = el.querySelector('.itkt-scan-progress-stats');
            if (stats) stats.textContent = 'Scan unterbrochen: ' + error.message + '. Seite neu laden und den Scan erneut starten.';
            scanForms.forEach(function (form) {
                form.querySelectorAll('button,select').forEach(function (control) { control.disabled = false; });
            });
        });
    }

    scanForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;
            const typeInput = form.querySelector('input[name="source_type"]');
            const keyInput = form.querySelector('[name="source_key"]');
            const type = typeInput ? typeInput.value : '';
            const key = keyInput ? keyInput.value : '';
            if (!type || !key) return;

            scanForms.forEach(function (scanForm) {
                scanForm.querySelectorAll('button,select').forEach(function (control) { control.disabled = true; });
            });
            setProgress('Scan wird vorbereitet …', false);
            post({
                action: 'itkt_scan_strings_start',
                nonce: ITKT_ADMIN.scanNonce,
                source_type: type,
                source_key: key
            }).then(function (json) {
                if (!json || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : 'Scan konnte nicht gestartet werden.');
                processJob(json.data.job_id);
            }).catch(function (error) {
                const el = ensurePanel();
                el.classList.add('is-error');
                const stats = el.querySelector('.itkt-scan-progress-stats');
                if (stats) stats.textContent = 'Scan konnte nicht gestartet werden: ' + error.message;
                scanForms.forEach(function (scanForm) {
                    scanForm.querySelectorAll('button,select').forEach(function (control) { control.disabled = false; });
                });
            });
        });
    });

    // Fallback for a no-JS/form submission that was redirected into a resumable job.
    const existingJob = new URLSearchParams(window.location.search).get('scan_job');
    if (existingJob) {
        setProgress('Vorhandener Scan wird fortgesetzt …', false);
        processJob(existingJob);
    }
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.itkt-copy-shortcode').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-copy') || '';
            if (!value) return;

            const done = function () {
                const original = button.innerHTML;
                button.classList.add('is-copied');
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> Kopiert';
                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                    button.innerHTML = original;
                }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done).catch(function () {});
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                if (document.execCommand('copy')) done();
            } catch (e) {}
            document.body.removeChild(textarea);
        });
    });
});


document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('.itkt-switcher-device-tabs [data-itkt-device-tab]');
    var panels = document.querySelectorAll('.itkt-switcher-device-panel[data-itkt-device-panel]');
    if (!tabs.length || !panels.length) return;

    function activate(device) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-itkt-device-tab') === device);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-itkt-device-panel') === device);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(tab.getAttribute('data-itkt-device-tab'));
        });
    });
});
