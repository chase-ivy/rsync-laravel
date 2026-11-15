<?php

namespace App\Models;

use App\Support\ContractDefaults;
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
        // Copy of the contract terms ("snapshot"), saved when the tenant is assigned.
        'monthly_rent',
        'repairs_policy',
        'deposit',
        'advance_months',
        'utilities_note',
        'terms',
        'house_rules',
        'terms_updated_at',
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
            'monthly_rent' => 'float',
            'deposit' => 'float',
            'advance_months' => 'integer',
            'terms' => 'array',
            'house_rules' => 'array',
            'terms_updated_at' => 'datetime',
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

    /** True when this contract has its own saved copy of the terms (contracts made before that feature don't). */
    public function hasSnapshot(): bool
    {
        return $this->terms !== null || $this->house_rules !== null || $this->repairs_policy !== null;
    }

    /**
     * The terms shown on the contract page:
     * 1. the copy saved on this contract, if there is one;
     * 2. otherwise (older contracts) the landlord's current template, or the standard format;
     * the rent comes from the copy, or else from the room's current price.
     */
    public function contractTerms(): array
    {
        if ($this->hasSnapshot()) {
            $terms = [
                'repairs_policy' => $this->repairs_policy ?: ContractDefaults::DEFAULT_REPAIRS,
                'deposit' => $this->deposit,
                'advance_months' => $this->advance_months,
                'utilities_note' => $this->utilities_note,
                'terms' => $this->terms ?? [],
                'house_rules' => $this->house_rules ?? [],
            ];
        } else {
            $terms = ContractTemplate::termsFor($this->room?->owner);
        }

        $terms['monthly_rent'] = $this->monthly_rent ?? (float) ($this->room?->price ?? 0);

        return $terms;
    }
}
