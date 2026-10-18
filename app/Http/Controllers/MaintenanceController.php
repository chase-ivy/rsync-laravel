<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;

/**
 * Replaces maintenance.php (the landlord dashboard's "Maintenance" link): the latest requests
 * and a count per status. Shows only requests for the logged-in landlord's own rooms.
 */
class MaintenanceController extends Controller
{
    /** Status order of the old database column (Pending, In Progress, Completed). */
    private const STATUS_ORDER = "CASE status WHEN 'Pending' THEN 1 WHEN 'In Progress' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END";

    public function index(Request $request)
    {
        $ownRequests = fn () => MaintenanceRequest::whereHas(
            'room', fn ($q) => $q->where('owner_id', $request->user()->user_id)
        );

        // Same as oopPHP::get_maintenance_requests(): the 8 newest.
        $requests = $ownRequests()
            ->orderByDesc('submitted_at')
            ->orderByDesc('request_id')
            ->limit(8)
            ->get(['request_id', 'description', 'status', 'submitted_at']);

        // Same as oopPHP::get_maintenance_status(): how many per status.
        $statusCounts = $ownRequests()
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')
            ->orderByRaw(self::STATUS_ORDER)
            ->get();

        return view('landlord.maintenance', [
            'maintenanceRequests' => $requests,
            'maintenanceStatus' => $statusCounts,
        ]);
    }
}