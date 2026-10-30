{{-- Error pages: self-contained (guests may see them before they're online) and
     free of database or session calls, so they still render when those fail. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('heading') - Lawa't Kape</title>
<style>
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
         background: #FDF8F5; color: #3E2723; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .card { max-width: 400px; width: 100%; background: #fff; border: 1px solid #F0E6D2; border-radius: 24px; padding: 32px 24px; text-align: center; }
  .icon { width: 64px; height: 64px; border-radius: 16px; background: #3B1A0E; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
  .code { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #A1887F; margin: 0 0 4px; }
  h1 { font-size: 20px; margin: 0 0 8px; }
  p { font-size: 15px; line-height: 1.5; color: #795548; margin: 0; }
  .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
  .btn { min-height: 44px; padding: 0 20px; border-radius: 999px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center;
         text-decoration: none; cursor: pointer; font-family: inherit; border: 1px solid #F0E6D2; background: #FDF8F5; color: #3E2723; }
  .btn.main { background: #3E2723; border-color: #3E2723; color: #fff; }
  .brand { margin-top: 20px; font-size: 12px; color: #A1887F; }
</style>
</head>
<body>
<main class="card">
  <div class="icon" aria-hidden="true">
    <svg width="36" height="36" viewBox="0 0 64 64" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 24h24v14a10 10 0 0 1-10 10h-4a10 10 0 0 1-10-10V24z" stroke="#FDE68A" stroke-width="4"/>
      <path d="M42 28h4a4 4 0 0 1 4 4v2a4 4 0 0 1-4 4h-4" stroke="#FDE68A" stroke-width="4"/>
    </svg>
  </div>
  <p class="code">Error @yield('code')</p>
  <h1>@yield('heading')</h1>
  <p>@yield('message')</p>
  <div class="actions">
    <button type="button" class="btn" onclick="history.length > 1 ? history.back() : location.assign('/')">Go back</button>
    <a class="btn main" href="/">@yield('home', 'Home')</a>
  </div>
  <p class="brand">Lawa't Kape</p>
</main>
</body>
</html>
