(() => {
    const dialog = document.getElementById('authDialog');
    const frame = document.getElementById('authFrame');
    const closeButton = document.getElementById('authDialogClose');
    const title = document.getElementById('authDialogTitle');
    const panel = document.getElementById('authPanel');
    const fallbackLink = document.getElementById('authFullPageLink');
    const status = document.getElementById('authDialogStatus');
    const tabs = [...document.querySelectorAll('.auth-dialog-tabs [data-auth-mode]')];
    const tabsBar = document.querySelector('.auth-dialog-tabs');

    if (!dialog || !frame || !closeButton || !title || !panel || !fallbackLink || !status || !tabsBar || tabs.length !== 2) return;

    const documentRoot = document.documentElement;
    const scrollLockClass = 'auth-modal-open';
    let otpMode = false;

    const sources = {
        login: frame.dataset.loginSrc,
        register: frame.dataset.registerSrc,
        otp: frame.dataset.otpSrc
    };
    const loginUrl = new URL(sources.login, window.location.href);
    const appPath = loginUrl.pathname.split('/auth/')[0];

    function setOtpMode(active) {
        otpMode = active;
        dialog.classList.toggle('auth-dialog--otp-required', active);
        closeButton.hidden = active;
        tabsBar.hidden = active;
        if (active) {
            title.textContent = 'Verify it’s you';
            frame.title = 'Verify your login';
        }
    }

    function setMode(mode, reload = false, sourceOverride = null) {
        if (!Object.prototype.hasOwnProperty.call(sources, mode)) return;
        const source = sourceOverride || sources[mode];
        setOtpMode(false);

        tabs.forEach(tab => {
            const selected = tab.dataset.authMode === mode;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });

        panel.setAttribute('aria-labelledby', mode === 'register' ? 'authRegisterTab' : 'authLoginTab');
        title.textContent = mode === 'register' ? 'Create your account' : 'Log in to eRequest Mo';
        frame.title = mode === 'register' ? 'Create an eRequest Mo account' : 'Log in to eRequest Mo';
        fallbackLink.href = source;
        status.hidden = true;
        status.textContent = '';

        if (reload || frame.getAttribute('src') !== source) frame.src = source;
    }

    function showDialog(mode, sourceOverride = null) {
        if (typeof dialog.showModal !== 'function') {
            window.location.assign(new URL(sourceOverride || sources[mode], window.location.href).href);
            return;
        }
        setMode(mode, true, sourceOverride);
        if (sourceOverride) setOtpMode(true);
        if (!dialog.open) {
            dialog.showModal();
            documentRoot.classList.add(scrollLockClass);
        }
    }

    function openDialog(mode, sourceOverride = null) {
        const offcanvas = document.getElementById('offcanvasExample');
        if (offcanvas?.classList.contains('show') && window.bootstrap?.Offcanvas) {
            offcanvas.addEventListener('hidden.bs.offcanvas', () => showDialog(mode, sourceOverride), { once: true });
            window.bootstrap.Offcanvas.getOrCreateInstance(offcanvas).hide();
            return;
        }
        showDialog(mode, sourceOverride);
    }

    document.querySelectorAll('[data-auth-open]').forEach(trigger => {
        trigger.addEventListener('click', event => {
            const mode = trigger.dataset.authOpen;
            if (!Object.prototype.hasOwnProperty.call(sources, mode)) return;
            event.preventDefault();
            openDialog(mode);
        });
    });

    tabs.forEach(tab => {
        tab.addEventListener('click', () => setMode(tab.dataset.authMode, true));
        tab.addEventListener('keydown', event => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            const nextTab = tabs.find(candidate => candidate !== tab);
            setMode(nextTab.dataset.authMode, true);
            nextTab.focus();
        });
    });

    closeButton.addEventListener('click', () => {
        if (!otpMode) dialog.close();
    });
    dialog.addEventListener('close', () => documentRoot.classList.remove(scrollLockClass));
    dialog.addEventListener('click', event => {
        if (event.target === dialog && !otpMode) dialog.close();
    });
    dialog.addEventListener('cancel', event => {
        if (otpMode) event.preventDefault();
    });

    frame.addEventListener('load', () => {
        let loadedUrl;
        try {
            loadedUrl = new URL(frame.contentWindow.location.href);
        } catch {
            status.textContent = 'This sign-in step needs a full page. Use “Open full page” to continue securely.';
            status.hidden = false;
            return;
        }

        if (loadedUrl.origin !== window.location.origin) return;

        const path = loadedUrl.pathname.replace(/\/+$/, '');
        if (path.startsWith(`${appPath}/dashboards/`)) {
            window.location.assign(loadedUrl.href);
            return;
        }

        if (path === `${appPath}/auth/index3-verify-otp`) setOtpMode(true);
        else if (path === `${appPath}/auth/index3-registration`) setMode('register');
        else if (path === `${appPath}/auth/index3-login`) setMode('login');
    });

    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== frame.contentWindow) return;
        if (event.data?.type === 'erequest-auth-complete') {
            const dashboardPaths = new Set(
                ['resident', 'city_administrator', 'employee']
                    .map(role => `${appPath}/dashboards/${role}`)
            );
            const destination = event.data.destination;
            if (typeof destination !== 'string' || !dashboardPaths.has(destination)) return;
            window.location.replace(destination);
            return;
        }
        if (event.data?.type !== 'erequest-auth-mode') return;
        if (event.data.mode === 'login' || event.data.mode === 'register') setMode(event.data.mode, true);
    });

    if (dialog.dataset.pendingOtp === '1') openDialog('login', sources.otp);
})();
