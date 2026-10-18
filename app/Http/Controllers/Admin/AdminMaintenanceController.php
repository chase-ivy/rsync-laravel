<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces the demo maintainance.php: every maintenance request (all rooms),
 * buttons to move it along, and the Maintenance Status Report.
 */
class AdminMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $requests = MaintenanceRequest::with(['tenant:user_id,name', 'room:room_id,room_name'])
            ->orderByRaw("CASE status WHEN 'Pending' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('request_id')
            ->get();

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('admin.maintenance', [
            'requests' => $requests,
            'total' => $requests->count(),
            'completed' => $requests->where('status', 'Completed')->count(),
            'inProgress' => $requests->where('status', 'In Progress')->count(),
            'pending' => $requests->where('status', 'Pending')->count(),
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /** POST /admin/maintenance/status — Pending → In Progress → Completed. */
    public function updateStatus(Request $request): RedirectResponse
    {
        if (! in_array($request->input('status'), ['In Progress', 'Completed'], true)) {
            return $this->back('err:Unknown status.');
        }

        $req = MaintenanceRequest::find((int) $request->input('request_id'));
        if (! $req) {
            return $this->back('err:Request not found.');
        }
        if ($req->status === 'Completed') {
            return $this->back('err:' . self::code($req->request_id) . ' is already completed.');
        }

        $new = $request->input('status');
        $req->update([
            'status' => $new,
            'resolved_at' => $new === 'Completed' ? now() : null,
        ]);

        return $this->back(self::code($req->request_id) . " marked {$new}.");
    }

    /** Request number shown on the page, e.g. 3 → "MR-003" (like the old demo). */
    public static function code(int $id): string
    {
        return 'MR-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('admin.maintenance')->with('msg', $msg);
    }
}