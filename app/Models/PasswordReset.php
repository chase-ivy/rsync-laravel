<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of `password_resets` = a 6-digit reset code (RSYNC's own table). */
class PasswordReset extends Model
{
    protected $table = 'password_resets';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'used',
    ];

    protected $hidden = [
        'otp',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}