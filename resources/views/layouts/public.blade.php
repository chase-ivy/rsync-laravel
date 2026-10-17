{{--
    Layout for the public pages (home, rooms, one room).
    A page fills: title, fonts (the <link> tags it used before), css (its stylesheet),
    js (its script, optional), body_attrs (data-... values its script needs), content.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title')</title>
@yield('fonts')
@vite(array_filter([$css ?? null, $js ?? null]))
</head>
<body @yield('body_attrs')>
@yield('content')
</body>
</html>