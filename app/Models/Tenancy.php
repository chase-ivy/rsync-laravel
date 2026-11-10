<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of `tenants` = one rental contract (a user staying in a room for a period).
 * Named "Tenancy" so it isn't confused with users whose role is "tenant".
 */
class Tenancy extends Model
{
    protected $table = 'tenants';

    protected $primaryKey = 'tenant_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'room_id',
        'start_date',
        'end_date',
        'status',
        'payment_status',
    ];

    /** Choices for "Update Tenant Status" (admin Tenants page, from tenants.php). */
    public const PAYMENT_STATUSES = ['Registered', 'Paid', 'On-Time', 'Delayed'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'room_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
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