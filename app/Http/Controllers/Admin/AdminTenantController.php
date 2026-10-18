<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Replaces the demo tenants.php: Tenant Details, Contract Details,
 * Update Contract Details and Update Tenant Status — with real data.
 * A "contract" is one row of the `tenants` table.
 */
class AdminTenantController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();

        // A. Tenant Details: every user with the tenant role, with the room(s) they rent right now.
        $tenants = User::where('role', 'tenant')
            ->with(['tenancies' => fn ($q) => $q->where('status', 'Active')->with('room:room_id,room_name')])
            ->orderBy('user_id')
            ->get();

        // B. Contract Details: every contract, newest first.
        $contracts = Tenancy::with(['user:user_id,name', 'room:room_id,room_name'])
            ->orderByDesc('start_date')
            ->orderByDesc('tenant_id')
            ->get();

        // C + D work on contracts that are still running.
        $active = $contracts->where('status', 'Active')->values();

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('admin.tenants', [
            'tenants' => $tenants,
            'contracts' => $contracts,
            'active' => $active,
            'today' => $today,
            'statuses' => Tenancy::PAYMENT_STATUSES,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /** C. POST /admin/contracts/update — change the start/end dates of a running contract. */
    public function updateContract(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'tenant_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ], [
            'tenant_id.required' => 'Please select a contract.',
            'end_date.after' => 'The end date must be after the start date.',
        ]);
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first());
        }

        $contract = Tenancy::with('user:user_id,name')->where('status', 'Active')->find((int) $request->input('tenant_id'));
        if (! $contract) {
            return $this->back('err:Contract not found or no longer active.');
        }

        $contract->update([
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        return $this->back('Contract ' . self::code($contract->tenant_id) . " for {$contract->user?->name} updated.");
    }

    /** D. POST /admin/contracts/status — Registered / Paid / On-Time / Delayed. */
    public function updateStatus(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'tenant_id' => ['required', 'integer'],
            'payment_status' => ['required', Rule::in(Tenancy::PAYMENT_STATUSES)],
        ]);
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first());
        }

        $contract = Tenancy::with('user:user_id,name')->where('status', 'Active')->find((int) $request->input('tenant_id'));
        if (! $contract) {
            return $this->back('err:Contract not found or no longer active.');
        }

        $contract->update(['payment_status' => $request->input('payment_status')]);

        return $this->back("{$contract->user?->name} is now marked {$contract->payment_status}.");
    }

    /** Contract number shown on the page, e.g. tenant_id 7 → "C007" (like the old demo). */
    public static function code(int $id): string
    {
        return 'C' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('admin.tenants')->with('msg', $msg);
    }
}