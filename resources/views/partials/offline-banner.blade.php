{{-- Shown while network:health finds the internet down, so staff know it's
     the provider and not the system. Sits above <main> because pages pull
     their background to its edges with negative margins. --}}
@if(\App\Services\InternetStatus::isDown())
    <div role="status" class="shrink-0 bg-amber-100 border-b border-amber-300 text-amber-900 px-4 sm:px-6 py-2.5 flex items-start gap-2 text-xs sm:text-sm">
        <x-lucide-wifi-off class="w-4 h-4 mt-0.5 shrink-0" />
        <p>
            <span class="font-bold">The internet is down.</span>
            The register, vouchers, the Wi-Fi login and reports still work. Barista AI and emails will be back when the internet returns; emails wait and go out then.
        </p>
    </div>
@endif
