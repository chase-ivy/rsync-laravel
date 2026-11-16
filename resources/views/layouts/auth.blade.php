{{--
    Shared layout for the sign-in pages (login, register, forgot password).
    A page fills these sections:
      title     → browser tab text
      page      → login | register | forgot  (picks the page's look in auth.css)
      hero      → left photo panel text (leave out for a page without the photo; empty = photo only)
      photo_only → set to 1 for a left panel with only the picture (no text)
      photo_extra → optional content on the picture (login: the three room numbers)
      photo     → optional picture address for the left panel (otherwise auth.css picks one)
      content   → the white card
--}}
@yield('before_html')
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · RSYNC</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&family=Inter+Tight:wght@600;800&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@vite(['resources/css/auth.css', 'resources/js/auth.js'])
</head>
<body class="page-@yield('page')">
@if ($__env->hasSection('hero') || $__env->hasSection('photo_only'))
<div class="left" @hasSection('photo') style="background-image:url('@yield('photo')')" @endif>
    @if (trim($__env->yieldContent('hero')) !== '')
    <div class="left-copy">
        <div class="logo">RS<span>Y</span>NC</div>
        @yield('hero')
    </div>
    @endif
    @yield('photo_extra')
</div>
<div class="right">
    @yield('content')
</div>
@else
    @yield('content')
@endif
</body>
</html>