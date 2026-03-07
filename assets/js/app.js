/**
 * نظام إدارة الزكاة والصدقات
 * Main Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Sidebar Toggle ───────────────────────────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const wrapper       = document.getElementById('wrapper');

    if (sidebarToggle && wrapper) {
        // Restore state from localStorage
        if (localStorage.getItem('sidebarToggled') === 'true') {
            wrapper.classList.add('toggled');
        }
        sidebarToggle.addEventListener('click', function () {
            wrapper.classList.toggle('toggled');
            localStorage.setItem('sidebarToggled', wrapper.classList.contains('toggled'));
        });
    }

    // ─── Auto-dismiss Alerts ──────────────────────────────────────────────────
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alertEl) {
        setTimeout(function () {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            } catch (_) {
                alertEl.style.display = 'none';
            }
        }, 4500);
    });

    // ─── Delete Confirmation Modal ────────────────────────────────────────────
    document.querySelectorAll('.btn-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id   = this.dataset.id   || '';
            const name = this.dataset.name || '';

            const deleteIdEl   = document.getElementById('deleteId');
            const deleteNameEl = document.getElementById('deleteName');

            if (deleteIdEl)   deleteIdEl.value       = id;
            if (deleteNameEl) deleteNameEl.textContent = name;
        });
    });

    // ─── Edit Modal: fill fields from data-record JSON ────────────────────────
    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let record;
            try {
                record = JSON.parse(this.dataset.record || '{}');
            } catch (_) {
                console.warn('Invalid JSON in data-record attribute');
                return;
            }

            Object.keys(record).forEach(function (key) {
                const el = document.getElementById('edit_' + key);
                if (!el) return;

                const tag = el.tagName.toLowerCase();
                const val = record[key] !== null ? record[key] : '';

                if (tag === 'select') {
                    // Set selected option by value
                    for (let i = 0; i < el.options.length; i++) {
                        if (el.options[i].value == val) {
                            el.selectedIndex = i;
                            break;
                        }
                    }
                } else if (tag === 'textarea') {
                    el.value = val;
                } else {
                    el.value = val;
                }
            });
        });
    });

    // ─── Confirm Delete with Enter key ────────────────────────────────────────
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                const form = deleteModal.querySelector('form');
                if (form) form.submit();
            }
        });
    }

    // ─── Highlight active tab from URL hash ───────────────────────────────────
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector('[data-bs-target="' + hash + '"]');
        if (tab) {
            try {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            } catch (_) {}
        }
    }

    // ─── Restore active tab on page reload ───────────────────────────────────
    const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
    const savedTab = localStorage.getItem('activeReportTab');

    if (savedTab) {
        const toRestore = document.querySelector('[data-bs-target="' + savedTab + '"]');
        if (toRestore) {
            try {
                bootstrap.Tab.getOrCreateInstance(toRestore).show();
            } catch (_) {}
        }
    }

    tabLinks.forEach(function (link) {
        link.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeReportTab', e.target.dataset.bsTarget || '');
        });
    });

});
