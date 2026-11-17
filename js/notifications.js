/*
 * ─────────────────────────────────────────────────────────────
 *  NOTIFICATION BELL — Real-time polling for in-app notifications
 *  Include this script on any dashboard page that has the bell markup.
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    // Use a more conservative poll interval and pause when tab is hidden.
    const POLL_INTERVAL = 60000; // 60 seconds

    const bell      = document.getElementById('notifBell');
    const badge     = document.getElementById('notifBadge');
    const dropdown  = document.getElementById('notifDropdown');
    const list      = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAll');

    if (!bell || !dropdown || !list) return;

    let isOpen = false;

    // ── Toggle dropdown ──
    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        isOpen = !isOpen;
        dropdown.classList.toggle('notif-open', isOpen);

        if (isOpen) fetchNotifications();
    });

    // ── Close on outside click ──
    document.addEventListener('click', (e) => {
        if (isOpen && !dropdown.contains(e.target) && !bell.contains(e.target)) {
            isOpen = false;
            dropdown.classList.remove('notif-open');
        }
    });

    // ── Mark all read ──
    const deleteAllBtn = document.getElementById('notifDeleteAll');

    if (markAllBtn) {
        markAllBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fetch('../action/mark_notifications_read', { method: 'POST' })
                .then(() => {
                    badge.style.display = 'none';
                    badge.textContent = '0';
                    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                });
        });
    }

    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fetch('../action/delete_all_notifications', { method: 'POST' })
                .then(() => {
                    badge.style.display = 'none';
                    badge.textContent = '0';
                    list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
                    updateDeleteAllVisibility();
                });
        });
    }

    // ── Fetch notifications ──
    function fetchNotifications() {
        fetch('../action/get_notifications')
            .then(r => r.json())
            .then(data => {
                // Update badge
                if (data.unread > 0) {
                    badge.textContent = data.unread > 99 ? '99+' : data.unread;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }

                // Render list
                if (data.items.length === 0) {
                    list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
                    updateDeleteAllVisibility();
                    return;
                }

                list.innerHTML = data.items.map(n => {
                    const ago = timeAgo(n.created_at);
                    const cls = n.is_read == 0 ? 'notif-item unread' : 'notif-item';
                    const deleteButton = String(n.id).startsWith('account_')
                        ? ''
                        : '<button class="notif-delete-btn" data-id="' + escapeHtml(String(n.id)) + '" aria-label="Dismiss notification">×</button>';
                    return `<div class="${cls}" data-id="${escapeHtml(String(n.id))}" data-link="${escapeHtml(n.link || '')}">
                        ${deleteButton}
                        <div class="notif-msg">${escapeHtml(n.message)}</div>
                        <div class="notif-time">${ago}</div>
                    </div>`;
                }).join('');

                // Click to mark read + navigate
                list.querySelectorAll('.notif-item').forEach(el => {
                    el.addEventListener('click', () => {
                        const id = el.dataset.id;
                        const link = el.dataset.link;

                        if (/^\d+$/.test(id)) {
                            fetch('../action/mark_notifications_read', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'id=' + encodeURIComponent(id),
                            });
                        } else if (id.startsWith('req_')) {
                            fetch('../action/mark_notifications_read', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'id=' + encodeURIComponent(id),
                            });
                        }

                        if (!id.startsWith('account_')) {
                            el.classList.remove('unread');
                            updateBadgeCount();
                        }

                        if (link) window.location.href = link;
                    });
                });

                list.querySelectorAll('.notif-delete-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const id = btn.dataset.id;
                        const item = btn.closest('.notif-item');
                        if (!id || !item) return;

                        const wasUnread = item.classList.contains('unread');

                        fetch('../action/delete_notification', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + id,
                        }).then(() => {
                            item.remove();
                            if (wasUnread) updateBadgeCount();
                            if (list.querySelectorAll('.notif-item').length === 0) {
                                list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
                            }
                            updateDeleteAllVisibility();
                        });
                    });
                });

                updateDeleteAllVisibility();
            })
            .catch(() => {}); // Silently fail on network errors
    }

    // ── Helper: time ago ──
    function timeAgo(dateStr) {
        const now  = new Date();
        const normalizedDate = /(?:Z|[+-]\d{2}:\d{2})$/i.test(dateStr)
            ? dateStr
            : dateStr.replace(' ', 'T') + 'Z';
        const then = new Date(normalizedDate);
        if (Number.isNaN(then.getTime())) return '';
        const diff = Math.floor((now - then) / 1000);

        if (diff < 60)   return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return then.toLocaleDateString();
    }

    function updateDeleteAllVisibility() {
        if (!deleteAllBtn) return;
        const hasItems = list.querySelectorAll('.notif-item').length > 0;
        deleteAllBtn.style.display = hasItems ? 'inline-flex' : 'none';
    }

    // ── Helper: update the badge count from unread items ─
    function updateBadgeCount() {
        const unreadCount = document.querySelectorAll('.notif-item.unread').length;
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // ── Helper: escape HTML ──
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Initial fetch + start polling ──
    function pollBadge() {
        fetch('../action/get_notifications')
            .then(r => r.json())
            .then(data => {
                if (data.unread > 0) {
                    badge.textContent = data.unread > 99 ? '99+' : data.unread;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }

    pollBadge();

    let pollTimer = null;
    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollBadge, POLL_INTERVAL);
    }
    function stopPolling() {
        if (!pollTimer) return;
        clearInterval(pollTimer);
        pollTimer = null;
    }

    // Pause polling when the document is hidden (saves battery/data on mobile)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopPolling(); else startPolling();
    });

    startPolling();
})();
