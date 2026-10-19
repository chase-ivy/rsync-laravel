<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * RSYNC user — mapped to the existing `users` table.
 *
 * Differences from Laravel's default User model (all required by the RSYNC schema):
 * - primary key is `user_id` (not `id`)
 * - there is no `updated_at` column, so Laravel's automatic timestamps are off
 *   (`created_at` is filled by the database default)
 * - there is no `remember_token` column, so "remember me" is switched off
 *
 * MustVerifyEmail (new feature): new users get a link by email; until they click it,
 * email_verified_at is empty and routes with the 'verified' middleware are blocked.
 * (The old `verified` column is something else: the admin approving a user — see the Admin Dashboard.)
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'age',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'verified' => 'boolean',
            'verification_rejected_at' => 'datetime',
            'business_permit_uploaded_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** No remember_token column: tell Laravel not to use one. */
    public function getRememberTokenName()
    {
        return '';
    }

    /** Name of the page this user lands on after login (same rules as oopPHP::login()). */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'landlord', 'sublessor' => 'landlord.dashboard',   // sublessors also rent out rooms
            'tenant' => 'tenant.dashboard',
            default => 'home',
        };
    }

    /** Landlords and sublessors both rent out rooms and use the same dashboard. */
    public function isOwner(): bool
    {
        return in_array($this->role, ['landlord', 'sublessor'], true);
    }

    /** Web address of the profile picture, or null when there is none. */
    public function photoUrl(): ?string
    {
        return $this->profile_photo ? \App\Support\RoomPhotos::url($this->profile_photo) : null;
    }

    /** Up to two letters for the round placeholder when there is no picture ("Kobe Bryant" → "KB"). */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(array_filter($parts), 0, 2));

        return implode('', $letters) ?: '?';
    }

    /** Users the admin may approve or reject: not admins, and landlords only once they uploaded a business permit. */
    public function scopeReviewable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('role', '!=', 'admin')
            ->where(fn ($q) => $q->where('role', '!=', 'landlord')->orWhereNotNull('business_permit'));
    }

    /** Business permit review: none (not uploaded), pending, approved or rejected. */
    public function permitStatus(): string
    {
        return match (true) {
            ! $this->business_permit => 'none',
            (bool) $this->verified => 'approved',
            $this->verification_rejected_at !== null => 'rejected',
            default => 'pending',
        };
    }

    /** Rooms this user owns as a landlord. */
    public function ownedRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'owner_id', 'user_id');
    }

    /** Rental contracts of this user (rows in `tenants`). */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class, 'user_id', 'user_id');
    }

    /** The room this user currently rents (an Active contract), or null. A tenant rents one room at a time. */
    public function activeTenancy(): ?Tenancy
    {
        return $this->tenancies()->where('status', 'Active')->with('room:room_id,room_name')->first();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RoomApplication::class, 'user_id', 'user_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'tenant_id', 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id', 'user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id', 'user_id');
    }
}
