<?php

namespace App\Models;

use App\Support\ContractDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A landlord's own contract format (one row per landlord / sublessor).
 * New contracts copy these values when a tenant is assigned (see Tenancy::contractTerms()).
 */
class ContractTemplate extends Model
{
    protected $table = 'contract_templates';

    protected $fillable = [
        'user_id',
        'repairs_policy',
        'deposit',
        'advance_months',
        'utilities_note',
        'terms',
        'house_rules',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'deposit' => 'float',
            'advance_months' => 'integer',
            'terms' => 'array',
            'house_rules' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * The terms a landlord uses right now: their saved template, or the standard format
     * when they haven't saved one yet.
     */
    public static function termsFor(?User $owner): array
    {
        $template = $owner ? self::where('user_id', $owner->user_id)->first() : null;

        return $template ? $template->toTerms() : ContractDefaults::terms();
    }

    /** This template as a plain list of contract terms. */
    public function toTerms(): array
    {
        return [
            'repairs_policy' => $this->repairs_policy ?: ContractDefaults::DEFAULT_REPAIRS,
            'deposit' => $this->deposit,
            'advance_months' => $this->advance_months,
            'utilities_note' => $this->utilities_note,
            'terms' => $this->terms ?? [],
            'house_rules' => $this->house_rules ?? [],
        ];
    }
}
