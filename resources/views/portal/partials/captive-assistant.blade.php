{{-- Shared detection for the captive-network assistant (CNA) — the cut-down
     browser a phone pops up when it joins Wi-Fi and finds a portal.

     It matters because the CNA is disposable: the OS kills it the moment its
     own connectivity probe succeeds. Anything we navigate to inside it is
     thrown away, so the portal must not treat it like a normal browser.

     Include this in <head>. It is deliberately a plain inline script rather
     than part of app.js, for two reasons: @vite emits type="module", which is
     deferred and would run *after* the inline scripts in these views; and the
     html.is-cna class below has to be set before first paint so the CSS can
     branch without a flash of the wrong copy. --}}
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
