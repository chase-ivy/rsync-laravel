<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\RoomPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Replaces landlord_dashboard.php: the room table, Add / Edit / Delete room and room photos
 * (oopPHP::add_room, update_room, delete_room, get_room_images, delete_image).
 *
 * Every action only works on rooms the logged-in landlord owns.
 */
class LandlordController extends Controller
{
    /** Most photos one room can have (same as before). */
    public const MAX_IMAGES = 8;

    /** GET /landlord/dashboard */
    public function index(Request $request)
    {
        $landlord = $request->user();

        $rooms = Room::withListingInfo()
            ->where('owner_id', $landlord->user_id)
            ->with(['images' => fn ($q) => $q->orderBy('image_id')])
            ->orderByDesc('created_at')
            ->orderByDesc('room_id')
            ->get();

        // Active tenants per room, for the "Tenants" column.
        $tenantMap = Tenancy::with('user:user_id,name')
            ->whereIn('room_id', $rooms->pluck('room_id'))
            ->where('status', 'Active')
            ->orderBy('tenant_id')
            ->get()
            ->groupBy('room_id');

        // Everyone with the tenant role, for the "Assign Tenant" list (same as before).
        $tenantUsers = User::where('role', 'tenant')->orderBy('user_id')->get(['user_id', 'name']);

        $msg = $request->session()->pull('msg');
        $isErr = $msg && str_starts_with($msg, 'err:');

        return view('landlord.dashboard', [
            'rooms' => $rooms,
            'tenantMap' => $tenantMap,
            'tenantUsers' => $tenantUsers,
            'msg' => $isErr ? substr($msg, 4) : $msg,
            'isErr' => $isErr,
            'uploadStatus' => $request->session()->pull('upload_status'),
        ]);
    }

    /** POST /landlord/rooms — "Save Room" in the Add Room window. */
    public function store(Request $request): RedirectResponse
    {
        if ($error = $this->validateRoom($request)) {
            return $this->back('err:' . $error);
        }

        $room = Room::create([
            'owner_id' => $request->user()->user_id,
            'room_name' => $request->input('room_name'),
            'price' => (int) $request->input('price'),
            'description' => $request->input('description'),
            'status' => 'Available',
            'max_occupants' => max(1, (int) $request->input('max_occupants')),
        ]);

        $saved = $this->saveImages($room, $request->file('images', []), self::MAX_IMAGES);
        if ($saved > 0) {
            $request->session()->put('upload_status', "{$saved} image(s) stored successfully.");
        }

        return $this->back('Room added successfully.');
    }

    /** POST /landlord/rooms/update — "Update Room" in the Edit Room window. New photos are added to the old ones. */
    public function update(Request $request): RedirectResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return $this->back('err:Failed to update. You can only change your own rooms.');
        }
        if ($error = $this->validateRoom($request)) {
            return $this->back('err:' . $error);
        }

        $room->update([
            'room_name' => $request->input('room_name'),
            'price' => (int) $request->input('price'),
            'description' => $request->input('description'),
            'max_occupants' => max(1, (int) $request->input('max_occupants')),
        ]);

        $slots = max(0, self::MAX_IMAGES - $room->images()->count());
        $saved = $this->saveImages($room, $request->file('images', []), $slots);
        if ($saved > 0) {
            $request->session()->put('upload_status', "{$saved} new image(s) added.");
        }

        return $this->back('Room updated.');
    }

    /** POST /landlord/rooms/delete — answers {"status":"deleted"} like before. */
    public function destroy(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $room->delete();   // its photos, tenants and applications are removed by the database (ON DELETE CASCADE)

        return response()->json(['status' => 'deleted']);
    }

    /** POST /landlord/rooms/images — the photos shown in the Edit Room window. */
    public function images(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));

        return response()->json($room
            ? $room->images()->orderBy('image_id')->get(['image_id', 'room_id', 'filename', 'sort_order'])
            : []);
    }

    /** POST /landlord/rooms/images/delete — the ✕ on a photo. */
    public function destroyImage(Request $request): JsonResponse
    {
        $room = $this->ownRoom($request, $request->input('room_id'));
        if (! $room) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $image = $room->images()->whereKey((int) $request->input('image_id'))->first();
        if (! $image) {
            return response()->json(['status' => 'error', 'message' => 'Image not found']);
        }

        $image->delete();
        RoomPhotos::delete($image->filename);   // fine if the file is already gone

        // Renumber so the first remaining photo is the cover (sort_order 0).
        $remaining = $room->images()->orderBy('image_id')->get();   // images() is already sorted by sort_order
        foreach ($remaining as $order => $img) {
            $img->update(['sort_order' => $order]);
        }

        return response()->json(['status' => 'deleted', 'remaining' => $remaining->count()]);
    }

    // ---------------------------------------------------------------- helpers

    /** The room, if it exists and belongs to the logged-in landlord. */
    private function ownRoom(Request $request, mixed $roomId): ?Room
    {
        if (! ctype_digit((string) $roomId)) {
            return null;
        }

        return Room::whereKey((int) $roomId)->where('owner_id', $request->user()->user_id)->first();
    }

    /** Server-side checks for the Add / Edit form. Returns the first problem, or null. */
    private function validateRoom(Request $request): ?string
    {
        $v = Validator::make($request->all(), [
            'room_name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'max_occupants' => ['required', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'images' => ['array', 'max:' . self::MAX_IMAGES],
            // Security fix: the old page saved any file type; now only real pictures up to 5 MB.
            'images.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ], [
            'images.*.image' => 'Only picture files can be uploaded.',
            'images.*.mimes' => 'Pictures must be JPG, PNG, GIF or WEBP.',
            'images.*.max' => 'Each picture must be 5 MB or smaller.',
        ]);

        return $v->fails() ? $v->errors()->first() : null;
    }

    /**
     * Saves up to $limit uploaded photos to public/images and adds them after the room's existing photos.
     * File names follow the old pattern: uniqid() + "_" + cleaned original name.
     */
        private function saveImages(Room $room, array $files, int $limit): int
    {
        $files = array_slice(array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid())), 0, $limit);
        if (! $files) {
            return 0;
        }

        // New photos go after the existing ones (the first photo of a room is the cover, sort_order 0).
        $max = DB::table('room_images')->where('room_id', $room->room_id)->max('sort_order');
        $next = $max === null ? 0 : (int) $max + 1;

        $saved = 0;
        foreach ($files as $file) {
            $name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file->getClientOriginalName()));
            try {
                RoomPhotos::store($file, $name);
            } catch (\Throwable $e) {
                report($e);   // written to the log; the room itself is still saved
                continue;
            }
            RoomImage::create(['room_id' => $room->room_id, 'filename' => $name, 'sort_order' => $next + $saved]);
            $saved++;
        }

        return $saved;
    }
    private function back(string $msg): RedirectResponse
    {
        return redirect()->route('landlord.dashboard')->with('msg', $msg);
    }
}