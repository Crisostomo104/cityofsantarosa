/**
 * heartbeat.js — Keeps users.last_active fresh while the tab is open
 * ─────────────────────────────────────────────────────────────────
 * Pings heartbeat.php every 30s, but only while the tab is visible
 * and the user has been active recently (reuses the same idle logic
 * spirit as session_guard.js). Stops immediately when the tab is
 * closed or hidden — no request = last_active goes stale = the next
 * login attempt on another device can correctly treat this session
 * as dead after SESSION_LIVE_SECONDS.
 */
(function () {
    const HEARTBEAT_URL = '/cityofsantarosa/action/heartbeat';
    const PING_MS = 30_000; // every 30s

    let pingTimer = null;

    function sendHeartbeat() {
        // Don't ping a hidden/backgrounded tab — closing/backgrounding
        // should let last_active go stale, not stay artificially fresh.
        if (document.visibilityState !== 'visible') return;

        fetch(HEARTBEAT_URL, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
        }).catch(() => {
            // Silent fail — network hiccup shouldn't disrupt the page.
            // If the session is truly gone, check_login_redirect() on
            // the next page load will handle it.
        });
    }

    function start() {
        if (pingTimer) return;
        sendHeartbeat(); // immediate ping on load
        pingTimer = setInterval(sendHeartbeat, PING_MS);
    }

    function stop() {
        if (pingTimer) {
            clearInterval(pingTimer);
            pingTimer = null;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sendHeartbeat();
            start();
        } else {
            stop();
        }
    });

    document.addEventListener('DOMContentLoaded', start);
})();