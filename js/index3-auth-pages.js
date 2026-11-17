(() => {
    const siteNavbar = document.querySelector('.auth-site-navbar');
    if (siteNavbar && !document.documentElement.classList.contains('auth-embedded')) {
        let previousScrollY = window.scrollY;
        let scrollDistance = 0;
        let scrollTicking = false;
        const mobileNavbar = window.matchMedia('(max-width: 575.98px)');

        const updateNavbarVisibility = () => {
            const currentScrollY = window.scrollY;
            const directionDelta = currentScrollY - previousScrollY;
            const menuOpen = document.querySelector('.auth-site-offcanvas.show');

            if (
                mobileNavbar.matches
                || currentScrollY < 80
                || menuOpen
                || siteNavbar.matches(':focus-within')
            ) {
                siteNavbar.classList.remove('navbar--hidden');
                scrollDistance = 0;
            } else if (directionDelta !== 0) {
                scrollDistance += directionDelta;

                if (scrollDistance > 48 && currentScrollY > 120) {
                    siteNavbar.classList.add('navbar--hidden');
                    scrollDistance = 0;
                } else if (scrollDistance < -24) {
                    siteNavbar.classList.remove('navbar--hidden');
                    scrollDistance = 0;
                }
            }

            previousScrollY = currentScrollY;
            scrollTicking = false;
        };

        window.addEventListener('scroll', () => {
            if (scrollTicking) return;
            scrollTicking = true;
            window.requestAnimationFrame(updateNavbarVisibility);
        }, { passive: true });

        window.addEventListener('resize', () => {
            if (mobileNavbar.matches) {
                siteNavbar.classList.remove('navbar--hidden');
                scrollDistance = 0;
            }
        }, { passive: true });
    }

    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            if (!(input instanceof HTMLInputElement)) return;

            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.setAttribute('aria-label', `${showPassword ? 'Hide' : 'Show'} password`);
            button.setAttribute('aria-pressed', String(showPassword));
        });
    });

    document.querySelectorAll('.auth-captcha').forEach(container => {
        const widget = container.querySelector('.g-recaptcha');
        if (!widget) return;

        const fitCaptcha = () => {
            const widgetWidth = widget.offsetWidth;
            const availableWidth = container.clientWidth;
            if (!widgetWidth || !availableWidth) return;

            const scale = Math.min(1, availableWidth / widgetWidth);
            container.style.setProperty('--auth-captcha-scale', String(scale));
        };

        fitCaptcha();
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(fitCaptcha);
            observer.observe(container);
            observer.observe(widget);
        } else {
            window.addEventListener('resize', fitCaptcha, { passive: true });
        }
    });

    const loginForm = document.getElementById('index3LoginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', event => {
            if (typeof window.grecaptcha !== 'undefined' && window.grecaptcha.getResponse()) return;
            event.preventDefault();
            const alert = document.createElement('p');
            alert.className = 'auth-alert';
            alert.setAttribute('role', 'alert');
            alert.textContent = 'Please complete the reCAPTCHA check before logging in.';
            loginForm.querySelector('.auth-alert')?.remove();
            loginForm.before(alert);
        });
    }

    const modal = document.getElementById('forgotPasswordModal');
    const modalOpenButton = document.getElementById('forgotPasswordBtn');
    const modalCloseButtons = document.querySelectorAll('[data-auth-modal-close]');
    const modalForm = document.getElementById('forgotPasswordForm');
    const modalMessage = document.getElementById('forgotPasswordMessage');
    const modalSubmitButton = modalForm?.querySelector('button[type="submit"]');

    const openForgotPasswordModal = () => {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('auth-modal-open');
        const emailInput = document.getElementById('forgotPasswordEmail');
        if (emailInput) {
            emailInput.focus();
        }
    };

    const closeForgotPasswordModal = () => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('auth-modal-open');
        if (modalMessage) {
            modalMessage.textContent = '';
            modalMessage.classList.remove('is-error', 'is-success');
        }
        if (modalForm) modalForm.reset();
        modalOpenButton?.focus();
    };

    if (modalOpenButton) {
        modalOpenButton.addEventListener('click', event => {
            event.preventDefault();
            openForgotPasswordModal();
        });
    }

    modalCloseButtons.forEach(button => button.addEventListener('click', closeForgotPasswordModal));

    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                closeForgotPasswordModal();
            }
        });
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeForgotPasswordModal();
        }
    });

    if (modalForm) {
        modalForm.addEventListener('submit', async event => {
            event.preventDefault();
            const emailInput = document.getElementById('forgotPasswordEmail');
            const email = emailInput ? emailInput.value.trim() : '';

            if (!email) {
                if (modalMessage) {
                    modalMessage.textContent = 'Please enter your email address.';
                    modalMessage.classList.add('is-error');
                    modalMessage.classList.remove('is-success');
                }
                return;
            }
            if (emailInput instanceof HTMLInputElement && !emailInput.checkValidity()) {
                emailInput.reportValidity();
                return;
            }

            if (modalMessage) {
                modalMessage.textContent = 'Sending reset instructions...';
                modalMessage.classList.remove('is-error', 'is-success');
            }
            if (modalSubmitButton) modalSubmitButton.disabled = true;

            try {
                const response = await fetch('../auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();

                if (modalMessage) {
                    modalMessage.textContent = result.message || (response.ok ? 'Request sent.' : 'Unable to send reset instructions.');
                    modalMessage.classList.toggle('is-error', !result.success);
                    modalMessage.classList.toggle('is-success', Boolean(result.success));
                }

                if (result.success) {
                    setTimeout(closeForgotPasswordModal, 2200);
                }
            } catch {
                if (modalMessage) {
                    modalMessage.textContent = 'Something went wrong. Please try again later.';
                    modalMessage.classList.add('is-error');
                    modalMessage.classList.remove('is-success');
                }
            } finally {
                if (modalSubmitButton) modalSubmitButton.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-auth-switch]').forEach(link => {
        link.addEventListener('click', event => {
            if (window.parent === window) return;
            event.preventDefault();
            window.parent.postMessage({
                type: 'erequest-auth-mode',
                mode: link.dataset.authSwitch
            }, window.location.origin);
        });
    });
})();
