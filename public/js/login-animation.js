(() => {
    const intro = document.querySelector('[data-login-intro]');
    const content = document.querySelector('[data-login-content]');
    if (!intro || !content) return;
    const video = intro.querySelector('[data-intro-video]');
    const skip = intro.querySelector('[data-skip-intro]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let seen = false;
    const storageKey = 'tmta-login-intro-v2-seen';
    try { seen = sessionStorage.getItem(storageKey) === '1'; } catch (_) {}
    if (seen || motion.matches || intro.dataset.skip === 'true') return;

    let finishing = false;
    let finished = false;
    let focusAfter = false;
    let timeout;
    let fadeTimeout;
    const complete = () => {
        if (finished) return;
        finished = true;
        clearTimeout(timeout);
        clearTimeout(fadeTimeout);
        video.pause();
        intro.hidden = true;
        content.inert = false;
        document.body.classList.remove('intro-running', 'intro-finishing');
        if (focusAfter) document.querySelector('#username, #password')?.focus({ preventScroll: true });
    };
    const finish = (focus = false, immediate = false) => {
        focusAfter ||= focus;
        if (finished) return;
        if (immediate || motion.matches) { complete(); return; }
        if (finishing) return;
        finishing = true;
        clearTimeout(timeout);
        // Keep the completed logo visible while the working form fades in underneath.
        video.pause();
        document.body.classList.add('intro-finishing');
        intro.classList.add('is-exiting');
        fadeTimeout = setTimeout(complete, 1150);
    };
    intro.hidden = false;
    content.inert = true;
    document.body.classList.add('intro-running');
    try { sessionStorage.setItem(storageKey, '1'); } catch (_) {}
    skip.addEventListener('click', () => finish(true));
    intro.addEventListener('transitionend', event => {
        if (event.target === intro && event.propertyName === 'opacity' && finishing) complete();
    });
    video.addEventListener('ended', () => finish());
    video.addEventListener('error', () => finish(false, true));
    // Crossfade from the completed logo before the video starts its own form transition.
    video.addEventListener('timeupdate', () => { if (video.currentTime >= 6.5) finish(); });
    motion.addEventListener('change', event => { if (event.matches) finish(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') finish(true); });
    timeout = setTimeout(() => finish(), 12000);
    // Keep the white intro visible until an actual frame replaces the browser's empty video surface.
    const revealVideo = () => { if (!finishing && !finished) video.classList.add('is-ready'); };
    if ('requestVideoFrameCallback' in video) {
        video.requestVideoFrameCallback(revealVideo);
    } else {
        video.addEventListener('loadeddata', () => requestAnimationFrame(revealVideo), { once: true });
    }
    video.muted = true;
    video.src = video.dataset.src;
    video.play()?.catch(() => finish(false, true));
})();
