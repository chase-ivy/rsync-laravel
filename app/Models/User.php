<?php

namespace App\Models;

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
class User extends Authenticatable
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
}