<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of `maintenance_requests`. Note: tenant_id points to users.user_id. */
class MaintenanceRequest extends Model
{
    protected $table = 'maintenance_requests';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'room_id',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'room_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /** The user who submitted the request. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
}