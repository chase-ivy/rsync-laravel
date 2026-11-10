{{-- Top bar for every admin page. The current page's link is highlighted. --}}
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-links">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('admin.tenants') }}" class="{{ request()->routeIs('admin.tenants') ? 'active' : '' }}">Tenants</a>
        <a href="{{ route('admin.finance') }}" class="{{ request()->routeIs('admin.finance') ? 'active' : '' }}">Finance</a>
        <a href="{{ route('admin.maintenance') }}" class="{{ request()->routeIs('admin.maintenance') ? 'active' : '' }}">Maintenance</a>
    </div>
</nav>
@include('partials.verify-banner')