{{-- Detects the captive-network assistant (CNA): the throwaway mini-browser a
     phone opens on joining Wi-Fi, which the OS kills as soon as its own probe
     succeeds — so the portal must not treat it like a normal browser.

     Include in <head> as a plain inline script, not app.js: @vite emits a
     deferred module that runs after these views' inline scripts, and
     html.is-cna must be set before first paint so the CSS branches without a
     flash of the wrong copy. --}}
<script>
    window.isCaptiveAssistant = function () {
        const ua = navigator.userAgent || '';

        // iOS shows the portal in a stripped WebView that keeps the device
        // token but drops the "Safari" / "Version/" pair real Safari sends.
        const ios = /iPhone|iPad|iPod/.test(ua) && !/Safari/.test(ua);

        // Android's CaptivePortalLogin is an ordinary WebView, and every
        // Android WebView tags itself "; wv)". Its UA otherwise still contains
        // both Chrome and Safari — which is why checking for the absence of
        // those (the old test) never matched a single Android device.
        const android = /Android/.test(ua) && /;\s*wv\)/.test(ua);

        return ios || android || /CaptiveNetworkSupport/.test(ua);
    };

    if (window.isCaptiveAssistant()) {
        document.documentElement.classList.add('is-cna');
    }
</script>
<style>
    /* Default to the ordinary-browser copy; swap only inside the assistant.
       Kept here rather than in app.css so the rule ships with the class that
       drives it and needs no asset rebuild to stay correct. */
    .cna-only { display: none; }
    html.is-cna .cna-only { display: block; }
    html.is-cna .browser-only { display: none; }
</style>
<script>
    // Some phones (Xiaomi's sign-in window and browser among them) wrap the
    // page in a native pull-to-refresh layer that takes every downward swipe
    // while the PAGE is at its top. It can't see that the menu or chat panel
    // inside is scrolled down, so the guest can scroll down but never back up.
    // Keeping the page itself 1px below its top lets those swipes reach the panel.
    (function () {
        var root = document.documentElement;
        root.style.minHeight = 'calc(100vh + 1px)';
        function nudge() {
            var page = document.scrollingElement || root;
            if (page.scrollTop < 1) window.scrollTo(0, 1);
        }
        window.addEventListener('load', nudge);
        window.addEventListener('touchstart', nudge, { passive: true });
    })();
</script>
