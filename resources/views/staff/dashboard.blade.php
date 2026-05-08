@extends('layouts.staff')
@section('title', 'Staff Dashboard')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-6xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-2xl font-bold tracking-[0.2em] uppercase mt-4">Staff Hub</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Welcome back! Here is your shift overview.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <a href="{{ route('pos') }}" class="bg-[#3E2723] hover:bg-[#271815] text-white p-8 rounded-2xl shadow-sm transition-all duration-300 flex flex-col items-center justify-center group">
            <span class="text-5xl mb-4 group-hover:scale-110 transition">🛒</span>
            <h3 class="text-xl font-bold uppercase tracking-widest">Open POS Register</h3>
        </a>

        <div class="bg-white p-8 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-amber-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
            <div class="relative z-10 flex flex-col items-center justify-center h-full">
                <h3 class="text-[#8D6E63] text-sm font-black uppercase tracking-[0.2em] mb-2">Available Vouchers</h3>
                <p class="text-6xl font-black text-[#3E2723]">{{ $unusedVouchers ?? 0 }}</p>
            </div>
        </div>
    </div>
</div>
@endsection