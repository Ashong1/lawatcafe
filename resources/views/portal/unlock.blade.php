<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Authorizing - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased overflow-hidden flex items-center justify-center p-4 lg:p-8" 
      style="font-family: 'Montserrat', sans-serif;"
      onload="setTimeout(function(){ document.getElementById('unlock-form').submit(); }, 2000);">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-[20s] hover:scale-105" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-2xl rounded-[2.5rem] shadow-2xl border border-[#E6D5C3] overflow-hidden bg-[#FAF7F2] flex flex-col p-10 lg:p-16 transition-all duration-500 text-center my-4">

        <div class="relative w-24 h-24 mx-auto mb-10">
            <div class="absolute inset-0 border-4 border-[#F0E6D2] rounded-full"></div>
            <div class="absolute inset-0 border-4 border-[#3E2723] rounded-full border-t-transparent animate-spin"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <x-lucide-wifi class="w-8 h-8 text-[#8D6E63] animate-pulse" />
            </div>
        </div>

        <h2 class="text-3xl font-black text-[#3E2723] mb-4 tracking-tight">Authenticating...</h2>
        <p class="text-base text-[#8D6E63] font-medium leading-relaxed max-w-md mx-auto">Verifying voucher and configuring firewall access for your device. Please do not close this window.</p>
        
        <div class="mt-12 pt-8 border-t border-[#F0E6D2] opacity-50">
            <p class="text-[10px] font-black uppercase tracking-[0.4em]">Directing to Lawa't Core Gateway</p>
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
