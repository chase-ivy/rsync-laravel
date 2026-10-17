{{-- TEMPORARY page: shown for routes whose real page is migrated in a later step. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · RSYNC</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#0a0a0f;--ink3:#8e8ea0;--bg:#f4f3ef;--amber:#e8a020;--border:rgba(0,0,0,.08);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;flex-direction:column;}
nav{background:var(--ink);display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:56px;}
nav span{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;letter-spacing:3px;}
nav span em{color:var(--amber);font-style:normal;}
nav a{font-size:13px;color:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.2);padding:6px 14px;border-radius:8px;text-decoration:none;}
.box{max-width:520px;margin:60px auto;background:#fff;border:1px solid var(--border);border-radius:16px;padding:32px;}
h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:10px;}
p{color:var(--ink3);font-size:14px;line-height:1.6;}
</style>
</head>
<body>
<nav>
    <span>RS<em>Y</em>NC</span>
    @auth
        <a href="{{ route('logout') }}">Logout</a>
    @else
        <a href="{{ route('login') }}">Login</a>
    @endauth
</nav>
<div class="box">
    <h1>{{ $title }}</h1>
    @auth
        <p>Signed in as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->role }}).</p>
    @endauth
    <p>This page is migrated in {{ $step }}.</p>
</div>
</body>
</html>