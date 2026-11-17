<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PARTIAL: SCRIPTS — Common JS includes for all dashboards.
 *  Usage: <?php include __DIR__ . '/../partials/scripts.php'; ?>
 *
 *  Expected variables:
 *   $extra_js — Optional array of additional JS file paths
 * ─────────────────────────────────────────────────────────────
 */
$extra_js = $extra_js ?? [];
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/session_guard.js"></script>
<script src="../js/notifications.js"></script>
<?php foreach ($extra_js as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>

<!-- Shared: Mobile sidebar toggle -->
<script>
(function() {
    const btn = document.getElementById('hamburgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;

    function toggle() {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');
    }

    btn.addEventListener('click', toggle);
    if (overlay) overlay.addEventListener('click', toggle);

    sidebar.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('open');
            }
        });
    });
})();
</script>

<!-- Shared: Sidebar clock -->
<script>
function updateSidebarClock() {
    const el = document.getElementById('sidebarClock');
    if (!el) return;
    const now = new Date();
    const date = now.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    const time = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    el.textContent = `${date} ${time}`;
}
setInterval(updateSidebarClock, 1000);
window.addEventListener('DOMContentLoaded', updateSidebarClock);
</script>

<!-- Shared: CSRF-safe POST helper -->
<script>
function csrfPost(url, params = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = csrfMeta.content;
        form.appendChild(csrf);
    }

    for (const [key, value] of Object.entries(params)) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = key;
        inp.value = value;
        form.appendChild(inp);
    }

    document.body.appendChild(form);
    form.submit();
}
</script>
