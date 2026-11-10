<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of `reports`. */
class Report extends Model
{
    protected $table = 'reports';

    protected $primaryKey = 'report_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'room_id',
        'subject',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'room_id' => 'integer',
            'created_at' => 'datetime',
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