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
            'landlord' => 'landlord.dashboard',
            'tenant' => 'tenant.dashboard',
            default => 'home',
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