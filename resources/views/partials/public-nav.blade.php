{{-- Top bar of the home and rooms pages (same markup as rooms.php). Logged-in users get their own links. --}}
<nav>
    <a class="nav-logo" href="{{ route('home') }}">RS<span>Y</span>NC</a>
    <div class="nav-links">
        @auth
            @php $home = auth()->user()->homeRoute(); @endphp
            <a href="{{ route($home) }}">{{ $home === 'home' ? 'Home' : 'Dashboard' }}</a>
            <a href="{{ route('account') }}">Account</a>
            <a href="{{ route('logout') }}" class="cta">Logout</a>
        @else
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('rooms.index') }}">Rooms</a>
            <a href="{{ route('login') }}" class="cta">Login</a>
        @endauth
    </div>
</nav>