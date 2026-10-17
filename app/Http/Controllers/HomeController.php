<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

/**
 * Replaces index.php (home page + the live search box).
 */
class HomeController extends Controller
{
    /** GET / — or GET /?search=... for the live search (JSON), like index.php?search=... */
    public function index(Request $request)
    {
        if ($request->has('search')) {
            return response()->json($this->search((string) $request->query('search')));
        }

        // Same as index.php: all rooms (newest first), counts, and 6 featured (available first).
        $all = Room::withListingInfo()->orderByDesc('created_at')->get();
        $available = $all->where('status', 'Available');

        return view('home', [
            'total' => $all->count(),
            'available' => $available->count(),
            'occupied' => $all->count() - $available->count(),
            'featured' => $available->concat($all->where('status', '!==', 'Available'))->take(6),
        ]);
    }

    /** Same query as the old ?search= block: name, description or price contains the text. */
    private function search(string $text): array
    {
        $like = '%' . mb_strtolower(trim($text)) . '%';

        return Room::withListingInfo()
            ->where(function ($q) use ($like) {
                // MariaDB ignored upper/lower case; LOWER() keeps that on PostgreSQL.
                $q->whereRaw('LOWER(room_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
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