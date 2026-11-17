/**
 * Alert Auto-Dismiss
 * Automatically fades out and removes flash/action alerts after 10 seconds.
 * Targets: .alert-success, .alert-danger, .alert-info (flash messages only).
 * Skips persistent/structural alerts (e.g. "No employees found" placeholders).
 */
(function () {
    'use strict';

    var DISMISS_DELAY = 10000; // 10 seconds
    var REMOVE_DELAY  = 600;   // time for CSS fade-out animation to finish

    function autoDismissAlerts() {
        // Target only flash-type alerts (success, danger, info) inside main-content
        var alerts = document.querySelectorAll(
            '.main-content > .alert-success,' +
            '.main-content > .alert-danger,' +
            '.main-content > .alert-info,' +
            '.main-content .alert-success,' +
            '.main-content .alert-danger,' +
            '.main-content .alert-info'
        );

        alerts.forEach(function (alert) {
            // Skip alerts that are structural/permanent (contain tables, forms, links to panels, etc.)
            if (alert.querySelector('table, form')) return;
            // Skip "no data" placeholders
            if (alert.textContent.indexOf('No employee') >= 0 && alert.textContent.indexOf('Add New') >= 0) return;
            if (alert.textContent.indexOf('No users are currently') >= 0) return;
            // Skip announcement banners (they have their own dismiss button + are informational)
            if (alert.querySelector('[onclick*="showPanel"]')) return;
            // Skip already-processed alerts
            if (alert.dataset.autoDismiss === 'set') return;

            alert.dataset.autoDismiss = 'set';

            setTimeout(function () {
                // Don't dismiss if user is hovering over it
                if (alert.matches(':hover')) {
                    // Retry after another 5 seconds
                    setTimeout(function () {
                        dismissAlert(alert);
                    }, 5000);
                    return;
                }
                dismissAlert(alert);
            }, DISMISS_DELAY);
        });
    }

    function dismissAlert(alert) {
        if (!alert || !alert.parentNode) return;
        alert.classList.add('alert-autodismiss-out');
        setTimeout(function () {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, REMOVE_DELAY);
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoDismissAlerts);
    } else {
        autoDismissAlerts();
    }
})();
