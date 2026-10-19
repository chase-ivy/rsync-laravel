<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomApplication;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Apply" buttons on the home page and the room page (oopPHP::apply_for_room()).
 * Answers with the same JSON as before: {"status": "success"|"error", "message": "..."}.
 */
class RoomApplicationController extends Controller
{
    /** POST /rooms/apply  (room_id in the form data) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('You must be logged in');
        }
        if ($user->role !== 'tenant') {
            return $this->error('Only tenants can apply');
        }
        // New rule (email verification): look around freely, but verify before applying.
        if (! $user->hasVerifiedEmail()) {
            return $this->error('Please verify your email first. Check your inbox for the link.');
        }

                // Business rule: a tenant rents one room at a time.
        if ($current = $user->activeTenancy()) {
            $name = $current->room?->room_name ?? 'a room';
            return $this->error("You already have a room ({$name}). You can apply for another room after your current stay ends.");
        }

        $roomId = $request->input('room_id');
        if (! ctype_digit((string) $roomId) || ! Room::whereKey((int) $roomId)->exists()) {
            return $this->error('Room not found');
        }

        $alreadyApplied = RoomApplication::where('user_id', $user->user_id)
            ->where('room_id', (int) $roomId)
            ->where('status', 'Pending')
            ->exists();
        if ($alreadyApplied) {
            return $this->error('You already applied for this room');
        }

        try {
            RoomApplication::create([
                'user_id' => $user->user_id,
                'room_id' => (int) $roomId,
                'status' => 'Pending',
                'applied_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The table allows one application per user and room (an older, non-pending one exists).
            // The old app crashed here; now the user gets a clear message instead.
            return $this->error('You already applied for this room');
        }

        return response()->json(['status' => 'success', 'message' => 'Application submitted successfully']);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message]);
    }
}