<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row of `rooms`. */
class Room extends Model
{
    protected $table = 'rooms';

    protected $primaryKey = 'room_id';

    public $timestamps = false; // only created_at exists; the database fills it

    protected $fillable = [
        'owner_id',
        'room_name',
        'price',
        'description',
        'status',
        'max_occupants',
    ];

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'price' => 'integer',
            'max_occupants' => 'integer',
            'created_at' => 'datetime',
        ];
    }
        /**
     * Adds `current_occupants` (active tenancies) and `cover_image` (first photo) to each room —
     * the same extra columns as oopPHP::get_rooms(), get_room() and get_room_by_id().
     */
    public function scopeWithListingInfo(Builder $query): Builder
    {
        return $query->select('rooms.*')
            ->withCount(['activeTenancies as current_occupants'])
            ->addSelect(['cover_image' => RoomImage::select('filename')
                ->whereColumn('room_images.room_id', 'rooms.room_id')
                ->orderBy('sort_order')
                ->orderBy('image_id')
                ->limit(1)]);
    }
    /** The landlord who owns the room (can be empty). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    /** Photos, cover first (sort_order 0). */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class, 'room_id', 'room_id')->orderBy('sort_order');
    }

    /** Every tenancy ever recorded for this room. */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class, 'room_id', 'room_id');
    }

    /** Only the tenancies with status 'Active' (the current occupants). */
    public function activeTenancies(): HasMany
    {
        return $this->tenancies()->where('status', 'Active');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RoomApplication::class, 'room_id', 'room_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'room_id', 'room_id');
    }
}