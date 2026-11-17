(() => {
    const navbar = document.querySelector('.legal-policy-navbar');
    if (!navbar) return;

    window.requestAnimationFrame(() => navbar.classList.add('legal-nav-ready'));

    let previousScrollY = window.scrollY;
    let scrollDistance = 0;
    let scrollTicking = false;
    const mobileNavbar = window.matchMedia('(max-width: 575.98px)');

    function updateNavbarVisibility() {
        const currentScrollY = window.scrollY;
        const directionDelta = currentScrollY - previousScrollY;

        if (mobileNavbar.matches || currentScrollY < 80 || navbar.matches(':focus-within')) {
            navbar.classList.remove('navbar--hidden');
            scrollDistance = 0;
        } else if (directionDelta !== 0) {
            scrollDistance += directionDelta;

            if (scrollDistance > 48 && currentScrollY > 120) {
                navbar.classList.add('navbar--hidden');
                scrollDistance = 0;
            } else if (scrollDistance < -24) {
                navbar.classList.remove('navbar--hidden');
                scrollDistance = 0;
            }
        }

        previousScrollY = currentScrollY;
        scrollTicking = false;
    }

    window.addEventListener('scroll', () => {
        if (scrollTicking) return;
        scrollTicking = true;
        window.requestAnimationFrame(updateNavbarVisibility);
    }, { passive: true });
})();
