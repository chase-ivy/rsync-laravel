<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of `applications` = a user applying for a room.
 * Named "RoomApplication" so it isn't confused with Laravel's own Application class.
 */
class RoomApplication extends Model
{
    protected $table = 'applications';

    protected $primaryKey = 'application_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'room_id',
        'status',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'room_id' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
}