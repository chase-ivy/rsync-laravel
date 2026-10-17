<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Replaces login.php, logout.php and oopPHP::login().
 */
class LoginController extends Controller
{
    /** GET /login — the sign-in page. */
    public function show(Request $request)
    {
        return view('auth.login', [
            'resetMsg' => $request->session()->pull('reset_success'),
            'loginFailed' => false,
        ]);
    }

    /** POST /login — same checks and redirects as oopPHP::login(). */
    public function login(Request $request)
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        // Legacy MariaDB matched emails case-insensitively; keep that on PostgreSQL.
        $user = User::whereRaw('LOWER(email) = LOWER(?)', [$email])->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            // Legacy behaviour: same page again with alert('Invalid login credentials.').
            return view('auth.login', [
                'resetMsg' => $request->session()->pull('reset_success'),
                'loginFailed' => true,
            ]);
        }

        Auth::login($user);

        // Security fix (S8): new session ID after login, prevents session fixation.
        $request->session()->regenerate();

        return match ($user->role) {
            'landlord' => redirect()->route('landlord.dashboard'),
            'tenant' => redirect()->route('tenant.dashboard'),
            default => redirect()->route('home'),
        };
    }

    /** GET or POST /logout — same as logout.php: end the session, go to login. */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}