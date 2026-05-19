<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', "Lawa't Kape") }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-screen flex items-center justify-center relative bg-[#271815]">

    <!-- Blurred Background -->
    <div class="absolute inset-0 z-0 opacity-40 bg-cover bg-center" style="background-image: url('/images/lawat-bg.jpg'); filter: blur(8px);"></div>

    <!-- Main Centered Card -->
    <div class="relative z-10 w-full max-w-6xl min-h-[600px] mx-4 flex rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-[#5D4037]">
        
        <!-- Left Side: Full Artwork -->
        <div class="hidden md:block md:w-[55%] lg:w-[60%] bg-[#FDF8F5]">
            <img src="/images/lawat-bg.jpg" alt="Lawa't Kape Artwork" class="w-full h-full object-cover" />
        </div>

        <!-- Right Side: Dark Coffee Form -->
        <div class="w-full md:w-[45%] lg:w-[40%] bg-[#3E2723] p-8 sm:p-10 flex flex-col justify-center text-[#FDF8F5] overflow-y-auto max-h-[90vh]">
            
            <!-- Logo & Header -->
            <div class="text-center mb-10 mt-4">
                <div class="mb-6 flex flex-col items-center justify-center">
                    <h1 class="text-5xl sm:text-6xl text-white tracking-wide" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                    
                    <div class="flex items-center gap-4 mt-2">
                        <div class="h-[1px] w-8 bg-[#A1887F]"></div>
                        <h2 class="text-xl sm:text-2xl tracking-[0.4em] font-semibold text-white uppercase" style="font-family: 'Montserrat', sans-serif;">Kape</h2>
                        <div class="h-[1px] w-8 bg-[#A1887F]"></div>
                    </div>
                </div>

                @if(isset($header))
                    {{ $header }}
                @endif
            </div>

            <div style="font-family: 'Montserrat', sans-serif;">
                {{ $slot }}
            </div>
            
        </div>
    </div>

</body>
</html>
