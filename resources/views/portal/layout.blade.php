<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Classroom Portal</title>
    <style>
        body{font:16px system-ui;margin:0;background:#f6f8fb;color:#172033}main{max-width:1000px;margin:auto;padding:32px}nav{background:#172033;color:#fff;padding:14px 32px;display:flex;gap:16px;align-items:center}nav a{color:#fff;text-decoration:none}button,.button{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:9px 13px;cursor:pointer;text-decoration:none}button.danger{background:#b91c1c}form.inline{display:inline}.card{background:#fff;border:1px solid #e4e8ef;border-radius:10px;padding:20px;margin:16px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}input,select{box-sizing:border-box;width:100%;padding:9px;margin:5px 0 13px;border:1px solid #cbd5e1;border-radius:5px}code{background:#eef2ff;padding:3px 5px;border-radius:4px}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}.error{color:#b91c1c}.success{color:#166534}.method{font-weight:bold;color:#1d4ed8}
    </style>
</head>
<body>
    @auth('web')
        <nav>
            <a href="{{ route('portal.dashboard') }}">Dashboard</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('portal.admin') }}">Admin Overview</a>
            @endif
            <span style="margin-left:auto">{{ auth()->user()->name }}</span>
            <form class="inline" method="post" action="{{ route('portal.logout') }}">
                @csrf
                <button>Logout</button>
            </form>
        </nav>
    @endauth
    <main>
        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
    <script>
        function copyText(value, button) {
            navigator.clipboard.writeText(value);
            const old = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => button.textContent = old, 1200);
        }
    </script>
</body>
</html>
