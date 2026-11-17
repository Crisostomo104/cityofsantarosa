(() => {
    if (window.self !== window.top) {
        document.querySelectorAll('input[name="auth_modal"]').forEach(input => {
            input.value = '1';
        });
    }

    const otpInput = document.querySelector('.verify-otp-code');
    otpInput?.addEventListener('input', () => {
        const digitsOnly = otpInput.value.replace(/[^0-9]/g, '').slice(0, 6);
        if (otpInput.value !== digitsOnly) otpInput.value = digitsOnly;
    });

    const resendButton = document.querySelector('[data-otp-resend]');
    const resendLabel = resendButton?.querySelector('[data-otp-resend-label]');
    if (!resendButton || !resendLabel) return;

    const expiresAt = Number(resendButton.dataset.otpExpiresAt || 0);

    function updateResendCountdown() {
        const remaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
        resendButton.disabled = remaining > 0;

        if (!remaining) {
            resendLabel.textContent = 'Send a new code';
            return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        resendLabel.textContent = `Send a new code (${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')})`;
    }

    updateResendCountdown();
    window.setInterval(updateResendCountdown, 1000);
})();
