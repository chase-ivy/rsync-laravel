@if ($loginFailed)
{{-- Same as legacy oopPHP::login(): the alert is printed before the page. --}}
<script>alert('Invalid login credentials.');</script>
@endif
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · RSYNC</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
<style>
:root{--ink:#0a0a0f;--ink2:#3d3d4a;--ink3:#8e8ea0;--bg:#f4f3ef;--amber:#e8a020;--border:rgba(0,0,0,.09);--r:12px;}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;}
.left{flex:1;position:relative;background:url('https://images.unsplash.com/photo-1505691938895-1758d7feb511') center/cover no-repeat;}
.left::after{content:'';position:absolute;inset:0;background:rgba(10,10,15,.52);}
.left-copy{position:absolute;bottom:52px;left:52px;z-index:2;color:#fff;max-width:380px;}
.left-copy .logo{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;letter-spacing:3px;margin-bottom:18px;}
.left-copy .logo span{color:var(--amber);}
.left-copy h2{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;line-height:1.15;margin-bottom:10px;}
.left-copy p{font-size:14px;opacity:.7;line-height:1.6;}
.right{flex:1;display:flex;justify-content:center;align-items:center;padding:32px 20px;}
.box{width:100%;max-width:380px;background:#fff;border:1px solid var(--border);border-radius:18px;padding:38px;box-shadow:0 8px 32px rgba(0,0,0,.07);animation:up .5s both;}
@keyframes up{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.box-logo{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;letter-spacing:3px;color:var(--ink);margin-bottom:22px;}
.box-logo span{color:var(--amber);}
.box h2{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;margin-bottom:4px;}
.box .sub{font-size:13px;color:var(--ink3);margin-bottom:26px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:11px;font-weight:600;color:var(--ink3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
.field .inp-wrap{position:relative;}
.field input{width:100%;padding:11px 38px 11px 13px;border:1.5px solid var(--border);border-radius:var(--r);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:.18s;background:#fafaf8;}
.field input:focus{border-color:var(--amber);background:#fff;box-shadow:0 0 0 3px rgba(232,160,32,.13);}
.field i{position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:17px;color:var(--ink3);cursor:pointer;}
.forgot{text-align:right;margin-top:-8px;margin-bottom:18px;}
.forgot a{font-size:12px;color:var(--amber);font-weight:500;}
.forgot a:hover{text-decoration:underline;}
.btn-main{width:100%;padding:12px;border:none;border-radius:var(--r);background:var(--ink);color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:.18s;margin-top:4px;}
.btn-main:hover{background:#1e1e2e;}
.btn-back{display:block;width:100%;margin-top:10px;padding:11px;text-align:center;border-radius:var(--r);background:transparent;border:1.5px solid var(--border);color:var(--ink2);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;text-decoration:none;transition:.18s;}
.btn-back:hover{border-color:var(--amber);color:var(--ink);}
.foot{margin-top:20px;text-align:center;font-size:13px;color:var(--ink3);}
.foot a{color:var(--amber);font-weight:500;}
.foot a:hover{text-decoration:underline;}
.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;font-size:13px;padding:10px 13px;border-radius:var(--r);margin-bottom:16px;display:flex;align-items:center;gap:7px;}
@media(max-width:860px){.left{display:none;}.right{background:var(--bg);}}
</style>
</head>
<body>
<div class="left">
    <div class="left-copy">
        <div class="logo">RS<span>Y</span>NC</div>
        <h2>Welcome back</h2>
        <p>Access your dashboard, manage listings, and track your tenants in one place.</p>
    </div>
</div>
<div class="right">
<form method="POST" action="{{ route('login.attempt') }}" class="box">
    @csrf
    <div class="box-logo">RS<span>Y</span>NC</div>
    <h2>Sign in</h2>
    <p class="sub">Enter your credentials to continue</p>

    @if ($resetMsg)<div class="ok"><i class="bx bx-check-circle"></i>{{ $resetMsg }}</div>@endif

    <div class="field">
        <label>Email</label>
        <div class="inp-wrap">
            <input type="email" name="email" placeholder="you@example.com" required>
            <i class="bx bx-envelope"></i>
        </div>
    </div>

    <div class="field">
        <label>Password</label>
        <div class="inp-wrap">
            <input type="password" id="pw" name="password" placeholder="••••••••" required>
            <i class="bx bx-show" onclick="togglePw()"></i>
        </div>
    </div>

    <div class="forgot"><a href="{{ route('password.request') }}">Forgot password?</a></div>

    <button class="btn-main" name="login">Login</button>
    <a href="{{ route('home') }}" class="btn-back">← Back to Home</a>

    <div class="foot">Don't have an account? <a href="{{ route('register') }}">Create one</a></div>
</form>
</div>
<script>
function togglePw(){const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password';}
</script>
</body>
</html>