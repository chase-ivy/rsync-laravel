<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of `transactions`. Its key is text like "202605-RM01", not an auto-number. */
class Transaction extends Model
{
    protected $table = 'transactions';

    protected $primaryKey = 'transaction_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'type',
        'amount',
        'date',
        'status',
        'user_id',
    ];

    /** Choices for "Record Transaction" (the first two are the types already in the old data). */
    public const TYPES = ['Rental Payment', 'Maintenance Fee', 'Deposit', 'Partial Payment', 'Other'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
        ];
    }

    /** The tenant this transaction belongs to (empty for the old imported rows). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Next ID in the old format: year+month of the date, "-RM", then a 2-digit number
     * for that month — e.g. 202605-RM01, 202605-RM02 …
     */
    public static function nextId(string $date): string
    {
        $prefix = date('Ym', strtotime($date)) . '-RM';
        $used = static::where('transaction_id', 'like', $prefix . '%')->pluck('transaction_id')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($used + 1), 2, '0', STR_PAD_LEFT);
    }
}