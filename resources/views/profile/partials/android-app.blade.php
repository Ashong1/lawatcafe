{{-- The apps: Android (an APK) and Windows/Linux (installed from the browser
     via public/manifest.json). Hidden inside the Android app itself (its
     user agent says LawatKapeApp). --}}
@php $inApp = str_contains((string) request()->userAgent(), 'LawatKapeApp'); @endphp
@unless($inApp)
<div id="android-app" class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
    <div class="flex flex-col sm:flex-row sm:items-start gap-5">
        <img src="{{ asset('favicon.svg') }}" alt="" class="w-14 h-14 rounded-2xl shrink-0">
        <div class="flex-1 min-w-0">
            <h3 class="text-lg font-bold text-[#3E2723]">Lawa't Kape for Android</h3>
            <p class="text-sm text-[#795548] mt-1">Run the whole system as an app on the shop phone: full screen, the screen stays on at the register, and order reminders vibrate. It works on the shop Wi-Fi.</p>

            <ol class="mt-4 space-y-2 text-sm text-[#4A3B32] list-decimal pl-5">
                <li>On the phone, connect to the shop Wi-Fi and open this page.</li>
                <li>Tap <span class="font-bold">Download the app</span>, then open the downloaded file.</li>
                <li>If Android asks, allow your browser to <span class="font-bold">install unknown apps</span> (it only asks once), then tap <span class="font-bold">Install</span>.</li>
                <li>Open <span class="font-bold">Lawa't Kape</span> from the home screen and sign in.</li>
            </ol>
            <p class="text-xs text-[#795548] mt-3">So the phone doesn't get the guest Wi-Fi sign-in page, an admin should add it on Network → Trusted Devices.</p>

            @if(\App\Http\Controllers\AndroidAppController::available())
                <a href="{{ route('app.android') }}" class="mt-5 inline-flex items-center justify-center gap-2 min-h-[44px] px-6 rounded-full bg-[#3E2723] hover:bg-[#271815] text-white text-sm font-bold transition">
                    <x-lucide-download class="w-4 h-4" /> Download the app
                </a>
            @else
                <p class="mt-5 text-sm font-bold text-amber-800">The app hasn't been built on this server yet.</p>
            @endif
        </div>
    </div>

    <div class="mt-8 pt-6 border-t border-[#F0E6D2] sm:pl-[4.75rem]"
         x-data="{
             prompt: null,
             installed: window.matchMedia('(display-mode: standalone)').matches,
             secure: window.isSecureContext,
             init() {
                 window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); this.prompt = e; });
                 window.addEventListener('appinstalled', () => { this.installed = true; this.prompt = null; });
             },
             async install() {
                 this.prompt.prompt();
                 if ((await this.prompt.userChoice).outcome === 'accepted') this.installed = true;
                 this.prompt = null;
             },
         }">
        <h3 class="text-lg font-bold text-[#3E2723]">Windows and Linux computers</h3>
        <p class="text-sm text-[#795548] mt-1">Install the system as an app with its own window and a desktop icon. It always opens the latest version; there is nothing to update.</p>

        <p x-show="installed" x-cloak class="mt-4 text-sm font-bold text-green-700">Installed on this computer. Open it from the Start menu or desktop.</p>

        <button type="button" x-show="!installed && prompt" x-cloak @click="install()"
                class="mt-4 inline-flex items-center justify-center gap-2 min-h-[44px] px-6 rounded-full bg-[#3E2723] hover:bg-[#271815] text-white text-sm font-bold transition">
            <x-lucide-monitor-down class="w-4 h-4" /> Install on this computer
        </button>

        <div x-show="!installed && !prompt" class="mt-4 text-sm text-[#4A3B32] space-y-2">
            <p x-show="!secure" x-cloak>Open <a href="https://lawatkape.aserotech.com/profile" class="font-bold underline text-[#3E2723]">https://lawatkape.aserotech.com/profile</a> in Chrome or Edge on the computer; the install button appears there.</p>
            <p x-show="secure">Use <span class="font-bold">Chrome</span> or <span class="font-bold">Edge</span>: click the install icon at the right end of the address bar, or open the browser menu and choose <span class="font-bold">Install Lawa't Kape</span> (Chrome: <span class="font-bold">Cast, save and share → Install page as app</span>). Firefox can't install apps.</p>
        </div>
    </div>
</div>
@endunless
