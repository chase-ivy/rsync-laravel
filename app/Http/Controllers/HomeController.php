<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\RoomFilters;
use Illuminate\Http\Request;

/**
 * Replaces index.php (home page + the live search box).
 */
class HomeController extends Controller
{
    /** GET / (with optional filters, see RoomFilters) — or GET /?search=... for the old live search (JSON), like index.php?search=... */
    public function index(Request $request)
    {
        if ($request->has('search')) {
            $text = $request->query('search');

            return response()->json($this->search(is_string($text) ? $text : ''));   // ?search[]=x must not crash
        }

        // Listed rooms (full rooms are hidden) that match the filters in the address bar.
        $browse = RoomFilters::browse($request);
        $rooms = $browse['rooms'];

        return view('home', $browse + [
            // Available first, then newest. The page shows the first few as cards; /rooms has the full list.
            'cards' => $rooms->sortBy(fn (Room $r) => $r->status === 'Available' ? 0 : 1)->take(12)->values(),
            'total' => $rooms->count(),
            // Map pins: every matching room that has a pin (also the ones beyond the 6 cards).
            'mapRooms' => RoomFilters::mapPins($rooms),
        ]);
    }

    /** Same query as the old ?search= block: name, description or price contains the text (new: or the address). */
    private function search(string $text): array
    {
        $like = '%' . mb_strtolower(trim($text)) . '%';

               return Room::withListingInfo()
            ->withOpenSlots()   // full rooms are not shown in the search either
            ->where(function($q) use ($like) {
                // MariaDB ignored upper/lower case; LOWER() keeps that on PostgreSQL.
                $q->whereRaw('LOWER(room_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$like])
                    ->orWhereRaw('CAST(price AS TEXT) LIKE ?', [$like]);
            })
            ->orderBy('status')
            ->orderBy('room_id')   // the old query left ties unsorted; this keeps them in a fixed order
            ->limit(10)
            ->get()
            ->map(fn (Room $r) => [
                'room_id' => $r->room_id,
                'room_name' => $r->room_name,
                'price' => $r->price,
                'status' => $r->status,
                'current_occupants' => $r->current_occupants,
                'max_occupants' => $r->max_occupants,
                'cover_image' => $r->cover_image,
            ])
            ->all();
    }
}