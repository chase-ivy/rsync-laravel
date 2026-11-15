<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminTenantController;
use App\Models\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable rental agreement for one contract (one row of `tenants`).
 * Built from the data in the app (the terms are the ones the landlord set, see Tenancy::contractTerms()); the browser's "Print / Save as PDF" turns it into a PDF.
 * The tenant can also accept ("I agree") their contract here.
 */
class ContractController extends Controller
{
    /** GET /contracts/{tenancy}: only the tenant, the landlord who owns the room, and admins. */
    public function show(Request $request, Tenancy $tenancy): View
    {
        $tenancy->load(['user', 'room.owner']);
        $viewer = $request->user();

        $isTenant = (int) $tenancy->user_id === (int) $viewer->user_id;
        $allowed = $viewer->role === 'admin'
            || $isTenant
            || ($viewer->isOwner() && $tenancy->room && (int) $tenancy->room->owner_id === (int) $viewer->user_id);
        abort_unless($allowed, 403);

        // The tenant opened their contract: notices about it on the dashboard count as read.
        if ($isTenant) {
            $viewer->unreadNotifications
                ->filter(fn ($n) => (int) ($n->data['tenancy_id'] ?? 0) === (int) $tenancy->tenant_id)
                ->each->markAsRead();
        }

        $start = $tenancy->start_date;
        $end = $tenancy->end_date;

        $msg = $request->session()->pull('msg');   // after "Accept contract"
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('contracts.show', [
            'contract' => $tenancy,
            'terms' => $tenancy->contractTerms(),   // saved copy, or the landlord's template for older contracts
            'code' => AdminTenantController::code($tenancy->tenant_id),
            'tenant' => $tenancy->user,
            'room' => $tenancy->room,
            'landlord' => $tenancy->room?->owner,
            'months' => $start && $end ? max(1, (int) round($start->floatDiffInMonths($end))) : null,
            'backRoute' => $viewer->homeRoute(),
            // Only the tenant of this contract can accept it, and only while it is Active.
            'canAccept' => $isTenant && $viewer->role === 'tenant' && $tenancy->status === 'Active' && ! $tenancy->isAccepted(),
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /** POST /contracts/{tenancy}/accept — the tenant ticks "I have read and agree" and accepts. */
    public function accept(Request $request, Tenancy $tenancy): RedirectResponse
    {
        abort_unless((int) $tenancy->user_id === (int) $request->user()->user_id, 403);

        $back = redirect()->route('contracts.show', $tenancy->tenant_id);

        if ($tenancy->status !== 'Active') {
            return $back->with('msg', 'err:Only current (Active) contracts can be accepted.');
        }
        if ($tenancy->isAccepted()) {
            return $back->with('msg', 'You already accepted this contract.');
        }

        $request->validate(
            ['agree' => ['accepted']],
            ['agree.accepted' => 'Please tick "I have read and agree to this contract" first.'],
        );

        $tenancy->update(['accepted_at' => now(), 'accepted_ip' => $request->ip()]);

        return $back->with('msg', 'Thank you. You accepted this contract.');
    }
}
