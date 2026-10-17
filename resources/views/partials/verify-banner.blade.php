{{--
    Reminder for logged-in users whose email is not verified yet.
    Include it at the top of every logged-in page:  @include('partials.verify-banner')
--}}
@auth
    @unless (auth()->user()->hasVerifiedEmail())
        <div style="background:#fffbeb;border-bottom:1px solid #fde68a;color:#92400e;font-size:13px;padding:10px 32px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
            <span>Please verify your email (<strong>{{ auth()->user()->email }}</strong>) to apply for rooms and use all features.</span>
            <form method="POST" action="{{ route('verification.send') }}" style="margin:0;">
                @csrf
                <button style="background:none;border:0;padding:0;color:#b45309;font:inherit;font-weight:600;text-decoration:underline;cursor:pointer;">Resend link</button>
            </form>
        </div>
    @endunless
@endauth
@if (session('status'))
    <div style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;color:#16a34a;font-size:13px;padding:10px 32px;">{{ session('status') }}</div>
@endif