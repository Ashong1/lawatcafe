<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Authorizing - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] min-h-screen flex items-center justify-center p-4 antialiased" 
      style="font-family: 'Montserrat', sans-serif;"
      onload="setTimeout(function(){ document.getElementById('unlock-form').submit(); }, 1500);">

    <!-- Background Image with Blur & Dark Overlay -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
    </div>

    <div class="relative z-10 bg-white/90 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/20 p-10 max-w-sm w-full text-center">
        
        <div class="relative w-20 h-20 mx-auto mb-8">
            <div class="absolute inset-0 border-4 border-[#F0E6D2] rounded-full"></div>
            <div class="absolute inset-0 border-4 border-[#3E2723] rounded-full border-t-transparent animate-spin"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <x-lucide-wifi class="w-6 h-6 text-[#8D6E63] animate-pulse" />
            </div>
        </div>

        <h2 class="text-2xl font-black text-[#3E2723] mb-3 tracking-tight">Authenticating...</h2>
        <p class="text-sm text-[#8D6E63] font-medium leading-relaxed">Verifying voucher and configuring firewall access for your device.</p>
        
        <div class="mt-8 pt-6 border-t border-[#F0E6D2] opacity-50">
            <p class="text-[9px] font-black uppercase tracking-[0.3em]">Directing to Lawa't Core Gateway</p>
        </div>
    </div>

    <!-- The critical hidden form that tells OPNsense to unlock this specific device -->
    <form id="unlock-form" action="http://{{ $opnsenseIp }}:8000/" method="POST" class="hidden">
        <input type="hidden" name="zone" value="{{ $zone }}">
        <input type="hidden" name="accept" value="Continue">
        <input type="hidden" name="redirurl" value="{{ route('portal.success') }}">
    </form>

</body>
</html>
