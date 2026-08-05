<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Opening your browser - Lawa't Kape</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
{{-- No @vite here on purpose. This page exists for a fraction of a second and
     its whole job is to navigate away; pulling in the full CSS/JS bundle would
     add a round trip to the one moment we are racing the OS for. Everything it
     needs is inline. --}}
<style>
    body {
        margin: 0;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #FAF7F2;
        color: #6D4C41;
        font-family: 'Montserrat', system-ui, -apple-system, sans-serif;
        text-align: center;
        padding: 2rem;
    }
    p {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        line-height: 1.8;
    }
    a { color: #3E2723; }
</style>
</head>
<body>

    <p>
        You're online.<br>Opening your browser&hellip;
        {{-- Visible only if both the intent and the fallback somehow fail to
             navigate, which would otherwise leave a blank-looking page. --}}
        <noscript><br><a href="{{ $statusUrl }}">Tap here to see your remaining time</a></noscript>
    </p>

    <script>
        (function () {
            var status = @json($statusUrl);
            var fallback = @json($fallbackUrl);

            // Ask Android to open the status page in whichever browser the guest
            // actually uses. S.browser_fallback_url is honoured by Chromium
            // WebViews that understand intent: but decline this particular one;
            // the timer below covers the WebViews that ignore the scheme
            // entirely and simply do nothing.
            var intent = 'intent://' + status.replace(/^https?:\/\//, '')
                + '#Intent;scheme=http;action=android.intent.action.VIEW'
                + ';S.browser_fallback_url=' + encodeURIComponent(status)
                + ';end';

            // If the intent is going to work, the OS takes over well within a
            // second. If it does not, we must still let the sign-in window close
            // the way it did before this page existed — reaching the
            // connectivity probe is what tells Android it is online, and a guest
            // stuck on a dead handoff screen would be worse than no handoff at
            // all.
            var handedOver = false;
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { handedOver = true; }
            });

            try {
                window.location.href = intent;
            } catch (e) {
                // Some WebViews throw rather than ignore an unknown scheme.
            }

            setTimeout(function () {
                if (!handedOver) {
                    window.location.replace(fallback);
                }
            }, 1200);
        })();
    </script>

</body>
</html>
