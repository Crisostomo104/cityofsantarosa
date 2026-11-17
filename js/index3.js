(() => {
    const videoContainer = document.getElementById('promoVideo');
    const playerElement = document.getElementById('youtubePlayer');
    const surfaceButton = document.getElementById('videoSurfaceToggle');
    const videoFrame = document.querySelector('.hero-video-frame');
    const videoDialog = document.getElementById('videoExpandDialog');
    const videoDialogContent = document.getElementById('videoDialogContent');
    const videoDialogClose = document.getElementById('videoDialogClose');
    const soundButton = document.getElementById('videoSoundToggle');
    const volumeSlider = document.getElementById('videoVolume');
    const playButton = document.getElementById('videoPlayToggle');
    const videoError = document.getElementById('videoError');
    const videoId = videoContainer?.dataset.videoId;
    const videoFrameParent = videoFrame?.parentElement;
    const videoFrameNextSibling = videoFrame?.nextSibling;
    let player = null;
    let playerReady = false;
    let isPlaying = false;
    let isMuted = true;
    let volumeLevel = 0;
    let lastNonZeroVolume = 50;
    let scrollVolumeFactor = 1;
    let scrollVolumeTicking = false;
    let lastAppliedVolume = null;
    let lastAppliedMuted = null;
    let controlsHideTimer = null;

    function showVideoControls() {
        if (!videoFrame) return;
        window.clearTimeout(controlsHideTimer);
        videoFrame.classList.remove('video-controls-hidden');
        if (isPlaying && !videoFrame.matches(':focus-within')) {
            controlsHideTimer = window.setTimeout(() => {
                videoFrame.classList.add('video-controls-hidden');
            }, 2000);
        }
    }

    function updateVideoControlsVisibility() {
        if (!videoFrame) return;
        window.clearTimeout(controlsHideTimer);
        if (isPlaying && !videoFrame.matches(':focus-within')) {
            controlsHideTimer = window.setTimeout(() => {
                videoFrame.classList.add('video-controls-hidden');
            }, 2000);
        } else {
            videoFrame.classList.remove('video-controls-hidden');
        }
    }

    videoFrame?.addEventListener('pointermove', showVideoControls);
    videoFrame?.addEventListener('pointerenter', showVideoControls);
    videoFrame?.addEventListener('pointerleave', updateVideoControlsVisibility);
    videoFrame?.addEventListener('touchstart', showVideoControls, { passive: true });
    videoFrame?.addEventListener('focusin', () => {
        window.clearTimeout(controlsHideTimer);
        videoFrame.classList.remove('video-controls-hidden');
    });
    videoFrame?.addEventListener('focusout', () => window.setTimeout(updateVideoControlsVisibility, 0));

    function openVideoDialog() {
        if (!videoFrame || !videoDialog || !videoDialogContent || typeof videoDialog.showModal !== 'function') return;
        videoDialogContent.appendChild(videoFrame);
        videoDialog.showModal();
        videoDialogClose?.focus();
    }

    function restoreVideoFrame() {
        if (!videoFrame || !videoDialog || !videoDialogContent?.contains(videoFrame)) return;
        if (videoFrameParent) {
            videoFrameParent.insertBefore(
                videoFrame,
                videoFrameNextSibling?.parentElement === videoFrameParent ? videoFrameNextSibling : null
            );
        }
        if (playerReady) player.pauseVideo();
    }

    surfaceButton?.addEventListener('click', openVideoDialog);
    videoDialogClose?.addEventListener('click', () => videoDialog?.close());
    videoDialog?.addEventListener('click', event => {
        if (event.target === videoDialog) videoDialog.close();
    });
    videoDialog?.addEventListener('close', restoreVideoFrame);

    const sectionLinks = [...document.querySelectorAll('.section-nav a, .section-nav-mobile a')];
    const sectionTargets = ['home', 'about-system', 'how-it-works', 'faq']
        .map(id => document.getElementById(id))
        .filter(Boolean);

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll(
            '.hero-media, .hero-copy, .stats-section, #maincontent .row > [class*="col-"]'
        ).forEach((element, index) => {
            element.classList.add('ix3-enter');
            element.style.animationDelay = `${Math.min(index * 70, 420)}ms`;
        });
    }

    function setActiveSection(id) {
        sectionLinks.forEach(link => {
            const active = link.hash === `#${id}`;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }

    function navigateToSection(hash) {
        const target = document.querySelector(hash);
        if (!target) return;

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const navbar = document.querySelector('.navbar.bg-theme');
        const scrollOffset = (navbar?.offsetHeight ?? 68) + 28;
        const targetTop = target.getBoundingClientRect().top + window.scrollY - scrollOffset;
        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: reduceMotion ? 'auto' : 'smooth'
        });
        window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${hash}`);
        setActiveSection(hash.slice(1));
    }

    let sectionUpdateScheduled = false;
    function updateActiveSectionFromScroll() {
        if (sectionUpdateScheduled) return;
        sectionUpdateScheduled = true;

        window.requestAnimationFrame(() => {
            sectionUpdateScheduled = false;
            if (!sectionTargets.length) return;

            const navbar = document.querySelector('.navbar.bg-theme');
            const activationLine = (navbar?.offsetHeight ?? 68) + 36;
            let activeTarget = sectionTargets[0];
            for (const target of sectionTargets) {
                if (target.getBoundingClientRect().top > activationLine) break;
                activeTarget = target;
            }

            const pageAtBottom = window.scrollY + window.innerHeight
                >= document.documentElement.scrollHeight - 2;
            if (pageAtBottom) activeTarget = sectionTargets[sectionTargets.length - 1];
            setActiveSection(activeTarget.id);
        });
    }

    sectionLinks.forEach(link => {
        link.addEventListener('click', event => {
            const hash = link.hash;
            if (!hash || !document.querySelector(hash)) return;
            event.preventDefault();

            const openMenu = document.getElementById('offcanvasExample');
            if (openMenu?.classList.contains('show') && window.bootstrap?.Offcanvas) {
                openMenu.addEventListener('hidden.bs.offcanvas', () => navigateToSection(hash), { once: true });
                window.bootstrap.Offcanvas.getOrCreateInstance(openMenu).hide();
                return;
            }

            navigateToSection(hash);
        });
    });

    window.addEventListener('scroll', updateActiveSectionFromScroll, { passive: true });
    window.addEventListener('resize', updateActiveSectionFromScroll, { passive: true });
    updateActiveSectionFromScroll();

    function getEffectiveVideoVolume() {
        return Math.round(volumeLevel * scrollVolumeFactor);
    }

    function applyEffectiveVideoVolume() {
        if (!playerReady) return;

        const effectiveVolume = getEffectiveVideoVolume();
        const shouldMute = isMuted || effectiveVolume === 0;
        if (effectiveVolume !== lastAppliedVolume) {
            player.setVolume(effectiveVolume);
            lastAppliedVolume = effectiveVolume;
        }
        if (shouldMute !== lastAppliedMuted) {
            if (shouldMute) player.mute();
            else player.unMute();
            lastAppliedMuted = shouldMute;
        }
        updateVideoControls();
    }

    function updateVideoVolumeFromScroll() {
        if (scrollVolumeTicking) return;
        scrollVolumeTicking = true;

        window.requestAnimationFrame(() => {
            const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            scrollVolumeFactor = maxScroll > 0
                ? 1 - Math.min(1, Math.max(0, window.scrollY / maxScroll))
                : 1;
            scrollVolumeTicking = false;
            applyEffectiveVideoVolume();
        });
    }

    window.addEventListener('scroll', updateVideoVolumeFromScroll, { passive: true });
    window.addEventListener('resize', updateVideoVolumeFromScroll, { passive: true });
    updateVideoVolumeFromScroll();

    function updateVideoControls() {
        updateVideoControlsVisibility();
        const effectiveVolume = getEffectiveVideoVolume();
        const effectivelyMuted = isMuted || effectiveVolume === 0;
        if (playButton) {
            playButton.setAttribute('aria-pressed', String(isPlaying));
            playButton.setAttribute('aria-label', isPlaying ? 'Pause promotional video' : 'Play promotional video');
            playButton.innerHTML = isPlaying
                ? '<i class="bi bi-pause-fill" aria-hidden="true"></i>'
                : '<i class="bi bi-play-fill" aria-hidden="true"></i>';
        }

        if (soundButton) {
            soundButton.setAttribute('aria-pressed', String(!effectivelyMuted));
            soundButton.setAttribute('aria-label', effectivelyMuted ? 'Enable video sound' : 'Mute video sound');
            soundButton.innerHTML = effectivelyMuted
                ? '<i class="bi bi-volume-mute-fill" aria-hidden="true"></i>'
                : effectiveVolume < 50
                    ? '<i class="bi bi-volume-down-fill" aria-hidden="true"></i>'
                    : '<i class="bi bi-volume-up-fill" aria-hidden="true"></i>';
        }

        if (volumeSlider) {
            volumeSlider.value = String(volumeLevel);
            volumeSlider.style.setProperty('--video-volume-progress', `${volumeLevel}%`);
            const outputDescription = effectiveVolume !== volumeLevel
                ? `, output ${effectiveVolume}% while scrolling`
                : '';
            volumeSlider.setAttribute(
                'aria-valuetext',
                `${volumeLevel}% set${effectivelyMuted ? ', muted' : ''}${outputDescription}`
            );
        }
    }

    function togglePlayback() {
        if (!playerReady) return;
        if (isPlaying) {
            player.pauseVideo();
        } else {
            player.playVideo();
        }
    }

    function enableSoundFromVolumeGesture() {
        if (!playerReady) return;
        if (Number(volumeSlider?.value ?? volumeLevel) === 0) return;

        isMuted = false;
        const effectiveVolume = getEffectiveVideoVolume();
        player.setVolume(effectiveVolume);
        player.unMute();
        lastAppliedVolume = effectiveVolume;
        lastAppliedMuted = false;
        if (!isPlaying && effectiveVolume > 0) player.playVideo();
        updateVideoControls();
    }

    if (videoId && playerElement) {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        window.onYouTubeIframeAPIReady = () => {
            player = new window.YT.Player(playerElement, {
                videoId,
                playerVars: {
                    autoplay: prefersReducedMotion ? 0 : 1,
                    controls: 0,
                    loop: 1,
                    modestbranding: 1,
                    mute: 1,
                    playsinline: 1,
                    playlist: videoId,
                    rel: 0,
                    origin: window.location.origin
                },
                events: {
                    onReady: event => {
                        playerReady = true;
                        event.target.mute();
                        event.target.setVolume(volumeLevel);
                        isMuted = true;
                        applyEffectiveVideoVolume();
                        if (!prefersReducedMotion) event.target.playVideo();
                        updateVideoControls();
                    },
                    onStateChange: event => {
                        isPlaying = event.data === window.YT.PlayerState.PLAYING
                            || event.data === window.YT.PlayerState.BUFFERING;
                        updateVideoControls();
                    },
                    onError: event => {
                        console.error('YouTube promotional video failed to load:', event.data);
                        if (videoError) videoError.hidden = false;
                    }
                }
            });
        };

        const apiScript = document.createElement('script');
        apiScript.src = 'https://www.youtube.com/iframe_api';
        apiScript.async = true;
        apiScript.onerror = () => {
            console.error('Could not load the YouTube player API.');
            if (videoError) videoError.hidden = false;
        };
        document.head.appendChild(apiScript);

        playButton?.addEventListener('click', togglePlayback);
        soundButton?.addEventListener('click', () => {
            if (!playerReady) return;
            if (isMuted || getEffectiveVideoVolume() === 0) {
                volumeLevel = lastNonZeroVolume || 50;
                isMuted = false;
                if (!isPlaying) player.playVideo();
            } else {
                lastNonZeroVolume = volumeLevel;
                volumeLevel = 0;
                isMuted = true;
            }
            applyEffectiveVideoVolume();
        });

        volumeSlider?.addEventListener('pointerup', enableSoundFromVolumeGesture);
        volumeSlider?.addEventListener('keydown', event => {
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'].includes(event.key)) {
                enableSoundFromVolumeGesture();
            }
        });
        volumeSlider?.addEventListener('input', () => {
            if (!playerReady) return;
            volumeLevel = Number(volumeSlider.value);
            if (volumeLevel > 0) {
                lastNonZeroVolume = volumeLevel;
                isMuted = false;
                if (!isPlaying) player.playVideo();
            } else {
                isMuted = true;
            }
            applyEffectiveVideoVolume();
        });
    }

    const navbar = document.querySelector('.navbar.bg-theme');
    if (navbar) {
        let previousScrollY = window.scrollY;
        let scrollDistance = 0;
        let scrollTicking = false;
        const mobileNavbar = window.matchMedia('(max-width: 575.98px)');

        const updateNavbarVisibility = () => {
            const currentScrollY = window.scrollY;
            const directionDelta = currentScrollY - previousScrollY;
            const menuOpen = document.querySelector('.offcanvas.show');
            const videoOpen = videoDialog?.open;

            if (mobileNavbar.matches || currentScrollY < 80 || menuOpen || videoOpen || navbar.matches(':focus-within')) {
                navbar.classList.remove('navbar--hidden');
                scrollDistance = 0;
            } else if (Math.abs(directionDelta) > 0) {
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
        };

        window.addEventListener('scroll', () => {
            if (scrollTicking) return;
            scrollTicking = true;
            window.requestAnimationFrame(updateNavbarVisibility);
        }, { passive: true });
    }

    const cookieConsentButtons = [
        { id: 'acceptCookiesBtn', value: 'accepted' },
        { id: 'declineCookiesBtn', value: 'declined' }
    ];
    cookieConsentButtons.forEach(({ id, value }) => {
        const button = document.getElementById(id);
        if (button) {
            button.addEventListener('click', () => {
                const maxAge = 60 * 60 * 24 * 365;
                document.cookie = `ei_cookie_consent=${value}; max-age=${maxAge}; path=/; samesite=lax`;
                document.getElementById('cookieBanner')?.remove();
            });
        }
    });

    const backToTop = document.querySelector('.back-to-top');
    if (backToTop) {
        const updateBackToTop = () => {
            backToTop.style.display = window.scrollY > 300 ? 'flex' : 'none';
        };

        window.addEventListener('scroll', updateBackToTop, { passive: true });
        updateBackToTop();
    }
})();
