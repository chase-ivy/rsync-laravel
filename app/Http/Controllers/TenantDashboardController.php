<?php

namespace App\Http\Controllers;

use App\Models\RoomApplication;
use App\Models\RoomImage;
use App\Models\Tenancy;
use Illuminate\Http\Request;

/**
 * Replaces tenant_dashboard.php. Only tenants get here (route uses the 'role:tenant' check).
 */
class TenantDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // "Your Applied Rooms": the tenant's Active rentals, oldest start first (same query as before).
        $bookings = Tenancy::with(['room' => fn ($q) => $q->withListingInfo()])
            ->where('user_id', $user->user_id)
            ->where('status', 'Active')
            ->orderBy('start_date')
            ->orderBy('tenant_id')
            ->get();

        // "Your Applications": newest first, with the room's name, price, description and cover photo
        // (oopPHP::get_tenant_applications()).
        $applications = RoomApplication::query()
            ->join('rooms', 'rooms.room_id', '=', 'applications.room_id')
            ->where('applications.user_id', $user->user_id)
            ->select('applications.*', 'rooms.room_name', 'rooms.price', 'rooms.description')
            ->addSelect(['cover_image' => RoomImage::select('filename')
                ->whereColumn('room_images.room_id', 'rooms.room_id')
                ->orderBy('sort_order')
                ->orderBy('image_id')
                ->limit(1)])
            ->orderByDesc('applications.applied_at')
            ->orderByDesc('applications.application_id')
            ->get();

        // Same one-time message as $_SESSION['msg'] ("err:..." = red).
        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('tenant.dashboard', [
            'bookings' => $bookings,
            'applications' => $applications,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
        ]);
    }
}