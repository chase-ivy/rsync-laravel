<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Replaces the demo report.php: Tenant Total Amount, Monthly Income Summary (+ pie chart)
 * and Tenant Transactions (record a transaction, mark it as paid).
 */
class AdminFinanceController extends Controller
{
    public function index(Request $request)
    {
        // A. One row per running contract: the room's monthly rent and what that tenant has paid.
        $contracts = Tenancy::with(['user:user_id,name', 'room:room_id,room_name,price'])
            ->where('status', 'Active')
            ->orderBy('tenant_id')
            ->get();

        $paid = Payment::where('status', 'Verified')->whereIn('user_id', $contracts->pluck('user_id'))
            ->selectRaw('user_id, SUM(amount) AS total')->groupBy('user_id')->pluck('total', 'user_id');
        $waiting = Payment::where('status', 'Pending')->whereIn('user_id', $contracts->pluck('user_id'))
            ->selectRaw('user_id, SUM(amount) AS total')->groupBy('user_id')->pluck('total', 'user_id');

        $rows = $contracts->map(fn ($c) => [
            'tenant' => $c->user?->name ?? '—',
            'room' => $c->room?->room_name ?? '—',
            'rent' => (float) ($c->room?->price ?? 0),
            'paid' => (float) ($paid[$c->user_id] ?? 0),
            'waiting' => (float) ($waiting[$c->user_id] ?? 0),
        ]);

        // Monthly Income Summary + pie chart (verified payments of this year).
        $monthly = Payment::monthlyVerifiedTotals();

        // B. Tenant Transactions: all of them, newest first.
        $transactions = Transaction::with('user:user_id,name')
            ->orderByDesc('date')
            ->orderByDesc('transaction_id')
            ->get();

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('admin.finance', [
            'rows' => $rows,
            'monthly' => $monthly,
            'transactions' => $transactions,
            'tenants' => User::where('role', 'tenant')->orderBy('name')->get(['user_id', 'name']),
            'types' => Transaction::TYPES,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /** POST /admin/transactions — "Record Transaction". The ID is made automatically (e.g. 202610-RM01). */
    public function store(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'user_id')->where('role', 'tenant')],
            'type' => ['required', Rule::in(Transaction::TYPES)],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['Pending', 'Completed'])],
        ], [
            'user_id.exists' => 'Please pick a tenant from the list.',
            'date.before_or_equal' => 'The date cannot be in the future.',
        ]);
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first());
        }

        $data = [
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'type' => $request->input('type'),
            'amount' => round((float) $request->input('amount'), 2),
            'date' => $request->input('date'),
            'status' => $request->input('status'),
        ];

        // Two admins saving at the same moment could pick the same number; try the next one.
        for ($try = 0; $try < 3; $try++) {
            try {
                $txn = Transaction::create(['transaction_id' => Transaction::nextId($data['date'])] + $data);

                return $this->back("Transaction {$txn->transaction_id} recorded.");
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return $this->back('err:Could not save the transaction. Please try again.');
    }

    /** POST /admin/transactions/paid — "Mark as Paid" (Pending → Completed). */
    public function markPaid(Request $request): RedirectResponse
    {
        $txn = Transaction::find((string) $request->input('transaction_id'));
        if (! $txn) {
            return $this->back('err:Transaction not found.');
        }
        if ($txn->status === 'Completed') {
            return $this->back("err:{$txn->transaction_id} is already completed.");
        }
        $txn->update(['status' => 'Completed']);

        return $this->back("{$txn->transaction_id} marked as paid.");
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('admin.finance')->with('msg', $msg);
    }
}