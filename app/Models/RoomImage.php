<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of `room_images`. The file itself is public/images/{filename}. */
class RoomImage extends Model
{
    protected $table = 'room_images';

    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'filename',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'room_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
}