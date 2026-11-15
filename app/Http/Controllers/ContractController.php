<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminTenantController;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable rental agreement for one contract (one row of `tenants`).
 * Built from the data in the app (the terms are the ones the landlord set, see Tenancy::contractTerms()); the browser's "Print / Save as PDF" turns it into a PDF.
 */
class ContractController extends Controller
{
    /** GET /contracts/{tenancy}: only the tenant, the landlord who owns the room, and admins. */
    public function show(Request $request, Tenancy $tenancy): View
    {
        $tenancy->load(['user', 'room.owner']);
        $viewer = $request->user();

        $allowed = $viewer->role === 'admin'
            || (int) $tenancy->user_id === (int) $viewer->user_id
            || ($viewer->isOwner() && $tenancy->room && (int) $tenancy->room->owner_id === (int) $viewer->user_id);
        abort_unless($allowed, 403);

        $start = $tenancy->start_date;
        $end = $tenancy->end_date;

        return view('contracts.show', [
            'contract' => $tenancy,
            'terms' => $tenancy->contractTerms(),   // saved copy, or the landlord's template for older contracts
            'code' => AdminTenantController::code($tenancy->tenant_id),
            'tenant' => $tenancy->user,
            'room' => $tenancy->room,
            'landlord' => $tenancy->room?->owner,
            'months' => $start && $end ? max(1, (int) round($start->floatDiffInMonths($end))) : null,
            'backRoute' => $viewer->homeRoute(),
        ]);
    }
}
