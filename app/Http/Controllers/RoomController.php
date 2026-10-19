<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

/**
 * Replaces rooms.php (all rooms) and room_detail.php (one room).
 */
class RoomController extends Controller
{
    /** GET /rooms — or /rooms?name=... like rooms.php?name=... */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('name', ''));

       
                $rooms = Room::withListingInfo()
            ->withOpenSlots()   // full rooms are not listed
            ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(room_name) LIKE ?', ['%' . mb_strtolower($search) . '%'])->orderBy('room_id'))
            ->when($search === '', fn ($q) => $q->orderByDesc('created_at'))
            ->get();

        return view('rooms.index', ['rooms' => $rooms, 'search' => $search]);
    }

    /** GET /rooms/{id} — like room_detail.php?id=... ; unknown room → back to the list. */
    public function show(string $id)
    {
        $room = ctype_digit($id) ? Room::withListingInfo()->find((int) $id) : null;

        if (! $room) {
            return redirect()->route('rooms.index');
        }

        $images = $room->images()->orderBy('image_id')->get();
        $max = (int) ($room->max_occupants ?? 1);
        $current = (int) $room->current_occupants;

        return view('rooms.show', [
            'room' => $room,
            'images' => $images,
            'max' => $max,
            'current' => $current,
            'pct' => $max > 0 ? (int) round($current / $max * 100) : 0,
            'full' => $current >= $max,
        ]);
    }
}