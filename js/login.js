// =====================================================
// LOGIN PAGE JAVASCRIPT
// Purpose: Password toggle, forgot password modal, blocked popup
// =====================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Password visibility toggle ──
    const toggleBtn = document.getElementById('togglePassword');
    const pwInput   = document.getElementById('loginPassword');
    const eyeShow   = document.getElementById('eyeShow');
    const eyeHide   = document.getElementById('eyeHide');

    if (toggleBtn && pwInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            eyeShow.style.display = isPassword ? 'none' : 'block';
            eyeHide.style.display = isPassword ? 'block' : 'none';
            toggleBtn.title = isPassword ? 'Hide password' : 'Show password';
        });
    }

    // ── About the System Modal ──
    const aboutBtn       = document.getElementById('aboutSystemBtn');
    const aboutModal     = document.getElementById('aboutSystemModal');
    const closeAboutBtn  = document.getElementById('closeAboutModal');

    if (aboutBtn && aboutModal) {
        aboutBtn.addEventListener('click', e => {
            e.preventDefault();
            aboutModal.style.display = 'flex';
        });
    }

    if (closeAboutBtn && aboutModal) {
        closeAboutBtn.addEventListener('click', () => { aboutModal.style.display = 'none'; });
    }

    window.addEventListener('click', e => {
        if (e.target === aboutModal) aboutModal.style.display = 'none';
        if (e.target === document.getElementById('forgotPasswordModal')) {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }
    });

    // ── Forgot Password Modal ──
    const modal        = document.getElementById('forgotPasswordModal');
    const openBtn      = document.getElementById('forgotPasswordBtn');
    const closeBtn     = document.getElementById('closeModal');
    const form         = document.getElementById('forgotPasswordForm');
    const resetMessage = document.getElementById('resetMessage');

    if (openBtn && modal) {
        openBtn.addEventListener('click', e => {
            e.preventDefault();
            resetMessage.textContent = '';
            form.reset();
            modal.style.display = 'flex';
        });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    }

    // ── Forgot Password Form Submit (AJAX) ──
    if (form) {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const email = form.resetEmail.value.trim();
            if (!email) {
                resetMessage.style.color = 'red';
                resetMessage.textContent = 'Please enter your email address.';
                return;
            }
            resetMessage.style.color = 'black';
            resetMessage.textContent = 'Sending reset instructions...';
            try {
                const response = await fetch('forgot_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();
                resetMessage.style.color = result.success ? 'green' : 'red';
                resetMessage.textContent = result.message;
                if (result.success) setTimeout(() => { modal.style.display = 'none'; }, 3000);
            } catch (err) {
                resetMessage.style.color = 'red';
                resetMessage.textContent = 'An error occurred. Please try again later.';
            }
        });
    }

    // ── "Too many failed" auto-open forgot modal ──
    const showForgotLink = document.getElementById('showForgotModal');
    if (showForgotLink) {
        showForgotLink.addEventListener('click', e => {
            e.preventDefault();
            if (form) form.reset();
            if (resetMessage) resetMessage.textContent = '';
            // Pre-fill email from hidden data attribute
            const prefillEmail = showForgotLink.dataset.email || '';
            const emailInput = document.getElementById('resetEmail');
            if (emailInput && prefillEmail) emailInput.value = prefillEmail;
            if (modal) modal.style.display = 'flex';
        });
    }

    // ── Already-logged-in-on-another-device popup ──
    const isBlocked = document.body.dataset.blocked === '1';
    if (isBlocked && typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Already Logged In',
            html: '<p>This account is currently active on <strong>another device or browser</strong>.</p>' +
                  '<p style="color:#888;font-size:13px;margin-top:8px;">You can only be logged in on <strong>one device at a time</strong>.<br>Please log out from the other device first, then try again.</p>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545',
            allowOutsideClick: false,
        });
    }

    // ── reCAPTCHA check before submit ──
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            const captchaResponse = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';
            if (!captchaResponse || captchaResponse.length === 0) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Captcha required',
                        text: 'Please check "I\'m not a robot" before logging in.',
                        confirmButtonColor: '#C62828'
                    });
                } else {
                    alert('Please check "I\'m not a robot" before logging in.');
                }
            }
        });
    }

});