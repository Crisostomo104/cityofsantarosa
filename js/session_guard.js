/**
 * session_guard.js — Inactivity Timeout Guard
 * ─────────────────────────────────────────────
 * Tracks user activity (mouse, keyboard, scroll).
 * After INACTIVE_MS of no activity → shows a SweetAlert2 popup:
 *   • "Stay Logged In" → redirect to login page (re-authenticate)
 *   • "Logout"         → redirect to logout page
 * The popup itself has a 30-second countdown; if ignored → auto-logout.
 */

(function () {
    const INACTIVE_MS = 900_000;  // 30s inactivity before popup (change to 900_000 for 15min in production)
    const LOGIN_URL = '/cityofsantarosa/auth/login';
    const LOGOUT_URL = '/cityofsantarosa/auth/logout';

    let lastActivity = Date.now();
    let popupShown = false;
    let checkTimer = null;

    // ── Track any user activity ──────────────────────────────
    function resetActivity() {
        lastActivity = Date.now();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'click', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetActivity, { passive: true });
    });

    // ── Show the inactivity popup ────────────────────────────
    function showInactivePopup() {
        if (popupShown) return;
        popupShown = true;
        clearInterval(checkTimer); // stop checking while popup is open

        Swal.fire({
            icon: 'warning',
            title: 'Are you still there?',
            html: `
                <p style="margin-bottom:6px;">You've been inactive for a while.</p>
                <p style="color:#888;font-size:13px;">
                    Click <strong>Stay Logged In</strong> to continue
                    (you'll need to log in again), or <strong>Logout</strong> to end your session.
                </p>
            `,
            showCancelButton: true,
            confirmButtonText: 'Stay Logged In',
            cancelButtonText: 'Logout',
            confirmButtonColor: '#198754',
            cancelButtonColor: '#dc3545',
            allowOutsideClick: false,
            allowEscapeKey: false,
            timer: 30_000,   // auto-logout after 30s if no response
            timerProgressBar: true,
            didOpen: () => {
                const pb = Swal.getTimerProgressBar();
                if (pb) {
                    pb.style.backgroundColor = '#dc3545'; // Make progress bar red for urgency
                    pb.style.height = '7px';              // Make it slightly thicker to be more visible
                }
            }
        }).then(result => {
            if (result.isConfirmed) {
                // Re-authenticate
                window.location.href = LOGIN_URL;
            } else {
                // Dismissed, timed out, or clicked Logout → end session
                window.location.href = LOGOUT_URL;
            }
        });
    }

    // ── Poll every 5 seconds to see if inactivity threshold hit ──
    function checkInactivity() {
        if (!popupShown && (Date.now() - lastActivity) >= INACTIVE_MS) {
            showInactivePopup();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        checkTimer = setInterval(checkInactivity, 5_000);
    });

})();
