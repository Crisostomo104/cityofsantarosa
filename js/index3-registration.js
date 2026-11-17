(() => {
    const form = document.getElementById('index3RegistrationForm');
    const dialog = document.getElementById('registrationOtpDialog');
    const otpInput = document.getElementById('registrationOtpInput');
    const status = document.getElementById('registrationOtpStatus');
    const verifyButton = document.getElementById('registrationOtpVerify');
    const resendButton = document.getElementById('registrationOtpResend');
    const idImageInput = document.getElementById('registrationIdImage');
    const idPreview = document.getElementById('registrationIdPreview');
    const idThumbnail = document.getElementById('registrationIdThumbnail');
    const idViewButton = document.getElementById('viewRegistrationId');
    const idPreviewDialog = document.getElementById('registrationIdPreviewDialog');
    const largeIdPreview = document.getElementById('registrationIdLargePreview');
    const idSuccessDialog = document.getElementById('idVerificationSuccessDialog');
    const countdown = document.getElementById('registrationOtpCountdown');
    if (!form || !dialog || !otpInput || !status || !verifyButton || !resendButton || !countdown) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let selectedIdUrl = '';
    let hasUnsavedChanges = false;
    let isSubmitting = false;
    let isVerifying = false;
    let isResending = false;
    let expiresAt = Number(dialog.dataset.expiresAt || 0);
    let resendAfter = Number(dialog.dataset.resendAfter || 0);
    let lockedUntil = Number(dialog.dataset.lockedUntil || 0);

    if (form) {
        form.addEventListener('input', () => { hasUnsavedChanges = true; });
        form.addEventListener('change', () => { hasUnsavedChanges = true; });
        window.addEventListener('beforeunload', event => {
            if (!hasUnsavedChanges || isSubmitting) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    if (idImageInput && idPreview && idThumbnail && idViewButton && idPreviewDialog && largeIdPreview) {
        idImageInput.addEventListener('change', () => {
            if (selectedIdUrl) URL.revokeObjectURL(selectedIdUrl);
            selectedIdUrl = '';

            const file = idImageInput.files?.[0];
            if (!file || !file.type.startsWith('image/')) {
                idPreview.hidden = true;
                return;
            }

            selectedIdUrl = URL.createObjectURL(file);
            idThumbnail.src = selectedIdUrl;
            largeIdPreview.src = selectedIdUrl;
            idPreview.hidden = false;
            hasUnsavedChanges = true;
        });

        idViewButton.addEventListener('click', () => {
            if (selectedIdUrl) idPreviewDialog.showModal();
        });
    }

    if (idSuccessDialog?.dataset.open === 'true' && typeof idSuccessDialog.showModal === 'function') {
        idSuccessDialog.showModal();
        idSuccessDialog.addEventListener('close', () => {
            if (dialog.dataset.open === 'true' && !dialog.open) {
                dialog.showModal();
                otpInput.focus();
            }
        }, { once: true });
        idSuccessDialog.querySelector('[data-id-verification-continue]')?.addEventListener('click', () => {
            idSuccessDialog.close();
        });
    } else if (dialog.dataset.open === 'true' && typeof dialog.showModal === 'function') {
        dialog.showModal();
        otpInput.focus();
    }

    form.addEventListener('submit', event => {
        const password = form.elements.namedItem('password');
        const confirmation = form.elements.namedItem('confirm_password');
        if (password.value !== confirmation.value) {
            event.preventDefault();
            confirmation.setCustomValidity('Passwords do not match.');
            confirmation.reportValidity();
            confirmation.addEventListener('input', () => confirmation.setCustomValidity(''), { once: true });
            return;
        }
        if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password.value)) {
            event.preventDefault();
            password.setCustomValidity('Use at least 8 characters with uppercase, lowercase, number, and symbol.');
            password.reportValidity();
            password.addEventListener('input', () => password.setCustomValidity(''), { once: true });
            return;
        }
        if (typeof window.grecaptcha === 'undefined' || !window.grecaptcha.getResponse()) {
            event.preventDefault();
            const alert = document.createElement('p');
            alert.className = 'auth-alert';
            alert.setAttribute('role', 'alert');
            alert.textContent = 'Please complete the reCAPTCHA check before creating your account.';
            form.querySelector('.auth-alert')?.remove();
            form.before(alert);
            return;
        }
        isSubmitting = true;
    });

    otpInput.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });

    function formatSeconds(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
    }

    function updateCountdown() {
        const now = Math.floor(Date.now() / 1000);
        const lockRemaining = Math.max(0, lockedUntil - now);
        const otpRemaining = Math.max(0, expiresAt - now);
        const resendRemaining = Math.max(0, resendAfter - now);

        if (lockRemaining > 0) {
            countdown.textContent = `Too many incorrect codes. You can request a new OTP in ${formatSeconds(lockRemaining)}.`;
            countdown.classList.add('is-locked');
            countdown.classList.remove('is-expired');
            verifyButton.disabled = true;
            resendButton.disabled = true;
            return;
        }

        countdown.classList.remove('is-locked');
        if (expiresAt && otpRemaining > 0) {
            countdown.textContent = `Code expires in ${formatSeconds(otpRemaining)}.`;
            countdown.classList.remove('is-expired');
        } else {
            countdown.textContent = 'This code has expired. Request a new code to continue.';
            countdown.classList.add('is-expired');
        }

        verifyButton.disabled = isVerifying || !expiresAt || otpRemaining <= 0;
        resendButton.disabled = isResending || resendRemaining > 0;
        if (resendRemaining > 0) {
            resendButton.textContent = `Resend code (${formatSeconds(resendRemaining)})`;
        } else {
            resendButton.textContent = 'Resend code';
        }
    }

    if (countdown) {
        updateCountdown();
        window.setInterval(updateCountdown, 1000);
    }

    async function postOtp(url, payload = {}) {
        const body = new URLSearchParams({ ...payload, csrf_token: csrfToken });
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': csrfToken
            },
            body
        });
        const result = await response.json();
        if (!response.ok && !result.message) throw new Error('Request failed. Please try again.');
        return result;
    }

    verifyButton.addEventListener('click', async () => {
        if (!/^\d{6}$/.test(otpInput.value)) {
            status.textContent = 'Enter the 6-digit verification code.';
            otpInput.focus();
            return;
        }

        isVerifying = true;
        verifyButton.disabled = true;
        status.textContent = 'Verifying your code…';
        try {
            const result = await postOtp('verify_otp', { otp: otpInput.value });
            if (!result.success) {
                status.textContent = result.message || 'The code could not be verified. Please try again.';
                if (result.locked_until) {
                    lockedUntil = Number(result.locked_until);
                    updateCountdown();
                }
                return;
            }

            status.textContent = 'Your account is verified. Continue to log in.';
            verifyButton.textContent = 'Continue to log in';
            verifyButton.disabled = false;
            resendButton.hidden = true;
            verifyButton.onclick = () => {
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'erequest-auth-mode', mode: 'login' }, window.location.origin);
                } else {
                    window.location.assign('index3-login');
                }
            };
        } catch (error) {
            status.textContent = error.message || 'Unable to verify your code right now.';
        } finally {
            isVerifying = false;
            updateCountdown();
        }
    });

    resendButton.addEventListener('click', async () => {
        isResending = true;
        resendButton.disabled = true;
        status.textContent = 'Sending a new code…';
        try {
            const result = await postOtp('resend_otp');
            if (!result.success) {
                status.textContent = result.message || 'Unable to send a new code.';
                if (result.retry_after) {
                    const retryAfter = Math.max(0, Number(result.retry_after));
                    lockedUntil = Number(result.locked_until || 0);
                    if (!lockedUntil) resendAfter = Math.floor(Date.now() / 1000) + retryAfter;
                    updateCountdown();
                }
                return;
            }
            status.textContent = result.message || 'A new code was sent to your phone.';
            expiresAt = Number(result.expires_at || 0);
            resendAfter = Math.floor(Date.now() / 1000) + 30;
            lockedUntil = 0;
            updateCountdown();
        } catch (error) {
            status.textContent = error.message || 'Unable to send a new code.';
        } finally {
            isResending = false;
            updateCountdown();
        }
    });
})();
