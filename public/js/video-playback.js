(() => {
    document.querySelectorAll('[data-er-video]').forEach(video => {
        if (video.dataset.trackingReady) return;
        video.dataset.trackingReady = '1';
        let pending = 0, previous = 0, lastClock = performance.now(), started = false, sending = false;
        const clientKey = 'er-video-client';
        let clientId = '';
        try {
            clientId = localStorage.getItem(clientKey) || (crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2)+Date.now());
            localStorage.setItem(clientKey, clientId);
        } catch (_) { clientId = ''; }
        const send = async (initial = false) => {
            if (sending || (!initial && pending < 1)) return;
            const seconds = initial ? 0 : Math.min(15, Math.floor(pending));
            pending -= seconds; sending = true;
            try {
                await fetch(video.dataset.trackUrl, {
                    method: 'POST', credentials: 'same-origin', keepalive: true,
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':video.dataset.csrf, ...(clientId ? {'X-Video-Client':clientId} : {})},
                    body: JSON.stringify({seconds})
                });
            } catch (_) { /* Playback remains available if analytics is offline. */ }
            finally { sending = false; }
        };
        video.addEventListener('play', () => {
            previous = video.currentTime; lastClock = performance.now();
            if (!started) { started = true; send(true); }
        });
        video.addEventListener('timeupdate', () => {
            const now = performance.now(), elapsed = (now-lastClock)/1000, delta = video.currentTime-previous;
            if (!video.paused && !video.seeking && delta > 0 && delta <= elapsed*2.5+0.5) pending += Math.min(delta,elapsed);
            previous = video.currentTime; lastClock = now;
            if (pending >= 10) send();
        });
        video.addEventListener('pause', () => send());
        video.addEventListener('ended', () => send());
        document.addEventListener('visibilitychange', () => { if (document.hidden) send(); });
    });
})();