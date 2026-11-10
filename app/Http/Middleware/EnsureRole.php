<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets users with one of the given roles through; everyone else goes to the
 * login page — the same rule as the top of tenant_dashboard.php.
 *
 * Usage in routes: ->middleware('role:tenant') or ->middleware('role:landlord')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}