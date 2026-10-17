{{-- Top bar of the home and rooms pages (same markup as rooms.php). --}}
<nav>
    <a class="nav-logo" href="{{ route('home') }}">RS<span>Y</span>NC</a>
    <div class="nav-links">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('rooms.index') }}">Rooms</a>
        <a href="{{ route('login') }}" class="cta">Login</a>
    </div>
</nav>