<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The landlord dashboard's tenant and applicant windows (oopPHP::assign_tenant, remove_tenant,
 * get_room_tenants, get_room_applications, verify_application, reject_application).
 * Answers with the same JSON as before. Every action only works on the landlord's own rooms.
 */
class LandlordTenantController extends Controller
{
    /** POST /landlord/tenants/assign */
    public function assign(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return $this->error('Room not found');
        }

        $tenant = User::whereKey((int) $request->input('user_id'))->where('role', 'tenant')->first();
        if (! $tenant) {
            return $this->error('Tenant not found');
        }

        $start = (string) $request->input('start_date', '');
        $end = (string) $request->input('end_date', '');

        return DB::transaction(function () use ($room, $tenant, $start, $end) {
            $room = Room::whereKey($room->room_id)->lockForUpdate()->first();   // two clicks at once can't overfill the room
            $current = $room->tenancies()->where('status', 'Active')->count();

            // Same checks and messages as before, in the same order.
            if ($current >= $room->max_occupants) {
                return $this->error("Room is full ({$current}/{$room->max_occupants} slots)");
            }
            if ($start === '' || $end === '') {
                return $this->error('Start and end date are required.');
            }
            if (! $this->isDate($start) || ! $this->isDate($end)) {
                return $this->error('Please enter valid dates.');
            }
            if ($end <= $start) {
                return $this->error('End date must be after start date.');
            }

                        // Business rule: a tenant rents one room at a time (checked after the old checks, inside the lock).
            if ($existing = $tenant->activeTenancy()) {
                $where = $existing->room_id === $room->room_id ? 'this room' : ($existing->room?->room_name ?? 'another room');
                return $this->error("{$tenant->name} already has a room ({$where}). Remove them there first.");
            }

            Tenancy::create([
                'user_id' => $tenant->user_id,
                'room_id' => $room->room_id,
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'Active',
            ]);

            $room->update(['status' => $current + 1 >= $room->max_occupants ? 'Occupied' : 'Available']);

            return response()->json(['status' => 'success']);
        });
    }

    /** POST /landlord/tenants/remove — the tenant "Left": end date becomes today if it was later. */
    public function remove(Request $request): JsonResponse
    {
        $tenancy = Tenancy::whereKey((int) $request->input('tenant_id'))->first();
        if (! $tenancy || ! $this->ownRoom($request, $tenancy->room_id)) {
            return $this->error('Tenant not found');
        }

        $today = now()->startOfDay();
        $tenancy->update([
            'status' => 'Left',
            'end_date' => $tenancy->end_date->lessThan($today) ? $tenancy->end_date : $today,
        ]);

        // Same rule as before: any tenant left → Occupied, nobody left → Available.
        $remaining = Tenancy::where('room_id', $tenancy->room_id)->where('status', 'Active')->count();
        Room::whereKey($tenancy->room_id)->update(['status' => $remaining > 0 ? 'Occupied' : 'Available']);

        return response()->json(['status' => 'removed']);
    }

    /** POST /landlord/rooms/tenants — list for the Remove Tenant window. */
    public function roomTenants(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return response()->json([]);
        }

        return response()->json(
            Tenancy::with('user:user_id,name')
                ->where('room_id', $room->room_id)
                ->where('status', 'Active')
                ->orderBy('start_date')
                ->orderBy('tenant_id')
                ->get()
                ->map(fn (Tenancy $t) => [
                    'tenant_id' => $t->tenant_id,
                    'start_date' => $t->start_date->format('Y-m-d'),
                    'end_date' => $t->end_date->format('Y-m-d'),
                    'name' => $t->user->name,
                    'user_id' => $t->user_id,
                ])
        );
    }

    /** POST /landlord/rooms/applicants — list for the Applicants window (newest first). */
    public function applicants(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return response()->json([]);
        }

        return response()->json(
            RoomApplication::with('user:user_id,name,email')
                ->where('room_id', $room->room_id)
                ->orderByDesc('applied_at')
                ->orderByDesc('application_id')
                ->get()
                ->map(fn (RoomApplication $a) => [
                    'application_id' => $a->application_id,
                    'user_id' => $a->user_id,
                    'status' => $a->status,
                    'applied_at' => $a->applied_at?->format('Y-m-d H:i:s'),
                    'name' => $a->user->name,
                    'email' => $a->user->email,
                ])
        );
    }

    /** POST /landlord/applications/verify */
    public function verify(Request $request): JsonResponse
    {
        return $this->decide($request, 'Verified');
    }

    /** POST /landlord/applications/reject */
    public function reject(Request $request): JsonResponse
    {
        return $this->decide($request, 'Rejected');
    }

    // ---------------------------------------------------------------- helpers

    private function decide(Request $request, string $status): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return $this->error('Unauthorized');
        }

        // Security fix: the application must really be for this room (the old code didn't check).
        $updated = RoomApplication::whereKey((int) $request->input('application_id'))
            ->where('room_id', $room->room_id)
            ->update(['status' => $status]);

        return $updated ? response()->json(['status' => 'success']) : $this->error('Application not found');
    }

    private function ownRoom(Request $request, mixed $roomId): ?Room
    {
        if (! ctype_digit((string) $roomId)) {
            return null;
        }

        return Room::whereKey((int) $roomId)->where('owner_id', $request->user()->user_id)->first();
    }

    private function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $value);

        return $d && $d->format('Y-m-d') === $value;
    }

    private function error(string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message]);
    }
}