<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Authorizing - Lawa't Kape</title>
    <style>
        body { background-color: #FAF7F2; color: #4A3B32; font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .fixed-bg { position: fixed; inset: 0; z-index: 0; background: #3E2723; }
        .overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(2px); }
        .card { position: relative; z-index: 10; background: rgba(255,255,255,0.9); border-radius: 2.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.2); padding: 2.5rem; max-width: 24rem; width: 100%; text-align: center; }
        .spinner-container { position: relative; width: 5rem; height: 5rem; margin: 0 auto 2rem; }
        .spinner-bg { position: absolute; inset: 0; border: 4px solid #F0E6D2; border-radius: 9999px; }
        .spinner-active { position: absolute; inset: 0; border: 4px solid #3E2723; border-radius: 9999px; border-top-color: transparent; animation: spin 1s linear infinite; }
        .spinner-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; opacity: 0.5; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        h2 { font-size: 1.5rem; font-weight: 900; color: #3E2723; margin: 0 0 0.75rem; letter-spacing: -0.025em; }
        p { font-size: 0.875rem; color: #8D6E63; font-weight: 500; line-height: 1.625; margin: 0; }
        .footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #F0E6D2; opacity: 0.5; font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.3em; }
        .hidden { display: none; }
    </style>
</head>
<body onload="setTimeout(function(){ document.getElementById('unlock-form').submit(); }, 1500);">

    <!-- Background Overlay -->
    <div class="fixed-bg">
        <div class="overlay"></div>
    </div>

    <div class="card">
        
        <div class="spinner-container">
            <div class="spinner-bg"></div>
            <div class="spinner-active"></div>
            <div class="spinner-icon">📶</div>
        </div>

        <h2>Authenticating...</h2>
        <p>Verifying voucher and configuring firewall access for your device.</p>
        
        <div class="footer">
            Directing to Lawa't Core Gateway
        </div>
    </div>

    <!-- The critical hidden form that tells OPNsense to unlock this specific device -->
    <!-- OPNsense listens on port 8000 for standard HTTP captive portal authorization -->
    <form id="unlock-form" action="http://{{ $opnsenseIp }}:8000/" method="POST" class="hidden">
        <input type="hidden" name="zone" value="{{ $zone }}">
        <input type="hidden" name="accept" value="Continue">
        <input type="hidden" name="redirurl" value="http://neverssl.com">
    </form>

</body>
</html>
