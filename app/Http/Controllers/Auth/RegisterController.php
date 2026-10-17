<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Replaces registration.php.
 */
class RegisterController extends Controller
{
    /** Plans from the pricing section of the home page (index.php). */
    public const PLANS = ['Starter', 'Professional', 'Enterprise'];

    /**
     * GET /register
     * - From "Create one" (no ?plan): role dropdown; a plan choice appears for landlord/sublessor.
     * - From a pricing button (?plan=Professional): role is locked to landlord, plan already chosen.
     */
    public function show(Request $request)
    {
        $plan = $this->validPlan($request->query('plan'));

        return view('auth.register', [
            'error' => null,
            'lockedPlan' => $plan,
            'selectedPlan' => $plan,
            'selectedRole' => $plan !== '' ? 'landlord' : '',
        ]);
    }

    /** POST /register — same flow as registration.php. */
    public function store(Request $request)
    {
        $role = (string) $request->input('role', '');
        $plan = $this->validPlan($request->input('plan'));
        $lockedPlan = $request->boolean('plan_locked') ? $plan : '';

        $page = fn (string $error) => view('auth.register', [
            'error' => $error,
            'lockedPlan' => $lockedPlan,
            'selectedPlan' => $plan,
            'selectedRole' => $role !== '' ? $role : ($lockedPlan !== '' ? 'landlord' : ''),
        ]);

        // Server-side checks (security fix S6: the old page only checked in the browser).
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string'],
            'role' => ['required', 'in:tenant,landlord,sublessor'],
            // Landlords and sublessors must pick a plan; tenants don't have one.
            'plan' => ['required_if:role,landlord,sublessor', 'nullable', 'in:' . implode(',', self::PLANS)],
        ], [
            'plan.required_if' => 'Please choose a plan.',
        ]);
        if ($validator->fails()) {
            return $page($validator->errors()->first());
        }

        $email = (string) $request->input('email');

        // Same message as legacy; case-insensitive like the old MariaDB database.
        if (User::whereRaw('LOWER(email) = LOWER(?)', [$email])->exists()) {
            return $page('Email already exists.');
        }

        try {
            $user = User::create([
                'name' => $request->input('name'),
                'age' => (int) $request->input('age'),
                'email' => $email,
                'password' => Hash::make((string) $request->input('password')),
                'role' => $role,
            ]);
        } catch (QueryException $e) {
            report($e);
            return $page('Something went wrong. Please try again.');
        }

               // Laravel sends the email-verification link when this event fires (Part C3).
        event(new Registered($user));

        return redirect()->to(route('login') . '?registered=1')
            ->with('status', 'Account created! We sent a verification link to your email.');
    }

    /** Returns the plan name if it is one of PLANS, otherwise ''. */
    private function validPlan(mixed $plan): string
    {
        return in_array($plan, self::PLANS, true) ? $plan : '';
    }
}