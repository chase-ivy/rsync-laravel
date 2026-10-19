<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Replaces the demo admin_dashboard.php with real data:
 * Financial Summary, Verify Users, Verify Payments and Transaction Monitoring.
 */
class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();

        // Money only counts once the admin has verified the payment.
        $verified = Payment::where('status', 'Verified');
        $totalPayments = (float) (clone $verified)->sum('amount');
        $monthlyEarnings = (float) (clone $verified)
            ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->sum('amount');

        // Pie chart: verified payments per month of this year.
        $monthly = Payment::monthlyVerifiedTotals();

        // Verify Users (oopPHP::get_pending_users: not a landlord and not yet verified) — admins excluded,
        // and users the admin already rejected are not shown again.
        // New: landlords show up too once they upload a business permit.
        $pendingUsers = User::reviewable()
            ->where('verified', false)
            ->whereNull('verification_rejected_at')
            ->orderBy('created_at')
            ->orderBy('user_id')
            ->get();

        // Verify Payments: everything still Pending first, then the newest verified ones.
        $payments = Payment::with('user:user_id,name,email')
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByDesc('date')
            ->orderByDesc('payment_id')
            ->limit(20)
            ->get();

        // Transaction Monitoring (oopPHP::get_transactions: newest 12).
        $transactions = Transaction::orderByDesc('date')->orderByDesc('transaction_id')->limit(12)->get();

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('admin.dashboard', [
            'totalPayments' => $totalPayments,
            'monthlyEarnings' => $monthlyEarnings,
            'monthly' => $monthly,
            'pendingUsers' => $pendingUsers,
            'payments' => $payments,
            'transactions' => $transactions,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /** POST /admin/users/{id}/approve — oopPHP::verify_user (verified = 1). */
    public function approveUser(int $id): RedirectResponse
    {
        $user = $this->reviewable($id);
        if (! $user) {
            return $this->back('err:User not found.');
        }
        $user->forceFill(['verified' => true, 'verification_rejected_at' => null])->save();

        return $this->back("{$user->name} is now verified.");
    }

    /** POST /admin/users/{id}/reject — stays unverified and leaves the list. Nothing is deleted. */
    public function rejectUser(int $id): RedirectResponse
    {
        $user = $this->reviewable($id);
        if (! $user) {
            return $this->back('err:User not found.');
        }
        $user->forceFill(['verified' => false, 'verification_rejected_at' => now()])->save();

        return $this->back("{$user->name} was rejected.");
    }

    /** POST /admin/payments/{id}/verify — oopPHP::verify_payment (status = 'Verified'). */
    public function verifyPayment(int $id): RedirectResponse
    {
        $payment = Payment::find($id);
        if (! $payment) {
            return $this->back('err:Payment not found.');
        }
        if ($payment->status === 'Verified') {
            return $this->back('err:That payment is already verified.');
        }
        $payment->update(['status' => 'Verified']);

        return $this->back('Payment of ₱' . number_format((float) $payment->amount, 2) . ' verified.');
    }

    /** Admins are never in the Verify Users list; landlords only after uploading a business permit. */
    private function reviewable(int $id): ?User
    {
        return User::reviewable()->find($id);
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('admin.dashboard')->with('msg', $msg);
    }
}
