<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * New tenant features (not in the old app): ask for maintenance and report a payment.
 * Both start as "Pending"; the admin (and, for maintenance, the landlord) sees them.
 * Only tenants who are currently renting a room can use them.
 */
class TenantRequestController extends Controller
{
    /** POST /tenant/maintenance */
    public function storeMaintenance(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'room_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:1000'],
        ], [
            'description.required' => 'Please describe the problem.',
        ]);
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first());
        }

        // The room must be one the tenant is renting right now.
        if (! $this->rentsRoom($request, (int) $request->input('room_id'))) {
            return $this->back('err:You can only request maintenance for a room you are renting.');
        }

        MaintenanceRequest::create([
            'tenant_id' => $request->user()->user_id,
            'room_id' => (int) $request->input('room_id'),
            'description' => trim($request->input('description')),
            'status' => 'Pending',
        ]);

        return $this->back('Maintenance request sent. Your landlord will be notified.');
    }

    /** POST /tenant/payments */
    public function storePayment(Request $request): RedirectResponse
    {
        if (! Tenancy::where('user_id', $request->user()->user_id)->where('status', 'Active')->exists()) {
            return $this->back('err:You can only submit payments while you are renting a room.');
        }

        $v = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:today'],
        ], [
            'date.before_or_equal' => 'The payment date cannot be in the future.',
        ]);
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first());
        }

        Payment::create([
            'user_id' => $request->user()->user_id,
            'amount' => round((float) $request->input('amount'), 2),
            'date' => $request->input('date'),
            'status' => 'Pending',
        ]);

        return $this->back('Payment submitted. It will show as Verified once the admin checks it.');
    }

    private function rentsRoom(Request $request, int $roomId): bool
    {
        return Tenancy::where('user_id', $request->user()->user_id)
            ->where('room_id', $roomId)
            ->where('status', 'Active')
            ->exists();
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('tenant.dashboard')->with('msg', $msg);
    }
}