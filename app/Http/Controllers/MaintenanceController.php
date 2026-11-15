<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminMaintenanceController;
use App\Models\MaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Replaces maintenance.php (the landlord dashboard's "Maintenance" link): the latest requests
 * and a count per status. Shows only requests for the logged-in landlord's own rooms.
 * New: the landlord moves a request along (Pending → In Progress → Completed) and can write a short reply.
 */
class MaintenanceController extends Controller
{
    /** Status order of the old database column (Pending, In Progress, Completed). */
    private const STATUS_ORDER = "CASE status WHEN 'Pending' THEN 1 WHEN 'In Progress' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END";

    /** Choices of the filter above the table (?status=...). */
    public const FILTERS = ['Pending', 'In Progress', 'Completed'];

    public function index(Request $request)
    {
        $ownRequests = fn () => MaintenanceRequest::whereHas(
            'room', fn ($q) => $q->where('owner_id', $request->user()->user_id)
        );

        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : null;

        // No filter: same as oopPHP::get_maintenance_requests(), the 8 newest.
        // With a filter: every request with that status, newest first.
        $requests = $ownRequests()
            ->with(['room:room_id,room_name', 'tenant:user_id,name'])
            ->when($filter, fn ($q) => $q->where('status', $filter))
            ->orderByDesc('submitted_at')
            ->orderByDesc('request_id')
            ->when(! $filter, fn ($q) => $q->limit(8))
            ->get(['request_id', 'tenant_id', 'room_id', 'description', 'status', 'submitted_at', 'resolved_at', 'landlord_note']);

        // Same as oopPHP::get_maintenance_status(): how many per status.
        $statusCounts = $ownRequests()
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')
            ->orderByRaw(self::STATUS_ORDER)
            ->get();

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('landlord.maintenance', [
            'maintenanceRequests' => $requests,
            'maintenanceStatus' => $statusCounts,
            'filter' => $filter,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }

    /**
     * POST /landlord/maintenance/status: same rules as the admin's button
     * (Pending → In Progress → Completed, a completed request stays completed), plus an optional reply.
     * Only for requests in the landlord's own rooms.
     */
    public function updateStatus(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'request_id' => ['required', 'integer'],
            'status' => ['required', 'in:In Progress,Completed'],
            'landlord_note' => ['nullable', 'string', 'max:500'],
        ], [
            'request_id.required' => 'Request not found.',
            'request_id.integer' => 'Request not found.',
            'status.required' => 'Please choose a status.',
            'status.in' => 'Unknown status.',
            'landlord_note.max' => 'The reply must be 500 characters or fewer.',
        ]);
        // The page's filter travels with the form, so we can go back to the same list.
        $filter = in_array($request->input('filter'), self::FILTERS, true) ? $request->input('filter') : null;
        if ($v->fails()) {
            return $this->back('err:' . $v->errors()->first(), $filter);
        }

        $req = MaintenanceRequest::with('room:room_id,owner_id')->find((int) $request->input('request_id'));
        if (! $req) {
            return $this->back('err:Request not found.', $filter);
        }
        // Another landlord's request: not allowed.
        abort_unless($req->room && (int) $req->room->owner_id === (int) $request->user()->user_id, 403);

        $new = $request->input('status');
        $code = AdminMaintenanceController::code($req->request_id);
        if ($req->status === 'Completed' && $new !== 'Completed') {
            return $this->back("err:{$code} is already completed.", $filter);
        }

        $note = trim((string) $request->input('landlord_note', ''));
        $wasCompleted = $req->status === 'Completed';
        $req->forceFill([
            'status' => $new,
            // Keep the first completion time if only the reply changes.
            'resolved_at' => $new === 'Completed' ? ($wasCompleted && $req->resolved_at ? $req->resolved_at : now()) : null,
            'landlord_note' => $note === '' ? null : $note,
        ])->save();

        return $this->back($wasCompleted ? "Reply on {$code} saved." : "{$code} marked {$new}.", $filter);
    }

    private function back(string $msg, ?string $filter = null): RedirectResponse
    {
        return redirect()->route('landlord.maintenance', $filter ? ['status' => $filter] : [])->with('msg', $msg);
    }
}
