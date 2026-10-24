<!doctype html>
<html lang="{{ app()->getLocale() === 'fil' ? 'fil' : 'en' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="only light">
<title>{{ __('Privacy notice') }} - Lawa't Kape</title>
{{-- Self-contained on purpose: a guest reading this has no internet yet. --}}
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #FDF8F5; color: #3E2723; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.55; }
  main { max-width: 640px; margin: 0 auto; padding: 20px 16px 40px; }
  .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
  a.back { display: inline-flex; align-items: center; min-height: 44px; color: #3E2723; font-weight: 700; text-decoration: none; }
  .lang { display: inline-flex; border: 1px solid #E6D5C3; border-radius: 999px; padding: 2px; }
  .lang a { min-height: 40px; display: flex; align-items: center; padding: 0 14px; border-radius: 999px; color: #5D4037; font-weight: 700; font-size: 14px; text-decoration: none; }
  .lang a[aria-current] { background: #3E2723; color: #fff; }
  h1 { font-size: 26px; margin: 8px 0 8px; }
  h2 { font-size: 17px; margin: 24px 0 6px; }
  p, li { font-size: 16px; color: #4A3B32; }
  ul { padding-left: 20px; margin: 6px 0; }
  li { margin: 4px 0; }
  .card { background: #fff; border: 1px solid #F0E6D2; border-radius: 20px; padding: 18px 18px 8px; }
  .card h2:first-child { margin-top: 0; }
  .muted { color: #795548; font-size: 14px; }
</style>
</head>
<body>
<main>
  <div class="top">
    <a class="back" href="{{ route('portal.index') }}">← {{ __('Back to Wi-Fi sign-in') }}</a>
    <div class="lang" role="group" aria-label="Language / Wika">
      @foreach(['en' => 'English', 'fil' => 'Filipino'] as $code => $name)
        <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" @if(app()->getLocale() === $code) aria-current="true" @endif>{{ $name }}</a>
      @endforeach
    </div>
  </div>

  <h1>{{ __('Privacy notice') }}</h1>
  <p>{{ __('Lawa\'t Kape runs this Wi-Fi. This explains what we collect when you use it, why, and for how long, as required by the Data Privacy Act of 2012 (Republic Act No. 10173).') }}</p>

  <div class="card">
    <h2>{{ __('What we collect') }}</h2>
    <ul>
      <li>{{ __('Your device\'s network address (MAC), the name it shows on the network, and the IP address it gets on our Wi-Fi.') }}</li>
      <li>{{ __('Your Wi-Fi code, when you signed in, how much data you use, and when your time ended.') }}</li>
      <li>{{ __('The names of websites your device looks up, recorded by our website filter. We do not see what you type or read on those sites.') }}</li>
      <li>{{ __('Your messages, if you chat with Barista AI.') }}</li>
    </ul>
    <p>{{ __('We never ask for your name, phone number or email.') }}</p>

    <h2>{{ __('Why') }}</h2>
    <ul>
      <li>{{ __('To give you the Wi-Fi time on your code and end it when it is over.') }}</li>
      <li>{{ __('To keep the Wi-Fi fair and safe: speed limits, blocking adult websites, and stopping a device that misuses the network.') }}</li>
      <li>{{ __('To answer your questions in the chat.') }}</li>
    </ul>

    <h2>{{ __('Who sees it') }}</h2>
    <ul>
      <li>{{ __('The shop owner and staff, when helping you or looking after the network.') }}</li>
      <li>{{ __('Your Barista AI messages are sent to an AI service on the internet (OpenRouter) to get an answer. The shop does not keep them, unless you rate an answer: then that message is kept to improve the assistant.') }}</li>
      <li>{{ __('We do not sell or give your data to anyone else.') }}</li>
    </ul>

    <h2>{{ __('How long we keep it') }}</h2>
    <ul>
      <li>{{ __('Sign-in history: 90 days.') }}</li>
      <li>{{ __('Website lookups: 91 days.') }}</li>
      <li>{{ __('Your Wi-Fi code record (code, device address, time used) stays with the shop\'s sales records. Device addresses are stored encrypted.') }}</li>
    </ul>

    <h2>{{ __('Your rights') }}</h2>
    <p>{{ __('You can ask what we hold about your device, ask us to correct or delete it, or object to how we use it. You can also complain to the National Privacy Commission (privacy.gov.ph).') }}</p>

    <h2>{{ __('Data Protection Officer') }}</h2>
    <p>{{ __('Owner, Lawa\'t Kape. Ask at the counter.') }}</p>
  </div>

  <p class="muted">{{ __('Last updated: October 25, 2026') }}</p>
</main>
</body>
</html>
