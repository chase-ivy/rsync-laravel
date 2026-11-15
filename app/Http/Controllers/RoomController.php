<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\RoomFilters;
use Illuminate\Http\Request;

/**
 * Replaces rooms.php (all rooms) and room_detail.php (one room).
 */
class RoomController extends Controller
{
    /** GET /rooms — every listed room, with the same filters as the home page (see RoomFilters). rooms.php?name=... still works. */
    public function index(Request $request)
    {
        $browse = RoomFilters::browse($request);

        return view('rooms.index', $browse + [
            'total' => $browse['rooms']->count(),
            'mapRooms' => RoomFilters::mapPins($browse['rooms']),
        ]);
    }

    /** GET /rooms/suggest?q=lo — suggestions for the keyword box while typing (JSON). */
    public function suggest(Request $request)
    {
        $q = $request->query('q');

        return response()->json(RoomFilters::suggest(is_string($q) ? $q : ''));   // ?q[]=x must not crash
    }

    /** GET /rooms/{id} — like room_detail.php?id=... ; unknown room → back to the list. */
    public function show(string $id)
    {
        $room = ctype_digit($id) ? Room::withListingInfo()->find((int) $id) : null;

        if (! $room) {
            return redirect()->route('rooms.index');
        }

        $images = $room->images()->orderBy('image_id')->get();
        return view('rooms.show', [
            'room' => $room,
            'images' => $images,
            // The occupant count is not shown to the public any more; it only decides "Room Full".
            'full' => (int) $room->current_occupants >= max(1, (int) ($room->max_occupants ?? 1)),
        ]);
    }
}