@extends('layouts.auth')

@section('title', 'Register')
@section('page', 'register')

@section('hero')
    <h2>Start your journey</h2>
    <p>Find your ideal boarding space — designed for comfort, safety, and student living.</p>
@endsection

@section('content')
<form method="POST" action="{{ route('register.store') }}" class="box">
    @csrf
    <div class="box-logo">RS<span>Y</span>NC</div>
    <h2>Create account</h2>
    <p class="sub">Join RSYNC and manage your boarding experience. You can add your name and other details after verifying your email.</p>

    @if (!empty($error))
        <div class="err">{{ $error }}</div>
    @endif

    <div class="field">
        <label>Email</label>
        <div class="inp-wrap">
            <input type="email" name="email" placeholder="Your email" required value="{{ request('email', '') }}">
            <i class="bx bx-envelope"></i>
        </div>
    </div>

    <div class="field">
        <label>Password</label>
        <div class="inp-wrap">
            <input type="password" id="pw" name="password" placeholder="••••••••" minlength="8" required autocomplete="new-password">
            <i class="bx bx-show clickable" onclick="togglePw()"></i>
        </div>
        <p class="pw-hint">{{ \App\Support\PasswordRules::HINT }}</p>
    </div>

    <div class="field">
        <label>Confirm Password</label>
        <div class="inp-wrap">
            <input type="password" name="password_confirmation" placeholder="••••••••" minlength="8" required autocomplete="new-password">
            <i class="bx bx-lock-alt"></i>
        </div>
    </div>

    @if ($lockedPlan)
    {{-- Came from a pricing button on the home page: plan and role are already chosen. --}}
    <div class="field">
        <label>Selected plan</label>
        <div class="inp-wrap plan-chip">{{ $lockedPlan }}</div>
        <input type="hidden" name="plan" value="{{ $lockedPlan }}">
        <input type="hidden" name="plan_locked" value="1">
    </div>
    <div class="field">
        <label>Registration type</label>
        <div class="inp-wrap plan-chip">Landlord</div>
        <input type="hidden" name="role" value="landlord">
    </div>
    @else
    <div class="field">
        <label>Role</label>
        <div class="inp-wrap">
            <select name="role" required>
                <option value="" disabled {{ empty($selectedRole) ? 'selected' : '' }}>Select role</option>
                <option value="tenant"    {{ $selectedRole === 'tenant' ? 'selected' : '' }}>Tenant</option>
                <option value="landlord"  {{ $selectedRole === 'landlord' ? 'selected' : '' }}>Landlord</option>
                <option value="sublessor" {{ $selectedRole === 'sublessor' ? 'selected' : '' }}>Sublessor</option>
            </select>
            <i class="bx bx-chevron-down"></i>
        </div>
    </div>

    {{-- Shown only for Landlord / Sublessor (auth.js shows and hides it when the role changes). --}}
    @php $needsPlan = in_array($selectedRole, ['landlord', 'sublessor'], true); @endphp
    <div class="field" id="plan-field"{{ $needsPlan ? '' : ' hidden' }}>
        <label>Plan</label>
        <div class="inp-wrap">
            <select name="plan" id="plan" {{ $needsPlan ? 'required' : 'disabled' }}>
                <option value="" disabled {{ $selectedPlan === '' ? 'selected' : '' }}>Select plan</option>
                <option value="Starter"      {{ $selectedPlan === 'Starter' ? 'selected' : '' }}>Starter — ₱0/mo</option>
                <option value="Professional" {{ $selectedPlan === 'Professional' ? 'selected' : '' }}>Professional — ₱399/mo</option>
                <option value="Enterprise"   {{ $selectedPlan === 'Enterprise' ? 'selected' : '' }}>Enterprise — ₱699/mo</option>
            </select>
            <i class="bx bx-chevron-down"></i>
        </div>
    </div>
    @endif

    <button class="btn-main" name="register">Create Account</button>

    <div class="foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
</form>
@endsection
