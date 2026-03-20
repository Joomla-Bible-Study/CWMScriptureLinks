/**
 * CWM Scripture Library - Translations Manager
 *
 * @copyright  (C) 2026 CWM Team
 * @license    GNU General Public License version 2 or later
 */
((Joomla) => {
    'use strict';

    if (!Joomla) {
        return;
    }

    const options = Joomla.getOptions('cwmscripture.manager', {});
    const ajaxUrl = options.ajaxUrl || '';
    const token = options.token || '';

    function showStatus(message, type) {
        const el = document.getElementById('cwm-tm-status');
        if (!el) return;
        el.className = 'alert alert-' + type;
        el.textContent = message;
        el.style.display = 'block';
        if (type === 'success') {
            setTimeout(() => { el.style.display = 'none'; }, 5000);
        }
    }

    function setRowLoading(abbreviation, loading) {
        const row = document.getElementById('cwm-tm-row-' + abbreviation);
        if (!row) return;
        const btns = row.querySelectorAll('.cwm-tm-actions button');
        btns.forEach(btn => {
            btn.disabled = loading;
        });
        if (loading) {
            row.classList.add('cwm-tm-loading');
        } else {
            row.classList.remove('cwm-tm-loading');
        }
    }

    function updateRow(abbreviation, data) {
        const row = document.getElementById('cwm-tm-row-' + abbreviation);
        if (!row) return;

        const badge = row.querySelector('.cwm-tm-status-badge');
        if (badge) {
            if (data.installed) {
                badge.className = 'cwm-tm-status-badge badge bg-success';
                badge.textContent = Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_INSTALLED') || 'Installed';
            } else {
                badge.className = 'cwm-tm-status-badge badge bg-secondary';
                badge.textContent = Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_AVAILABLE') || 'Available';
            }
        }

        const versesCell = row.querySelector('.cwm-tm-verses');
        if (versesCell && data.verse_count !== undefined) {
            versesCell.textContent = data.verse_count > 0
                ? Number(data.verse_count).toLocaleString()
                : '—';
        }

        const sizeCell = row.querySelector('.cwm-tm-size');
        if (sizeCell && data.data_size !== undefined) {
            sizeCell.textContent = data.data_size > 0
                ? formatBytes(data.data_size)
                : '—';
        }

        // Rebuild action buttons
        const actionsCell = row.querySelector('.cwm-tm-actions');
        if (!actionsCell) return;

        actionsCell.innerHTML = '';
        const isCore = ['kjv', 'web'].includes(abbreviation.toLowerCase());

        if (data.installed) {
            const refreshBtn = document.createElement('button');
            refreshBtn.type = 'button';
            refreshBtn.className = 'btn btn-sm btn-outline-primary cwm-tm-btn-refresh';
            refreshBtn.dataset.abbreviation = abbreviation;
            refreshBtn.innerHTML = '<span class="icon-loop" aria-hidden="true"></span> '
                + (Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_REFRESH') || 'Refresh');
            actionsCell.appendChild(refreshBtn);

            if (!isCore) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger cwm-tm-btn-remove ms-1';
                removeBtn.dataset.abbreviation = abbreviation;
                removeBtn.innerHTML = '<span class="icon-trash" aria-hidden="true"></span> '
                    + (Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_REMOVE') || 'Remove');
                actionsCell.appendChild(removeBtn);
            }
        } else {
            const downloadBtn = document.createElement('button');
            downloadBtn.type = 'button';
            downloadBtn.className = 'btn btn-sm btn-success cwm-tm-btn-download';
            downloadBtn.dataset.abbreviation = abbreviation;
            downloadBtn.innerHTML = '<span class="icon-download" aria-hidden="true"></span> '
                + (Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_DOWNLOAD') || 'Download');
            actionsCell.appendChild(downloadBtn);
        }
    }

    function formatBytes(bytes) {
        bytes = parseInt(bytes, 10);
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    async function doAction(action, abbreviation) {
        setRowLoading(abbreviation, true);
        showStatus(
            (Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_WORKING') || 'Working...') + ' ' + abbreviation.toUpperCase(),
            'info'
        );

        const formData = new URLSearchParams();
        formData.append('action', action);
        formData.append('abbreviation', abbreviation);
        formData.append(token, '1');

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
            });

            const result = await response.json();
            setRowLoading(abbreviation, false);

            // com_ajax wraps plugin results in { success: true, data: [ ...results ] }
            if (result.success && result.data) {
                // data is an array of results from all responding plugins; ours is first
                const d = Array.isArray(result.data) ? result.data[0] : result.data;
                showStatus(d.message || 'Done', 'success');
                updateRow(abbreviation, d);
            } else {
                const msg = (result.message) || 'Unknown error';
                showStatus(msg, 'danger');
            }
        } catch (err) {
            setRowLoading(abbreviation, false);
            showStatus(err.message || 'Request failed', 'danger');
        }
    }

    document.addEventListener('click', (e) => {
        const downloadBtn = e.target.closest('.cwm-tm-btn-download');
        if (downloadBtn) {
            e.preventDefault();
            doAction('download', downloadBtn.dataset.abbreviation);
            return;
        }

        const refreshBtn = e.target.closest('.cwm-tm-btn-refresh');
        if (refreshBtn) {
            e.preventDefault();
            doAction('refresh', refreshBtn.dataset.abbreviation);
            return;
        }

        const removeBtn = e.target.closest('.cwm-tm-btn-remove');
        if (removeBtn) {
            e.preventDefault();
            const abbr = removeBtn.dataset.abbreviation;
            const confirmMsg = Joomla.Text._('PLG_CONTENT_SCRIPTURELINKS_TM_CONFIRM_REMOVE') ||
                'Remove this translation? All locally stored verses will be deleted.';
            if (confirm(confirmMsg)) {
                doAction('remove', abbr);
            }
        }
    });

})(Joomla);
