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

    /** New rooms are priced per person unless the landlord picks "whole room". */
    protected $attributes = [
        'price_type' => 'per_person',
    ];

    protected $fillable = [
        'owner_id',
        'room_name',
        'price',
        'description',
        'status',
        'max_occupants',
        'latitude',
        'longitude',
        'address',
        'amenities',
        'price_type',
    ];

    /** How the price is meant: key saved in the database => label in the Add / Edit Room window. */
    public const PRICE_TYPES = [
        'per_person' => 'Per person (each tenant pays this)',
        'whole_room' => 'For the whole room (split between tenants)',
    ];

    public const DEFAULT_PRICE_TYPE = 'per_person';

    /**
     * Amenities a landlord can tick in the Add / Edit Room window.
     * key (saved in the database) => [label shown on the page, Boxicons icon].
     * To add one, add a line here; nothing else needs to change.
     */
    public const AMENITIES = [
        'wifi' => ['WiFi', 'bx-wifi'],
        'own_cr' => ['Own CR / Bathroom', 'bx-bath'],
        'shared_cr' => ['Shared CR', 'bx-group'],
        'kitchen' => ['Kitchen access', 'bx-restaurant'],
        'laundry' => ['Laundry area', 'bx-basket'],
        'water' => ['Water included', 'bx-water'],
        'electricity' => ['Electricity included', 'bx-bolt-circle'],
        'aircon' => ['Air-conditioning', 'bx-wind'],
        'study_table' => ['Study table', 'bx-book-open'],
        'cabinet' => ['Cabinet / Closet', 'bx-cabinet'],
        'parking' => ['Parking', 'bx-car'],
        'cctv' => ['CCTV', 'bx-cctv'],
        'curfew_free' => ['Curfew-free', 'bx-time-five'],
        'pet_friendly' => ['Pet-friendly', 'bx-heart'],
    ];

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'price' => 'integer',
            'max_occupants' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'amenities' => 'array',
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


       /** Only rooms with a free slot (fewer Active tenants than max_occupants). Full rooms are hidden from the public lists. */
    public function scopeWithOpenSlots(Builder $query): Builder
    {
        return $query->whereRaw(
            "(SELECT COUNT(*) FROM tenants WHERE tenants.room_id = rooms.room_id AND tenants.status = 'Active') < COALESCE(rooms.max_occupants, 1)"
        );
    }

    /**
     * The room's amenities as [key => [label, icon]], in the order of AMENITIES.
     * Unknown keys (e.g. an amenity removed from the list later) are skipped.
     */
    public function amenityList(): array
    {
        $chosen = (array) ($this->amenities ?? []);

        return array_filter(self::AMENITIES, fn ($key) => in_array($key, $chosen, true), ARRAY_FILTER_USE_KEY);
    }

    /** True when the price is for the whole room (split between tenants). */
    public function isWholeRoomPrice(): bool
    {
        return $this->price_type === 'whole_room';
    }

    /**
     * The monthly rent of ONE tenant's contract.
     * - per_person: the room price.
     * - whole_room: the price divided by the room's capacity (max_occupants), rounded to centavos.
     *   We split by capacity, not by how many live there now, so a tenant's rent doesn't change
     *   every time a roommate moves in or out (e.g. ₱3,500 for 2 → ₱1,750.00 each).
     */
    public function perPersonRent(): float
    {
        $price = (float) ($this->price ?? 0);
        if (! $this->isWholeRoomPrice()) {
            return $price;
        }

        return round($price / max(1, (int) ($this->max_occupants ?? 1)), 2);
    }

    /** Short price text, e.g. "₱3,500 / person" or "₱3,500 / room". */
    public function priceLabel(): string
    {
        return '₱' . number_format((int) $this->price) . ($this->isWholeRoomPrice() ? ' / room' : ' / person');
    }

    /** Extra line for whole-room prices, e.g. "about ₱1,750 per person when full (2)"; null for per-person prices. */
    public function splitNote(): ?string
    {
        if (! $this->isWholeRoomPrice()) {
            return null;
        }
        $each = $this->perPersonRent();
        $text = fmod($each, 1.0) === 0.0 ? number_format($each) : number_format($each, 2);

        return 'about ₱' . $text . ' per person when full (' . max(1, (int) ($this->max_occupants ?? 1)) . ')';
    }

    /** True when the landlord has dropped a pin for this room. */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
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